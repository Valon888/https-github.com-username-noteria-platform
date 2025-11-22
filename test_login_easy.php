<?php
// File: test_login_easy.php
// A simple login page for testing admin settings without redirect loops

session_start();
// Destroy any existing session to prevent redirect loops
session_unset();
session_destroy();

// Start a new clean session
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simplified login for testing admin settings
    if ($_POST['password'] === 'admin123') {
        $_SESSION['admin_id'] = 1;
        $_SESSION['emri'] = 'Admin';
        $_SESSION['mbiemri'] = 'Test';
        $_SESSION['email'] = 'admin@noteria.al';
        $_SESSION['roli'] = 'admin';
        $_SESSION['auth_test'] = true; // Flag special për të evituar redirect loop
        
        // Redirect to admin settings view
        header('Location: admin_settings_view.php');
        exit();
    } else {
        $error = 'Fjalëkalimi nuk është i saktë! Përdorni "admin123"';
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login i Thjeshtë Admin - Noteria</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header h1 {
            color: #3c6382;
            margin: 0;
            font-size: 1.75rem;
        }
        .login-header p {
            color: #777;
            margin-top: 0.5rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        input[type="text"]:focus, input[type="password"]:focus {
            border-color: #3c6382;
            outline: none;
        }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background-color: #3c6382;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            width: 100%;
            transition: background-color 0.2s;
        }
        .btn:hover {
            background-color: #2c3e50;
        }
        .error-message {
            background-color: #ffe9e9;
            color: #d63031;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        .note {
            margin-top: 1.5rem;
            text-align: center;
            color: #777;
            font-size: 0.9rem;
        }
        .note code {
            background-color: #f5f7fa;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Noteria Admin</h1>
            <p>Login i thjeshtë për akses në cilësime</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="text" id="email" name="email" value="admin@noteria.al" readonly>
            </div>
            
            <div class="form-group">
                <label for="password">Fjalëkalimi</label>
                <input type="password" id="password" name="password" placeholder="Vendosni fjalëkalimin" autofocus>
            </div>
            
            <button type="submit" class="btn">Hyrje</button>
            
            <div class="note">
                Përdorni fjalëkalimin: <code>admin123</code>
            </div>
        </form>
    </div>
</body>
</html>