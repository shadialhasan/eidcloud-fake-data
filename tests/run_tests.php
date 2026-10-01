<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';
require_once __DIR__ . '/FakeDataTest.php';

use EidCloud\FakeData\Tests\FakeDataTest;

echo "========================================================\n";
echo " EidCloud Fake Data Engine - Automated Zero-Dep Test Suite \n";
echo " PHP Version: " . PHP_VERSION . "\n";
echo "========================================================\n\n";

$tester = new FakeDataTest();
try {
    $exitCode = $tester->runAll();
    exit($exitCode);
} catch (Throwable $e) {
    echo "\n❌ TEST SUITE FAILED!\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
