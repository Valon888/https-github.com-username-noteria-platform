<?php
/**
 * Audit Trail & Compliance Logging System
 * Tracks all important actions for compliance, security, and analytics
 */

class AuditTrail {
    private $conn;
    private $user_id;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
        $this->user_id = $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Log an action
     */
    public function log($action, $details = [], $severity = 'info') {
        $action = $this->sanitizeString($action);
        $details_json = json_encode($details);
        $ip_address = $this->getClientIP();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $stmt = $this->conn->prepare("
            INSERT INTO audit_log (
                user_id, action, details, severity, 
                ip_address, user_agent, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        if (!$stmt) {
            error_log("Audit Trail Prepare Error: " . $this->conn->error);
            return false;
        }
        
        $result = $stmt->bind_param(
            "ssssss",
            $this->user_id,
            $action,
            $details_json,
            $severity,
            $ip_address,
            $user_agent
        );
        
        if (!$result) {
            error_log("Audit Trail Bind Error: " . $stmt->error);
            $stmt->close();
            return false;
        }
        
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }
    
    /**
     * Log authentication event
     */
    public function logAuth($type, $email, $success = true) {
        $severity = $success ? 'info' : 'warning';
        $details = [
            'type' => $type, // 'login', 'register', 'logout', 'login_failed'
            'email' => $email,
            'success' => $success
        ];
        
        return $this->log('authentication_' . $type, $details, $severity);
    }
    
    /**
     * Log payment transaction
     */
    public function logPayment($transaction_id, $amount, $status, $method) {
        $details = [
            'transaction_id' => $transaction_id,
            'amount' => $amount,
            'status' => $status,
            'method' => $method
        ];
        
        return $this->log('payment_' . $status, $details, 'info');
    }
    
    /**
     * Log video call
     */
    public function logVideoCall($call_id, $duration_seconds, $status) {
        $details = [
            'call_id' => $call_id,
            'duration_seconds' => $duration_seconds,
            'status' => $status
        ];
        
        return $this->log('video_call_' . $status, $details, 'info');
    }
    
    /**
     * Log document action
     */
    public function logDocument($action, $document_id, $document_name) {
        $details = [
            'document_id' => $document_id,
            'document_name' => $document_name
        ];
        
        return $this->log('document_' . $action, $details, 'info');
    }
    
    /**
     * Log signature action
     */
    public function logSignature($action, $envelope_id, $signer_email) {
        $details = [
            'envelope_id' => $envelope_id,
            'signer_email' => $signer_email
        ];
        
        return $this->log('signature_' . $action, $details, 'info');
    }
    
    /**
     * Log security event
     */
    public function logSecurity($event_type, $description, $severity = 'warning') {
        $details = ['description' => $description];
        return $this->log('security_' . $event_type, $details, $severity);
    }
    
    /**
     * Log data access
     */
    public function logDataAccess($resource_type, $resource_id, $access_type) {
        $details = [
            'resource_type' => $resource_type,
            'resource_id' => $resource_id,
            'access_type' => $access_type // 'read', 'write', 'delete'
        ];
        
        return $this->log('data_access_' . $access_type, $details, 'info');
    }
    
    /**
     * Get audit log for export (compliance)
     */
    public function getLogsForCompliance($days = 90) {
        $date_from = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        $stmt = $this->conn->prepare("
            SELECT * FROM audit_log 
            WHERE created_at >= ? 
            ORDER BY created_at DESC
        ");
        
        $stmt->bind_param("s", $date_from);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get user's audit trail
     */
    public function getUserAuditTrail($user_id, $limit = 100) {
        $stmt = $this->conn->prepare("
            SELECT * FROM audit_log 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        
        $stmt->bind_param("si", $user_id, $limit);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get security events
     */
    public function getSecurityEvents($limit = 50) {
        $stmt = $this->conn->prepare("
            SELECT * FROM audit_log 
            WHERE action LIKE 'security_%' OR severity IN ('warning', 'critical')
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Generate compliance report
     */
    public function generateComplianceReport($start_date, $end_date) {
        $stmt = $this->conn->prepare("
            SELECT 
                action,
                COUNT(*) as count,
                severity,
                MIN(created_at) as first_occurrence,
                MAX(created_at) as last_occurrence
            FROM audit_log 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY action, severity
            ORDER BY created_at DESC
        ");
        
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        }
    }
    
    /**
     * Sanitize string
     */
    private function sanitizeString($str) {
        return substr(preg_replace('/[^a-zA-Z0-9_-]/', '_', $str), 0, 100);
    }
}

// Helper functions
function auditLog($action, $details = [], $severity = 'info') {
    global $audit_trail;
    if (isset($audit_trail)) {
        return $audit_trail->log($action, $details, $severity);
    }
    return false;
}

function auditLogAuth($type, $email, $success = true) {
    global $audit_trail;
    if (isset($audit_trail)) {
        return $audit_trail->logAuth($type, $email, $success);
    }
    return false;
}

function auditLogPayment($transaction_id, $amount, $status, $method) {
    global $audit_trail;
    if (isset($audit_trail)) {
        return $audit_trail->logPayment($transaction_id, $amount, $status, $method);
    }
    return false;
}

?>
