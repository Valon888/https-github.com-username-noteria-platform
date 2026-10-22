<?php
/**
 * Track Ad Clicks
 * Gjurmim i klikov të reklamave
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

require_once 'config.php';

$response = ['success' => false, 'message' => 'No ad ID provided'];

try {
    // Get ad_id from either GET or POST
    $ad_id = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $ad_id = isset($data['ad_id']) ? (int)$data['ad_id'] : null;
    } elseif (isset($_GET['ad_id'])) {
        $ad_id = (int)$_GET['ad_id'];
    } elseif (isset($_POST['ad_id'])) {
        $ad_id = (int)$_POST['ad_id'];
    }
    
    if ($ad_id) {
        // Update clicks count
        $stmt = $pdo->prepare("UPDATE ads SET clicks = clicks + 1 WHERE id = ?");
        $stmt->execute([$ad_id]);
        
        // Log interaction
        $stmt = $pdo->prepare("
            INSERT INTO ad_interactions (ad_id, interaction_type, user_ip, user_agent, referer) 
            VALUES (?, 'click', ?, ?, ?)
        ");
        $stmt->execute([
            $ad_id,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_REFERER'] ?? ''
        ]);
        
        $response = [
            'success' => true,
            'message' => 'Click tracked successfully',
            'ad_id' => $ad_id
        ];
        http_response_code(200);
    } else {
        $response = [
            'success' => false,
            'message' => 'Invalid or missing ad ID'
        ];
        http_response_code(400);
    }
} catch (Exception $e) {
    error_log("Error tracking click: " . $e->getMessage());
    $response = [
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ];
    http_response_code(500);
}

echo json_encode($response);
