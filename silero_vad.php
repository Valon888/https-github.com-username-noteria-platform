<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php'; // ose db_connection.php nëse $pdo inicializohet aty
// =================== REGJISTRIM PERDORUESI ME OTP (UI & VALIDIME) ===================
if (isset($_GET['register'])) {
    $ga = new PHPGangsta_GoogleAuthenticator();
    $reg_error = $reg_success = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['reg_username'] ?? '');
    $emri = trim($_POST['reg_emri'] ?? '');
    $mbiemri = trim($_POST['reg_mbiemri'] ?? '');
    $telefoni = trim($_POST['reg_telefoni'] ?? '');
    $email = trim($_POST['reg_email'] ?? '');
        $password = $_POST['reg_password'] ?? '';
        $password2 = $_POST['reg_password2'] ?? '';
        // Validime bazë
        if (strlen($username) < 4) {
            if (strlen($emri) < 2) {
                $reg_error = 'Emri duhet të ketë të paktën 2 karaktere.';
            } elseif (strlen($mbiemri) < 2) {
                $reg_error = 'Mbiemri duhet të ketë të paktën 2 karaktere.';
            } elseif (strlen($telefoni) < 7) {
                $reg_error = 'Telefoni duhet të ketë të paktën 7 shifra.';
            } elseif (isset($_POST['reg_email']) && trim($_POST['reg_email']) !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $reg_error = 'Email nuk është i vlefshëm.';
            }
        } elseif (strlen($password) < 8) {
            $reg_error = 'Fjalëkalimi duhet të ketë të paktën 8 karaktere.';
        } elseif ($password !== $password2) {
            $reg_error = 'Fjalëkalimet nuk përputhen.';
        } else {
            // Kontrollo nëse email ekziston
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $reg_error = 'Email ekziston.';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $otp_secret = $ga->createSecret();
                // Gjenero backup codes (8 kode, 10 karaktere secili)
                $backup_codes = [];
                for ($i = 0; $i < 8; $i++) {
                    $backup_codes[] = substr(bin2hex(random_bytes(8)), 0, 10);
                }
                $backup_codes_json = json_encode($backup_codes);
                $stmt = $pdo->prepare("INSERT INTO users (emri, mbiemri, telefoni, email, password_hash, otp_secret, mfa_enabled, backup_codes) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
                $stmt->execute([$emri, $mbiemri, $telefoni, $email, $password_hash, $otp_secret, $backup_codes_json]);
                $reg_success = '<h3 style="color:#388e3c">Regjistrimi u krye me sukses!</h3>';
                $reg_success .= 'Skano këtë QR code me Google Authenticator:<br>';
                $reg_success .= '<img src="'.$qrCodeUrl.'" style="margin:10px 0;" />';
                $reg_success .= '<br>Ose përdor këtë secret: <b>'.$otp_secret.'</b>';
                $reg_success .= '<br><b>Backup Codes (ruaji diku të sigurt, përdoren vetëm një herë):</b><br>';
                foreach ($backup_codes as $code) {
                    $reg_success .= '<div style="font-family:monospace;color:#184fa3;font-size:1.1em;margin-bottom:2px;">'.$code.'</div>';
                }
                $reg_success .= '<br><a href="silero_vad.php" style="color:#1976d2">Kthehu te login</a>';
            }
        }
    }
    // Forma e regjistrimit me UI të përmirësuar
    echo '<div style="max-width:420px;margin:40px auto;padding:24px 28px;border-radius:10px;border:1px solid #e0e0e0;background:#fafbfc;box-shadow:0 2px 8px #eee;">';
    echo '<h2 style="text-align:center;color:#1976d2;margin-bottom:18px;">Regjistrohu me MFA</h2>';
    if ($reg_error) echo '<div style="color:#c62828;background:#ffebee;padding:8px 12px;border-radius:5px;margin-bottom:12px;">'.$reg_error.'</div>';
    if ($reg_success) { echo '<div style="background:#e8f5e9;padding:12px 16px;border-radius:5px;text-align:center;">'.$reg_success.'</div>'; } else {
    echo '<form method="post">';
    echo '<label>Emri</label><input type="text" name="reg_emri" value="'.htmlspecialchars($_POST['reg_emri'] ?? '').'" required minlength="2" class="form-control" style="margin-bottom:10px;width:100%;padding:8px;">';
    echo '<label>Mbiemri</label><input type="text" name="reg_mbiemri" value="'.htmlspecialchars($_POST['reg_mbiemri'] ?? '').'" required minlength="2" class="form-control" style="margin-bottom:10px;width:100%;padding:8px;">';
    echo '<label>Telefoni</label><input type="text" name="reg_telefoni" value="'.htmlspecialchars($_POST['reg_telefoni'] ?? '').'" required minlength="7" class="form-control" style="margin-bottom:10px;width:100%;padding:8px;">';
    echo '<label>Email</label><input type="email" name="reg_email" value="'.htmlspecialchars($_POST['reg_email'] ?? '').'" required class="form-control" style="margin-bottom:10px;width:100%;padding:8px;">';
    echo '<label>Fjalëkalimi</label><input type="password" name="reg_password" required minlength="8" class="form-control" style="margin-bottom:10px;width:100%;padding:8px;">';
    echo '<label>Përsërit fjalëkalimin</label><input type="password" name="reg_password2" required minlength="8" class="form-control" style="margin-bottom:16px;width:100%;padding:8px;">';
    echo '<button type="submit" class="btn btn-success" style="width:100%;padding:10px 0;background:#1976d2;color:#fff;font-size:16px;border:none;border-radius:5px;">Regjistrohu</button>';
    echo '</form>';
    }
    echo '</div>';
    exit;
}
// =================== LOGIN ME HASHING DHE MFA (OTP) ===================
if (isset($_POST['login_username'], $_POST['login_password'], $_POST['login_otp'])) {
    $username = $_POST['login_username'];
    $password = $_POST['login_password'];
    $otp = $_POST['login_otp'];

    // RATE LIMITING: max 5 tentativa në 10 min për username
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM audit_log WHERE username = ? AND action = "login_attempt" AND created_at > (NOW() - INTERVAL 10 MINUTE)');
    $stmt->execute([$username]);
    $attempts = $stmt->fetchColumn();
    if ($attempts >= 5) {
        $error = 'Shumë tentativa login! Provo pas 10 minutash.';
        // Audit log: rate limit triggered
        $stmt = $pdo->prepare('INSERT INTO audit_log (username, ip, action, status, created_at) VALUES (?, ?, "login_rate_limit", "fail", NOW())');
        $stmt->execute([$username, $ip]);
    } else {
        // Merr nga databaza hash-in, secret-in dhe backup codes për këtë përdorues
        $stmt = $pdo->prepare('SELECT id, password_hash, otp_secret, backup_codes FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $login_status = 'fail';
        $login_type = '';
        if ($user && password_verify($password, $user['password_hash'])) {
            $ga = new PHPGangsta_GoogleAuthenticator();
            $otp_valid = $ga->verifyCode($user['otp_secret'], $otp, 2); // 2x30 sekonda tolerancë
            $backup_codes = $user['backup_codes'] ? json_decode($user['backup_codes'], true) : [];
            $backup_code_used = false;
            if (!$otp_valid && in_array($otp, $backup_codes)) {
                // Përdor backup code (hiqe nga lista)
                $backup_codes = array_values(array_diff($backup_codes, [$otp]));
                $backup_code_used = true;
                // Ruaj backup codes të reja
                $stmt = $pdo->prepare('UPDATE users SET backup_codes = ? WHERE id = ?');
                $stmt->execute([json_encode($backup_codes), $user['id']]);
            }
            if ($otp_valid || $backup_code_used) {
                $_SESSION['user_id'] = $user['id'];
                $success = $otp_valid ? 'Login me sukses (MFA)!' : 'Login me sukses me backup code!';
                $login_status = 'success';
                $login_type = $otp_valid ? 'otp' : 'backup_code';
            } else {
                $error = 'OTP ose backup code gabim!';
                $login_type = $otp_valid ? 'otp' : 'backup_code';
            }
        } else {
            $error = 'Fjalëkalimi ose përdoruesi gabim!';
            $login_type = 'password';
        }
        // Audit log: çdo tentativë login
        $stmt = $pdo->prepare('INSERT INTO audit_log (username, ip, action, status, login_type, created_at) VALUES (?, ?, "login_attempt", ?, ?, NOW())');
        $stmt->execute([$username, $ip, $login_status, $login_type]);
    }
}

// =================== FORM LOGIN ME MFA (UI & VALIDIME) ===================
if (!isset($_SESSION['user_id'])) {
    echo '<div style="max-width:420px;margin:40px auto;padding:24px 28px;border-radius:10px;border:1px solid #e0e0e0;background:#fafbfc;box-shadow:0 2px 8px #eee;">';
    echo '<h2 style="text-align:center;color:#1976d2;margin-bottom:18px;">Kyçu me MFA</h2>';
    if (isset($error)) echo '<div style="color:#c62828;background:#ffebee;padding:8px 12px;border-radius:5px;margin-bottom:12px;">'.$error.'</div>';
    if (isset($success)) echo '<div style="background:#e8f5e9;padding:12px 16px;border-radius:5px;text-align:center;margin-bottom:12px;">'.$success.'</div>';
        echo '<form method="post">';
        echo '<label>Email</label><input type="email" name="login_email" required class="form-control" style="margin-bottom:10px;width:100%;padding:8px;">';
    echo '<label>Fjalëkalimi</label><input type="password" name="login_password" required class="form-control" style="margin-bottom:10px;width:100%;padding:8px;">';
    echo '<label>OTP nga Google Authenticator</label><input type="text" name="login_otp" required class="form-control" style="margin-bottom:16px;width:100%;padding:8px;">';
    echo '<button type="submit" class="btn btn-primary" style="width:100%;padding:10px 0;background:#1976d2;color:#fff;font-size:16px;border:none;border-radius:5px;">Kyçu</button>';
    echo '<div style="margin-top:18px;text-align:center;">Nuk ke llogari? <a href="?register" style="color:#1976d2;">Regjistrohu</a></div>';
    echo '</form>';
    echo '</div>';
    exit;
}
// =================== UI për veprime kritike me MFA ===================
if (isset($_SESSION['user_id'])) {
    echo '<div style="max-width:520px;margin:40px auto 0 auto;padding:32px 36px;border-radius:14px;border:1px solid #e0e0e0;background:#f5f8fc;box-shadow:0 4px 18px #2d6cdf22;">';
    echo '<h2 style="text-align:center;color:#1976d2;margin-bottom:24px;">Veprime kritike të mbrojtura me MFA</h2>';
    if (isset($error) && $error) echo '<div style="color:#d32f2f;background:#ffeaea;padding:10px 16px;border-radius:7px;margin-bottom:18px;text-align:center;font-weight:600;">'.htmlspecialchars($error).'</div>';
    if (isset($success) && $success) echo '<div style="color:#388e3c;background:#eafaf1;padding:10px 16px;border-radius:7px;margin-bottom:18px;text-align:center;font-weight:600;">'.htmlspecialchars($success).'</div>';
    // Ndryshim email
    echo '<form method="post" style="margin-bottom:28px;background:#fff;border-radius:10px;padding:18px 22px;box-shadow:0 2px 8px #e0eafc;">';
    echo '<h3 style="color:#184fa3;margin-bottom:12px;">Ndrysho Email</h3>';
    echo '<label>Email i ri</label><input type="email" name="new_email" required class="form-control" style="margin-bottom:10px;width:100%;padding:8px;">';
    echo '<label>OTP/Backup Code</label><input type="text" name="otp_code" required class="form-control" style="margin-bottom:16px;width:100%;padding:8px;">';
    echo '<button type="submit" name="change_email" class="btn btn-primary" style="width:100%;padding:10px 0;background:#1976d2;color:#fff;font-size:16px;border:none;border-radius:6px;">Ndrysho Email</button>';
    echo '</form>';
    // Ndryshim fjalëkalimi
    echo '<form method="post" style="margin-bottom:28px;background:#fff;border-radius:10px;padding:18px 22px;box-shadow:0 2px 8px #e0eafc;">';
    echo '<h3 style="color:#184fa3;margin-bottom:12px;">Ndrysho Fjalëkalimin</h3>';
    echo '<label>Fjalëkalimi i ri</label><input type="password" name="new_password" required minlength="8" class="form-control" style="margin-bottom:10px;width:100%;padding:8px;">';
    echo '<label>Përsërit fjalëkalimin</label><input type="password" name="new_password2" required minlength="8" class="form-control" style="margin-bottom:10px;width:100%;padding:8px;">';
    echo '<label>OTP/Backup Code</label><input type="text" name="otp_code" required class="form-control" style="margin-bottom:16px;width:100%;padding:8px;">';
    echo '<button type="submit" name="change_password" class="btn btn-success" style="width:100%;padding:10px 0;background:#388e3c;color:#fff;font-size:16px;border:none;border-radius:6px;">Ndrysho Fjalëkalimin</button>';
    echo '</form>';
    // Eksport PDF
    echo '<form method="post" style="background:#fff;border-radius:10px;padding:18px 22px;box-shadow:0 2px 8px #e0eafc;">';
    echo '<h3 style="color:#184fa3;margin-bottom:12px;">Eksporto Transkript si PDF</h3>';
    echo '<label>OTP/Backup Code</label><input type="text" name="otp_code" required class="form-control" style="margin-bottom:16px;width:100%;padding:8px;">';
    echo '<button type="submit" name="export_pdf" class="btn btn-info" style="width:100%;padding:10px 0;background:#2d6cdf;color:#fff;font-size:16px;border:none;border-radius:6px;">Eksporto PDF</button>';
    echo '</form>';
    echo '</div>';
}
?>
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// silero_vad.php - Platformë e avancuar për Policinë e Kosovës
// session_start();
// if (!isset($_SESSION['user_id'])) {
//     header('Location: login.php');
//     exit();
// }
require_once 'config.php';

// Funksion: Ruaj transkriptin në databazë
function save_transcript($pdo, $user_id, $filename, $transcript, $lang, $speakers, $keywords) {
    $stmt = $pdo->prepare("INSERT INTO transcripts (user_id, filename, transcript, lang, speakers, keywords, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$user_id, $filename, $transcript, $lang, $speakers, $keywords]);
}
// Funksion: Kërko transkripte
function search_transcripts($pdo, $query) {
    $stmt = $pdo->prepare("SELECT * FROM transcripts WHERE transcript LIKE ? ORDER BY created_at DESC");
    $stmt->execute(['%' . $query . '%']);
    return $stmt->fetchAll();
}
// Funksion: Listo të gjitha transkriptet
function list_transcripts($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM transcripts WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}
// Funksion: Eksporto PDF (placeholder)
// Funksion: Eksporto PDF (placeholder) me MFA
function export_pdf($transcript) {
    // Këtu mund të përdorësh FPDF ose librari tjetër për PDF
    return false;
}

// Funksion: MFA verifikim për veprime kritike
function verify_mfa_action($pdo, $user_id, $otp) {
    $stmt = $pdo->prepare('SELECT otp_secret, backup_codes FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) return false;
    $ga = new PHPGangsta_GoogleAuthenticator();
    $otp_valid = $ga->verifyCode($user['otp_secret'], $otp, 2);
    $backup_codes = $user['backup_codes'] ? json_decode($user['backup_codes'], true) : [];
    $backup_code_used = false;
    if (!$otp_valid && in_array($otp, $backup_codes)) {
        $backup_codes = array_values(array_diff($backup_codes, [$otp]));
        $backup_code_used = true;
        $stmt = $pdo->prepare('UPDATE users SET backup_codes = ? WHERE id = ?');
        $stmt->execute([json_encode($backup_codes), $user_id]);
    }
    return $otp_valid || $backup_code_used;
}
// =================== MFA për veprime kritike ===================
if (isset($_POST['change_email']) && isset($_SESSION['user_id'])) {
    $new_email = trim($_POST['new_email'] ?? '');
    $otp = $_POST['otp_code'] ?? '';
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email i ri nuk është i vlefshëm!';
    } elseif (!verify_mfa_action($pdo, $_SESSION['user_id'], $otp)) {
        $error = 'OTP ose backup code gabim!';
    } else {
        $stmt = $pdo->prepare('UPDATE users SET email = ? WHERE id = ?');
        $stmt->execute([$new_email, $_SESSION['user_id']]);
        $success = 'Email u ndryshua me sukses!';
        // Audit log
        $stmt = $pdo->prepare('INSERT INTO audit_log (username, ip, action, status, created_at) VALUES ((SELECT email FROM users WHERE id = ?), ?, "change_email", "success", NOW())');
        $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR'] ?? '']);
    }
}

if (isset($_POST['change_password']) && isset($_SESSION['user_id'])) {
    $new_password = $_POST['new_password'] ?? '';
    $new_password2 = $_POST['new_password2'] ?? '';
    $otp = $_POST['otp_code'] ?? '';
    if (strlen($new_password) < 8) {
        $error = 'Fjalëkalimi i ri duhet të ketë të paktën 8 karaktere!';
    } elseif ($new_password !== $new_password2) {
        $error = 'Fjalëkalimet nuk përputhen!';
    } elseif (!verify_mfa_action($pdo, $_SESSION['user_id'], $otp)) {
        $error = 'OTP ose backup code gabim!';
    } else {
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$password_hash, $_SESSION['user_id']]);
        $success = 'Fjalëkalimi u ndryshua me sukses!';
        // Audit log
        $stmt = $pdo->prepare('INSERT INTO audit_log (username, ip, action, status, created_at) VALUES ((SELECT email FROM users WHERE id = ?), ?, "change_password", "success", NOW())');
        $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR'] ?? '']);
    }
}

