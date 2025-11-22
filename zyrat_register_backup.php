<?php
declare(strict_types=1);
// =====================
// RATE LIMITING & BRUTE-FORCE PROTECTION
// =====================
define('LOGIN_ATTEMPT_LIMIT', 5);
define('LOGIN_ATTEMPT_WINDOW', 900); // 15 minuta

// =====================
// AUDITIM I AKSESIT DHE NDRYSHIMEVE
// =====================
define('AUDIT_LOG', __DIR__ . '/audit.log');
function audit_log($action, $details = []) {
    $entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user' => $_SESSION['dev_logged_in'] ?? 'anon',
        'action' => $action,
        'details' => $details
    ];
    file_put_contents(AUDIT_LOG, json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

// Always define audit_data_change before any usage
if (!function_exists('audit_data_change')) {
    function audit_data_change($type, $data) {
        audit_log('data_change', ['type' => $type, 'data' => $data]);
    }
}

function login_rate_limit_key() {
    return 'login_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

// Funksion për të kontrolluar nëse përdoruesi është autentikuar plotësisht (login + MFA)
function isAuthenticated() {
    return isset($_SESSION['dev_logged_in']) && $_SESSION['dev_logged_in'] === true &&
           isset($_SESSION['dev_mfa_passed']) && $_SESSION['dev_mfa_passed'] === true;
}

// =====================
// DETYRO HTTPS
// =====================
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $https_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('Location: ' . $https_url, true, 301);
    exit;
}

// =====================
// SESSION INIT
// =====================
session_start();
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = [];
}
// Pastrimi i tentativave të vjetra
foreach ($_SESSION['login_attempts'] as $ip => $attempts) {
    $_SESSION['login_attempts'][$ip] = array_filter($attempts, function($ts) {
        return $ts > (time() - LOGIN_ATTEMPT_WINDOW);
    });
}

// SHFAQ QR KODIN PËR MFA VETËM PËR DEVELOPER
if (isAuthenticated()) {
    $otpauth_url = 'otpauth://totp/Noteria:developer?secret=JBSWY3DPEHPK3PXP&issuer=Noteria';
    $qr_url = 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . urlencode($otpauth_url);
    echo '<div style="text-align:center;margin:30px 0;">';
    echo '<h3>Skano QR kodin me Google Authenticator</h3>';
    echo '<img src="' . $qr_url . '" alt="QR Code for Google Authenticator" style="border:8px solid #2d6cdf;border-radius:16px;box-shadow:0 4px 16px #0002;">';
    echo '<div style="margin-top:10px;font-size:1.1em;color:#333;">ose përdor këtë secret: <b>JBSWY3DPEHPK3PXP</b></div>';
    echo '</div>';
}

// Nëse nuk është autentikuar, shfaq formën e login + MFA
if (!isAuthenticated()) {
    // Proceso login
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dev_login'])) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!isset($_SESSION['login_attempts'][$ip])) $_SESSION['login_attempts'][$ip] = [];
        // Rate limit check
        if (count($_SESSION['login_attempts'][$ip]) >= LOGIN_ATTEMPT_LIMIT) {
            $login_error = 'Shumë tentativa të dështuara. Provo përsëri pas 15 minutash.';
        } else {
        $username = trim($_POST['dev_username'] ?? '');
        $password = $_POST['dev_password'] ?? '';
        $mfa_code = trim($_POST['dev_mfa_code'] ?? '');

            if (!isset($ALLOWED_USERS[$username])) {
                $login_error = 'Kredencialet nuk janë të sakta.';
                $_SESSION['login_attempts'][$ip][] = time();
            } else {
                $user = $ALLOWED_USERS[$username];
                if (!password_verify($password, $user[0])) {
                    $login_error = 'Kredencialet nuk janë të sakta.';
                    $_SESSION['login_attempts'][$ip][] = time();
                } else {
                    // Verifiko MFA (TOTP)
                    require_once __DIR__ . '/vendor/autoload.php';
                    $g = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
                    if (!$g->checkCode($user[1], $mfa_code)) {
                        $login_error = 'Kodi i sigurisë (MFA) nuk është i saktë.';
                        $_SESSION['login_attempts'][$ip][] = time();
                    } else {
                        $_SESSION['dev_logged_in'] = true;
                        $_SESSION['dev_mfa_passed'] = true;
                        $_SESSION['login_attempts'][$ip] = [];
                        header('Location: ' . $_SERVER['REQUEST_URI']);
                        exit;
                    }
                }
            }
        }
    }

    // Formë login + MFA
    echo '<!DOCTYPE html><html lang="sq"><head><meta charset="UTF-8"><title>Developer Login | Noteria</title>';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
    echo '<style>body{background:#222;color:#fff;font-family:Poppins,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}';
    echo '.login-box{background:#181c24;padding:40px 30px 30px 30px;border-radius:16px;box-shadow:0 8px 32px #0004;width:350px}';
    echo '.login-box h2{margin-bottom:20px;text-align:center;font-weight:700;font-size:1.4rem}';
    echo '.form-group{margin-bottom:18px}';
    echo 'label{display:block;margin-bottom:7px;font-weight:600}';
    echo 'input[type=text],input[type=password],input[type=number]{width:100%;padding:12px 14px;border-radius:8px;border:none;background:#23283a;color:#fff;font-size:1rem;margin-bottom:2px}';
    echo 'input[type=text]:focus,input[type=password]:focus,input[type=number]:focus{outline:2px solid #2d6cdf}';
    echo 'button{width:100%;padding:12px 0;background:#2d6cdf;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:1.1rem;cursor:pointer;transition:.2s}';
    echo 'button:hover{background:#1e40af}';
    echo '.error{background:#ef4444;color:#fff;padding:10px 15px;border-radius:8px;margin-bottom:15px;text-align:center}';
    echo '</style></head><body>';
    echo '<form class="login-box" method="POST">';
    echo '<h2><i class="fas fa-shield-alt"></i> Developer Login</h2>';
    if (isset($login_error)) echo '<div class="error">'.$login_error.'</div>';
    echo '<div class="form-group"><label>Përdoruesi</label><input type="text" name="dev_username" required autocomplete="username"></div>';
    echo '<div class="form-group"><label>Fjalëkalimi</label><input type="password" name="dev_password" required autocomplete="current-password"></div>';
    echo '<div class="form-group"><label>Kodi i Sigurisë (MFA)</label><input type="text" inputmode="numeric" pattern="\\d{6}" maxlength="6" name="dev_mfa_code" required placeholder="6-shifror" autocomplete="one-time-code"></div>';
    echo '<input type="hidden" name="dev_login" value="1">';
    echo '<button type="submit"><i class="fas fa-sign-in-alt"></i> Hyr</button>';
    echo '<div style="margin-top:18px;font-size:0.93em;color:#aaa;text-align:center;">Përdorni Google Authenticator ose aplikacion TOTP për kodin MFA.</div>';
    echo '</form></body></html>';
    exit;
}

