<?php
require_once 'confidb.php';

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM zyrat");
    $count = $stmt->fetchColumn();
    echo "Total zyra: " . $count . "<br>";
    
    if ($count > 0) {
        $stmt = $pdo->query("SELECT id, emri, qyteti FROM zyrat LIMIT 5");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($rows);
        echo "</pre>";
    } else {
        echo "Tabela eshte bosh.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>