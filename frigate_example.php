<?php
/**
 * Frigate NVR Integration Example
 * 
 * This script demonstrates how to use the FrigateAPI class to interact
 * with a Frigate NVR server for security monitoring.
 */

// Include the FrigateAPI class
require_once 'FrigateAPI.php';

// Set up error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configuration - Change these settings to match your setup
$frigateHost = 'http://192.168.1.100:5000'; // Replace with your Frigate NVR IP and port
$cameraName = 'front_door';                 // Replace with your camera name in Frigate

try {
    // Initialize Frigate API client
    $frigate = new FrigateAPI($frigateHost);
    
    // Example 1: Get system information
    $config = $frigate->getConfig();
    echo "<h2>Frigate System Information</h2>";
    echo "<pre>" . json_encode($config, JSON_PRETTY_PRINT) . "</pre>";
    
    // Example 2: Get list of all cameras
    $cameras = $frigate->getCameras();
    echo "<h2>Connected Cameras</h2>";
    echo "<ul>";
    foreach ($cameras as $name => $details) {
        echo "<li><strong>{$name}</strong>: {$details['width']}x{$details['height']}</li>";
    }
    echo "</ul>";
    
    // Example 3: Show a camera snapshot
    echo "<h2>Latest Snapshot from {$cameraName}</h2>";
    // Save the snapshot
    $snapshotFile = "snapshots/{$cameraName}_" . date('Y-m-d_H-i-s') . ".jpg";
    if (!file_exists('snapshots')) {
        mkdir('snapshots', 0755, true);
    }
    
    if ($frigate->saveSnapshot($cameraName, $snapshotFile)) {
        echo "<img src='{$snapshotFile}' style='max-width: 640px;' alt='Camera snapshot'>";
    } else {
        echo "<p>Failed to save snapshot</p>";
    }
    
    // Example 4: Display MJPEG stream (embedded)
    echo "<h2>Live Stream from {$cameraName}</h2>";
    $mjpegUrl = $frigate->getMjpegUrl($cameraName);
    echo "<img src='{$mjpegUrl}' style='max-width: 640px;' alt='Live stream'>";
    
    // Example 5: Show recent events (detected objects)
    $events = $frigate->getEvents(['limit' => 5]);
    echo "<h2>Recent Detection Events</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Time</th><th>Camera</th><th>Object</th><th>Score</th></tr>";
    foreach ($events as $event) {
        $time = date('Y-m-d H:i:s', $event['timestamp']);
        echo "<tr>";
        echo "<td>{$time}</td>";
        echo "<td>{$event['camera']}</td>";
        echo "<td>{$event['label']}</td>";
        echo "<td>{$event['score']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Example 6: Show RTSP streaming URL for external players
    $rtspUrl = $frigate->getRtspUrl($cameraName);
    echo "<h2>RTSP Stream URL</h2>";
    echo "<p>Use this URL in VLC or other media players:</p>";
    echo "<code>{$rtspUrl}</code>";
    
} catch (Exception $e) {
    echo "<h2>Error</h2>";
    echo "<p>Failed to connect to Frigate NVR: " . $e->getMessage() . "</p>";
    echo "<p>Please verify that:</p>";
    echo "<ul>";
    echo "<li>Frigate NVR is running and accessible at {$frigateHost}</li>";
    echo "<li>The camera name '{$cameraName}' exists in your Frigate configuration</li>";
    echo "</ul>";
}
?>