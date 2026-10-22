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
require_once 'ad_helper.php';

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
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="description" content="Kyçuni në platformën Noteria - Sistemi i sigurt SaaS për zyrat noteriale në Kosovë">
    <meta name="keywords" content="noteria, kosove, login, platforma, saas, sigurim">
    <meta name="author" content="Noteria Platform">
    <title>Kyçuni | Noteria - Platforma SaaS për Zyrat Noteriale</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== ADVANCED RESET & BASE STYLES ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        html, body {
            height: 100%;
            width: 100%;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
        }

        /* ===== SOPHISTICATED BACKGROUND SYSTEM ===== */
        body {
            background:
                radial-gradient(circle at 20% 80%, rgba(207, 168, 86, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(36, 77, 150, 0.15) 0%, transparent 50%),
                linear-gradient(135deg, #244d96 0%, #1e3c72 25%, #2a5298 50%, #cfa856 75%, #b8860b 100%);
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow-x: hidden;
            padding: 16px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            background-attachment: fixed;
        }

        /* Multi-layered animated background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                radial-gradient(circle at 30% 70%, rgba(255, 255, 255, 0.05) 0%, transparent 40%),
                radial-gradient(circle at 70% 30%, rgba(207, 168, 86, 0.08) 0%, transparent 40%);
            animation: backgroundFloat 25s ease-in-out infinite;
            z-index: -1;
            pointer-events: none;
        }

        body::after {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.02) 50%, transparent 70%);
            animation: shimmer 15s linear infinite;
            z-index: -1;
            pointer-events: none;
        }

        @keyframes backgroundFloat {
            0%, 100% {
                transform: translate(0, 0) rotate(0deg);
                opacity: 0.6;
            }
            25% {
                transform: translate(-20px, -20px) rotate(1deg);
                opacity: 0.8;
            }
            50% {
                transform: translate(20px, -10px) rotate(-1deg);
                opacity: 0.7;
            }
            75% {
                transform: translate(-10px, 20px) rotate(0.5deg);
                opacity: 0.9;
            }
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(0deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(360deg); }
        }

        /* ===== PREMIUM LOGIN WRAPPER ===== */
        .login-wrapper {
            width: 100%;
            max-width: 440px;
            background:
                linear-gradient(145deg, rgba(255, 255, 255, 0.98) 0%, rgba(255, 255, 255, 0.95) 100%),
                linear-gradient(135deg, rgba(36, 77, 150, 0.02) 0%, rgba(207, 168, 86, 0.02) 100%);
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%);
            border-radius: 28px;
            box-shadow:
                0 32px 64px rgba(36, 77, 150, 0.12),
                0 16px 32px rgba(207, 168, 86, 0.08),
                inset 0 1px 0 rgba(255, 255, 255, 0.9),
                inset 0 -1px 0 rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
            animation: entrance 1.2s cubic-bezier(0.16, 1, 0.3, 1);
            transform-style: preserve-3d;
        }

        @keyframes entrance {
            0% {
                opacity: 0;
                transform: translateY(40px) scale(0.9) rotateX(10deg);
                filter: blur(10px);
            }
            50% {
                opacity: 0.8;
                transform: translateY(-5px) scale(1.02) rotateX(-2deg);
                filter: blur(2px);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1) rotateX(0deg);
                filter: blur(0);
            }
        }

        /* Premium border glow effect */
        .login-wrapper::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg,
                #244d96 0%,
                #cfa856 25%,
                #244d96 50%,
                #cfa856 75%,
                #244d96 100%);
            background-size: 200% 100%;
            animation: borderGlow 4s linear infinite;
            border-radius: 28px 28px 0 0;
        }

        @keyframes borderGlow {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Subtle inner shadow */
        .login-wrapper::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 28px;
            padding: 2px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(0, 0, 0, 0.05));
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask-composite: exclude;
            -webkit-mask-composite: xor;
            pointer-events: none;
        }

        /* ===== ELEGANT CONTAINER ===== */
        .login-container {
            padding: 48px 40px;
            position: relative;
            z-index: 1;
        }

        /* ===== LOGO SECTION ===== */
        .logo-container {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        .logo-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto;
            filter: drop-shadow(0 8px 16px rgba(36, 77, 150, 0.2));
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            animation: logoFloat 3s ease-in-out infinite;
            display: block;
            object-fit: contain;
            flex-shrink: 0;
        }

        .logo-icon:hover {
            transform: scale(1.08) rotate(5deg);
            filter: drop-shadow(0 12px 24px rgba(36, 77, 150, 0.3));
        }

        @keyframes logoFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
        }

        .logo-container h2 {
            font-size: 32px;
            font-weight: 800;
            color: #244d96;
            margin: 0;
            letter-spacing: -0.8px;
            text-shadow: 0 2px 4px rgba(36, 77, 150, 0.1);
            background: linear-gradient(135deg, #244d96 0%, #1e3c72 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: titleGlow 2s ease-in-out infinite alternate;
        }

        @keyframes titleGlow {
            from { filter: brightness(1); }
            to { filter: brightness(1.1); }
        }

        .subtitle {
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
            margin: 0;
            letter-spacing: 0.2px;
            animation: fadeIn 1s ease-out 0.6s both;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        /* ===== ADVANCED FORM STYLING ===== */
        .form-group {
            margin-bottom: 28px;
            position: relative;
            animation: slideInUp 0.6s ease-out both;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }
        .form-group:nth-child(4) { animation-delay: 0.4s; }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .form-group label i {
            color: #244d96;
            font-size: 16px;
            transition: transform 0.3s ease;
        }

        .form-group label:hover i {
            transform: scale(1.1);
        }

        .input-wrapper {
            position: relative;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 18px 20px 18px 50px;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            font-size: 16px;
            font-family: inherit;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            outline: none;
            position: relative;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #244d96;
            background: #ffffff;
            box-shadow:
                0 0 0 4px rgba(36, 77, 150, 0.08),
                0 8px 24px rgba(36, 77, 150, 0.12),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            transform: translateY(-2px);
        }

        .form-group input::placeholder {
            color: #9ca3af;
            transition: color 0.3s ease;
        }

        .form-group input:focus::placeholder {
            color: #d1d5db;
        }

        /* ===== PREMIUM BUTTONS ===== */
        .button-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 32px;
            margin-bottom: 24px;
        }

        .btn, button[type="submit"] {
            padding: 18px 24px;
            border: none;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            min-height: 56px;
            position: relative;
            overflow: hidden;
        }

        button[type="submit"],
        button[type="submit"] {
            background: linear-gradient(135deg, #244d96 0%, #1e3c72 100%);
            color: white;
            box-shadow: 0 8px 16px rgba(36, 77, 150, 0.25);
        }

        button[type="submit"]:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 32px rgba(36, 77, 150, 0.35), 0 0 0 4px rgba(36, 77, 150, 0.1);
        }

        button[type="submit"]:active {
            transform: translateY(-1px);
        }

        .reset-btn {
            background: linear-gradient(135deg, #cfa856 0%, #b8860b 100%);
            color: white;
            box-shadow: 0 8px 16px rgba(207, 168, 86, 0.25);
        }

        .reset-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 32px rgba(207, 168, 86, 0.35), 0 0 0 4px rgba(207, 168, 86, 0.1);
        }

        /* ===== ERROR & SUCCESS MESSAGES ===== */
        .error, .success {
            padding: 16px 18px;
            border-radius: 16px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.6;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: slideInDown 0.5s ease-out;
        }

        .error {
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.1) 0%, rgba(220, 38, 38, 0.05) 100%);
            border: 1px solid rgba(220, 38, 38, 0.2);
            color: #991b1b;
        }

        .error i {
            color: #dc2626;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .success {
            background: linear-gradient(135deg, rgba(22, 163, 74, 0.1) 0%, rgba(22, 163, 74, 0.05) 100%);
            border: 1px solid rgba(22, 163, 74, 0.2);
            color: #15803d;
        }

        .success i {
            color: #16a34a;
            flex-shrink: 0;
            margin-top: 2px;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ===== REGISTER LINKS ===== */
        .register-link {
            text-align: center;
            padding: 20px 0;
            border-top: 1px solid rgba(36, 77, 150, 0.1);
        }

        .register-link p {
            margin-bottom: 12px;
            font-size: 14px;
            color: #64748b;
        }

        .register-link a {
            color: #244d96;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border-bottom: 2px solid transparent;
        }

        .register-link a:hover {
            color: #1e3c72;
            border-bottom-color: #cfa856;
        }

        /* ===== SECURITY BADGE ===== */
        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 16px 18px;
            background: linear-gradient(135deg, rgba(36, 77, 150, 0.05) 0%, rgba(207, 168, 86, 0.05) 100%);
            border: 1px solid rgba(36, 77, 150, 0.1);
            border-radius: 12px;
            margin-top: 20px;
        }

        .security-badge i {
            color: #244d96;
            font-size: 16px;
        }

        .security-badge span {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }

        /* ===== RESPONSIVE DESIGN ===== */
        /* Extra Small Mobile (phones < 375px) */
        @media (max-width: 375px) {
            body {
                padding: 8px;
                align-items: flex-start;
                padding-top: 16px;
            }

            .login-wrapper {
                max-width: 100%;
                border-radius: 16px;
            }

            .login-container {
                padding: 24px 16px;
            }

            .logo-icon {
                width: 48px;
                height: 48px;
                margin: 0 auto;
            }

            .logo-container h2 {
                font-size: 20px;
                letter-spacing: -0.5px;
            }

            .subtitle {
                font-size: 12px;
            }

            .form-group {
                margin-bottom: 20px;
            }

            .form-group label {
                font-size: 13px;
                margin-bottom: 8px;
            }

            .form-group input,
            .form-group select {
                padding: 14px 16px 14px 40px;
                font-size: 16px;
                border-radius: 12px;
            }

            .button-group {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .btn, button[type="submit"] {
                padding: 14px 16px;
                min-height: 48px;
                font-size: 15px;
            }

            .register-link p {
                font-size: 12px;
                margin-bottom: 8px;
            }

            .security-badge {
                padding: 12px 14px;
            }

            .security-badge span {
                font-size: 11px;
            }
        }

        /* Small Mobile (phones 376-480px) */
        @media (min-width: 376px) and (max-width: 480px) {
            body {
                padding: 12px;
                align-items: flex-start;
                padding-top: 20px;
            }

            .login-wrapper {
                max-width: 100%;
                border-radius: 20px;
            }

            .login-container {
                padding: 32px 24px;
            }

            .logo-icon {
                width: 56px;
                height: 56px;
                margin: 0 auto;
            }

            .logo-container h2 {
                font-size: 24px;
            }

            .subtitle {
                font-size: 13px;
            }

            .form-group input,
            .form-group select {
                padding: 16px 18px 16px 44px;
                font-size: 16px;
                border-radius: 14px;
            }

            .button-group {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .btn, button[type="submit"] {
                padding: 16px 20px;
                min-height: 52px;
            }

            .register-link p {
                font-size: 13px;
            }

            .security-badge {
                padding: 14px 16px;
            }

            .security-badge span {
                font-size: 12px;
            }
        }

        /* Tablet (481-768px) */
        @media (min-width: 481px) and (max-width: 768px) {
            .login-wrapper {
                max-width: 400px;
            }

            .login-container {
                padding: 40px 32px;
            }

            .button-group {
                grid-template-columns: 1fr 1fr;
                gap: 16px;
            }

            .btn, button[type="submit"] {
                min-height: 48px;
            }
        }

        /* Small Desktop (769-1024px) */
        @media (min-width: 769px) and (max-width: 1024px) {
            .login-wrapper {
                max-width: 440px;
            }

            .login-container {
                padding: 48px 40px;
            }

            .button-group {
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }
        }

        /* Large Desktop (1025px+) */
        @media (min-width: 1025px) {
            .login-wrapper {
                max-width: 480px;
            }

            .login-container {
                padding: 56px 48px;
            }

            .logo-container h2 {
                font-size: 36px;
            }

            .form-group input,
            .form-group select {
                padding: 20px 22px 20px 52px;
                font-size: 17px;
            }

            .button-group {
                grid-template-columns: 1fr 1fr;
                gap: 24px;
            }

            .btn, button[type="submit"] {
                padding: 18px 24px;
                min-height: 56px;
                font-size: 17px;
            }
        }

        /* Ultra-wide screens */
        @media (min-width: 1440px) {
            .login-wrapper {
                max-width: 520px;
            }

            .login-container {
                padding: 64px 56px;
            }
        }

        /* ===== ACCESSIBILITY ===== */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation: none !important;
                transition: none !important;
            }

            .login-wrapper {
                animation: none;
            }

            body::before,
            body::after {
                animation: none;
            }
        }

        @media (prefers-contrast: high) {
            .login-wrapper {
                border: 3px solid #000;
            }

            button[type="submit"] {
                background: #000;
                color: #fff;
            }

            .reset-btn {
                background: #fff;
                color: #000;
                border: 2px solid #000;
            }
        }

        button[type="submit"]:focus-visible,
        .form-group input:focus-visible,
        .form-group select:focus-visible {
            outline: 3px solid #244d96;
            outline-offset: 2px;
        }

        /* ===== UTILITY CLASSES ===== */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
    </style>
    <?php echo getAdCSS(); ?>
