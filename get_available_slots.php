<?php
header('Content-Type: application/json');

session_start();
require_once 'confidb.php';

if (!isset($_GET['zyra_id'])) {
    echo json_encode(['error' => 'Zyra ID mungon']);
    exit;
}

$zyra_id = intval($_GET['zyra_id']);

// Get booked dates for this office in the next 30 days
$stmt = $pdo->prepare("
    SELECT DISTINCT DATE(date) as booked_date 
    FROM reservations 
    WHERE zyra_id = ? AND DATE(date) >= CURDATE()
    ORDER BY DATE(date)
");
$stmt->execute([$zyra_id]);
$bookedDates = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Also get booked slots count per date (to check if fully booked)
$stmt2 = $pdo->prepare("
    SELECT DATE(date) as booked_date, TIME(date) as time, COUNT(*) as count
    FROM reservations 
    WHERE zyra_id = ? AND DATE(date) >= CURDATE()
    GROUP BY DATE(date), TIME(date)
    ORDER BY DATE(date)
");
$stmt2->execute([$zyra_id]);
$bookedSlots = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'booked' => $bookedDates,
    'slots' => $bookedSlots,
    'success' => true
]);
