<?php
// filepath: api/ad_impression.php
// Track advertisement impressions

header('Content-Type: application/json');

require_once __DIR__ . '/../confidb.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $ad_id = intval($data['ad_id'] ?? 0);
    
    if (!$ad_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid ad_id']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'] ?? null;
    $placement = $_POST['placement'] ?? 'unknown';
    
    recordAdImpression($pdo, $ad_id, $placement, $user_id);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
