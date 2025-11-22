<?php
require_once 'confidb.php';

// Get all columns from the payments table
try {
    $sql = "DESCRIBE payments";
    $result = $pdo->query($sql);
    
    echo "Payments Table Structure (All Columns):\n";
    echo "-------------------------------------\n";
    $columns = [];
    while($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "Column: " . $row['Field'] . ", Type: " . $row['Type'] . "\n";
        $columns[] = $row['Field'];
    }
    
    // Get a sample row from payments table
    echo "\nSample Data from Payments Table:\n";
    echo "-------------------------------\n";
    $sql = "SELECT * FROM payments LIMIT 1";
    $result = $pdo->query($sql);
    $row = $result->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        print_r($row);
    } else {
        echo "No data found in payments table.";
    }
    
    // Check if there's a zyra_id column
    echo "\nChecking for JOIN columns:\n";
    echo "-------------------------\n";
    echo "Has zyra_id: " . (in_array('zyra_id', $columns) ? "YES" : "NO") . "\n";
    echo "Has user_id: " . (in_array('user_id', $columns) ? "YES" : "NO") . "\n";
    
    // Look for foreign keys that might represent user/office relationship
    echo "\nPossible Join Columns:\n";
    foreach ($columns as $column) {
        if (strpos($column, 'id') !== false || strpos($column, '_id') !== false) {
            echo "- $column\n";
        }
    }
    
} catch(PDOException $e) {
    echo 'Error: ' . $e->getMessage();
}
?>