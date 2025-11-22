<?php
/**
 * Logout Handler
 * Siguro se përdoruesi logout saktë
 */

require_once 'config.php';

// Log logout
if (isset($_SESSION['user_id'])) {
    error_log("USER_LOGOUT: user_" . $_SESSION['user_id'] . " from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
} elseif (isset($_SESSION['admin_id'])) {
    error_log("ADMIN_LOGOUT: admin_" . $_SESSION['admin_id'] . " from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

// Destroy session
logoutUser();

// Determine redirect
$redirect = 'login.php';
if (isset($_GET['type']) && $_GET['type'] === 'admin') {
    $redirect = 'admin_login.php';
}

// Add success message
header("Location: $redirect?message=logged_out");
exit();
?>
