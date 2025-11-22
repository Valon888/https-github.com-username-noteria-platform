<?php
/**
 * Test Session Persistence
 * Teston nëse sesioni vendoset dhe ruhet saktë
 */

// Fillimi i sigurt i sesionit
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

require_once 'config.php';
require_once 'confidb.php';
require_once 'developer_config.php';

echo "=== TEST SESSION ===\n\n";

// Test 1: Kontrollo nëse sesioni është i hapur
echo "[TEST 1] Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? "ACTIVE ✓" : "NOT ACTIVE ✗") . "\n";
echo "[TEST 1] Session ID: " . session_id() . "\n\n";

// Test 2: Vendo variabla të sesionit
$_SESSION['test_admin_id'] = 1;
$_SESSION['test_admin_email'] = 'admin@noteria.al';
$_SESSION['test_admin_name'] = 'Test Admin';
$_SESSION['test_is_developer'] = true;

echo "[TEST 2] Session variables set:\n";
echo "  - admin_id: " . $_SESSION['test_admin_id'] . "\n";
echo "  - admin_email: " . $_SESSION['test_admin_email'] . "\n";
echo "  - admin_name: " . $_SESSION['test_admin_name'] . "\n";
echo "  - is_developer: " . ($_SESSION['test_is_developer'] ? 'true' : 'false') . "\n\n";

// Test 3: Kontrollo databazën
try {
    $stmt = $pdo->prepare("SELECT id, email, password, emri FROM admins WHERE email = ? AND status = 'active' LIMIT 1");
    $stmt->execute(['admin@noteria.al']);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "[TEST 3] Admin from database ✓\n";
        echo "  - ID: " . $admin['id'] . "\n";
        echo "  - Email: " . $admin['email'] . "\n";
        echo "  - Name: " . $admin['emri'] . "\n";
        
        // Test password
        $testPassword = 'Admin@2025';
        $passwordMatch = password_verify($testPassword, $admin['password']);
        echo "  - Password Match: " . ($passwordMatch ? 'YES ✓' : 'NO ✗') . "\n\n";
    } else {
        echo "[TEST 3] Admin NOT found in database ✗\n\n";
    }
} catch (Exception $e) {
    echo "[TEST 3] Database Error: " . $e->getMessage() . "\n\n";
}

// Test 4: Kontrollo isDeveloper funksion
try {
    $isDev = isDeveloper(1, 'admin@noteria.al');
    echo "[TEST 4] isDeveloper(1, 'admin@noteria.al'): " . ($isDev ? 'true ✓' : 'false') . "\n\n";
} catch (Exception $e) {
    echo "[TEST 4] isDeveloper Error: " . $e->getMessage() . "\n\n";
}

// Test 5: Emuloni login dhe session set
echo "[TEST 5] Simulating login process:\n";
if ($admin && password_verify('Admin@2025', $admin['password'])) {
    $_SESSION["admin_id"] = $admin['id'];
    $_SESSION["admin_email"] = $admin['email'];
    $_SESSION["admin_name"] = $admin['emri'];
    $_SESSION["is_developer"] = isDeveloper($admin['id'], $admin['email']);
    
    echo "  Session set successfully ✓\n";
    echo "  - admin_id: " . $_SESSION["admin_id"] . "\n";
    echo "  - admin_email: " . $_SESSION["admin_email"] . "\n";
    echo "  - admin_name: " . $_SESSION["admin_name"] . "\n";
    echo "  - is_developer: " . ($_SESSION["is_developer"] ? 'true' : 'false') . "\n";
    echo "  - Session ID: " . session_id() . "\n\n";
    
    // Test 6: Kontrollo nëse $_SESSION përmban admin_id
    echo "[TEST 6] Session contains admin_id: " . (isset($_SESSION['admin_id']) ? 'YES ✓' : 'NO ✗') . "\n";
    echo "[TEST 6] Session admin_id value: " . (isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 'EMPTY') . "\n\n";
} else {
    echo "  Password verification failed ✗\n\n";
}

// Test 7: Kontrollo headers nga php ini
echo "[TEST 7] PHP Session Configuration:\n";
echo "  - session.cookie_httponly: " . ini_get('session.cookie_httponly') . "\n";
echo "  - session.cookie_secure: " . ini_get('session.cookie_secure') . "\n";
echo "  - session.use_strict_mode: " . ini_get('session.use_strict_mode') . "\n";
echo "  - session.cookie_samesite: " . ini_get('session.cookie_samesite') . "\n";
echo "  - session.save_path: " . ini_get('session.save_path') . "\n";
echo "  - session.name: " . ini_get('session.name') . "\n\n";

echo "=== END TEST ===\n";
?>
