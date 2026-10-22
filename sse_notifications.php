<?php
// sse_notifications.php
// Disable error reporting to prevent breaking the stream
ini_set('display_errors', 0);

// Clear any previous output
if (ob_get_level()) ob_end_clean();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Nginx: disable buffering

require_once 'config.php';
require_once 'confidb.php';
require_once 'notifications_helper.php';

// Session is already started by config.php -> session_helper.php
// We just need to get the user_id and close it to prevent locking
$user_id = $_SESSION['user_id'] ?? null;
session_write_close(); 

if (!$user_id) {
    echo "data: " . json_encode(['error' => 'Unauthorized']) . "\n\n";
    flush();
    exit();
}

// Set time limit to 0 to allow long running script
set_time_limit(0);

// Keep checking for new notifications
$last_check = time();

while (true) {
    // Check connection status
    if (connection_aborted()) {
        break;
    }

    try {
        // Merr njoftimet e reja të palexuara
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at ASC");
        $stmt->execute([$user_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($notifications) {
            foreach ($notifications as $notif) {
                echo "data: " . json_encode($notif) . "\n\n";
                flush();
            }
            
            // Shëno si të lexuara pasi janë dërguar
            markNotificationsAsRead($pdo, $user_id);
        } else {
            // Dërgo heartbeat për të mbajtur lidhjen gjallë
            echo ": heartbeat\n\n";
            flush();
        }

    } catch (Exception $e) {
        // In case of DB error, wait a bit and retry
    }

    // Wait 2 seconds before next check
    sleep(2);
}
?>
