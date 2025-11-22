<?php
session_start();
require_once 'confidb.php';
require_once 'includes/security_functions.php';

// Simple API to handle security camera operations
header('Content-Type: application/json');

// Check user authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get the action from GET or POST
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

// Handle various actions
switch ($action) {
    case 'get_cameras':
        // Get all cameras
        try {
            $stmt = $pdo->query("SELECT * FROM security_cameras ORDER BY name");
            $cameras = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'cameras' => $cameras]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_camera':
        // Get single camera details
        $camera_id = $_GET['camera_id'] ?? 0;
        
        if (!$camera_id) {
            echo json_encode(['success' => false, 'message' => 'Missing camera ID']);
            break;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM security_cameras WHERE id = ?");
            $stmt->execute([$camera_id]);
            $camera = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($camera) {
                // Log access
                logCameraAccess($pdo, $user_id, $camera_id);
                
                echo json_encode(['success' => true, 'camera' => $camera]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Camera not found']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'add_camera':
        // Add new camera
        $camera_name = $_POST['camera_name'] ?? '';
        $camera_location = $_POST['camera_location'] ?? '';
        $camera_url = $_POST['camera_url'] ?? '';
        $camera_type = $_POST['camera_type'] ?? 'ip';
        $camera_username = $_POST['camera_username'] ?? '';
        $camera_password = $_POST['camera_password'] ?? '';
        $camera_zyra = $_POST['camera_zyra'] ?? null;
        
        if (empty($camera_name) || empty($camera_url)) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            break;
        }
        
        try {
            // Insert camera
            $stmt = $pdo->prepare("INSERT INTO security_cameras (name, location, url, type, username, password, zyra_id, status) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, 'inactive')");
            $stmt->execute([
                $camera_name,
                $camera_location,
                $camera_url,
                $camera_type,
                $camera_username,
                $camera_password ? password_hash($camera_password, PASSWORD_DEFAULT) : '',
                $camera_zyra
            ]);
            
            $camera_id = $pdo->lastInsertId();
            
            // Add configuration settings
            $enable_motion = isset($_POST['enable_motion']) ? $_POST['enable_motion'] : '0';
            $enable_recording = isset($_POST['enable_recording']) ? $_POST['enable_recording'] : '0';
            $enable_alerts = isset($_POST['enable_alerts']) ? $_POST['enable_alerts'] : '0';
            
            $config_stmt = $pdo->prepare("INSERT INTO camera_configurations (camera_id, setting_name, setting_value) VALUES (?, ?, ?)");
            
            $configs = [
                ['motion_enabled', $enable_motion],
                ['motion_sensitivity', '5'],
                ['recording_enabled', $enable_recording],
                ['recording_mode', 'motion'],
                ['recording_quality', 'medium'],
                ['alerts_enabled', $enable_alerts]
            ];
            
            foreach ($configs as $config) {
                $config_stmt->execute([$camera_id, $config[0], $config[1]]);
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Camera added successfully', 
                'camera_id' => $camera_id
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'update_camera':
        // Update existing camera
        $camera_id = $_POST['camera_id'] ?? 0;
        $camera_name = $_POST['camera_name'] ?? '';
        $camera_location = $_POST['camera_location'] ?? '';
        $camera_url = $_POST['camera_url'] ?? '';
        $camera_username = $_POST['camera_username'] ?? '';
        $camera_password = $_POST['camera_password'] ?? '';
        $camera_status = $_POST['camera_status'] ?? 'inactive';
        
        if (!$camera_id || empty($camera_name) || empty($camera_url)) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            break;
        }
        
        try {
            // Check if camera exists
            $check_stmt = $pdo->prepare("SELECT id FROM security_cameras WHERE id = ?");
            $check_stmt->execute([$camera_id]);
            if (!$check_stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Camera not found']);
                break;
            }
            
            // Update camera
            if ($camera_password) {
                // If password was changed
                $stmt = $pdo->prepare("UPDATE security_cameras 
                                     SET name = ?, location = ?, url = ?, username = ?, password = ?, status = ? 
                                     WHERE id = ?");
                $stmt->execute([
                    $camera_name,
                    $camera_location,
                    $camera_url,
                    $camera_username,
                    password_hash($camera_password, PASSWORD_DEFAULT),
                    $camera_status,
                    $camera_id
                ]);
            } else {
                // Keep existing password
                $stmt = $pdo->prepare("UPDATE security_cameras 
                                     SET name = ?, location = ?, url = ?, username = ?, status = ? 
                                     WHERE id = ?");
                $stmt->execute([
                    $camera_name,
                    $camera_location,
                    $camera_url,
                    $camera_username,
                    $camera_status,
                    $camera_id
                ]);
            }
            
            // Update configurations
            $configs = [
                'motion_enabled', 'motion_sensitivity', 'motion_cooldown',
                'recording_enabled', 'recording_mode', 'recording_quality', 'recording_retention',
                'alerts_enabled', 'alert_motion', 'alert_person', 'alert_vehicle', 'alert_offline',
                'alert_cooldown', 'alert_recipients',
                'notify_dashboard', 'notify_email', 'notify_sms',
                'camera_protocol', 'camera_port', 'camera_path', 'camera_fps',
                'use_ssl', 'verify_cert', 'reconnect_automatically', 'camera_custom'
            ];
            
            foreach ($configs as $config) {
                if (isset($_POST[$config])) {
                    setCameraConfiguration($pdo, $camera_id, $config, $_POST[$config]);
                }
            }
            
            // Log access
            logCameraAccess($pdo, $user_id, $camera_id, 'configure');
            
            echo json_encode([
                'success' => true, 
                'message' => 'Camera updated successfully'
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'delete_camera':
        // Delete camera
        $camera_id = $_GET['camera_id'] ?? 0;
        
        if (!$camera_id) {
            echo json_encode(['success' => false, 'message' => 'Missing camera ID']);
            break;
        }
        
        try {
            // Delete camera (cascade will delete configurations, recordings, alerts, etc.)
            $stmt = $pdo->prepare("DELETE FROM security_cameras WHERE id = ?");
            $stmt->execute([$camera_id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Camera deleted successfully'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Camera not found']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_camera_configs':
        // Get camera configurations
        $camera_id = $_GET['camera_id'] ?? 0;
        
        if (!$camera_id) {
            echo json_encode(['success' => false, 'message' => 'Missing camera ID']);
            break;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT setting_name, setting_value FROM camera_configurations WHERE camera_id = ?");
            $stmt->execute([$camera_id]);
            $configs_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $configs = [];
            foreach ($configs_rows as $row) {
                $configs[$row['setting_name']] = $row['setting_value'];
            }
            
            echo json_encode(['success' => true, 'configs' => $configs]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_alerts':
        // Get alerts for a camera
        $camera_id = $_GET['camera_id'] ?? 0;
        $processed = isset($_GET['processed']) ? (int)$_GET['processed'] : 0;
        $limit = $_GET['limit'] ?? 10;
        
        try {
            $params = [];
            $query = "SELECT a.*, c.name as camera_name 
                     FROM security_alerts a
                     JOIN security_cameras c ON a.camera_id = c.id
                     WHERE a.processed = ?";
            $params[] = $processed;
            
            if ($camera_id) {
                $query .= " AND a.camera_id = ?";
                $params[] = $camera_id;
            }
            
            $query .= " ORDER BY a.alert_time DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'alerts' => $alerts]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_alert':
        // Get single alert
        $alert_id = $_GET['alert_id'] ?? 0;
        
        if (!$alert_id) {
            echo json_encode(['success' => false, 'message' => 'Missing alert ID']);
            break;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT a.*, c.name as camera_name 
                                  FROM security_alerts a
                                  JOIN security_cameras c ON a.camera_id = c.id
                                  WHERE a.id = ?");
            $stmt->execute([$alert_id]);
            $alert = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($alert) {
                echo json_encode(['success' => true, 'alert' => $alert]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Alert not found']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'resolve_alert':
        // Mark alert as processed
        $alert_id = $_GET['alert_id'] ?? 0;
        
        if (!$alert_id) {
            echo json_encode(['success' => false, 'message' => 'Missing alert ID']);
            break;
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE security_alerts 
                                  SET processed = 1, processed_by = ?, resolution_notes = 'Marked as resolved by admin'
                                  WHERE id = ?");
            $stmt->execute([$user_id, $alert_id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Alert resolved successfully'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Alert not found']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'start_recording':
        // Start recording
        $camera_id = $_GET['camera_id'] ?? 0;
        
        if (!$camera_id) {
            echo json_encode(['success' => false, 'message' => 'Missing camera ID']);
            break;
        }
        
        try {
            // Check if camera exists and is active
            $stmt = $pdo->prepare("SELECT * FROM security_cameras WHERE id = ? AND status = 'active'");
            $stmt->execute([$camera_id]);
            $camera = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$camera) {
                echo json_encode(['success' => false, 'message' => 'Camera not active or not found']);
                break;
            }
            
            // In a real application, you would start actual recording via external system
            // For demo purposes, we'll just create a record in the database
            
            $recording_path = 'recordings/' . date('Y/m/d') . '/' . $camera_id . '_' . time() . '.mp4';
            
            // Create recording record
            $stmt = $pdo->prepare("INSERT INTO camera_recordings (camera_id, start_time, file_path, recording_type) 
                                 VALUES (?, NOW(), ?, 'manual')");
            $stmt->execute([$camera_id, $recording_path]);
            
            $recording_id = $pdo->lastInsertId();
            
            // Log access
            logCameraAccess($pdo, $user_id, $camera_id, 'view');
            
            echo json_encode([
                'success' => true, 
                'message' => 'Recording started',
                'recording_id' => $recording_id
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'stop_recording':
        // Stop recording
        $camera_id = $_GET['camera_id'] ?? 0;
        
        if (!$camera_id) {
            echo json_encode(['success' => false, 'message' => 'Missing camera ID']);
            break;
        }
        
        try {
            // Find active recording for this camera
            $stmt = $pdo->prepare("SELECT id FROM camera_recordings 
                                  WHERE camera_id = ? AND status = 'recording' 
                                  ORDER BY start_time DESC LIMIT 1");
            $stmt->execute([$camera_id]);
            $recording = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$recording) {
                echo json_encode(['success' => false, 'message' => 'No active recording found']);
                break;
            }
            
            // In a real application, you would stop actual recording via external system
            // For demo purposes, we'll just update the record in the database
            
            // Update recording record
            $stmt = $pdo->prepare("UPDATE camera_recordings 
                                 SET end_time = NOW(), status = 'completed', duration = TIMESTAMPDIFF(SECOND, start_time, NOW())
                                 WHERE id = ?");
            $stmt->execute([$recording['id']]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Recording stopped',
                'recording_id' => $recording['id']
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'take_screenshot':
        // Take screenshot
        $camera_id = $_GET['camera_id'] ?? 0;
        
        if (!$camera_id) {
            echo json_encode(['success' => false, 'message' => 'Missing camera ID']);
            break;
        }
        
        try {
            // Check if camera exists and is active
            $stmt = $pdo->prepare("SELECT * FROM security_cameras WHERE id = ? AND status = 'active'");
            $stmt->execute([$camera_id]);
            $camera = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$camera) {
                echo json_encode(['success' => false, 'message' => 'Camera not active or not found']);
                break;
            }
            
            // In a real application, you would capture actual screenshot via external system
            // For demo purposes, we'll just return success
            
            // Log access
            logCameraAccess($pdo, $user_id, $camera_id, 'export');
            
            echo json_encode([
                'success' => true, 
                'message' => 'Screenshot captured successfully'
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'log_access':
        // Log camera access
        $camera_id = $_GET['camera_id'] ?? 0;
        $access_type = $_GET['access_type'] ?? 'view';
        
        if (!$camera_id) {
            echo json_encode(['success' => false, 'message' => 'Missing camera ID']);
            break;
        }
        
        try {
            $result = logCameraAccess($pdo, $user_id, $camera_id, $access_type);
            
            echo json_encode([
                'success' => $result, 
                'message' => $result ? 'Access logged successfully' : 'Failed to log access'
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>