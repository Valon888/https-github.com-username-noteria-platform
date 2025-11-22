<?php
require_once 'confidb.php';

echo "Checking Payments Table Structure:\n";
try {
    $sql = "DESCRIBE payments";
    $result = $pdo->query($sql);
    
    echo "Payments Table Structure:\n";
    echo "------------------------\n";
    while($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "Column: " . $row['Field'] . ", Type: " . $row['Type'] . "\n";
    }
    
    echo "\n\n";
    
    // Check if the subscription table has 'amount' column
    echo "Checking Subscription Table Structure:\n";
    echo "------------------------------------\n";
    $sql = "DESCRIBE subscription";
    $result = $pdo->query($sql);
    
    while($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "Column: " . $row['Field'] . ", Type: " . $row['Type'] . "\n";
    }
} catch(PDOException $e) {
    echo 'Error: ' . $e->getMessage();
}
?>