<?php
require_once 'config.php';

try {
    echo "Checking 'zyrat' table structure...\n";
    
    // Check if column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM zyrat LIKE 'fjalekalimi'");
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo "Column 'fjalekalimi' does not exist. Adding it...\n";
        $sql = "ALTER TABLE zyrat ADD COLUMN fjalekalimi VARCHAR(255) NOT NULL AFTER email";
        $pdo->exec($sql);
        echo "Column 'fjalekalimi' added successfully.\n";
    } else {
        echo "Column 'fjalekalimi' already exists.\n";
    }
    
    // Also check for 'password' column just in case
    $stmt = $pdo->prepare("SHOW COLUMNS FROM zyrat LIKE 'password'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "Note: A 'password' column also exists.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>