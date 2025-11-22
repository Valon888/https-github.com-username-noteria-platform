<?php
/**
 * Frigate NVR Connection Diagnostic Tool
 * 
 * This script performs detailed diagnostics on the connection to a Frigate NVR server,
 * helping to identify and resolve connectivity issues.
 */

// Error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Web or CLI environment
$isCli = php_sapi_name() === 'cli';

// Get Frigate host from command line or GET parameters
if ($isCli && isset($argv[1])) {
    $frigateHost = $argv[1];
} elseif (isset($_GET['host'])) {
    $frigateHost = $_GET['host'];
} else {
    $frigateHost = "http://192.168.1.1:5000";
}

// Remove trailing slashes
$frigateHost = rtrim($frigateHost, '/');

// Initialize results array
$results = [];

// Format output function
function output($message, $status = null) {
    global $isCli, $results;
    
    // Add to results array
    if ($status !== null) {
        $results[] = [
            'message' => $message,
            'status' => $status
        ];
    }
    
    // Format for output
    $statusStr = '';
    if ($status === true) {
        $statusStr = $isCli ? "✓ " : "<span style='color:green;'>✓ </span>";
    } elseif ($status === false) {
        $statusStr = $isCli ? "✗ " : "<span style='color:red;'>✗ </span>";
    } elseif ($status === null) {
        $statusStr = $isCli ? "ℹ " : "<span style='color:blue;'>ℹ </span>";
    }
    
    // Output
    if ($isCli) {
        echo $statusStr . $message . "\n";
    } else {
        echo $statusStr . htmlspecialchars($message) . "<br>\n";
    }
}

// Function to test basic HTTP connectivity
function testHttpConnectivity($url, $timeout = 5) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request only
    curl_exec($ch);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    
    return [
        'httpCode' => $httpCode,
        'error' => $error,
        'connectTime' => $info['connect_time'],
        'totalTime' => $info['total_time'],
        'success' => ($httpCode > 0 && $httpCode < 400 && !$error)
    ];
}

// Function to ping a host
function pingHost($host) {
    $host = parse_url($host, PHP_URL_HOST);
    if (!$host) return false;
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows ping command
        exec("ping -n 2 -w 1000 $host", $output, $returnVal);
    } else {
        // Linux/Unix ping command
        exec("ping -c 2 -W 1 $host", $output, $returnVal);
    }
    
    // Return true if ping was successful (return value 0)
    return $returnVal === 0;
}

// Function to check port availability
function checkPort($host, $port, $timeout = 2) {
    $host = parse_url($host, PHP_URL_HOST);
    if (!$host) return false;
    
    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
    $status = false;
    
    if ($fp) {
        $status = true;
        fclose($fp);
    }
    
    return $status;
}

// Function to get local and remote IP info
function getNetworkInfo() {
    $info = [];
    
    // Local IP
    $localIp = $_SERVER['SERVER_ADDR'] ?? null;
    if (!$localIp) {
        // Try alternative methods
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec("ipconfig | findstr /i \"ipv4\"", $output);
            if (!empty($output)) {
                preg_match('/\d+\.\d+\.\d+\.\d+/', $output[0], $matches);
                if (!empty($matches)) $localIp = $matches[0];
            }
        } else {
            exec("hostname -I", $output);
            if (!empty($output)) $localIp = trim($output[0]);
        }
    }
    
    $info['local_ip'] = $localIp;
    
    // Subnet info
    if ($localIp) {
        $parts = explode('.', $localIp);
        if (count($parts) === 4) {
            $info['subnet'] = "{$parts[0]}.{$parts[1]}.{$parts[2]}";
        }
    }
    
    return $info;
}

