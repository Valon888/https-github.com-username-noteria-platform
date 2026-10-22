<?php
// check_connections.php
require_once '../includes/db.php';

$timeout = 15; // seconds

$stmt = $pdo->prepare("SELECT * FROM user_sessions WHERE status='connected' AND last_activity < (NOW() - INTERVAL ? SECOND)");
$stmt->execute([$timeout]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($sessions as $session) {
    // Mark as disconnected
    $update = $pdo->prepare("UPDATE user_sessions SET status='disconnected' WHERE user_id=? AND session_id=? AND appointment_id=?");
    $update->execute([$session['user_id'], $session['session_id'], $session['appointment_id']]);

    // Log event
    $log = $pdo->prepare("INSERT INTO connection_logs (user_id, event_type, event_time, reason) VALUES (?, 'disconnect', NOW(), 'possible_power_outage')");
    $log->execute([$session['user_id']]);
}