<?php
/**
 * Session Management Helper
 * 
 * Përfshin:
 * - Session timeout management
 * - Activity tracking
 * - Session security
 */

/**
 * Inicialize session me security settings
 */
function initializeSecureSession() {
    // Start session only if not already started
    if (session_status() === PHP_SESSION_NONE) {
        // Session configuration (must be set before session_start)
        ini_set('session.use_only_cookies', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_httponly', 1);
        // Set secure flag nëse HTTPS aktive
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            ini_set('session.cookie_secure', 1);
        }
        // Session timeout
        $timeout = getenv('SESSION_TIMEOUT') ?: 1800; // 30 minuta default
        ini_set('session.gc_maxlifetime', $timeout);
        ini_set('session.gc_probability', 1);
        ini_set('session.gc_divisor', 100);
        session_start();
    }
}

/**
 * Kontrollo session timeout
 * Logout përdoruesi nëse session ka expired
 * 
 * @param int $timeout Timeout në sekonda (default: 1800 = 30 minuta)
 * @param string $redirect_url URL për redirect pas timeout
 * @return bool True nëse session aktive, false nëse timeout
 */
function checkSessionTimeout($timeout = 1800, $redirect_url = '/login.php') {
    // Skip timeout check për guest pages
    $skip_timeout_pages = ['login.php', 'register.php', 'forgot_password.php', 'admin_login.php'];
    $current_page = basename($_SERVER['PHP_SELF']);
    
    if (in_array($current_page, $skip_timeout_pages)) {
        return true;
    }
    
    // Nëse nuk ka session user, skip
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
        return true;
    }
    
    // Get timeout value from .env
    $timeout = getenv('SESSION_TIMEOUT') ?: $timeout;
    
    // Check nëse session ka expired
    if (isset($_SESSION['last_activity'])) {
        $inactive_time = time() - $_SESSION['last_activity'];
        
        if ($inactive_time > $timeout) {
            // Session timeout - destroy and redirect
            error_log("SESSION_TIMEOUT: " . 
                     (isset($_SESSION['user_id']) ? "user_" . $_SESSION['user_id'] : 
                      "admin_" . $_SESSION['admin_id']) . 
                     " inactive for " . $inactive_time . " seconds");
            
            // Clear session
            session_destroy();
            session_start();
            $_SESSION['timeout_message'] = "Sesioni juaj ka skaduar. Ju lutemi kyçuni përsëri.";
            
            // Redirect
            header("Location: " . $redirect_url . "?message=session_expired");
            exit();
        }
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    
    return true;
}

/**
 * Regenerate session ID për security
 * Thirrni këtë pas login
 */
function regenerateSessionId() {
    if (session_status() !== PHP_SESSION_NONE) {
        session_regenerate_id(true);
    }
}

/**
 * Logout përdoruesi - destroy session
 */
function logoutUser() {
    // Log logout activity
    if (isset($_SESSION['user_id'])) {
        error_log("USER_LOGOUT: user_" . $_SESSION['user_id'] . " from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    } elseif (isset($_SESSION['admin_id'])) {
        error_log("ADMIN_LOGOUT: admin_" . $_SESSION['admin_id'] . " from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }
    
    // Clear session
    $_SESSION = [];
    
    if (session_status() !== PHP_SESSION_NONE) {
        session_destroy();
    }
    
    // Clear cookies
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
}

/**
 * Kontrollo nëse përdoruesi ka timed out (message display)
 * 
 * @return string|null Timeout message nëse timeout, null otherwise
 */
function getTimeoutMessage() {
    if (isset($_SESSION['timeout_message'])) {
        $msg = $_SESSION['timeout_message'];
        unset($_SESSION['timeout_message']);
        return $msg;
    }
    return null;
}

/**
 * Get remaining session time në sekonda
 * 
 * @return int Remaining time në sekonda
 */
function getRemainingSessionTime($timeout = 1800) {
    $timeout = getenv('SESSION_TIMEOUT') ?: $timeout;
    
    if (!isset($_SESSION['last_activity'])) {
        return $timeout;
    }
    
    $elapsed = time() - $_SESSION['last_activity'];
    $remaining = max(0, $timeout - $elapsed);
    
    return $remaining;
}

/**
 * Get remaining session time në minute
 * 
 * @return float Remaining time në minute
 */
function getRemainingSessionTimeMinutes($timeout = 1800) {
    return ceil(getRemainingSessionTime($timeout) / 60);
}

/**
 * Check nëse session aktive
 * 
 * @return bool
 */
function isSessionActive() {
    return isset($_SESSION['user_id']) || isset($_SESSION['admin_id']);
}

/**
 * Get current user ID
 * 
 * @return int|null User ID ose null nëse nuk është kyçur
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current admin ID
 * 
 * @return int|null Admin ID ose null nëse nuk është kyçur
 */
function getCurrentAdminId() {
    return $_SESSION['admin_id'] ?? null;
}

?>
