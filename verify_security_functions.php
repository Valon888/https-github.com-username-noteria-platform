<?php
// Verification tool for security_functions.php
require_once 'confidb.php';
require_once 'includes/security_functions.php';

echo "<h1>Security Functions Verification</h1>";

// Create test helper function
function testFunction($functionName, $args = [], $expectedType = null) {
    echo "<div style='margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px;'>";
    echo "<h3>Testing function: $functionName()</h3>";
    
    try {
        // Check if function exists
        if (!function_exists($functionName)) {
            echo "<p style='color: red;'><strong>FAIL</strong> - Function does not exist!</p>";
            echo "</div>";
            return;
        }
        
        echo "<p><strong>Arguments:</strong></p>";
        echo "<pre>";
        print_r($args);
        echo "</pre>";
        
        // Call the function with the provided arguments
        $result = call_user_func_array($functionName, $args);
        
        echo "<p><strong>Return value:</strong></p>";
        echo "<pre>";
        var_dump($result);
        echo "</pre>";
        
        // Check return type if specified
        if ($expectedType !== null) {
            $actualType = gettype($result);
            $typeMatch = ($actualType === $expectedType) || 
                        ($expectedType === 'array' && is_array($result)) ||
                        ($expectedType === 'boolean' && is_bool($result)) ||
                        ($expectedType === 'object' && is_object($result)) ||
                        ($expectedType === 'numeric' && is_numeric($result));
            
            if ($typeMatch) {
                echo "<p style='color: green;'><strong>PASS</strong> - Return type matches expected: $expectedType</p>";
            } else {
                echo "<p style='color: red;'><strong>FAIL</strong> - Return type mismatch. Expected: $expectedType, Got: $actualType</p>";
            }
        }
        
        echo "<p style='color: green;'><strong>PASS</strong> - Function executed without errors</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>FAIL</strong> - Exception thrown:</p>";
        echo "<pre>" . $e->getMessage() . "</pre>";
    }
    
    echo "</div>";
}

// Test all functions in security_functions.php

// 1. Test logCameraAccess
echo "<h2>1. Testing logCameraAccess()</h2>";

// This will simulate a test case but not actually insert if no cameras exist
testFunction('logCameraAccess', [$pdo, 1, 1, 'view'], 'boolean');

// 2. Test generateSecurityAlert
echo "<h2>2. Testing generateSecurityAlert()</h2>";

// This will simulate a test case but not actually insert if no cameras exist
testFunction('generateSecurityAlert', [$pdo, 1, 'motion', 'medium'], 'boolean');

// 3. Test getCameraConfigurationValue
echo "<h2>3. Testing getCameraConfigurationValue()</h2>";

// Try to get a configuration value (may return null if not exists)
testFunction('getCameraConfigurationValue', [$pdo, 1, 'motion_enabled', 'default_value']);

// 4. Test setCameraConfiguration
echo "<h2>4. Testing setCameraConfiguration()</h2>";

// Set a test configuration
$testSetting = 'test_setting_' . time();
testFunction('setCameraConfiguration', [$pdo, 1, $testSetting, 'test_value'], 'boolean');

// 5. Test getActiveSecurityCameras
echo "<h2>5. Testing getActiveSecurityCameras()</h2>";

// Get active cameras
testFunction('getActiveSecurityCameras', [$pdo], 'array');

// 6. Test getUnprocessedSecurityAlerts
echo "<h2>6. Testing getUnprocessedSecurityAlerts()</h2>";

// Get unprocessed alerts
testFunction('getUnprocessedSecurityAlerts', [$pdo, 5], 'array');

// Check for database tables to give advice
echo "<h2>Database Status Check</h2>";
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
    echo "<div style='padding: 15px; background-color: #ffe8e8; border-left: 5px solid #ff6b6b; margin: 20px 0;'>";
    echo "<h3>⚠️ Missing Database Tables</h3>";
    echo "<p>The following tables are missing from your database:</p>";
    echo "<ul>";
    foreach ($missingTables as $table) {
        echo "<li><code>$table</code></li>";
    }
    echo "</ul>";
    echo "<p>Please run <a href='setup_security_system.php' target='_blank'>setup_security_system.php</a> to create these tables.</p>";
    echo "</div>";
} else {
    echo "<div style='padding: 15px; background-color: #e8ffe8; border-left: 5px solid #6bff6b; margin: 20px 0;'>";
    echo "<h3>✅ Database Tables OK</h3>";
    echo "<p>All required database tables are present.</p>";
    echo "</div>";
}

echo "<h2>Next Steps</h2>";
echo "<ul>";
echo "<li><a href='test_security_system.php'>Run Complete System Test</a></li>";
echo "<li><a href='setup_security_system.php'>Create Database Tables</a></li>";
echo "<li><a href='setup_security_system.php?demo=1'>Create Database Tables with Demo Data</a></li>";
echo "<li><a href='security_cameras.php'>Go to Security Camera Dashboard</a></li>";
echo "</ul>";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Functions Verification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        h1, h2 {
            color: #333;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
        }
        
        h3 {
            color: #444;
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