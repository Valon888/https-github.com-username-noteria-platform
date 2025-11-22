<?php
// Load Composer autoloader first - CRITICAL for Twilio and other vendors
require_once __DIR__ . '/vendor/autoload.php';

// ==========================================
// LOAD ENVIRONMENT VARIABLES
// ==========================================
$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    $env_lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env_lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                $value = substr($value, 1, -1);
            }
            
            // Set as environment variable
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Konfigurimi i raportimit të gabimeve (përshtat sipas APP_ENV)
$app_env = getenv('APP_ENV') ?: 'development';
if ($app_env === 'production') {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
} else {
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
}
ini_set('error_log', __DIR__ . '/error.log');

// ==========================================
// LOAD SESSION HELPER
// ==========================================
require_once __DIR__ . '/session_helper.php';
initializeSecureSession();

// ==========================================
// SET SECURITY HEADERS
// ==========================================

// Content Security Policy (CSP)
// Kufizon burimet e JS, CSS, dhe të tjera
$csp = "default-src 'self'; " .
       "script-src 'self' 'unsafe-inline' https://chart.googleapis.com https://cdnjs.cloudflare.com; " .
       "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; " .
       "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; " .
       "img-src 'self' data: https:; " .
       "connect-src 'self' https://api.paysera.com https://api.raiffeisen.al; " .
       "frame-ancestors 'none'; " .
       "base-uri 'self'; " .
       "form-action 'self'";

if ($app_env !== 'development') {
    header("Content-Security-Policy: " . $csp);
}

// X-Frame-Options - Parandaloj clickjacking
header("X-Frame-Options: DENY");

// X-Content-Type-Options - Parandaloj MIME sniffing
header("X-Content-Type-Options: nosniff");

// X-XSS-Protection - Enable XSS protection në browser
header("X-XSS-Protection: 1; mode=block");

// Strict-Transport-Security (HSTS) - Force HTTPS
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
}

// Referrer-Policy
header("Referrer-Policy: strict-origin-when-cross-origin");

// Permissions-Policy (previously Feature-Policy)
header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=()");

// Remove X-Powered-By header (don't reveal server info)
header_remove('X-Powered-By');

// ==========================================
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'Noteria';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Global configuration array
$config = [
    // Payment gateway configurations
    'paysera' => [
        'projectid' => '12345',            // Replace with actual Paysera project ID
        'password' => 'YOUR_PASSWORD',     // Replace with actual Paysera password/secret
        'test_mode' => true,               // Set to false for production
        'callback_url' => 'https://noteria.al/payment_callback.php',
        'success_url' => 'https://noteria.al/payment_confirmation.php?status=success',
        'cancel_url' => 'https://noteria.al/payment_confirmation.php?status=cancel',
        'api_url' => 'https://sandbox.paysera.com/pay/',  // Use https://www.paysera.com/pay/ for production
    ],
    'raiffeisen' => [
        'merchantId' => 'MERCHANT_ID',     // Replace with actual merchant ID
        'terminalId' => 'TERMINAL_ID',     // Replace with actual terminal ID
        'secretKey' => 'SECRET_KEY',       // Replace with actual secret key
        'test_mode' => true,               // Set to false for production
        'callback_url' => 'https://noteria.al/payment_callback.php',
        'success_url' => 'https://noteria.al/payment_confirmation.php?status=success',
        'cancel_url' => 'https://noteria.al/payment_confirmation.php?status=cancel',
        'api_url' => 'https://ecommerce-test.raiffeisen.al/vpos/',  // Use production URL for live environment
    ],
    'bkt' => [
        'merchantId' => 'MERCHANT_ID',     // Replace with actual merchant ID
        'terminalId' => 'TERMINAL_ID',     // Replace with actual terminal ID
        'secretKey' => 'SECRET_KEY',       // Replace with actual secret key
        'test_mode' => true,               // Set to false for production
        'callback_url' => 'https://noteria.al/payment_callback.php',
        'success_url' => 'https://noteria.al/payment_confirmation.php?status=success',
        'cancel_url' => 'https://noteria.al/payment_confirmation.php?status=cancel',
        'api_url' => 'https://test.bkt.al/payment/',  // Use production URL for live environment
    ],
    
    // Services configuration
    'video_consultation' => [
        'price' => 15.00,                 // Price in EUR
        'currency' => 'EUR',              // Currency
        'duration' => 30,                 // Duration in minutes
        'description' => 'Konsulencë video 30 minutëshe me noter',
    ],
    
    // Application settings
    'site_url' => 'https://noteria.com',
    'app_name' => 'Noteria',
    'support_email' => 'support@noteria.com',
    'default_language' => 'sq',
];

// Krijimi i lidhjes me databazën
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log("Gabim në lidhjen me databazën: " . $e->getMessage());
    die("<div style='color:#d32f2f; background:#ffeaea; border-radius:8px; padding:10px; max-width:420px; margin:40px auto; font-family:Montserrat;'>Ndodhi një gabim në lidhjen me databazën. Ju lutemi provoni përsëri ose kontaktoni administratorin.</div>");
}

// Funksion për shfaqjen e gabimeve për përdoruesin
function show_error($message) {
    error_log($message); // Log gabimin në server
    echo "<div style='color:#d32f2f; background:#ffeaea; border-radius:8px; padding:10px; max-width:420px; margin:40px auto; font-family:Montserrat;'>Ndodhi një gabim. Ju lutemi provoni përsëri ose kontaktoni administratorin.</div>";
}

// Funksion për të marrë lokacionin nga IP (dummy function)
function get_location_from_ip($ip) {
    // Në një mjedis prodhimi, këtu do të përdorim një API për të marrë lokacionin
    return "Prishtinë, Kosovë";
}

// Twilio SMS configuration
$twilio_sid = 'TWILIO_ACCOUNT_SID';
$twilio_token = 'TWILIO_AUTH_TOKEN';
$twilio_from = '+YOUR_TWILIO_NUMBER';

function send2faSMS($to, $code) {
    global $twilio_sid, $twilio_token, $twilio_from;
    
    // Skip if using placeholder credentials
    if (strpos($twilio_sid, 'TWILIO') !== false || strpos($twilio_token, 'TWILIO') !== false) {
        error_log("SMS skipped: Twilio credentials not configured. Recipient: $to, Code: $code");
        return true; // Return true to continue flow for testing
    }
    
    $url = "https://api.twilio.com/2010-04-01/Accounts/$twilio_sid/Messages.json";
    $post_data = http_build_query([
        'From' => $twilio_from,
        'To' => $to,
        'Body' => "Kodi juaj i verifikimit Noteria: $code"
    ]);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_USERPWD, "$twilio_sid:$twilio_token");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code >= 200 && $http_code < 300) {
        return true;
    } else {
        error_log("Twilio SMS error ($http_code): $response");
        return false;
    }
}
