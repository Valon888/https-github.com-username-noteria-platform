<?php
/**
 * MFA Setup Helper Functions
 * 
 * Përfshin:
 * - Gjenero unique TOTP secret
 * - Gjenero backup codes
 * - QR code URL generation
 * - TOTP verification
 */

require_once __DIR__ . '/vendor/autoload.php';

use Sonata\GoogleAuthenticator\GoogleAuthenticator;

/**
 * Gjenero unique TOTP secret për përdoruesin
 * 
 * @return string 32-character secret
 */
function generateMFASecret() {
    $g = new GoogleAuthenticator();
    return $g->generateSecret();
}

/**
 * Gjenero backup codes për MFA
 * 
 * @param int $count Numri i backup codes
 * @return array Array i backup codes
 */
function generateBackupCodes($count = 10) {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $codes[] = strtoupper(bin2hex(random_bytes(4))); // 8-character hex codes
    }
    return $codes;
}

/**
 * Gjenero QR code URL për Google Authenticator
 * 
 * @param string $email Email i përdoruesit
 * @param string $secret TOTP secret
 * @param string $appName Emri i aplikacionit
 * @return string URL për QR code
 */
function generateQRCodeUrl($email, $secret, $appName = 'Noteria') {
    $otpauth_url = 'otpauth://totp/' . urlencode("$appName:$email") . 
                   '?secret=' . urlencode($secret) . 
                   '&issuer=' . urlencode($appName) .
                   '&algorithm=SHA1&digits=6&period=30';
    
    // Google Charts API për QR code
    return 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . urlencode($otpauth_url);
}

/**
 * Verifikohet TOTP code-i
 * 
 * @param string $secret TOTP secret
 * @param string $code 6-digit TOTP code
 * @return bool True nëse code-i është i saktë
 */
function verifyTOTPCode($secret, $code) {
    try {
        $g = new GoogleAuthenticator();
        return $g->checkCode($secret, $code);
    } catch (Exception $e) {
        error_log("TOTP verification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Setup MFA për përdoruesin
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param string $secret TOTP secret (optional, do të gjenerohet nëse mungon)
 * @return array ['secret' => '...', 'backup_codes' => [...], 'qr_url' => '...']
 */
function setupUserMFA($pdo, $user_id, $secret = null) {
    if (!$secret) {
        $secret = generateMFASecret();
    }
    
    $backup_codes = generateBackupCodes(10);
    $backup_codes_json = json_encode($backup_codes);
    
    // Get user email for QR code
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception("User not found");
    }
    
    $qr_url = generateQRCodeUrl($user['email'], $secret);
    
    // Ruaj në databazë
    $insert_stmt = $pdo->prepare("
        INSERT INTO user_mfa (user_id, secret, backup_codes, is_verified)
        VALUES (?, ?, ?, 0)
        ON DUPLICATE KEY UPDATE secret = VALUES(secret), backup_codes = VALUES(backup_codes), is_verified = 0
    ");
    
    $insert_stmt->execute([$user_id, $secret, $backup_codes_json]);
    
    return [
        'secret' => $secret,
        'backup_codes' => $backup_codes,
        'qr_url' => $qr_url,
        'manual_entry' => generateManualEntryString($user['email'], $secret)
    ];
}

/**
 * Setup MFA për admin
 * 
 * @param PDO $pdo Database connection
 * @param int $admin_id Admin ID
 * @param string $secret TOTP secret (optional)
 * @return array
 */
function setupAdminMFA($pdo, $admin_id, $secret = null) {
    if (!$secret) {
        $secret = generateMFASecret();
    }
    
    $backup_codes = generateBackupCodes(10);
    $backup_codes_json = json_encode($backup_codes);
    
    // Get admin email for QR code
    $stmt = $pdo->prepare("SELECT email FROM admins WHERE id = ? LIMIT 1");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        throw new Exception("Admin not found");
    }
    
    $qr_url = generateQRCodeUrl($admin['email'], $secret);
    
    // Ruaj në databazë
    $insert_stmt = $pdo->prepare("
        INSERT INTO admin_mfa (admin_id, secret, backup_codes, is_verified)
        VALUES (?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE secret = VALUES(secret), backup_codes = VALUES(backup_codes), verified_at = NOW()
    ");
    
    $insert_stmt->execute([$admin_id, $secret, $backup_codes_json]);
    
    return [
        'secret' => $secret,
        'backup_codes' => $backup_codes,
        'qr_url' => $qr_url,
        'manual_entry' => generateManualEntryString($admin['email'], $secret)
    ];
}

/**
 * Verifikohet MFA për user
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param string $code 6-digit TOTP code
 * @return bool
 */
function verifyUserMFA($pdo, $user_id, $code) {
    $stmt = $pdo->prepare("SELECT secret FROM user_mfa WHERE user_id = ? AND is_verified = 1 LIMIT 1");
    $stmt->execute([$user_id]);
    $mfa = $stmt->fetch();
    
    if (!$mfa) {
        return false;
    }
    
    return verifyTOTPCode($mfa['secret'], $code);
}

/**
 * Verifikohet MFA për admin
 * 
 * @param PDO $pdo Database connection
 * @param int $admin_id Admin ID
 * @param string $code 6-digit TOTP code
 * @return bool
 */
function verifyAdminMFA($pdo, $admin_id, $code) {
    $stmt = $pdo->prepare("SELECT secret FROM admin_mfa WHERE admin_id = ? AND is_verified = 1 LIMIT 1");
    $stmt->execute([$admin_id]);
    $mfa = $stmt->fetch();
    
    if (!$mfa) {
        return false;
    }
    
    return verifyTOTPCode($mfa['secret'], $code);
}

/**
 * Gjenero string për manual entry në Google Authenticator
 * Format: issuer (account)
 * 
 * @param string $email Email i përdoruesit
 * @param string $secret TOTP secret
 * @return string Manual entry string
 */
function generateManualEntryString($email, $secret) {
    $parts = explode('@', $email);
    $username = $parts[0];
    return "Noteria ($username) | Secret: " . chunk_split($secret, 4, ' ');
}

/**
 * Kontrollo nëse përdoruesi ka MFA të aktivizuar
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return bool
 */
function userHasMFA($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_mfa WHERE user_id = ? AND is_verified = 1");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result['count'] > 0;
}

/**
 * Kontrollo nëse admin ka MFA
 * 
 * @param PDO $pdo Database connection
 * @param int $admin_id Admin ID
 * @return bool
 */
function adminHasMFA($pdo, $admin_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM admin_mfa WHERE admin_id = ? AND is_verified = 1");
    $stmt->execute([$admin_id]);
    $result = $stmt->fetch();
    return $result['count'] > 0;
}

?>
