<?php
session_start();
$_SESSION['user_role'] = 'Cashier';
$_SESSION['user_name'] = 'Test Cashier';

chdir(__DIR__ . '/../admin');

try {
    ob_start();
    include __DIR__ . '/../admin/queue.php';
    $out = ob_get_clean();
    echo "SUCCESS\n" . substr($out, 0, 200);
} catch (Throwable $e) {
    ob_end_clean();
    echo "EXCEPTION CAUGHT: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
