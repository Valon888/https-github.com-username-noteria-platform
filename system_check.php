<?php
require 'confidb.php';

echo "=== Testing All Key Files ===\n\n";

$files_to_test = [
    'login.php' => false,  // Just checking existence
    'dashboard.php' => false,
    'admin_dashboard.php' => false,
    'billing_dashboard.php' => false,
    'admin_settings.php' => false,
    'config.php' => true,  // Require and execute
];

foreach ($files_to_test as $file => $require) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "✓ $file exists\n";
        
        // Try to parse PHP syntax
        $output = shell_exec("php -l " . escapeshellarg($path) . " 2>&1");
        if (strpos($output, 'No syntax errors detected') !== false) {
            echo "  ✓ PHP syntax OK\n";
        } else if (strpos($output, 'Parse error') !== false) {
            echo "  ✗ PHP syntax error:\n";
            echo "    " . trim($output) . "\n";
        }
    } else {
        echo "✗ $file not found\n";
    }
    echo "\n";
}

echo "=== Database Tables Check ===\n";
$tables = [
    'users', 'audit_log', 'lajme', 'messages', 
    'notifications', 'noteret', 'abonimet'
];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM $table");
        $result = $stmt->fetch();
        echo "✓ $table: {$result['cnt']} rows\n";
    } catch (Exception $e) {
        echo "✗ $table: Error - " . $e->getMessage() . "\n";
    }
}

echo "\n=== Session Configuration Check ===\n";
echo "Session cookie httponly: " . ini_get('session.cookie_httponly') . "\n";
echo "Session use strict mode: " . ini_get('session.use_strict_mode') . "\n";
echo "Session gc maxlifetime: " . ini_get('session.gc_maxlifetime') . " seconds\n";

echo "\n=== All Checks Complete ===\n";
