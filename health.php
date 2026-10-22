<?php
// Health check endpoint for Cloudflare Worker and monitoring
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// ==================== DATABASE CHECK ====================
$db_status = 'ok';
try {
    require_once __DIR__ . '/config/database.php';
    if (!function_exists('get_db_connection')) {
        function get_db_connection() {
            $host = 'localhost';
            $db   = 'your_database';
            $user = 'your_username';
            $pass = 'your_password';
            $charset = 'utf8mb4';
            $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            return new PDO($dsn, $user, $pass, $options);
        }
    }
    $pdo = get_db_connection();
    $pdo->query('SELECT 1');
    $db_status = 'ok';
} catch (Exception $e) {
    $db_status = 'error';
}

// ==================== CACHE CHECK ====================
$cache_status = 'ok';
if (!is_writable(sys_get_temp_dir())) {
    $cache_status = 'error';
}

// ==================== MEMORY CHECK ====================
$memory_usage = memory_get_peak_usage(true) / 1024 / 1024; // MB
$memory_status = $memory_usage < 200 ? 'ok' : 'warning';

// ==================== SYSTEM LOAD CHECK ====================
$load_status = 'ok';
$load_avg = 0;
if (function_exists('sys_getloadavg')) {
    $load = sys_getloadavg();
    $load_avg = $load[0];
    if ($load[0] > 2) $load_status = 'warning';
    if ($load[0] > 5) $load_status = 'error';
}

// ==================== OVERALL STATUS ====================
$all_ok = ($db_status === 'ok' && $cache_status === 'ok' && $memory_status === 'ok' && $load_status === 'ok');

$response = [
    'status'    => $all_ok ? 'ok' : 'error',
    'timestamp' => date('c'),
    'checks'    => [
        'database' => $db_status,
        'cache'    => $cache_status,
        'memory'   => $memory_status,
        'load'     => $load_status
    ],
    'metrics'   => [
        'memory_mb'      => round($memory_usage, 2),
        'load_avg'       => round($load_avg, 2),
        'php_version'    => PHP_VERSION,
        'uptime_seconds' => time() - $_SERVER['REQUEST_TIME']
    ],
    'environment' => 'hetzner-php'
];

http_response_code($all_ok ? 200 : 503);
echo json_encode($response, JSON_PRETTY_PRINT);
?>