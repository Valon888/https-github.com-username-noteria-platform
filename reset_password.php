<?php
/**
 * Faqja për rivendosjen e fjalëkalimit (Reset Password)
 * 
 * Funksionalitete:
 * - Validim token sigurie
 * - Kontrollim validiteti të token-it (1 orë)
 * - Gjenero fjalëkalim të ri me hashing
 * - Audit logging
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

session_start();
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

if (empty($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = null;
$success = null;
$token_valid = false;
$user_id = null;

require_once 'confidb.php';

// Merr token nga URL
$token = isset($_GET['token']) ? trim($_GET['token']) : null;

if (!$token) {
    $error = 'Token nuk u gjet. Linku nuk është i vlefshëm.';
} else {
    try {
        // Kontrolloni token
        $stmt = $pdo->prepare('
            SELECT id FROM users 
            WHERE reset_token = ? 
            AND reset_expires > NOW()
            LIMIT 1
        ');
        $stmt->execute(array($token));
        $user = $stmt->fetch();
        
        if ($user) {
            $token_valid = true;
            $user_id = $user['id'];
        } else {
            $error = 'Linku nuk është i vlefshëm ose ka skaduar (1 orë).';
        }
    } catch (Exception $e) {
        error_log('Reset password token error: ' . $e->getMessage());
        $error = 'Gabim në sistem.';
    }
}

// Procesim formularit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid && $user_id) {
    if (empty($_POST['csrf_token'] ?? null) || ($_POST['csrf_token'] ?? null) !== $_SESSION['csrf_token']) {
        $error = 'Gabim sigurimi. Provo përsëri.';
    } else {
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        
        // Validim fjalëkalim
        if (strlen($password) < 6) {
            $error = 'Fjalëkalimi duhet të ketë të paktën 6 karaktere.';
        } elseif ($password !== $password_confirm) {
            $error = 'Fjalëkalimet nuk përputhen.';
        } else {
            try {
                // Hash fjalëkalimi
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                
                // Përditëso fjalëkalimin
                $stmt = $pdo->prepare('
                    UPDATE users 
                    SET password = ?, reset_token = NULL, reset_expires = NULL 
                    WHERE id = ?
                ');
                $stmt->execute(array($hashed_password, $user_id));
                
                // Regjistro në audit log
                log_password_reset($pdo, $user_id, 'success');
                
                $success = 'Fjalëkalimi u ndryshua me sukses. Mund të kyçesh tani.';
                $token_valid = false; // Fshih formularin pas suksesit
                
            } catch (Exception $e) {
                error_log('Reset password error: ' . $e->getMessage());
                $error = 'Gabim në sistem.';
                log_password_reset($pdo, $user_id, 'failed');
            }
        }
    }
}

// Gjenero CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function log_password_reset($pdo, $user_id, $status) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt = $pdo->prepare('
            INSERT INTO audit_log (user_id, action, details, ip_address) 
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute(array($user_id, 'Password Reset', "Status: $status", $ip));
    } catch (Exception $e) {
        error_log('Audit log error: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ndryshimi i Fjalëkalimit | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 420px;
            width: 100%;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h2 {
            color: #2d3748;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .header p {
            color: #718096;
            font-size: 14px;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #2d3748;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: #f7fafc;
            transition: all 0.3s ease;
        }

        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        button[type="submit"] {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 14px;
            line-height: 1.6;
        }

        .alert-error {
            background: #fed7d7;
            color: #c53030;
            border-left: 4px solid #c53030;
        }

        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border-left: 4px solid #22543d;
        }

        .back-link {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        .help-text {
            color: #718096;
            font-size: 13px;
            margin-top: 8px;
        }

        .password-strength {
            margin-top: 8px;
            height: 4px;
            border-radius: 2px;
            background: #e2e8f0;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            background: #22543d;
            transition: all 0.3s ease;
        }

        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }

            .header h2 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>🔑 Ndryshimi i Fjalëkalimit</h2>
            <p>Krijoni një fjalëkalim të ri dhe të sigurt.</p>
        </div>

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

        <?php if ($token_valid && $user_id): ?>
            <form method="POST" action="" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Fjalëkalim i Ri:
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Minimumi 6 karaktere" 
                        required 
                        autocomplete="new-password"
                        onkeyup="checkPasswordStrength(this.value)"
                    >
                    <div class="password-strength">
                        <div class="password-strength-bar" id="strength-bar"></div>
                    </div>
                    <div class="help-text">Përdor kombinim të shkronjave, numrave dhe karaktereve speciale.</div>
                </div>

                <div class="form-group">
                    <label for="password_confirm">
                        <i class="fas fa-lock"></i> Konfirmo Fjalëkalimin:
                    </label>
                    <input 
                        type="password" 
                        id="password_confirm" 
                        name="password_confirm" 
                        placeholder="Përsërit fjalëkalimin" 
                        required 
                        autocomplete="new-password"
                    >
                </div>

                <button type="submit">
                    <i class="fas fa-save"></i> Ndrysho Fjalëkalimin
                </button>
            </form>
        <?php endif; ?>

        <div class="back-link">
            <p>
                <a href="login.php"><i class="fas fa-arrow-left"></i> Kthehu në Kyçje</a>
            </p>
        </div>
    </div>

    <script>
        function checkPasswordStrength(password) {
            let strength = 0;
            const bar = document.getElementById('strength-bar');
            
            if (password.length >= 6) strength += 20;
            if (password.length >= 10) strength += 20;
            if (/[a-z]/.test(password)) strength += 20;
            if (/[A-Z]/.test(password)) strength += 20;
            if (/[0-9]/.test(password)) strength += 20;
            if (/[^a-zA-Z0-9]/.test(password)) strength += 20;
            
            bar.style.width = Math.min(strength, 100) + '%';
            
            if (strength < 40) {
                bar.style.background = '#c53030';
            } else if (strength < 80) {
                bar.style.background = '#ed8936';
            } else {
                bar.style.background = '#22543d';
            }
        }
    </script>
</body>
</html>