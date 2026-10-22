<?php
// api/heartbeat.php
require_once '../includes/db.php';
require_once '../includes/session.php';

// CSRF protection function fallback if not already defined
if (!function_exists('validate_csrf_token')) {
    function validate_csrf_token($token) {
        // Simple example: check token in session
        session_start();
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

require_once '../includes/csrf.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// CSRF Protection
if (!validate_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    http_response_code(403); exit;
}

// Input validation
$user_id = filter_var($data['user_id'] ?? null, FILTER_VALIDATE_INT);
$session_id = preg_replace('/[^a-zA-Z0-9]/', '', $data['session_id'] ?? '');
$appointment_id = filter_var($data['appointment_id'] ?? null, FILTER_VALIDATE_INT);
$timestamp = filter_var($data['timestamp'] ?? null, FILTER_VALIDATE_INT);

if (!$user_id || !$session_id || !$appointment_id || !$timestamp) {
    http_response_code(400); exit;
}

// Session validation (customize as needed)
function is_valid_session($user_id, $session_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_sessions WHERE user_id = ? AND session_id = ? AND status = 'connected'");
    $stmt->execute([$user_id, $session_id]);
    return $stmt->fetchColumn() > 0;
}

if (!is_valid_session($user_id, $session_id)) {
    http_response_code(401); exit;
}

// Rate limiting (simple example)
session_start();
if (!isset($_SESSION['last_heartbeat'])) $_SESSION['last_heartbeat'] = 0;
if (time() - $_SESSION['last_heartbeat'] < 2) {
    http_response_code(429); exit;
}
$_SESSION['last_heartbeat'] = time();

// Update last_activity
$stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_id, appointment_id, last_activity, status)
    VALUES (?, ?, ?, NOW(), 'connected')
    ON DUPLICATE KEY UPDATE last_activity=NOW(), status='connected'");
$stmt->execute([$user_id, $session_id, $appointment_id]);

// Log reconnect if previously disconnected
$stmt = $pdo->prepare("SELECT status FROM user_sessions WHERE user_id=? AND session_id=? AND appointment_id=?");
$stmt->execute([$user_id, $session_id, $appointment_id]);
$status = $stmt->fetchColumn();
if ($status === 'disconnected') {
    $log = $pdo->prepare("INSERT INTO connection_logs (user_id, event_type, event_time, reason) VALUES (?, 'reconnect', NOW(), NULL)");
    $log->execute([$user_id]);
}

echo json_encode(['success' => true]);