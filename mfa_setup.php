<?php
/**
 * MFA Setup Page
 * Konfigurimi i Microsoft Authenticator për 2FA
 */

session_start();
require_once 'confidb.php';
require_once 'mfa_helper.php';
require_once 'activity_logger.php';

// Kontrollo nëse përdoruesi është i kyçur
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = isset($_SESSION['admin_id']) ? 'admin' : 'user';
$error = null;
$success = null;
$mfa_data = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
        $error = "Veprimi i paautorizuar!";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'setup') {
            // Setup MFA
            try {
                if ($user_type === 'admin') {
                    $mfa_data = setupAdminMFA($pdo, $_SESSION['admin_id']);
                } else {
                    $mfa_data = setupUserMFA($pdo, $user_id);
                }
                $success = "Konfigurimi MFA filloi! Skano QR kodin me Microsoft Authenticator.";
                log_activity($pdo, $user_id, 'MFA Setup', 'Filloi konfigurimin e MFA');
            } catch (Exception $e) {
                $error = "Gabim gjatë konfigurimit: " . $e->getMessage();
            }
        } elseif ($action === 'verify') {
            // Verify setup
            $verification_code = trim($_POST['verification_code'] ?? '');

            if (empty($verification_code)) {
                $error = "Ju lutem shkruani kodin e verifikimit!";
            } else {
                $verified = false;
                if ($user_type === 'admin') {
                    $verified = verifyAdminMFA($pdo, $_SESSION['admin_id'], $verification_code);
                } else {
                    $verified = verifyUserMFA($pdo, $user_id, $verification_code);
                }

                if ($verified) {
                    // Mark as verified
                    $table = $user_type === 'admin' ? 'admin_mfa' : 'user_mfa';
                    $id_field = $user_type === 'admin' ? 'admin_id' : 'user_id';
                    $id_value = $user_type === 'admin' ? $_SESSION['admin_id'] : $user_id;

                    $update_stmt = $pdo->prepare("UPDATE $table SET is_verified = 1, verified_at = NOW() WHERE $id_field = ?");
                    $update_stmt->execute([$id_value]);

                    $success = "MFA u aktivizua me sukses! Tani do të kërkohet kod verifikimi gjatë kyçjes.";
                    log_activity($pdo, $user_id, 'MFA Enabled', 'MFA u aktivizua për llogarinë');
                } else {
                    $error = "Kodi i verifikimit është i pavlefshëm!";
                }
            }
        } elseif ($action === 'disable') {
            // Disable MFA
            $table = $user_type === 'admin' ? 'admin_mfa' : 'user_mfa';
            $id_field = $user_type === 'admin' ? 'admin_id' : 'user_id';
            $id_value = $user_type === 'admin' ? $_SESSION['admin_id'] : $user_id;

            $delete_stmt = $pdo->prepare("DELETE FROM $table WHERE $id_field = ?");
            $delete_stmt->execute([$id_value]);

            $success = "MFA u çaktivizua. Llogaria juaj tani është më pak e sigurt.";
            log_activity($pdo, $user_id, 'MFA Disabled', 'MFA u çaktivizua për llogarinë');
        }
    }
}

// Check current MFA status
$has_mfa = false;
if ($user_type === 'admin') {
    $has_mfa = adminHasMFA($pdo, $_SESSION['admin_id']);
} else {
    $has_mfa = userHasMFA($pdo, $user_id);
}

