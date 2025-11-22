<?php
/**
 * Network Camera Scanner
 * 
 * This script scans your local network for common security camera ports
 * and protocols to help locate cameras that can be used with Frigate NVR.
 */

// Set execution time limit to a higher value as scanning may take time
ini_set('max_execution_time', 300);

// Web or CLI environment
$isCli = php_sapi_name() === 'cli';

// Output function
function output($message) {
    global $isCli;
    if ($isCli) {
        echo $message . "\n";
    } else {
        echo htmlspecialchars($message) . "<br>";
        ob_flush();
        flush();
    }
}

// Get the local subnet
function getLocalSubnet() {
    // Try to get the local IP address
    $localIp = $_SERVER['SERVER_ADDR'] ?? null;
    
    // If not available, try alternative methods
    if (!$localIp) {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            exec("ipconfig | findstr /i \"ipv4\"", $output);
            if (!empty($output)) {
                foreach ($output as $line) {
                    if (preg_match('/\d+\.\d+\.\d+\.\d+/', $line, $matches)) {
                        $localIp = $matches[0];
                        break;
                    }
                }
            }
        } else {
            // Linux/Unix
            exec("hostname -I", $output);
            if (!empty($output)) {
                $ips = explode(" ", trim($output[0]));
                $localIp = $ips[0];
            }
        }
    }
    
    // Extract subnet from IP
    if ($localIp) {
        $parts = explode('.', $localIp);
        if (count($parts) === 4) {
            return [
                'ip' => $localIp,
                'subnet' => "{$parts[0]}.{$parts[1]}.{$parts[2]}",
            ];
        }
    }
    
    return ['ip' => 'Unknown', 'subnet' => '192.168.1']; // Default fallback
}

// Scan a single IP for open ports
function scanIp($ip, $ports) {
    $results = [];
    
    foreach ($ports as $port => $service) {
        $fp = @fsockopen($ip, $port, $errno, $errstr, 1);
        if ($fp) {
            $results[] = [
                'port' => $port,
                'service' => $service,
                'open' => true
            ];
            fclose($fp);
        }
    }
    
    return $results;
}

// Check if a device is likely to be a camera
function checkForCamera($ip, $openPorts) {
    $possibleCamera = false;
    $cameraType = [];
    $rtspUrl = null;
    
    // Check for common camera ports
    foreach ($openPorts as $portInfo) {
        $port = $portInfo['port'];
        
        switch ($port) {
            case 554:
                $possibleCamera = true;
                $cameraType[] = 'RTSP capable';
                $rtspUrl = "rtsp://$ip:554/";
                break;
                
            case 80:
            case 443:
                // Try to get the web interface title to identify camera
                $url = ($port == 443 ? "https://" : "http://") . $ip;
                $context = stream_context_create([
                    'http' => ['timeout' => 2],
                    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
                ]);
                $content = @file_get_contents($url, false, $context);
                if ($content !== false) {
                    if (preg_match('/<title>(.*?)<\/title>/i', $content, $matches)) {
                        $title = $matches[1];
                        if (preg_match('/camera|ipcam|webcam|hikvision|dahua|reolink|amcrest|axis/i', $title)) {
                            $possibleCamera = true;
                            $cameraType[] = "Web UI: $title";
                        }
                    }
                }
                break;
                
            case 8000:
                $cameraType[] = 'Possible Hikvision';
                $possibleCamera = true;
                break;
                
            case 37777:
                $cameraType[] = 'Possible Dahua';
                $possibleCamera = true;
                break;
                
            case 9000:
                $cameraType[] = 'Possible Amcrest/Dahua';
                $possibleCamera = true;
                break;
        }
    }
    
    // If no specific type identified but has potential camera ports
    if (empty($cameraType) && $possibleCamera) {
        $cameraType[] = 'Unknown camera type';
    }
    
    return [
        'isCamera' => $possibleCamera,
        'type' => implode(', ', $cameraType),
        'rtspUrl' => $rtspUrl
    ];
}

