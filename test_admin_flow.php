<?php
/**
 * Test Admin Login Flow
 * Simulon të gjithë stepat e admin login-it
 */

require_once 'confidb.php';
require_once 'developer_config.php';

echo "=== ADMIN LOGIN FLOW TEST ===\n\n";

// Step 1: Query admins table
echo "[STEP 1] Query admins table për admin@noteria.al\n";
try {
    $stmt = $pdo->prepare("SELECT id, emri, password FROM admins WHERE email = ?");
    $stmt->execute(['admin@noteria.al']);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "[OK] Admin found: " . $admin['emri'] . " (ID: " . $admin['id'] . ")\n";
        echo "  Password hash: " . substr($admin['password'], 0, 20) . "...\n\n";
    } else {
        echo "[FAIL] Admin not found\n\n";
        exit(1);
    }
} catch (PDOException $e) {
    echo "[ERROR] Database error: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Step 2: Verify password
echo "[STEP 2] Verify password with bcrypt\n";
$password = "Admin@2025";
if (password_verify($password, $admin['password'])) {
    echo "[OK] Password verified successfully\n\n";
} else {
    echo "[FAIL] Password verification failed\n\n";
    exit(1);
}

// Step 3: Check if developer
echo "[STEP 3] Check isDeveloper function\n";
$isDev = isDeveloper($admin['id'], 'admin@noteria.al');
if ($isDev) {
    echo "[OK] User is a developer\n";
    echo "  Admin ID: " . $admin['id'] . "\n";
    echo "  Email: admin@noteria.al\n\n";
} else {
    echo "[FAIL] User is not a developer\n\n";
    exit(1);
}

// Step 4: Simulate session setup
echo "[STEP 4] Simulate session variables\n";
session_start();
$_SESSION["user_id"] = $admin['id'];
$_SESSION["emri"] = $admin['emri'] ?? "Administrator";
$_SESSION["mbiemri"] = "Developer";
$_SESSION["roli"] = "admin";
$_SESSION["last_activity"] = time();

echo "[OK] Session variables set:\n";
echo "  user_id: " . $_SESSION['user_id'] . "\n";
echo "  emri: " . $_SESSION['emri'] . "\n";
echo "  mbiemri: " . $_SESSION['mbiemri'] . "\n";
echo "  roli: " . $_SESSION['roli'] . "\n";
echo "  last_activity: " . $_SESSION['last_activity'] . "\n\n";

// Step 5: Test dashboard.php session compatibility
echo "[STEP 5] Test dashboard.php compatibility\n";
try {
    // Simulate what dashboard.php does
    checkSessionTimeout(1800, 'login.php');
    echo "[OK] Session timeout check passed\n";
    
    if (!isset($_SESSION['user_id'])) {
        echo "[FAIL] user_id not in session\n";
        exit(1);
    }
    echo "[OK] user_id exists in session\n";
    
    // Check if dashboard can read these values
    $user_id = $_SESSION['user_id'];
    $roli = $_SESSION['roli'] ?? null;
    $zyra_id = $_SESSION['zyra_id'] ?? 1; // Default to 1
    
    echo "[OK] Dashboard can read:\n";
    echo "  user_id: $user_id\n";
    echo "  roli: $roli\n";
    echo "  zyra_id: $zyra_id\n\n";
    
} catch (Exception $e) {
    echo "[ERROR] Error during dashboard simulation: " . $e->getMessage() . "\n\n";
    exit(1);
}

echo "=== ALL TESTS PASSED ===\n";
echo "Admin login flow is ready to use!\n";

?>
