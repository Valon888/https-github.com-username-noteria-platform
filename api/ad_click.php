<?php
// filepath: api/ad_click.php
// Track advertisement clicks

header('Content-Type: application/json');

require_once __DIR__ . '/../confidb.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $ad_id = intval($data['ad_id'] ?? 0);
    $impression_id = intval($data['impression_id'] ?? 0);
    
    if (!$ad_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid ad_id']);
        exit;
    }
    
    recordAdClick($pdo, $ad_id, $impression_id ? $impression_id : null);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
