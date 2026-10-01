<?php

declare(strict_types=1);

/**
 * EidCloud FakeData PSR-4 Autoloader
 * Zero external vendor dependencies required.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'EidCloud\\FakeData\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
