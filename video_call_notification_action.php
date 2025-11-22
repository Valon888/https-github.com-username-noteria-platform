<?php
// video_call_notification_action.php
require_once 'db_connection.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['roli'] !== 'zyra') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses i palejuar.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$call_id = isset($_POST['call_id']) ? intval($_POST['call_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if (!$call_id || !in_array($action, ['accept', 'reject'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Kërkesë e pavlefshme.']);
    exit;
}

// Kontrollo nëse thirrja i përket këtij noteri dhe është ende në pritje
$stmt = $pdo->prepare("SELECT * FROM video_calls WHERE id = ? AND notary_id = ? AND notification_status = 'pending'");
$stmt->execute([$call_id, $user_id]);
$call = $stmt->fetch();
if (!$call) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Thirrja nuk u gjet ose është përditësuar tashmë.']);
    exit;
}

$new_status = $action === 'accept' ? 'accepted' : 'rejected';
$stmt = $pdo->prepare("UPDATE video_calls SET notification_status = ? WHERE id = ?");
$stmt->execute([$new_status, $call_id]);

// (Opsionale) Mund të dërgoni email ose të ruani log për përdoruesin këtu

echo json_encode(['success' => true, 'new_status' => $new_status]);
