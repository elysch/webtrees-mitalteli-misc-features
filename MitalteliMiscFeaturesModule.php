<?php

/**
 * mitalteli-misc-features
 * Copyright (C) 2025
 */

declare(strict_types=1);

namespace MitalteliMiscFeatures;

use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\SearchService;
use Fisharebest\Webtrees\Services\TreeService;
use MitalteliMiscFeatures\Elements\MarriageType;
use MitalteliMiscFeatures\Services\UidSearchService;
use MitalteliMiscFeatures\WebtreesCompat;
use MitalteliMiscFeatures\Factories\MarkdownFactory;
use MitalteliMiscFeatures\Http\RequestHandlers\SearchAdvancedPage;
use Fisharebest\Webtrees\Contracts\MarkdownFactoryInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\SearchAdvancedPage as CoreSearchAdvancedPage;
use Fisharebest\Webtrees\Http\RequestHandlers\SearchGeneralPage;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Webtrees;

/**
 * Misc Features Module for webtrees 2.1.25
 */
class MitalteliMiscFeaturesModule extends AbstractModule implements
    ModuleCustomInterface,
    ModuleGlobalInterface
{
    use ModuleCustomTrait;
    use ModuleGlobalTrait;

    public const CUSTOM_AUTHOR = 'elysch';
    public const CUSTOM_VERSION = '1.0.1';
    public const GITHUB_REPO = 'webtrees-mitalteli-misc-features';
    public const AUTHOR_WEBSITE = 'https://github.com/elysch/webtrees-mitalteli-misc-features/';
    public const CUSTOM_SUPPORT_URL = self::AUTHOR_WEBSITE . 'issues';

    public const REPORT_TITLE = 'Mitalteli Misc Features';

    public const minimumWebtreesVersion = '2.1.0';
    public const maximumWebtreesVersion = 'undefined'; // no upper limit yet, but we should set one if we start using features that require a minimum version

    /**
     * Check if the current Webtrees version is acceptable for this module.
     * returns true if compatible.
     * Die() with an error message if not compatible.
     * 
     * @return bool
     */
    public static function isWebtreesVersionAcceptable(): bool
    {

        //webtrees major version switch
        if (defined("WT_VERSION")) {
            $webtreesVersion = WT_VERSION;
        } else {
            $webtreesVersion = Webtrees::VERSION;
        }

        if (! (version_compare($webtreesVersion, SELF::minimumWebtreesVersion, '>=')
            && ((SELF::maximumWebtreesVersion === 'undefined') ||
                version_compare($webtreesVersion, SELF::maximumWebtreesVersion, '<='))
        )) {
            if (SELF::maximumWebtreesVersion === 'undefined') {
                die(printf('%s module version %s is not compatible. Supported Webtrees versions are %s and above.', SELF::REPORT_TITLE, SELF::CUSTOM_VERSION, SELF::minimumWebtreesVersion));
            } else {
                die(printf('%s module version %s is not compatible. Supported Webtrees versions are between: %s and %s.', SELF::REPORT_TITLE, SELF::CUSTOM_VERSION, SELF::minimumWebtreesVersion, SELF::maximumWebtreesVersion));
            }
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // ModuleCustomInterface
    // -------------------------------------------------------------------------

    public function title(): string
    {
        return I18N::translate(MitalteliMiscFeaturesModule::REPORT_TITLE);
    }

    public function description(): string
    {
        return I18N::translate(
            'Adds UID/UUID markdown links, extended birth/death place search, </br>' .
            'COHABITATION marriage type, and more.</br></br>' .
            '<div class="markdown-alert" dir="auto">🛑<g-emoji class="g-emoji" alias="warning">⚠️</g-emoji> Critical Performance Warning (Scalability Limits)</div>' .
            '<div class="markdown-alert markdown-alert-caution" dir="auto"><p class="markdown-alert-title" dir="auto"><svg data-component="Octicon" class="octicon octicon-stop mr-2" viewBox="0 0 16 16" version="1.1" width="16" height="16" aria-hidden="true"><path d="M4.47.22A.749.749 0 0 1 5 0h6c.199 0 .389.079.53.22l4.25 4.25c.141.14.22.331.22.53v6a.749.749 0 0 1-.22.53l-4.25 4.25A.749.749 0 0 1 11 16H5a.749.749 0 0 1-.53-.22L.22 11.53A.749.749 0 0 1 0 11V5c0-.199.079-.389.22-.53Zm.84 1.28L1.5 5.31v5.38l3.81 3.81h5.38l3.81-3.81V5.31L10.69 1.5ZM8 4a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 8 4Zm0 8a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"></path></svg>Caution</p><p dir="auto"><strong>Severe Slowdowns on Large Trees (UID / _UID Searches)</strong>
<code>webtrees</code> lacks database indexes on <code>UID</code> and <code>_UID</code> fields. Because of how the current database architecture is structured, there is no way to add manual indexes to mitigate the underlying performance bottleneck.</p>
</div>'
        );
    }

    public function customModuleAuthorName(): string
    {
        return self::CUSTOM_AUTHOR;
    }

    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    public function customModuleLatestVersionUrl(): string
    {
        return 'https://raw.githubusercontent.com/' . self::CUSTOM_AUTHOR . '/' . self::GITHUB_REPO . '/main/latest-version.txt';
    }

    public function customModuleSupportUrl(): string
    {
        return self::AUTHOR_WEBSITE;
    }

    // -------------------------------------------------------------------------
    // AbstractModule::boot()
    // -------------------------------------------------------------------------

    /**
     * Boot the module.
     *
     * In webtrees 2.1.x there is no ModuleMiddlewareInterface.  The supported
     * way to intercept a specific route handler is to rebind its class in the
     * IoC container so that when webtrees' own RequestHandler middleware calls
     *   app(CoreSearchAdvancedPage::class)->handle($request)
     * it receives our subclass instead.
     */
    public function boot(): void
    {
        // 1. Replace the MarkdownFactory so #UUID references become hyperlinks.
        $markdownFactory = new MarkdownFactory();
        WebtreesCompat::bindInstance(MarkdownFactoryInterface::class, $markdownFactory);
        Registry::markdownFactory($markdownFactory);

        // 2. Add COHABITATION to MARR:TYPE and canonicalise RELI -> RELIGIOUS.
        $label = I18N::translate('Marriage type');
        Registry::elementFactory()->registerTags([
            'FAM:MARR:TYPE'  => new MarriageType($label),
            'INDI:MARR:TYPE' => new MarriageType($label),
        ]);

        // 3. Register our view namespace.
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');

        // 4. Override core view templates.
        //    First argument MUST start with '::' (webtrees default namespace).
        View::registerCustomView('::search-advanced-field',   $this->name() . '::search-advanced-field');
        View::registerCustomView('::search-advanced-page',    $this->name() . '::search-advanced-page');
        View::registerCustomView('::search-results',          $this->name() . '::search-results');
        View::registerCustomView('::lists/individuals-table', $this->name() . '::lists/individuals-table');

        // 5. Rebind the core SearchAdvancedPage handler to our extended version.
        //    webtrees resolves route handlers via app(ClassName::class), so
        //    binding the core class to a factory that returns our subclass is
        //    sufficient — no middleware interface required.
        $treeService = new TreeService(new GedcomImportService());
        WebtreesCompat::bindInstance(
            CoreSearchAdvancedPage::class,
            new SearchAdvancedPage(new UidSearchService($treeService))
        );

        // 6. Register UidSearchService as the global SearchService so that
        //    ALL searches in webtrees (simple search, advanced search,
        //    autocomplete, etc.) benefit from the corrected rawGedcomFilter
        //    that recognises 38-char Ancestry UIDs.
        $plainSearchService = new SearchService($treeService);
        $uidSearchService   = new UidSearchService($treeService);

        if (WebtreesCompat::isV22()) {
            // 2.2: Container caches instances. Strategy:
            // 1. Bind UidSearchService temporarily.
            // 2. Force-build SearchGeneralPage — cached with UidSearchService.
            // 3. Restore plain SearchService so SearchReplaceAction and all
            //    other auto-wired handlers get exact matching.
            WebtreesCompat::bindInstance(SearchService::class, $uidSearchService);
            \Fisharebest\Webtrees\Registry::container()->get(SearchGeneralPage::class);
            WebtreesCompat::bindInstance(SearchService::class, $plainSearchService);
        } else {
            // 2.1/2.0: Laravel container does not cache handler instances.
            // Each request auto-wires a fresh handler, so UidSearchService
            // is injected into SearchGeneralPage on every request automatically.
            // Limitation: SearchReplaceAction also receives UidSearchService,
            // which may show false matches for UID-format search terms, but
            // the replace step will silently do nothing (no corruption).
            WebtreesCompat::bindInstance(SearchService::class, $uidSearchService);
        }
    }

    // -------------------------------------------------------------------------
    // ModuleGlobalInterface
    // -------------------------------------------------------------------------

    public function headContent(): string
    {
        // JavaScript that warns the user when they search for a UID-shaped term.
        // Only the first 32 hex chars are used for matching (see UidSearchService),
        // so results may include records whose UID shares that prefix.
        // The warning is injected after the input's closest ancestor that is a
        // direct child of the form, so it appears below the search field on both
        // the search-general page and the search-advanced page.
        return '<script>
(function () {
    var UID_REGEX = /^[0-9a-f]{8}-?[0-9a-f]{4}-?[0-9a-f]{4}-?[0-9a-f]{4}-?[0-9a-f]{12}$|^[0-9a-f]{32}$|^[0-9a-f]{36}$|^[0-9a-f]{38}$/i;
    var WARNING_ID = "mitalteli-uid-search-warning";
    var MSG_TEXT   = ' . json_encode(I18N::translate(
            'UID search uses only the first 32 characters. Results may include records with a similar UID prefix.'
        )) . ';

    function getInsertionPoint(input) {
        // Walk up from the input until we find a direct child of the <form>,
        // then insert the warning after it.
        var form = input.closest("form");
        if (!form) { return null; }
        var node = input;
        while (node.parentNode && node.parentNode !== form) {
            node = node.parentNode;
        }
        return { parent: form, before: node.nextSibling };
    }

    function showWarning(input) {
        if (document.getElementById(WARNING_ID)) { return; }
        var point = getInsertionPoint(input);
        if (!point) { return; }
        var msg = document.createElement("div");
        msg.id        = WARNING_ID;
        msg.className = "alert alert-warning small mt-1 mb-0 py-1";
        msg.textContent = MSG_TEXT;
        point.parent.insertBefore(msg, point.before);
    }

    function removeWarning() {
        var el = document.getElementById(WARNING_ID);
        if (el) { el.remove(); }
    }

    function checkInput(input) {
        if (UID_REGEX.test(input.value.trim())) {
            showWarning(input);
        } else {
            removeWarning();
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        // Covers: search-general page, search-advanced page, search results page.
        // Excludes navbar search box (no name=query there in standard webtrees).
        document.querySelectorAll("input[name=query]").forEach(function (input) {
            input.addEventListener("input", function () { checkInput(input); });
            checkInput(input); // fire immediately on page load if query is pre-filled
        });
    });
}());
</script>';
    }

    public function bodyContent(): string
    {
        return '';
    }

    // -------------------------------------------------------------------------
    // ModuleCustomInterface — translations
    // -------------------------------------------------------------------------

    /**
     * Additional translations for strings introduced by this module.
     *
     * webtrees calls this method and merges the returned array into the active
     * language catalogue, so I18N::translate() picks them up automatically.
     *
     * Only strings that are NOT already translated by the webtrees core need
     * to be listed here.  Currently that is only 'Cohabitation', which was
     * added to MarriageType by this module and has no core translation.
     *
     * @param string $language  ISO 639 language code, e.g. 'es', 'en', 'fr'
     *
     * @return array<string,string>
     */
    public function customTranslations(string $language): array
    {
        $translations = [
            'es' => [
                'Cohabitation' => 'Cohabitación',
                'UID search uses only the first 32 characters. Results may include records with a similar UID prefix.'
                    => 'La búsqueda por UID utiliza solo los primeros 32 caracteres. Los resultados pueden incluir registros con un prefijo de UID similar.',
            ],
        ];

        return $translations[$language] ?? [];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function resourcesFolder(): string
    {
        return __DIR__ . '/resources/';
    }
}
