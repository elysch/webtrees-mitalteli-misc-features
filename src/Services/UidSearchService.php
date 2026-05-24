<?php

/**
 * mitalteli-misc-features
 * Copyright (C) 2025
 */

declare(strict_types=1);

namespace MitalteliMiscFeatures\Services;

use Closure;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Services\SearchService;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;
use MitalteliMiscFeatures\GedcomUid;

/**
 * Extends SearchService with a corrected rawGedcomFilter for webtrees 2.1 / 2.0.
 *
 * NOT used on webtrees 2.2+ because the core SearchService already contains
 * the equivalent fix (rawGedcomFilter uses Gedcom::REGEX_UID in 2.2).
 *
 * The problem in the unpatched 2.1/2.0 core:
 *   rawGedcomFilter() checks search terms against a hard-coded regex that only
 *   recognises two UID formats (UUID v4 with dashes, and 36 hex chars).
 *   38-char Ancestry UIDs are not recognised, so their _UID lines are stripped
 *   before the content search and the records are never found.
 *
 * This subclass overrides searchIndividuals() and searchFamilies() — the only
 * two methods that call rawGedcomFilter() — with an identical implementation
 * except that the UID detection regex uses GedcomUid::REGEX_UID, which covers
 * all three formats including 38-char UIDs.
 *
 * The private parent helpers (whereTrees, whereSearch, rowLimiter, *RowMapper)
 * are accessed via ReflectionMethod to avoid code duplication.
 */
class UidSearchService extends SearchService
{
    /**
     * @param array<Tree>   $trees
     * @param array<string> $search
     * @return Collection<int,\Fisharebest\Webtrees\Family>
     */
    public function searchFamilies(array $trees, array $search): Collection
    {
        $query = DB::table('families');

        $this->call('whereTrees', $query, 'f_file', $trees);
        $this->call('whereSearch', $query, 'f_gedcom', $this->uidLikeTerms($search));

        $search = array_map(
            static fn (string $x): string => I18N::language()->normalize($x),
            $search
        );

        return $query
            ->get()
            ->each($this->call('rowLimiter'))
            ->map($this->call('familyRowMapper'))
            ->filter(GedcomRecord::accessFilter())
            ->filter($this->correctedRawGedcomFilter($search));
    }

    /**
     * @param array<Tree>   $trees
     * @param array<string> $search
     * @return Collection<int,\Fisharebest\Webtrees\Individual>
     */
    public function searchIndividuals(array $trees, array $search): Collection
    {
        $query = DB::table('individuals');

        $this->call('whereTrees', $query, 'i_file', $trees);
        $this->call('whereSearch', $query, 'i_gedcom', $this->uidLikeTerms($search));

        $search = array_map(
            static fn (string $x): string => I18N::language()->normalize($x),
            $search
        );

        return $query
            ->get()
            ->each($this->call('rowLimiter'))
            ->map($this->call('individualRowMapper'))
            ->filter(GedcomRecord::accessFilter())
            ->filter($this->correctedRawGedcomFilter($search));
    }

    // -------------------------------------------------------------------------
    // UID-aware LIKE search terms
    // -------------------------------------------------------------------------

    /**
     * For UID search terms, return only the first 32 hex chars as the LIKE
     * pattern. This is needed because:
     *   - A 36-char UID and the 38-char Ancestry variant share the same first
     *     32 chars but differ after position 32.
     *   - Using the full 36-char term as a LIKE pattern would NOT match the
     *     38-char stored value (and vice versa).
     *   - Using 32 chars as the LIKE term matches BOTH variants.
     *   - correctedRawGedcomFilter then does exact mb_stripos matching with
     *     the ORIGINAL full search term to filter false positives.
     *
     * Non-UID search terms are returned unchanged.
     *
     * @param array<string> $search_terms
     * @return array<string>
     */
    private function uidLikeTerms(array $search_terms): array
    {
        $uid_regex = '/^(' . GedcomUid::REGEX_UID . ')$/i';

        return array_map(
            static function (string $term) use ($uid_regex): string {
                if (preg_match($uid_regex, $term) === 1) {
                    // Strip any dashes, then use first 32 hex chars
                    $hex = preg_replace('/[^0-9a-fA-F]/', '', $term);
                    return substr($hex, 0, 32);
                }
                return $term;
            },
            $search_terms
        );
    }

    // -------------------------------------------------------------------------
    // Corrected rawGedcomFilter (identical to parent except the UID regex)
    // -------------------------------------------------------------------------

    /**
     * Like SearchService::rawGedcomFilter() but uses GedcomUid::REGEX_UID
     * instead of the two-format regex hard-coded in the unpatched 2.1/2.0 core.
     *
     * The core only recognises:
     *   - xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx  (UUID v4, 36 chars with dashes)
     *   - [0-9A-F]{36}                           (36 hex chars, no dashes)
     *
     * GedcomUid::REGEX_UID additionally covers:
     *   - [0-9a-fA-F]{38}                        (Ancestry-style, 38 hex chars)
     *
     * When a search term matches ANY UID format, _UID lines are preserved in
     * the GEDCOM before the content search — otherwise they are stripped and
     * the record is never found.
     */
    private function correctedRawGedcomFilter(array $search_terms): Closure
    {
        $uid_regex = '/^(' . GedcomUid::REGEX_UID . ')$/i';

        return static function (GedcomRecord $record) use ($search_terms, $uid_regex): bool {
            $is_uid_search = array_filter(
                $search_terms,
                static fn (string $term): bool => preg_match($uid_regex, $term) === 1
            ) !== [];

            if ($is_uid_search) {
                // UID search: preserve _UID lines, only strip _WT_USER
                $gedcom = preg_replace('/\n\d _WT_USER .*/', '', $record->gedcom());
            } else {
                // Normal search: strip all UID/_UID/_WT_USER lines
                $gedcom = preg_replace('/\n\d (UID|_UID|_WT_USER) .*/', '', $record->gedcom());
            }

            $gedcom = preg_replace(
                '/\n\d ' . Gedcom::REGEX_TAG . '( @' . Gedcom::REGEX_XREF . '@)?/',
                '',
                $gedcom
            );

            $gedcom = I18N::language()->normalize($gedcom);

            foreach ($search_terms as $search_term) {
                if ($is_uid_search && preg_match($uid_regex, $search_term) === 1) {
                    // For UID terms: use the 32-char hex prefix so that a
                    // 36-char searched UID also matches a 38-char stored UID
                    // (both share the same first 32 hex chars).
                    // This also correctly matches the UID when mentioned in
                    // notes or other free-text fields.
                    $prefix = substr(preg_replace('/[^0-9a-fA-F]/', '', $search_term), 0, 32);
                    if (mb_stripos($gedcom, $prefix) === false) {
                        return false;
                    }
                } else {
                    // Normal search term: exact substring match
                    if (mb_stripos($gedcom, $search_term) === false) {
                        return false;
                    }
                }
            }

            return true;
        };
    }

    // -------------------------------------------------------------------------
    // Reflection helper to access private parent methods
    // -------------------------------------------------------------------------

    private function call(string $method, ...$args)
    {
        static $cache = [];

        if (!isset($cache[$method])) {
            $ref = new \ReflectionMethod(SearchService::class, $method);
            $ref->setAccessible(true);
            $cache[$method] = $ref;
        }

        return $cache[$method]->invoke($this, ...$args);
    }
}
