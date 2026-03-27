<?php
// Move all session and ini_set calls to the very top, before any output
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
ini_set('session.use_strict_mode', 1);

// Redirekto në HTTPS nëse nuk është aktiv dhe nëse variablat ekzistojnë
if (
    (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') &&
    isset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'])
) {
    $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('Location: ' . $redirect);
    exit();
}

// Cloudflare real IP support
if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
}

if (!file_exists('confidb.php')) {
    die("Gabim: File-i 'confidb.php' nuk ekziston.");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
if (!isset($_SESSION['last_login_attempt'])) $_SESSION['last_login_attempt'] = 0;
if ($_SESSION['login_attempts'] > 5 && (time() - $_SESSION['last_login_attempt']) < 900) {
    die('Shumë tentativa të dështuara. Provo pas 15 minutash.');
}

require_once 'confidb.php';
require_once 'activity_logger.php';
require_once 'mfa_helper.php';

if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit();
}

$error = null;

if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = filter_var(trim($_POST["email"] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST["password"] ?? '');
    $role_selected = trim($_POST["role_type"] ?? '');

    // Validim
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email-i nuk është i vlefshëm.";
    } elseif (strlen($password) < 6) {
        $error = "Fjalëkalimi duhet të ketë të paktën 6 karaktere.";
    } elseif (!in_array($role_selected, ['user', 'noter', 'admin'])) {
        $error = "Ju lutem zgjidhni një rol të vlefshëm.";
    }

    if (!$error) {
        $stmt = $pdo->prepare("SELECT id, emri, mbiemri, email, password, roli FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $_SESSION['login_attempts']++;
            $_SESSION['last_login_attempt'] = time();
            $error = "Ky email nuk ekziston në sistem.";
        } elseif (!password_verify($password, $user["password"])) {
            $_SESSION['login_attempts']++;
            $_SESSION['last_login_attempt'] = time();
            $error = "Fjalëkalimi është i pasaktë për këtë email.";
        } else {
            $roli_db = $user["roli"] ?? "user";
            // Prano 'notary' si 'noter', 'perdorues' si 'user'
            if ($roli_db === "notary") $roli_db = "noter";
            if ($roli_db === "perdorues") $roli_db = "user";
            // Nëse roli në DB është bosh, vendos rolin e zgjedhur në DB
            if (!$roli_db) {
                $stmt = $pdo->prepare("UPDATE users SET roli = ? WHERE id = ?");
                $stmt->execute([$role_selected, $user["id"]]);
                $roli_db = $role_selected;
            }

            // Kontrollo nëse roli i zgjedhur përputhet me rolin në DB
            if ($roli_db !== $role_selected) {
                $_SESSION['login_attempts']++;
                $_SESSION['last_login_attempt'] = time();
                $error = "Roli i zgjedhur nuk përputhet me llogarinë tuaj. (Në DB: $roli_db, Zgjedhur: $role_selected)";
            } else {
                $_SESSION["user_id"]  = $user["id"];
                $_SESSION["emri"]     = htmlspecialchars($user["emri"]);
                $_SESSION["mbiemri"]  = htmlspecialchars($user["mbiemri"]);
                $_SESSION["email"]    = htmlspecialchars($user["email"]);
                $_SESSION["roli"]     = htmlspecialchars($roli_db);
                $_SESSION['last_activity'] = time();
                unset($_SESSION['captcha_text']);
                $_SESSION['login_attempts'] = 0;

                // Get and set user's office ID from users table
                try {
                    $stmt = $pdo->prepare("SELECT zyra_id FROM users WHERE id = ?");
                    $stmt->execute([$user["id"]]);
                    $user_office = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user_office && !empty($user_office['zyra_id'])) {
                        $_SESSION['zyra_id'] = $user_office['zyra_id'];
                    }
                } catch (Exception $e) {
                    error_log("Error fetching zyra_id: " . $e->getMessage());
                }

                log_activity($pdo, $_SESSION['user_id'], 'Kyçje', 'Kyçje e suksesshme - Roli: ' . $_SESSION["roli"]);

                    if ($_SESSION["roli"] === "admin") {
                        header("Location: admin_dashboard.php");
                    } elseif ($_SESSION["roli"] === "noter") {
                        header("Location: dashboard.php");
                    } else {
                        header("Location: dashboard.php");
                    }
                exit();
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
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="description" content="Kyçuni në platformën Noteria - Sistemi i sigurt SaaS për zyrat noteriale në Kosovë">
    <title>Kyçuni | e-Noteria - Platforma SaaS për Zyrat Noteriale</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        html, body { height: 100%; width: 100%; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }

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
            background-attachment: fixed;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background:
                radial-gradient(circle at 30% 70%, rgba(255,255,255,0.05) 0%, transparent 40%),
                radial-gradient(circle at 70% 30%, rgba(207,168,86,0.08) 0%, transparent 40%);
            animation: backgroundFloat 25s ease-in-out infinite;
            z-index: -1;
            pointer-events: none;
        }

        @keyframes backgroundFloat {
            0%, 100% { transform: translate(0,0) rotate(0deg); opacity: 0.6; }
            25%  { transform: translate(-20px,-20px) rotate(1deg); opacity: 0.8; }
            50%  { transform: translate(20px,-10px) rotate(-1deg); opacity: 0.7; }
            75%  { transform: translate(-10px,20px) rotate(0.5deg); opacity: 0.9; }
        }

        .login-wrapper {
            width: 100%;
            max-width: 460px;
            background: linear-gradient(145deg, rgba(255,255,255,0.98) 0%, rgba(255,255,255,0.95) 100%);
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%);
            border-radius: 28px;
            box-shadow:
                0 32px 64px rgba(36,77,150,0.12),
                0 16px 32px rgba(207,168,86,0.08),
                inset 0 1px 0 rgba(255,255,255,0.9);
            border: 1px solid rgba(255,255,255,0.3);
            position: relative;
            overflow: hidden;
            animation: entrance 1.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes entrance {
            0%   { opacity: 0; transform: translateY(40px) scale(0.9); filter: blur(10px); }
            50%  { opacity: 0.8; transform: translateY(-5px) scale(1.02); filter: blur(2px); }
            100% { opacity: 1; transform: translateY(0) scale(1); filter: blur(0); }
        }

        .login-wrapper::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #244d96 0%, #cfa856 25%, #244d96 50%, #cfa856 75%, #244d96 100%);
            background-size: 200% 100%;
            animation: borderGlow 4s linear infinite;
            border-radius: 28px 28px 0 0;
        }

        @keyframes borderGlow {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .login-container {
            padding: 48px 40px;
            position: relative;
            z-index: 1;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 36px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 72px;
            height: 72px;
            filter: drop-shadow(0 8px 16px rgba(36,77,150,0.2));
            animation: logoFloat 3s ease-in-out infinite;
            object-fit: contain;
        }

        @keyframes logoFloat {
            0%, 100% { transform: translateY(0px); }
            50%       { transform: translateY(-8px); }
        }

        .logo-container h2 {
            font-size: 32px;
            font-weight: 800;
            color: #244d96;
            letter-spacing: -0.8px;
            background: linear-gradient(135deg, #244d96 0%, #1e3c72 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .subtitle {
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
        }

        /* ===== ROLE SELECTOR ===== */
        .role-selector {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 28px;
        }

        .role-option {
            position: relative;
        }

        .role-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .role-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 8px;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            text-align: center;
            user-select: none;
            min-height: 90px;
        }

        .role-card:hover {
            border-color: #244d96;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(36,77,150,0.12);
            background: linear-gradient(135deg, #eef2ff 0%, #ffffff 100%);
        }

        .role-option input[type="radio"]:checked + .role-card {
            border-color: #244d96;
            background: linear-gradient(135deg, #244d96 0%, #1e3c72 100%);
            box-shadow: 0 8px 24px rgba(36,77,150,0.3);
            transform: translateY(-3px);
        }

        .role-option input[type="radio"]:checked + .role-card .role-icon {
            color: #cfa856;
            filter: drop-shadow(0 2px 4px rgba(207,168,86,0.4));
        }

        .role-option input[type="radio"]:checked + .role-card .role-label {
            color: #ffffff;
        }

        .role-option input[type="radio"]:checked + .role-card .role-desc {
            color: rgba(255,255,255,0.75);
        }

        .role-icon {
            font-size: 24px;
            color: #244d96;
            transition: all 0.3s ease;
        }

        .role-label {
            font-size: 12px;
            font-weight: 700;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            transition: color 0.3s ease;
            line-height: 1.2;
        }

        .role-desc {
            font-size: 10px;
            color: #9ca3af;
            transition: color 0.3s ease;
            line-height: 1.3;
        }

        .role-selector-label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 12px;
        }

        .role-selector-label i {
            color: #244d96;
            font-size: 16px;
        }

        /* ===== FORM GROUPS ===== */
        .form-group {
            margin-bottom: 24px;
            position: relative;
            animation: slideInUp 0.6s ease-out both;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }

        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 10px;
        }

        .form-group label i {
            color: #244d96;
            font-size: 16px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 16px;
            pointer-events: none;
            transition: color 0.3s ease;
            z-index: 1;
        }

        .form-group input {
            width: 100%;
            padding: 17px 20px 17px 48px;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            font-size: 16px;
            font-family: inherit;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            outline: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .form-group input:focus {
            border-color: #244d96;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(36,77,150,0.08), 0 8px 24px rgba(36,77,150,0.12);
            transform: translateY(-2px);
        }

        .form-group input:focus + .input-icon,
        .input-wrapper:focus-within .input-icon {
            color: #244d96;
        }

        .form-group input::placeholder { color: #9ca3af; }

        /* ===== BUTTONS ===== */
        .button-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 28px;
            margin-bottom: 24px;
        }

        .btn {
            padding: 18px 24px;
            border: none;
            border-radius: 16px;
            font-size: 15px;
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

        .btn-login {
            background: linear-gradient(135deg, #244d96 0%, #1e3c72 100%);
            color: white;
            box-shadow: 0 8px 16px rgba(36,77,150,0.25);
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 32px rgba(36,77,150,0.35);
        }

        .btn-reset {
            background: linear-gradient(135deg, #cfa856 0%, #b8860b 100%);
            color: white;
            box-shadow: 0 8px 16px rgba(207,168,86,0.25);
        }

        .btn-reset:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 32px rgba(207,168,86,0.35);
        }

        /* ===== ERROR / SUCCESS ===== */
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
            background: linear-gradient(135deg, rgba(220,38,38,0.1) 0%, rgba(220,38,38,0.05) 100%);
            border: 1px solid rgba(220,38,38,0.2);
            color: #991b1b;
        }

        .error i { color: #dc2626; flex-shrink: 0; margin-top: 2px; }

        .success {
            background: linear-gradient(135deg, rgba(22,163,74,0.1) 0%, rgba(22,163,74,0.05) 100%);
            border: 1px solid rgba(22,163,74,0.2);
            color: #15803d;
        }

        .success i { color: #16a34a; flex-shrink: 0; margin-top: 2px; }

        @keyframes slideInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ===== REGISTER LINKS ===== */
        .register-link {
            text-align: center;
            padding: 20px 0 0;
            border-top: 1px solid rgba(36,77,150,0.1);
        }

        .register-link p {
            margin-bottom: 10px;
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
            padding: 14px 16px;
            background: linear-gradient(135deg, rgba(36,77,150,0.05) 0%, rgba(207,168,86,0.05) 100%);
            border: 1px solid rgba(36,77,150,0.1);
            border-radius: 12px;
            margin-top: 20px;
        }

        .security-badge i { color: #244d96; font-size: 15px; }
        .security-badge span { font-size: 12px; color: #64748b; font-weight: 500; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 480px) {
            body { padding: 12px; align-items: flex-start; padding-top: 20px; }
            .login-wrapper { max-width: 100%; border-radius: 20px; }
            .login-container { padding: 32px 20px; }
            .logo-icon { width: 56px; height: 56px; }
            .logo-container h2 { font-size: 24px; }

            .role-selector { grid-template-columns: repeat(3, 1fr); gap: 8px; }
            .role-card { padding: 10px 6px; min-height: 80px; border-radius: 12px; }
            .role-icon { font-size: 20px; }
            .role-label { font-size: 10px; }
            .role-desc { font-size: 9px; }

            .button-group { grid-template-columns: 1fr; gap: 10px; }
            .btn { padding: 14px 16px; min-height: 48px; font-size: 14px; }
        }

        @media (max-width: 360px) {
            .role-selector { gap: 6px; }
            .role-card { padding: 8px 4px; min-height: 72px; }
            .role-icon { font-size: 18px; }
            .role-label { font-size: 9px; letter-spacing: 0; }
            .role-desc { display: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            * { animation: none !important; transition: none !important; }
        }

        @media (prefers-contrast: high) {
            .login-wrapper { border: 3px solid #000; }
            .btn-login { background: #000; color: #fff; }
        }

        .btn:focus-visible,
        .form-group input:focus-visible {
            outline: 3px solid #244d96;
            outline-offset: 2px;
        }
    </style>
    <?php
    if (!function_exists('getAdCSS')) {
        require_once 'ad_helper.php';
    }
    echo getAdCSS();
    ?>
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
            <!-- Logo -->
            <div class="logo-container">
                <img src="images/pngwing.com (1).png" class="logo-icon" alt="Noteria Logo">
                <h2>e-Noteria</h2>
                <p class="subtitle">Platforma SaaS për Zyrat Noteriale</p>
            </div>

            <form method="POST" action="" novalidate>
                <!-- Role Selector -->
                <div class="form-group">
                    <div class="role-selector-label">
                        <i class="fas fa-user-tag"></i>
                        Zgjidhni Rolin Tuaj
                    </div>
                    <div class="role-selector" role="group" aria-labelledby="role-label">
                        <!-- Përdorues i thjeshtë -->
                        <label class="role-option">
                            <input
                                type="radio"
                                name="role_type"
                                value="user"
                                <?php echo (($_POST['role_type'] ?? '') === 'user' || !isset($_POST['role_type'])) ? 'checked' : ''; ?>
                                required
                            >
                            <div class="role-card">
                                <i class="fas fa-user role-icon"></i>
                                <span class="role-label">Përdorues</span>
                                <span class="role-desc">I thjeshtë</span>
                            </div>
                        </label>

                        <!-- Zyrë Noteriale -->
                        <label class="role-option">
                            <input
                                type="radio"
                                name="role_type"
                                value="noter"
                                <?php echo (($_POST['role_type'] ?? '') === 'noter') ? 'checked' : ''; ?>
                            >
                            <div class="role-card">
                                <i class="fas fa-stamp role-icon"></i>
                                <span class="role-label">Noteri</span>
                                <span class="role-desc">Zyrë Noteriale</span>
                            </div>
                        </label>

                        <!-- Administrator -->
                        <label class="role-option">
                            <input
                                type="radio"
                                name="role_type"
                                value="admin"
                                <?php echo (($_POST['role_type'] ?? '') === 'admin') ? 'checked' : ''; ?>
                            >
                            <div class="role-card">
                                <i class="fas fa-shield-alt role-icon"></i>
                                <span class="role-label">Admin</span>
                                <span class="role-desc">Administrator</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Email -->
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
                        <i class="fas fa-envelope input-icon"></i>
                    </div>
                </div>

                <!-- Fjalëkalimi -->
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
                            placeholder="Fjalëkalimi"
                            required
                            autocomplete="current-password"
                        >
                        <i class="fas fa-lock input-icon"></i>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="button-group">
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Kyçu</span>
                    </button>
                    <button type="button" onclick="window.location.href='forgot_password.php'" class="btn btn-reset">
                        <i class="fas fa-key"></i>
                        <span>Rivendos</span>
                    </button>
                </div>
            </form>

            <?php if ($error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div><strong>Gabim:</strong> <?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <?php
            if (isset($_SESSION['success'])) {
                echo '<div class="success">
                    <i class="fas fa-check-circle"></i>
                    <div>'.htmlspecialchars($_SESSION['success']).'</div>
                </div>';
                unset($_SESSION['success']);
            }
            ?>

            <div class="register-link">
                <p>Nuk keni llogari? <a href="register.php">Regjistrohuni këtu</a></p>
                <p>Jeni Zyrë Noteriale? <a href="zyrat_register.php">Regjistrohuni si Noter</a></p>
            </div>

            <div class="security-badge">
                <i class="fas fa-shield-alt"></i>
                <span>Qasja e paautorizuar është e ndaluar dhe të gjitha veprimet logohen.</span>
            </div>
        </div>
    </div>
</body>
</html>