// Get common RTSP URL patterns for detected camera types
function getRtspUrlPatterns($ip, $cameraType) {
    $patterns = [];
    
    // Default/generic pattern
    $patterns[] = "rtsp://<username>:<password>@$ip:554/";
    
    // Camera-specific patterns
    if (stripos($cameraType, 'hikvision') !== false) {
        $patterns[] = "rtsp://<username>:<password>@$ip:554/Streaming/Channels/101"; // Main stream
        $patterns[] = "rtsp://<username>:<password>@$ip:554/Streaming/Channels/102"; // Sub stream
    }
    
    if (stripos($cameraType, 'dahua') !== false || stripos($cameraType, 'amcrest') !== false) {
        $patterns[] = "rtsp://<username>:<password>@$ip:554/cam/realmonitor?channel=1&subtype=0"; // Main stream
        $patterns[] = "rtsp://<username>:<password>@$ip:554/cam/realmonitor?channel=1&subtype=1"; // Sub stream
    }
    
    if (stripos($cameraType, 'reolink') !== false) {
        $patterns[] = "rtsp://<username>:<password>@$ip:554/h264Preview_01_main"; // Main stream
        $patterns[] = "rtsp://<username>:<password>@$ip:554/h264Preview_01_sub"; // Sub stream
    }
    
    if (stripos($cameraType, 'axis') !== false) {
        $patterns[] = "rtsp://<username>:<password>@$ip:554/axis-media/media.amp"; // Axis format
    }
    
    // ONVIF pattern (works with many cameras)
    $patterns[] = "rtsp://<username>:<password>@$ip:554/onvif/profile0/media.smp"; // ONVIF
    
    return $patterns;
}

