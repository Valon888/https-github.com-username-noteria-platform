<?php
/**
 * Handler për Click-in në Reklama
 * Regjistron kur përdoruesi klikon në një reklam
 */

require_once 'confidb.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ad_id'])) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    
    $ad_id = intval($_POST['ad_id']);
    $user_id = $_SESSION['user_id'] ?? null;
    
    // Regjistro click-in
    $stmt = $pdo->prepare("UPDATE advertisements SET total_clicks = total_clicks + 1 WHERE id = ?");
    $stmt->execute([$ad_id]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
