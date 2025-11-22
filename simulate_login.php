<?php
/**
 * Simulate admin login POST request
 */

// Start session
session_start();

// Set up POST data
$_POST['email'] = 'admin@noteria.al';
$_POST['password'] = 'Admin@2025';
$_POST['csrf_token'] = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $_POST['csrf_token'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

echo "=== ADMIN LOGIN SIMULATION ===\n\n";
echo "Email: " . $_POST['email'] . "\n";
echo "Password: " . $_POST['password'] . "\n";
echo "Request method: " . $_SERVER['REQUEST_METHOD'] . "\n\n";

// Include necessary files
require_once 'confidb.php';
require_once 'developer_config.php';

echo "[Step 1] Query admins table for email\n";
$stmt = $pdo->prepare("SELECT id, email, password, emri FROM admins WHERE email = ?");
$stmt->execute([$_POST['email']]);
$admin = $stmt->fetch();

if (!$admin) {
    echo "  ERROR: Admin not found\n";
    exit(1);
}

echo "  OK: Admin found (ID: " . $admin['id'] . ")\n\n";

echo "[Step 2] Verify password\n";
if (!password_verify($_POST['password'], $admin['password'])) {
    echo "  ERROR: Password mismatch\n";
    exit(1);
}

echo "  OK: Password verified\n\n";

echo "[Step 3] Check if developer\n";
$_SESSION["is_developer"] = isDeveloper($admin['id'], $_POST['email']);
echo "  is_developer: " . ($_SESSION["is_developer"] ? "TRUE" : "FALSE") . "\n\n";

echo "[Step 4] Set session variables\n";
$_SESSION["user_id"] = $admin['id'];
$_SESSION["emri"] = $admin['emri'] ?? "Administrator";
$_SESSION["mbiemri"] = "Developer";
$_SESSION["roli"] = "admin";
$_SESSION['last_activity'] = time();

echo "  user_id: " . $_SESSION["user_id"] . "\n";
echo "  emri: " . $_SESSION["emri"] . "\n";
echo "  mbiemri: " . $_SESSION["mbiemri"] . "\n";
echo "  roli: " . $_SESSION["roli"] . "\n";
echo "  last_activity: " . $_SESSION['last_activity'] . "\n\n";

echo "[Step 5] Redirect would happen to: dashboard.php\n\n";

echo "=== LOGIN SIMULATION SUCCESS ===\n";
echo "All steps completed successfully!\n";
echo "Admin should be redirected to dashboard.php\n";
?>
