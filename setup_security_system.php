<?php
require_once 'confidb.php';

// This script will create all the tables needed for the security camera system

try {
    // 1. Create security_cameras table
    $pdo->exec("CREATE TABLE IF NOT EXISTS security_cameras (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        location VARCHAR(255) NOT NULL,
        ip_address VARCHAR(100),
        url VARCHAR(255) NOT NULL,
        type ENUM('ip', 'rtsp', 'onvif', 'webcam') DEFAULT 'ip',
        username VARCHAR(100),
        password VARCHAR(255),
        zyra_id INT(11),
        status ENUM('active', 'inactive', 'maintenance', 'offline') DEFAULT 'inactive',
        last_online DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (zyra_id) REFERENCES zyrat(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    echo "Created security_cameras table<br>";
    
    // 2. Create camera_recordings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS camera_recordings (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        camera_id INT(11) NOT NULL,
        start_time DATETIME NOT NULL,
        end_time DATETIME,
        file_path VARCHAR(255) NOT NULL,
        file_size INT(11) DEFAULT 0,
        duration INT(11) DEFAULT 0,
        recording_type ENUM('continuous', 'motion', 'manual', 'scheduled') DEFAULT 'manual',
        status ENUM('recording', 'completed', 'error') DEFAULT 'recording',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (camera_id) REFERENCES security_cameras(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    echo "Created camera_recordings table<br>";
    
    // 3. Create camera_configurations table
    $pdo->exec("CREATE TABLE IF NOT EXISTS camera_configurations (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        camera_id INT(11) NOT NULL,
        setting_name VARCHAR(100) NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (camera_id) REFERENCES security_cameras(id) ON DELETE CASCADE,
        UNIQUE KEY unique_camera_setting (camera_id, setting_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    echo "Created camera_configurations table<br>";
    
    // 4. Create security_alerts table
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
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (camera_id) REFERENCES security_cameras(id) ON DELETE CASCADE,
        FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    echo "Created security_alerts table<br>";
    
    // 5. Create camera_access_logs table
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
    
    echo "Created camera_access_logs table<br>";
    
    echo "<br>All tables created successfully!<br>";
    
    // Check if we should add demo data
    $add_demo = isset($_GET['demo']) && $_GET['demo'] == 1;
    
    if ($add_demo) {
        // Insert demo cameras
        $stmt = $pdo->query("SELECT id FROM zyrat LIMIT 1");
        $zyra = $stmt->fetch(PDO::FETCH_ASSOC);
        $zyra_id = $zyra ? $zyra['id'] : 1;
        
        $demo_cameras = [
            [
                'name' => 'Kamëra Hyrëse', 
                'location' => 'Hyrja Kryesore', 
                'url' => 'rtsp://example.com/entrance', 
                'type' => 'rtsp',
                'status' => 'active'
            ],
            [
                'name' => 'Kamëra Parkimi', 
                'location' => 'Parkingu Jugor', 
                'url' => 'rtsp://example.com/parking', 
                'type' => 'rtsp',
                'status' => 'active'
            ],
            [
                'name' => 'Kamëra Magazinë', 
                'location' => 'Magazina Kryesore', 
                'url' => 'rtsp://example.com/warehouse', 
                'type' => 'rtsp',
                'status' => 'active'
            ],
            [
                'name' => 'Kamëra Korridor', 
                'location' => 'Korridor 1', 
                'url' => 'rtsp://example.com/hallway1', 
                'type' => 'rtsp',
                'status' => 'active'
            ],
            [
                'name' => 'Kamëra Emergjence', 
                'location' => 'Dalja e Emergjencës', 
                'url' => 'rtsp://example.com/emergency', 
                'type' => 'rtsp',
                'status' => 'maintenance'
            ]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO security_cameras (name, location, url, type, zyra_id, status) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($demo_cameras as $camera) {
            $stmt->execute([
                $camera['name'],
                $camera['location'],
                $camera['url'],
                $camera['type'],
                $zyra_id,
                $camera['status']
            ]);
            $camera_id = $pdo->lastInsertId();
            
            // Add some camera configurations
            $configs = [
                ['motion_enabled', '1'],
                ['motion_sensitivity', '7'],
                ['recording_enabled', '1'],
                ['recording_mode', 'motion'],
                ['recording_quality', 'medium'],
                ['alerts_enabled', '1']
            ];
            
            $config_stmt = $pdo->prepare("INSERT INTO camera_configurations (camera_id, setting_name, setting_value) VALUES (?, ?, ?)");
            foreach ($configs as $config) {
                $config_stmt->execute([
                    $camera_id,
                    $config[0],
                    $config[1]
                ]);
            }
            
            // Add some alerts for this camera
            if (mt_rand(0, 1)) {
                $alert_types = ['motion', 'person', 'offline'];
                $alert_levels = ['low', 'medium', 'high'];
                
                $num_alerts = mt_rand(0, 3);
                for ($i = 0; $i < $num_alerts; $i++) {
                    $type = $alert_types[array_rand($alert_types)];
                    $level = $alert_levels[array_rand($alert_levels)];
                    
                    // Alert time within the last 48 hours
                    $hours = mt_rand(1, 48);
                    $alert_time = date('Y-m-d H:i:s', strtotime("-$hours hours"));
                    
                    $alert_stmt = $pdo->prepare("INSERT INTO security_alerts (camera_id, alert_time, alert_type, alert_level) VALUES (?, ?, ?, ?)");
                    $alert_stmt->execute([
                        $camera_id,
                        $alert_time,
                        $type,
                        $level
                    ]);
                }
            }
        }
        
        echo "<br>Demo data added successfully!<br>";
    }
    
    echo '<p>Setup complete! <a href="security_cameras.php">Go to Security Camera System</a></p>';
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}
?>