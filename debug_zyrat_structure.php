<?php
require_once 'config.php';

try {
    echo "<h2>Table Structure: zyrat</h2>";
    $stmt = $pdo->query("DESCRIBE zyrat");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        foreach ($col as $key => $val) {
            echo "<td>" . htmlspecialchars($val ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";

    echo "<h2>Triggers on zyrat</h2>";
    $stmt = $pdo->query("SHOW TRIGGERS LIKE 'zyrat'");
    $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($triggers) > 0) {
        echo "<pre>" . print_r($triggers, true) . "</pre>";
    } else {
        echo "No triggers found.";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>