<?php
require 'config.php';

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM documents");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Columns in 'documents' table:\n";
    foreach ($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>