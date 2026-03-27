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
require_once 'activity_logger.php';
require_once 'mfa_helper.php';

// Regjenero ID pas kyçjes
if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Redirect logic
$redirect_url = isset($_GET['redirect']) ? $_GET['redirect'] : '';
if (isset($_POST['redirect'])) {
    $redirect_url = $_POST['redirect'];
}

if (isset($_SESSION["user_id"])) {
    if (isset($_SESSION["roli"]) && $_SESSION["roli"] === 'admin') {
        $loc = !empty($redirect_url) ? $redirect_url : "admin_dashboard.php";
        header("Location: " . $loc);
        exit();
    } else {
        // If they are a non-admin user trying to access an admin page (redirect_url is set),
        // we don't redirect them to dashboard.php automatically.
        // This allows them to see the login form and switch to an admin account.
        if (empty($redirect_url)) {
            header("Location: dashboard.php");
            exit();
        }
    }
}

$error = null;
$login_type = isset($_GET['type']) ? $_GET['type'] : 'user'; // 'user' ose 'admin'

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Validate CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
        $error = "Veprimi i paautorizuar (CSRF)!";
    } else {
        // Verify Cloudflare Turnstile
        $turnstile_token = $_POST['cf-turnstile-response'] ?? '';
        if (empty($turnstile_token)) {
            $error = "Ju lutem verifikoni se jeni njeri.";
        } elseif ($turnstile_token === 'test') {
            // Test token, skip verification
        } else {
            // In production, verify with Cloudflare API
            // Example: curl to https://challenges.cloudflare.com/turnstile/v0/siteverify
        }

        $email = filter_var(trim($_POST["email"] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = trim($_POST["password"] ?? '');
        $login_type = trim($_POST["login_type"] ?? 'user');

        // Validimi i email-it
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Email-i nuk është i vlefshëm.";
        } elseif (strlen($password) < 6) {
            $error = "Fjalëkalimi duhet të ketë të paktën 6 karaktere.";
        }

        if (!$error) {
            if ($login_type === 'admin') {
                // Kyçje si Admin
                $stmt = $pdo->prepare("SELECT id, email, password, emri, status FROM admins WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $admin = $stmt->fetch();

                if ($admin && $admin['status'] === 'active' && password_verify($password, $admin["password"])) {
                    // Kontrollo nëse admin ka MFA të aktivizuar
                    if (adminHasMFA($pdo, $admin["id"])) {
                        // Ruaj të dhënat temporare dhe kërko MFA
                        $_SESSION['mfa_pending'] = true;
                        $_SESSION['temp_user_data'] = [
                            'user_type' => 'admin',
                            'user_id' => $admin["id"],
                            'admin_id' => $admin["id"],
                            'emri' => htmlspecialchars($admin["emri"] ?? "Administrator"),
                            'email' => htmlspecialchars($admin["email"]),
                            'roli' => "admin",
                            'redirect' => $redirect_url
                        ];
                        header("Location: mfa_verify.php");
                        exit();
                    } else {
                        // Kyçje normale pa MFA
                        $_SESSION["user_id"] = $admin["id"];
                        $_SESSION["admin_id"] = $admin["id"];
                        $_SESSION["emri"] = htmlspecialchars($admin["emri"] ?? "Administrator");
                        $_SESSION["email"] = htmlspecialchars($admin["email"]);
                        $_SESSION["roli"] = "admin";
                        $_SESSION['last_activity'] = time();
                        unset($_SESSION['captcha_text']);

                        // Log activity
                        log_activity($pdo, $_SESSION['user_id'], 'Admin Kyçje', 'Admin kyçje e suksesshme');

                        $target = !empty($redirect_url) ? $redirect_url : "admin_dashboard.php";
                        header("Location: " . $target);
                        exit();
                    }
                } else {
                    $error = "Email ose fjalëkalim i pasaktë për admin!";
                }
            } else {
                // Kyçje si User
                $stmt = $pdo->prepare("SELECT id, emri, mbiemri, email, password, roli FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user["password"])) {
                    // Kontrollo nëse user ka MFA të aktivizuar
                    if (userHasMFA($pdo, $user["id"])) {
                        // Ruaj të dhënat temporare dhe kërko MFA
                        $_SESSION['mfa_pending'] = true;
                        $_SESSION['temp_user_data'] = [
                            'user_type' => 'user',
                            'user_id' => $user["id"],
                            'emri' => htmlspecialchars($user["emri"]),
                            'mbiemri' => htmlspecialchars($user["mbiemri"]),
                            'email' => htmlspecialchars($user["email"]),
                            'roli' => htmlspecialchars($user["roli"] ?? "user"),
                            'redirect' => $redirect_url
                        ];
                        header("Location: mfa_verify.php");
                        exit();
                    } else {
                        // Kyçje normale pa MFA
                        $_SESSION["user_id"] = $user["id"];
                        $_SESSION["emri"] = htmlspecialchars($user["emri"]);
                        $_SESSION["mbiemri"] = htmlspecialchars($user["mbiemri"]);
                        $_SESSION["email"] = htmlspecialchars($user["email"]);
                        $_SESSION["roli"] = htmlspecialchars($user["roli"] ?? "user");
                        $_SESSION['last_activity'] = time();
                        unset($_SESSION['captcha_text']);

                        // Log activity
                        log_activity($pdo, $_SESSION['user_id'], 'Kyçje', 'Kyçje e suksesshme - Roli: ' . $_SESSION["roli"]);

                        // Redirect bazuar në rol
                        error_log("DEBUG: Roli = " . $_SESSION["roli"]);
                        
                        if (!empty($redirect_url)) {
                            header("Location: " . $redirect_url);
                            exit();
                        }

                        if ($_SESSION["roli"] === "admin") {
                            error_log("DEBUG: Redirejtim në admin_dashboard.php");
                            header("Location: admin_dashboard.php");
                            exit();
                        } elseif ($_SESSION["roli"] === "notary") {
                            error_log("DEBUG: Redirejtim në dashboard.php");
                            header("Location: dashboard.php");
                            exit();
                        } else {
                            error_log("DEBUG: Redirejtim në billing_dashboard.php");
                            header("Location: billing_dashboard.php");
                            exit();
                        }
                    }
                } else {
                    $error = "Email ose fjalëkalim i pasaktë!";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="description" content="Kyçuni në platformën e-Noteria - Sistemi i sigurt SaaS për zyrat noteriale në Kosovë">
    <meta name="keywords" content="noteria, kosovo, login, platforma, SaaS, sigurim">
    <meta name="author" content="e-Noteria Platform">
    <title>Kyçuni | e-Noteria - Platforma SaaS për Zyrat Noteriale</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="apple-touch-icon" href="images/Emblem_of_the_Republic_of_Kosovo.svg.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
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
            background: linear-gradient(135deg, #244d96 0%, #1e3c72 25%, #2a5298 50%, #cfa856 75%, #b8860b 100%);
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow-x: hidden;
            padding: 12px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Animated gradient background with Kosovo flag colors */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                radial-gradient(circle at 20% 30%, rgba(196, 168, 86, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(36, 77, 150, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(42, 82, 152, 0.1) 0%, transparent 50%);
            pointer-events: none;
            animation: kosovoWaves 20s ease-in-out infinite;
            z-index: 0;
        }

        @keyframes kosovoWaves {
            0%, 100% {
                transform: scale(1) rotate(0deg);
                opacity: 0.8;
            }
            33% {
                transform: scale(1.05) rotate(1deg);
                opacity: 0.9;
            }
            66% {
                transform: scale(0.98) rotate(-1deg);
                opacity: 0.7;
            }
        }

        /* Floating geometric shapes */
        body::after {
            content: '';
            position: fixed;
            top: 10%;
            right: 8%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(196, 168, 86, 0.08) 1px, transparent 1px);
            background-size: 40px 40px;
            animation: geometricFloat 30s infinite ease-in-out;
            pointer-events: none;
            z-index: 0;
            border-radius: 50%;
            opacity: 0.6;
        }

        @keyframes geometricFloat {
            0%, 100% { transform: translateY(0px) translateX(0px) rotate(0deg); }
            25% { transform: translateY(-40px) translateX(20px) rotate(90deg); }
            50% { transform: translateY(-80px) translateX(-20px) rotate(180deg); }
            75% { transform: translateY(-40px) translateX(20px) rotate(270deg); }
        }

        /* ===== LOGIN WRAPPER ===== */
        .login-wrapper {
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
            padding: 0;
            margin: 0 auto;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border-radius: 24px;
            padding: 40px 32px;
            box-shadow:
                0 32px 80px rgba(0, 0, 0, 0.15),
                0 0 1px rgba(255, 255, 255, 0.8) inset,
                0 0 40px rgba(196, 168, 86, 0.1);
            border: 1px solid rgba(196, 168, 86, 0.2);
            animation: elegantSlideIn 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
            min-height: auto;
            position: relative;
            overflow: hidden;
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #244d96 0%, #cfa856 50%, #b8860b 100%);
            border-radius: 24px 24px 0 0;
        }

        @keyframes elegantSlideIn {
            0% {
                opacity: 0;
                transform: translateY(40px) scale(0.95);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* ===== LOGO & HEADER ===== */
        .logo-container {
            text-align: center;
            margin-bottom: 32px;
            position: relative;
        }

        .logo-icon {
            width: 90px;
            height: auto;
            margin-bottom: 16px;
            filter: drop-shadow(0 8px 16px rgba(36, 77, 150, 0.3));
            animation: logoGlow 3s ease-in-out infinite alternate;
        }

        @keyframes logoGlow {
            0% { filter: drop-shadow(0 8px 16px rgba(36, 77, 150, 0.3)); }
            100% { filter: drop-shadow(0 8px 20px rgba(196, 168, 86, 0.4)); }
        }

        .logo-container h2 {
            font-size: 28px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #244d96 0%, #2a5298 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .subtitle {
            font-size: 16px;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 0;
            letter-spacing: 0.2px;
        }

        /* ===== FORM ELEMENTS ===== */
        .form-group {
            margin-bottom: 24px;
            position: relative;
        }

        label {
            display: block;
            font-weight: 600;
            font-size: 14px;
            color: #374151;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        label i {
            color: #244d96;
            font-size: 16px;
        }

        /* Modern floating label inputs */
        .input-wrapper {
            position: relative;
        }

        input[type="email"],
        input[type="password"],
        input[type="text"],
        select {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            font-size: 16px;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: inherit;
            color: #1e293b;
            -webkit-appearance: none;
            appearance: none;
            position: relative;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="text"]:focus,
        select:focus {
            border-color: #244d96;
            background: linear-gradient(135deg, #ffffff 0%, #f0f4ff 100%);
            outline: none;
            box-shadow:
                0 0 0 4px rgba(36, 77, 150, 0.1),
                0 8px 24px rgba(36, 77, 150, 0.15);
            transform: translateY(-2px);
        }

        input[type="email"]::placeholder,
        input[type="password"]::placeholder {
            color: #9ca3af;
            opacity: 0.7;
        }

        /* Enhanced select styling */
        select {
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23244d96' stroke-width='2'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 20px;
            padding-right: 50px;
            cursor: pointer;
        }

        /* ===== BUTTONS ===== */
        .button-group {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 16px;
            margin-top: 16px;
            position: relative;
            z-index: 1;
        }

        button[type="submit"],
        button[type="button"] {
            width: 100%;
            padding: 16px 24px;
            background: linear-gradient(135deg, #244d96 0%, #2a5298 50%, #cfa856 100%);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 56px;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(36, 77, 150, 0.3);
        }

        button[type="submit"]:hover,
        button[type="button"]:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(36, 77, 150, 0.4);
        }

        button[type="submit"]:active,
        button[type="button"]:active {
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(36, 77, 150, 0.3);
        }

        button[type="submit"]::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        button[type="submit"]:active::before {
            width: 300px;
            height: 300px;
        }

        /* ===== LINKS ===== */
        .register-link {
            text-align: center;
            margin: 24px 0;
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
            line-height: 1.6;
        }

        .register-link a {
            color: #244d96;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s ease;
            position: relative;
            display: inline-block;
            margin: 4px 0;
        }

        .register-link a::before {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 3px;
            background: linear-gradient(90deg, #244d96 0%, #cfa856 100%);
            transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 2px;
        }

        .register-link a:hover::before {
            width: 100%;
        }

        .register-link a:hover {
            color: #1e3c72;
            transform: translateY(-1px);
        }

        /* ===== SECURITY BADGE ===== */
        .security-badge {
            margin-top: 32px;
            padding: 16px 20px;
            background: linear-gradient(135deg, #f0f4ff 0%, #fef7ed 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            border: 2px solid #e0e7ff;
            position: relative;
            z-index: 1;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        }

        .security-badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(196, 168, 86, 0.2), transparent);
            animation: securityShimmer 4s infinite;
        }

        @keyframes securityShimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .security-badge i {
            color: #22c55e;
            font-size: 20px;
            animation: securePulse 3s ease-in-out infinite;
        }

        @keyframes securePulse {
            0%, 100% {
                transform: scale(1);
                color: #22c55e;
            }
            50% {
                transform: scale(1.1);
                color: #16a34a;
            }
        }

        .security-badge span {
            color: #1e293b;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        /* ===== ERROR & SUCCESS MESSAGES ===== */
        .error, .success {
            padding: 16px 20px;
            border-radius: 16px;
            margin-top: 20px;
            font-size: 14px;
            line-height: 1.6;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: messageSlideIn 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 1;
            border: 1px solid;
        }

        .error {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #dc2626;
            border-color: #fecaca;
        }

        .success {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: #16a34a;
            border-color: #bbf7d0;
        }

        @keyframes messageSlideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ===== RESPONSIVE DESIGN ===== */
        @media (max-width: 480px) {
            body {
                padding: 8px;
            }

            .login-container {
                padding: 24px 20px;
                border-radius: 20px;
                box-shadow: 0 16px 40px rgba(0,0,0,0.2);
                backdrop-filter: none;
                -webkit-backdrop-filter: none;
                border: 1px solid rgba(196, 168, 86, 0.15);
            }

            .login-container::before {
                height: 3px;
            }

            /* LOGO */
            .logo-icon {
                width: 70px;
                height: auto;
                margin-bottom: 12px;
                animation: none;
            }

            .logo-container h2 {
                font-size: 24px;
                margin-bottom: 6px;
            }

            .subtitle {
                font-size: 14px;
                margin-bottom: 20px;
            }

            /* INPUTS */
            input,
            select {
                font-size: 16px !important; /* Prevent iOS zoom */
                padding: 14px 16px;
                border-radius: 12px;
            }

            label {
                font-size: 13px;
                margin-bottom: 6px;
            }

            .form-group {
                margin-bottom: 20px;
            }

            /* BUTTONS */
            .button-group {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            button {
                min-height: 50px;
                font-size: 14px;
                padding: 14px;
                border-radius: 12px;
            }

            /* TURNSTILE */
            .cf-turnstile {
                transform: scale(0.9);
                transform-origin: center;
            }

            /* SECURITY BADGE */
            .security-badge {
                padding: 12px 16px;
                font-size: 12px;
            }

            .security-badge i {
                font-size: 16px;
            }
        }

        /* TABLET & DESKTOP */
        @media (min-width: 481px) {
            .login-wrapper { max-width: 400px; }
            .login-container { padding: 36px 32px; }
        }

        /* ===== ACCESSIBILITY ===== */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation: none !important;
                transition: none !important;
            }
        }

        /* Focus states for accessibility */
        button:focus-visible,
        input:focus-visible,
        select:focus-visible {
            outline: 2px solid #244d96;
            outline-offset: 2px;
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .login-container {
                background: #ffffff;
                border: 2px solid #000000;
            }

            input, select {
                border: 2px solid #000000;
            }
        }

        /* ===== LOADING OVERLAY ===== */
        #loadingOverlay {
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* ===== ENHANCED BUTTON STYLES ===== */
        .reset-btn {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%) !important;
            box-shadow: 0 8px 24px rgba(107, 114, 128, 0.3) !important;
        }

        .reset-btn:hover {
            background: linear-gradient(135deg, #4b5563 0%, #374151 100%) !important;
            box-shadow: 0 12px 32px rgba(107, 114, 128, 0.4) !important;
        }

        /* ===== FORM VALIDATION STYLES ===== */
        input:invalid:not(:placeholder-shown) {
            border-color: #ef4444;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
        }

        input:valid:not(:placeholder-shown) {
            border-color: #22c55e;
            box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.1);
        }
    </style>
            overflow: hidden;
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            animation: float 20s infinite ease-in-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(40px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* ===== LOGO ===== */
        .logo-container {
            text-align: center;
            margin-bottom: 24px;
            position: relative;
            z-index: 1;
        }

        .logo-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 15px;
            animation: bounce 2.5s ease-in-out infinite;
            filter: drop-shadow(0 10px 20px rgba(102, 126, 234, 0.3));
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-15px) scale(1.05); }
        }

        h2 {
            color: #1e3c72;
            font-size: 26px;
            font-weight: 800;
            text-align: center;
            margin-bottom: 5px;
            letter-spacing: -1px;
            background: linear-gradient(135deg, #1e3c72 0%, #7e22ce 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .subtitle {
            text-align: center;
            color: #64748b;
            font-size: 13px;
            margin-bottom: 24px;
            line-height: 1.6;
            font-weight: 500;
        }

        /* ===== FORMS ===== */
        .form-group {
            margin-bottom: 18px;
            animation: fadeInUp 0.6s ease-out backwards;
            position: relative;
            z-index: 1;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }
        .form-group:nth-child(4) { animation-delay: 0.4s; }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(25px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        label {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            color: #1e293b;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            gap: 8px;
        }

        label i {
            color: #667eea;
            font-size: 15px;
        }

        input[type="email"], 
        input[type="password"], 
        input[type="text"],
        select {
            width: 100%;
            padding: clamp(13px, 2.5vw, 16px) clamp(14px, 3.5vw, 16px);
            border: 2px solid #e0e7ff;
            border-radius: 12px;
            font-size: 16px;
            background: linear-gradient(135deg, #f8faff 0%, #f0f4ff 100%);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: inherit;
            color: #1e293b;
            -webkit-appearance: none;
            appearance: none;
            position: relative;
        }

        input[type="email"]:focus, 
        input[type="password"]:focus, 
        input[type="text"]:focus,
        select:focus {
            border-color: #667eea;
            background: linear-gradient(135deg, #ffffff 0%, #f0f4ff 100%);
            outline: none;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
            transform: translateY(-2px);
        }

        input[type="email"]::placeholder, 
        input[type="password"]::placeholder {
            color: #94a3b8;
        }

        /* Select box styling */
        select {
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23667eea' stroke-width='2'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 20px;
            padding-right: 40px;
        }

        /* ===== BUTTON ===== */
        .button-group {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 12px;
            margin-top: 12px;
            position: relative;
            z-index: 1;
        }

        button[type="submit"],
        button[type="button"] {
            width: 100%;
            padding: clamp(13px, 2.5vw, 16px) clamp(16px, 4vw, 24px);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: clamp(13px, 3.5vw, 15px);
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 50px;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            position: relative;
            overflow: hidden;
        }

        button[type="submit"]::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        button[type="submit"]:active::before,
        button[type="button"]:active::before {
            width: 300px;
            height: 300px;
        }

        button[type="submit"]:hover,
        button[type="button"]:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.5);
        }

        button[type="submit"]:active,
        button[type="button"]:active {
            transform: translateY(-1px);
        }

        button[type="submit"] i,
        button[type="button"] i {
            transition: transform 0.3s ease;
        }

        button[type="submit"]:hover i,
        button[type="button"]:hover i {
            transform: scale(1.1);
        }

        /* ===== ALERTS ===== */
        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-top: 18px;
            font-size: 14px;
            line-height: 1.6;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: slideDown 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 1;
        }

        .alert-danger {
            background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%);
            color: #991b1b;
            border-left: 5px solid #dc2626;
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.2);
        }

        .alert-success {
            background: linear-gradient(135deg, #bbf7d0 0%, #86efac 100%);
            color: #15803d;
            border-left: 5px solid #22c55e;
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.2);
        }

        /* ===== LINKS ===== */
        .forgot-password {
            text-align: right;
            margin-top: -12px;
            margin-bottom: 18px;
            position: relative;
            z-index: 1;
        }

        .forgot-password a {
            color: #667eea;
            font-size: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .forgot-password a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }

        .forgot-password a:hover::after {
            width: 100%;
        }

        /* ===== REGISTER LINKS ===== */
        .register-link {
            margin-top: 28px;
            padding-top: 24px;
            border-top: 2px solid #f0f4ff;
            position: relative;
            z-index: 1;
        }

        .register-link p {
            text-align: center;
            margin: 12px 0;
            font-size: 14px;
            color: #475569;
            font-weight: 500;
        }

        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s ease;
            position: relative;
            display: inline-block;
        }

        .register-link a::before {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 2px;
        }

        .register-link a:hover::before {
            width: 100%;
        }

        /* ===== SECURITY BADGE ===== */
        .security-badge {
            margin-top: 24px;
            padding: 14px 18px;
            background: linear-gradient(135deg, #f0f4ff 0%, #f5f3ff 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            border: 2px solid #e0e7ff;
            position: relative;
            z-index: 1;
            overflow: hidden;
        }

        .security-badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .security-badge i {
            color: #22c55e;
            font-size: 18px;
            animation: pulse-lock 2s ease-in-out infinite;
        }

        @keyframes pulse-lock {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.1) rotate(-5deg); }
        }

        .security-badge span {
            color: #1e293b;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        /* ===== ERROR & SUCCESS MESSAGES ===== */
        .error, .success {
            padding: 14px 16px;
            border-radius: 12px;
            margin-top: 18px;
            font-size: 14px;
            line-height: 1.6;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 1;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .error {
            background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%);
            color: #991b1b;
            border-left: 5px solid #dc2626;
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.2);
        }

        .error i {
            font-size: 18px;
            flex-shrink: 0;
        }

        .success {
            background: linear-gradient(135deg, #bbf7d0 0%, #86efac 100%);
            color: #15803d;
            border-left: 5px solid #22c55e;
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.2);
        }

        .success i {
            font-size: 18px;
            flex-shrink: 0;
        }

        /* =========================
           ULTRA MOBILE OPTIMIZATION
           ========================= */

        /* MOBILE FIRST – ALL PHONES */
        @media (max-width: 480px) {

            body {
                padding: 6px;
                align-items: flex-start;
            }

            .login-wrapper {
                width: 100%;
                max-width: 100%;
                margin-top: 12px;
            }

            .login-container {
                padding: 15px 12px;
                border-radius: 10px;
                box-shadow: 0 8px 25px rgba(0,0,0,0.18);
                backdrop-filter: none;
                -webkit-backdrop-filter: none;
                border: 1px solid rgba(0,0,0,0.05);
            }

            /* LOGO */
            .logo-icon {
                width: 40px;
                height: auto;
                margin-bottom: 8px;
                animation: none;
            }

            h2 {
                font-size: 20px;
                margin-bottom: 2px;
            }

            .subtitle {
                font-size: 11px;
                margin-bottom: 15px;
            }

            /* INPUTS – prevent iOS zoom */
            input,
            select {
                font-size: 16px !important;
                padding: 10px 12px;
                border-radius: 8px;
            }

            label {
                font-size: 11px;
                margin-bottom: 5px;
            }

            /* BUTTONS – stack vertical */
            .button-group {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            button {
                min-height: 42px;
                font-size: 13px;
                padding: 10px;
                border-radius: 8px;
            }

            /* TURNSTILE */
            .cf-turnstile {
                transform: scale(0.95);
                transform-origin: center;
            }

            /* REMOVE HEAVY EFFECTS */
            body::before,
            body::after,
            .login-container::before {
                display: none !important;
            }

            /* SECURITY BADGE */
            .security-badge {
                padding: 10px;
                font-size: 11px;
            }

            .security-badge i {
                font-size: 14px;
            }
        }

        /* LANDSCAPE PHONES */
        @media (max-height: 480px) and (orientation: landscape) {
            body {
                align-items: flex-start;
            }

            .login-container {
                padding: 12px 14px;
            }

            h2 {
                font-size: 18px;
            }

            .form-group {
                margin-bottom: 10px;
            }
        }

        /* TABLET & DESKTOP */
        @media (min-width: 481px) {
            .login-wrapper { max-width: 380px; }
            .login-container { padding: 25px 30px; }
        }

        /* HIDE ALL OFFERS/ADS */
        div[class*="ads"], div[class*="offer"], .ads-sidebar, .offers-widget, aside {
            display: none !important;
        }

        /* LOW PERFORMANCE DEVICES */
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
            <!-- Welcome Message -->
            <div style="text-align: center; margin-bottom: 8px;">
                <p style="color: #64748b; font-size: 14px; margin: 0;">
                    <i class="fas fa-handshake" style="color: #244d96; margin-right: 6px;"></i>
                    Mirë se vini në platformën e-Noteria
                </p>
            </div>

            <!-- Header Section -->
            <div class="logo-container">
                <img src="images/Emblem_of_the_Republic_of_Kosovo.svg.png" alt="Kosovo Emblem" class="logo-icon">
                <h2>e-Noteria</h2>
                <p class="subtitle">Platforma SaaS për Zyrat Noteriale</p>
            </div>

            <!-- Login Form -->
            <form method="POST" action="" novalidate>
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_url); ?>">

                <!-- Email Field -->
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i>
                        Email Adresa
                    </label>
                    <div class="input-wrapper">
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="emri@shembull.com"
                            required
                            autocomplete="username"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        >
                    </div>
                </div>

                <!-- Password Field -->
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        Fjalëkalimi
                    </label>
                    <div class="input-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="••••••••"
                            required
                            autocomplete="current-password"
                        >
                    </div>
                </div>

                <!-- Login Type Selection -->
                <div class="form-group">
                    <label for="login_type">
                        <i class="fas fa-user-shield"></i>
                        Lloji i Hyrjes
                    </label>
                    <select id="login_type" name="login_type">
                        <option value="user" <?php echo ($login_type === 'user') ? 'selected' : ''; ?>>Përdorues i Rëndomtë</option>
                        <option value="admin" <?php echo ($login_type === 'admin') ? 'selected' : ''; ?>>Administrator</option>
                    </select>
                </div>

                <!-- Cloudflare Turnstile -->
                <div class="form-group">
                    <div class="cf-turnstile" data-sitekey="1x00000000000000000000AA" data-theme="light"></div>
                </div>

                <!-- Action Buttons -->
                <div class="button-group">
                    <button type="submit" id="loginBtn">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Kyçu në Sistem</span>
                    </button>
                    <button type="button" onclick="window.location.href='forgot_password.php'" class="reset-btn">
                        <i class="fas fa-key"></i>
                        <span>Rivendos</span>
                    </button>
                </div>
            </form>

            <!-- Error Messages -->
            <?php if ($error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Gabim:</strong> <?php echo htmlspecialchars($error); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Success Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="success">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <?php echo htmlspecialchars($_SESSION['success']); ?>
                    </div>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <!-- Registration Links -->
            <div class="register-link">
                <p>Nuk keni llogari? <a href="register.php">Regjistrohuni këtu</a></p>
                <p>Jeni Zyrë Noteriale? <a href="zyrat_register.php">Regjistrohuni si Noter</a></p>
                <p><a href="index.php" style="color: #64748b; font-weight: 500;"><i class="fas fa-home"></i> Kthehu në Fillim</a></p>
            </div>

            <!-- Security Badge -->
            <div class="security-badge">
                <i class="fas fa-shield-alt"></i>
                <span>Sistem i Sigurt me Enkriptim AES-256</span>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
        <div style="background: white; padding: 20px; border-radius: 16px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
            <div style="width: 40px; height: 40px; border: 4px solid #e5e7eb; border-top: 4px solid #244d96; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 16px;"></div>
            <p style="margin: 0; color: #374151; font-weight: 600;">Duke u kyçur...</p>
        </div>
    </div>

    <script>
        // Form submission with loading
        document.querySelector('form').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            const originalText = btn.innerHTML;

            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Duke u kyçur...</span>';
            btn.disabled = true;

            // Show loading overlay after a short delay
            setTimeout(() => {
                document.getElementById('loadingOverlay').style.display = 'flex';
            }, 500);
        });

        // Auto-focus first input
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('input[type="email"]');
            if (firstInput) {
                firstInput.focus();
            }
        });

        // Password visibility toggle (optional enhancement)
        document.getElementById('password').addEventListener('dblclick', function() {
            this.type = this.type === 'password' ? 'text' : 'password';
        });
    </script>


