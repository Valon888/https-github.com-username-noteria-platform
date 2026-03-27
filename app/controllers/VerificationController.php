<?php
require_once __DIR__ . '/../models/Document.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

class VerificationController {
    public static function verify($uploadedFilePath) {
        $hash = Document::hashFile($uploadedFilePath);
        // In real app, lookup in DB
        // $doc = Document::findByHash($hash);
        $doc = null; // placeholder
        if (!$doc) return ['valid' => false, 'reason' => 'Document not found'];
        return ['valid' => true, 'doc' => $doc];
    }
}
