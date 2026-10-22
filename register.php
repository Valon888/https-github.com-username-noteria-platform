
<?php
// Fix: shmang gabimet nëse REQUEST_METHOD nuk është i caktuar
if (!isset($_SERVER["REQUEST_METHOD"])) {
    $_SERVER["REQUEST_METHOD"] = null;
}
// ==================== KONFIGURIMI FILLESTAR ====================
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// session_start() DUHET të jetë i pari — vetëm një herë
session_start();
require_once 'config.php';
require_once 'confidb.php';

// CSRF helper nëse nuk ekziston në config.php
if (!function_exists('csrfField')) {
    function csrfField() {
        $token = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}

// Gjenero CSRF token nëse nuk ekziston
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==================== GJUHA ====================
$lang = $_GET['lang'] ?? $_COOKIE['lang'] ?? 'sq';
if (!in_array($lang, ['sq', 'sr', 'en'])) $lang = 'sq';
setcookie('lang', $lang, time() + 60 * 60 * 24 * 30, '/');

$labels = [
    'sq' => [
        'title'        => 'Regjistrohu si Përdorues i Thjeshtë',
        'name'         => 'Emri:',
        'surname'      => 'Mbiemri:',
        'email'        => 'Email:',
        'password'     => 'Fjalëkalimi:',
        'personal_number' => 'Numri Personal:',
        'phone'        => 'Numri i Telefonit:',
        'photo'        => 'Ngarko Foto të Letërnjoftimit/Pasaportës:',
        'register'     => 'Regjistrohu',
        'login'        => 'Keni llogari? Kyçuni këtu',
        'verify_title' => 'Kodi i Verifikimit 2FA',
        'verify_label' => 'Shkruani kodin e verifikimit (6 shifra):',
        'verify_btn'   => 'Verifiko dhe Përfundo Regjistrimin',
        'resend'       => 'Dërgo kodin përsëri',
        'timer'        => 'Mund të kërkoni ri-dërgimin e kodit pas',
        'seconds'      => 'sekondash.',
        'not_received' => 'Nuk morët kodin?',
        'dev_toggle'   => 'Developer Registration',
    ],
    'sr' => [
        'title'        => 'Registrujte se kao običan korisnik',
        'name'         => 'Ime:',
        'surname'      => 'Prezime:',
        'email'        => 'Email:',
        'password'     => 'Lozinka:',
        'personal_number' => 'JMBG:',
        'phone'        => 'Broj telefona:',
        'photo'        => 'Otpremite fotografiju lične karte/pasoša:',
        'register'     => 'Registrujte se',
        'login'        => 'Imate nalog? Prijavite se ovde',
        'verify_title' => '2FA verifikacioni kod',
        'verify_label' => 'Unesite verifikacioni kod (6 cifara):',
        'verify_btn'   => 'Verifikujte i završite registraciju',
        'resend'       => 'Pošaljite kod ponovo',
        'timer'        => 'Možete ponovo zatražiti kod za',
        'seconds'      => 'sekundi.',
        'not_received' => 'Niste dobili kod?',
        'dev_toggle'   => 'Registracija za developere',
    ],
    'en' => [
        'title'        => 'Register as Regular User',
        'name'         => 'First Name:',
        'surname'      => 'Last Name:',
        'email'        => 'Email:',
        'password'     => 'Password:',
        'personal_number' => 'Personal Number:',
        'phone'        => 'Phone Number:',
        'photo'        => 'Upload ID/Passport Photo:',
        'register'     => 'Register',
        'login'        => 'Already have an account? Login here',
        'verify_title' => '2FA Verification Code',
        'verify_label' => 'Enter the verification code (6 digits):',
        'verify_btn'   => 'Verify and Complete Registration',
        'resend'       => 'Resend Code',
        'timer'        => 'You can request the code again in',
        'seconds'      => 'seconds.',
        'not_received' => "Didn't receive the code?",
        'dev_toggle'   => 'Developer Registration',
    ],
];
$L = $labels[$lang];

// ==================== VARIABLAT GLOBALE ====================
$error      = null;
$success    = null;
$pending_2fa = false;

// ==================== RESEND 2FA ====================
if (
    isset($_GET['resend_2fa']) && $_GET['resend_2fa'] == '1' &&
    isset($_SESSION['pending_reg'], $_SESSION['2fa_code'])
) {
    require_once __DIR__ . '/vendor/autoload.php';
    $telefoni = $_SESSION['pending_reg']['telefoni'];
    $code     = $_SESSION['2fa_code'];
    if (function_exists('send2faSMS')) {
        echo send2faSMS($telefoni, $code) ? 'success' : 'error';
    } else {
        error_log('send2faSMS function not found');
        echo 'error';
    }
    exit;
}

// ==================== REGJISTRIM I RËNDOMTË ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['verify_2fa'], $_POST['dev_registration'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
        $error = 'Veprimi i paautorizuar (CSRF)!';
    } else {
        $turnstile_token = $_POST['cf-turnstile-response'] ?? '';
        if (empty($turnstile_token)) {
            $error = 'Ju lutem verifikoni se jeni njeri.';
        }

        if (!$error) {
            $emri            = trim($_POST['emri'] ?? '');
            $mbiemri         = trim($_POST['mbiemri'] ?? '');
            $email           = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
            $password        = trim($_POST['password'] ?? '');
            $personal_number = trim($_POST['personal_number'] ?? '');
            $telefoni        = trim($_POST['telefoni'] ?? '');
            $photo           = $_FILES['photo'] ?? null;
            $social_category = $_POST['social_category'] ?? 'none';

            if (!preg_match('/^[A-Za-zÇçËë\s]{2,}$/u', $emri)) {
                $error = 'Emri duhet të përmbajë vetëm shkronja dhe të jetë të paktën 2 karaktere.';
            } elseif (!preg_match('/^[A-Za-zÇçËë\s]{2,}$/u', $mbiemri)) {
                $error = 'Mbiemri duhet të përmbajë vetëm shkronja dhe të jetë të paktën 2 karaktere.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Email-i nuk është i vlefshëm.';
            }
        }

        // Fjalëkalimi
        $password_strength  = 0;
        $password_warnings  = [];

        if (!$error) {
            if (strlen($password) < 6) {
                $error = 'Fjalëkalimi duhet të ketë të paktën 6 karaktere.';
            } else {
                if (strlen($password) >= 8)          $password_strength++;
                else $password_warnings[] = 'Fjalëkalim i shkurtër - rekomandohet të paktën 8 karaktere';
                if (preg_match('/[A-Z]/', $password)) $password_strength++;
                else $password_warnings[] = 'Nuk ka shkronja të mëdha - shtoji për siguri më të lartë';
                if (preg_match('/[a-z]/', $password)) $password_strength++;
                else $password_warnings[] = 'Nuk ka shkronja të vogla - shtoji për siguri më të lartë';
                if (preg_match('/\d/', $password))    $password_strength++;
                else $password_warnings[] = 'Nuk ka numra - shtoji për siguri më të lartë';
                if (preg_match('/[^A-Za-z0-9]/', $password)) $password_strength++;
                else $password_warnings[] = 'Nuk ka simbole - shtoji për siguri më të lartë';

                $_SESSION['temp_password_strength'] = $password_strength;
                $_SESSION['temp_password_warnings'] = $password_warnings;
            }
        }

        if (!$error && !preg_match('/^\d{10}$/', $personal_number)) {
            $error = 'Numri personal duhet të jetë saktësisht 10 shifra!';
        }

        if (!$error) {
            if (!preg_match('/^\+383(43|44|45|46|48|49|38|39)\d{6}$/', $telefoni)) {
                $error = 'Numri i telefonit duhet të fillojë me +383 dhe të jetë i vlefshëm për Kosovë (p.sh. +38344123456)!';
            } else {
                $komuna = substr($personal_number, 0, 2);
                $komunat_kosove = ['01','02','03','04','05','06','07','08','09','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24','25','26','27'];
                if (!in_array($komuna, $komunat_kosove)) {
                    $error = 'Numri personal nuk i përket Republikës së Kosovës!';
                }
            }
        }

        if (!$error) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Ky email është i regjistruar!';
            }
        }

        if (!$error) {
            if (!$photo || $photo['error'] !== UPLOAD_ERR_OK) {
                $error = 'Ngarkoni një foto të vlefshme!';
            } elseif (!in_array($photo['type'], ['image/jpeg', 'image/png'])) {
                $error = 'Fotoja duhet të jetë në format JPG ose PNG!';
            } elseif ($photo['size'] > 2 * 1024 * 1024) {
                $error = 'Fotoja nuk duhet të jetë më e madhe se 2MB!';
            }
        }

        if (!$error) {
            $target_dir = __DIR__ . '/uploads/';
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
            $safe_filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($photo['name']));
            $target_file   = $target_dir . $safe_filename;
            if (!move_uploaded_file($photo['tmp_name'], $target_file)) {
                $error = 'Gabim gjatë ngarkimit të fotos. Provoni përsëri.';
            }
        }

        if (!$error) {
            $_SESSION['pending_reg'] = [
                'emri'             => $emri,
                'mbiemri'          => $mbiemri,
                'email'            => $email,
                'password'         => password_hash($password, PASSWORD_DEFAULT),
                'password_strength'=> $password_strength,
                'personal_number'  => $personal_number,
                'telefoni'         => $telefoni,
                'photo_path'       => $target_file,
                'created_at'       => date('Y-m-d H:i:s'),
                'mfa_enabled'      => ($password_strength < 3) ? 1 : 0,
                'social_category'  => $social_category,
            ];
            $_SESSION['2fa_code'] = rand(100000, 999999);

            if (function_exists('send2faSMS')) {
                require_once __DIR__ . '/vendor/autoload.php';
                send2faSMS($telefoni, $_SESSION['2fa_code']);
            } else {
                error_log('send2faSMS not found – 2FA code: ' . $_SESSION['2fa_code']);
            }

            $pending_2fa = true;
            $success     = 'Një kod verifikimi është dërguar në telefonin tuaj. Ju lutemi vendoseni më poshtë për të përfunduar regjistrimin.';
        }
    }
}

