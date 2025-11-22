<?php
// Kontrollon statusin e thirrjes video
header('Content-Type: application/json');

// Përfshirja e file-it për lidhjen me bazën e të dhënave
require_once('db_connect.php');

// Sigurohemi që ID e thirrjes është e disponueshme
if (!isset($_GET['call_id'])) {
    echo json_encode(['error' => 'Mungon ID e thirrjes', 'status' => 'error']);
    exit;
}

$callId = $_GET['call_id'];

try {
    // Marrim statusin e thirrjes nga baza e të dhënave
    $stmt = $conn->prepare("
        SELECT status 
        FROM video_calls 
        WHERE id = :call_id
    ");
    
    $stmt->bindParam(':call_id', $callId, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $call = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode([
            'status' => $call['status']
        ]);
    } else {
        echo json_encode([
            'error' => 'Thirrja nuk u gjet',
            'status' => 'error'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'error' => 'Gabim në lidhjen me bazën e të dhënave: ' . $e->getMessage(),
        'status' => 'error'
    ]);
}
?>