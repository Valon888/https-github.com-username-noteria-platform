<?php
/**
 * Activity Logging Helper Functions
 * Funksionet ndihmëse për regjistrim aktivitetesh
 */

/**
 * Log user activity to audit trail
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param string $action Action description
 * @param string $details Additional details
 * @param string $severity Log severity (info, warning, error)
 * @return bool
 */
function log_activity($pdo, $user_id, $action, $details = '', $severity = 'info') {
    try {
        // Check if audit_log table exists
        $checkTable = $pdo->query("SHOW TABLES LIKE 'audit_log'");
        if ($checkTable->rowCount() == 0) {
            error_log("Warning: audit_log table does not exist. Creating it...");
            create_audit_log_table($pdo);
        }

        $ip_address = get_client_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $timestamp = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("
            INSERT INTO audit_log (
                user_id, action, details, severity, 
                ip_address, user_agent, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $user_id,
            htmlspecialchars($action),
            htmlspecialchars($details),
            htmlspecialchars($severity),
            $ip_address,
            substr($user_agent, 0, 255),
            $timestamp
        ]);
    } catch (PDOException $e) {
        error_log("Activity Log Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get client IP address
 * @return string
 */
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * Create audit_log table if it doesn't exist
 * @param PDO $pdo Database connection
 */
function create_audit_log_table($pdo) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS audit_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                action VARCHAR(255) NOT NULL,
                details LONGTEXT,
                severity VARCHAR(50) DEFAULT 'info',
                ip_address VARCHAR(45),
                user_agent VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_action (action),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        error_log("audit_log table created successfully");
    } catch (PDOException $e) {
        error_log("Error creating audit_log table: " . $e->getMessage());
    }
}

/**
 * Get activity logs for a user
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $limit Number of records to return
 * @return array
 */
function get_user_activity_logs($pdo, $user_id, $limit = 50) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, action, details, severity, ip_address, created_at
            FROM audit_log
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching activity logs: " . $e->getMessage());
        return [];
    }
}

/**
 * Clear old activity logs (older than specified days)
 * @param PDO $pdo Database connection
 * @param int $days Number of days to keep
 * @return bool
 */
function clear_old_activity_logs($pdo, $days = 90) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM audit_log
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        return $stmt->execute([$days]);
    } catch (PDOException $e) {
        error_log("Error clearing old activity logs: " . $e->getMessage());
        return false;
    }
}
