<?php
require_once 'config.php';

try {
    echo "=== PAYMENT_LOGS TABLE STRUCTURE ===\n";
    $stmt = $pdo->query('DESCRIBE payment_logs');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']}) {$col['Null']} {$col['Key']}\n";
    }
    
    echo "\n=== CHECKING FOR MISSING COLUMNS ===\n";
    $required_columns = ['phone_number', 'phone_verified', 'phone_verified_at'];
    
    $existing_columns = array_column($columns, 'Field');
    
    foreach($required_columns as $req_col) {
        if (in_array($req_col, $existing_columns)) {
            echo "✅ $req_col - EXISTS\n";
        } else {
            echo "❌ $req_col - MISSING\n";
        }
    }
    
} catch(Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>