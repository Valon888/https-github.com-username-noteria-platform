<?php
function logCameraAccess($pdo, $user_id, $camera_id, $action = 'view') {
    try {
        // Check if camera_access_logs table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'camera_access_logs'");
        $tableExists = $stmt->rowCount() > 0;
        
        if (!$tableExists) {
            // If table doesn't exist, create it
            $pdo->exec("CREATE TABLE IF NOT EXISTS camera_access_logs (
                id INT(11) AUTO_INCREMENT PRIMARY KEY,
                user_id INT(11) NOT NULL,
                camera_id INT(11) NOT NULL,
                access_time DATETIME NOT NULL,
                action ENUM('view', 'export', 'configure', 'maintenance') NOT NULL,
                ip_address VARCHAR(50),
                user_agent TEXT,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (camera_id) REFERENCES security_cameras(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
        
        // Insert log entry
        $stmt = $pdo->prepare("INSERT INTO camera_access_logs 
                            (user_id, camera_id, access_time, action, ip_address, user_agent) 
                            VALUES (?, ?, NOW(), ?, ?, ?)");
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        return $stmt->execute([$user_id, $camera_id, $action, $ip, $userAgent]);
    } catch (PDOException $e) {
        // Silently fail - don't let logging interfere with main functionality
        error_log("Error logging camera access: " . $e->getMessage());
        return false;
    }
}

function generateSecurityAlert($pdo, $camera_id, $alert_type, $alert_level = 'medium', $image_path = null) {
    try {
        // Check if security_alerts table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'security_alerts'");
        $tableExists = $stmt->rowCount() > 0;
        
        if (!$tableExists) {
            // If table doesn't exist, create it
            $pdo->exec("CREATE TABLE IF NOT EXISTS security_alerts (
                id INT(11) AUTO_INCREMENT PRIMARY KEY,
                camera_id INT(11) NOT NULL,
                alert_time DATETIME NOT NULL,
                alert_type ENUM('motion', 'person', 'vehicle', 'animal', 'custom', 'offline') NOT NULL,
                alert_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                image_path VARCHAR(255),
                video_path VARCHAR(255),
                processed TINYINT(1) DEFAULT 0,
                processed_by INT(11),
                resolution_notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (camera_id) REFERENCES security_cameras(id) ON DELETE CASCADE,
                FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
        
        // Insert alert
        $stmt = $pdo->prepare("INSERT INTO security_alerts 
                            (camera_id, alert_time, alert_type, alert_level, image_path) 
                            VALUES (?, NOW(), ?, ?, ?)");
        
        return $stmt->execute([$camera_id, $alert_type, $alert_level, $image_path]);
    } catch (PDOException $e) {
        // Silently fail - don't let logging interfere with main functionality
        error_log("Error generating security alert: " . $e->getMessage());
        return false;
    }
}

function getCameraConfigurationValue($pdo, $camera_id, $setting_name, $default_value = null) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM camera_configurations 
                               WHERE camera_id = ? AND setting_name = ? 
                               ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute([$camera_id, $setting_name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['setting_value'];
        }
        
        return $default_value;
    } catch (PDOException $e) {
        error_log("Error getting camera configuration: " . $e->getMessage());
        return $default_value;
    }
}

function setCameraConfiguration($pdo, $camera_id, $setting_name, $setting_value) {
    try {
        // First check if the setting already exists
        $stmt = $pdo->prepare("SELECT id FROM camera_configurations 
                               WHERE camera_id = ? AND setting_name = ?");
        $stmt->execute([$camera_id, $setting_name]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            // Update existing setting
            $stmt = $pdo->prepare("UPDATE camera_configurations 
                                  SET setting_value = ?, updated_at = CURRENT_TIMESTAMP
                                  WHERE camera_id = ? AND setting_name = ?");
            return $stmt->execute([$setting_value, $camera_id, $setting_name]);
        } else {
            // Insert new setting
            $stmt = $pdo->prepare("INSERT INTO camera_configurations 
                                  (camera_id, setting_name, setting_value)
                                  VALUES (?, ?, ?)");
            return $stmt->execute([$camera_id, $setting_name, $setting_value]);
        }
    } catch (PDOException $e) {
        error_log("Error setting camera configuration: " . $e->getMessage());
        return false;
    }
}

function getActiveSecurityCameras($pdo, $zyra_id = null) {
    try {
        $query = "SELECT * FROM security_cameras WHERE status = 'active'";
        $params = [];
        
        if ($zyra_id !== null) {
            $query .= " AND zyra_id = ?";
            $params[] = $zyra_id;
        }
        
        $query .= " ORDER BY name";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting active cameras: " . $e->getMessage());
        return [];
    }
}

function getUnprocessedSecurityAlerts($pdo, $limit = 10) {
    try {
        $query = "SELECT a.*, c.name AS camera_name 
                  FROM security_alerts a
                  JOIN security_cameras c ON a.camera_id = c.id
                  WHERE a.processed = 0
                  ORDER BY a.alert_level DESC, a.alert_time DESC
                  LIMIT ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting unprocessed alerts: " . $e->getMessage());
        return [];
    }
}
?>