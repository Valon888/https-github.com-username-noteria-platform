<?php
/**
 * Debug Login Flow
 * Teston procesin e kyçjes me detaje debugging
 */

// Fillimi i sigurt i sesionit
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

require_once 'confidb.php';
require_once 'developer_config.php';
require_once 'config.php';

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

// Handle login
$loginResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password'] ?? '');
    
    try {
        $stmt = $pdo->prepare("SELECT id, email, password, emri FROM admins WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['password'])) {
            // Set session
            $_SESSION["admin_id"] = $admin['id'];
            $_SESSION["admin_email"] = $admin['email'];
            $_SESSION["admin_name"] = $admin['emri'];
            $_SESSION["is_developer"] = isDeveloper($admin['id'], $admin['email']);
            
            $loginResult = "success";
            header("Location: billing_dashboard.php");
            exit();
        } else {
            $loginResult = "failed";
        }
    } catch (Exception $e) {
        $loginResult = "error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Debug Login</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .test { margin: 15px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #667eea; }
        .success { border-left-color: #10b981; }
        .error { border-left-color: #ef4444; }
        code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
        h2 { color: #333; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
        td:first-child { font-weight: bold; width: 40%; }
        form div { margin: 10px 0; }
        input { width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box; }
        button { padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Debug Login Flow</h1>
        
        <div class="test success">
            <h2>1. Session Information</h2>
            <table>
                <tr><td>Session Status:</td><td><?= (session_status() === PHP_SESSION_ACTIVE ? "ACTIVE" : "NOT ACTIVE") ?></td></tr>
                <tr><td>Session ID:</td><td><code><?= session_id() ?></code></td></tr>
                <tr><td>Session Name:</td><td><?= ini_get('session.name') ?></td></tr>
            </table>
        </div>

        <?php
        try {
            $stmt = $pdo->query("SELECT 1");
            echo '<div class="test success">';
            echo '<h2>2. Database Connection</h2>';
            echo '<p>OK</p>';
            echo '</div>';
        } catch (Exception $e) {
            echo '<div class="test error">';
            echo '<h2>2. Database Connection</h2>';
            echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
        ?>

        <?php
        try {
            $stmt = $pdo->prepare("SELECT id, email, password, emri FROM admins WHERE email = ? AND status = 'active' LIMIT 1");
            $stmt->execute(['admin@noteria.al']);
            $admin = $stmt->fetch();
            
            if ($admin) {
                echo '<div class="test success">';
                echo '<h2>3. Admin Account</h2>';
                echo '<table>';
                echo '<tr><td>ID:</td><td>' . htmlspecialchars($admin['id']) . '</td></tr>';
                echo '<tr><td>Email:</td><td>' . htmlspecialchars($admin['email']) . '</td></tr>';
                echo '<tr><td>Name:</td><td>' . htmlspecialchars($admin['emri']) . '</td></tr>';
                echo '<tr><td>Password Verify:</td><td>' . (password_verify('Admin@2025', $admin['password']) ? 'CORRECT' : 'WRONG') . '</td></tr>';
                echo '</table>';
                echo '</div>';
            } else {
                echo '<div class="test error">';
                echo '<h2>3. Admin Account</h2>';
                echo '<p>Admin not found</p>';
                echo '</div>';
            }
        } catch (Exception $e) {
            echo '<div class="test error">';
            echo '<h2>3. Admin Account</h2>';
            echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
        ?>

        <?php
        try {
            $isDev = isDeveloper(1, 'admin@noteria.al');
            echo '<div class="test success">';
            echo '<h2>4. Developer Check</h2>';
            echo '<p>isDeveloper(1, admin@noteria.al): ' . ($isDev ? 'TRUE' : 'FALSE') . '</p>';
            echo '</div>';
        } catch (Exception $e) {
            echo '<div class="test error">';
            echo '<h2>4. Developer Check</h2>';
            echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
        ?>

        <div class="test">
            <h2>5. Test Login</h2>
            <?php if ($loginResult): ?>
                <p style="color: <?= $loginResult === 'success' ? 'green' : 'red' ?>">
                    <?= $loginResult === 'success' ? 'Login successful!' : 'Login failed: ' . htmlspecialchars($loginResult) ?>
                </p>
            <?php endif; ?>
            <form method="POST">
                <div>
                    <label>Email: <input type="email" name="email" value="admin@noteria.al" required></label>
                </div>
                <div>
                    <label>Password: <input type="password" name="password" value="Admin@2025" required></label>
                </div>
                <div style="margin: 10px 0; padding: 10px; background: #f0f0f0; border-radius: 4px;">
                    <strong>Kredencialet për testim:</strong><br>
                    Email: <code>admin@noteria.al</code><br>
                    Password: <code>Admin@2025</code>
                </div>
                <button type="submit">Test Login</button>
            </form>
        </div>
    </div>
</body>
</html>
?>
