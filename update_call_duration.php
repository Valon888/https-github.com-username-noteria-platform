<?php
// update_call_duration.php - Përditëson kohëzgjatjen e thirrjes
header('Content-Type: application/json');

// Përfshirja e file-it për lidhjen me bazën e të dhënave
require_once('db_connect.php');

// Kontrollo nëse të gjitha parametrat janë marrë
if (!isset($_GET['call_id']) || !isset($_GET['duration'])) {
    echo json_encode(['success' => false, 'error' => 'Mungojnë parametrat e kërkuar']);
    exit;
}

$call_id = $_GET['call_id'];
$duration = intval($_GET['duration']);

// Përditëso kohëzgjatjen e thirrjes
try {
    $stmt = $conn->prepare("UPDATE video_calls SET duration = :duration WHERE id = :call_id");
    $stmt->bindParam(':duration', $duration, PDO::PARAM_INT);
    $stmt->bindParam(':call_id', $call_id, PDO::PARAM_INT);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'duration' => $duration]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Gabim në databazë: ' . $e->getMessage()]);
}
?>