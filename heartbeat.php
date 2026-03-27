<?php
// heartbeat.php - update last_seen for active video call
header('Content-Type: application/json');
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "noteria";

// Accept call_id from POST or session
$call_id = isset($_POST['call_id']) ? $_POST['call_id'] : ($_SESSION['current_call']['call_id'] ?? null);
$user_id = $_SESSION['user_id'] ?? null;

if (!$call_id || !$user_id) {
    echo json_encode(['success' => false, 'error' => 'Missing call_id or user_id', 'debug' => ['call_id' => $call_id, 'user_id' => $user_id]]);
    exit;
}

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("UPDATE video_calls SET last_seen = NOW() WHERE call_id = :call_id AND user_id = :user_id AND status = 'active'");
    $stmt->execute(['call_id' => $call_id, 'user_id' => $user_id]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'sql_error_code' => $e->getCode()]);
}
?>
