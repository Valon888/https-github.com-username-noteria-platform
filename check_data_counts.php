<?php
require_once 'confidb.php';

try {
    // Check notaret
    $stmt = $pdo->query("SELECT COUNT(*) FROM notaret");
    $countNotaret = $stmt->fetchColumn();
    echo "Numri i noterëve në tabelën 'notaret': " . $countNotaret . "<br>";

    // Check zyrat
    $stmt = $pdo->query("SELECT COUNT(*) FROM zyrat");
    $countZyrat = $stmt->fetchColumn();
    echo "Numri i zyrave në tabelën 'zyrat': " . $countZyrat . "<br>";

} catch (PDOException $e) {
    echo "Gabim: " . $e->getMessage();
}
?>