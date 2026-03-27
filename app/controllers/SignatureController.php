<?php
require_once __DIR__ . '/../models/Signature.php';
require_once __DIR__ . '/../models/Document.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

class SignatureController {
    public static function sign($userId, $docId, $signatureType, $signatureData) {
        // Rate limit
        if (!SecurityHelper::rateLimit($_SERVER['REMOTE_ADDR'] . ':sign', 10, 60)) {
            return ['success' => false, 'message' => 'Too many attempts'];
        }
        // Document hash
        $doc = new Document(); // Load from DB in real app
        $hash = $doc->hash;
        $signatureHash = hash('sha256', $signatureData . $hash);
        // Save signature (pseudo)
        $sig = new Signature();
        $sig->document_id = $docId;
        $sig->user_id = $userId;
        $sig->signature_type = $signatureType;
        $sig->signature_data = $signatureData;
        $sig->signature_hash = $signatureHash;
        $sig->signed_at = date('c');
        $sig->ip_address = $_SERVER['REMOTE_ADDR'];
        $sig->device_info = $_SERVER['HTTP_USER_AGENT'] ?? '';
        // Audit
        AuditLog::log($userId, 'document_signed', $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '', ['doc_id' => $docId]);
        return ['success' => true];
    }
}
