<?php
// Database credentials
$host = "localhost";
$db = "noteria";
$user = "root";
$pass = "";

// 1. MySQLi Connection (For legacy code like index.php)
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("MySQLi Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// 2. PDO Connection (For new code and specific functions)
$pdo = null;
try {
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // If PDO fails, we log it but don't stop execution 
    // because some files only need the MySQLi $conn.
    error_log("PDO Connection Error: " . $e->getMessage());
}

/**
 * Function to get PDO connection
 * Used by updated files like video_call.php
 */
function connectToDatabase() {
    global $pdo;
    if ($pdo === null) {
        // Retry connection if it was null
        global $host, $db, $user, $pass;
        try {
            $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
            $GLOBALS['pdo'] = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            return $GLOBALS['pdo'];
        } catch (PDOException $e) {
            die("Critical: PDO Connection could not be established.");
        }
    }
    return $pdo;
}
?>