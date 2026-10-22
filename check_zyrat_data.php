<?php
require_once 'confidb.php';

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM zyrat");
    $count = $stmt->fetchColumn();
    echo "Numri i zyrave në databazë: " . $count . "<br>";
    
    if ($count > 0) {
        $stmt = $pdo->query("SELECT * FROM zyrat LIMIT 5");
        $zyrat = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($zyrat);
        echo "</pre>";
    } else {
        echo "Tabela 'zyrat' është bosh.";
    }
} catch (PDOException $e) {
    echo "Gabim: " . $e->getMessage();
}
?>