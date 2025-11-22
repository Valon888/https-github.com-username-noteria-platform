<?php
/**
 * Test login credentials and fix if needed
 */

require_once 'config.php';

$email = 'admin@noteria.al';
$test_password = 'Admin@2025';

try {
    $stmt = $pdo->prepare("SELECT id, email, password, emri FROM admins WHERE email = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if (!$admin) {
        echo "[ERROR] Admin not found or inactive!\n";
        exit(1);
    }

    echo "[INFO] Admin found: {$admin['emri']}\n";
    echo "[TEST] Testing password verification...\n\n";

    if (password_verify($test_password, $admin['password'])) {
        echo "[SUCCESS] Password is CORRECT!\n";
        echo "You can login with:\n";
        echo "  Email: admin@noteria.al\n";
        echo "  Password: Admin@2025\n";
    } else {
        echo "[FAILED] Password mismatch. Updating...\n";
        
        // Generate new hash
        $new_hash = password_hash($test_password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        // Update in database
        $update_stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
        $update_stmt->execute([$new_hash, $admin['id']]);
        
        echo "[SUCCESS] Password updated successfully!\n";
        echo "\nLogin credentials:\n";
        echo "  Email: admin@noteria.al\n";
        echo "  Password: Admin@2025\n";
    }

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage();
}
