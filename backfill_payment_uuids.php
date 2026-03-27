<?php
// backfill_payment_uuids.php - Assign UUIDs to all payments missing one (standalone PDO)
$host = "localhost";
$db = "noteria";
$user = "root";
$pass = "";
function generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$rows = $pdo->query("SELECT id FROM payments WHERE uuid IS NULL OR uuid = ''")->fetchAll(PDO::FETCH_ASSOC);
$count = 0;
foreach ($rows as $row) {
    $uuid = generate_uuid();
    $stmt = $pdo->prepare("UPDATE payments SET uuid = ? WHERE id = ?");
    $stmt->execute([$uuid, $row['id']]);
    $count++;
}
echo "Backfilled $count payments with UUIDs.\n";
