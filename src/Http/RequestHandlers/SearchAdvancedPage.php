<?php

/**
 * mitalteli-misc-features
 * Copyright (C) 2025
 */

declare(strict_types=1);

namespace MitalteliMiscFeatures\Http\RequestHandlers;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Log;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\SearchService;
use Fisharebest\Webtrees\Validator;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_fill_keys;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function implode;
use function strcmp;
use function strtr;

/**
 * Extended Advanced Search page handler.
 *
 * This class is bound in the IoC container to replace the core handler:
 *   app()->bind(CoreSearchAdvancedPage::class, SearchAdvancedPage::class)
 *
 * It adds:
 *  - birth_place_options: search across BIRT + CHR + BAPM
 *  - death_place_options: search across DEAT + BURI + CREM
 *
 * Both variables are passed to the view so the extended templates can render
 * the optional "AND" modifier dropdowns.
 */
class SearchAdvancedPage implements RequestHandlerInterface
{
    use ViewResponseTrait;

    private const BIRTH_EVENTS = ['BIRT', 'CHR', 'BAPM'];
    private const DEATH_EVENTS = ['DEAT', 'BURI', 'CREM'];

    private const DEFAULT_ADVANCED_FIELDS = [
        'INDI:NAME:GIVN',
        'INDI:NAME:SURN',
        'INDI:BIRT:DATE',
        'INDI:BIRT:PLAC',
        'FAM:MARR:DATE',
        'FAM:MARR:PLAC',
        'INDI:DEAT:DATE',
        'INDI:DEAT:PLAC',
        'INDI:BURI:PLAC',
        'FATHER:NAME:GIVN',
        'FATHER:NAME:SURN',
        'MOTHER:NAME:GIVN',
        'MOTHER:NAME:SURN',
    ];

    private const OTHER_ADVANCED_FIELDS = [
        'INDI:ADOP:DATE', 'INDI:ADOP:PLAC', 'INDI:AFN',
        'INDI:BAPL:DATE', 'INDI:BAPL:PLAC', 'INDI:BAPM:DATE', 'INDI:BAPM:PLAC',
        'INDI:BARM:DATE', 'INDI:BARM:PLAC', 'INDI:BASM:DATE', 'INDI:BASM:PLAC',
        'INDI:BLES:DATE', 'INDI:BLES:PLAC', 'INDI:BURI:DATE', 'INDI:BURI:PLAC',
        'INDI:CENS:DATE', 'INDI:CENS:PLAC', 'INDI:CHAN:DATE', 'INDI:CHAN:_WT_USER',
        'INDI:CHR:DATE',  'INDI:CHR:PLAC',  'INDI:CREM:DATE', 'INDI:CREM:PLAC',
        'INDI:DSCR',
        'INDI:EMIG:DATE', 'INDI:EMIG:PLAC', 'INDI:ENDL:DATE', 'INDI:ENDL:PLAC',
        'INDI:EVEN',      'INDI:EVEN:TYPE', 'INDI:EVEN:DATE', 'INDI:EVEN:PLAC',
        'INDI:FACT',      'INDI:FACT:TYPE',
        'INDI:FCOM:DATE', 'INDI:FCOM:PLAC',
        'INDI:IMMI:DATE', 'INDI:IMMI:PLAC',
        'INDI:NAME:NICK', 'INDI:NAME:_MARNM', 'INDI:NAME:_HEB', 'INDI:NAME:ROMN',
        'INDI:NATI',      'INDI:NATU:DATE', 'INDI:NATU:PLAC',
        'INDI:NOTE',      'INDI:OCCU',
        'INDI:ORDN:DATE', 'INDI:ORDN:PLAC',
        'INDI:REFN',      'INDI:RELI',
        'INDI:RESI:DATE', 'INDI:RESI:EMAIL', 'INDI:RESI:PLAC',
        'INDI:SLGC:DATE', 'INDI:SLGC:PLAC',
        'INDI:TITL',
        'FAM:DIV:DATE',   'FAM:SLGS:DATE',  'FAM:SLGS:PLAC',
    ];

    private SearchService $search_service;

