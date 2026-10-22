<?php
/**
 * Forgot Password Recovery Page
 * Noteria Shtetërore — Republika e Kosovës
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

session_start();

if (empty($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php', true, 302);
    exit;
}

$error            = null;
$success          = null;
$rate_limit_error = false;

require_once 'confidb.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting
$client_ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rate_limit_key  = 'forgot_pwd_' . hash('sha256', $client_ip);
$max_attempts    = 5;
$rate_limit_window = 900;

if (isset($_SESSION[$rate_limit_key])) {
    $attempts     = $_SESSION[$rate_limit_key];
    $time_elapsed = time() - $attempts['timestamp'];
    if ($attempts['count'] >= $max_attempts && $time_elapsed < $rate_limit_window) {
        $rate_limit_error = true;
        $remaining_time   = ceil(($rate_limit_window - $time_elapsed) / 60);
        $error = "Shumë përpjekje. Provo përsëri pas {$remaining_time} minutash.";
    } elseif ($time_elapsed >= $rate_limit_window) {
        unset($_SESSION[$rate_limit_key]);
    }
}

// POST processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$rate_limit_error) {
    $csrf_post    = $_POST['csrf_token'] ?? null;
    $csrf_session = $_SESSION['csrf_token'] ?? null;

    if (empty($csrf_post) || !hash_equals($csrf_session, $csrf_post)) {
        $error = 'Gabim sigurie. Provo përsëri.';
        log_security_event($pdo, $client_ip, 'csrf_failure');
    } else {
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email-i nuk është i vlefshëm. Kontrolloni të dhënat.';
        } else {
            try {
                $email = strtolower($email);
                if (!isset($_SESSION[$rate_limit_key])) {
                    $_SESSION[$rate_limit_key] = ['count' => 1, 'timestamp' => time()];
                } else {
                    $_SESSION[$rate_limit_key]['count']++;
                }

                $stmt = $pdo->prepare('SELECT id, emri, mbiemri FROM users WHERE LOWER(email) = ? AND status = "aktiv" LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $reset_token = bin2hex(random_bytes(32));
                    $expires     = date('Y-m-d H:i:s', time() + 3600);
                    $stmt2 = $pdo->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?');
                    $stmt2->execute([$reset_token, $expires, $user['id']]);

                    $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $reset_url = "{$protocol}://{$host}/noteria/reset_password.php?token=" . urlencode($reset_token);

                    $sent = send_password_reset_email($user['emri'], $user['mbiemri'], $email, $reset_url);
                    if ($sent) {
                        $success = 'Linku për rivendosjen u dërgua. Kontrollo kutinë e marrjes.';
                        log_forgot_password_request($pdo, $user['id'], $email, 'email_sent', $client_ip);
                    } else {
                        $error = 'Gabim gjatë dërgimit të emailit. Provo përsëri më vonë.';
                        log_forgot_password_request($pdo, $user['id'], $email, 'email_failed', $client_ip);
                    }
                } else {
                    $success = 'Nëse ky email ekziston në sistem, do të marrësh instruksione për rivendosje.';
                    log_forgot_password_request($pdo, null, $email, 'user_not_found', $client_ip);
                }
            } catch (PDOException $e) {
                error_log('DB error in forgot_password: ' . $e->getMessage());
                $error = 'Gabim në sistem. Kontaktoni përkrahjen teknikore.';
                log_security_event($pdo, $client_ip, 'database_error');
            } catch (Exception $e) {
                error_log('Error in forgot_password: ' . $e->getMessage());
                $error = 'Gabim i panjohur. Provo përsëri.';
            }
        }
    }
}

function send_password_reset_email($emri, $mbiemri, $email, $reset_url) {
    try {
        $full_name = htmlspecialchars(trim("{$emri} {$mbiemri}"), ENT_QUOTES, 'UTF-8');
        error_log("Password reset link for {$email}: {$reset_url}");
        return true;
    } catch (Exception $e) {
        error_log('Email error: ' . $e->getMessage());
        return false;
    }
}

function log_forgot_password_request($pdo, $user_id, $email, $status, $client_ip) {
    try {
        $stmt = $pdo->prepare('INSERT INTO audit_log (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$user_id, 'Forgot Password Request', "Email: {$email} | Status: {$status}", $client_ip]);
    } catch (PDOException $e) { error_log('Audit log error: ' . $e->getMessage()); }
}

function log_security_event($pdo, $client_ip, $event_type) {
    try {
        $stmt = $pdo->prepare('INSERT INTO audit_log (user_id, action, details, ip_address, created_at) VALUES (NULL, ?, ?, ?, NOW())');
        $stmt->execute(['Security Event', "Type: {$event_type}", $client_ip]);
    } catch (PDOException $e) { error_log('Security log error: ' . $e->getMessage()); }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Rivendose fjalëkalimin tuaj në e-Noteria">
    <title>Rivendos Fjalëkalimin | e-Noteria</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">

    <style>
        /* ── TOKENS ─────────────────────────────────── */
        :root {
            --navy:   #0B1F4B;
            --navy2:  #122660;
            --gold:   #C9A84C;
            --gold2:  #E8C97A;
            --cream:  #FAF7F2;
            --smoke:  #F0EDE8;
            --mist:   #E2DDD5;
            --ink:    #1A1A2E;
            --ash:    #6B6B7B;
            --danger: #C0392B;
            --ok:     #1A6B4A;

            --font-display: 'Cormorant Garamond', Georgia, serif;
            --font-body:    'DM Sans', system-ui, sans-serif;

            --radius-sm: 6px;
            --radius-md: 14px;
            --radius-lg: 24px;
            --shadow-card: 0 32px 80px rgba(11,31,75,0.14), 0 4px 16px rgba(11,31,75,0.07);
            --shadow-btn:  0 8px 24px rgba(201,168,76,0.35);
        }

        /* ── RESET ───────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }

        /* ── BODY / SCENE ────────────────────────────── */
        body {
            font-family: var(--font-body);
            background-color: var(--navy);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
            position: relative;
            overflow-x: hidden;
            color: var(--ink);
        }

        /* Geometric background decoration */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 60% 50% at 15% 20%, rgba(201,168,76,0.10) 0%, transparent 70%),
                radial-gradient(ellipse 50% 60% at 85% 80%, rgba(18,38,96,0.6) 0%, transparent 70%),
                linear-gradient(160deg, #0B1F4B 0%, #0d2050 45%, #091838 100%);
            pointer-events: none;
            z-index: 0;
        }

        /* Subtle grid overlay */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(201,168,76,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(201,168,76,0.04) 1px, transparent 1px);
            background-size: 48px 48px;
            pointer-events: none;
            z-index: 0;
        }

        /* ── FLOATING ORBS ───────────────────────────── */
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.18;
            pointer-events: none;
            z-index: 0;
            animation: drift 18s ease-in-out infinite alternate;
        }
        .orb-1 { width: 500px; height: 500px; background: var(--gold); top: -120px; left: -120px; animation-delay: 0s; }
        .orb-2 { width: 400px; height: 400px; background: #3a5bd9; bottom: -100px; right: -100px; animation-delay: -7s; }
        .orb-3 { width: 260px; height: 260px; background: var(--gold2); top: 40%; left: 60%; animation-delay: -3s; }

        @keyframes drift {
            from { transform: translate(0, 0) scale(1); }
            to   { transform: translate(30px, 20px) scale(1.05); }
        }

        /* ── WRAPPER ─────────────────────────────────── */
        .wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 480px;
            display: flex;
            flex-direction: column;
            gap: 0;
            animation: rise 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @keyframes rise {
            from { opacity: 0; transform: translateY(32px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── BADGE (top label) ───────────────────────── */
        .badge-gov {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(201,168,76,0.12);
            border: 1px solid rgba(201,168,76,0.28);
            border-radius: 100px;
            padding: 7px 18px 7px 12px;
            width: fit-content;
            margin: 0 auto 20px;
        }
        .badge-gov .seal {
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, var(--gold), var(--gold2));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            color: var(--navy);
            font-weight: 700;
        }
        .badge-gov span {
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: var(--gold2);
        }

        /* ── CARD ────────────────────────────────────── */
        .card {
            background: var(--cream);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            overflow: hidden;
            border: 1px solid var(--mist);
        }

        /* ── CARD HEADER ─────────────────────────────── */
        .card-header {
            background: var(--navy);
            padding: 40px 44px 36px;
            position: relative;
            overflow: hidden;
        }

        .card-header::before {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--gold), var(--gold2), transparent);
        }

        /* Decorative corner element */
        .card-header::after {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(201,168,76,0.12) 0%, transparent 70%);
        }

        .header-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold2) 100%);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            box-shadow: 0 8px 24px rgba(201,168,76,0.30);
            position: relative;
            z-index: 1;
        }
        .header-icon i {
            font-size: 22px;
            color: var(--navy);
        }

        .card-header h1 {
            font-family: var(--font-display);
            font-size: 2.1rem;
            font-weight: 600;
            color: #fff;
            line-height: 1.15;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .card-header p {
            font-size: 0.88rem;
            color: rgba(255,255,255,0.62);
            line-height: 1.6;
            font-weight: 300;
            position: relative;
            z-index: 1;
            max-width: 320px;
        }

        /* ── CARD BODY ───────────────────────────────── */
        .card-body {
            padding: 40px 44px 44px;
        }

        /* ── FORM GROUP ──────────────────────────────── */
        .form-group {
            margin-bottom: 28px;
        }

        .form-label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.9px;
            text-transform: uppercase;
            color: var(--ash);
            margin-bottom: 10px;
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--mist);
            font-size: 15px;
            transition: color 0.2s;
            pointer-events: none;
        }

        input[type="email"] {
            width: 100%;
            padding: 15px 16px 15px 44px;
            background: #fff;
            border: 1.5px solid var(--mist);
            border-radius: var(--radius-md);
            font-family: var(--font-body);
            font-size: 0.95rem;
            color: var(--ink);
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
            appearance: none;
            -webkit-appearance: none;
        }

        input[type="email"]::placeholder { color: #C2BDB5; }

        input[type="email"]:focus {
            border-color: var(--navy2);
            box-shadow: 0 0 0 3.5px rgba(11,31,75,0.09);
        }

        input[type="email"]:focus + .input-icon-right {
            color: var(--navy);
        }

        /* Floating label trick for icon on focus */
        .input-wrap:focus-within .input-icon {
            color: var(--gold);
        }

        .form-hint {
            margin-top: 8px;
            font-size: 0.78rem;
            color: var(--ash);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .form-hint i { font-size: 11px; }

        /* ── BUTTON ──────────────────────────────────── */
        .btn-submit {
            width: 100%;
            padding: 16px 24px;
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy2) 100%);
            border: none;
            border-radius: var(--radius-md);
            color: #fff;
            font-family: var(--font-body);
            font-size: 0.92rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: transform 0.18s, box-shadow 0.18s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold2) 100%);
            opacity: 0;
            transition: opacity 0.25s;
        }

        .btn-submit:hover::before { opacity: 1; }
        .btn-submit:hover { color: var(--navy); box-shadow: var(--shadow-btn); transform: translateY(-2px); }
        .btn-submit:hover i { color: var(--navy); }
        .btn-submit:active { transform: translateY(0); box-shadow: none; }

        .btn-submit span, .btn-submit i { position: relative; z-index: 1; }
        .btn-submit i { font-size: 15px; transition: color 0.25s; }

        /* ── DIVIDER ─────────────────────────────────── */
        .divider {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 28px 0;
            color: var(--mist);
            font-size: 0.75rem;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--mist);
        }

        /* ── ALERTS ──────────────────────────────────── */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 13px;
            border-radius: var(--radius-md);
            padding: 16px 18px;
            font-size: 0.875rem;
            line-height: 1.55;
            margin-bottom: 24px;
            animation: alertIn 0.35s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @keyframes alertIn {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .alert-icon {
            flex-shrink: 0;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            margin-top: 1px;
        }

        .alert-error {
            background: #FDF2F1;
            border: 1px solid #F0D0CE;
        }
        .alert-error .alert-icon { background: #FBDEDB; color: var(--danger); }
        .alert-error strong { color: var(--danger); display: block; margin-bottom: 3px; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; }

        .alert-success {
            background: #F0F9F4;
            border: 1px solid #C3E6D2;
        }
        .alert-success .alert-icon { background: #D4EDDF; color: var(--ok); }
        .alert-success strong { color: var(--ok); display: block; margin-bottom: 3px; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; }

        .alert p { color: var(--ash); }

        /* ── FOOTER LINKS ────────────────────────────── */
        .card-footer {
            padding: 24px 44px 36px;
            background: var(--smoke);
            border-top: 1px solid var(--mist);
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .nav-link {
            flex: 1;
            min-width: 140px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 16px;
            border: 1.5px solid var(--mist);
            border-radius: var(--radius-sm);
            background: #fff;
            text-decoration: none;
            color: var(--ash);
            font-size: 0.82rem;
            font-weight: 500;
            transition: border-color 0.18s, color 0.18s, box-shadow 0.18s;
        }
        .nav-link:hover {
            border-color: var(--navy);
            color: var(--navy);
            box-shadow: 0 4px 12px rgba(11,31,75,0.08);
        }
        .nav-link i { font-size: 13px; }

        /* ── FOOTER ──────────────────────────────────── */
        .site-footer {
            margin-top: 32px;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        .site-footer p {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.28);
            line-height: 1.8;
        }
        .site-footer a {
            color: rgba(201,168,76,0.6);
            text-decoration: none;
            transition: color 0.18s;
        }
        .site-footer a:hover { color: var(--gold2); }
        .site-footer .sep { margin: 0 8px; opacity: 0.3; }

        /* ── SECURITY INDICATOR ──────────────────────── */
        .security-strip {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 18px;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        .sec-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.7rem;
            color: rgba(255,255,255,0.30);
            letter-spacing: 0.5px;
        }
        .sec-item i { color: var(--gold); opacity: 0.6; font-size: 11px; }

        /* ── STEP INDICATOR ──────────────────────────── */
        .steps {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0;
            margin-bottom: 28px;
        }
        .step-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--mist);
        }
        .step-dot.active { background: var(--navy); width: 24px; border-radius: 4px; }
        .step-line {
            width: 32px; height: 1px;
            background: var(--mist);
        }

        /* ── CONFETTI CANVAS ─────────────────────────── */
        #confetti-canvas {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 9999;
        }

        /* ── RESPONSIVE ──────────────────────────────── */
        @media (max-width: 520px) {
            .card-header { padding: 32px 28px 28px; }
            .card-body   { padding: 32px 28px 36px; }
            .card-footer { padding: 20px 28px 28px; }
            .card-header h1 { font-size: 1.75rem; }
            .nav-link { min-width: 100%; }
        }
    </style>
</head>
<body>

<!-- Floating orbs -->
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<div class="orb orb-3"></div>

<!-- Confetti canvas -->
<canvas id="confetti-canvas"></canvas>

<div class="wrapper">

    <!-- Gov badge -->
    <div class="badge-gov">
        <div class="seal">K</div>
        <span>e-Noteria &mdash; Republika e Kosovës</span>
    </div>

    <!-- Card -->
    <div class="card">

        <!-- Header -->
        <div class="card-header">
            <div class="header-icon">
                <i class="fas fa-key"></i>
            </div>
            <h1>Harruat<br>Fjalëkalimin?</h1>
            <p>Shkruajeni adresën e emailit dhe do t'ju dërgojmë instruksionet e rivendosjes menjëherë.</p>
        </div>

        <!-- Body -->
        <div class="card-body">

            <!-- Step indicator -->
            <div class="steps">
                <div class="step-dot active"></div>
                <div class="step-line"></div>
                <div class="step-dot"></div>
                <div class="step-line"></div>
                <div class="step-dot"></div>
            </div>

            <!-- Alerts -->
            <?php if ($error): ?>
            <div class="alert alert-error" role="alert">
                <div class="alert-icon"><i class="fas fa-exclamation"></i></div>
                <div>
                    <strong>Gabim</strong>
                    <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success" role="status" id="success-alert">
                <div class="alert-icon"><i class="fas fa-check"></i></div>
                <div>
                    <strong>U dërgua!</strong>
                    <p><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" action="" novalidate id="reset-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                <div class="form-group">
                    <label class="form-label" for="email">Adresa e Email-it</label>
                    <div class="input-wrap">
                        <i class="fas fa-envelope input-icon"></i>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="emri@shembull.com"
                            required
                            autocomplete="email"
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                            aria-label="Emaili për rivendosje"
                        >
                    </div>
                    <p class="form-hint">
                        <i class="fas fa-circle-info"></i>
                        Përdorni emailin e regjistruar në platformë.
                    </p>
                </div>

                <button type="submit" class="btn-submit" id="submit-btn">
                    <i class="fas fa-paper-plane"></i>
                    <span>Dërgo Linkun e Rivendosjes</span>
                </button>
            </form>
        </div>

        <!-- Footer nav links -->
        <div class="card-footer">
            <a href="login.php" class="nav-link">
                <i class="fas fa-arrow-left"></i>
                Kthehu te Kyçja
            </a>
            <a href="register.php" class="nav-link">
                <i class="fas fa-user-plus"></i>
                Krijo Llogari
            </a>
        </div>
    </div>

    <!-- Site footer -->
    <div class="site-footer">
        <div class="security-strip">
            <div class="sec-item"><i class="fas fa-shield-halved"></i> SSL 256-bit</div>
            <div class="sec-item"><i class="fas fa-lock"></i> CSRF Protected</div>
            <div class="sec-item"><i class="fas fa-clock"></i> Rate Limited</div>
        </div>
        <p style="margin-top:16px;">
            <a href="terms.php">Kushtet e Përdorimit</a>
            <span class="sep">·</span>
            <a href="Privatesia.php">Privatësia</a>
            <span class="sep">·</span>
            <a href="ndihma.php">Ndihma</a>
        </p>
        <p>&copy; <?php echo date('Y'); ?> e-Noteria &mdash; Republika e Kosovës</p>
    </div>

</div><!-- /.wrapper -->

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── CONFETTI on success ───────────────────────────
    var successAlert = document.getElementById('success-alert');
    if (successAlert && typeof confetti === 'function') {
        setTimeout(function () {
            confetti({
                particleCount: 90,
                spread: 70,
                startVelocity: 38,
                gravity: 0.9,
                origin: { y: 0.28 },
                colors: ['#0B1F4B', '#C9A84C', '#E8C97A', '#ffffff', '#FAF7F2']
            });
        }, 350);
    }

    // ── BUTTON loading state ──────────────────────────
    var form      = document.getElementById('reset-form');
    var submitBtn = document.getElementById('submit-btn');

    if (form && submitBtn) {
        form.addEventListener('submit', function () {
            var emailVal = document.getElementById('email').value.trim();
            if (!emailVal) return;

            submitBtn.disabled = true;
            submitBtn.innerHTML =
                '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin 0.8s linear infinite;flex-shrink:0;">' +
                '<circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>' +
                '<path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>' +
                '</svg>' +
                '<span>Duke dërguar...</span>';

            // Fallback re-enable
            setTimeout(function () {
                if (submitBtn.disabled) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i><span>Dërgo Linkun e Rivendosjes</span>';
                }
            }, 12000);
        });
    }

    // ── Spin keyframe via JS (avoids extra <style>) ───
    var style = document.createElement('style');
    style.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
    document.head.appendChild(style);

    // ── Email input visual validation hint ───────────
    var emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.addEventListener('blur', function () {
            var val = this.value.trim();
            var valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
            this.style.borderColor = val === '' ? '' : (valid ? '#1A6B4A' : '#C0392B');
        });
        emailInput.addEventListener('input', function () {
            this.style.borderColor = '';
        });
    }
});
</script>

</body>
</html>