// ==================== REGJISTRIM DEVELOPER ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dev_registration'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
        $error = 'Veprimi i paautorizuar (CSRF)!';
    } else {
        $dev_key_input = trim($_POST['dev_key'] ?? '');
        $valid_dev_key = (defined('DEVELOPER_ACCESS_KEY') && constant('DEVELOPER_ACCESS_KEY')) ? constant('DEVELOPER_ACCESS_KEY') : 'NONE';

        if (empty($dev_key_input) || !hash_equals($valid_dev_key, $dev_key_input)) {
            $error = 'Çelësi i aksesit për developer është i pasaktë!';
        } else {
            $dev_name            = trim($_POST['dev_name'] ?? '');
            $dev_email           = filter_var(trim($_POST['dev_email'] ?? ''), FILTER_SANITIZE_EMAIL);
            $dev_password        = trim($_POST['dev_password'] ?? '');
            $dev_personal_number = trim($_POST['dev_personal_number'] ?? '');
            $dev_phone           = trim($_POST['dev_phone'] ?? '');
            $dev_photo           = $_FILES['dev_photo'] ?? null;

            if (!preg_match('/^[A-Za-zÇçËë\s]{2,}$/u', $dev_name)) {
                $error = 'Emri i zhvilluesit duhet të përmbajë vetëm shkronja dhe të jetë të paktën 2 karaktere.';
            } elseif (!filter_var($dev_email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Email-i i zhvilluesit nuk është i vlefshëm.';
            } elseif (strlen($dev_password) < 6) {
                $error = 'Fjalëkalimi i zhvilluesit duhet të ketë të paktën 6 karaktere.';
            } elseif (!preg_match('/^\d{10}$/', $dev_personal_number)) {
                $error = 'Numri personal i zhvilluesit duhet të ketë 10 shifra.';
            } elseif (!preg_match('/^\+383(43|44|45|46|48|49|38|39)\d{6}$/', $dev_phone)) {
                $error = 'Numri i telefonit të zhvilluesit duhet të jetë valid për Kosovë (p.sh. +38344123456)!';
            }

            if (!$error) {
                if (!$dev_photo || $dev_photo['error'] !== UPLOAD_ERR_OK) {
                    $error = 'Fotoja e dokumentit të zhvilluesit është e detyrueshme.';
                } elseif (!in_array($dev_photo['type'], ['image/jpeg', 'image/png'])) {
                    $error = 'Fotoja e zhvilluesit duhet të jetë JPEG ose PNG.';
                } elseif ($dev_photo['size'] > 2 * 1024 * 1024) {
                    $error = 'Fotoja e zhvilluesit nuk duhet të jetë më e madhe se 2MB!';
                }
            }

            if (!$error) {
                try {
                    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
                    $stmt->execute([$dev_email]);
                    if ($stmt->fetch()) {
                        $error = 'Ky email është tashmë i regjistruar.';
                    } else {
                        $target_dir    = __DIR__ . '/uploads/';
                        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                        $safe_filename = uniqid() . '_dev_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($dev_photo['name']));
                        $target_file   = $target_dir . $safe_filename;
                        move_uploaded_file($dev_photo['tmp_name'], $target_file);

                        $stmt = $pdo->prepare("
                            INSERT INTO users (emri, mbiemri, email, password, personal_number, telefoni, roli, photo_path, password_strength, mfa_enabled, password_changed_at, created_at)
                            VALUES (?, NULL, ?, ?, ?, ?, 'developer', ?, 3, 1, NOW(), NOW())
                        ");
                        $stmt->execute([$dev_name, $dev_email, password_hash($dev_password, PASSWORD_DEFAULT), $dev_personal_number, $dev_phone, $target_file]);

                        $success = 'Zhvilluesi u regjistrua me sukses! Mund të kyçeni tani.';
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    }
                } catch (Exception $e) {
                    error_log('Dev registration error: ' . $e->getMessage());
                    $error = 'Gabim gjatë regjistrimit të zhvilluesit. Provoni përsëri.';
                }
            }
        }
    }
}

// ==================== VERIFIKO 2FA ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_2fa'])) {
    $user_code = trim($_POST['code_2fa'] ?? '');
    if (
        isset($_SESSION['2fa_code'], $_SESSION['pending_reg']) &&
        hash_equals((string)$_SESSION['2fa_code'], $user_code)
    ) {
        $reg = $_SESSION['pending_reg'];

        $desiredData = [
            'emri'               => $reg['emri'],
            'mbiemri'            => $reg['mbiemri'],
            'email'              => $reg['email'],
            'password'           => $reg['password'],
            'personal_number'    => $reg['personal_number'] ?? null,
            'telefoni'           => $reg['telefoni'],
            'roli'               => 'perdorues',
            'photo_path'         => $reg['photo_path'] ?? null,
            'password_strength'  => $reg['password_strength'] ?? 0,
            'mfa_enabled'        => isset($reg['mfa_enabled']) ? (int)$reg['mfa_enabled'] : 0,
            'password_changed_at'=> date('Y-m-d H:i:s'),
        ];

        try {
            $availableColumns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC);
            $availableColumns = array_column($availableColumns, 'Field');
        } catch (Throwable $e) {
            $availableColumns = [];
            error_log('SHOW COLUMNS failed: ' . $e->getMessage());
        }

        $columns      = [];
        $placeholders = [];
        $values       = [];

        foreach ($desiredData as $column => $value) {
            if (in_array($column, $availableColumns, true)) {
                $columns[]      = $column;
                $placeholders[] = '?';
                $values[]       = $value;
            }
        }

        if (!empty($columns)) {
            $sql  = 'INSERT INTO users (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($values)) {
                $success = 'Regjistrimi u krye me sukses! Tani mund të kyçeni.';
                if (($reg['password_strength'] ?? 0) < 3) {
                    $success .= ' ⚠️ Fjalëkalimi juaj është i dobët. Do të ketë masa të shtuar sigurie (MFA i detyrueshëm, login alerts, password expiry çdo 90 ditë).';
                }

                // MCP server integration — pas regjistrimit të suksesshëm
                $mcp_url    = 'http://localhost:3000/register';
                $boundary   = uniqid();
                $delimiter  = '-------------' . $boundary;
                $fields     = ['emri' => $reg['emri'], 'mbiemri' => $reg['mbiemri'], 'email' => $reg['email'], 'telefoni' => $reg['telefoni']];
                $photo_path = $reg['photo_path'] ?? null;
                $data       = '';

                foreach ($fields as $name => $value) {
                    $data .= "--$delimiter\r\n";
                    $data .= "Content-Disposition: form-data; name=\"$name\"\r\n\r\n";
                    $data .= "$value\r\n";
                }

                if ($photo_path && file_exists($photo_path)) {
                    $data .= "--$delimiter\r\n";
                    $data .= "Content-Disposition: form-data; name=\"photo\"; filename=\"" . basename($photo_path) . "\"\r\n";
                    $data .= "Content-Type: " . mime_content_type($photo_path) . "\r\n\r\n";
                    $data .= file_get_contents($photo_path) . "\r\n";
                }

                $data .= "--$delimiter--\r\n";

                $context = stream_context_create([
                    'http' => [
                        'header'  => "Content-Type: multipart/form-data; boundary=$delimiter",
                        'method'  => 'POST',
                        'content' => $data,
                        'timeout' => 5,
                    ],
                ]);

                $result = @file_get_contents($mcp_url, false, $context);
                if ($result === false) {
                    error_log('MCP server integration failed after registration for: ' . $reg['email']);
                }

                unset($_SESSION['pending_reg'], $_SESSION['2fa_code']);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } else {
                $error = 'Gabim gjatë regjistrimit. Ju lutemi provoni përsëri.';
            }
        } else {
            $error = 'Konfigurimi i bazës së të dhënave nuk është i plotë për regjistrimin.';
        }
    } else {
        $error       = 'Kodi i verifikimit është i pasaktë!';
        $pending_2fa = true;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Regjistrohu | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            background: linear-gradient(120deg, #e2eafc 0%, #f8fafc 100%);
            font-family: 'Montserrat', Arial, sans-serif;
            margin: 0; padding: 0;
        }
        .container {
            max-width: 400px; margin: 60px auto;
            background: #fff; border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            padding: 36px 28px; text-align: center;
        }
        h2 { color: #2d6cdf; margin-bottom: 28px; font-size: 1.8rem; font-weight: 700; }
        .form-group { margin-bottom: 18px; text-align: left; }
        label { display: block; margin-bottom: 6px; color: #2d6cdf; font-weight: 600; }
        input[type="email"],
        input[type="password"],
        input[type="text"],
        select {
            width: 100%; padding: 10px 12px;
            border: 1px solid #e2eafc; border-radius: 8px;
            font-size: 1rem; background: #f8fafc;
            transition: border-color 0.2s;
        }
        input:focus, select:focus { border-color: #2d6cdf; outline: none; }
        button[type="submit"] {
            background: #2d6cdf; color: #fff; border: none;
            border-radius: 8px; padding: 12px 0; width: 100%;
            font-size: 1.1rem; font-weight: 700; cursor: pointer;
            transition: background 0.2s;
        }
        button[type="submit"]:hover { background: #184fa3; }
        .error   { color: #d32f2f; background: #ffeaea; border-radius: 8px; padding: 10px; margin-top: 18px; }
        .success { color: #2e7d32; background: #e8f5e9; border-radius: 8px; padding: 10px; margin-top: 18px; }
        .login-link { margin-top: 22px; font-size: 0.98rem; color: #333; }
        .login-link a { color: #2d6cdf; text-decoration: none; font-weight: 600; }
        .login-link a:hover { text-decoration: underline; }
        footer a:hover { color: white !important; }
    </style>
</head>
<body>
<div class="container">
    <!-- Language Switcher -->
    <form method="get" style="text-align:right;margin-bottom:10px;">
        <select name="lang" onchange="this.form.submit()" style="padding:4px 8px;border-radius:6px;">
            <option value="sq"<?php if ($lang==='sq') echo ' selected'; ?>>Shqip</option>
            <option value="sr"<?php if ($lang==='sr') echo ' selected'; ?>>Српски</option>
            <option value="en"<?php if ($lang==='en') echo ' selected'; ?>>English</option>
        </select>
    </form>

    <!-- Logo -->
    <div style="text-align:center;margin-bottom:20px;">
        <img src="images/pngwing.com (1).png" alt="Noteria Logo" style="width:120px;height:auto;">
    </div>

    <h2><?php echo htmlspecialchars($L['title']); ?></h2>

    <?php if ($pending_2fa): ?>
        <!-- 2FA Verification -->
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:16px;margin-bottom:20px;">
            <h3 style="color:#856404;margin-top:0;"><?php echo htmlspecialchars($L['verify_title']); ?></h3>
            <p style="color:#856404;margin:8px 0;">Kodi juaj i verifikimit:</p>
            <div style="background:#fff;border:2px solid #ffc107;border-radius:6px;padding:12px;text-align:center;margin:12px 0;font-family:monospace;font-size:1.8rem;letter-spacing:4px;font-weight:bold;color:#333;">
                <?php echo isset($_SESSION['2fa_code']) ? htmlspecialchars((string)$_SESSION['2fa_code']) : '<span style="color:#888;font-size:1rem;">-</span>'; ?>
            </div>
            <p style="color:#856404;font-size:0.9rem;margin:12px 0;">Ky kod u dërgua edhe në telefonin tuaj. Është i vlefshëm për 2 minuta.</p>
        </div>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <div class="form-group">
                <label for="code_2fa"><?php echo htmlspecialchars($L['verify_label']); ?></label>
                <input type="text" id="code_2fa" name="code_2fa" required maxlength="6" pattern="\d{6}" autofocus inputmode="numeric">
            </div>
            <button type="submit" name="verify_2fa"><?php echo htmlspecialchars($L['verify_btn']); ?></button>
        </form>
        <div id="2fa-timer" style="margin-top:12px;color:#888;font-size:0.98em;"></div>
        <button id="resend2fa" type="button" style="margin-top:8px;display:none;background:#2d6cdf;color:#fff;border:none;border-radius:8px;padding:8px 16px;cursor:pointer;">
            <?php echo htmlspecialchars($L['resend']); ?>
        </button>

    <?php else: ?>
        <!-- Main Registration Form -->
        <form method="POST" action="" enctype="multipart/form-data">
            <?php echo csrfField(); ?>
            <div class="form-group">
                <label for="emri"><?php echo htmlspecialchars($L['name']); ?></label>
                <input type="text" id="emri" name="emri" required value="<?php echo htmlspecialchars($_POST['emri'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="mbiemri"><?php echo htmlspecialchars($L['surname']); ?></label>
                <input type="text" id="mbiemri" name="mbiemri" required value="<?php echo htmlspecialchars($_POST['mbiemri'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="email"><?php echo htmlspecialchars($L['email']); ?></label>
                <input type="email" id="email" name="email" required autocomplete="username" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="password"><?php echo htmlspecialchars($L['password']); ?></label>
                <input type="password" id="password" name="password" required autocomplete="new-password" minlength="6">
                <div id="password-strength" style="margin-top:8px;font-size:0.85rem;"></div>
                <div id="password-warnings" style="margin-top:8px;font-size:0.85rem;color:#ff9800;"></div>
            </div>
            <div class="form-group">
                <label for="personal_number"><?php echo htmlspecialchars($L['personal_number']); ?></label>
                <input type="text" id="personal_number" name="personal_number" required maxlength="10" pattern="\d{10}" inputmode="numeric" value="<?php echo htmlspecialchars($_POST['personal_number'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="telefoni"><?php echo htmlspecialchars($L['phone']); ?></label>
                <input type="text" id="telefoni" name="telefoni" required placeholder="+38344123456" maxlength="13" pattern="^\+383(43|44|45|46|48|49|38|39)\d{6}$" value="<?php echo htmlspecialchars($_POST['telefoni'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="photo"><?php echo htmlspecialchars($L['photo']); ?></label>
                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png" required>
            </div>
            <div class="form-group">
                <label for="social_category">Kategoria Sociale:</label>
                <select id="social_category" name="social_category" required>
                    <option value="">-- Zgjidh --</option>
                    <option value="none">Asnjë</option>
                    <option value="invalid_lufte">Familje e Invalidit të Luftës</option>
                    <option value="asistence_sociale">Asistencë Sociale</option>
                </select>
                <small style="color:#888;">Nëse i përkisni njërës prej këtyre kategorive, rezervimi do të jetë falas.</small>
            </div>
            <div class="form-group">
                <div class="cf-turnstile" data-sitekey="1x00000000000000000000AA" data-theme="light"></div>
            </div>
            <button type="submit"><?php echo htmlspecialchars($L['register']); ?></button>
        </form>
    <?php endif; ?>

    <?php if (!empty($error)):   ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <div class="login-link">
        <?php
        if ($lang === 'sq')     echo 'Keni llogari? <a href="login.php">Kyçuni këtu</a>';
        elseif ($lang === 'sr') echo 'Imate nalog? <a href="login.php">Prijavite se ovde</a>';
        else                    echo 'Already have an account? <a href="login.php">Login here</a>';
        ?>
    </div>
</div>

<!-- Developer Registration Section -->
<div style="max-width:400px;margin:20px auto;background:#fff;border-radius:16px;box-shadow:0 8px 24px rgba(0,0,0,0.08);padding:24px;text-align:center;display:none;" id="devRegContainer">
    <h3 style="color:#333;font-size:1.2rem;margin-bottom:16px;">Developer Registration</h3>
    <form method="POST" action="" id="developerRegForm" enctype="multipart/form-data">
        <input type="hidden" name="dev_registration" value="1">
        <?php echo csrfField(); ?>
        <?php
        $devFields = [
            ['id'=>'dev_name',            'label'=>'Full Name:',            'type'=>'text',     'name'=>'dev_name'],
            ['id'=>'dev_email',           'label'=>'Email:',                'type'=>'email',    'name'=>'dev_email'],
            ['id'=>'dev_password',        'label'=>'Password:',             'type'=>'password', 'name'=>'dev_password'],
            ['id'=>'dev_personal_number', 'label'=>'Personal Number:',      'type'=>'text',     'name'=>'dev_personal_number'],
            ['id'=>'dev_phone',           'label'=>'Phone (+383...):', 'type'=>'text',     'name'=>'dev_phone'],
            ['id'=>'dev_key',             'label'=>'Developer Access Key:', 'type'=>'password', 'name'=>'dev_key'],
        ];
        foreach ($devFields as $f): ?>
        <div style="margin-bottom:18px;text-align:left;">
            <label for="<?php echo $f['id']; ?>" style="display:block;margin-bottom:6px;color:#2d6cdf;font-weight:600;"><?php echo $f['label']; ?></label>
            <input type="<?php echo $f['type']; ?>" id="<?php echo $f['id']; ?>" name="<?php echo $f['name']; ?>"
                   style="width:100%;padding:10px 12px;border:1px solid #e2eafc;border-radius:8px;font-size:1rem;background:#f8fafc;" required
                   <?php if ($f['name']==='dev_key') echo 'autocomplete="off"'; ?>>
        </div>
        <?php endforeach; ?>
        <div style="margin-bottom:18px;text-align:left;">
            <label for="dev_photo" style="display:block;margin-bottom:6px;color:#2d6cdf;font-weight:600;">Upload ID/Passport Photo:</label>
            <input type="file" id="dev_photo" name="dev_photo" accept="image/jpeg,image/png"
                   style="width:100%;padding:10px 12px;border:1px solid #e2eafc;border-radius:8px;font-size:1rem;background:#f8fafc;" required>
        </div>
        <button type="submit" style="background:#333;color:#fff;border:none;border-radius:8px;padding:12px 0;width:100%;font-size:1.1rem;font-weight:700;cursor:pointer;">Register as Developer</button>
    </form>
    <div style="margin-top:16px;">
        <button type="button" id="hideDevReg" style="background:none;border:none;color:#666;font-size:0.85rem;cursor:pointer;text-decoration:underline;">Hide Developer Registration</button>
    </div>
</div>

<!-- Developer toggle -->
<div style="text-align:center;margin:20px auto;max-width:400px;">
    <button type="button" id="devRegToggle" style="background:none;border:none;color:#666;font-size:0.85rem;cursor:pointer;text-decoration:underline;">
        <?php echo htmlspecialchars($L['dev_toggle']); ?>
    </button>
</div>

<!-- Footer -->
<footer style="background:linear-gradient(135deg,#1a1f2e 0%,#24292e 100%);color:#d1d5da;padding:40px 0 20px;margin-top:40px;text-align:center;position:relative;">
    <div style="position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,#cfa856,transparent);"></div>
    <div class="container">
        <span style="font-weight:800;font-size:1.5rem;margin-bottom:10px;display:block;background:linear-gradient(135deg,#ffffff,#cfa856);background-clip:text;-webkit-background-clip:text;-webkit-text-fill-color:transparent;">e-Noteria</span>
        <p style="color:#8b949e;margin:0;font-size:0.9rem;">Platformë SaaS për zyrat noteriale në Kosovë</p>
        <div style="border-top:1px solid #30363d;padding-top:20px;margin-top:20px;">
            <div style="margin-bottom:15px;">
                <a href="terms.php"      style="color:#8b949e;text-decoration:none;margin:0 15px;">Kushtet e Përdorimit</a>
                <a href="Privatesia.php" style="color:#8b949e;text-decoration:none;margin:0 15px;">Politika e Privatësisë</a>
                <a href="ndihma.php"     style="color:#8b949e;text-decoration:none;margin:0 15px;">Ndihma</a>
            </div>
            <p style="margin:0;font-size:0.8em;color:#8b949e;">&copy; <?php echo date('Y'); ?> Platforma e-Noteria | Republika e Kosovës</p>
        </div>
    </div>
</footer>

<script>
// Password strength
const passwordInput = document.getElementById('password');
const strengthDiv   = document.getElementById('password-strength');
const warningsDiv   = document.getElementById('password-warnings');
if (passwordInput) {
    passwordInput.addEventListener('input', function () {
        const pwd = this.value;
        let strength = 0, warnings = [];
        if (pwd.length >= 8)        strength++; else if (pwd.length > 0) warnings.push('⚠️ Shumë i shkurtër');
        if (/[A-Z]/.test(pwd))      strength++; else if (pwd.length > 0) warnings.push('⚠️ Shtoji shkronja të mëdha');
        if (/[a-z]/.test(pwd))      strength++; else if (pwd.length > 0) warnings.push('⚠️ Shtoji shkronja të vogla');
        if (/\d/.test(pwd))         strength++; else if (pwd.length > 0) warnings.push('⚠️ Shtoji numra');
        if (/[^\w\s]/.test(pwd))    strength++; else if (pwd.length > 0) warnings.push('⚠️ Shtoji simbole');
        const levels = ['','🔴 I dobët - Massat e sigurisë do të jenë shumë të larta','🔴 I dobët','🟡 I moderuar','🟢 I mirë','🟢 Shumë i mirë - Siguri maksimale'];
        const colors = ['','#d32f2f','#d32f2f','#ff9800','#388e3c','#1b5e20'];
        strengthDiv.style.color  = pwd.length ? colors[strength] : '';
        strengthDiv.textContent  = pwd.length ? levels[strength] : '';
        warningsDiv.innerHTML    = warnings.join('<br>');
    });
}

// 2FA timer
let timer = 60;
const timerDiv  = document.getElementById('2fa-timer');
const resendBtn = document.getElementById('resend2fa');
function updateTimer() {
    if (!timerDiv) return;
    if (timer > 0) {
        timerDiv.textContent = '<?php echo addslashes($L['timer']); ?> ' + timer + ' <?php echo addslashes($L['seconds']); ?>';
        if (resendBtn) resendBtn.style.display = 'none';
        timer--;
        setTimeout(updateTimer, 1000);
    } else {
        timerDiv.textContent = '<?php echo addslashes($L['not_received']); ?>';
        if (resendBtn) resendBtn.style.display = 'inline-block';
    }
}
if (timerDiv) updateTimer();

if (resendBtn) {
    resendBtn.onclick = function () {
        resendBtn.disabled    = true;
        resendBtn.textContent = '<?php echo addslashes($L['resend']); ?>...';
        fetch(window.location.pathname + '?resend_2fa=1')
            .then(r => r.text())
            .then(result => {
                resendBtn.disabled    = false;
                resendBtn.textContent = '<?php echo addslashes($L['resend']); ?>';
                timer = 60; updateTimer();
                alert(result === 'success' ? 'Kodi u dërgua përsëri!' : 'Dërgimi dështoi!');
            })
            .catch(() => {
                resendBtn.disabled    = false;
                resendBtn.textContent = '<?php echo addslashes($L['resend']); ?>';
                alert('Dërgimi dështoi!');
            });
    };
}

// Dev toggle
document.getElementById('devRegToggle').addEventListener('click', function () {
    const c = document.getElementById('devRegContainer');
    c.style.display = (c.style.display === 'none' || !c.style.display) ? 'block' : 'none';
    if (c.style.display === 'block') setTimeout(() => document.getElementById('dev_name').focus(), 100);
});
document.getElementById('hideDevReg').addEventListener('click', function () {
    document.getElementById('devRegContainer').style.display = 'none';
});
document.addEventListener('keydown', function (e) {
    if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'r') {
        e.preventDefault();
        document.getElementById('devRegContainer').style.display = 'block';
        setTimeout(() => document.getElementById('dev_name').focus(), 100);
    }
});
</script>
</body>
</html>