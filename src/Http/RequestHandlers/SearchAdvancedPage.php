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
use MitalteliMiscFeatures\WebtreesCompat;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_fill_keys;
use function array_filter;
use function array_flip;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function implode;
use function is_string;
use function method_exists;
use function mb_substr;
use function preg_match;
use function preg_replace;
use function redirect;
use function route;
use function strcmp;
use function strtr;

/**
 * Extended Advanced Search page handler.
 *
 * This class is bound in the IoC container to replace the core handler:
 *   app()->bind(CoreSearchAdvancedPage::class, SearchAdvancedPage::class)
 * (see WebtreesCompat::coreSearchAdvancedClass() for what "the core handler" is in each webtrees version).
 *
 * webtrees 2.0 - 2.2 send GET /search-advanced to this handler and POST /search-advanced to a different core
 * handler (SearchAdvancedAction), which is not replaced.  webtrees 2.3 sends both to the same controller, so
 * handle() also does the work of the POST handler (see post()).
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

    /** Fields that the 2.3 core page offers and the 2.1 / 2.2 core pages do not. Only added on webtrees 2.3. */
    private const OTHER_ADVANCED_FIELDS_V23 = [
        'INDI:EDUC',
        'INDI:PROP',
        'INDI:_TODO',
    ];

    /** Longest search value accepted (it is also written to the search log for visitors). */
    private const MAX_VALUE_LENGTH = 255;

    private SearchService $search_service;

    public function __construct(SearchService $search_service)
    {
        $this->search_service = $search_service;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // webtrees 2.3 has one controller for both verbs (the controller that this class replaces), and it
        // calls handle() for both.  In 2.0 - 2.2 a POST never gets here.
        if ($request->getMethod() === 'POST') {
            return $this->post($request);
        }

        $tree           = Validator::attributes($request)->tree();
        $default_fields = array_fill_keys(self::DEFAULT_ADVANCED_FIELDS, '');
        $fields         = $this->sanitiseFields(Validator::queryParams($request)->array('fields')) ?: $default_fields;
        $modifiers      = $this->sanitiseModifiers(Validator::queryParams($request)->array('modifiers'), $fields);

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

    /**
     * The form of the page was submitted (webtrees 2.3 only): go back to the page with the data in the URL,
     * adding the field that was chosen in the "other fields" list.
     *
     * Same as the core controller, but the field names are checked here as well: the page checks them again
     * (see sanitiseFields()) so this is only about not copying garbage into the redirect URL.
     */
    private function post(ServerRequestInterface $request): ResponseInterface
    {
        $tree        = Validator::attributes($request)->tree();
        $fields      = $this->sanitiseFields(Validator::parsedBody($request)->array('fields'));
        $modifiers   = $this->sanitiseModifiers(Validator::parsedBody($request)->array('modifiers'), $fields);
        $other_field = Validator::parsedBody($request)->string('other_field', '');

        if ($other_field !== '' && array_key_exists($other_field, array_flip($this->allAdvancedFields()))) {
            $fields[$other_field] = '';
        }

        return redirect(route((string) WebtreesCompat::coreSearchAdvancedClass(), [
            'fields'    => $fields,
            'modifiers' => $modifiers,
            'tree'      => $tree->name(),
        ]));
    }

    /**
     * The "other fields" of the advanced search: the ones of the core page, plus the ones of the 2.3 core page
     * when running on 2.3 (so that the 2.3 page does not lose any option because of this module).
     *
     * @return array<int,string>
     */
    private function otherAdvancedFields(): array
    {
        if (WebtreesCompat::isV23()) {
            return array_merge(self::OTHER_ADVANCED_FIELDS, self::OTHER_ADVANCED_FIELDS_V23);
        }

        return self::OTHER_ADVANCED_FIELDS;
    }

    /**
     * Every field name this page accepts.
     *
     * @return array<int,string>
     */
    private function allAdvancedFields(): array
    {
        return array_merge(self::DEFAULT_ADVANCED_FIELDS, $this->otherAdvancedFields());
    }

    /**
     * Keep only the fields this page knows about, with plain-text values.
     *
     * The field NAMES come straight from the query string and the core SearchService uses them to build
     * SQL LIKE patterns and even a PHP regular expression (e.g. the part after "INDI:NAME:"), so an
     * arbitrary name is a regex-injection / ReDoS vector (CWE-185, CWE-1333) and a non-string value is a
     * TypeError (HTTP 500). Allow-listing the names closes both.
     *
     * @param array<mixed> $fields
     *
     * @return array<string,string>
     */
    private function sanitiseFields(array $fields): array
    {
        $allowed = array_flip($this->allAdvancedFields());
        $clean   = [];

        foreach ($fields as $name => $value) {
            if (!is_string($value) || !array_key_exists((string) $name, $allowed)) {
                continue;
            }

            // No control characters (they would end up in the search log), and a sane length.
            $value = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $value);

            $clean[(string) $name] = mb_substr($value, 0, self::MAX_VALUE_LENGTH);
        }

        return $clean;
    }

    /**
     * Keep only short, plain modifiers (EXACT, BEGINS, OR, AND, BIRT, 5...) of the fields that are searched.
     * Name fields always get a modifier: the core reads $modifiers[$field] without checking that it exists.
     *
     * @param array<mixed>         $modifiers
     * @param array<string,string> $fields
     *
     * @return array<string,string>
     */
    private function sanitiseModifiers(array $modifiers, array $fields): array
    {
        $clean = [];

        foreach ($modifiers as $name => $value) {
            if (array_key_exists((string) $name, $fields) && is_string($value) && preg_match('/^[A-Z0-9_]{1,10}$/', $value) === 1) {
                $clean[(string) $name] = $value;
            }
        }

        foreach (array_keys($fields) as $name) {
            if (preg_match('/(GIVN|SURN)$/', $name) === 1 && !array_key_exists($name, $clean)) {
                $clean[$name] = 'EXACT';
            }
        }

        return $clean;
    }

    private function otherFields(array $fields): array
    {
        $default_facts = new Collection($this->otherAdvancedFields());

        $comparator = static function (string $x, string $y): int {
            $ef     = Registry::elementFactory();
            $label1 = $ef->make(strtr($x, [':DATE' => '', ':PLAC' => '', ':TYPE' => '']))->label();
            $label2 = $ef->make(strtr($y, [':DATE' => '', ':PLAC' => '', ':TYPE' => '']))->label();

            // I18N::comparator() (a Closure) was replaced by I18N::compare() in webtrees 2.3.
            $compare = method_exists(I18N::class, 'compare')
                ? I18N::compare($label1, $label2)
                : I18N::comparator()($label1, $label2);

            return $compare ?: strcmp($x, $y);
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
        foreach (array_merge($this->otherAdvancedFields(), self::DEFAULT_ADVANCED_FIELDS) as $field) {
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
