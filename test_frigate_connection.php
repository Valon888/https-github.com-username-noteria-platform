<?php
/**
 * Frigate NVR Security System Test
 * 
 * This script tests the connection with a Frigate NVR server
 * and demonstrates basic functionality.
 * 
 * Usage: php test_frigate_connection.php [frigate_host]
 * Example: php test_frigate_connection.php http://192.168.1.10:5000
 */

// Include the FrigateAPI class
require_once 'FrigateAPI.php';

// Test if this is running in web browser or command line
$isCli = php_sapi_name() === 'cli';
$lineBreak = $isCli ? "\n" : "<br>";
$successSymbol = $isCli ? "✓" : "✅";
$failureSymbol = $isCli ? "✗" : "❌";
$infoSymbol = $isCli ? "ℹ" : "ℹ️";

// Configuration settings - override these with your settings
$config = [
    'frigate_host' => 'http://localhost:5000', // Default, will be overridden if provided as argument
    'cameras' => [
        'main' => 'front',      // Will try to auto-detect if not found
        'secondary' => 'back'   // Will try to auto-detect if not found
    ],
    'verbose' => true           // Show detailed output
];

// Check for command line arguments or GET parameters
if (isset($argv[1]) && filter_var($argv[1], FILTER_VALIDATE_URL)) {
    $config['frigate_host'] = $argv[1];
    echo "Using provided Frigate host: {$config['frigate_host']}\n";
} elseif (isset($_GET['frigate_host']) && filter_var($_GET['frigate_host'], FILTER_VALIDATE_URL)) {
    $config['frigate_host'] = $_GET['frigate_host'];
    output("Using provided Frigate host: {$config['frigate_host']}", $isCli);
}

// Format output based on environment
function output($message, $isCli) {
    if ($isCli) {
        echo $message . "\n";
    } else {
        echo htmlspecialchars($message) . "<br>";
    }
}

