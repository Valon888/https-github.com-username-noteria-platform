<?php
/**
 * Admin Login Page
 * Kyçja e administratorëve në sistemin e menaxhimit
 */

// ==========================================
// SESSION INITIALIZATION
// ==========================================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// ==========================================
// LOAD REQUIRED FILES
// ==========================================
require_once 'confidb.php';
require_once 'developer_config.php';

// ==========================================
// SECURITY HEADERS
// ==========================================
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

// ==========================================
// SESSION TIMEOUT CHECK
// ==========================================
$session_timeout = getenv('SESSION_TIMEOUT') ?: 1800; // 30 minutes default
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
    session_destroy();
    header("Location: admin_login.php?message=session_expired");
    exit();
}
$_SESSION['last_activity'] = time();

// ==========================================
// CSRF TOKEN GENERATION
// ==========================================
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==========================================
// REDIRECT IF ALREADY LOGGED IN
// ==========================================
if (isset($_SESSION['admin_id'])) {
    header("Location: billing_dashboard.php");
    exit();
}

// ==========================================
// INITIALIZE VARIABLES
// ==========================================
$error = null;
$success = null;

// ==========================================
// HANDLE URL MESSAGES
// ==========================================
if (isset($_GET['message'])) {
    if ($_GET['message'] === 'logged_out') {
        $success = "U shkëputët me sukses nga sistemi!";
    } elseif ($_GET['message'] === 'session_expired') {
        $error = "Sesioni ka skaduar. Ju lutemi kyçuni përsëri.";
    }
}

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'auth_required') {
        $error = "Duhet të kyçeni si admin për të aksesuar këtë faqe.";
    } elseif ($_GET['error'] === 'admin_required') {
        $error = "Kjo faqe kërkon autorizim admin.";
    }
}

