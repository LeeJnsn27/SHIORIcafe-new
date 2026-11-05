<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
/**
 * Master Test Runner for SHIORIcafe Cashier TDD Suite
 */

require_once __DIR__ . '/CashierWorkflowTest.php';
require_once __DIR__ . '/CashierWorkflowIntegrationTest.php';

$pdo = CashierWorkflowTest::setUpEnv();

$suites = [
    'Unit & Contract Tests' => new CashierWorkflowTest($pdo),
    'End-to-End Integration Tests' => new CashierWorkflowIntegrationTest($pdo),
];

echo "========================================================\n";
echo "SHIORI CAFE CASHIER WORKFLOW TDD AUDIT - TEST RUNNER\n";
echo "========================================================\n";

$totalPassed = 0;
$totalFailed = 0;
$allResults = [];

foreach ($suites as $suiteName => $suite) {
    echo "\n>>> SUITE: {$suiteName}\n";
    $methods = get_class_methods($suite);
    $testMethods = array_filter($methods, fn($m) => str_starts_with($m, 'test_'));

    foreach ($testMethods as $test) {
        try {
            $suite->$test();
            $allResults[$test] = ['status' => 'PASS', 'message' => 'OK'];
            $totalPassed++;
            echo "  [ PASS ] {$test}\n";
        } catch (Throwable $e) {
            $allResults[$test] = [
                'status' => 'FAIL',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];
            $totalFailed++;
            echo "  [ FAIL ] {$test}\n";
            echo "           -> " . $e->getMessage() . "\n";
        }
    }
}

echo "\n========================================================\n";
echo "OVERALL RESULTS: " . ($totalPassed + $totalFailed) . " | PASSED: {$totalPassed} | FAILED: {$totalFailed}\n";
echo "========================================================\n";
