<?php
header('Content-Type: application/json');

session_start();
require_once 'confidb.php';

if (!isset($_GET['zyra_id']) || !isset($_GET['date'])) {
    echo json_encode(['error' => 'Parametrat mungojnë']);
    exit;
}

$zyra_id = intval($_GET['zyra_id']);
$date = $_GET['date'];

// Validate date format (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['error' => 'Formati i datës është i pavlefshëm']);
    exit;
}

// Check if it's weekday
$dayOfWeek = date('N', strtotime($date));
if ($dayOfWeek >= 6) {
    echo json_encode(['slots' => []]);
    exit;
}

// Specific holiday: First day of Fitër Bajramit
if ($date === '2026-03-20') {
    echo json_encode(['slots' => []]);
    exit;
}

// Get all booked times for this date and office
$stmt = $pdo->prepare("
    SELECT TIME_FORMAT(time, '%H:%i') as booked_time
    FROM reservations 
    WHERE zyra_id = ? AND DATE(date) = ?
");
$stmt->execute([$zyra_id, $date]);
$bookedTimes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Generate available time slots (09:00 to 16:00, every hour)
$availableSlots = [];
for ($hour = 9; $hour < 16; $hour++) {
    $time = sprintf('%02d:00', $hour);
    if (!in_array($time, $bookedTimes)) {
        $availableSlots[] = $time;
    }
}

echo json_encode([
    'slots' => $availableSlots,
    'booked' => $bookedTimes,
    'success' => true
]);
