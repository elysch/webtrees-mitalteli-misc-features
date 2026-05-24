<?php

/**
 * mitalteli-misc-features
 * Copyright (C) 2025
 */

declare(strict_types=1);

namespace MitalteliMiscFeatures;

use Fisharebest\Webtrees\Webtrees;

/**
 * Compatibility helper for webtrees 2.0 / 2.1 / 2.2+.
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
 *     PSR-11 implementation. It does NOT auto-wire; get() only returns
 *     previously registered entries.
 *   - Gedcom::REGEX_UID and corrected rawGedcomFilter NOT in official core.
 *     UidSearchService is still needed on all versions.
 *
 * Usage in this module:
 *   WebtreesCompat::make(Foo::class)              — resolve from IoC container
 *   WebtreesCompat::bindInstance($abs, $instance) — register an instance
 *   WebtreesCompat::isV22()                       — true on webtrees >= 2.2.0
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
     * In 2.2, Registry::container() is a PSR-11 container that does NOT
     * auto-wire — get() only returns previously set entries.  We therefore
     * pass a ready-made instance rather than asking the container to build it.
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
}
