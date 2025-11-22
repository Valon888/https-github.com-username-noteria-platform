<?php
// Kontrollo strukturën e payment_audit_log
$pdo = new PDO('mysql:host=localhost;dbname=noteria;charset=utf8', 'root', '');

echo "=== STRUKTURA E TABELËS payment_audit_log ===\n";
$stmt = $pdo->query('DESCRIBE payment_audit_log');
while ($row = $stmt->fetch()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
?>