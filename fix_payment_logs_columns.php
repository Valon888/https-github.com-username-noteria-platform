<?php
// Script për të shtuar kolona që mungojnë në payment_logs
$pdo = new PDO('mysql:host=localhost;dbname=noteria;charset=utf8', 'root', '');

echo "=== SHTIMI I KOLONAVE TË REJA ===\n";

// Kontrollo për office_name
$stmt = $pdo->query("SHOW COLUMNS FROM payment_logs LIKE 'office_name'");
if ($stmt->rowCount() == 0) {
    echo "Shtoj kolonën 'office_name'...\n";
    $pdo->exec("ALTER TABLE payment_logs ADD COLUMN office_name VARCHAR(255) AFTER office_email");
} else {
    echo "✓ Kolona 'office_name' ekziston\n";
}

// Kontrollo për payment_amount
$stmt = $pdo->query("SHOW COLUMNS FROM payment_logs LIKE 'payment_amount'");
if ($stmt->rowCount() == 0) {
    echo "Shtoj kolonën 'payment_amount'...\n";
    $pdo->exec("ALTER TABLE payment_logs ADD COLUMN payment_amount DECIMAL(10,2) AFTER office_name");
} else {
    echo "✓ Kolona 'payment_amount' ekziston\n";
}

// Kontrollo për payment_details
$stmt = $pdo->query("SHOW COLUMNS FROM payment_logs LIKE 'payment_details'");
if ($stmt->rowCount() == 0) {
    echo "Shtoj kolonën 'payment_details'...\n";
    $pdo->exec("ALTER TABLE payment_logs ADD COLUMN payment_details TEXT AFTER payment_amount");
} else {
    echo "✓ Kolona 'payment_details' ekziston\n";
}

// Kontrollo për verification_status
$stmt = $pdo->query("SHOW COLUMNS FROM payment_logs LIKE 'verification_status'");
if ($stmt->rowCount() == 0) {
    echo "Shtoj kolonën 'verification_status'...\n";
    $pdo->exec("ALTER TABLE payment_logs ADD COLUMN verification_status ENUM('pending','verified','rejected') DEFAULT 'pending' AFTER payment_details");
} else {
    echo "✓ Kolona 'verification_status' ekziston\n";
}

// Kontrollo për file_path
$stmt = $pdo->query("SHOW COLUMNS FROM payment_logs LIKE 'file_path'");
if ($stmt->rowCount() == 0) {
    echo "Shtoj kolonën 'file_path'...\n";
    $pdo->exec("ALTER TABLE payment_logs ADD COLUMN file_path VARCHAR(500) AFTER verification_status");
} else {
    echo "✓ Kolona 'file_path' ekziston\n";
}

// Kontrollo për admin_notes
$stmt = $pdo->query("SHOW COLUMNS FROM payment_logs LIKE 'admin_notes'");
if ($stmt->rowCount() == 0) {
    echo "Shtoj kolonën 'admin_notes'...\n";
    $pdo->exec("ALTER TABLE payment_logs ADD COLUMN admin_notes TEXT AFTER file_path");
} else {
    echo "✓ Kolona 'admin_notes' ekziston\n";
}

echo "\n=== STRUKTURA E RE ===\n";
$stmt = $pdo->query('DESCRIBE payment_logs');
while ($row = $stmt->fetch()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
?>