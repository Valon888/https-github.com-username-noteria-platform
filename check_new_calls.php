<?php
// Kontrollon për thirrje të reja video për noterin e loguar
header('Content-Type: application/json');

// Përfshirja e file-it për lidhjen me bazën e të dhënave
require_once('db_connect.php');

// Sigurohemi që përdoruesi është i loguar
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Nuk jeni të loguar', 'hasNewCalls' => false]);
    exit;
}

// ID e noterit të loguar
$noterId = $_SESSION['user_id'];

try {
    // Kontrollon për thirrje të reja në bazën e të dhënave
    $stmt = $conn->prepare("
        SELECT c.*, u.emri, u.mbiemri, 
        n.permbajtja as koment, 
        CASE 
            WHEN c.has_video = 1 THEN 'video'
            ELSE 'audio'
        END as lloji_thirrjes 
        FROM video_calls c 
        JOIN users u ON c.caller_id = u.id
        LEFT JOIN notes n ON c.id = n.call_id
        WHERE c.noteri_id = :noteri_id 
        AND (c.status = 'active' OR c.status = 'pending')
        AND c.created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    
    $stmt->bindParam(':noteri_id', $noterId, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $call = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode([
            'hasNewCalls' => true,
            'callerId' => $call['caller_id'],
            'callerName' => $call['emri'] . ' ' . $call['mbiemri'],
            'callId' => $call['id'],
            'callTime' => $call['created_at'],
            'callStatus' => $call['status'],
            'callType' => $call['lloji_thirrjes'],
            'comment' => $call['koment'] ?? '',
            'durationLimit' => $call['duration_limit'] ?? 0,
            'isUrgent' => ($call['is_urgent'] == 1) ? true : false
        ]);
    } else {
        echo json_encode(['hasNewCalls' => false]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'error' => 'Gabim në lidhjen me bazën e të dhënave: ' . $e->getMessage(),
        'hasNewCalls' => false
    ]);
}
?>