if (isset($_POST['export_pdf']) && isset($_SESSION['user_id'])) {
    $otp = $_POST['otp_code'] ?? '';
    if (!verify_mfa_action($pdo, $_SESSION['user_id'], $otp)) {
        $error = 'OTP ose backup code gabim!';
    } else {
        // Këtu mund të shtosh logjikën për eksport PDF
        $success = 'Eksporti PDF u lejua me MFA!';
        // Audit log
        $stmt = $pdo->prepare('INSERT INTO audit_log (username, ip, action, status, created_at) VALUES ((SELECT username FROM users WHERE id = ?), ?, "export_pdf", "success", NOW())');
        $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR'] ?? '']);
        $stmt = $pdo->prepare('INSERT INTO audit_log (username, ip, action, status, created_at) VALUES ((SELECT email FROM users WHERE id = ?), ?, "export_pdf", "success", NOW())');
        $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR'] ?? '']);
    }
}

// --- Analizë e avancuar e sjelljes në transkript ---
function analyze_behavior_advanced($text) {
    $patterns = [
        // Fjalë/fraza kërcënuese
        '/(do të pësosh|do të pendohesh|nëse nuk bën|do të të gjej|do të hakmerrem|do të të ndjek|do të të shkatërroj)/i' => 'Kërcënim',
        // Fyerje të rënda
        '/(idiot|budalla|injorant|plehrë|maskara|qen|kafshë|shtrigë|rrugaç|hajdut|kriminel)/i' => 'Fyerje',
        // Diskriminim
        '/(racist|homofob|seksist|i/e paafte|i/e papërshtatshme|i/e ulët|i/e neveritshme)/i' => 'Diskriminim',
        // Shantazh
        '/(nëse nuk paguan|do të publikoj|do të tregoj të gjithëve|do të nxjerr sekretet|do të të turpëroj)/i' => 'Shantazh',
        // Ngacmim seksual
        '/(do të të prek|do të të zhvesh|do të të përdhunoj|seks|orgji|lakuriq|të zhveshur|të prek|të puth|të ngacmoj)/i' => 'Ngacmim seksual',
        // Manipulim
        '/(nëse më do|vetëm ti mundesh|askush tjetër nuk të ndihmon|vetëm unë të dua|nëse nuk më dëgjon|nëse nuk më bindesh)/i' => 'Manipulim',
        // Dhunë verbale
        '/(do të të godas|do të të rrah|do të të lëndoj|do të të vras|do të të dëmtoj|do të të shtyj|do të të shkel)/i' => 'Dhunë verbale',
        // Presion psikologjik
        '/(nëse nuk pranon|nëse nuk dëshmon|nëse nuk bashkëpunon|do të kesh pasoja|do të humbësh gjithçka)/i' => 'Presion psikologjik',
        // Gënjeshtra të mundshme
        '/(nuk mbaj mend|nuk e di|nuk isha aty|nuk kam parë|nuk kam dëgjuar)/i' => 'Gënjeshtër e mundshme',
        // Fjalë urrejtjeje
        '/(urrej|e urrej|të urrej|të shkatërroj|të zhduk|të eliminoj)/i' => 'Urrejtje',
    ];
    $results = [];
    foreach ($patterns as $pattern => $label) {
        if (preg_match($pattern, $text)) {
            $results[] = $label;
        }
    }
    // Analizë sentimenti e thjeshtë
    $negative_words = ['i/e frikësuar','i/e pasigurt','i/e shqetësuar','i/e zemëruar','i/e trishtuar','i/e dëshpëruar','i/e tensionuar','i/e stresuar'];
    foreach ($negative_words as $word) {
        if (stripos($text, $word) !== false) {
            $results[] = 'Sentiment negativ';
            break;
        }
    }
    // Analizë e ndërprerjeve të shpeshta (simulim)
    if (substr_count($text, '...') > 5) {
        $results[] = 'Ndërprerje të shpeshta (presion psikologjik)';
    }
    // Analizë e strukturës së bisedës (simulim)
    if (preg_match_all('/(?!^)[A-ZÇË][a-zçë]+:/m', $text, $matches) && count($matches[0]) > 3) {
        $results[] = 'Shumë pjesëmarrës (bisedë e tensionuar)';
    }
    return array_unique($results);
}
// --- Fund analizë e avancuar ---

