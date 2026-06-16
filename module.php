<?php

/**
 * mitalteli-misc-features module entry point.
 *
 * webtrees loads modules via a bare include(), so Composer's autoloader is
 * not available for module classes.  We register our own PSR-4 autoloader
 * here before instantiating the module class.
 */

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    // Only handle our own namespace
    $prefix = 'MitalteliMiscFeatures\\';
    $len    = strlen($prefix);

    if (strncmp($class, $prefix, $len) !== 0) {
        return;
    }

    $relative = substr($class, $len);
    $file     = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// The module class itself lives in the module root, not under src/
require_once __DIR__ . '/MitalteliMiscFeaturesModule.php';

use MitalteliMiscFeatures\MitalteliMiscFeaturesModule;

// If versions are not compatible, the method isWebtreesVersionAcceptable 
// will die() with an error message. 
// If they are compatible, the module class will be returned.
if (MitalteliMiscFeaturesModule::isWebtreesVersionAcceptable()) {
    return new MitalteliMiscFeaturesModule();
}

return;