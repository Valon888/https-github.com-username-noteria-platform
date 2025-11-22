<?php
// Script to check the current structure of the zyrat table
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
require_once 'config.php';

try {
    echo "<h2>Structure of the 'zyrat' table:</h2>";
    $stmt = $pdo->query("DESCRIBE zyrat");
    echo "<pre>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    echo "</pre>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>