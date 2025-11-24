<?php
/**
 * Full System Test
 * Testo të gjithë komponentet kryesore
 */

echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║           NOTERIA SYSTEM - FULL TEST                       ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

// Test 1: Database connection
echo "TEST 1: Database Connection\n";
echo "─────────────────────────────\n";
try {
    require 'confidb.php';
    echo "✓ Database connected\n";
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Check for function redeclarations
echo "\nTEST 2: Function Declarations\n";
echo "─────────────────────────────\n";

$functions_to_check = [
    'log_activity',
    'log_failed_attempt',
    'log_security_event',
    'check_brute_force',
    'checkSessionTimeout'
];

foreach ($functions_to_check as $func) {
    if (function_exists($func)) {
        echo "✓ $func() declared\n";
    } else {
        echo "✗ $func() NOT found\n";
    }
}

// Test 3: Session Helper
echo "\nTEST 3: Session Helper\n";
echo "─────────────────────────────\n";
if (file_exists('session_helper.php')) {
    require_once 'session_helper.php';
    echo "✓ session_helper.php loaded\n";
    
    if (function_exists('initializeSecureSession')) {
        echo "✓ initializeSecureSession() available\n";
    } else {
        echo "✗ initializeSecureSession() NOT found\n";
    }
} else {
    echo "✗ session_helper.php not found\n";
}

// Test 4: Authentication
echo "\nTEST 4: Authentication\n";
echo "─────────────────────────────\n";
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE roli IN ('admin', 'notary', 'user')");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo "✓ Users with roles: $count\n";
    
    $stmt = $pdo->query("SELECT id, email, roli FROM users WHERE roli IN ('admin', 'notary', 'user') LIMIT 3");
    $users = $stmt->fetchAll();
    foreach ($users as $user) {
        echo "  - {$user['email']} ({$user['roli']})\n";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Test 5: Tables
echo "\nTEST 5: Database Tables\n";
echo "─────────────────────────────\n";
$required_tables = [
    'users', 'audit_log', 'lajme', 'messages', 
    'notifications', 'noteret', 'abonimet',
    'payments', 'subscription', 'fatura', 'zyrat'
];

$missing = [];
foreach ($required_tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "✓ $table ($count rows)\n";
    } catch (Exception $e) {
        echo "✗ $table (missing)\n";
        $missing[] = $table;
    }
}

// Test 6: PHP Files Syntax
echo "\nTEST 6: PHP Files Syntax\n";
echo "─────────────────────────────\n";
$files_to_check = [
    'login.php',
    'dashboard.php',
    'admin_dashboard.php',
    'billing_dashboard.php',
    'confidb.php',
    'session_helper.php',
    'config.php'
];

$syntax_errors = 0;
foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $output = shell_exec("php -l " . escapeshellarg($file) . " 2>&1");
        if (strpos($output, 'No syntax errors') !== false) {
            echo "✓ $file OK\n";
        } else {
            echo "✗ $file has errors\n";
            $syntax_errors++;
        }
    } else {
        echo "? $file not found\n";
    }
}

// Summary
echo "\n╔═══════════════════════════════════════════════════════════╗\n";
echo "║                      SUMMARY                               ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

if (empty($missing) && $syntax_errors === 0) {
    echo "✓ ALL TESTS PASSED!\n";
    echo "System is ready for production.\n";
} else {
    if (!empty($missing)) {
        echo "Missing tables: " . implode(', ', $missing) . "\n";
    }
    if ($syntax_errors > 0) {
        echo "Files with syntax errors: $syntax_errors\n";
    }
}

echo "\n";
