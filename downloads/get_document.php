<?php
/**
 * Secure Document Download Handler
 * 
 * Kontrollon autentifikimin e sesionit para se të shfaqet dokumenti
 * Shfaq dokumentet direkt nga private_documents folder
 * 
 * Usage: <a href="downloads/get_document.php?id=123">Download Document</a>
 */

session_start();

// ==========================================
// KONTROLLO AUTENTIFIKIMIN
// ==========================================
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('<h1>401 - Nuk jeni i kyçur</h1><p>Ju lutemi kyçuni për të aksesuar dokumentet.</p>');
}

require_once __DIR__ . '/../confidb.php';

// ==========================================
// VALIDIZOHEN PARAMETRAT
// ==========================================
$doc_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$doc_id) {
    http_response_code(400);
    die('<h1>400 - ID i pavlefshëm</h1><p>Ju lutemi sigurohuni se dokumenti ID është i saktë.</p>');
}

try {
    // ==========================================
    // KONTROLLO NËSE PËRDORUESI KA QASJE NË DOKUMENTIN
    // ==========================================
    $stmt = $pdo->prepare("
        SELECT 
            d.id,
            d.file_path,
            d.user_id,
            d.file_size,
            d.file_type,
            d.created_at
        FROM documents d
        WHERE 
            d.id = ? 
            AND (
                d.user_id = ? 
                OR ? IN (SELECT user_id FROM admins WHERE status = 'active')
            )
        LIMIT 1
    ");
    
    $user_id = $_SESSION['user_id'];
    $stmt->execute([$doc_id, $user_id, $user_id]);
    $document = $stmt->fetch();
    
    if (!$document) {
        http_response_code(404);
        die('<h1>404 - Dokumenti nuk u gjet</h1><p>Dokumenti nuk ekziston ose nuk keni qasje në të.</p>');
    }
    
    // ==========================================
    // VERIFIKOJMË SKEDARIN
    // ==========================================
    $file_path = __DIR__ . '/../private_documents/' . basename($document['file_path']);
    
    // Siguro se skedari ekziston
    if (!file_exists($file_path)) {
        error_log("Document file not found: {$file_path}");
        http_response_code(404);
        die('<h1>404 - Skedari nuk gjendet</h1><p>Skedari fizik nuk ekziston më në server.</p>');
    }
    
    // Siguro se skedari është në private_documents directory
    $real_path = realpath($file_path);
    $private_dir = realpath(__DIR__ . '/../private_documents');
    
    if (strpos($real_path, $private_dir) !== 0) {
        error_log("Unauthorized file access attempt: {$real_path}");
        http_response_code(403);
        die('<h1>403 - Akses i ndaluar</h1><p>Nuk mund të aksesoni këtë skedar.</p>');
    }
    
    // ==========================================
    // LOG DOWNLOAD ACTIVITY
    // ==========================================
    error_log("DOCUMENT_DOWNLOAD: User {$user_id} downloaded document {$doc_id} from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    
    try {
        $log_stmt = $pdo->prepare("
            INSERT INTO document_download_logs (document_id, user_id, ip_address, downloaded_at)
            VALUES (?, ?, ?, NOW())
        ");
        $log_stmt->execute([$doc_id, $user_id, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    } catch (Exception $e) {
        // Vazhdo edhe nëse logging dështon
        error_log("Failed to log document download: " . $e->getMessage());
    }
    
    // ==========================================
    // DËRGO SKEDARIN
    // ==========================================
    
    // Përcakto MIME type-in
    $mime_type = $document['file_type'] ?? 'application/octet-stream';
    
    // Emrin i file-it për download
    $original_name = basename($document['file_path']);
    if (strpos($original_name, '_') !== false) {
        // Nëse është hashueme, hiq hash-in
        $parts = explode('_', $original_name, 2);
        $original_name = count($parts) > 1 ? $parts[1] : $original_name;
    }
    
    // Headers për download
    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . filesize($file_path));
    header('Content-Disposition: attachment; filename="' . urlencode($original_name) . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Dërgo skedarin
    readfile($file_path);
    exit;
    
} catch (PDOException $e) {
    error_log("Database error in document download: " . $e->getMessage());
    http_response_code(500);
    die('<h1>500 - Gabim serveri</h1><p>Ndodhi një gabim teknik. Ju lutemi provoni përsëri.</p>');
} catch (Exception $e) {
    error_log("Unexpected error in document download: " . $e->getMessage());
    http_response_code(500);
    die('<h1>500 - Gabim serveri</h1><p>Ndodhi një gabim teknik. Ju lutemi provoni përsëri.</p>');
}
?>
