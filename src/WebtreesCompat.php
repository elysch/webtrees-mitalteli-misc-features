<?php

/**
 * mitalteli-misc-features
 * Copyright (C) 2025
 */

declare(strict_types=1);

namespace MitalteliMiscFeatures;

use Fisharebest\Webtrees\Webtrees;

/**
 * Compatibility helper for webtrees 2.0 / 2.1 / 2.2 / 2.3+.
 *
 * Breaking changes between versions:
 *
 * 2.0 → 2.1
 *   - GedcomImportService constructor gained no new required parameters.
 *   - app() helper available.
 *
 * 2.1 → 2.2
 *   - app() global helper REMOVED.
 *     Replacement: Registry::container()->get(Foo::class)
 *   - Registry::container() returns Fisharebest\Webtrees\Container, a simple
 *     PSR-11 implementation. get() returns a previously registered entry or,
 *     if there is none, builds (and caches!) the class by reflecting on its
 *     constructor.
 *   - Gedcom::REGEX_UID and corrected rawGedcomFilter NOT in official core.
 *     UidSearchService is still needed on all versions.
 *
 * 2.2 → 2.3
 *   - The route targets of the search pages are no longer request handlers
 *     (Http\RequestHandlers\SearchAdvancedPage / SearchAdvancedAction / SearchGeneralPage /
 *     SearchGeneralAction) but controllers with get() and post() methods
 *     (Http\Controllers\SearchAdvanced / SearchGeneral).  See coreSearchAdvancedClass()
 *     and coreSearchGeneralClass().
 *   - The container auto-wires (also in 2.2) and has bind(); set() is still available.
 *   - fisharebest/localization is gone (the module reads its language files itself).
 *   - I18N::comparator() was replaced by I18N::compare().
 *   - The core views that this module overrides changed (see resources/views-2.3/).
 *   - The core MarkdownFactory wraps its output in <div class="wt-markdown">; this module's factory
 *     deliberately does NOT: with the wrapper, NoteStructure (2.3) shows short notes collapsed, with a
 *     plain-text first line, so the #UID# links would only appear after expanding the note.
 *
 * Usage in this module:
 *   WebtreesCompat::make(Foo::class)              — resolve from IoC container
 *   WebtreesCompat::bindInstance($abs, $instance) — register an instance
 *   WebtreesCompat::isV22()                       — true on webtrees >= 2.2.0
 *   WebtreesCompat::isV23()                       — true on webtrees >= 2.3.0-dev
 */
class WebtreesCompat
{
    /**
     * Resolve a class from the IoC container.
     *
     * @template T
     * @param class-string<T> $abstract
     * @return T
     */
    public static function make(string $abstract)
    {
        if (self::isV22()) {
            // 2.2+: app() is gone; use Registry::container()->get()
            return \Fisharebest\Webtrees\Registry::container()->get($abstract);
        }

        // 2.0 / 2.1: app() is available as a global helper
        return app($abstract);
    }

    /**
     * Register a concrete instance in the IoC container under $abstract.
     *
     * In 2.2+, Registry::container() is a PSR-11 container: get() returns the entry
     * that was set or, if there is none, builds and caches a new object.  We therefore
     * pass a ready-made instance so that the container returns exactly this object.
     *
     * In 2.1/2.0, we use app()->instance() to bind the pre-built object so
     * that the Laravel container always returns this exact instance.
     *
     * @param class-string $abstract
     * @param object       $instance
     */
    public static function bindInstance(string $abstract, object $instance): void
    {
        if (self::isV22()) {
            \Fisharebest\Webtrees\Registry::container()->set($abstract, $instance);
        } else {
            app()->instance($abstract, $instance);
        }
    }

    /**
     * Returns true when running on webtrees 2.2.0 or later.
     */
    public static function isV22(): bool
    {
        return version_compare(Webtrees::VERSION, '2.2.0', '>=');
    }

    /**
     * Returns true when running on webtrees 2.3.0 or later.
     *
     * The 2.3 development branch reports itself as "2.3.0-dev", which is the lowest 2.3 version
     * (-dev sorts before -alpha, -beta, -RC and the final release).
     */
    public static function isV23(): bool
    {
        return version_compare(Webtrees::VERSION, '2.3.0-dev', '>=');
    }

    /**
     * Class that the core route /search-advanced resolves from the container, or null if it cannot be found.
     * It is the class whose container entry this module replaces with its own handler.
     *
     *   2.0 - 2.2: Fisharebest\Webtrees\Http\RequestHandlers\SearchAdvancedPage (GET; POST goes to SearchAdvancedAction)
     *   2.3+     : Fisharebest\Webtrees\Http\Controllers\SearchAdvanced (GET and POST)
     *
     * Looked up by existence (not by version number) because the 2.3 branch is still in development.
     *
     * @return class-string|null
     */
    public static function coreSearchAdvancedClass(): ?string
    {
        return self::firstExistingClass([
            'Fisharebest\\Webtrees\\Http\\Controllers\\SearchAdvanced',
            'Fisharebest\\Webtrees\\Http\\RequestHandlers\\SearchAdvancedPage',
        ]);
    }

    /**
     * Class that the core route /search-general resolves from the container, or null if it cannot be found.
     *
     *   2.0 - 2.2: Fisharebest\Webtrees\Http\RequestHandlers\SearchGeneralPage
     *   2.3+     : Fisharebest\Webtrees\Http\Controllers\SearchGeneral
     *
     * @return class-string|null
     */
    public static function coreSearchGeneralClass(): ?string
    {
        return self::firstExistingClass([
            'Fisharebest\\Webtrees\\Http\\Controllers\\SearchGeneral',
            'Fisharebest\\Webtrees\\Http\\RequestHandlers\\SearchGeneralPage',
        ]);
    }

    /**
     * @param array<string> $classes
     *
     * @return class-string|null
     */
    private static function firstExistingClass(array $classes): ?string
    {
        foreach ($classes as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }

        return null;
    }
}
