<?php
// Refuzon një thirrje video
header('Content-Type: application/json');

// Përfshirja e file-it për lidhjen me bazën e të dhënave
require_once('db_connect.php');

// Sigurohemi që përdoruesi është i loguar
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Nuk jeni të loguar']);
    exit;
}

// Kontrollon nëse të dhënat janë marrë në formatin e duhur JSON
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['userId']) || empty($data['userId'])) {
    echo json_encode(['success' => false, 'error' => 'Të dhëna të paplota']);
    exit;
}

$callerId = $data['userId'];
$noterId = $_SESSION['user_id'];

try {
    // Përditësojmë statusin e thirrjes në bazën e të dhënave
    $stmt = $conn->prepare("
        UPDATE video_calls 
        SET status = 'declined', 
            updated_at = NOW() 
        WHERE caller_id = :caller_id 
        AND noteri_id = :noteri_id 
        AND status = 'active'
    ");
    
    $stmt->bindParam(':caller_id', $callerId, PDO::PARAM_INT);
    $stmt->bindParam(':noteri_id', $noterId, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Thirrja nuk u gjet ose është mbyllur tashmë']);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Gabim në lidhjen me bazën e të dhënave: ' . $e->getMessage()
    ]);
}
?>