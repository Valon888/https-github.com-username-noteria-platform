<?php
/**
 * Apply Office Payment Routing Migration
 * Adds support for routing payments directly to notary office bank accounts
 */

require_once 'confidb.php';

try {
    $migration_file = __DIR__ . '/migration_office_payment_routing.sql';
    if (!file_exists($migration_file)) {
        die("Migration file not found: $migration_file\n");
    }

    $sql = file_get_contents($migration_file);
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)), function($s) {
        return !empty($s) && !str_starts_with(trim($s), '--');
    });

    $count = 0;
    foreach ($statements as $statement) {
        if (empty(trim($statement))) continue;
        
        try {
            $pdo->exec($statement);
            $count++;
            echo "✓ Executed statement $count\n";
        } catch (PDOException $e) {
            // Some statements may fail if already exist, that's OK
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'Duplicate') !== false ||
                strpos($e->getMessage(), 'already defined') !== false) {
                echo "ℹ Statement $count already applied (skipping)\n";
                $count++;
            } else {
                throw $e;
            }
        }
    }

    echo "\n✓ Migration completed successfully!\n";
    echo "✓ Applied $count statements\n";
    echo "\nNew Features:\n";
    echo "• payments table now tracks zyra_id (office ID)\n";
    echo "• office_bank, office_iban, routed_at fields added\n";
    echo "• office_payment_summary view created for reporting\n";
    echo "• office_payment_audit table created for tracking\n";

} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
