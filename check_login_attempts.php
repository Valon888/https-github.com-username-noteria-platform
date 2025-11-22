<?php
// Include database configuration
require_once 'config.php';

try {
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'login_attempts'");
    if ($stmt->rowCount() > 0) {
        echo "Table 'login_attempts' exists.<br>";
        
        // Count rows
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM login_attempts");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "Number of records in login_attempts: " . $count . "<br>";
        
        // Show sample data (limited to 5 rows)
        if ($count > 0) {
            echo "<br>Sample data from login_attempts:<br>";
            $stmt = $pdo->query("SELECT * FROM login_attempts LIMIT 5");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "<pre>";
            print_r($rows);
            echo "</pre>";
        } else {
            echo "<br>No data in login_attempts table yet.<br>";
        }
    } else {
        echo "Table 'login_attempts' does not exist.<br>";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>