// Start HTML output for web
if (!$isCli) {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Frigate NVR Connection Diagnostics</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
            h1, h2 { color: #333; }
            .container { max-width: 800px; margin: 0 auto; }
            .success { color: green; }
            .error { color: red; }
            .info { color: blue; }
            .diagnostic-box { 
                background-color: #f8f8f8; 
                border: 1px solid #ddd; 
                padding: 15px; 
                margin-bottom: 15px; 
                border-radius: 5px;
            }
            .solution-box {
                background-color: #e8f5e9;
                border-left: 5px solid #4caf50;
                padding: 10px;
                margin: 10px 0;
            }
            table { width: 100%; border-collapse: collapse; }
            table, th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f5f5f5; }
            code { background-color: #f0f0f0; padding: 2px 5px; border-radius: 3px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Frigate NVR Connection Diagnostics</h1>
            <form method="get">
                <label for="host">Frigate Host URL:</label>
                <input type="text" id="host" name="host" value="' . htmlspecialchars($frigateHost) . '" style="width: 300px; padding: 5px;">
                <input type="submit" value="Run Diagnostics" style="padding: 5px 10px;">
            </form>
            <hr>
    ';
}

// Start diagnostics
output("Starting Frigate NVR Connection Diagnostics");
output("Testing connection to: " . $frigateHost);
output("");

// Get host components
$parsedUrl = parse_url($frigateHost);
$host = $parsedUrl['host'] ?? '';
$port = $parsedUrl['port'] ?? 5000;
$scheme = $parsedUrl['scheme'] ?? 'http';

// Get network information
$networkInfo = getNetworkInfo();
output("Local network information:", null);
output("Local IP: " . ($networkInfo['local_ip'] ?? 'Unknown'), null);
output("Local subnet: " . ($networkInfo['subnet'] ?? 'Unknown'), null);
output("");

// Test 1: Basic connectivity (ping)
output("STEP 1: Testing basic network connectivity", null);
$canPing = pingHost($host);
if ($canPing) {
    output("Host $host is reachable (ping successful)", true);
} else {
    output("Cannot ping host $host", false);
    output("NOTE: Some servers have ICMP/ping disabled. This test may fail even if the server is online.", null);
}
output("");

// Test 2: Port check
output("STEP 2: Testing if port $port is open", null);
$portOpen = checkPort($host, $port);
if ($portOpen) {
    output("Port $port on $host is open and accepting connections", true);
} else {
    output("Cannot connect to port $port on $host", false);
    output("Possible issues:", null);
    output("- Frigate is not running", null);
    output("- Firewall is blocking port $port", null);
    output("- Port forwarding is not configured correctly (if accessing from outside the network)", null);
}
output("");

// Test 3: HTTP request
output("STEP 3: Testing HTTP connectivity", null);
$url = "$scheme://$host:$port";
$httpResult = testHttpConnectivity($url);

if ($httpResult['success']) {
    output("Successfully connected to $url (HTTP code: {$httpResult['httpCode']})", true);
    output("Connection time: " . round($httpResult['connectTime'] * 1000) . "ms", null);
} else {
    output("Failed to connect to $url", false);
    if ($httpResult['error']) {
        output("Error: " . $httpResult['error'], null);
    } else {
        output("HTTP code: " . $httpResult['httpCode'], null);
    }
}
output("");

// Test 4: API endpoint test
output("STEP 4: Testing specific Frigate API endpoints", null);

// Test version endpoint
$endpointTests = [
    '/api/version' => 'Version API',
    '/api/' => 'Base API',
];

foreach ($endpointTests as $endpoint => $description) {
    $endpointUrl = $frigateHost . $endpoint;
    $ch = curl_init($endpointUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        output("Failed to connect to $description ($endpointUrl): $error", false);
    } elseif ($httpCode >= 200 && $httpCode < 300) {
        output("Successfully connected to $description ($endpointUrl)", true);
    } else {
        output("Received HTTP error code $httpCode from $description ($endpointUrl)", false);
    }
}
output("");

// Test 5: Connection with different timeout
if (!$httpResult['success']) {
    output("STEP 5: Testing with increased timeout", null);
    $extendedTimeout = 20;
    output("Trying again with {$extendedTimeout} second timeout...", null);
    
    $ch = curl_init($frigateHost);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $extendedTimeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $extendedTimeout);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_exec($ch);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if (!$error && $httpCode > 0 && $httpCode < 400) {
        output("Success with increased timeout!", true);
        output("Your network may have higher latency to this server. Consider increasing the timeout in FrigateAPI.php", null);
    } else {
        output("Still failed with increased timeout", false);
        if ($error) {
            output("Error: " . $error, null);
        }
    }
    output("");
}

