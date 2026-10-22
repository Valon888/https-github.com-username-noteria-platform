<?php
require_once 'confidb.php';

try {
    // Add photo_path column if it doesn't exist
    $sql = "ALTER TABLE `users` ADD COLUMN `photo_path` VARCHAR(255) DEFAULT NULL";
    try {
        $pdo->exec($sql);
        echo "Kolona 'photo_path' u shtua me sukses në tabelën 'users'.<br>";
    } catch (PDOException $e) {
        echo "Kolona 'photo_path' mund të ekzistojë tashmë ose pati një gabim: " . $e->getMessage() . "<br>";
    }

} catch (PDOException $e) {
    echo "Gabim i përgjithshëm: " . $e->getMessage();
}
?>