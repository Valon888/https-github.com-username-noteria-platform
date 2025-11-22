<?php
// Fix for raportet.php - Creates a patch to fix the query

require_once 'config.php';

// Function to check if column exists
function columnExists($pdo, $table, $column) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM $table LIKE '$column'");
        return ($stmt && $stmt->rowCount() > 0);
    } catch (PDOException $e) {
        return false;
    }
}

try {
    echo "<h1>Fixing Reports SQL Query</h1>";
    
    // Check for log_time column
    $logTimeExists = columnExists($pdo, 'payment_logs', 'log_time');
    $createdAtExists = columnExists($pdo, 'payment_logs', 'created_at');
    
    echo "<p>Column 'log_time' exists in payment_logs table: " . ($logTimeExists ? 'Yes' : 'No') . "</p>";
    echo "<p>Column 'created_at' exists in payment_logs table: " . ($createdAtExists ? 'Yes' : 'No') . "</p>";
    
    echo "<h2>Generating Fix</h2>";
    
    echo "<pre>";
    echo "In raportet.php, find all instances of 'pl.log_time' and replace with '" . 
         ($logTimeExists ? 'pl.log_time' : 'pl.created_at') . "'.\n\n";
    
    echo "Example of the fixed query for the case when neither noter_id nor user_id exists:\n\n";
    
    echo '$stmt = $pdo->prepare("SELECT 
    pl.id, NULL as noter_id, \'N/A\' as emri, \'\' as mbiemri, pl.amount as shuma, 
    \'completed\' as status, pl.payment_method as metoda, 
    pl.transaction_id as transaksioni,
    DATE_FORMAT(pl.' . ($logTimeExists ? 'log_time' : 'created_at') . ', \'%d.%m.%Y %H:%i\') as data
FROM payment_logs pl
WHERE pl.' . ($logTimeExists ? 'log_time' : 'created_at') . ' BETWEEN ? AND ?
ORDER BY pl.' . ($logTimeExists ? 'log_time' : 'created_at') . ' DESC");';
    
    echo "</pre>";
    
    // Also check the other statistics query
    echo "<h3>Second Query Fix</h3>";
    echo "<pre>";
    echo "For the statistics query, replace:\n\n";
    
    echo '$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total, 
    SUM(amount) as shuma_total, 
    AVG(amount) as mesatare
FROM payment_logs 
WHERE log_time BETWEEN ? AND ?");';
    
    echo "\n\nWith:\n\n";
    
    echo '$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total, 
    SUM(amount) as shuma_total, 
    AVG(amount) as mesatare
FROM payment_logs 
WHERE ' . ($logTimeExists ? 'log_time' : 'created_at') . ' BETWEEN ? AND ?");';
    
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "<h2>Error</h2>";
    echo "<p>An error occurred: " . $e->getMessage() . "</p>";
}
?>