// Diagnostic Summary
output("DIAGNOSTIC SUMMARY", null);
$success = $canPing || $portOpen || $httpResult['success'];

if ($success) {
    output("At least one connectivity test passed.", true);
} else {
    output("All connectivity tests failed.", false);
}

// Provide recommendations
output("", null);
output("RECOMMENDATIONS", null);

// IP subnet mismatch check
if (isset($networkInfo['subnet']) && isset($parsedUrl['host']) && !empty($networkInfo['subnet'])) {
    $hostParts = explode('.', $parsedUrl['host']);
    $hostSubnet = count($hostParts) == 4 ? "{$hostParts[0]}.{$hostParts[1]}.{$hostParts[2]}" : '';
    
    if ($hostSubnet && $hostSubnet !== $networkInfo['subnet']) {
        output("WARNING: The Frigate host appears to be on a different subnet than this server.", false);
        output("Your subnet: {$networkInfo['subnet']}.x", null);
        output("Frigate subnet: {$hostSubnet}.x", null);
        output("Make sure proper routing is configured between these networks.", null);
        output("", null);
    }
}

if (!$canPing && !$portOpen) {
    output("1. Verify the Frigate server IP address and port are correct", null);
    output("2. Check that the Frigate server is powered on and running", null);
    output("3. Verify that you are on the same network as the Frigate server", null);
    output("   (or have proper routing between networks)", null);
}

if (!$portOpen && $canPing) {
    output("1. Verify that Frigate is running on the server", null);
    output("2. Check firewall settings to ensure port $port is allowed", null);
    output("3. Verify the correct port is being used (default is 5000)", null);
}

if ($httpResult['error'] && (strpos($httpResult['error'], 'timed out') !== false)) {
    output("1. Your connection to Frigate is timing out. This could be due to:", null);
    output("   - Network congestion or high latency", null);
    output("   - The server is under heavy load", null);
    output("   - A firewall or proxy is delaying the connection", null);
    output("2. Try increasing the connection timeout in the FrigateAPI.php file", null);
}

// Complete HTML for web
if (!$isCli) {
    // Provide code snippet for updating timeout
    echo '<div class="solution-box">
        <h3>How to Increase Connection Timeout</h3>
        <p>Open the <code>FrigateAPI.php</code> file and locate the constructor function:</p>
        <pre><code>public function __construct($baseUrl, $timeout = 10) {
    $this->baseUrl = rtrim($baseUrl, \'/\');
    $this->timeout = $timeout;
}</code></pre>
        <p>Change the default timeout value from 10 to a higher number, such as 30:</p>
        <pre><code>public function __construct($baseUrl, $timeout = 30) {
    $this->baseUrl = rtrim($baseUrl, \'/\');
    $this->timeout = $timeout;
}</code></pre>
        <p>Alternatively, specify a longer timeout when creating the FrigateAPI object:</p>
        <pre><code>$frigate = new FrigateAPI(\'http://your-frigate-host:5000\', 30);</code></pre>
    </div>';

    // Check for specific conditions and suggest solutions
    if (!$success) {
        echo '<h2>Possible Solutions</h2>';
        
        if (!$canPing && !$portOpen) {
            echo '<div class="diagnostic-box">
                <h3>Network Connectivity Issues</h3>
                <p>It appears that your server cannot reach the Frigate host at all.</p>
                <ol>
                    <li>Verify the Frigate host is online and running</li>
                    <li>Check that you\'re using the correct IP address</li>
                    <li>Make sure both servers are on the same network or can route to each other</li>
                    <li>Try accessing the Frigate web UI directly in a browser</li>
                </ol>
            </div>';
        }
        
        echo '<div class="diagnostic-box">
            <h3>Try Alternative Connection Methods</h3>
            <p>If direct connection isn\'t working, consider these alternatives:</p>
            <ol>
                <li><strong>Port Forwarding</strong>: If Frigate is on a different network, set up port forwarding on your router</li>
                <li><strong>Reverse Proxy</strong>: Use Nginx or Apache as a reverse proxy to your Frigate instance</li>
                <li><strong>VPN Connection</strong>: Establish a VPN between the networks</li>
            </ol>
        </div>';
    }
    
    echo '</div>
    </body>
    </html>';
}
?>