<?php
/**
 * MFA Verification Page
 * Verifikimi i dy-faktorëve për kyçje
 */

session_start();
require_once 'confidb.php';
require_once 'mfa_helper.php';
require_once 'activity_logger.php';

// Kontrollo nëse përdoruesi është duke verifikuar MFA
if (!isset($_SESSION['mfa_pending']) || !isset($_SESSION['temp_user_data'])) {
    header("Location: login.php");
    exit();
}

$error = null;
$success = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
        $error = "Veprimi i paautorizuar!";
    } else {
        $mfa_code = trim($_POST['mfa_code'] ?? '');
        $use_backup = isset($_POST['use_backup']);

        $temp_data = $_SESSION['temp_user_data'];
        $user_type = $temp_data['user_type'];
        $user_id = $temp_data['user_id'];

        $verified = false;

        if ($use_backup && !empty($mfa_code)) {
            // Verifiko backup code
            if ($user_type === 'admin') {
                $verified = verifyAndConsumeAdminBackupCode($pdo, $user_id, $mfa_code);
            } else {
                $verified = verifyAndConsumeBackupCode($pdo, $user_id, $mfa_code);
            }

            if ($verified) {
                $success = "Backup code u verifikua me sukses!";
                log_activity($pdo, $user_id, 'MFA Backup Code', 'Backup code u përdor për kyçje');
            } else {
                $error = "Backup code i pavlefshëm!";
            }
        } elseif (!empty($mfa_code)) {
            // Verifiko TOTP code
            if ($user_type === 'admin') {
                $verified = verifyAdminMFA($pdo, $user_id, $mfa_code);
            } else {
                $verified = verifyUserMFA($pdo, $user_id, $mfa_code);
            }

            if ($verified) {
                $success = "Kodi TOTP u verifikua me sukses!";
                log_activity($pdo, $user_id, 'MFA TOTP', 'TOTP code u verifikua për kyçje');
            } else {
                $error = "Kodi TOTP është i pavlefshëm!";
            }
        } else {
            $error = "Ju lutem shkruani kodin MFA!";
        }

        if ($verified) {
            // Transfer session data
            foreach ($temp_data as $key => $value) {
                if ($key !== 'user_type' && $key !== 'user_id') {
                    $_SESSION[$key] = $value;
                }
            }

            $_SESSION['mfa_verified'] = true;
            $_SESSION['last_activity'] = time();

            // Clear temporary data
            unset($_SESSION['mfa_pending']);
            unset($_SESSION['temp_user_data']);

            // Redirect based on role
            if ($temp_data['roli'] === 'admin') {
                header("Location: admin_dashboard.php");
            } elseif ($temp_data['roli'] === 'notary') {
                header("Location: dashboard.php");
            } else {
                header("Location: billing_dashboard.php");
            }
            exit();
        }
    }
}

$temp_data = $_SESSION['temp_user_data'];
$user_email = $temp_data['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikimi 2FA | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 15px;
        }

        .mfa-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }

        .mfa-header {
            margin-bottom: 30px;
        }

        .mfa-header h2 {
            color: #333;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .mfa-header p {
            color: #666;
            font-size: 16px;
        }

        .microsoft-authenticator {
            background: #0078d4;
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .microsoft-authenticator h3 {
            font-size: 18px;
            margin-bottom: 10px;
        }

        .microsoft-authenticator p {
            font-size: 14px;
            opacity: 0.9;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input[type="text"] {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 18px;
            text-align: center;
            letter-spacing: 2px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .form-group input[type="text"]:focus {
            border-color: #0078d4;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 120, 212, 0.1);
        }

        .backup-toggle {
            margin: 20px 0;
            text-align: center;
        }

        .backup-toggle label {
            color: #666;
            cursor: pointer;
            text-decoration: underline;
        }

        .backup-info {
            display: none;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            text-align: left;
        }

        .backup-info.show {
            display: block;
        }

        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0078d4 0%, #005ba1 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 120, 212, 0.3);
        }

        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #fcc;
        }

        .success-message {
            background: #efe;
            color: #363;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #cfc;
        }

        .back-link {
            margin-top: 20px;
            text-align: center;
        }

        .back-link a {
            color: #0078d4;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .mfa-container {
                padding: 30px 20px;
            }

            .mfa-header h2 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="mfa-container">
        <div class="mfa-header">
            <h2><i class="fas fa-shield-alt"></i> Verifikimi 2-Faktor</h2>
            <p>Shkruani kodin nga Microsoft Authenticator</p>
        </div>

        <div class="microsoft-authenticator">
            <h3><i class="fab fa-microsoft"></i> Microsoft Authenticator</h3>
            <p>Hapni aplikacionin dhe shkruani kodin 6-shifror</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

            <div class="form-group">
                <label for="mfa_code">
                    <i class="fas fa-key"></i> Kodi 6-shifror
                </label>
                <input type="text" id="mfa_code" name="mfa_code" maxlength="6"
                       placeholder="000000" pattern="[0-9]{6}" required
                       autocomplete="one-time-code">
            </div>

            <div class="backup-toggle">
                <label for="use_backup">
                    <input type="checkbox" id="use_backup" name="use_backup">
                    Përdor kod backup
                </label>
            </div>

            <div class="backup-info" id="backup-info">
                <strong><i class="fas fa-info-circle"></i> Kodi Backup:</strong><br>
                Nëse nuk keni akses në aplikacionin, përdorni një nga kodet backup që keni ruajtur gjatë konfigurimit.
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-check"></i> Verifiko
            </button>
        </form>

        <div class="back-link">
            <a href="login.php">
                <i class="fas fa-arrow-left"></i> Kthehu në kyçje
            </a>
        </div>
    </div>

    <script>
        // Toggle backup info
        document.getElementById('use_backup').addEventListener('change', function() {
            const backupInfo = document.getElementById('backup-info');
            if (this.checked) {
                backupInfo.classList.add('show');
                document.getElementById('mfa_code').placeholder = 'ABC123';
                document.getElementById('mfa_code').pattern = '[A-Za-z0-9]{6}';
                document.getElementById('mfa_code').maxLength = 8;
            } else {
                backupInfo.classList.remove('show');
                document.getElementById('mfa_code').placeholder = '000000';
                document.getElementById('mfa_code').pattern = '[0-9]{6}';
                document.getElementById('mfa_code').maxLength = 6;
            }
        });

        // Auto-focus input
        document.getElementById('mfa_code').focus();

        // Auto-submit on 6 digits for TOTP
        document.getElementById('mfa_code').addEventListener('input', function() {
            if (!document.getElementById('use_backup').checked && this.value.length === 6) {
                this.form.submit();
            }
        });
    </script>
</body>
</html>
