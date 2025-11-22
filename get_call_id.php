<?php
// Përgjigjen e kthejmë në format JSON
header('Content-Type: application/json');

// Përfshirja e file-it për lidhjen me bazën e të dhënave
require_once('db_connection.php');

// Sigurohemi që ID e dhomës është i disponueshëm
if (!isset($_GET['room_id'])) {
    echo json_encode(['success' => false, 'error' => 'Room ID not provided']);
    exit;
}

$roomId = $_GET['room_id'];

// Marrim call_id nga tabela video_calls (kollona 'room' dhe 'call_id' priten sipas implementimit të video_call.php)
try {
    $stmt = $conn->prepare("SELECT call_id FROM video_calls WHERE room = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("s", $roomId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'call_id' => $row['call_id']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No call found with the provided room ID'
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
}
?>