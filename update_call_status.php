<?php
// Përditëson statusin e thirrjes video
header('Content-Type: application/json');

// Përfshirja e file-it për lidhjen me bazën e të dhënave
require_once('db_connection.php');

// Sigurohemi që të dhënat e kërkuara janë të disponueshme
if (!isset($_GET['call_id']) || !isset($_GET['status'])) {
    echo json_encode(['success' => false, 'error' => 'Mungojnë të dhëna të nevojshme']);
    exit;
}

$callId = $_GET['call_id'];
$status = $_GET['status'];

// Validojmë statusin
$allowedStatuses = ['scheduled', 'in-progress', 'ended', 'cancelled', 'completed'];
if (!in_array($status, $allowedStatuses)) {
    echo json_encode(['success' => false, 'error' => 'Status i pavlefshëm']);
    exit;
}

// Përditësojmë statusin e thirrjes duke përdorur call_id (jo id numerik)
try {
    $endTimeUpdate = ($status == 'completed' || $status == 'ended');
    if ($endTimeUpdate) {
        $stmt = $conn->prepare("UPDATE video_calls SET status = ?, end_time = NOW() WHERE call_id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE video_calls SET status = ? WHERE call_id = ?");
    }

    $stmt->bind_param('ss', $status, $callId);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $log_message = "Call call_id: $callId status updated to: $status at " . date('Y-m-d H:i:s');
        error_log($log_message, 3, "video_calls.log");
        echo json_encode(['success' => true, 'message' => 'Call status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'No call found with call_id: ' . $callId]);
    }

    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>