    public function __construct(SearchService $search_service)
    {
        $this->search_service = $search_service;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree           = Validator::attributes($request)->tree();
        $default_fields = array_fill_keys(self::DEFAULT_ADVANCED_FIELDS, '');
        $fields         = Validator::queryParams($request)->array('fields') ?: $default_fields;
        $modifiers      = Validator::queryParams($request)->array('modifiers');

        $fields = array_map(
            static fn (string $x): string => preg_replace('/^\s+|\s+$/uD', '', $x),
            $fields
        );

        $search_fields = array_filter($fields, static fn (string $x): bool => $x !== '');

        if ($search_fields !== []) {
            if (Auth::id() === null) {
                $fn      = static fn (string $x, string $y): string => $x . '=' . $y;
                $message = 'Advanced: ' . implode(', ', array_map($fn, array_keys($search_fields), $search_fields));
                Log::addSearchLog($message, [$tree]);
            }

            $individuals = $this->search_service->searchIndividualsAdvanced($tree, $search_fields, $modifiers);
        } else {
            $individuals = new Collection();
        }

        return $this->viewResponse('search-advanced-page', [
            'date_options'        => $this->dateOptions(),
            'fields'              => $fields,
            'field_labels'        => $this->fieldLabels(),
            'individuals'         => $individuals,
            'modifiers'           => $modifiers,
            'name_options'        => $this->nameOptions(),
            'other_fields'        => $this->otherFields($fields),
            'title'               => I18N::translate('Advanced search'),
            'tree'                => $tree,
            // Extended variables (not present in the core handler):
            'birth_place_options' => $this->birthPlaceOptions(),
            'death_place_options' => $this->deathPlaceOptions(),
        ]);
    }

    private function otherFields(array $fields): array
    {
        $default_facts = new Collection(self::OTHER_ADVANCED_FIELDS);

        $comparator = static function (string $x, string $y): int {
            $ef     = Registry::elementFactory();
            $label1 = $ef->make(strtr($x, [':DATE' => '', ':PLAC' => '', ':TYPE' => '']))->label();
            $label2 = $ef->make(strtr($y, [':DATE' => '', ':PLAC' => '', ':TYPE' => '']))->label();
            return I18N::comparator()($label1, $label2) ?: strcmp($x, $y);
        };

        return $default_facts
            ->reject(fn (string $field): bool => array_key_exists($field, $fields))
            ->sort($comparator)
            ->mapWithKeys(fn (string $fact): array => [$fact => Registry::elementFactory()->make($fact)->label()])
            ->all();
    }

    private function fieldLabels(): array
    {
        $return = [];
        foreach (array_merge(self::OTHER_ADVANCED_FIELDS, self::DEFAULT_ADVANCED_FIELDS) as $field) {
            $tmp            = strtr($field, ['MOTHER:' => 'INDI:', 'FATHER:' => 'INDI:']);
            $return[$field] = Registry::elementFactory()->make($tmp)->label();
        }
        return $return;
    }

    private function dateOptions(): array
    {
        return [
            0  => I18N::translate('Exact date'),
            1  => I18N::plural('±%s year', '±%s years', 1, I18N::number(1)),
            2  => I18N::plural('±%s year', '±%s years', 2, I18N::number(2)),
            5  => I18N::plural('±%s year', '±%s years', 5, I18N::number(5)),
            10 => I18N::plural('±%s year', '±%s years', 10, I18N::number(10)),
            20 => I18N::plural('±%s year', '±%s years', 20, I18N::number(20)),
        ];
    }

    private function nameOptions(): array
    {
        return [
            'EXACT'    => I18N::translate('Exact'),
            'BEGINS'   => I18N::translate('Begins with'),
            'CONTAINS' => I18N::translate('Contains'),
            'SDX'      => I18N::translate('Sounds like'),
        ];
    }

    private function birthPlaceOptions(): array
    {
        return [
            'OR'  => I18N::translate('Default'),
            'AND' => I18N::translate(implode(' + ', self::BIRTH_EVENTS)),
        ];
    }

    private function deathPlaceOptions(): array
    {
        return [
            'OR'  => I18N::translate('Default'),
            'AND' => I18N::translate(implode(' + ', self::DEATH_EVENTS)),
        ];
    }
}
