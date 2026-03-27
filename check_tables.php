<?php
/**
 * Database Table Diagnostics
 * Shows all tables in the Noteria database
 */

require_once 'config.php';

try {
    $stmt = $pdo->query("SHOW TABLES FROM `noteria`");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "=== Tables in 'noteria' Database ===\n\n";
    
    foreach ($tables as $table) {
        echo "Table: $table\n";
        
        // Get columns
        $colStmt = $pdo->query("DESCRIBE `$table`");
        $columns = $colStmt->fetchAll();
        
        foreach ($columns as $col) {
            $type = $col['Type'];
            $null = $col['Null'] == 'YES' ? 'NULL' : 'NOT NULL';
            $key = $col['Key'] ? " [{$col['Key']}]" : '';
            echo "  - {$col['Field']}: $type ($null)$key\n";
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