// Begin web output
if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Network Camera Scanner</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
            h1, h2 { color: #333; }
            .container { max-width: 900px; margin: 0 auto; }
            .progress { height: 20px; background-color: #f5f5f5; border-radius: 4px; margin-bottom: 15px; overflow: hidden; }
            .progress-bar { height: 100%; background-color: #4CAF50; width: 0%; transition: width 0.3s; }
            .scanner-controls { margin: 20px 0; padding: 15px; background-color: #f8f8f8; border: 1px solid #ddd; border-radius: 5px; }
            .camera-box { margin: 10px 0; padding: 15px; background-color: #e8f5e9; border-left: 5px solid #4caf50; border-radius: 3px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            table, th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f5f5f5; }
            .scan-options label { margin-right: 15px; }
            .rtsp-patterns { background-color: #f5f5f5; padding: 10px; border-left: 3px solid #2196F3; margin-top: 10px; }
            #scanLog { height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background-color: #f9f9f9; margin-top: 15px; }
            .port-open { color: green; }
            .port-closed { color: red; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Network Camera Scanner</h1>
            <p>This tool scans your local network for security cameras that can be used with Frigate NVR.</p>';
            
    // Get subnet info
    $subnetInfo = getLocalSubnet();
    echo '<div class="scanner-controls">
            <h3>Network Information</h3>
            <p><strong>Your IP Address:</strong> ' . $subnetInfo['ip'] . '</p>
            <p><strong>Your Subnet:</strong> ' . $subnetInfo['subnet'] . '.x</p>
            
            <form method="post" id="scanForm">
                <h3>Scan Settings</h3>
                <div class="scan-options">
                    <label>
                        <input type="radio" name="scan_type" value="quick" checked> 
                        Quick Scan (common IP ranges)
                    </label>
                    <label>
                        <input type="radio" name="scan_type" value="full"> 
                        Full Subnet Scan (may take 5+ minutes)
                    </label>
                </div>
                <p>
                    <label for="custom_subnet">Custom Subnet (optional):</label>
                    <input type="text" id="custom_subnet" name="custom_subnet" placeholder="e.g., 192.168.1" value="' . $subnetInfo['subnet'] . '">
                </p>
                <button type="submit" id="startScan">Start Scan</button>
            </form>
        </div>
        
        <div id="scanProgress" style="display:none;">
            <h3>Scan Progress</h3>
            <div class="progress">
                <div class="progress-bar" id="progressBar"></div>
            </div>
            <p id="statusText">Preparing to scan...</p>
            <div id="scanLog"></div>
        </div>
        
        <div id="resultsContainer" style="display:none;">
            <h2>Scan Results</h2>
            <p id="resultsSummary"></p>
            <div id="camerasFound"></div>
        </div>
        
        <script>
        document.getElementById("scanForm").addEventListener("submit", function(e) {
            e.preventDefault();
            
            // Show progress section
            document.getElementById("scanProgress").style.display = "block";
            document.getElementById("scanLog").innerHTML = "";
            document.getElementById("resultsContainer").style.display = "none";
            document.getElementById("camerasFound").innerHTML = "";
            
            const scanType = document.querySelector("input[name=\'scan_type\']:checked").value;
            const customSubnet = document.getElementById("custom_subnet").value;
            
            // Start the scan via AJAX
            startScan(scanType, customSubnet);
        });
        
        function startScan(scanType, customSubnet) {
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "frigate_camera_scanner.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            
            // Track progress
            let totalIPs = 0;
            let scannedIPs = 0;
            let camerasFound = 0;
            
            xhr.onreadystatechange = function() {
                if (this.readyState === 3) { // Processing
                    // Process partial response
                    const newData = this.responseText.substr(this.lastProcessedSize || 0);
                    this.lastProcessedSize = this.responseText.length;
                    
                    // Look for JSON chunks in the response
                    try {
                        const matches = newData.match(/\{.*?\}/g);
                        if (matches) {
                            matches.forEach(match => {
                                try {
                                    const data = JSON.parse(match);
                                    
                                    if (data.type === "progress") {
                                        updateProgress(data.current, data.total);
                                    } else if (data.type === "log") {
                                        addToLog(data.message);
                                    } else if (data.type === "camera") {
                                        addCamera(data);
                                        camerasFound++;
                                    } else if (data.type === "init") {
                                        totalIPs = data.total;
                                        document.getElementById("statusText").textContent = 
                                            "Scanning " + data.total + " IP addresses...";
                                    }
                                } catch (e) {
                                    // Invalid JSON chunk, ignore
                                }
                            });
                        }
                    } catch (e) {
                        // Error processing chunks, ignore
                    }
                } else if (this.readyState === 4) { // Complete
                    if (this.status === 200) {
                        document.getElementById("progressBar").style.width = "100%";
                        document.getElementById("statusText").textContent = "Scan complete!";
                        document.getElementById("resultsContainer").style.display = "block";
                        document.getElementById("resultsSummary").textContent = 
                            "Found " + camerasFound + " potential cameras out of " + totalIPs + " IP addresses scanned.";
                    } else {
                        document.getElementById("statusText").textContent = "Error during scan";
                        addToLog("Error: " + this.statusText);
                    }
                }
            };
            
            xhr.send("scan_type=" + encodeURIComponent(scanType) + "&custom_subnet=" + encodeURIComponent(customSubnet));
        }
        
        function updateProgress(current, total) {
            const percent = Math.round((current / total) * 100);
            document.getElementById("progressBar").style.width = percent + "%";
            document.getElementById("statusText").textContent = 
                "Scanning: " + current + " of " + total + " (" + percent + "%)";
        }
        
        function addToLog(message) {
            const log = document.getElementById("scanLog");
            log.innerHTML += message + "<br>";
            log.scrollTop = log.scrollHeight;
        }
        
        function addCamera(data) {
            const container = document.getElementById("camerasFound");
            
            const cameraBox = document.createElement("div");
            cameraBox.className = "camera-box";
            
            let html = `<h3>Camera Found: ${data.ip}</h3>
                        <p><strong>Type:</strong> ${data.info.type}</p>`;
            
            if (data.ports.length > 0) {
                html += `<p><strong>Open Ports:</strong> ${data.ports.map(p => p.port + " (" + p.service + ")").join(", ")}</p>`;
            }
            
            if (data.info.rtspUrl) {
                html += `<p><strong>Base RTSP URL:</strong> ${data.info.rtspUrl}</p>`;
            }
            
            if (data.rtspPatterns && data.rtspPatterns.length > 0) {
                html += `<div class="rtsp-patterns">
                            <p><strong>Possible RTSP URL Patterns:</strong></p>
                            <ul>`;
                data.rtspPatterns.forEach(pattern => {
                    html += `<li><code>${pattern}</code></li>`;
                });
                html += `</ul>
                            <p><em>Replace &lt;username&gt; and &lt;password&gt; with your camera credentials</em></p>
                        </div>`;
            }
            
            html += `<p>
                        <a href="http://${data.ip}" target="_blank">Open Web Interface</a>`;
            if (data.info.rtspUrl) {
                html += ` | <a href="frigate_test_rtsp.php?url=${encodeURIComponent(data.info.rtspUrl)}" target="_blank">Test RTSP Stream</a>`;
            }
            html += `</p>`;
            
            cameraBox.innerHTML = html;
            container.appendChild(cameraBox);
            
            // Also log to the scan log
            addToLog(`✓ Found potential camera at ${data.ip} (${data.info.type})`);
        }
        </script>
    </div>
    </body>
    </html>';
}

// Process the scan request
if (!$isCli && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set headers for streaming response
    header('Content-Type: text/plain');
    header('Cache-Control: no-cache');
    ob_implicit_flush(true);
    ob_end_flush();
    
    // Get parameters
    $scanType = isset($_POST['scan_type']) ? $_POST['scan_type'] : 'quick';
    $customSubnet = isset($_POST['custom_subnet']) ? $_POST['custom_subnet'] : null;
    
    // Get subnet
    $subnetInfo = getLocalSubnet();
    $subnet = $customSubnet ?: $subnetInfo['subnet'];
    
    // Define IP ranges to scan
    $ipAddresses = [];
    
    if ($scanType === 'quick') {
        // Quick scan: Common IP ranges for cameras
        $commonRanges = [
            // Common camera IP ranges
            ['start' => 1, 'end' => 20],
            ['start' => 64, 'end' => 84],
            ['start' => 100, 'end' => 110],
            ['start' => 200, 'end' => 220],
            // Add custom range around local IP
            ['start' => max(1, intval(explode('.', $subnetInfo['ip'])[3]) - 5), 
             'end' => min(254, intval(explode('.', $subnetInfo['ip'])[3]) + 5)]
        ];
        
        foreach ($commonRanges as $range) {
            for ($i = $range['start']; $i <= $range['end']; $i++) {
                $ip = "$subnet.$i";
                if (!in_array($ip, $ipAddresses)) {
                    $ipAddresses[] = $ip;
                }
            }
        }
    } else {
        // Full scan: All IPs in subnet
        for ($i = 1; $i <= 254; $i++) {
            $ipAddresses[] = "$subnet.$i";
        }
    }
    
    // Common ports for security cameras
    $portsToScan = [
        554 => 'RTSP',         // RTSP streaming
        80 => 'HTTP',          // Web interface
        443 => 'HTTPS',        // Secure web interface
        8000 => 'Hikvision',   // Hikvision specific
        8554 => 'RTSP Alt',    // Alternative RTSP port
        37777 => 'Dahua',      // Dahua specific
        9000 => 'Amcrest',     // Amcrest/Dahua specific
        8899 => 'ONVIF'        // ONVIF port
    ];
    
    // Remove duplicates and sort
    $ipAddresses = array_unique($ipAddresses);
    sort($ipAddresses);
    
    // Output initialization info
    echo json_encode([
        'type' => 'init',
        'total' => count($ipAddresses)
    ]);
    
    // Scan each IP
    $camerasFound = 0;
    
    foreach ($ipAddresses as $index => $ip) {
        // Skip local IP
        if ($ip === $subnetInfo['ip']) continue;
        
        // Update progress
        echo json_encode([
            'type' => 'progress',
            'current' => $index + 1,
            'total' => count($ipAddresses)
        ]);
        
        echo json_encode([
            'type' => 'log',
            'message' => "Scanning $ip..."
        ]);
        
        // Scan this IP
        $openPorts = scanIp($ip, $portsToScan);
        
        // Skip IPs with no open ports
        if (empty($openPorts)) continue;
        
        // Check if this might be a camera
        $cameraInfo = checkForCamera($ip, $openPorts);
        
        if ($cameraInfo['isCamera']) {
            $camerasFound++;
            
            // Get RTSP URL patterns
            $rtspPatterns = getRtspUrlPatterns($ip, $cameraInfo['type']);
            
            // Output camera info
            echo json_encode([
                'type' => 'camera',
                'ip' => $ip,
                'ports' => $openPorts,
                'info' => $cameraInfo,
                'rtspPatterns' => $rtspPatterns
            ]);
        } else if (!empty($openPorts)) {
            // Device with open ports but not identified as camera
            echo json_encode([
                'type' => 'log',
                'message' => "Device at $ip has open ports: " . implode(', ', array_map(function($p) { 
                    return $p['port'] . ' (' . $p['service'] . ')'; 
                }, $openPorts))
            ]);
        }
        
        // Small delay to prevent overwhelming the network
        usleep(50000); // 50ms
    }
    
    // Final summary
    echo json_encode([
        'type' => 'log',
        'message' => "Scan complete. Found $camerasFound potential cameras."
    ]);
    
    exit;
}

// CLI mode
if ($isCli) {
    $subnetInfo = getLocalSubnet();
    $subnet = isset($argv[1]) ? $argv[1] : $subnetInfo['subnet'];
    
    output("Network Camera Scanner");
    output("-------------------");
    output("Your IP: {$subnetInfo['ip']}");
    output("Scanning subnet: $subnet.x");
    output("");
    
    // Define IP range to scan (1-254 for CLI mode)
    $ipAddresses = [];
    for ($i = 1; $i <= 254; $i++) {
        $ipAddresses[] = "$subnet.$i";
    }
    
    // Ports to scan
    $portsToScan = [
        554 => 'RTSP',         // RTSP streaming
        80 => 'HTTP',          // Web interface
        443 => 'HTTPS',        // Secure web interface
        8000 => 'Hikvision',   // Hikvision specific
        8554 => 'RTSP Alt',    // Alternative RTSP port
        37777 => 'Dahua',      // Dahua specific
        9000 => 'Amcrest',     // Amcrest/Dahua specific
        8899 => 'ONVIF'        // ONVIF port
    ];
    
    output("Scanning " . count($ipAddresses) . " IP addresses for security cameras...");
    output("This may take several minutes. Press Ctrl+C to abort.");
    output("");
    
    $camerasFound = 0;
    
    foreach ($ipAddresses as $index => $ip) {
        // Skip local IP
        if ($ip === $subnetInfo['ip']) continue;
        
        // Show progress every 10 IPs
        if ($index % 10 === 0 || $index === count($ipAddresses) - 1) {
            output("Progress: " . ($index + 1) . " / " . count($ipAddresses) . 
                  " (" . round(($index + 1) / count($ipAddresses) * 100) . "%)");
        }
        
        // Scan this IP
        $openPorts = scanIp($ip, $portsToScan);
        
        // Skip IPs with no open ports
        if (empty($openPorts)) continue;
        
        // Check if this might be a camera
        $cameraInfo = checkForCamera($ip, $openPorts);
        
        if ($cameraInfo['isCamera']) {
            $camerasFound++;
            output("");
            output("CAMERA FOUND: $ip");
            output("Type: " . $cameraInfo['type']);
            output("Open Ports: " . implode(', ', array_map(function($p) { 
                return $p['port'] . ' (' . $p['service'] . ')'; 
            }, $openPorts)));
            
            if ($cameraInfo['rtspUrl']) {
                output("Base RTSP URL: " . $cameraInfo['rtspUrl']);
            }
            
            // Get RTSP URL patterns
            $rtspPatterns = getRtspUrlPatterns($ip, $cameraInfo['type']);
            if (!empty($rtspPatterns)) {
                output("Possible RTSP URL Patterns:");
                foreach ($rtspPatterns as $pattern) {
                    output("  $pattern");
                }
                output("  (Replace <username> and <password> with your camera credentials)");
            }
            
            output("");
        }
        
        // Small delay to prevent overwhelming the network
        usleep(50000); // 50ms
    }
    
    output("");
    output("Scan complete. Found $camerasFound potential cameras.");
}
?>