</head>
<body>
    <!-- ADS TOP -->
    <div style="position: fixed; top: 10px; right: 10px; max-width: 300px; z-index: 999;">
        <?php
        $top_ads = getAdsForPlacement($pdo, 'login_top', 'all', 1);
        foreach ($top_ads as $ad) {
            echo displayAd($ad);
            recordAdImpression($pdo, $ad['id'], 'login_top');
        }
        ?>
    </div>
    
    <div class="login-wrapper">
        <div class="login-container">
            <div class="logo-container">
                <img src="images/Emblem_of_the_Republic_of_Kosovo.svg.png" alt="Kosovo Emblem" class="logo-icon">
                <h2>Noteria</h2>
                <p class="subtitle">Platforma SaaS për Zyrat Noteriale</p>
            </div>

            <form method="POST" action="" novalidate>
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

                <!-- Action Buttons -->
                <div class="button-group">
                    <button type="submit" class="btn">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Kyçu</span>
                    </button>
                    <button type="button" onclick="window.location.href='forgot_password.php'" class="btn reset-btn">
                        <i class="fas fa-key"></i>
                        <span>Rivendos</span>
                    </button>
                </div>
            </form>

            <?php if ($error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Gabim:</strong> <?php echo htmlspecialchars($error); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php
            if (isset($_SESSION['success'])) {
                echo '<div class="success">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        '.htmlspecialchars($_SESSION['success']).'
                    </div>
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
    
    <?php echo getAdJS(); ?>
    
    <script>
        // Enhanced form validation and interactions
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const submitBtn = document.querySelector('button[type="submit"]');
            
            // Real-time email validation
            emailInput.addEventListener('blur', function() {
                const isValid = this.value && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value);
                this.classList.toggle('valid', isValid);
                this.classList.toggle('invalid', !isValid && this.value);
            });
            
            // Password validation feedback
            passwordInput.addEventListener('input', function() {
                const isValid = this.value.length >= 6;
                this.classList.toggle('valid', isValid && emailInput.value);
                this.classList.toggle('invalid', !isValid && this.value.length > 0);
            });
            
            // Form submission with loading state
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.style.opacity = '0.7';
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Duke u kyçur...</span>';
                    }
                });
            }
            
            // Auto-focus first input
            if (emailInput) {
                setTimeout(() => {
                    emailInput.focus();
                }, 300);
            }
        });
    </script>
</body>
</html>
</html>