$error = $success = $transcript = '';
$lang = $_POST['lang'] ?? 'sq';
$keywords = $_POST['keywords'] ?? '';
$speakers = '';
$transcripts = [];

// Kërkim transkriptesh
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    $user_id = null;
}
if (isset($_GET['search'])) {
    $transcripts = search_transcripts($pdo, $_GET['search']);
}
// Listo transkriptet e përdoruesit
elseif (isset($_GET['list'])) {
    $transcripts = isset($_SESSION['user_id']) ? list_transcripts($pdo, $_SESSION['user_id']) : [];
}

// Ngarkim dhe transkriptim
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['audio_file'])) {
    $file = $_FILES['audio_file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $target_dir = __DIR__ . '/uploads/';
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $filename = uniqid('audio_') . '_' . basename($file['name']);
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            // API për transkriptim automatik (shembull Silero)
            $api_url = 'https://api.silero.ai/vad/transcribe';
            $api_key = 'API_KEY_YT';
            $cfile = new CURLFile($target_file);
            $post = [
                'audio' => $cfile,
                'lang' => $lang,
                'diarization' => 'true', // Speaker diarization
            ];
            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $api_key
            ]);
            $result = curl_exec($ch);
            if ($result === false) {
                $error = 'Gabim gjatë transkriptimit: ' . curl_error($ch);
            } else {
                $data = json_decode($result, true);
                if (isset($data['transcript'])) {
                    $transcript = $data['transcript'];
                    $speakers = $data['speakers'] ?? '';
                    // Thekso fjalët kyçe
                    if ($keywords) {
                        foreach (explode(',', $keywords) as $kw) {
                            $kw = trim($kw);
                            if ($kw) $transcript = preg_replace('/(' . preg_quote($kw, '/') . ')/i', '<mark>$1</mark>', $transcript);
                        }
                    }
                    // Analizë e avancuar e sjelljes
                    $behavior_flags = analyze_behavior_advanced($transcript);
                    $behavior_note = $behavior_flags ? 'Sjellje të papërshtatshme të detektuara: ' . implode(', ', $behavior_flags) : '';
                    save_transcript($pdo, $_SESSION['user_id'] ?? 0, $filename, $transcript, $lang, $speakers, $keywords);
                    $success = 'Transkriptimi u krye me sukses dhe u ruajt!';
                } else {
                    $error = 'Nuk u mor transkriptimi nga API.';
                }
            }
            curl_close($ch);
        } else {
            $error = 'Ngarkimi i file-it dështoi.';
        }
    } else {
        $error = 'Gabim gjatë ngarkimit të file-it.';
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Silero VAD - Platformë e avancuar</title>
    <style>
        body { font-family: Montserrat, Arial, sans-serif; background: #f8fafc; margin:0; padding:0; }
        .container { max-width: 700px; margin: 40px auto; background: #fff; border-radius: 14px; box-shadow: 0 4px 18px #2d6cdf22; padding: 32px 24px; }
        h1 { color: #2d6cdf; text-align:center; }
        .form-group { margin-bottom: 18px; }
        label { font-weight: 600; color: #184fa3; }
        input[type="file"], select, input[type="text"] { margin-top: 8px; width:100%; }
        button { background: #2d6cdf; color: #fff; border: none; border-radius: 8px; padding: 12px 28px; font-size: 1.1rem; font-weight: 700; cursor: pointer; }
        button:hover { background: #184fa3; }
        .success { color: #388e3c; background: #eafaf1; border-radius: 8px; padding: 10px; margin-bottom: 18px; text-align:center; }
        .error { color: #d32f2f; background: #ffeaea; border-radius: 8px; padding: 10px; margin-bottom: 18px; text-align:center; }
        .transcript { background: #f8fafc; border: 1px solid #e2eafc; border-radius: 8px; padding: 14px; margin-top: 18px; font-size: 1.05rem; color: #184fa3; }
        .call-btn { display:block; margin: 24px auto 0 auto; background:#388e3c; color:#fff; border-radius:8px; padding:12px 24px; font-weight:600; text-align:center; text-decoration:none; font-size:1.1rem; }
        .call-btn:hover { background:#2d6cdf; }
        .list-table { width:100%; border-collapse:collapse; margin-top:24px; }
        .list-table th, .list-table td { border:1px solid #e2eafc; padding:8px; }
        .list-table th { background:#e2eafc; color:#184fa3; }
        .list-table tr:nth-child(even) { background:#f8fafc; }
        .list-table tr:hover td { background:#e2eafc; }
        .search-bar { margin-bottom:18px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Silero VAD<br><span style="font-size:1.1rem; color:#184fa3;">Transkriptim automatik, thirrje të shpejta & më shumë</span></h1>
        <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="audio_file">Ngarko file audio/video (wav/mp3/mp4):</label>
                <input type="file" name="audio_file" id="audio_file" accept="audio/*,video/*" required>
            </div>
            <div class="form-group">
                <label for="lang">Gjuha e intervistës:</label>
                <select name="lang" id="lang">
                    <option value="sq">Shqip</option>
                    <option value="en">Anglisht</option>
                    <option value="sr">Serbisht</option>
                    <option value="de">Gjermanisht</option>
                    <!-- Shto gjuhë të tjera sipas API-së -->
                </select>
            </div>
            <div class="form-group">
                <label for="keywords">Fjalë kyçe për theksim (ndaji me presje):</label>
                <input type="text" name="keywords" id="keywords" placeholder="shembull: drogë, armë, dëshmitar">
            </div>
            <button type="submit">Transkripto Automatikisht</button>
        </form>
        <?php if ($transcript): ?>
            <div class="transcript">
                <strong>Transkripti:</strong><br>
                <?php echo nl2br($transcript); ?><br>
                <?php if ($speakers): ?><em>Folës të detektuar: <?php echo htmlspecialchars($speakers); ?></em><?php endif; ?>
                <form method="post" action="?export=pdf">
                    <input type="hidden" name="transcript" value="<?php echo htmlspecialchars($transcript); ?>">
                    <button type="submit">Shkarko si PDF</button>
                </form>
            </div>
        <?php endif; ?>
        <a href="video_call.php?room=policia&token=TOKEN" class="call-btn" target="_blank">Thirrje e Shpejtë me Policinë</a>
        <hr style="margin:32px 0;">
        <form method="get" class="search-bar">
            <input type="text" name="search" placeholder="Kërko në transkripte..." style="width:70%;padding:8px;">
            <button type="submit">Kërko</button>
            <a href="?list=1" style="margin-left:12px;">Shfaq të gjitha</a>
        </form>
        <?php if ($transcripts): ?>
            <table class="list-table">
                <tr><th>File</th><th>Gjuha</th><th>Folës</th><th>Fjalë kyçe</th><th>Data</th><th>Shiko</th></tr>
                <?php foreach ($transcripts as $tr): ?>
                <tr>
                    <td><?php echo htmlspecialchars($tr['filename']); ?></td>
                    <td><?php echo htmlspecialchars($tr['lang']); ?></td>
                    <td><?php echo htmlspecialchars($tr['speakers']); ?></td>
                    <td><?php echo htmlspecialchars($tr['keywords']); ?></td>
                    <td><?php echo htmlspecialchars($tr['created_at']); ?></td>
                    <td><a href="?view=<?php echo $tr['id']; ?>">Shiko</a></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
