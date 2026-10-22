<?php
/**
 * e-Noteria Advanced Security Configuration
 * Nivele shumë të larta sigurie për platformën SaaS
 */

// Prevent direct access - only allow inclusion from other PHP files
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    die('Direct access not permitted');
}

// Security Constants
define('ENCRYPTION_KEY', 'e-noteria-2026-secure-encryption-key-256-bit');
define('CSRF_TOKEN_LENGTH', 32);
define('SESSION_LIFETIME', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes
define('PASSWORD_MIN_LENGTH', 12);
define('REQUIRE_SPECIAL_CHARS', true);
define('REQUIRE_NUMBERS', true);
define('REQUIRE_UPPERCASE', true);

// Security Headers Configuration
class SecurityHeaders {
    public static function setAllHeaders() {
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src \'self\' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src \'self\' data: https:; connect-src \'self\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; frame-ancestors \'none\';');

        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');

        // Enable XSS protection
        header('X-XSS-Protection: 1; mode=block');

        // Prevent referrer leakage
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // HSTS - HTTP Strict Transport Security
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');

        // Prevent caching of sensitive content
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Feature Policy / Permissions Policy
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), magnetometer=(), gyroscope=(), fullscreen=(self), payment=()');

        // Remove server information
        header_remove('X-Powered-By');
        header_remove('Server');

        // Custom security headers
        header('X-Security-Header: e-Noteria-Enterprise-Security');
        header('X-Content-Security-Header: Enabled');
    }
}

// Advanced Encryption Class
class SecureEncryption {
    private static $key;
    private static $cipher = 'AES-256-CBC';

    public static function init() {
        self::$key = hash('sha256', ENCRYPTION_KEY, true);
    }

    public static function encrypt($data) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::$cipher));
        $encrypted = openssl_encrypt($data, self::$cipher, self::$key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt($data) {
        $data = base64_decode($data);
        $iv_length = openssl_cipher_iv_length(self::$cipher);
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        return openssl_decrypt($encrypted, self::$cipher, self::$key, 0, $iv);
    }
}

// CSRF Protection Class
class CSRFProtection {
    public static function generateToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateToken($token) {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            SecurityLogger::logSecurityEvent('CSRF token validation failed');
            die('CSRF token validation failed');
        }
        // Regenerate token after successful validation
        unset($_SESSION['csrf_token']);
        return true;
    }

    public static function getTokenField() {
        return '<input type="hidden" name="csrf_token" value="' . self::generateToken() . '">';
    }
}

// Rate Limiting Class
class RateLimiter {
    private static $redis = null;

    public static function init() {
        // In production, connect to Redis
        // self::$redis = new Redis();
        // self::$redis->connect('127.0.0.1', 6379);
    }

    public static function checkRateLimit($key, $max_requests = 100, $time_window = 60) {
        $current_time = time();
        $window_start = $current_time - $time_window;

        // For demo purposes, using session. In production use Redis/database
        if (!isset($_SESSION['rate_limit'][$key])) {
            $_SESSION['rate_limit'][$key] = [];
        }

        // Clean old requests
        $_SESSION['rate_limit'][$key] = array_filter($_SESSION['rate_limit'][$key], function($timestamp) use ($window_start) {
            return $timestamp > $window_start;
        });

        if (count($_SESSION['rate_limit'][$key]) >= $max_requests) {
            SecurityLogger::logSecurityEvent('Rate limit exceeded for: ' . $key);
            return false;
        }

        // Add current request
        $_SESSION['rate_limit'][$key][] = $current_time;
        return true;
    }
}

// Input Validation and Sanitization
class InputValidator {
    public static function sanitizeString($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validatePassword($password) {
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            return false;
        }

        if (REQUIRE_SPECIAL_CHARS && !preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
            return false;
        }

        if (REQUIRE_NUMBERS && !preg_match('/\d/', $password)) {
            return false;
        }

        if (REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
            return false;
        }

        return true;
    }

    public static function validatePhone($phone) {
        // Kosovo phone number validation
        $phone = preg_replace('/\D/', '', $phone);
        return preg_match('/^(\+383|0)[0-9]{8,9}$/', $phone);
    }

    public static function isSQLInjection($input) {
        $patterns = [
            '/(\b(union|select|insert|update|delete|drop|create|alter|exec|execute)\b)/i',
            '/(\'|\")/',
            '/(\-\-)/',
            '/(\/\*)/',
            '/(\*\/)/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                SecurityLogger::logSecurityEvent('Potential SQL injection detected: ' . substr($input, 0, 100));
                return true;
            }
        }
        return false;
    }

    public static function isXSS($input) {
        $patterns = [
            '/<script/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                SecurityLogger::logSecurityEvent('Potential XSS detected: ' . substr($input, 0, 100));
                return true;
            }
        }
        return false;
    }
}

// Session Security Management
class SecureSession {
    public static function init() {
        // Secure session configuration
        ini_set('session.cookie_httponly', 1);
        // Only require HTTPS in production, allow HTTP for development
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Regenerate session ID periodically
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }

