<?php
// filepath: c:\xampp\htdocs\noteria\check_slot.php
require_once 'confidb.php';

$zyra_id = $_POST['zyra_id'] ?? '';
$date = $_POST['date'] ?? '';
$time = $_POST['time'] ?? '';

if ($zyra_id && $date && $time) {
    $stmt = $pdo->prepare("SELECT id FROM reservations WHERE zyra_id = ? AND date = ? AND time = ?");
    $stmt->execute([$zyra_id, $date, $time]);
    if ($stmt->fetch()) {
        echo json_encode(['status' => 'busy']);
    } else {
        echo json_encode(['status' => 'free']);
    }
} else {
    echo json_encode(['status' => 'invalid']);
}
// Close the database connection
$pdo = null;
