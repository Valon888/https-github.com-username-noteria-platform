<?php
/**
 * Admin Logout
 * Dalja e sigurt e adminit nga sistemi
 */

// Fillimi i sigurt i sesionit - PARA require_once
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// Pastro të gjitha session variables
$_SESSION = array();

// Nëse përdoren cookies për session, pastro edhe ato
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Shkatërro session
session_destroy();

// Redirect me mesazh suksesi
header("Location: admin_login.php?message=logged_out");
exit();
?>