        // Check for session hijacking
        if (!isset($_SESSION['user_agent'])) {
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        } elseif (isset($_SERVER['HTTP_USER_AGENT']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
            SecurityLogger::logSecurityEvent('Session hijacking detected');
            session_destroy();
            header('Location: login.php?error=session_security');
            exit;
        }

        // Check IP address consistency (optional, can be too strict)
        if (!isset($_SESSION['ip_address'])) {
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
        }
    }

    public static function destroy() {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }
}

// Login Security (Brute Force Protection)
class LoginSecurity {
    public static function checkBruteForce($username) {
        $key = 'login_attempts_' . md5($username);

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'last_attempt' => 0];
        }

        $attempts = &$_SESSION[$key];

        // Reset counter if enough time has passed
        if (time() - $attempts['last_attempt'] > LOCKOUT_TIME) {
            $attempts['count'] = 0;
        }

        $attempts['last_attempt'] = time();

        if ($attempts['count'] >= MAX_LOGIN_ATTEMPTS) {
            SecurityLogger::logSecurityEvent('Brute force protection triggered for user: ' . $username);
            return false; // Account locked
        }

        $attempts['count']++;
        return true;
    }

    public static function resetAttempts($username) {
        $key = 'login_attempts_' . md5($username);
        unset($_SESSION[$key]);
    }
}

// Security Audit Logging
class SecurityLogger {
    private static $log_file = 'security_audit.log';

    public static function logSecurityEvent($message, $level = 'WARNING') {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $user_id = $_SESSION['user_id'] ?? 'guest';

        $log_entry = sprintf(
            "[%s] [%s] [IP: %s] [User: %s] [UA: %s] %s\n",
            $timestamp,
            $level,
            $ip,
            $user_id,
            substr($user_agent, 0, 50),
            $message
        );

        // In production, use proper logging system
        error_log($log_entry, 3, self::$log_file);

        // Also log to system log (if available)
        if (function_exists('syslog')) {
            syslog(LOG_WARNING, 'e-Noteria Security: ' . $message);
        }
    }

    public static function logAccess($action, $resource = '') {
        $message = "Access: $action" . ($resource ? " - Resource: $resource" : "");
        self::logSecurityEvent($message, 'INFO');
    }
}

// Database Security
class DatabaseSecurity {
    public static function prepareSecureQuery($pdo, $query, $params = []) {
        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            SecurityLogger::logSecurityEvent('Database error: ' . $e->getMessage());
            throw new Exception('Database operation failed');
        }
    }

    public static function sanitizeForDatabase($input) {
        // Additional database-specific sanitization
        return trim($input);
    }
}

// File Upload Security
class FileSecurity {
    private static $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
    private static $max_file_size = 5242880; // 5MB

    public static function validateUpload($file) {
        // Check file size
        if ($file['size'] > self::$max_file_size) {
            SecurityLogger::logSecurityEvent('File too large: ' . $file['size']);
            return false;
        }

        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::$allowed_extensions)) {
            SecurityLogger::logSecurityEvent('Invalid file extension: ' . $extension);
            return false;
        }

        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed_mimes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/jpg'
        ];

        if (!in_array($mime_type, $allowed_mimes)) {
            SecurityLogger::logSecurityEvent('Invalid MIME type: ' . $mime_type);
            return false;
        }

        // Check for malicious content (basic)
        $content = file_get_contents($file['tmp_name']);
        if (self::containsMaliciousContent($content)) {
            SecurityLogger::logSecurityEvent('Malicious content detected in file upload');
            return false;
        }

        return true;
    }

    private static function containsMaliciousContent($content) {
        $malicious_patterns = [
            '/<\?php/i',
            '/<script/i',
            '/javascript:/i',
            '/eval\(/i',
            '/base64_decode/i'
        ];

        foreach ($malicious_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        return false;
    }

    public static function generateSecureFilename($original_name) {
        $extension = pathinfo($original_name, PATHINFO_EXTENSION);
        return uniqid('secure_', true) . '.' . $extension;
    }
}

// API Security for future API endpoints
class APISecurity {
    public static function validateAPIKey($key) {
        // In production, validate against database
        return !empty($key) && strlen($key) === 64; // Example validation
    }

    public static function rateLimitAPI($endpoint) {
        $key = 'api_' . md5($_SERVER['REMOTE_ADDR'] . $endpoint);
        return RateLimiter::checkRateLimit($key, 1000, 3600); // 1000 requests per hour
    }
}

// Initialize Security Components
function initSecurity() {
    // Set security headers
    SecurityHeaders::setAllHeaders();

    // Initialize secure session
    SecureSession::init();

    // Initialize encryption
    SecureEncryption::init();

    // Initialize rate limiter
    RateLimiter::init();

    // Log access
    SecurityLogger::logAccess('Page access', $_SERVER['REQUEST_URI'] ?? '');
}

// Global security initialization - commented out to prevent auto-init conflicts
// define('SECURE_ACCESS', true);
// initSecurity();
?>