// ==========================================
// PROCESS LOGIN FORM
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Basic validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email-i nuk është i vlefshëm.";
    } elseif (strlen($password) < 1) {
        $error = "Fjalëkalimi nuk mund të jetë bosh.";
    } else {
        try {
            // Check rate limiting - 5 failed attempts in 15 minutes
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM admin_login_attempts 
                 WHERE email = ? AND ip_address = ? 
                 AND attempt_time > (NOW() - INTERVAL 15 MINUTE)"
            );
            $stmt->execute([$email, $ip]);
            $failed_attempts = (int)$stmt->fetchColumn();
            
            if ($failed_attempts >= 5) {
                $error = "Janë bërë shumë përpjekje të dështuara. Provo pas 15 minutash.";
                error_log("ADMIN_LOGIN_BLOCKED: $email from $ip (attempts: $failed_attempts)");
            } else {
                // Query admin from database
                $stmt = $pdo->prepare(
                    "SELECT id, email, password, emri, status 
                     FROM admins 
                     WHERE email = ? 
                     LIMIT 1"
                );
                $stmt->execute([$email]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if admin exists, is active, and password is correct
                if ($admin && $admin['status'] === 'active' && password_verify($password, $admin['password'])) {
                    // ✅ LOGIN SUCCESSFUL
                    $adminId = $admin['id'];
                    
                    // Set admin session variables
                    $_SESSION['admin_id'] = $adminId;
                    $_SESSION['admin_email'] = $admin['email'];
                    $_SESSION['admin_name'] = $admin['emri'] ?? 'Administrator';
                    $_SESSION['is_developer'] = isDeveloper($adminId, $admin['email']);
                    $_SESSION['last_activity'] = time();
                    
                    // Also set user session variables for compatibility
                    $_SESSION['user_id'] = $adminId;
                    $_SESSION['emri'] = $admin['emri'] ?? 'Administrator';
                    $_SESSION['mbiemri'] = 'Admin';
                    $_SESSION['roli'] = 'admin';
                    
                    // Log successful login
                    error_log("ADMIN_LOGIN_SUCCESS: {$admin['email']} (ID: $adminId) from $ip");
                    
                    // Clear failed login attempts for this email/IP
                    try {
                        $stmt = $pdo->prepare(
                            "DELETE FROM admin_login_attempts 
                             WHERE email = ? AND ip_address = ? 
                             AND attempt_time > (NOW() - INTERVAL 1 DAY)"
                        );
                        $stmt->execute([$email, $ip]);
                    } catch (PDOException $e) {
                        error_log("Failed to clear login attempts: " . $e->getMessage());
                    }
                    
                    // Redirect based on developer status
                    session_write_close();
                    if ($_SESSION['is_developer']) {
                        header("Location: dashboard.php");
                    } else {
                        header("Location: billing_dashboard.php");
                    }
                    exit();
                    
                } else {
                    // ❌ LOGIN FAILED
                    $error = "Email ose fjalëkalim i gabuar!";
                    
                    // Log failed attempt
                    error_log("ADMIN_LOGIN_FAILED: $email from $ip");
                    
                    // Record failed login attempt in database
                    try {
                        $stmt = $pdo->prepare(
                            "INSERT INTO admin_login_attempts (email, ip_address, attempt_time) 
                             VALUES (?, ?, NOW())"
                        );
                        $stmt->execute([$email, $ip]);
                    } catch (PDOException $e) {
                        error_log("Failed to record login attempt: " . $e->getMessage());
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Admin Login Database Error: " . $e->getMessage());
            $error = "Ndodhi një gabim teknik. Ju lutemi provoni përsëri më vonë.";
        } catch (Exception $e) {
            error_log("Admin Login Unexpected Error: " . $e->getMessage());
            $error = "Ndodhi një gabim i papritur. Ju lutemi provoni përsëri.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Sistemi i Faturimit</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5568d3;
            --secondary: #764ba2;
            --success: #16a34a;
            --danger: #dc2626;
            --warning: #f59e0b;
            --light: #f9fafb;
            --dark: #1f2937;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-reverse: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            width: 100%;
        }

        body {
            font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--gradient);
            min-height: 100vh;
            min-height: 100dvh;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem;
            overflow-x: hidden;
            position: relative;
        }

        /* Background Animation */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255,255,255,0.05) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .login-container {
            background: white;
            border-radius: clamp(16px, 5vw, 24px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.2), 0 0 0 1px rgba(255, 255, 255, 0.1);
            overflow: hidden;
            max-width: clamp(280px, 95vw, 550px);
            width: 100%;
            animation: slideUp 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            max-height: 95dvh;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            background: var(--gradient);
            color: white;
            padding: clamp(1.5rem, 4vw, 3rem) clamp(1.25rem, 4vw, 2.5rem);
            text-align: center;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) translateX(0px); }
            50% { transform: translateY(-30px) translateX(30px); }
        }

        .login-header h1 {
            font-size: clamp(1.3rem, 6vw, 2.2rem);
            margin-bottom: clamp(0.5rem, 2vw, 0.75rem);
            font-weight: 800;
            letter-spacing: -0.5px;
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: clamp(0.5rem, 2vw, 0.75rem);
            flex-wrap: wrap;
            line-height: 1.2;
        }

        .login-header h1 i {
            font-size: clamp(1.2rem, 5vw, 2rem);
            flex-shrink: 0;
        }

        .login-header p {
            opacity: 0.95;
            font-size: clamp(0.75rem, 3vw, 0.95rem);
            position: relative;
            z-index: 1;
            font-weight: 500;
            letter-spacing: 0.3px;
            margin: 0;
            line-height: 1.4;
        }

        .login-form {
            padding: clamp(1.25rem, 4vw, 3rem);
            flex: 1;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        .form-group {
            margin-bottom: clamp(1rem, 3vw, 1.75rem);
        }

        .form-label {
            display: block;
            margin-bottom: clamp(0.4rem, 2vw, 0.75rem);
            font-weight: 700;
            color: var(--dark);
            font-size: clamp(0.75rem, 2.5vw, 0.95rem);
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .form-label i {
            margin-right: 0.5rem;
            color: var(--primary);
        }

        .form-control {
            width: 100%;
            padding: clamp(0.7rem, 2vw, 0.95rem) clamp(0.8rem, 2vw, 1.25rem);
            border: 2px solid var(--gray-200);
            border-radius: 14px;
            font-size: clamp(0.85rem, 2.5vw, 1rem);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: 'Montserrat', sans-serif;
            background: #fafafa;
        }

        .form-control::placeholder {
            color: #9ca3af;
        }

        .form-control:hover {
            border-color: var(--primary);
            background: #fff;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15), inset 0 0 0 1px rgba(102, 126, 234, 0.1);
        }

        .captcha-group {
            display: none;
        }

        .captcha-wrapper {
            flex: 0 1 auto;
            min-width: 0;
        }

        .captcha-label {
            display: block;
            margin-bottom: clamp(0.4rem, 2vw, 0.75rem);
            font-weight: 700;
            color: var(--dark);
            font-size: clamp(0.75rem, 2.5vw, 0.95rem);
            letter-spacing: 0.3px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .captcha-code {
            background: var(--gradient);
            color: white;
            padding: clamp(0.7rem, 2vw, 1.1rem) clamp(0.9rem, 3vw, 2rem);
            border-radius: 14px;
            font-weight: 800;
            font-size: clamp(1rem, 4vw, 1.5rem);
            letter-spacing: clamp(1px, 2vw, 3px);
            border: 2px solid transparent;
            font-family: 'Courier New', monospace;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.35);
            min-width: clamp(100px, 22vw, 160px);
            text-align: center;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .captcha-code:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(102, 126, 234, 0.45);
        }

        .captcha-input {
            flex: 1;
            min-width: clamp(70px, 25vw, 120px);
        }

        .btn {
            width: 100%;
            padding: clamp(0.8rem, 2vw, 1.1rem) clamp(0.9rem, 2vw, 1.5rem);
            background: var(--gradient);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: clamp(0.8rem, 2.5vw, 1rem);
            font-weight: 800;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            text-transform: uppercase;
            letter-spacing: clamp(0.3px, 1vw, 1px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
            margin-top: clamp(0.3rem, 1vw, 0.5rem);
        }

        .btn::before {
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

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.45);
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn:active {
            transform: translateY(-1px);
        }

        .alert {
            padding: clamp(0.8rem, 2vw, 1.25rem) clamp(0.9rem, 2vw, 1.5rem);
            border-radius: 14px;
            margin-bottom: clamp(0.8rem, 2vw, 1.75rem);
            display: flex;
            align-items: flex-start;
            gap: clamp(0.6rem, 2vw, 1rem);
            animation: slideDown 0.4s ease-out;
            position: relative;
            overflow: hidden;
            font-size: clamp(0.75rem, 2.5vw, 0.95rem);
            line-height: 1.5;
        }

        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--gradient);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert i {
            flex-shrink: 0;
            font-size: clamp(0.9rem, 2vw, 1.2rem);
            margin-top: 0.1rem;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid var(--danger);
        }

        .alert-error i {
            color: var(--danger);
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border-left: 4px solid var(--success);
        }

        .alert-success i {
            color: var(--success);
        }

        .developer-note {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            padding: clamp(0.8rem, 3vw, 1.5rem);
            border-radius: 14px;
            margin-top: clamp(1rem, 3vw, 2.5rem);
            font-size: clamp(0.65rem, 2vw, 0.85rem);
            border-left: 4px solid var(--warning);
            border-right: 1px solid rgba(146, 64, 14, 0.1);
        }

        .developer-note h4 {
            margin-bottom: clamp(0.5rem, 1.5vw, 1rem);
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: clamp(0.7rem, 2vw, 0.95rem);
            font-weight: 700;
            color: #78350f;
        }

        .developer-note p {
            margin-bottom: clamp(0.4rem, 1vw, 0.75rem);
            line-height: 1.5;
            color: #92400e;
        }

        .credentials-list {
            margin-top: clamp(0.4rem, 1vw, 0.75rem);
        }

        .credentials-list pre {
            background: rgba(255, 255, 255, 0.6);
            padding: clamp(0.4rem, 1.5vw, 0.875rem);
            border-radius: 8px;
            overflow-x: auto;
            font-size: clamp(0.5rem, 1.5vw, 0.7rem);
            margin-top: clamp(0.4rem, 1vw, 0.75rem);
            line-height: 1.4;
            border: 1px solid rgba(0, 0, 0, 0.05);
            color: #15803d;
            -webkit-overflow-scrolling: touch;
        }

        /* ===== RESPONSIVE DESIGN ===== */
        
        /* Large Desktop (1280px+) */
        @media (min-width: 1280px) {
            body {
                padding: 2rem;
            }

            .login-container {
                max-width: 500px;
                box-shadow: 0 40px 80px rgba(0, 0, 0, 0.25);
            }

            .login-header {
                padding: 3.5rem 3rem;
            }

            .login-header h1 {
                font-size: 2.5rem;
            }

            .login-form {
                padding: 3.5rem 3rem;
            }

            .form-group {
                margin-bottom: 2rem;
            }

            .form-label {
                font-size: 0.98rem;
            }

            .form-control {
                padding: 1rem 1.35rem;
                font-size: 1.05rem;
            }

            .btn {
                padding: 1.2rem 1.5rem;
                font-size: 1.05rem;
            }

            .developer-note {
                margin-top: 3rem;
                padding: 1.75rem;
                font-size: 0.9rem;
            }
        }

        /* Desktop (1024px - 1279px) */
        @media (min-width: 1024px) and (max-width: 1279px) {
            .login-container {
                max-width: 480px;
            }
        }

        /* Tablet (768px - 1023px) */
        @media (max-width: 1023px) {
            .login-container {
                max-width: 450px;
            }

            .login-header {
                padding: 2.5rem 2rem;
            }

            .login-header h1 {
                font-size: 2rem;
            }

            .login-form {
                padding: 2.5rem 2rem;
            }

            .form-group {
                margin-bottom: 1.5rem;
            }

            .captcha-group {
                gap: 1rem;
            }

            .captcha-code {
                min-width: 140px;
                padding: 0.95rem 1.5rem;
                font-size: 1.3rem;
            }

            .developer-note {
                margin-top: 2rem;
                padding: 1.25rem;
                font-size: 0.8rem;
            }

            .credentials-list pre {
                font-size: 0.6rem;
            }
        }

        /* Mobile (480px - 767px) */
        @media (max-width: 767px) {
            body {
                padding: 0.75rem;
            }

            .login-container {
                border-radius: 20px;
                max-width: 100%;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            }

            .login-header {
                padding: 2rem 1.75rem;
            }

            .login-header h1 {
                font-size: 1.75rem;
            }

            .login-header p {
                font-size: 0.9rem;
            }

            .login-form {
                padding: 2rem 1.75rem;
            }

            .form-group {
                margin-bottom: 1.3rem;
            }

            .form-label {
                font-size: 0.9rem;
            }

            .form-control {
                padding: 0.85rem 1.1rem;
                font-size: 16px; /* Prevents zoom on iOS */
            }

            .captcha-group {
                flex-direction: column;
                align-items: stretch;
                gap: 0.75rem;
            }

            .captcha-wrapper {
                width: 100%;
            }

            .captcha-code {
                width: 100%;
                padding: 0.85rem 1rem;
                font-size: 1.2rem;
                letter-spacing: 2px;
                min-width: unset;
            }

            .captcha-input {
                min-width: unset;
            }

            .btn {
                padding: 0.95rem 1.25rem;
                font-size: 0.95rem;
                letter-spacing: 0.5px;
            }

            .alert {
                padding: 1rem 1.25rem;
                font-size: 0.9rem;
                gap: 0.75rem;
            }

            .alert i {
                font-size: 1rem;
            }

            .developer-note {
                margin-top: 1.5rem;
                padding: 1rem;
                font-size: 0.75rem;
                border-radius: 12px;
            }

            .developer-note h4 {
                font-size: 0.85rem;
                margin-bottom: 0.75rem;
            }

            .developer-note p {
                font-size: 0.75rem;
                margin-bottom: 0.5rem;
            }

            .credentials-list pre {
                padding: 0.6rem;
                font-size: 0.55rem;
            }
        }

        /* Small Mobile (max 479px) */
        @media (max-width: 479px) {
            body {
                padding: 0.5rem;
                min-height: 100vh;
            }

            .login-container {
                max-width: 100%;
                border-radius: 18px;
            }

            .login-header {
                padding: 1.75rem 1.5rem;
            }

            .login-header h1 {
                font-size: 1.5rem;
            }

            .login-header p {
                font-size: 0.85rem;
            }

            .login-form {
                padding: 1.75rem 1.5rem;
            }

            .form-group {
                margin-bottom: 1.2rem;
            }

            .form-label {
                font-size: 0.85rem;
            }

            .form-control {
                padding: 0.8rem 1rem;
                font-size: 16px;
            }

            .captcha-code {
                padding: 0.8rem 0.9rem;
                font-size: 1.1rem;
                letter-spacing: 1.5px;
            }

            .btn {
                padding: 0.9rem 1.2rem;
                font-size: 0.9rem;
            }

            .developer-note {
                display: none;
            }
        }

        /* Landscape (max-height: 600px) */
        @media (max-height: 600px) and (orientation: landscape) {
            body {
                padding: 0.5rem;
                min-height: auto;
            }

            .login-container {
                max-height: 95dvh;
                max-width: clamp(300px, 90vw, 500px);
            }

            .login-header {
                padding: 1rem 1.25rem;
            }

            .login-header h1 {
                font-size: 1.2rem;
                margin-bottom: 0.2rem;
            }

            .login-header p {
                font-size: 0.7rem;
            }

            .login-form {
                padding: 1rem 1.25rem;
                max-height: 60dvh;
            }

            .form-group {
                margin-bottom: 0.7rem;
            }

            .form-label {
                font-size: 0.75rem;
            }

            .captcha-group {
                gap: 0.5rem;
                margin-bottom: 0.7rem;
            }

            .captcha-label {
                font-size: 0.7rem;
            }

            .captcha-code {
                padding: 0.6rem 0.8rem;
                font-size: 0.95rem;
                letter-spacing: 1.5px;
                min-width: 90px;
            }

            .btn {
                padding: 0.7rem 1rem;
                font-size: 0.8rem;
            }

            .developer-note {
                display: none;
            }
        }

        /* Small Landscape (max-height: 480px) */
        @media (max-height: 480px) and (orientation: landscape) {
            .login-container {
                max-height: 90dvh;
            }

            .login-header {
                padding: 0.6rem 0.9rem;
            }

            .login-header h1 {
                font-size: 1rem;
                margin-bottom: 0.1rem;
            }

            .login-header p {
                font-size: 0.6rem;
            }

            .login-form {
                padding: 0.6rem 0.9rem;
                max-height: 50dvh;
            }

            .form-group {
                margin-bottom: 0.5rem;
            }

            .captcha-group {
                margin-bottom: 0.5rem;
            }

            .btn {
                padding: 0.6rem 0.9rem;
                font-size: 0.75rem;
            }
        }

        /* ===== ACCESSIBILITY ===== */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation: none !important;
                transition: none !important;
            }

            .btn::before {
                display: none;
            }
        }

        /* Dark Mode Support */
        @media (prefers-color-scheme: dark) {
            body {
                background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            }

            .login-container {
                background: #1f2937;
                color: #f3f4f6;
            }

            .form-control {
                background: #374151;
                border-color: #4b5563;
                color: #f3f4f6;
            }

            .form-control::placeholder {
                color: #9ca3af;
            }

            .form-label {
                color: #e5e7eb;
            }

            .developer-note {
                background: #374151;
                color: #fcd34d;
                border-left-color: #fbbf24;
            }

            .developer-note h4 {
                color: #fcd34d;
            }

            .credentials-list pre {
                background: #111827;
                color: #86efac;
                border-color: #4b5563;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><i class="fas fa-shield-alt"></i> Admin Login</h1>
            <p>Sistemi i Menaxhimit të Faturimit</p>
        </div>

        <div class="login-form">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                
                <div class="form-group">
                    <label class="form-label" for="email">
                        <i class="fas fa-envelope"></i> Email
                    </label>
                    <input type="email" id="email" name="email" class="form-control" required autocomplete="email"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">
                        <i class="fas fa-lock"></i> Fjalëkalimi
                    </label>
                    <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
                </div>

                <button type="submit" class="btn">
                    <i class="fas fa-sign-in-alt"></i> Kyçu si Admin
                </button>
            </form>

            <div class="developer-note">
                <h4><i class="fas fa-code"></i> Për Administratorët</h4>
                <p>Kredencialet ruhen në databazën <strong>admins</strong> me hashing bcrypt.</p>
                <p style="margin-top: 0.5rem;">Për të shtuar admin të ri ose ndryshuar fjalëkalimin:</p>
                <div class="credentials-list" style="margin-top: 0.5rem;">
<pre style="background:#f3f4f6; padding:0.5rem; border-radius:4px; overflow-x:auto; font-size:0.75rem;">php -r "echo password_hash('password', PASSWORD_DEFAULT);"</pre>
                    <p style="margin-top: 0.5rem; color:#666;">Pastaj INSERT/UPDATE në tabelën admins:</p>
<pre style="background:#f3f4f6; padding:0.5rem; border-radius:4px; overflow-x:auto; font-size:0.75rem;">INSERT INTO admins (email, password, emri, status)
VALUES ('user@noteria.com', '$2y$10$hash...', 'Emri', 'active');</pre>
                </div>
            </div>
        </div>
    </div>
</body>
</html>