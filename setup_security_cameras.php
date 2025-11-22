<?php
require_once 'confidb.php';

// Set up the security camera tables
try {
    // Create security_cameras table
    $pdo->exec("CREATE TABLE IF NOT EXISTS security_cameras (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        location VARCHAR(255) NOT NULL,
        ip_address VARCHAR(50) NOT NULL,
        model VARCHAR(100),
        status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
        zyra_id INT(11),
        resolution VARCHAR(50),
        feed_url VARCHAR(255) NOT NULL,
        username VARCHAR(100),
        password VARCHAR(255),
        last_maintenance DATETIME,
        installation_date DATE,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (zyra_id) REFERENCES zyrat(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Create security_recordings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS security_recordings (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        camera_id INT(11) NOT NULL,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_size BIGINT,
        recording_type ENUM('scheduled', 'motion', 'manual', 'alarm') DEFAULT 'scheduled',
        status ENUM('available', 'archived', 'deleted') DEFAULT 'available',
        viewed TINYINT(1) DEFAULT 0,
        flagged TINYINT(1) DEFAULT 0,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (camera_id) REFERENCES security_cameras(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Create security_alerts table
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

    // Create camera_access_logs table
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
    
    // Create camera_configurations table
    $pdo->exec("CREATE TABLE IF NOT EXISTS camera_configurations (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        camera_id INT(11) NOT NULL,
        setting_name VARCHAR(100) NOT NULL,
        setting_value TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (camera_id) REFERENCES security_cameras(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    echo "<div class='success'>Security camera tables created successfully!</div>";
    
    // Insert some sample camera data
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM security_cameras");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        // Get available zyra_id values
        $stmt = $pdo->query("SELECT id FROM zyrat LIMIT 5");
        $zyra_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // If we have offices, add sample cameras
        if (!empty($zyra_ids)) {
            $sample_cameras = [
                [
                    'name' => 'Kamera hyrëse',
                    'location' => 'Dera kryesore',
                    'ip_address' => '192.168.1.100',
                    'model' => 'Hikvision DS-2CD2385G1-I',
                    'resolution' => '4K (8MP)',
                    'feed_url' => 'rtsp://192.168.1.100:554/Streaming/Channels/101',
                ],
                [
                    'name' => 'Kamera e dhomës kryesore',
                    'location' => 'Dhoma e pritjes',
                    'ip_address' => '192.168.1.101',
                    'model' => 'Dahua IPC-HDW5831R-ZE',
                    'resolution' => '1080p',
                    'feed_url' => 'rtsp://192.168.1.101:554/Streaming/Channels/101',
                ],
                [
                    'name' => 'Kamera e parkimit',
                    'location' => 'Parkingun i jashtëm',
                    'ip_address' => '192.168.1.102',
                    'model' => 'Axis P3245-LVE',
                    'resolution' => '1080p',
                    'feed_url' => 'rtsp://192.168.1.102:554/Streaming/Channels/101',
                ],
                [
                    'name' => 'Kamera e korridorit',
                    'location' => 'Korridori kryesor',
                    'ip_address' => '192.168.1.103',
                    'model' => 'Avigilon 4.0C-H5A-BO1-IR',
                    'resolution' => '4MP',
                    'feed_url' => 'rtsp://192.168.1.103:554/Streaming/Channels/101',
                ],
            ];
            
            $stmt = $pdo->prepare("INSERT INTO security_cameras (name, location, ip_address, model, status, zyra_id, resolution, feed_url, installation_date) VALUES (?, ?, ?, ?, 'active', ?, ?, ?, CURRENT_DATE)");
            
            foreach ($sample_cameras as $index => $camera) {
                // Use modulo to cycle through zyra_ids
                $zyra_id = $zyra_ids[$index % count($zyra_ids)];
                
                $stmt->execute([
                    $camera['name'],
                    $camera['location'],
                    $camera['ip_address'],
                    $camera['model'],
                    $zyra_id,
                    $camera['resolution'],
                    $camera['feed_url']
                ]);
            }
            
            echo "<div class='success'>Sample cameras added successfully!</div>";
        }
    }
    
} catch (PDOException $e) {
    echo "<div class='error'>Database error: " . $e->getMessage() . "</div>";
}
?>

<style>
.success {
    padding: 10px;
    background-color: #d4edda;
    color: #155724;
    margin: 10px 0;
    border-radius: 5px;
}
.error {
    padding: 10px;
    background-color: #f8d7da;
    color: #721c24;
    margin: 10px 0;
    border-radius: 5px;
}
</style>

<p><a href="admin_security.php">Go to Security Camera Management</a></p>