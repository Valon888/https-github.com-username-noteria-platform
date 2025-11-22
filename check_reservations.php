<?php
require_once 'confidb.php';

// Check the reservations table structure to see if it links to users and zyrat
try {
    $sql = "DESCRIBE reservations";
    $result = $pdo->query($sql);
    
    echo "Reservations Table Structure:\n";
    echo "----------------------------\n";
    while($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "Column: " . $row['Field'] . ", Type: " . $row['Type'] . "\n";
    }
    
    // Get a sample row from reservations
    echo "\nSample Data from Reservations Table:\n";
    echo "----------------------------------\n";
    $sql = "SELECT * FROM reservations LIMIT 1";
    $result = $pdo->query($sql);
    $row = $result->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        print_r($row);
    } else {
        echo "No data found in reservations table.";
    }
    
} catch(PDOException $e) {
    echo 'Error: ' . $e->getMessage();
}
?>