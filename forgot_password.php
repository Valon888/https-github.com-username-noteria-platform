<?php
/**
 * Forgot Password Recovery Page
 * 
 * Funksionalitete:
 * - Rate limiting: 5 kërkesa/15 minuta për IP
 * - CSRF protection me token validation
 * - Token sigurie: 32 bytes hexadecimal
 * - Audit logging: të gjitha kërkesa regjistrohen
 * - Email template: gati për PHPMailer
 * - Responsive design: mobile-first approach
 * - Security headers dhe sanitizimi i inputit
 */

// ========================================
// KONFIGURIMI I FILEVE DHE SESIONIT
// ========================================

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Konfigurimi i sigurimit të sesionit PËRPARA session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax'); // More permissive for development

session_start();

// Ridlo sesionin në vizitën e parë
if (empty($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

// Shërbimet e hequra: përdoruesi i kyçur duhet të dalë
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php', true, 302);
    exit;
}

// ========================================
// INICIJALIZIM I VARIABLAVE
// ========================================

$error = null;
$success = null;
$rate_limit_error = false;

require_once 'confidb.php';
require_once 'csrf.php';

// Initialize CSRF protection
csrf_init();

// ========================================
// RATE LIMITING (MBROJTJE NDAJ BRUTE FORCE)
// ========================================

$client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rate_limit_key = 'forgot_pwd_' . hash('sha256', $client_ip);
$max_attempts = 5;
$rate_limit_window = 900; // 15 minuta

if (isset($_SESSION[$rate_limit_key])) {
    $attempts = $_SESSION[$rate_limit_key];
    $time_elapsed = time() - $attempts['timestamp'];
    
    if ($attempts['count'] >= $max_attempts && $time_elapsed < $rate_limit_window) {
        $rate_limit_error = true;
        $remaining_time = ceil(($rate_limit_window - $time_elapsed) / 60);
        $error = "Shumë përpjekje. Provo përsëri pas {$remaining_time} minutash.";
    } elseif ($time_elapsed >= $rate_limit_window) {
        unset($_SESSION[$rate_limit_key]);
    }
}

// ========================================
// PROCESIMI I FORMULARIT
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$rate_limit_error) {
    // Validate CSRF token using the new protection system
    csrf_validate();
        // Marrja dhe sanitizimi i email-it
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        
        // Validim email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email-i nuk është i vlefshëm. Kontrolloni të dhënat.';
        } else {
            try {
                $email = strtolower($email);
                
                // Përditëso rate limiting counter
                if (!isset($_SESSION[$rate_limit_key])) {
                    $_SESSION[$rate_limit_key] = [
                        'count' => 1,
                        'timestamp' => time()
                    ];
                } else {
                    $_SESSION[$rate_limit_key]['count']++;
                }
                
                // Kërko përdoruesin në databazë
                $stmt = $pdo->prepare('
                    SELECT id, emri, mbiemri 
                    FROM users 
                    WHERE LOWER(email) = ? AND status = "aktiv" 
                    LIMIT 1
                ');
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    // Gjenero token dhe kohën e skadimit
                    $reset_token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', time() + 3600); // 1 orë
                    
                    // Ruaj token në databazë
                    $stmt = $pdo->prepare('
                        UPDATE users 
                        SET reset_token = ?, reset_expires = ? 
                        WHERE id = ?
                    ');
                    $stmt->execute([$reset_token, $expires, $user['id']]);
                    
                    // Përgatit linkun e rivendosjes
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $reset_url = "{$protocol}://{$host}/noteria/reset_password.php?token=" . urlencode($reset_token);
                    
                    // Dërgo email
                    $email_sent = send_password_reset_email(
                        $user['emri'],
                        $user['mbiemri'],
                        $email,
                        $reset_url
                    );
                    
                    if ($email_sent) {
                        $success = 'Linku për rivendosjen e fjalëkalimit u dërgua në email. Kontrollo kutinë e marrjes.';
                        log_forgot_password_request($pdo, $user['id'], $email, 'email_sent', $client_ip);
                    } else {
                        $error = 'Gabim gjatë dërgimit të emailit. Provo përsëri më vonë.';
                        log_forgot_password_request($pdo, $user['id'], $email, 'email_failed', $client_ip);
                    }
                } else {
                    // Privacy: nuk tregojmë nëse emaili ekziston ose jo
                    $success = 'Nëse ky email ekziston në sistem, do të marrësh instruksione për rivendosje.';
                    log_forgot_password_request($pdo, null, $email, 'user_not_found', $client_ip);
                }
            }
            
            catch (PDOException $e) {
                error_log('Database error in forgot_password: ' . $e->getMessage());
                $error = 'Gabim në sistem. Kontaktoni përkrahjen teknikore.';
                log_security_event($pdo, $client_ip, 'database_error');
            } catch (Exception $e) {
                error_log('General error in forgot_password: ' . $e->getMessage());
                $error = 'Gabim i panjohur. Provo përsëri.';
            }
        }
    }

