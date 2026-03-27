<?php
require_once 'confidb.php';

try {
    // Check admins table structure
    $stmt = $pdo->query("DESCRIBE admins");
    echo "Admins table structure:\n";
    echo str_repeat("=", 80) . "\n";
    while ($column = $stmt->fetch()) {
        echo $column['Field'] . " | " . $column['Type'] . " | " . $column['Null'] . " | " . $column['Key'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
