<?php
/**
 * DocuSign Webhook Callback Handler
 * Receives signature events from DocuSign
 */

require_once 'db_connection.php';

// Verify webhook signature (optional but recommended)
$raw_body = file_get_contents("php://input");
$data = json_decode($raw_body, true);

error_log("DocuSign Webhook: " . json_encode($data));

if (!isset($data['data']['envelopeId'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid webhook']);
    exit();
}

$envelopeId = $data['data']['envelopeId'];
$eventType = $data['event'] ?? 'unknown';
$status = $data['data']['status'] ?? 'unknown';

// Map DocuSign status to our status
$status_map = [
    'sent' => 'sent',
    'delivered' => 'delivered',
    'completed' => 'signed',
    'declined' => 'declined',
    'voided' => 'voided'
];

$our_status = $status_map[$status] ?? $status;

// Update envelope status in database
$stmt = $conn->prepare("UPDATE docusign_envelopes SET status = ?, updated_at = NOW() WHERE envelope_id = ?");
$stmt->bind_param("ss", $our_status, $envelopeId);
$success = $stmt->execute();
$stmt->close();

if ($success && $our_status === 'signed') {
    // Get envelope details
    $stmt = $conn->prepare("SELECT call_id, signer_email FROM docusign_envelopes WHERE envelope_id = ?");
    $stmt->bind_param("s", $envelopeId);
    $stmt->execute();
    $envelope = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($envelope) {
        // Update signed_at timestamp
        $stmt = $conn->prepare("UPDATE docusign_envelopes SET signed_at = NOW() WHERE envelope_id = ?");
        $stmt->bind_param("s", $envelopeId);
        $stmt->execute();
        $stmt->close();
        
        // Log audit event
        $stmt = $conn->prepare("INSERT INTO audit_log (action, description) VALUES ('document_signed', ?)");
        $desc = "Dokumenti nënshkruar elektronikisht: " . $envelopeId;
        $stmt->bind_param("s", $desc);
        $stmt->execute();
        $stmt->close();
        
        // Send notification email
        $to = $envelope['signer_email'];
        $subject = "✅ Dokumenti juaj u nënshkrua me sukses - Noteria";
        $message = "Dokumenti juaj u nënshkrua dhe u përfundua me sukses në platformën Noteria.";
        $headers = "Content-Type: text/html; charset=UTF-8\r\n";
        
        mail($to, $subject, $message, $headers);
    }
}

http_response_code(200);
echo json_encode(['status' => 'received']);

?>
