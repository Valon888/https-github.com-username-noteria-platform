<?php
require_once 'confidb.php';

try {
    $stmt = $pdo->query("DESCRIBE ads");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns in 'ads' table: <br>";
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>