<?php
require_once 'confidb.php';

try {
    $sql = file_get_contents('create_ads_tables.sql');
    $pdo->exec($sql);
    echo "Tabelat 'ads' dhe 'ad_interactions' u krijuan me sukses.";
} catch (PDOException $e) {
    echo "Gabim gjatë krijimit të tabelave: " . $e->getMessage();
}
?>