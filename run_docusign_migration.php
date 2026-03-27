<?php
require_once 'confidb.php';

try {
    $sql = file_get_contents('create_docusign_table.sql');
    $pdo->exec($sql);
    echo "Tabela 'docusign_envelopes' u krijua me sukses.";
} catch (PDOException $e) {
    echo "Gabim gjatë krijimit të tabelës: " . $e->getMessage();
}
?>