// Get user email for display
$user_email = $_SESSION['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Konfigurimi 2FA | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
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
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header h1 {
            color: #333;
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 18px;
        }

        .status-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
        }

        .status-card.disabled {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
        }

        .status-card h3 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .setup-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .setup-section h3 {
            color: #333;
            font-size: 22px;
            margin-bottom: 20px;
            text-align: center;
        }

        .qr-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .qr-code {
            border: 5px solid #0078d4;
            border-radius: 10px;
            padding: 10px;
            background: white;
            display: inline-block;
            margin-bottom: 20px;
        }

        .manual-code {
            background: #e9ecef;
            padding: 15px;
            border-radius: 8px;
            font-family: monospace;
            word-break: break-all;
            margin-bottom: 20px;
        }

        .backup-codes {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }

        .backup-codes h4 {
            color: #856404;
            margin-bottom: 15px;
        }

        .backup-codes .codes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
        }

        .backup-codes .code {
            background: white;
            padding: 8px;
            border-radius: 4px;
            text-align: center;
            font-family: monospace;
            font-weight: bold;
            border: 1px solid #ddd;
        }

        .form-group {
            margin-bottom: 20px;
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
            font-size: 16px;
            text-align: center;
            letter-spacing: 2px;
            transition: all 0.3s ease;
        }

        .form-group input[type="text"]:focus {
            border-color: #0078d4;
            outline: none;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0078d4 0%, #005ba1 100%);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .error-message {
            background: #fee;
            color: #c33;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #fcc;
        }

        .success-message {
            background: #efe;
            color: #363;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #cfc;
        }

        .steps {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            border-left: 5px solid #0078d4;
        }

        .steps ol {
            padding-left: 20px;
        }

        .steps li {
            margin-bottom: 10px;
            color: #555;
        }

        .microsoft-info {
            background: #f0f8ff;
            border: 1px solid #b3d9ff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .microsoft-info h4 {
            color: #0078d4;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }

            .backup-codes .codes {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-shield-alt"></i> Konfigurimi 2-Faktor Authentication</h1>
            <p>Rrisni sigurinë e llogarisë suaj me Microsoft Authenticator</p>
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

        <div class="status-card <?php echo $has_mfa ? '' : 'disabled'; ?>">
            <h3>
                <i class="fas fa-<?php echo $has_mfa ? 'check-circle' : 'times-circle'; ?>"></i>
                <?php echo $has_mfa ? '2FA Aktiv' : '2FA Joaktiv'; ?>
            </h3>
            <p>
                <?php echo $has_mfa
                    ? 'Llogaria juaj është e mbrojtur me verifikim dy-faktor'
                    : 'Aktivizoni 2FA për siguri më të lartë'; ?>
            </p>
        </div>

        <?php if (!$has_mfa): ?>
            <div class="microsoft-info">
                <h4><i class="fab fa-microsoft"></i> Microsoft Authenticator</h4>
                <p>Microsoft Authenticator është aplikacioni zyrtar për verifikim dy-faktor nga Microsoft. Mund ta shkarkoni falas nga:</p>
                <ul>
                    <li><strong>App Store (iOS):</strong> Kërkoni "Microsoft Authenticator"</li>
                    <li><strong>Google Play (Android):</strong> Kërkoni "Microsoft Authenticator"</li>
                </ul>
            </div>

            <div class="steps">
                <h3>Hapat për Konfigurim:</h3>
                <ol>
                    <li>Shkarkoni dhe instaloni Microsoft Authenticator</li>
                    <li>Klikoni butonin "Konfiguro 2FA" më poshtë</li>
                    <li>Skano QR kodin me aplikacionin</li>
                    <li>Shkruani kodin 6-shifror që shfaqet në aplikacion</li>
                    <li>Ruani kodet backup në një vend të sigurt</li>
                </ol>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <input type="hidden" name="action" value="setup">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-qrcode"></i> Konfiguro 2FA
                </button>
            </form>
        <?php endif; ?>

        <?php if ($mfa_data): ?>
            <div class="setup-section">
                <h3>Konfigurimi i Microsoft Authenticator</h3>

                <div class="qr-container">
                    <p>Skano këtë QR kod me Microsoft Authenticator:</p>
                    <div class="qr-code">
                        <img src="<?php echo htmlspecialchars($mfa_data['qr_url']); ?>" alt="QR Code" style="width: 200px; height: 200px;">
                    </div>
                </div>

                <div class="manual-code">
                    <strong>Kodi për shtim manual:</strong><br>
                    <?php echo htmlspecialchars($mfa_data['manual_entry']); ?>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" name="action" value="verify">

                    <div class="form-group">
                        <label for="verification_code">
                            <i class="fas fa-key"></i> Kodi i verifikimit nga aplikacioni:
                        </label>
                        <input type="text" id="verification_code" name="verification_code"
                               maxlength="6" placeholder="000000" pattern="[0-9]{6}" required>
                    </div>

                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Verifiko dhe Aktivizo
                    </button>
                </form>

                <?php if (isset($mfa_data['backup_codes'])): ?>
                    <div class="backup-codes">
                        <h4><i class="fas fa-save"></i> Kodet Backup (Ruajini!)</h4>
                        <p>Këto kode mund t'i përdorni nëse humbni aksesin në aplikacion. Çdo kod përdoret vetëm një herë.</p>
                        <div class="codes">
                            <?php foreach ($mfa_data['backup_codes'] as $code): ?>
                                <div class="code"><?php echo htmlspecialchars($code); ?></div>
                            <?php endforeach; ?>
                        </div>
                        <p style="color: #856404; font-size: 14px; margin-top: 10px;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Ruajini këto kode në një vend të sigurt!</strong> Mund t'i printoni ose t'i ruani në një menaxher fjalëkalimesh.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($has_mfa): ?>
            <div class="setup-section">
                <h3>Çaktivizimi i 2FA</h3>
                <p style="color: #dc3545; text-align: center; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Kujdes:</strong> Çaktivizimi i 2FA do të zvogëlojë sigurinë e llogarisë suaj.
                </p>

                <form method="POST" action="" onsubmit="return confirm('Jeni të sigurt që doni të çaktivizoni 2FA?')">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" name="action" value="disable">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times"></i> Çaktivizo 2FA
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="<?php echo $user_type === 'admin' ? 'admin_dashboard.php' : 'dashboard.php'; ?>" class="btn">
                <i class="fas fa-arrow-left"></i> Kthehu në Dashboard
            </a>
        </div>
    </div>

    <script>
        // Auto-focus verification input if it exists
        const verificationInput = document.getElementById('verification_code');
        if (verificationInput) {
            verificationInput.focus();
        }
    </script>
</body>
</html>
