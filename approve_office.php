<?php
/**
 * Approve Office Registration
 * Aprovojë regjistrimin e zyrës noteriale
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

session_start();
require_once 'config.php';
require_once 'confidb.php';
require_once 'activity_logger.php';

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['roli'] ?? 'user') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Nuk keni permission']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$office_id = intval($data['id'] ?? 0);

if (!$office_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID-ja e zyrës nuk është e vlefshme']);
    exit();
}

try {
    // Update office status to aktiv
    $stmt = $pdo->prepare("
        UPDATE zyrat 
        SET status = 'aktiv', 
            verified = TRUE, 
            verification_date = NOW() 
        WHERE id = ? AND status = 'ne_verifikim'
    ");
    
    if ($stmt->execute([$office_id])) {
        // Log the approval
        log_activity($pdo, $_SESSION['user_id'], 'Aprovim Zyra', "Zyra ID: $office_id u aprovua");
        
        // Send confirmation email (if email function exists)
        $stmt_office = $pdo->prepare("SELECT email, emri FROM zyrat WHERE id = ?");
        $stmt_office->execute([$office_id]);
        $office = $stmt_office->fetch();
        if ($office) {
            // Send email notification
            $to = $office['email'];
            $subject = "Zyra Juaj u Aprovua - Noteria Platform";
            $message = "Përshëndetje {$office['emri']},\n\n"
                . "Zyra juaj noteriale është aprovuar dhe aktualisht aktive në platformën Noteria.\n"
                . "Mund të filloni të përdorni të gjitha shërbimet e platformës.\n\n"
                . "Përterësi informacione kontaktoni support@noteria.com\n\n"
                . "Respektueshëm,\nEkipi Noteria";
            
            $headers = "From: Noteria <noreply@noteria.com>\r\n"
                . "Content-Type: text/plain; charset=UTF-8";
            
            @mail($to, $subject, $message, $headers);
        }
        
        echo json_encode(['success' => true, 'message' => 'Zyra u aprovua me sukses']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gabim në update të statusit']);
    }
} catch (PDOException $e) {
    error_log("Approval error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gabim në server']);
}
