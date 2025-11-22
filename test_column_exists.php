<?php
require_once 'config.php';

// Function to check if a column exists in a table
function columnExists($pdo, $table, $column) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM $table LIKE '$column'");
        return ($stmt && $stmt->rowCount() > 0);
    } catch (PDOException $e) {
        return false;
    }
}

echo "<h1>Testing Column Existence in payment_logs Table</h1>";

// Check if noter_id column exists
$noterIdExists = columnExists($pdo, 'payment_logs', 'noter_id');
echo "Column 'noter_id' exists in payment_logs table: " . ($noterIdExists ? 'Yes' : 'No') . "<br>";

// Check if user_id column exists
$userIdExists = columnExists($pdo, 'payment_logs', 'user_id');
echo "Column 'user_id' exists in payment_logs table: " . ($userIdExists ? 'Yes' : 'No') . "<br>";

// Check table structure
try {
    echo "<h2>Table Structure for payment_logs:</h2>";
    $stmt = $pdo->query("DESCRIBE payment_logs");
    echo "<pre>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    echo "</pre>";
} catch (PDOException $e) {
    echo "Error retrieving table structure: " . $e->getMessage();
}
?>