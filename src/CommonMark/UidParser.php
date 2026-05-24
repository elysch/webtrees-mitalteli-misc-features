<?php

/**
 * mitalteli-misc-features
 * Copyright (C) 2025
 */

declare(strict_types=1);

namespace MitalteliMiscFeatures\CommonMark;

use Fisharebest\Webtrees\GedcomRecord;
use MitalteliMiscFeatures\WebtreesCompat;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use MitalteliMiscFeatures\GedcomUid;
use MitalteliMiscFeatures\Services\UidSearchService;

/**
 * Inline parser: finds #UUID# and #UUID:text# references and resolves them
 * to GedcomRecord hyperlinks.
 *
 * Syntax:
 *   #<UUID>#               → link using the record's full name
 *   #<UUID>:Custom text#   → link with custom text
 *   #<UUID>:FULLNAME#      → explicit alias for the record's full name
 *
 * Version compatibility (2.0 / 2.1 / 2.2+):
 *   Gedcom::REGEX_UID and the corrected rawGedcomFilter exist only in the
 *   custom patch — they were never merged into the official webtrees core.
 *   UidSearchService is therefore used on all versions.
 */
class UidParser implements InlineParserInterface
{
    private Tree          $tree;
    private UidSearchService $search_service;

    public function __construct(Tree $tree)
    {
        $this->tree = $tree;

        // Resolve UidSearchService from the container (registered globally in
        // MitalteliMiscFeaturesModule::boot() via bindInstance).
        // Fall back to direct instantiation if boot() has not run yet
        // (e.g. when MarkdownFactory is called before boot() completes).
        try {
            $this->search_service = WebtreesCompat::make(UidSearchService::class);
        } catch (\Throwable $e) {
            $this->search_service = new UidSearchService(
                new \Fisharebest\Webtrees\Services\TreeService(
                    new \Fisharebest\Webtrees\Services\GedcomImportService()
                )
            );
        }
    }

    /**
     * Match #<UID># or #<UID>:text#
     * Regex taken verbatim from the original patch (GedcomUid::REGEX_UID
     * replaces Gedcom::REGEX_UID which does not exist in 2.1.25 core).
     */
    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex(
            '#(' . GedcomUid::REGEX_UID . ')(?::(.+?[^\\\\]))?#'
        );
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $subm     = $inlineContext->getSubMatches();
        $uid      = $subm[0];
        $linkText = isset($subm[1]) ? preg_replace('/\\\\#/', '#', $subm[1]) : '';

        $record = $this->findRecordByUid($uid);

        if (!$record instanceof GedcomRecord) {
            return false;
        }

        $inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());
        $inlineContext->getContainer()->appendChild(new UidNode($record, $linkText));

        return true;
    }

    // -------------------------------------------------------------------------
    // Record lookup
    // -------------------------------------------------------------------------

    /**
     * Return the first record in $collection whose raw GEDCOM contains a
     * matching "1 UID …" or "1 _UID …" line.
     *
     * _? makes the underscore optional — covers both UID and _UID tags.
     */
    private function firstMatchingUidRecord(string $uid, Collection $collection): ?GedcomRecord
    {
        // Use the first 32 hex chars as the match prefix so that a 36-char
        // searched UID also matches a 38-char Ancestry UID stored in the DB
        // (both share the same first 32 chars but differ after position 32).
        $hex     = preg_replace('/[^0-9a-fA-F]/', '', $uid);
        $prefix  = substr($hex, 0, 32);
        $pattern = '/\n1 _?UID ' . $prefix . '[0-9a-fA-F]*(?:\n|$)/i';

        foreach ($collection as $record) {
            if (preg_match($pattern, $record->gedcom())) {
                return $record;
            }
        }

        return null;
    }

    private function findRecordByUid(string $uid): ?GedcomRecord
    {
        $trees = [$this->tree];
        $terms = [$uid];

        // searchIndividuals and searchFamilies use the corrected rawGedcomFilter
        // from UidSearchService (the fix is not in the official core on any version).
        // All other search methods use paginateQuery (no rawGedcomFilter).
        $searches = [
            fn () => $this->search_service->searchIndividuals($trees, $terms),
            fn () => $this->search_service->searchFamilies($trees, $terms),
            fn () => $this->search_service->searchRepositories($trees, $terms),
            fn () => $this->search_service->searchSources($trees, $terms),
            fn () => $this->search_service->searchNotes($trees, $terms),
            fn () => $this->search_service->searchLocations($trees, $terms),
            fn () => $this->search_service->searchMedia($trees, $terms),
        ];

        foreach ($searches as $search) {
            $record = $this->firstMatchingUidRecord($uid, $search());
            if ($record !== null) {
                return $record;
            }
        }

        return null;
    }
}