// Test connection and functionality
function testFrigateConnection($config, $isCli, $successSymbol, $failureSymbol, $infoSymbol) {
    try {
        // Create API client with increased timeout for slow networks
        $frigate = new FrigateAPI($config['frigate_host'], 15);
        
        // Test 1: Basic connectivity
        output("{$infoSymbol} Testing connection to Frigate NVR...", $isCli);
        $systemInfo = $frigate->getConfig();
        output("{$successSymbol} Successfully connected to Frigate NVR" . 
               (isset($systemInfo['version']) ? " v{$systemInfo['version']}" : ""), $isCli);
        
        // Test 2: Camera availability
        output("{$infoSymbol} Checking for configured cameras...", $isCli);
        $cameras = $frigate->getCameras();
        
        if (empty($cameras)) {
            output("{$failureSymbol} No cameras found in Frigate configuration", $isCli);
        } else {
            output("{$successSymbol} Found " . count($cameras) . " camera(s) in configuration", $isCli);
            output("Camera names: " . implode(", ", array_keys($cameras)), $isCli);
            
            // Auto-detect cameras if specified ones not found
            $mainCamera = $config['cameras']['main'];
            if (!isset($cameras[$mainCamera])) {
                $cameraKeys = array_keys($cameras);
                $mainCamera = reset($cameraKeys);
                output("{$infoSymbol} Auto-selected '{$mainCamera}' as main camera", $isCli);
            }
            
            // Test 3: Check main camera
            output("{$infoSymbol} Testing access to camera '{$mainCamera}'...", $isCli);
            
            try {
                // Test 4: Try to get a snapshot
                $snapshotFile = "test_snapshot.jpg";
                if ($frigate->saveSnapshot($mainCamera, $snapshotFile)) {
                    output("{$successSymbol} Successfully captured snapshot from camera '{$mainCamera}'", $isCli);
                    $path = realpath($snapshotFile);
                    output("  Saved to: " . $path, $isCli);
                    
                    // If in web mode, display the image
                    if (!$isCli) {
                        echo "<div style='margin: 10px 0;'>";
                        echo "<img src='{$snapshotFile}' style='max-width: 640px; border: 1px solid #ccc;' alt='Camera snapshot'>";
                        echo "</div>";
                    }
                } else {
                    output("{$failureSymbol} Failed to capture snapshot from camera '{$mainCamera}'", $isCli);
                }
            } catch (Exception $e) {
                output("{$failureSymbol} Error accessing camera: " . $e->getMessage(), $isCli);
            }
            
            // Test 5: Check events API
            output("{$infoSymbol} Testing events API...", $isCli);
            try {
                $events = $frigate->getEvents(['limit' => 1]);
                if (count($events) > 0) {
                    output("{$successSymbol} Events API is working properly", $isCli);
                } else {
                    output("{$infoSymbol} Events API returned no events (this may be normal if no recent detections)", $isCli);
                }
            } catch (Exception $e) {
                output("{$failureSymbol} Error accessing events: " . $e->getMessage(), $isCli);
            }
        }
        
        // Summary
        if (!$isCli) echo "<div style='margin-top: 20px; padding: 10px; background-color: #f0f0f0; border: 1px solid #ccc;'>";
        output("\n=== SUMMARY ===", $isCli);
        output("Frigate NVR connection test completed", $isCli);
        
        if (!empty($cameras)) {
            output("RTSP URL for main camera: " . $frigate->getRtspUrl($mainCamera), $isCli);
            output("MJPEG URL for main camera: " . $frigate->getMjpegUrl($mainCamera), $isCli);
        }
        
        output("Web UI available at: {$config['frigate_host']}", $isCli);
        
        // Display next steps
        output("\n=== NEXT STEPS ===", $isCli);
        output("1. Update the configuration in your PHP files with your Frigate host and camera names", $isCli);
        output("2. Try the frigate_example.php file for a more comprehensive demo", $isCli);
        output("3. Check the FRIGATE_INTEGRATION_GUIDE.md file for detailed setup instructions", $isCli);
        if (!$isCli) echo "</div>";
        
        return true;
    } catch (Exception $e) {
        if (!$isCli) echo "<div style='margin-top: 20px; padding: 10px; background-color: #ffe0e0; border: 1px solid #ffb0b0;'>";
        output("{$failureSymbol} CONNECTION ERROR: " . $e->getMessage(), $isCli);
        output("\nTroubleshooting steps:", $isCli);
        output("1. Verify Frigate NVR is running at {$config['frigate_host']}", $isCli);
        output("2. Check network connectivity between this server and Frigate", $isCli);
        output("   - Try pinging the Frigate host: ping " . parse_url($config['frigate_host'], PHP_URL_HOST), $isCli);
        output("   - Try accessing the Frigate web UI in a browser", $isCli);
        output("3. Verify firewall settings allow access to Frigate's API port (default: 5000)", $isCli);
        output("4. Check for any proxies or network restrictions that might block the connection", $isCli);
        
        output("\nFor more help:", $isCli);
        output("- See the FRIGATE_INTEGRATION_GUIDE.md file for detailed setup information", $isCli);
        output("- Visit Frigate documentation: https://docs.frigate.video/", $isCli);
        if (!$isCli) echo "</div>";
        
        return false;
    }
}

// Run the test
if ($isCli) {
    // Command line output
    echo "=== FRIGATE NVR INTEGRATION TEST ===\n";
    echo "Testing connection to: {$config['frigate_host']}\n";
    echo "----------------------------------------\n";
    testFrigateConnection($config, $isCli, $successSymbol, $failureSymbol, $infoSymbol);
} else {
    // Web output
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Frigate NVR Connection Test</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
            h1 { color: #333; }
            .container { max-width: 800px; margin: 0 auto; }
            .code { font-family: monospace; background-color: #f5f5f5; padding: 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>Frigate NVR Connection Test</h1>
            <p>Testing connection to: <span class='code'>{$config['frigate_host']}</span></p>
            <hr>";
            
    testFrigateConnection($config, $isCli, $successSymbol, $failureSymbol, $infoSymbol);
            
    echo "</div>
    </body>
    </html>";
}
?>