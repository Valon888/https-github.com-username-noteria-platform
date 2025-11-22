<?php
/**
 * Test Dashboard Redirect
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
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Dashboard Redirect</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .test { margin: 15px 0; padding: 15px; background: #f0f0f0; border-radius: 8px; }
        code { background: #e0e0e0; padding: 2px 6px; }
    </style>
</head>
<body>
    <h1>Dashboard Redirect Test</h1>

    <div class="test">
        <h2>Session Status</h2>
        <p>Session ID: <code><?= session_id() ?></code></p>
        <p>Session user_id: <?= isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET' ?></p>
        <p>Session admin_id: <?= isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 'NOT SET' ?></p>
        <p>Session emri: <?= isset($_SESSION['emri']) ? $_SESSION['emri'] : 'NOT SET' ?></p>
    </div>

    <div class="test">
        <h2>Simulate Admin Login</h2>
        <form method="POST">
            <button type="submit" name="action" value="login">Set Admin Session</button>
        </form>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login'): ?>
            <?php
            $_SESSION["admin_id"] = 1;
            $_SESSION["admin_email"] = 'admin@noteria.al';
            $_SESSION["admin_name"] = 'Admin Noteria';
            $_SESSION["is_developer"] = isDeveloper(1, 'admin@noteria.al');
            
            // Set user session
            $_SESSION["user_id"] = 1;
            $_SESSION["emri"] = 'Admin';
            $_SESSION["mbiemri"] = 'Developer';
            $_SESSION["roli"] = 'admin';
            ?>
            <p style="color: green;"><strong>Admin session set!</strong></p>
            <p>admin_id: <?= $_SESSION["admin_id"] ?></p>
            <p>user_id: <?= $_SESSION["user_id"] ?></p>
            <p>emri: <?= $_SESSION["emri"] ?></p>
            <p style="margin-top: 10px;"><a href="dashboard.php" style="background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;">Go to Dashboard</a></p>
        <?php endif; ?>
    </div>

</body>
</html>
