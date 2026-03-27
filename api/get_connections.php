<?php
// get_connections.php - Returns active user sessions or dummy data for testing
header('Content-Type: application/json');
require_once '../confidb.php';
$response = [
    'status' => 'success',
    'connections' => []
];
try {
    // Example: Fetch users who have logged in within the last 30 minutes
    $stmt = $pdo->prepare("SELECT id, emri, mbiemri, roli, last_activity FROM users WHERE last_activity > (NOW() - INTERVAL 30 MINUTE)");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $response['connections'][] = [
            'user_id' => $row['id'],
            'name' => $row['emri'] . ' ' . $row['mbiemri'],
            'role' => $row['roli'],
            'last_activity' => $row['last_activity'],
            'active' => true
        ];
    }
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['error'] = $e->getMessage();
}
echo json_encode($response);
