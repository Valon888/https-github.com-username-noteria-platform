<?php
// Ruaj shënimet e thirrjes video
header('Content-Type: application/json');

// Përfshirja e file-it për lidhjen me bazën e të dhënave
require_once('db_connect.php');

// Sigurohemi që përdoruesi është i loguar
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Nuk jeni të loguar']);
    exit;
}

// Lexojmë të dhënat JSON nga kërkesa
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['call_id']) || !isset($data['notes']) || empty($data['notes'])) {
    echo json_encode(['success' => false, 'error' => 'Të dhëna të paplota']);
    exit;
}

$callId = $data['call_id'];
$notes = $data['notes'];
$userId = $_SESSION['user_id'];

try {
    // Kontrollojmë nëse përdoruesi ka të drejtë të shtojë shënime për këtë thirrje
    $stmt = $conn->prepare("
        SELECT id FROM video_calls 
        WHERE id = :call_id AND (caller_id = :user_id OR noteri_id = :user_id)
    ");
    
    $stmt->bindParam(':call_id', $callId, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Nuk keni të drejtë të shtoni shënime për këtë thirrje']);
        exit;
    }
    
    // Kontrollojmë nëse ekzistojnë shënime për këtë thirrje
    $stmtCheck = $conn->prepare("
        SELECT id FROM call_notes 
        WHERE call_id = :call_id
    ");
    
    $stmtCheck->bindParam(':call_id', $callId, PDO::PARAM_INT);
    $stmtCheck->execute();
    
    if ($stmtCheck->rowCount() > 0) {
        // Përditësojmë shënimet ekzistuese
        $stmt = $conn->prepare("
            UPDATE call_notes 
            SET notes = :notes, updated_at = NOW() 
            WHERE call_id = :call_id
        ");
    } else {
        // Shtojmë shënime të reja
        $stmt = $conn->prepare("
            INSERT INTO call_notes (call_id, user_id, notes) 
            VALUES (:call_id, :user_id, :notes)
        ");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    }
    
    $stmt->bindParam(':call_id', $callId, PDO::PARAM_INT);
    $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Gabim në lidhjen me bazën e të dhënave: ' . $e->getMessage()
    ]);
}
?>