<?php
// heartbeat.php - update last_seen for active video call
header('Content-Type: application/json');
require_once('db_connection.php');
session_start();

// Accept call_id from POST or session
$call_id = isset($_POST['call_id']) ? $_POST['call_id'] : ($_SESSION['current_call']['call_id'] ?? null);
$user_id = $_SESSION['user_id'] ?? null;

if (!$call_id || !$user_id) {
    echo json_encode(['success' => false, 'error' => 'Missing call_id or user_id']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE video_calls SET last_seen = NOW() WHERE call_id = ? AND user_id = ? AND status = 'active'");
    $stmt->bind_param('ss', $call_id, $user_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
