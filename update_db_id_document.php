<?php
/**
 * Script për përditësimin e databazës - shtimi i kolonës id_document në tabelën users
 * Noteria - Sistemi i Menaxhimit të Zyreve Noteriale
 */

// Konfigurimi i raportimit të gabimeve
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'confidb.php';

try {
    // Kontrollo nëse kolona id_document ekziston në tabelën users
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'id_document'");
    $column_exists = $stmt->fetch() ? true : false;

    if (!$column_exists) {
        // Shto kolonën id_document në tabelën users
        $pdo->exec("ALTER TABLE users ADD COLUMN id_document VARCHAR(255) DEFAULT NULL AFTER personal_number");
        echo "Kolona 'id_document' u shtua me sukses në tabelën 'users'.";
    } else {
        echo "Kolona 'id_document' tashmë ekziston në tabelën 'users'.";
    }
} catch (PDOException $e) {
    echo "Gabim gjatë përditësimit të databazës: " . $e->getMessage();
    error_log("Gabim në update_db_id_document.php: " . $e->getMessage());
}
?>