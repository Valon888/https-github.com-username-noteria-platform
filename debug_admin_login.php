<?php
/**
 * Debug Admin Login Flow
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

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Debug Admin Login</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .test { margin: 20px 0; padding: 15px; background: #f0f0f0; border-radius: 8px; border-left: 4px solid #667eea; }
        code { background: #e0e0e0; padding: 2px 6px; border-radius: 3px; }
        h2 { color: #333; }
        button { padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .success { border-left-color: #10b981; }
        .error { border-left-color: #ef4444; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Debug Admin Login Flow</h1>

        <div class="test">
            <h2>1. Current Session</h2>
            <p>Session ID: <code><?= session_id() ?></code></p>
            <p>Session Status: <?= session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'NOT ACTIVE' ?></p>
            <p>user_id: <?= $_SESSION['user_id'] ?? 'NOT SET' ?></p>
            <p>admin_id: <?= $_SESSION['admin_id'] ?? 'NOT SET' ?></p>
        </div>

        <div class="test">
            <h2>2. Test Admin Credentials</h2>
            <?php
            try {
                $stmt = $pdo->prepare("SELECT id, email, password, emri FROM admins WHERE email = ? AND status = 'active' LIMIT 1");
                $stmt->execute(['admin@noteria.al']);
                $admin = $stmt->fetch();
                
                if ($admin) {
                    echo "<p>Admin found: <strong>" . htmlspecialchars($admin['emri']) . "</strong></p>";
                    echo "<p>Email: <code>" . htmlspecialchars($admin['email']) . "</code></p>";
                    echo "<p>Password verify: " . (password_verify('Admin@2025', $admin['password']) ? '<span style="color:green">✓ CORRECT</span>' : '<span style="color:red">✗ WRONG</span>') . "</p>";
                } else {
                    echo "<p style='color:red;'>Admin not found!</p>";
                }
            } catch (Exception $e) {
                echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
            ?>
        </div>

        <div class="test">
            <h2>3. Simulate Admin Login</h2>
            <form method="POST">
                <button type="submit" name="action" value="simulate_login">Simulate Admin Login (admin@noteria.al)</button>
            </form>

            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'simulate_login') {
                try {
                    $stmt = $pdo->prepare("SELECT id, email, password, emri FROM admins WHERE email = ? AND status = 'active' LIMIT 1");
                    $stmt->execute(['admin@noteria.al']);
                    $admin = $stmt->fetch();
                    
                    if ($admin && password_verify('Admin@2025', $admin['password'])) {
                        $adminId = $admin['id'];
                        
                        // Set admin session
                        $_SESSION["admin_id"] = $adminId;
                        $_SESSION["admin_email"] = 'admin@noteria.al';
                        $_SESSION["admin_name"] = $admin['emri'] ?? "Administrator";
                        $_SESSION["is_developer"] = isDeveloper($adminId, 'admin@noteria.al');
                        $_SESSION['last_activity'] = time();
                        
                        // Set user session for dashboard
                        $_SESSION["user_id"] = $adminId;
                        $_SESSION["emri"] = $admin['emri'] ?? "Administrator";
                        $_SESSION["mbiemri"] = "Developer";
                        $_SESSION["roli"] = "admin";
                        
                        echo '<div class="test success">';
                        echo '<h3>Login Successful!</h3>';
                        echo '<p>admin_id: ' . $_SESSION["admin_id"] . '</p>';
                        echo '<p>user_id: ' . $_SESSION["user_id"] . '</p>';
                        echo '<p>emri: ' . htmlspecialchars($_SESSION["emri"]) . '</p>';
                        echo '<p>roli: ' . htmlspecialchars($_SESSION["roli"]) . '</p>';
                        echo '<p>last_activity: ' . $_SESSION['last_activity'] . '</p>';
                        echo '<p><a href="dashboard.php" style="display:inline-block; padding:10px 20px; background:#667eea; color:white; text-decoration:none; border-radius:4px;">Go to Dashboard →</a></p>';
                        echo '</div>';
                    } else {
                        echo '<div class="test error"><h3>Login Failed!</h3><p>Credentials do not match</p></div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="test error"><h3>Error</h3><p>' . htmlspecialchars($e->getMessage()) . '</p></div>';
                }
            }
            ?>
        </div>

        <div class="test">
            <h2>4. Check Dashboard Accessibility</h2>
            <form method="POST">
                <button type="submit" name="action" value="check_dashboard">Check Dashboard (After Login Above)</button>
            </form>

            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_dashboard') {
                echo '<p>Session user_id: ' . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET') . '</p>';
                echo '<p>Session admin_id: ' . (isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 'NOT SET') . '</p>';
                
                if (isset($_SESSION['user_id'])) {
                    echo '<p style="color:green;">✓ Session ready for dashboard</p>';
                } else {
                    echo '<p style="color:red;">✗ user_id not in session - dashboard will redirect to login.php</p>';
                }
            }
            ?>
        </div>
    </div>
</body>
</html>
