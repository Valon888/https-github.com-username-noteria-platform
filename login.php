<?php
// Konfigurimi i raportimit të gabimeve - PARA require_once
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Fillimi i sigurt i sesionit - PARA require_once
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
ini_set('session.use_strict_mode', 1);

// Kontrollo nëse ekziston file-i i konfigurimit të databazës
if (!file_exists('confidb.php')) {
    die("Gabim: File-i 'confidb.php' nuk ekziston. Ju lutem krijoni këtë file me konfigurimet e databazës.");
}

// Fillo sesionin PARA se të require-jë confidb.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'confidb.php';

// Regjenero ID pas kyçjes
if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit();
}

$error = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = filter_var(trim($_POST["email"] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST["password"] ?? '');

    // Validimi i email-it
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email-i nuk është i vlefshëm.";
    } elseif (strlen($password) < 6) {
        $error = "Fjalëkalimi duhet të ketë të paktën 6 karaktere.";
    }

    if (!$error) {
        // Merr përdoruesin me rolin
        $stmt = $pdo->prepare("SELECT id, emri, mbiemri, email, password, roli FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user["password"])) {
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["emri"] = htmlspecialchars($user["emri"]);
            $_SESSION["mbiemri"] = htmlspecialchars($user["mbiemri"]);
            $_SESSION["email"] = htmlspecialchars($user["email"]);
            $_SESSION["roli"] = htmlspecialchars($user["roli"] ?? "user"); // Rol i kyçur (default: user)
            $_SESSION['last_activity'] = time(); // Track activity for timeout
            unset($_SESSION['captcha_text']);
            
            // Log activity
            log_activity($pdo, $_SESSION['user_id'], 'Kyçje', 'Kyçje e suksesshme - Roli: ' . $_SESSION["roli"]);
            
            // Redirect bazuar në rol
            error_log("DEBUG: Roli = " . $_SESSION["roli"]);
            if ($_SESSION["roli"] === "admin") {
                error_log("DEBUG: Redirejtim në admin_dashboard.php");
                header("Location: admin_dashboard.php");
                exit();
            } elseif ($_SESSION["roli"] === "notary") {
                error_log("DEBUG: Redirejtim në dashboard.php");
                header("Location: dashboard.php");
                exit();
            } else {
                // user - sheh vetëm shërbimet dhe pagesa
                error_log("DEBUG: Redirejtim në billing_dashboard.php");
                header("Location: billing_dashboard.php");
                exit();
            }
        } else {
            $error = "Email ose fjalëkalim i pasaktë!";
        }
    }
}

