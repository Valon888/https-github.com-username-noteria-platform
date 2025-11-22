<?php
require_once 'config.php';

try {
    echo "=== PHONE_VERIFICATION_CODES TABLE STRUCTURE ===\n";
    $stmt = $pdo->query('DESCRIBE phone_verification_codes');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']}) {$col['Null']} {$col['Key']}\n";
    }
    
    echo "\n=== CHECKING FOR COLUMN MISMATCH ===\n";
    $existing_columns = array_column($columns, 'Field');
    
    if (in_array('is_used', $existing_columns)) {
        echo "✅ is_used column EXISTS\n";
    } else {
        echo "❌ is_used column MISSING\n";
    }
    
    if (in_array('is_verified', $existing_columns)) {
        echo "✅ is_verified column EXISTS\n";
    } else {
        echo "❌ is_verified column MISSING\n";
    }
    
} catch(Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>