<?php
/**
 * API endpoint to check reservation/application status
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once 'config.php';
require_once 'confidb.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get reference number from POST
$referenceNumber = isset($_POST['reference_number']) ? trim($_POST['reference_number']) : '';

if (empty($referenceNumber)) {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'title' => 'Gabim',
        'message' => 'Ju lutemi shkruani numrin e referencës.',
        'icon' => 'fas fa-exclamation-triangle'
    ]);
    exit;
}

try {
    // Check in reservations table
    $stmt = $pdo->prepare("
        SELECT r.id, r.status, r.service, r.date, r.time, r.document_path,
               u.emri, u.mbiemri, u.email, z.emri as zyra_name
        FROM reservations r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN zyrat z ON r.zyra_id = z.id
        WHERE r.id = ? OR CONCAT('RES-', r.id) = ?
        LIMIT 1
    ");
    
    $stmt->execute([$referenceNumber, $referenceNumber]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($reservation) {
        // Map reservation status to user-friendly status
        $status_map = [
            'në pritje' => ['pending', 'Në Pritje', 'Rezervimi juaj është regjistruar dhe në pritje të aprovimit.'],
            'aprovohet' => ['success', 'Aprovuar', 'Rezervimi juaj është aprovuar. Ju lutem paraqituni në zyrë në datën dhe orën e caktuar.'],
            'refuzohet' => ['error', 'Refuzuar', 'Rezervimi juaj nuk u aprovua. Ju lutemi kontaktoni zyrën për më shumë detaje.'],
            'e_plotesuar' => ['success', 'Përfunduar', 'Dokumentet tuaja janë përpunuar dhe gati. Ju lutemi kontaktoni zyrën.']
        ];
        
        $res_status = strtolower($reservation['status']);
        $display_status = $status_map[$res_status] ?? ['pending', 'Në Proces', 'Kërkesa juaj është duke u përpunuar.'];
        
        $message = $display_status[2];
        if ($reservation['date']) {
            $message .= ' Termin: ' . $reservation['date'] . ' ' . $reservation['time'];
        }
        if ($reservation['zyra_name']) {
            $message .= ' Zyra: ' . htmlspecialchars($reservation['zyra_name']);
        }
        
        echo json_encode([
            'success' => true,
            'status' => $display_status[0],
            'title' => $display_status[1],
            'message' => $message,
            'icon' => $display_status[0] === 'success' ? 'fas fa-check-circle' : 
                     ($display_status[0] === 'pending' ? 'fas fa-clock' : 'fas fa-times-circle'),
            'reference_type' => 'Rezervim'
        ]);
        exit;
    }
    
    // Check in payments table
    $stmt = $pdo->prepare("
        SELECT p.id, p.status, p.amount, p.created_at, p.service_type,
               u.emri, u.mbiemri, u.email
        FROM payments p
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.uuid = ? OR p.transaction_id = ? OR CONCAT('PAY-', p.id) = ?
        LIMIT 1
    ");
    
    $stmt->execute([$referenceNumber, $referenceNumber, $referenceNumber]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($payment) {
        // Map payment status to user-friendly status
        $status_map = [
            'pending' => ['pending', 'Në Pritje', 'Pagesa juaj është regjistruar dhe në pritje.'],
            'completed' => ['success', 'Përfunduar', 'Pagesa juaj u përpunua me sukses.'],
            'failed' => ['error', 'Dështoi', 'Pagesa juaj dështoi. Ju lutemi provoni përsëri.'],
            'refunded' => ['pending', 'Kthyer', 'Pagesa juaj u kthe. Kontaktoni suportin.']
        ];
        
        $pay_status = strtolower($payment['status']);
        $display_status = $status_map[$pay_status] ?? ['pending', 'Në Proces', 'Pagesa juaj është duke u përpunuar.'];
        
        $message = $display_status[2];
        $message .= ' Shuma: €' . number_format($payment['amount'], 2);
        if ($payment['service_type']) {
            $message .= ' Shërbim: ' . htmlspecialchars($payment['service_type']);
        }
        
        echo json_encode([
            'success' => true,
            'status' => $display_status[0],
            'title' => $display_status[1],
            'message' => $message,
            'icon' => $display_status[0] === 'success' ? 'fas fa-check-circle' : 
                     ($display_status[0] === 'pending' ? 'fas fa-clock' : 'fas fa-times-circle'),
            'reference_type' => 'Pagesa'
        ]);
        exit;
    }
    
    // No match found
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'title' => 'Statusi i Panjohur',
        'message' => 'Numri i referencës nuk është i vlefshëm ose nuk ekziston në sistem. Ju lutemi kontrolloni numrin ose kontaktoni suportin.',
        'icon' => 'fas fa-exclamation-triangle'
    ]);
    
} catch (Exception $e) {
    error_log('Status check error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'title' => 'Gabim në Sistem',
        'message' => 'Pati një gabim gjatë kontrollimit të statusit. Ju lutemi provoni më vonë.',
        'icon' => 'fas fa-exclamation-triangle'
    ]);
}
?>