function log_activity($pdo, $user_id, $action, $details = '') {
    $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, details) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $action, $details]);
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kyçuni | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== RESET ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            width: 100%;
        }

        /* ===== BODY & BACKGROUND ===== */
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow-x: hidden;
            padding: 15px;
        }

        body::before {
            content: '';
            position: fixed;
            top: -50%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: float 20s infinite ease-in-out;
            pointer-events: none;
        }

        body::after {
            content: '';
            position: fixed;
            bottom: -10%;
            left: -5%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: float 25s infinite ease-in-out reverse;
            pointer-events: none;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) translateX(0px); }
            25% { transform: translateY(-20px) translateX(10px); }
            50% { transform: translateY(-40px) translateX(-10px); }
            75% { transform: translateY(-20px) translateX(10px); }
        }

        /* ===== LOGIN WRAPPER ===== */
        .login-wrapper {
            width: 100%;
            max-width: 400px;
            position: relative;
            z-index: 1;
            padding: 0;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 40px 30px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: slideIn 0.6s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ===== LOGO ===== */
        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .logo-icon i {
            color: white;
            font-size: 28px;
        }

        h2 {
            color: #2d3748;
            font-size: 26px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .subtitle {
            text-align: center;
            color: #718096;
            font-size: 13px;
            margin-bottom: 28px;
            line-height: 1.5;
        }

        /* ===== FORMS ===== */
        .form-group {
            margin-bottom: 20px;
            animation: fadeInUp 0.5s ease-out backwards;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }
        .form-group:nth-child(4) { animation-delay: 0.4s; }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #2d3748;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input[type="email"], 
        input[type="password"], 
        input[type="text"] {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: #f7fafc;
            transition: all 0.3s ease;
            font-family: inherit;
            color: #2d3748;
        }

        input[type="email"]:focus, 
        input[type="password"]:focus, 
        input[type="text"]:focus {
            border-color: #667eea;
            background: #ffffff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        input[type="email"]::placeholder, 
        input[type="password"]::placeholder {
            color: #a0aec0;
        }

        /* ===== BUTTON ===== */
        button[type="submit"] {
            width: 100%;
            padding: 12px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        button[type="submit"]:active {
            transform: translateY(0);
        }

        /* ===== ALERTS ===== */
        .alert {
            padding: 12px 14px;
            border-radius: 8px;
            margin-top: 16px;
            font-size: 13px;
            line-height: 1.5;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .alert-danger {
            background: #fed7d7;
            color: #c53030;
            border-left: 4px solid #c53030;
        }

        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border-left: 4px solid #22543d;
        }

        /* ===== LINKS ===== */
        .forgot-password {
            text-align: right;
            margin-top: -10px;
            margin-bottom: 16px;
        }

        .forgot-password a {
            color: #667eea;
            font-size: 12px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .forgot-password a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        /* ===== RESPONSIVE: MOBILE (< 480px) ===== */
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }

            body::before, body::after {
                display: none;
            }

            .login-wrapper {
                max-width: 100%;
            }

            .login-container {
                padding: 28px 20px;
                border-radius: 12px;
            }

            h2 {
                font-size: 22px;
            }

            .subtitle {
                font-size: 12px;
                margin-bottom: 20px;
            }

            .logo-icon {
                width: 50px;
                height: 50px;
            }

            .logo-icon i {
                font-size: 24px;
            }

            input[type="email"], 
            input[type="password"], 
            input[type="text"] {
                padding: 11px 12px;
                font-size: 16px;
            }

            button[type="submit"] {
                padding: 11px 16px;
                font-size: 14px;
            }
        }

        /* ===== RESPONSIVE: TABLET (481px - 768px) ===== */
        @media (min-width: 481px) and (max-width: 768px) {
            .login-wrapper {
                max-width: 380px;
            }

            .login-container {
                padding: 36px 28px;
            }

            h2 {
                font-size: 24px;
            }
        }

        /* ===== RESPONSIVE: DESKTOP (769px - 1024px) ===== */
        @media (min-width: 769px) and (max-width: 1024px) {
            .login-wrapper {
                max-width: 420px;
            }

            .login-container {
                padding: 45px 35px;
            }

            h2 {
                font-size: 28px;
            }
        }

        /* ===== RESPONSIVE: LARGE DESKTOP (1025px+) ===== */
        @media (min-width: 1025px) {
            .login-wrapper {
                max-width: 450px;
            }

            .login-container {
                padding: 50px 40px;
            }

            h2 {
                font-size: 30px;
            }
        }

        /* ===== LANDSCAPE MODE ===== */
        @media (max-height: 600px) and (orientation: landscape) {
            .login-container {
                padding: 25px 30px;
            }

            h2 {
                font-size: 20px;
                margin-bottom: 5px;
            }

            .subtitle {
                margin-bottom: 15px;
            }

            .form-group {
                margin-bottom: 15px;
            }
        }

        /* ===== ACCESSIBILITY ===== */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation: none !important;
                transition: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <div class="logo-container">
                <div class="logo-icon">
                    <i class="fas fa-certificate"></i>
                </div>
                <h2>Noteria</h2>
                <p class="subtitle">Sistemi i Menaxhimit të Noterive</p>
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email:</label>
                    <input type="email" id="email" name="email" placeholder="emri@shembull.com" required autocomplete="username">
                </div>

                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Fjalëkalimi:</label>
                    <input type="password" id="password" name="password" placeholder="Futni fjalëkalimin tuaj" required autocomplete="current-password">
                </div>

                <div class="button-group">
                    <button type="submit">
                        <i class="fas fa-sign-in-alt"></i> Kyçu
                    </button>
                    <button type="button" onclick="window.location.href='forgot_password.php'">
                        <i class="fas fa-redo"></i> Rivendos
                    </button>
                </div>
            </form>

            <?php if ($error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php
            if (isset($_SESSION['success'])) {
                echo '<div class="success">
                    <i class="fas fa-check-circle"></i>
                    '.htmlspecialchars($_SESSION['success']).'
                </div>';
                unset($_SESSION['success']);
            }
            ?>

            <div class="register-link">
                <p>Nuk keni llogari? <a href="register.php">Regjistrohuni këtu</a></p>
                <p>Jeni Zyrë Noteriale? <a href="zyrat_register.php">Regjistrohuni si Noter</a></p>
            </div>

            <div class="security-badge">
                <i class="fas fa-lock"></i>
                <span>Koneksion i Sigurt me Enkriptim SSL</span>
            </div>
        </div>
    </div>
</body>
</html>