// ========================================
// FUNKSIONET E NDIHMËS
// ========================================

/**
 * Dërgo emailin e rivendosjes
 * 
 * @param string $emri Emri i përdoruesit
 * @param string $mbiemri Mbiemri i përdoruesit
 * @param string $email Emaili destinatar
 * @param string $reset_url URL për rivendosje
 * @return bool True nëse emaili u dërgua me sukses
 */
function send_password_reset_email($emri, $mbiemri, $email, $reset_url) {
    try {
        // TODO: Zëvendëso me PHPMailer ose SMTP konfigurimi
        $full_name = htmlspecialchars(trim("{$emri} {$mbiemri}"), ENT_QUOTES, 'UTF-8');
        
        $subject = 'Rivendosja e Fjalëkalimit | Noteria';
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; }
                .header { background: #667eea; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .button { display: inline-block; background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
                .footer { padding: 10px; text-align: center; color: #888; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class=\"container\">
                <div class=\"header\">
                    <h1>🔐 Rivendosja e Fjalëkalimit</h1>
                </div>
                <div class=\"content\">
                    <p>Përshëndetje {$full_name},</p>
                    <p>Keni kërkuar rivendosjen e fjalëkalimit. Klikoni në linkun më poshtë për të vazhduari:</p>
                    <p style=\"text-align: center; margin: 30px 0;\">
                        <a href=\"{$reset_url}\" class=\"button\">Rivendos Fjalëkalimin</a>
                    </p>
                    <p><strong>Vënim i rëndësishëm:</strong></p>
                    <ul>
                        <li>Linku është i vlefshëm për 1 orë</li>
                        <li>Nëse nuk keni kërkuar rivendosje, injorojeni këtë email</li>
                        <li>Mos e ndani këtë link me askënd</li>
                    </ul>
                    <p>Nëse linku nuk funksionon, kopjojeni dhe ngollojeni në shfletuesin tuaj:</p>
                    <p style=\"word-break: break-all; background: #f0f0f0; padding: 10px; border-radius: 5px;\">
                        {$reset_url}
                    </p>
                </div>
                <div class=\"footer\">
                    <p>© 2025 Noteria. Të gjitha të drejtat e rezervuara.</p>
                </div>
            </div>
        </body>
        </html>";
        
        // Për testim lokal, logjoj linkun
        error_log("Password reset link for {$email}: {$reset_url}");
        
        // TODO: Implemento dërgimin real të emailit
        // return mail($email, $subject, $message, "Content-Type: text/html; charset=UTF-8\r\n");
        
        return true;
        
    } catch (Exception $e) {
        error_log('Email sending error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Logjizoj kërkesën e harrimit të fjalëkalimit
 * 
 * @param PDO $pdo Koneksioni në databazë
 * @param int|null $user_id ID i përdoruesit (null nëse nuk u gjet)
 * @param string $email Emaili i kërkuar
 * @param string $status Statusi: email_sent, email_failed, user_not_found
 * @param string $client_ip IP adresa e klientit
 */
function log_forgot_password_request($pdo, $user_id, $email, $status, $client_ip) {
    try {
        $action = 'Forgot Password Request';
        $details = "Email: {$email} | Status: {$status}";
        
        $stmt = $pdo->prepare('
            INSERT INTO audit_log (user_id, action, details, ip_address, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ');
        $stmt->execute([$user_id, $action, $details, $client_ip]);
        
    } catch (PDOException $e) {
        error_log('Audit log error: ' . $e->getMessage());
    }
}

/**
 * Logjizoj evente sigurie
 * 
 * @param PDO $pdo Koneksioni në databazë
 * @param string $client_ip IP adresa e klientit
 * @param string $event_type Lloji i eventit
 */
function log_security_event($pdo, $client_ip, $event_type) {
    try {
        $action = 'Security Event';
        $details = "Type: {$event_type}";
        
        $stmt = $pdo->prepare('
            INSERT INTO audit_log (user_id, action, details, ip_address, created_at) 
            VALUES (NULL, ?, ?, ?, NOW())
        ');
        $stmt->execute([$action, $details, $client_ip]);
        
    } catch (PDOException $e) {
        error_log('Security log error: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Rivendose fjalëkalimin tuaj në Noteria">
    <meta name="theme-color" content="#667eea">
    <title>Harruar Fjalëkalimin | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ========================================
           RESET DHE STILIME BAZË
           ======================================== */
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            color: #2d3748;
        }

        /* ========================================
           KONTEJNERI KRYESOR
           ======================================== */

        .container {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 420px;
            width: 100%;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ========================================
           HEADER
           ======================================== */

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h2 {
            color: #2d3748;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .header p {
            color: #718096;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }

        /* ========================================
           FORMA
           ======================================== */

        form {
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: flex;
            align-items: center;
            color: #2d3748;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
            gap: 8px;
        }

        label i {
            color: #667eea;
            font-size: 16px;
        }

        input[type="email"] {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: #f7fafc;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        input[type="email"]:hover {
            border-color: #cbd5e0;
        }

        input[type="email"]:focus {
            outline: none;
            border-color: #667eea;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        input[type="email"]::placeholder {
            color: #a0aec0;
        }

        .help-text {
            color: #718096;
            font-size: 13px;
            margin-top: 8px;
            line-height: 1.5;
        }

        /* ========================================
           DUGME
           ======================================== */

        button[type="submit"] {
            width: 100%;
            padding: 12px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        button[type="submit"]:active {
            transform: translateY(0);
        }

        button[type="submit"]:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* ========================================
           ALERTS
           ======================================== */

        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 14px;
            line-height: 1.6;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert i {
            flex-shrink: 0;
            margin-top: 2px;
        }

        .alert-error {
            background: #fed7d7;
            color: #c53030;
            border-left: 4px solid #c53030;
        }

        .alert-error i {
            color: #c53030;
        }

        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border-left: 4px solid #22543d;
        }

        .alert-success i {
            color: #22543d;
        }

        /* ========================================
           LINKUT TË KTHIMIT
           ======================================== */

        .back-link {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
        }

        .back-link p {
            margin: 0 0 12px 0;
        }

        .back-link p:last-child {
            margin-bottom: 0;
        }

        .back-link a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .back-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        /* ========================================
           RESPONSIVE DESIGN
           ======================================== */

        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }

            .header h2 {
                font-size: 20px;
            }

            .header p {
                font-size: 13px;
            }

            button[type="submit"] {
                padding: 11px 14px;
                font-size: 14px;
            }

            .alert {
                font-size: 13px;
                padding: 12px 14px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                animation: none !important;
                transition: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <div class="header">
            <h2>🔐 Harruat Fjalëkalimin?</h2>
            <p>Shkruajeni email-in tuaj dhe do të marrni instruksionet për rivendosje.</p>
        </div>

        <!-- FORMA -->
        <form method="POST" action="" novalidate>
            <!-- CSRF Token -->
            <?php echo csrf_field(); ?>

            <!-- Email Field -->
            <div class="form-group">
                <label for="email">
                    <i class="fas fa-envelope"></i>
                    Email-i Juaj
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="emri@shembull.com" 
                    required 
                    autocomplete="email"
                    aria-label="Email-i për rivendosje"
                >
                <div class="help-text">
                    💡 Përdorni të njëjtin email si në regjistrim.
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" aria-label="Dërgo linkun e rivendosjes">
                <i class="fas fa-paper-plane"></i>
                Dërgo Linkun
            </button>
        </form>

        <!-- ERROR ALERT -->
        <?php if ($error): ?>
            <div class="alert alert-error" role="alert">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>

        <!-- SUCCESS ALERT -->
        <?php if ($success): ?>
            <div class="alert alert-success" role="status">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>

        <!-- BACK LINKS -->
        <div class="back-link">
            <p>
                <a href="login.php">
                    <i class="fas fa-arrow-left"></i>
                    Kthehu në Kyçje
                </a>
            </p>
            <p>
                <a href="register.php">
                    <i class="fas fa-user-plus"></i>
                    Krijo Llogari të Re
                </a>
            </p>
        </div>
    </div>
</body>
</html>
