<?php
// Security Camera System Test Script
session_start();
require_once 'confidb.php';
require_once 'includes/security_functions.php';

// Force admin role for testing
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

echo "<h1>Security Camera System Test</h1>";

// Function to run a test and display result
function runTest($testName, $testFunction) {
    global $pdo;
    echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #ccc;'>";
    echo "<h3>Testing: $testName</h3>";
    
    try {
        $result = $testFunction($pdo);
        if ($result === true) {
            echo "<p style='color: green;'><strong>PASS</strong> - Test completed successfully</p>";
        } else {
            echo "<p style='color: orange;'><strong>PARTIAL</strong> - Test completed with notes:</p>";
            echo "<pre>$result</pre>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>FAIL</strong> - Test failed with error:</p>";
        echo "<pre>" . $e->getMessage() . "</pre>";
    }
    
    echo "</div>";
}

// Test database tables existence
runTest("Database Tables Check", function($pdo) {
    $tables = [
        'security_cameras',
        'camera_recordings',
        'camera_configurations',
        'security_alerts',
        'camera_access_logs'
    ];
    
    $missingTables = [];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() == 0) {
            $missingTables[] = $table;
        }
    }
    
    if (!empty($missingTables)) {
        return "Missing tables: " . implode(", ", $missingTables) . 
               "\nPlease run setup_security_system.php first.";
    }
    
    return true;
});

// Test camera functions
runTest("Camera Functions", function($pdo) {
    // Get active cameras
    $cameras = getActiveSecurityCameras($pdo);
    
    if (empty($cameras)) {
        return "No active cameras found. This is not an error, but you may want to add some cameras or run setup_security_system.php?demo=1";
    }
    
    echo "<p>Found " . count($cameras) . " active cameras</p>";
    return true;
});

// Test logging function
runTest("Camera Access Logging", function($pdo) {
    global $_SESSION;
    // First check if we have any cameras
    $stmt = $pdo->query("SELECT id FROM security_cameras LIMIT 1");
    $camera = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$camera) {
        return "No cameras available for logging test. Add a camera or run setup_security_system.php?demo=1";
    }
    
    // Test logging access
    $result = logCameraAccess($pdo, $_SESSION['user_id'], $camera['id'], 'view');
    
    if (!$result) {
        return "Failed to log camera access";
    }
    
    // Check if log was created
    $stmt = $pdo->prepare("SELECT * FROM camera_access_logs WHERE user_id = ? AND camera_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id'], $camera['id']]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$log) {
        return "Log entry was not found in database";
    }
    
    echo "<p>Successfully logged camera access:</p>";
    echo "<pre>";
    print_r($log);
    echo "</pre>";
    
    return true;
});

// Test alert generation
runTest("Security Alert Generation", function($pdo) {
    // First check if we have any cameras
    $stmt = $pdo->query("SELECT id FROM security_cameras LIMIT 1");
    $camera = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$camera) {
        return "No cameras available for alert test. Add a camera or run setup_security_system.php?demo=1";
    }
    
    // Generate a test alert
    $result = generateSecurityAlert($pdo, $camera['id'], 'motion', 'medium');
    
    if (!$result) {
        return "Failed to generate security alert";
    }
    
    // Check if alert was created
    $stmt = $pdo->prepare("SELECT * FROM security_alerts WHERE camera_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$camera['id']]);
    $alert = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$alert) {
        return "Alert was not found in database";
    }
    
    echo "<p>Successfully generated security alert:</p>";
    echo "<pre>";
    print_r($alert);
    echo "</pre>";
    
    return true;
});

// Test camera configuration
runTest("Camera Configuration", function($pdo) {
    // First check if we have any cameras
    $stmt = $pdo->query("SELECT id FROM security_cameras LIMIT 1");
    $camera = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$camera) {
        return "No cameras available for configuration test. Add a camera or run setup_security_system.php?demo=1";
    }
    
    // Set a test configuration
    $testValue = "test_value_" . time();
    $result = setCameraConfiguration($pdo, $camera['id'], 'test_setting', $testValue);
    
    if (!$result) {
        return "Failed to set camera configuration";
    }
    
    // Get the configuration back
    $value = getCameraConfigurationValue($pdo, $camera['id'], 'test_setting');
    
    if ($value != $testValue) {
        return "Configuration value mismatch. Set: $testValue, Got: $value";
    }
    
    echo "<p>Successfully set and retrieved camera configuration</p>";
    return true;
});

// Display API endpoint information
echo "<h2>API Endpoints Available:</h2>";
echo "<ul>";
echo "<li><code>api_security_cameras.php?action=get_cameras</code> - List all cameras</li>";
echo "<li><code>api_security_cameras.php?action=get_camera&camera_id=1</code> - Get camera details</li>";
echo "<li><code>api_security_cameras.php?action=get_camera_configs&camera_id=1</code> - Get camera configurations</li>";
echo "<li><code>api_security_cameras.php?action=get_alerts&limit=5</code> - Get unprocessed alerts</li>";
echo "</ul>";

echo "<h2>Links:</h2>";
echo "<ul>";
echo "<li><a href='setup_security_system.php' target='_blank'>Setup Security System (Create Tables)</a></li>";
echo "<li><a href='setup_security_system.php?demo=1' target='_blank'>Setup Security System with Demo Data</a></li>";
echo "<li><a href='security_cameras.php' target='_blank'>Open Security Camera Dashboard</a></li>";
echo "</ul>";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Camera System Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        h1 {
            color: #333;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
        }
        
        pre {
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            overflow: auto;
        }
        
        code {
            background-color: #f5f5f5;
            padding: 2px 5px;
            border-radius: 3px;
        }
        
        a {
            color: #0066cc;
            text-decoration: none;
        }
        
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <!-- PHP output appears here -->
</body>
</html>
?>