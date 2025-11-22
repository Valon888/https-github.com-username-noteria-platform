<?php
// Show any PHP errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Frigate NVR Setup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        h1, h2, h3 {
            color: #333;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .setup-form {
            margin: 20px 0;
            padding: 20px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="url"], input[type="password"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #45a049;
        }
        .info-box {
            padding: 15px;
            margin: 20px 0;
            border-left: 5px solid #2196F3;
            background-color: #e3f2fd;
        }
        .test-results {
            padding: 20px;
            margin: 20px 0;
            background-color: #f0f0f0;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
        .tab {
            overflow: hidden;
            border: 1px solid #ccc;
            background-color: #f1f1f1;
            margin-top: 20px;
        }
        .tab button {
            background-color: inherit;
            float: left;
            border: none;
            outline: none;
            cursor: pointer;
            padding: 14px 16px;
            transition: 0.3s;
            color: black;
        }
        .tab button:hover {
            background-color: #ddd;
        }
        .tab button.active {
            background-color: #4CAF50;
            color: white;
        }
        .tabcontent {
            display: none;
            padding: 20px;
            border: 1px solid #ccc;
            border-top: none;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .step {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .rtsp-examples {
            max-width: 100%;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Frigate NVR Integration Setup</h1>
        
        <div class="info-box">
            <p>This tool will help you integrate Frigate NVR with your PHP application for security monitoring.</p>
            <p>Frigate NVR is an open-source Network Video Recorder with AI-powered object detection, designed for security cameras.</p>
        </div>

        <div class="tab">
            <button class="tablinks active" onclick="openTab(event, 'Setup')">Setup</button>
            <button class="tablinks" onclick="openTab(event, 'Test')">Connection Test</button>
            <button class="tablinks" onclick="openTab(event, 'Guide')">Integration Guide</button>
            <button class="tablinks" onclick="openTab(event, 'Examples')">RTSP Examples</button>
        </div>
        
        <div id="Setup" class="tabcontent" style="display: block;">
            <div class="step">
                <h2>Step 1: Configure Frigate NVR Connection</h2>
                <div class="setup-form">
                    <form action="test_frigate_connection.php" method="get" target="_blank">
                        <div class="form-group">
                            <label for="frigate_host">Frigate Host URL:</label>
                            <input type="url" id="frigate_host" name="frigate_host" 
                                   placeholder="http://192.168.1.100:5000" required>
                        </div>
                        <div class="form-group">
                            <button type="submit">Test Connection</button>
                        </div>
                    </form>
                </div>
                <p>Enter the URL where your Frigate NVR is running. This typically includes the IP address and port 5000.</p>
            </div>
            
            <div class="step">
                <h2>Step 2: Install Frigate NVR (if not already installed)</h2>
                <p>If you haven't installed Frigate NVR yet, here are quick installation options:</p>
                
                <h3>Docker Installation</h3>
                <pre><code>docker run -d \
  --name frigate \
  --restart=unless-stopped \
  -p 5000:5000 \
  -p 8554:8554 \
  -v /path/to/config.yml:/config/config.yml \
  -v /path/to/storage:/media/frigate \
  ghcr.io/blakeblackshear/frigate:stable</code></pre>
                
                <h3>Home Assistant Add-on</h3>
                <p>If you use Home Assistant, you can install Frigate directly as an add-on from the Home Assistant add-on store.</p>
                
                <p>For detailed installation instructions, see the <a href="https://docs.frigate.video/installation" target="_blank">Frigate Documentation</a>.</p>
            </div>
            
            <div class="step">
                <h2>Step 3: Configure Frigate NVR</h2>
                <p>Once installed, you need to configure your cameras in Frigate. Here's a simple example configuration:</p>
                
                <pre><code># Example config.yml for Frigate NVR
mqtt:
  host: mqtt.example.com  # Optional: only needed if using MQTT

cameras:
  front_door:  # Camera name
    ffmpeg:
      inputs:
        - path: rtsp://username:password@192.168.1.10:554/stream
          roles:
            - detect
            - record
    detect:
      enabled: true
      width: 1280
      height: 720</code></pre>
                
                <p>For more detailed configuration options, refer to the <a href="https://docs.frigate.video/configuration/index" target="_blank">Frigate Configuration Guide</a>.</p>
            </div>
        </div>
        
        <div id="Test" class="tabcontent">
            <h2>Connection Test</h2>
            <p>Use this tool to test your connection to Frigate NVR:</p>
            
            <div class="setup-form">
                <form action="test_frigate_connection.php" method="get" target="_blank">
                    <div class="form-group">
                        <label for="frigate_host_test">Frigate Host URL:</label>
                        <input type="url" id="frigate_host_test" name="frigate_host" 
                               placeholder="http://192.168.1.100:5000" required>
                    </div>
                    <div class="form-group">
                        <button type="submit">Run Test</button>
                    </div>
                </form>
            </div>
            
            <h3>What This Tests</h3>
            <ul>
                <li>Connectivity to your Frigate NVR server</li>
                <li>Camera configuration and availability</li>
                <li>Ability to capture snapshots</li>
                <li>Events API functionality</li>
            </ul>
            
            <div class="info-box">
                <p><strong>Note:</strong> If the test fails, check that:</p>
                <ul>
                    <li>Frigate NVR is running and accessible on your network</li>
                    <li>There are no firewalls blocking access to port 5000</li>
                    <li>The URL is correct (including http:// prefix and port)</li>
                </ul>
            </div>
        </div>
        
        <div id="Guide" class="tabcontent">
            <h2>Integration Guide</h2>
            
            <p>This guide will help you integrate Frigate NVR with your PHP application.</p>
            
            <h3>Prerequisites</h3>
            <ul>
                <li>PHP 7.4 or higher with cURL extension enabled</li>
                <li>Frigate NVR installed and configured with your cameras</li>
                <li>Network connectivity between your PHP server and Frigate NVR</li>
            </ul>
            
            <h3>API Files</h3>
            <p>The following files have been created for you:</p>
            <ul>
                <li><strong>FrigateAPI.php</strong> - The main API wrapper class</li>
                <li><strong>frigate_example.php</strong> - Example usage of the API</li>
                <li><strong>test_frigate_connection.php</strong> - Connection tester</li>
            </ul>
            
            <h3>Basic Usage Example</h3>
            <pre><code>&lt;?php
// Include the API wrapper
require_once 'FrigateAPI.php';

// Initialize with your Frigate server address
$frigate = new FrigateAPI('http://your-frigate-ip:5000');

// Get list of cameras
$cameras = $frigate->getCameras();
print_r($cameras);

// Display a live stream from a camera
$mjpegUrl = $frigate->getMjpegUrl('your_camera_name');
echo "&lt;img src='{$mjpegUrl}' alt='Live camera'&gt;";

// Get recent events
$events = $frigate->getEvents(['limit' => 10]);
print_r($events);
?&gt;</code></pre>
            
            <h3>For More Information</h3>
            <p>Check out the <code>FRIGATE_INTEGRATION_GUIDE.md</code> file for more detailed instructions and examples.</p>
            <p>Also visit the <a href="https://docs.frigate.video/" target="_blank">Frigate Documentation</a> for complete details on Frigate NVR.</p>
        </div>
        
        <div id="Examples" class="tabcontent">
            <h2>RTSP URL Examples for Common Cameras</h2>
            
            <p>Here are example RTSP URL patterns for various camera manufacturers:</p>
            
            <div class="rtsp-examples">
                <table>
                    <tr>
                        <th>Camera Brand</th>
                        <th>RTSP URL Pattern</th>
                        <th>Notes</th>
                    </tr>
                    <tr>
                        <td>Hikvision</td>
                        <td>rtsp://username:password@192.168.1.64:554/Streaming/Channels/101</td>
                        <td>101 = Channel 1, Stream 1</td>
                    </tr>
                    <tr>
                        <td>Hikvision</td>
                        <td>rtsp://username:password@192.168.1.64:554/Streaming/Channels/102</td>
                        <td>102 = Channel 1, Stream 2 (sub-stream)</td>
                    </tr>
                    <tr>
                        <td>Amcrest / Dahua</td>
                        <td>rtsp://username:password@192.168.1.64:554/cam/realmonitor?channel=1&subtype=0</td>
                        <td>subtype=0 is mainstream, subtype=1 is substream</td>
                    </tr>
                    <tr>
                        <td>Reolink</td>
                        <td>rtsp://username:password@192.168.1.64:554/h264Preview_01_main</td>
                        <td>For main stream</td>
                    </tr>
                    <tr>
                        <td>Reolink</td>
                        <td>rtsp://username:password@192.168.1.64:554/h264Preview_01_sub</td>
                        <td>For sub stream</td>
                    </tr>
                    <tr>
                        <td>Axis</td>
                        <td>rtsp://username:password@192.168.1.64:554/axis-media/media.amp</td>
                        <td>General Axis camera format</td>
                    </tr>
                    <tr>
                        <td>UniFi</td>
                        <td>rtsp://username:password@192.168.1.64:554/s0</td>
                        <td>s0 = stream 0, s1 = stream 1</td>
                    </tr>
                    <tr>
                        <td>Generic ONVIF</td>
                        <td>rtsp://username:password@192.168.1.64:554/onvif/profile0/media.smp</td>
                        <td>Try this for ONVIF-compliant cameras</td>
                    </tr>
                </table>
            </div>
            
            <h3>Adding URLs to Frigate Configuration</h3>
            <p>To use these URLs in your Frigate configuration:</p>
            <pre><code># In your Frigate config.yml
cameras:
  front_door:  # Choose a camera name
    ffmpeg:
      inputs:
        - path: rtsp://username:password@192.168.1.64:554/your_rtsp_path_here
          roles:
            - detect  # For motion detection
            - record  # For recording</code></pre>
            
            <div class="info-box">
                <p><strong>Tip:</strong> If you're not sure about your camera's RTSP URL, you can often find it in the camera's documentation or by searching online for "[camera brand] RTSP URL".</p>
                <p>You can also test RTSP URLs directly using VLC Media Player by selecting "Open Network Stream" and entering the RTSP URL.</p>
            </div>
        </div>
    </div>

    <script>
    function openTab(evt, tabName) {
        var i, tabcontent, tablinks;
        
        // Hide all tab content
        tabcontent = document.getElementsByClassName("tabcontent");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }
        
        // Remove "active" class from all tab buttons
        tablinks = document.getElementsByClassName("tablinks");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" active", "");
        }
        
        // Show the selected tab content and add "active" class to the button
        document.getElementById(tabName).style.display = "block";
        evt.currentTarget.className += " active";
    }
    </script>
</body>
</html>