<?php
/**
 * Fix Payment Types in Subscription Payments
 * Rregullon kolona e mungueshme në tabelën subscription_payments
 */

require_once 'confidb.php';

echo "=== Rregullimi i payment_type në subscription_payments ===\n";

try {
    // Kontrollo nëse kolona payment_type ekziston
    $checkColumn = $pdo->query("SHOW COLUMNS FROM subscription_payments LIKE 'payment_type'");
    
    if ($checkColumn->rowCount() == 0) {
        echo "Shtoj kolonën payment_type...\n";
        $pdo->exec("ALTER TABLE subscription_payments ADD COLUMN payment_type ENUM('automatic','manual') DEFAULT 'manual'");
        echo "✓ Kolona payment_type u shtua me sukses!\n";
    } else {
        echo "✓ Kolona payment_type tashmë ekziston.\n";
    }
    
    // Përditëso të gjitha rekording që nuk kanë payment_type
    $updateStmt = $pdo->prepare("
        UPDATE subscription_payments 
        SET payment_type = 'manual' 
        WHERE payment_type IS NULL OR payment_type = ''
    ");
    $updateStmt->execute();
    $updatedRows = $updateStmt->rowCount();
    
    echo "✓ U përditësuan $updatedRows rekorde me payment_type = 'manual'\n";
    
    // Kontrollo nëse kolona transaction_id ekziston dhe është unique
    $checkTransactionId = $pdo->query("SHOW COLUMNS FROM subscription_payments LIKE 'transaction_id'");
    
    if ($checkTransactionId->rowCount() == 0) {
        echo "Shtoj kolonën transaction_id...\n";
        $pdo->exec("ALTER TABLE subscription_payments ADD COLUMN transaction_id VARCHAR(100) UNIQUE");
        echo "✓ Kolona transaction_id u shtua!\n";
        
        // Gjenero transaction_id për rekorde ekzistuese
        $records = $pdo->query("SELECT id FROM subscription_payments WHERE transaction_id IS NULL")->fetchAll();
        
        $updateTxnStmt = $pdo->prepare("UPDATE subscription_payments SET transaction_id = ? WHERE id = ?");
        
        foreach ($records as $record) {
            $transactionId = 'MANUAL_' . date('Ymd') . '_' . $record['id'] . '_' . uniqid();
            $updateTxnStmt->execute([$transactionId, $record['id']]);
        }
        
        echo "✓ U gjeruan transaction_id për " . count($records) . " rekorde\n";
    }
    
    // Shfaq statistikat e përditësuara
    echo "\n=== STATISTIKAT ===\n";
    
    $stats = $pdo->query("
        SELECT 
            payment_type,
            COUNT(*) as count,
            SUM(amount) as total_amount
        FROM subscription_payments 
        GROUP BY payment_type
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($stats as $stat) {
        echo "- " . ucfirst($stat['payment_type']) . ": {$stat['count']} pagesa, Total: €" . number_format($stat['total_amount'], 2) . "\n";
    }
    
    echo "\n✓ Rregullimi u kompletua me sukses!\n";
    
} catch (PDOException $e) {
    echo "✗ GABIM: " . $e->getMessage() . "\n";
}

echo "\nTani mund të hapni billing_dashboard.php pa gabime!\n";
?>