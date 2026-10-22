<?php
require 'config.php';

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM zyrat");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Columns in 'zyrat' table:\n";
    foreach ($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>