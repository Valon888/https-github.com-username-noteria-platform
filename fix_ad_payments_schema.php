<?php
require 'confidb.php';

echo "=== Updating ad_payments table schema ===\n\n";

// Check and add plan_id column if not exists
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM ad_payments LIKE 'plan_id'");
    if ($stmt->rowCount() === 0) {
        echo "Adding plan_id column...\n";
        $pdo->exec("ALTER TABLE ad_payments ADD COLUMN plan_id INT(11) DEFAULT 2 AFTER advertiser_id");
        echo "✓ plan_id column added (default to plan 2 - Professional)\n";
    } else {
        echo "✓ plan_id column already exists\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Verify table structure
echo "\nVerifying ad_payments table structure:\n";
try {
    $stmt = $pdo->query("DESCRIBE ad_payments");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Schema update complete ===\n";
?>
