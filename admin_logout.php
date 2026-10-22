<?php
// Move all session and ini_set calls to the very top, before any output
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
ini_set('session.use_strict_mode', 1);

// Redirekto në HTTPS nëse nuk është aktiv dhe nëse variablat ekzistojnë
if (
    (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') &&
    isset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'])
) {
    $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('Location: ' . $redirect);
    exit();
}

// Cloudflare real IP support
if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
}

if (!file_exists('confidb.php')) {
    die("Gabim: File-i 'confidb.php' nuk ekziston.");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'confidb.php';
require_once 'activity_logger.php';

// Kontrollo nëse përdoruesi është logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// Kontrollo nëse përdoruesi është admin
if ($_SESSION["roli"] !== "admin") {
    die("Qasja e ndaluar. Vetëm administratorit lejohet.");
}

// Log aktivitetin e logout
$user_id = $_SESSION["user_id"];
log_activity($pdo, $user_id, 'Dalëje', 'Dalëje e suksesshme nga admin panel');

// Unset të gjithë session variables
unset($_SESSION["user_id"]);
unset($_SESSION["emri"]);
unset($_SESSION["mbiemri"]);
unset($_SESSION["email"]);
unset($_SESSION["roli"]);
unset($_SESSION['last_activity']);
unset($_SESSION['login_attempts']);
unset($_SESSION['last_login_attempt']);
unset($_SESSION['captcha_text']);
unset($_SESSION['regenerated']);

// Destroy session
session_destroy();

// Redirekto në login page
header("Location: login.php?logout=success");
exit();
?>