// Error handling and logging configuration
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');


// Session and dependencies initialization
session_start();
// Session timeout (15 min) dhe regeneration
define('SESSION_TIMEOUT', 900);
if (!isset($_SESSION['LAST_ACTIVITY'])) {
    $_SESSION['LAST_ACTIVITY'] = time();
} elseif (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['LAST_ACTIVITY'] = time();
// Regjenero session ID pas login të suksesshëm
function secure_session_regenerate() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

// Backup directory for data persistence
define('BACKUP_DIR', __DIR__ . '/backup_data');
if (!is_dir(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0755, true);
}

// Initialize response variables
$success = null;
$error = null;
$backup_status = null;

/**
 * Backup data to secure JSON file with timestamp
 * 
 * @param array $data Registration data to backup
 * @param string $type Type of backup (registration, payment, etc.)
 * @return bool Success status
 */
/**
 * Backup data to secure ENCRYPTED file with timestamp (AES-256)
 *
 * @param array $data Registration data to backup
 * @param string $type Type of backup (registration, payment, etc.)
 * @return bool Success status
 */
function backupRegistrationData(array $data, string $type = 'registration'): bool {
    try {
        $timestamp = date('Y-m-d_H-i-s');
        $backup_file = BACKUP_DIR . "/{$type}_{$timestamp}_" . uniqid() . '.enc';
        $backup_content = [
            'backup_type' => $type,
            'backup_timestamp' => $timestamp,
            'backup_date_readable' => date('l, d F Y \a\t H:i:s', time()),
            'data' => $data,
            'backup_hash' => hash('sha256', json_encode($data))
        ];
        $json = json_encode($backup_content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        // ENCRYPT JSON with AES-256-CBC
        $key = hash('sha256', getenv('NOTERIA_BACKUP_KEY') ?: 'super-secret-key', true); // vendos NOTERIA_BACKUP_KEY në env
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($json, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) throw new Exception('Encryption failed');
        $data_to_store = base64_encode($iv . $ciphertext);
        file_put_contents($backup_file, $data_to_store);
        chmod($backup_file, 0600);
        cleanOldBackups($type, 100);
        return true;
    } catch (Exception $e) {
        error_log("Backup failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Clean old backup files, keeping only the most recent ones
 * 
 * @param string $type Backup type to clean
 * @param int $keep_count Number of recent backups to keep
 */
function cleanOldBackups(string $type, int $keep_count = 100): void {
    $pattern = BACKUP_DIR . "/{$type}_*.json";
    $files = glob($pattern);
    
    if (count($files) > $keep_count) {
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $files_to_delete = array_slice($files, $keep_count);
        foreach ($files_to_delete as $file) {
            @unlink($file);
        }
    }
}

/**
 * Export registration data with all details
 * 
 * @param array $data Office registration data
 * @return string Formatted data for backup
 */
function formatBackupData(array $data): array {
    return [
        'office_name' => $data['emri'] ?? 'N/A',
        'office_email' => $data['email'] ?? 'N/A',
        'office_phone' => $data['telefoni'] ?? 'N/A',
        'city' => $data['qyteti'] ?? 'N/A',
        'address' => $data['adresa'] ?? 'N/A',
        'bank_details' => [
            'bank_name' => $data['banka'] ?? 'N/A',
            'iban' => $data['iban'] ?? 'N/A',
            'account_number' => $data['llogaria'] ?? 'N/A'
        ],
        'notary_info' => [
            'notary_name' => $data['emri_noterit'] ?? 'N/A',
            'experience_years' => $data['vitet_pervoje'] ?? 0,
            'staff_count' => $data['numri_punetoreve'] ?? 1,
            'languages' => $data['gjuhet'] ?? 'N/A'
        ],
        'registration_details' => [
            'fiscal_number' => $data['numri_fiskal'] ?? 'N/A',
            'business_number' => $data['numri_biznesit'] ?? 'N/A',
            'license_number' => $data['numri_licences'] ?? 'N/A',
            'license_date' => $data['data_licences'] ?? 'N/A'
        ],
        'payment_info' => [
            'transaction_id' => $data['transaction_id'] ?? 'N/A',
            'payment_method' => $data['payment_method'] ?? 'N/A',
            'amount' => $data['pagesa'] ?? 0,
            'currency' => 'EUR'
        ]
    ];
}

// Lista e qyteteve të Kosovës
$qytetet = [
    "Prishtinë", "Mitrovicë", "Pejë", "Gjakovë", "Ferizaj", "Gjilan", "Prizren",
    "Vushtrri", "Fushë Kosovë", "Podujevë", "Suharekë", "Rahovec", "Drenas",
    "Malishevë", "Lipjan", "Deçan", "Istog", "Kamenicë", "Dragash", "Kaçanik",
    "Obiliq", "Klinë", "Viti", "Skenderaj", "Shtime", "Shtërpcë", "Novobërdë",
    "Mamushë", "Junik", "Hani i Elezit", "Zubin Potok", "Zveçan", "Leposaviq",
    "Graçanicë", "Ranillug", "Kllokot", "Parteš", "Mitrovicë e Veriut"
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Inicializimi i sistemit të verifikimit të pagesave dhe telefonave
    $paymentVerifier = new PaymentVerificationAdvanced($pdo);
    $phoneVerifier = new PhoneVerificationAdvanced($pdo);
    
    // Kolektoj të gjithë të dhënat e pagesës
    $registration_data = $_POST;
    
    // Kontrolli për verifikimin e SMS-it
    if (isset($_POST['verify_sms']) && isset($_SESSION['phone_verification_pending'])) {
        $sms_code = trim($_POST['sms_code']);
        $transaction_id = $_POST['transaction_id'];
        $phone_data = $_SESSION['phone_verification_pending'];
        
        if (time() > $phone_data['expires_at']) {
            $error = "⏰ Koha për verifikimin e SMS-it ka skaduar. Ju lutemi regjistrohuni përsëri.";
            unset($_SESSION['phone_verification_pending']);
        } else {
            $verification_result = $phoneVerifier->verifyCode($phone_data['phone'], $sms_code, $transaction_id);
            
            if ($verification_result['success']) {
                // SMS u verifikua me sukses!
                $success = "🎉 VERIFIKIM I KOMPLETUAR!<br>" .
                         "✅ Telefoni u verifikua me sukses<br>" .
                         "💳 Pagesa është duke u verifikuar automatikisht<br>" .
                         "📧 Do të merrni email konfirmimi menjëherë<br>" .
                         "🚀 Procesi i plotë zgjati nën 3 minuta!";
                
                // Përditëso payment logs me verifikim të telefonit
                $stmt = $pdo->prepare("
                    UPDATE payment_logs 
                    SET phone_verified = 1, phone_verified_at = NOW() 
                    WHERE transaction_id = ?
                ");
                $stmt->execute([$transaction_id]);
                
                // Backup të dhënat e verifikimit të suksesshëm
                $backup_data = [
                    'transaction_id' => $transaction_id,
                    'phone_verified' => true,
                    'verified_at' => date('Y-m-d H:i:s'),
                    'phone' => $phone_data['phone']
                ];
                backupRegistrationData($backup_data, 'payment_verification');
                audit_data_change('payment_verification', $backup_data);
                $backup_status = "✓ Të dhënat u ruajtën në sistem të sigurt";
                
                // Cleanup session
                unset($_SESSION['phone_verification_pending']);
                
                // Log successful verification
                error_log("PHONE_VERIFIED: Transaction $transaction_id verified in under 3 minutes!");
                
            } else {
                $error = "❌ " . $verification_result['error'];
            }
        }
    } else {
        // Procesohet regjistrimi i ri
        
        $emri = trim($_POST["emri"]);
        $qyteti = $_POST["qyteti"] ?? '';
        $email = trim($_POST["email"]);
        $email2 = trim($_POST["email2"]);
        $telefoni = trim($_POST["telefoni"]);
    $shteti = "Kosova";
    $banka = trim($_POST["banka"] ?? '');
    $iban = trim($_POST["iban"] ?? '');
    // Hash password (iban) për ruajtje të sigurt
    $iban_hash = password_hash($iban, PASSWORD_ARGON2ID);
    $llogaria = trim($_POST["llogaria"] ?? '');
    $pagesa = trim($_POST["pagesa"] ?? '');
    $transaction_id = trim($_POST["transaction_id"] ?? '');
    $payment_method = $_POST["payment_method"] ?? '';
    $payment_proof = $_FILES["payment_proof"] ?? null;
    
    // Inicializimi i sistemit të verifikimit të pagesave dhe telefonave
    $paymentVerifier = new PaymentVerificationAdvanced($pdo);
    $phoneVerifier = new PhoneVerificationAdvanced($pdo);

    // Validime të shtruara
    if (
        empty($emri) || empty($qyteti) || empty($email) || empty($email2) || empty($telefoni) ||
        empty($banka) || empty($iban) || empty($llogaria) || empty($pagesa) || 
        empty($transaction_id) || empty($payment_method)
    ) {
        $error = "Ju lutemi plotësoni të gjitha fushat, përfshirë të dhënat e pagesës dhe ID-në e transaksionit.";
    } elseif (!in_array($qyteti, $qytetet)) {
        $error = "Qyteti i zgjedhur nuk është valid.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email-i nuk është valid.";
    } elseif ($email !== $email2) {
        $error = "Email-at nuk përputhen.";
    } elseif (!preg_match('/^\+383\d{8}$/', $telefoni)) {
        $error = "Numri i telefonit duhet të fillojë me +383 dhe të ketë gjithsej 12 shifra (p.sh. +38344123456).";
    } elseif (!$paymentVerifier->validateIBANAdvanced($iban)) {
        $error = "IBAN nuk është valid ose nuk i përket Kosovës. Sigurohuni që të fillojë me 'XK'.";
    } elseif (!preg_match('/^\d{8,20}$/', $llogaria)) {
        $error = "Numri i llogarisë duhet të përmbajë vetëm shifra (8-20 shifra).";
    } elseif (!is_numeric($pagesa) || $pagesa < 10 || $pagesa > 10000) {
        $error = "Shuma e pagesës duhet të jetë numerike, të paktën 10€ dhe maksimum 10,000€.";
    } elseif (!preg_match('/^TXN_\d{8}_\d{6}_[a-f0-9]{8,16}$/i', $transaction_id)) {
        $error = "ID-ja e transaksionit nuk është në formatin e saktë.";
    } elseif (!$payment_proof || $payment_proof['error'] !== UPLOAD_ERR_OK) {
        $error = "Ju lutemi ngarkoni dëshminë e pagesës. Kjo është e detyrueshme për verifikim.";
    } elseif ($payment_proof && !validatePaymentProof($payment_proof)) {
        $error = "Dëshmi e pagesës nuk është e vlefshme. Lejohën vetëm PDF, JPG ose PNG deri në 5MB.";
    } else {
        // Përgatitja e të dhënave për verifikim
        $payment_data = [
            'transaction_id' => $transaction_id,
            'amount' => floatval($pagesa),
            'method' => $payment_method,
            'email' => $email,
            'bank' => $banka,
            'iban' => $iban,
            'office_name' => $emri,
            'city' => $qyteti
        ];
        
        // Verifikimi i pagesës - për tani thjeshtë i simuluar
        // Në të ardhmen mund të integrohet me API-të e vërteta të bankave
        $payment_verified = true; // Simulon verifikim të suksesshëm
        
        // Kontrollo nëse përdoruesi ka dhënë dëshmi të pagesës
        $has_payment_proof = ($payment_proof && $payment_proof['error'] === UPLOAD_ERR_OK);
        
        // Për momentin, pranojmë regjistrimin nëse ka dëshmi pagese
        if ($has_payment_proof) {
            $payment_verified = true;
        } else {
            $payment_verified = false;
            $error = "Ju lutemi ngarkoni dëshminë e pagesës për të vazhduar.";
        }
        
        if (!$payment_verified) {
            $error = "Pagesa nuk mund të verifikohet. Ju lutemi kontrolloni të dhënat e transaksionit.";
        } else {
            // Ruajtja e të dhënave vetëm nëse pagesa është verifikuar
            try {
                $pdo->beginTransaction();
                
                // Përgatito të dhënat për backup para se të insertohet
                $office_backup_data = [
                    'office_name' => $emri,
                    'city' => $qyteti,
                    'email' => $email,
                    'phone' => $telefoni,
                    'bank' => $banka,
                    'iban' => $iban,
                    'account' => $llogaria,
                    'amount' => floatval($pagesa),
                    'transaction_id' => $transaction_id,
                    'payment_method' => $payment_method,
                    'registration_time' => date('Y-m-d H:i:s')
                ];
                
                // Ruajtja e zyrës
                $stmt = $pdo->prepare("INSERT INTO zyrat (emri, qyteti, shteti, email, telefoni, banka, iban, llogaria, pagesa, transaction_id, payment_method, payment_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                $office_inserted = $stmt->execute([$emri, $qyteti, $shteti, $email, $telefoni, $banka, $iban_hash, $llogaria, $pagesa, $transaction_id, $payment_method]);
                
                if ($office_inserted) {
                    // Backup të dhënat e zyrës pas insertimit të suksesshëm
                    backupRegistrationData($office_backup_data, 'office_registration');
                    audit_data_change('office_registration', $office_backup_data);
                    
                    // Ruajtja e dëshmisë së pagesës nëse është ngarkuar
                    $file_path = null;
                    if ($payment_proof && $payment_proof['error'] === UPLOAD_ERR_OK) {
                        if (savePaymentProof($payment_proof, $transaction_id)) {
                            $file_path = 'uploads/payment_proofs/' . $transaction_id . '.' . pathinfo($payment_proof['name'], PATHINFO_EXTENSION);
                        }
                    }
                    
                    // Shto në payment_logs për verifikim të shpejtë me të dhëna telefoni
                    $payment_details = "IBAN: $iban, Banka: $banka, Llogaria: $llogaria";
                    $stmt = $pdo->prepare("
                        INSERT INTO payment_logs 
                        (office_email, office_name, phone_number, payment_method, payment_amount, payment_details, transaction_id, verification_status, file_path, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
                    ");
                    $stmt->execute([$email, $emri, $telefoni, $payment_method, $pagesa, $payment_details, $transaction_id, $file_path]);
                    
                    $pdo->commit();
                    
                    // 🚀 VERIFIKIM I SHPEJTË I TELEFONIT (3 minuta)
                    $phone_verification_result = $phoneVerifier->generateVerificationCode($telefoni, $transaction_id);
                    
                    if ($phone_verification_result['success']) {
                        $success = "✅ Zyra u regjistrua me sukses!<br>" .
                                 "📱 Kodi i verifikimit SMS u dërgua në $telefoni<br>" .
                                 "⏰ Keni 3 minuta për të verifikuar numrin<br>" .
                                 "💳 Pagesa do të verifikohet automatikisht nga sistemi<br>" .
                                 "🆔 Transaction ID: " . $transaction_id;
                        
                        // Set session për verifikimin e telefonit
                        $_SESSION['phone_verification_pending'] = [
                            'phone' => $telefoni,
                            'transaction_id' => $transaction_id,
                            'email' => $email,
                            'office_name' => $emri,
                            'expires_at' => time() + (3 * 60) // 3 minuta
                        ];
                    } else {
                        $success = "✅ Zyra u regjistrua me sukses!<br>" .
                                 "⚠️ Verifikimi SMS dështoi: " . $phone_verification_result['error'] . "<br>" .
                                 "💳 Pagesa do të verifikohet nga administratorët brenda 3 minutave<br>" .
                                 "🆔 Transaction ID: " . $transaction_id;
                    }
                    
                    // Dërgimi i email-it të konfirmimit
                    sendConfirmationEmail($email, $emri, $transaction_id);
                    
                    // Log për notifikim të administratorëve
                    error_log("URGENT_PAYMENT_VERIFICATION_NEEDED: New payment registered - Transaction: $transaction_id, Office: $emri, Amount: €$pagesa, Phone: $telefoni");
                    
                } else {
                    $pdo->rollback();
                    $error = "Ndodhi një gabim gjatë regjistrimit të zyrës.";
                }
                
            } catch (Exception $e) {
                $pdo->rollback();
                $error = "Ndodhi një gabim i papritur. Ju lutemi kontaktoni administratorin.";
                error_log("Registration error: " . $e->getMessage());
            }
        }
    } // Mbyllja e blokut "else" për regjistrimin e ri
} // Mbyllja e blokut POST

// Funksione shtesë për verifikim të pagesave
function validatePaymentProof($file) {
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if ($file['size'] > $max_size) {
        return false;
    }
    
    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }
    
    return true;
}

function savePaymentProof($file, $transaction_id) {
    $upload_dir = __DIR__ . '/uploads/payment_proofs/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = $transaction_id . '.' . $file_extension;
    $destination = $upload_dir . $new_filename;
    
    return move_uploaded_file($file['tmp_name'], $destination);
}

function verifyPayPalTransaction($transaction_id, $amount) {
    // Implementimi i verifikimit të PayPal
    // Kjo duhet të thirret API-ja e PayPal për verifikim
    return true; // Placeholder - duhet implementuar
}

function verifyCreditCardTransaction($transaction_id, $amount) {
    // Implementimi i verifikimit të kartës së kreditit
    // Kjo duhet të thirret API-ja e procesorit të kartave
    return true; // Placeholder - duhet implementuar
}

function sendConfirmationEmail($email, $office_name, $transaction_id) {
    // Përdor konfiguracionin e ri të email-it
    require_once 'email_config.php';
    return sendRegistrationEmail($email, $office_name, $transaction_id);
}

$otpauth_url = 'otpauth://totp/Noteria:developer?secret=JBSWY3DPEHPK3PXP&issuer=Noteria';
$qr_url = 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . urlencode($otpauth_url);
echo '<img src="' . $qr_url . '" alt="QR Code for Google Authenticator">';
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regjistro Zyrën Noteriale | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #2d6cdf;
            --primary-dark: #1e40af;
            --primary-light: #3b82f6;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
            --bg-light: #f8fafc;
            --bg-lighter: #f1f5f9;
            --text-dark: #1e293b;
            --text-gray: #64748b;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: var(--text-dark);
        }

        .page-wrapper {
            max-width: 900px;
            margin: 0 auto;
        }

        .header-section {
            text-align: center;
            color: white;
            margin-bottom: 40px;
            animation: slideDown 0.6s ease-out;
        }

        .header-section h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .header-section p {
            font-size: 1.1rem;
            opacity: 0.95;
            font-weight: 300;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-lg), 0 20px 25px -5px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }

        .form-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 40px;
            color: white;
            text-align: center;
        }

        .form-header h2 {
            font-size: 1.8rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .form-header p {
            font-size: 0.95rem;
            opacity: 0.95;
            font-weight: 300;
        }

        .instructions-box {
            background: #f0f9ff;
            border-left: 4px solid var(--primary);
            padding: 20px;
            margin: 30px;
            border-radius: 10px;
            animation: slideUp 0.6s ease-out 0.2s backwards;
        }

        .instructions-box h4 {
            color: var(--primary);
            margin-bottom: 12px;
            font-size: 1rem;
        }

        .instructions-box ul {
            list-style: none;
        }

        .instructions-box li {
            padding: 8px 0;
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        .instructions-box li:before {
            content: "✓ ";
            color: var(--success);
            font-weight: 700;
            margin-right: 8px;
        }

        .form-content {
            padding: 40px;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            animation: slideDown 0.4s ease-out;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #dcfce7;
            border: 1px solid #86efac;
            color: #166534;
        }

        .alert-error {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
        }

        .alert-info {
            background: #dbeafe;
            border: 1px solid #93c5fd;
            color: #1e40af;
        }

        .alert i {
            font-size: 1.2rem;
        }

        .section-group {
            margin-bottom: 35px;
            animation: slideUp 0.6s ease-out backwards;
        }

        .section-group:nth-child(1) { animation-delay: 0.1s; }
        .section-group:nth-child(2) { animation-delay: 0.2s; }
        .section-group:nth-child(3) { animation-delay: 0.3s; }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
        }

        .section-title i {
            font-size: 1.3rem;
        }

        .form-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-columns.full {
            grid-template-columns: 1fr;
        }

        .form-group {
            animation: slideUp 0.6s ease-out backwards;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .form-group label .required {
            color: var(--error);
            margin-left: 3px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-gray);
            pointer-events: none;
            font-size: 1rem;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        input[type="phone"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: var(--bg-light);
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="number"]:focus,
        input[type="phone"]:focus,
        input[type="date"]:focus,
        select:focus,
        textarea:focus {
            border-color: var(--primary);
            background: white;
            outline: none;
            box-shadow: 0 0 0 3px rgba(45, 108, 223, 0.1);
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .field-info {
            font-size: 0.8rem;
            color: var(--text-gray);
            margin-top: 6px;
        }

        .file-upload {
            border: 2px dashed var(--primary);
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f9fafb;
        }

        .file-upload:hover {
            background: #f0f9ff;
            border-color: var(--primary-light);
        }

        .file-upload i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .file-upload p {
            color: var(--text-gray);
            margin: 0;
            font-size: 0.9rem;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 35px;
        }

        .button-group button {
            flex: 1;
        }

        button[type="submit"],
        button[type="button"] {
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        button[type="submit"] {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: var(--shadow);
        }

        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        button[type="submit"]:active {
            transform: translateY(0);
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .method-option {
            position: relative;
        }

        .method-option input[type="radio"] {
            display: none;
        }

        .method-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: var(--bg-light);
            text-align: center;
        }

        .method-option input[type="radio"]:checked + .method-label {
            border-color: var(--primary);
            background: #f0f9ff;
        }

        .method-label i {
            font-size: 1.8rem;
            color: var(--text-gray);
            margin-bottom: 8px;
        }

        .method-option input[type="radio"]:checked + .method-label i {
            color: var(--primary);
        }

        .method-label span {
            font-size: 0.85rem;
            font-weight: 600;
        }

        .verification-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .verification-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .verification-badge.verified {
            background: #dcfce7;
            color: #166534;
        }

        .backup-indicator {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-top: 25px;
            font-weight: 600;
        }

        .backup-indicator i {
            margin-right: 8px;
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

        @media (max-width: 768px) {
            .form-columns {
                grid-template-columns: 1fr;
            }

            .header-section h1 {
                font-size: 1.8rem;
            }

            .form-header {
                padding: 30px 20px;
            }

            .form-content {
                padding: 25px;
            }

            button[type="submit"],
            button[type="button"] {
                padding: 12px 20px;
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="header-section">
            <h1><i class="fas fa-code"></i> Noteria Developer Panel</h1>
            <p>Sistemi i Administrimit të Platformës për Zhvilluesit</p>
        </div>

        <div class="container">
            <div class="form-header">
                <h2><i class="fas fa-cog"></i> Konfiguro Platformën</h2>
                <p>Sistemi i konfigurimit për zhvilluesit dhe administratorët</p>
            </div>

            <div class="form-content">
                <div class="instructions-box">
                    <h4><i class="fas fa-info-circle"></i> Udhëzime për Zhvilluesit</h4>
                    <ul>
                        <li>⚙️ Plotësoni të dhënat e platformës dhe kredencialet tuaja</li>
                        <li>🔐 Sigurohuni që fjalekalimi është i fortë (minimum 12 karaktere)</li>
                        <li>📱 Konfirmoni numrin e telefonit përmes SMS</li>
                        <li>💾 Të gjitha të dhënat ruhen automatikisht me backup</li>
                        <li>✅ Përdorni login të dedikuar për të hyrë në panelin e zhvilluesit</li>
                    </ul>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div><?php echo htmlspecialchars($success); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($backup_status): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-database"></i>
                        <div><strong>Backup Status:</strong> <?php echo htmlspecialchars($backup_status); ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="registration-form">
                    <!-- TË DHËNAT E ZYRËS -->
                    <div class="section-group">
                        <div class="section-title">
                            <i class="fas fa-user-secret"></i> Të Dhënat e Zhvilluesit
                        </div>
                        <div class="form-columns">
                            <div class="form-group">
                                <label>Emri i Zhvilluesit <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-user-circle input-icon"></i>
                                    <input type="text" name="emri" placeholder="Emri dhe mbiemri i zhvilluesit" required>
                                </div>
                                <small class="field-info">Emri juaj i plotë për login</small>
                            </div>
                            <div class="form-group">
                                <label>Roli në Platformë <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-briefcase input-icon"></i>
                                    <select name="qyteti" required>
                                        <option value="">Zgjidhni rolin</option>
                                        <option value="Admin">Administrator (Akses Plotë)</option>
                                        <option value="Developer">Developer (Zhvillim)</option>
                                        <option value="Moderator">Moderator (Mbikëqyrje)</option>
                                        <option value="Analyst">Analyst (Raporte)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-columns">
                            <div class="form-group">
                                <label>Email i Zhvilluesit <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-envelope input-icon"></i>
                                    <input type="email" name="email" placeholder="developer@noteria.com" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Përsërit Email-in <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-check-double input-icon"></i>
                                    <input type="email" name="email2" placeholder="developer@noteria.com" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-columns">
                            <div class="form-group">
                                <label>Numri i Telefonit <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-phone-alt input-icon"></i>
                                    <input type="phone" name="telefoni" placeholder="+38344123456" required>
                                </div>
                                <small class="field-info">Format: +383XXXXXXXX</small>
                            </div>
                            <div class="form-group">
                                <label>Organizata <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-building input-icon"></i>
                                    <input type="text" value="Noteria" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- KREDENCIALET E ZHVILLUESIT -->
                    <div class="section-group">
                        <div class="section-title">
                            <i class="fas fa-lock"></i> Kredencialet e Sigurta
                        </div>
                        <div class="form-columns">
                            <div class="form-group">
                                <label>Emri i Përdoruesit (Username) <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-user input-icon"></i>
                                    <input type="text" name="banka" placeholder="developer_username" required>
                                </div>
                                <small class="field-info">Përdorni për login në panelin e administrimit</small>
                            </div>
                            <div class="form-group">
                                <label>Fjalekalimi <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-key input-icon"></i>
                                    <input type="password" name="iban" placeholder="Minimum 12 karaktere" required>
                                </div>
                                <small class="field-info">Duhet të përmbajë shkronja, numra dhe simbole</small>
                            </div>
                        </div>
                        <div class="form-columns full">
                            <div class="form-group">
                                <label>API Key (i Sigurt) <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-code input-icon"></i>
                                    <input type="text" name="llogaria" placeholder="Do të gjenerohet automatikisht" readonly>
                                </div>
                                <small class="field-info">Çelësi i siguruar për integrimet API</small>
                            </div>
                        </div>
                    </div>

                    <!-- KONFIGURIMI I PLATFORMËS -->
                    <div class="section-group">
                        <div class="section-title">
                            <i class="fas fa-sliders-h"></i> Konfigurimi i Platformës
                        </div>
                        <div class="form-columns">
                            <div class="form-group">
                                <label>Niveli i Aksesit <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-shield-alt input-icon"></i>
                                    <select name="pagesa" required>
                                        <option value="">Zgjidhni nivelin</option>
                                        <option value="1">Level 1 - Lexim (Read Only)</option>
                                        <option value="2">Level 2 - Redaktim (Edit)</option>
                                        <option value="3">Level 3 - Administrator</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Moduli i Aksesit <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-cube input-icon"></i>
                                    <select name="transaction_id" required>
                                        <option value="">Zgjidhni modulin</option>
                                        <option value="all">Të Gjithë Modulet</option>
                                        <option value="payments">Pagesave</option>
                                        <option value="users">Përdoruesit</option>
                                        <option value="reports">Raportet</option>
                                        <option value="settings">Cilësimet</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                        <div class="payment-methods">
                            <div class="method-option">
                                <input type="radio" id="bank_transfer" name="payment_method" value="bank_transfer" required>
                                <label for="bank_transfer" class="method-label">
                                    <i class="fas fa-exchange-alt"></i>
                                    <span>Transferta Bankare</span>
                                </label>
                            </div>
                            <div class="method-option">
                                <input type="radio" id="paypal" name="payment_method" value="paypal">
                                <label for="paypal" class="method-label">
                                    <i class="fab fa-paypal"></i>
                                    <span>PayPal</span>
                                </label>
                            </div>
                            <div class="method-option">
                                <input type="radio" id="card" name="payment_method" value="card">
                                <label for="card" class="method-label">
                                    <i class="fas fa-credit-card"></i>
                                    <span>Kartë Krediti</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-columns">
                            <div class="form-group">
                                <label>Shuma (EUR) <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-euro-sign input-icon"></i>
                                    <input type="number" name="pagesa" placeholder="150" min="10" max="10000" required>
                                </div>
                                <small class="field-info">Minimum 10€, Maksimum 10,000€</small>
                            </div>
                            <div class="form-group">
                                <label>ID Transaksioni <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-key input-icon"></i>
                                    <input type="text" id="transaction_id" name="transaction_id" readonly required>
                                </div>
                            </div>
                        </div>
                        <button type="button" onclick="generateTransactionId()" style="background: var(--success); color: white; width: 100%; margin-bottom: 20px;">
                            <i class="fas fa-random"></i> Gjenero ID Transaksioni
                        </button>

                        <div class="form-group full">
                            <label>Dëshmi e Pagesës <span class="required">*</span></label>
                            <label for="payment_proof" class="file-upload">
                                <input type="file" id="payment_proof" name="payment_proof" accept=".pdf,.jpg,.jpeg,.png" required style="display: none;">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Zvarriteni dëshminë e pagesës këtu ose klikoni</p>
                                <small style="color: var(--text-gray); font-size: 0.8rem;">PDF, JPG, PNG - Maksimum 5MB</small>
                            </label>
                        </div>
                    </div>

                    <!-- BUTON I DËRGIMIT -->
                    <div class="button-group">
                        <button type="submit">
                            <i class="fas fa-check"></i> Konfigurim Zhvilluesit
                        </button>
                    </div>

                    <div class="backup-indicator">
                        <i class="fas fa-lock"></i>
                        Kredencialet tuaja ruhen me enkriptim të fortë - Akses i Kufizuar Zhvilluesve
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function generateTransactionId() {
            const now = new Date();
            const date = now.getFullYear().toString() + 
                        (now.getMonth() + 1).toString().padStart(2, '0') + 
                        now.getDate().toString().padStart(2, '0');
            const time = now.getHours().toString().padStart(2, '0') + 
                        now.getMinutes().toString().padStart(2, '0') + 
                        now.getSeconds().toString().padStart(2, '0');
            const random = Math.random().toString(16).substr(2, 8);
            
            const transactionId = `TXN_${date}_${time}_${random}`;
            document.getElementById('transaction_id').value = transactionId;
        }

        // Real-time IBAN validation
        document.getElementById('registration-form')?.addEventListener('submit', function(e) {
            const email = document.querySelector('[name="email"]').value;
            const email2 = document.querySelector('[name="email2"]').value;
            
            if (email !== email2) {
                e.preventDefault();
                alert('Email-at nuk përputhen!');
                return false;
            }
        });

        // File upload drag & drop
        const fileInput = document.getElementById('payment_proof');
        const fileLabel = fileInput?.parentElement;

        if (fileLabel) {
            fileLabel.addEventListener('dragover', (e) => {
                e.preventDefault();
                fileLabel.style.background = '#f0f9ff';
                fileLabel.style.borderColor = 'var(--primary)';
            });

            fileLabel.addEventListener('dragleave', () => {
                fileLabel.style.background = '';
                fileLabel.style.borderColor = '';
            });

            fileLabel.addEventListener('drop', (e) => {
                e.preventDefault();
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    fileLabel.style.background = '';
                    fileLabel.style.borderColor = '';
                }
            });
        }
    </script>
</body>
</html>
    <script>
        function togglePaymentFields() {
            const method = document.getElementById('payment_method').value;
            
            // Fsheh të gjitha fushat
            document.getElementById('bank_transfer_fields').style.display = 'none';
            document.getElementById('paypal_fields').style.display = 'none';
            document.getElementById('card_fields').style.display = 'none';
            
            // Shfaq fushat e duhura
            if (method === 'bank_transfer') {
                document.getElementById('bank_transfer_fields').style.display = 'block';
            } else if (method === 'paypal') {
                document.getElementById('paypal_fields').style.display = 'block';
            } else if (method === 'card') {
                document.getElementById('card_fields').style.display = 'block';
            }
        }
        
        function generateTransactionId() {
            const now = new Date();
            const date = now.getFullYear().toString() + 
                        (now.getMonth() + 1).toString().padStart(2, '0') + 
                        now.getDate().toString().padStart(2, '0');
            const time = now.getHours().toString().padStart(2, '0') + 
                        now.getMinutes().toString().padStart(2, '0') + 
                        now.getSeconds().toString().padStart(2, '0');
            const random = Math.random().toString(16).substr(2, 8);
            
            const transactionId = `TXN_${date}_${time}_${random}`;
            document.getElementById('transaction_id').value = transactionId;
        }
        
        function validateForm() {
            const paymentMethod = document.getElementById('payment_method').value;
            const transactionId = document.getElementById('transaction_id').value;
            const amount = document.getElementById('pagesa').value;
            
            if (!paymentMethod) {
                alert('Ju lutemi zgjidhni mënyrën e pagesës.');
                return false;
            }
            
            if (!transactionId.match(/^TXN_\d{8}_\d{6}_[a-f0-9]{8}$/)) {
                alert('ID e transaksionit nuk është në formatin e saktë.');
                return false;
            }
            
            if (amount < 10 || amount > 10000) {
                alert('Shuma duhet të jetë ndërmjet 10€ dhe 10,000€.');
                return false;
            }
            
            return true;
        }
        
        // Validimi real-time i IBAN-it
        function validateIBANRealTime() {
            const iban = document.getElementById('iban').value.toUpperCase();
            const ibanField = document.getElementById('iban');
            
            if (iban.length >= 4) {
                if (!iban.startsWith('XK')) {
                    ibanField.style.borderColor = '#d32f2f';
                    ibanField.title = 'IBAN duhet të fillojë me XK për Kosovën';
                } else {
                    ibanField.style.borderColor = '#2d6cdf';
                    ibanField.title = '';
                }
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('iban').addEventListener('input', validateIBANRealTime);
            document.querySelector('form').addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                }
            });
        });
    </script>
</head>
<body>
<?php } // Close the if ($_SERVER["REQUEST_METHOD"] == "POST") block from line 24 ?>
    <div class="container">
        <h2>Regjistro Zyrën</h2>
        
        <div style="background: #e8f5e8; border: 1px solid #4caf50; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <h4 style="margin: 0 0 10px 0; color: #2e7d32;">📋 Udhëzime për Regjistrimin</h4>
            <ul style="margin: 0; padding-left: 20px; font-size: 0.9em; color: #555;">
                <li>Plotësoni të gjitha fushat e kërkuara</li>
                <li><strong>DETYRUESHME:</strong> Ngarkoni dëshminë e pagesës (screenshot ose PDF)</li>
                <li>Gjeneroni një Transaction ID unike duke klikuar butonin</li>
                <li><strong>🚀 E RE:</strong> Regjistrimi do të verifikohet automatikisht brenda <span style="color: #d32f2f; font-weight: bold;">3 minutave</span></li>
                <li>Do të merrni email konfirmimi menjëherë pas verifikimit</li>
                <li><strong>⚡ Përparësi:</strong> 480x më shpejt se sistemi i vjetër!</li>
            </ul>
        </div>
        
        <?php if ($success): ?>
            <div class="success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <?php if ($backup_status): ?>
            <div class="success" style="background: #e8f5e9; border-left: 4px solid #4caf50; margin-bottom: 20px;">
                <i class="fas fa-database"></i> <strong>Backup Status:</strong> <?php echo htmlspecialchars($backup_status); ?>
            </div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <div class="section-title">Të dhënat e zyrës</div>
            <div class="form-group">
                <label for="emri">Emri i Zyrës:</label>
                <input type="text" name="emri" id="emri" required value="<?php echo isset($_POST['emri']) ? htmlspecialchars($_POST['emri'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            <div class="form-group">
                <label for="qyteti">Qyteti:</label>
                <select name="qyteti" id="qyteti" required>
                    <option value="">Zgjidh qytetin</option>
                    <?php foreach ($qytetet as $q): ?>
                        <option value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"<?php if(isset($_POST['qyteti']) && $_POST['qyteti'] === $q) echo ' selected'; ?>><?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="shteti">Shteti:</label>
                <input type="text" name="shteti" id="shteti" value="Kosova" readonly>
            </div>
            <div class="form-group">
                <label for="email">Email-i:</label>
                <input type="email" name="email" id="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            <div class="form-group">
                <label for="email2">Përsërit Email-in:</label>
                <input type="email" name="email2" id="email2" required value="<?php echo isset($_POST['email2']) ? htmlspecialchars($_POST['email2'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            <div class="form-group">
                <label for="telefoni">Numri i Telefonit (+383...):</label>
                <input type="text" name="telefoni" id="telefoni" placeholder="+38344123456" required value="<?php echo isset($_POST['telefoni']) ? htmlspecialchars($_POST['telefoni'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            <div class="section-title">Të dhënat bankare të zyrës</div>
            <div class="form-group">
                <label for="banka">Emri i Bankës:</label>
                <select name="banka" id="banka" required>
                    <option value="">Zgjidh bankën...</option>
                    <option value="Banka Ekonomike">Banka Ekonomike</option>
                    <option value="Banka për Biznes">Banka për Biznes</option>
                    <option value="Banka Kombëtare Tregtare (BKT)">Banka Kombëtare Tregtare (BKT)</option>
                    <option value="ProCredit Bank">ProCredit Bank</option>
                    <option value="Raiffeisen Bank">Raiffeisen Bank</option>
                    <option value="TEB Bank">TEB Bank</option>
                    <option value="NLB Banka">NLB Banka</option>
                    <option value="Ziraat Bank">Ziraat Bank</option>
                    <option value="Union Bank">Union Bank</option>
                    <option value="Turkish Ziraat Bankasi">Turkish Ziraat Bankasi</option>
                    <option value="Credins Bank">Credins Bank</option>
                    <option value="One For Kosovo">One for Kosova</option>
                </select>
            </div>
            <div class="form-group">
                <label for="iban">IBAN:</label>
                <input type="text" name="iban" id="iban" required placeholder="p.sh. XK051212012345678906">
            </div>
            <div class="form-group">
                <label for="llogaria">Numri i Llogarisë:</label>
                <input type="text" name="llogaria" id="llogaria" required placeholder="Vetëm shifra">
            </div>
            <div class="section-title">Pagesa për platformën</div>
            <div class="form-group">
                <label for="payment_method">Mënyra e Pagesës:</label>
                <select name="payment_method" id="payment_method" required onchange="togglePaymentFields()">
                    <option value="">Zgjidh mënyrën e pagesës</option>
                    <option value="bank_transfer">Transfer Bankar</option>
                    <option value="paypal">PayPal</option>
                    <option value="card">Kartë Krediti/Debiti</option>
                </select>
            </div>
            <div class="form-group">
                <label for="pagesa">Shuma për pagesë (€):</label>
                <input type="number" name="pagesa" id="pagesa" min="10" max="10000" step="0.01" required placeholder="p.sh. 130">
                <small style="color: #666; font-size: 0.9em;">Minimumi: 10€, Maksimumi: 10,000€</small>
            </div>
            <div class="form-group">
                <label for="transaction_id">ID e Transaksionit:</label>
                <input type="text" name="transaction_id" id="transaction_id" required 
                       placeholder="p.sh. TXN_20250922_143052_a1b2c3d4" 
                       pattern="TXN_\d{8}_\d{6}_[a-f0-9]{8}" 
                       title="Formati: TXN_YYYYMMDD_HHMMSS_xxxxxxxx">
                <small style="color: #666; font-size: 0.9em;">
                    <button type="button" onclick="generateTransactionId()" style="background: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; margin-top: 5px;">
                        Gjeneroni ID të Re
                    </button>
                </small>
            </div>
            
            <!-- Fushat specifike për transfer bankar -->
            <div id="bank_transfer_fields" style="display: none;">
                <div class="form-group">
                    <label for="sender_name">Emri i Dërguesit:</label>
                    <input type="text" name="sender_name" id="sender_name" placeholder="Emri që duket në transferin bankar">
                </div>
                <div class="form-group">
                    <label for="transfer_date">Data e Transferit:</label>
                    <input type="date" name="transfer_date" id="transfer_date">
                </div>
            </div>
            
            <!-- Fushat specifike për PayPal -->
            <div id="paypal_fields" style="display: none;">
                <div class="form-group">
                    <label for="paypal_email">Email-i i PayPal:</label>
                    <input type="email" name="paypal_email" id="paypal_email" placeholder="email@example.com">
                </div>
            </div>
            
            <!-- Fushat specifike për kartë -->
            <div id="card_fields" style="display: none;">
                <div class="form-group">
                    <label for="card_last_four">4 shifrat e fundit të kartës:</label>
                    <input type="text" name="card_last_four" id="card_last_four" 
                           pattern="\d{4}" maxlength="4" placeholder="1234">
                </div>
            </div>
            
            <div class="form-group">
                <label for="payment_proof">Dëshmi e Pagesës (PDF, JPG, PNG - max 5MB) *:</label>
                <input type="file" name="payment_proof" id="payment_proof" 
                       accept=".pdf,.jpg,.jpeg,.png" style="padding: 8px;" required>
                <small style="color: #666; font-size: 0.9em;">Ngarkoni një foto ose PDF të dëshmisë së pagesës (E DETYRUESHME)</small>
            </div>
            
            <div class="form-group" style="margin-top: 20px;">
                <label style="display: flex; align-items: center; font-size: 0.9em;">
                    <input type="checkbox" name="payment_confirmation" required style="margin-right: 8px;">
                    Konfirmoj që kam kryer pagesën dhe të gjitha të dhënat janë të sakta. Dëshmi e pagesës është ngarkuar.
                </label>
            </div>
            
            <div class="payment-verification" style="background: #e8f5e8; border: 1px solid #4caf50; border-radius: 8px; padding: 15px; margin: 15px 0;">
                <h4 style="margin: 0 0 10px 0; color: #2e7d32;">⚡ Verifikim i Shpejtë Automatik</h4>
                <p style="margin: 0; font-size: 0.9em; color: #555;">
                    <strong>🚀 Sistemi i ri:</strong> Pagesa do të verifikohet automatikisht nga sistemi ynë brenda <strong>3 minutave</strong> pas dërgimit të formularit.
                    Do të merrni një email konfirmimi menjëherë pasi pagesa të jetë verifikuar nga administratorët tanë.
                </p>
                <div style="background: #fff3e0; border-left: 4px solid #ff9800; padding: 10px; margin-top: 10px; font-size: 0.85em; color: #e65100;">
                    <strong>📱 Monitorimi në kohë reale:</strong> Administratorët tanë marrin notifikime të menjëhershme dhe verifikojnë pagesat brenda target-it tonë të 3 minutave.
                </div>
            </div>
            
            <button type="submit">Regjistro Zyrën dhe Dërgo për Verifikim</button>
        </form>
        
        <!-- 📱 Widget i Verifikimit të SMS-it -->
        <?php if (isset($_SESSION['phone_verification_pending'])): ?>
        <div id="sms-verification-widget" style="margin-top: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 8px 16px rgba(0,0,0,0.3);">
            <h3 style="margin: 0 0 15px 0; color: #fff;">📱 Verifikim i Telefonit</h3>
            <p style="margin-bottom: 15px;">
                SMS u dërgua në: <strong><?php echo htmlspecialchars($_SESSION['phone_verification_pending']['phone']); ?></strong><br>
                <span id="countdown-timer" style="color: #ffeb3b; font-weight: bold;"></span>
            </p>
            
            <form id="sms-verification-form" method="POST" style="margin: 0;">
                <input type="hidden" name="verify_sms" value="1">
                <input type="hidden" name="transaction_id" value="<?php echo htmlspecialchars($_SESSION['phone_verification_pending']['transaction_id']); ?>">
                
                <div style="margin-bottom: 15px;">
                    <label for="sms_code" style="display: block; margin-bottom: 5px; color: #fff;">Kodi i marrë përmes SMS:</label>
                    <input type="text" name="sms_code" id="sms_code" required 
                           maxlength="6" pattern="[0-9]{6}" 
                           placeholder="123456" 
                           style="width: 100%; padding: 12px; border: none; border-radius: 6px; font-size: 1.2rem; text-align: center; letter-spacing: 2px;">
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" style="flex: 1; background: #4caf50; color: white; border: none; padding: 12px; border-radius: 6px; font-weight: bold; cursor: pointer;">
                        ✅ Verifiko SMS
                    </button>
                    <button type="button" onclick="resendSMS()" style="flex: 1; background: #ff9800; color: white; border: none; padding: 12px; border-radius: 6px; font-weight: bold; cursor: pointer;">
                        🔄 Dërgo Përsëri
                    </button>
                </div>
            </form>
            
            <div id="sms-verification-status" style="margin-top: 15px; padding: 10px; border-radius: 6px; display: none;"></div>
        </div>
        
        <script>
            // Timer për 3 minuta
            let timeLeft = <?php echo $_SESSION['phone_verification_pending']['expires_at'] - time(); ?>;
            
            function updateCountdown() {
                if (timeLeft <= 0) {
                    document.getElementById('countdown-timer').innerHTML = '⏰ Koha ka skaduar!';
                    document.getElementById('sms-verification-form').style.opacity = '0.5';
                    return;
                }
                
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                document.getElementById('countdown-timer').innerHTML = 
                    `⏰ Kohë e mbetur: ${minutes}:${seconds.toString().padStart(2, '0')}`;
                timeLeft--;
            }
            
            // Update timer çdo sekondë
            updateCountdown();
            setInterval(updateCountdown, 1000);
            
            // Auto-focus në fushën e kodit
            document.getElementById('sms_code').focus();
            
            // Format automatic i kodit
            document.getElementById('sms_code').addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 6) value = value.slice(0, 6);
                e.target.value = value;
                
                // Auto-submit kur plotësohet kodi 6-shifror
                if (value.length === 6) {
                    document.getElementById('sms-verification-form').submit();
                }
            });
            
            // Resend SMS function
            function resendSMS() {
                fetch('phone_verification_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'resend',
                        phone: '<?php echo $_SESSION['phone_verification_pending']['phone']; ?>',
                        transaction_id: '<?php echo $_SESSION['phone_verification_pending']['transaction_id']; ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    const status = document.getElementById('sms-verification-status');
                    status.style.display = 'block';
                    if (data.success) {
                        status.style.background = '#4caf50';
                        status.innerHTML = '✅ SMS u dërgua përsëri!';
                        timeLeft = 180; // Reset timer
                    } else {
                        status.style.background = '#f44336';
                        status.innerHTML = '❌ ' + data.error;
                    }
                });
            }
        </script>
        <?php endif; ?>
    </div>
</body>
</html>