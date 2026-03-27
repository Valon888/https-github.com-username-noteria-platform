<?php
/**
 * Developer Configuration File
 * Contains developer-specific settings and functions
 */

// Developer-specific configurations
define('DEVELOPER_MODE', getenv('APP_ENV') === 'development');
define('DEVELOPER_DEBUG', getenv('APP_DEBUG') === 'true');

// IP whitelist for developer access
$developer_ips = explode(',', getenv('DEVELOPER_IP_WHITELIST') ?: '127.0.0.1,::1');

// Function to check if user is a developer
function isDeveloper($admin_id) {
    // Add all developer admin IDs here
    // Each admin ID in this array will have developer/super-admin access
    $developer_ids = [1, 2, 3, 4, 5]; // Add developer user IDs here
    
    return in_array($admin_id, $developer_ids);
}

// Additional developer functions can be added here
function logDeveloperAction($action, $details = '') {
    if (DEVELOPER_MODE) {
        $log = sprintf("[%s] Developer Action: %s - %s\n", date('Y-m-d H:i:s'), $action, $details);
        file_put_contents(__DIR__ . '/developer.log', $log, FILE_APPEND);
    }
}

// Check if current IP is allowed for developer features
function isDeveloperIP() {
    global $developer_ips;
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    foreach ($developer_ips as $ip) {
        $ip = trim($ip);
        if ($ip === '*' || $ip === $client_ip || (strpos($ip, '*') !== false && fnmatch($ip, $client_ip))) {
            return true;
        }
    }
    return false;
}
?>