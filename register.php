<?php
// Endpoint për ri-dërgimin e kodit 2FA me AJAX
if (isset($_GET['resend_2fa']) && $_GET['resend_2fa'] == '1' && isset($_SESSION['pending_reg']) && isset($_SESSION['2fa_code'])) {
    require_once __DIR__ . '/vendor/autoload.php';
    // Use global send2faSMS from config.php
    $telefoni = $_SESSION['pending_reg']['telefoni'];
    $code = $_SESSION['2fa_code'];
    $ok = send2faSMS($telefoni, $code);
    echo $ok ? 'success' : 'error';
    exit;
}
// filepath: c:\xampp\htdocs\noteria\book.php
// Konfigurimi i raportimit të gabimeve
ini_set('display_errors', 0); // Mos shfaq gabime në faqe
ini_set('log_errors', 1);     // Log gabimet në server
ini_set('error_log', __DIR__ . '/error.log'); // Ruaj gabimet në error.log në këtë folder
// filepath: c:\xampp\htdocs\noteria\book.php
// Konfigurimi i raportimit të gabimeve
ini_set('display_errors', 0); // Mos shfaq gabime në faqe
ini_set('log_errors', 1);     // Log gabimet në server
ini_set('error_log', __DIR__ . '/error.log'); // Ruaj gabimet në error.log në këtë folder

session_start();
require_once 'config.php';

// Shto verifikime të forta për regjistrim
$error = null;
$success = null;
$pending_2fa = false;
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['verify_2fa'])) {
    $emri = trim($_POST["emri"] ?? '');
    $mbiemri = trim($_POST["mbiemri"] ?? '');
    $email = filter_var(trim($_POST["email"] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST["password"] ?? '');
    $personal_number = trim($_POST["personal_number"] ?? '');
    $telefoni = trim($_POST["telefoni"] ?? '');
    $photo = $_FILES['photo'] ?? null;

    // Emri dhe mbiemri duhet të jenë vetëm shkronja dhe të paktën 2 karaktere
    if (!preg_match('/^[A-Za-zÇçËë\s]{2,}$/u', $emri)) {
        $error = "Emri duhet të përmbajë vetëm shkronja dhe të jetë të paktën 2 karaktere.";
    } elseif (!preg_match('/^[A-Za-zÇçËë\s]{2,}$/u', $mbiemri)) {
        $error = "Mbiemri duhet të përmbajë vetëm shkronja dhe të jetë të paktën 2 karaktere.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email-i nuk është i vlefshëm.";
    }
    // Password strength validation - now lenient but with warnings
    if (!$error) {
        $password_strength = 0;
        $password_warnings = [];
        if (strlen($password) < 6) {
            $error = "Fjalëkalimi duhet të ketë të paktën 6 karaktere.";
        } else {
            if (strlen($password) >= 8) $password_strength++;
            else $password_warnings[] = "Fjalëkalim i shkurtër - rekomandohet të paktën 8 karaktere";
            
            if (preg_match('/[A-Z]/', $password)) $password_strength++;
            else $password_warnings[] = "Nuk ka shkronja të mëdha - shtoji për siguri më të lartë";
            
            if (preg_match('/[a-z]/', $password)) $password_strength++;
            else $password_warnings[] = "Nuk ka shkronja të vogla - shtoji për siguri më të lartë";
            
            if (preg_match('/\d/', $password)) $password_strength++;
            else $password_warnings[] = "Nuk ka numra - shtoji për siguri më të lartë";
            
            if (preg_match('/[^A-Za-z0-9]/', $password)) $password_strength++;
            else $password_warnings[] = "Nuk ka simbole - shtoji për siguri më të lartë";
            
            $_SESSION['temp_password_strength'] = $password_strength;
            $_SESSION['temp_password_warnings'] = $password_warnings;
        }
    }
    if (!$error && !preg_match('/^\d{10}$/', $personal_number)) {
        $error = "Numri personal duhet të jetë saktësisht 10 shifra!";
    } elseif (!preg_match('/^\+383[1-9]\d{7}$/', $telefoni)) {
        $error = "Numri i telefonit duhet të fillojë me +383 dhe të jetë i vlefshëm për Kosovë (p.sh. +38344123456)!";
    } else {
        $komuna = substr($personal_number, 0, 2);
        $komunat_kosove = ['01','02','03','04','05','06','07','08','09','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24','25','26','27'];
        if (!in_array($komuna, $komunat_kosove)) {
            $error = "Numri personal nuk i përket Republikës së Kosovës!";
        }
    }
    // Kontrollo email-in nëse ekziston
    if (!$error) {
        require_once 'confidb.php';
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Ky email është i regjistruar!";
        }
    }
    // Kontrollo foton
    if (!$error && $photo && $photo['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png'];
        if (!in_array($photo['type'], $allowed_types)) {
            $error = "Fotoja duhet të jetë në format JPG ose PNG!";
        } elseif ($photo['size'] > 2*1024*1024) {
            $error = "Fotoja nuk duhet të jetë më e madhe se 2MB!";
        }
    } elseif (!$error) {
        $error = "Ngarkoni një foto të vlefshme!";
    }
    // Nëse nuk ka gabime, ruaj të dhënat në sesion dhe kërko 2FA
    if (!$error) {
        $_SESSION['pending_reg'] = [
            'emri' => $emri,
            'mbiemri' => $mbiemri,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'password_strength' => $password_strength ?? 0,
            'personal_number' => $personal_number,
            'telefoni' => $telefoni,
            'photo_path' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'mfa_enabled' => ($password_strength ?? 0) < 3 // Force MFA for weak passwords
        ];
        $target_dir = __DIR__ . "/uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $target_file = $target_dir . uniqid() . '_' . basename($photo["name"]);
        move_uploaded_file($photo["tmp_name"], $target_file);
        $_SESSION['pending_reg']['photo_path'] = $target_file;
        // Gjenero kodin 2FA dhe dërgo me SMS (Twilio)
        $_SESSION['2fa_code'] = rand(100000, 999999);
        require_once __DIR__ . '/vendor/autoload.php';
        // Use global send2faSMS from config.php
        send2faSMS($telefoni, $_SESSION['2fa_code']);
        $pending_2fa = true;
        $success = "Një kod verifikimi është dërguar në telefonin tuaj. Ju lutemi vendoseni më poshtë për të përfunduar regjistrimin.";
    }
}
// Verifiko kodin 2FA
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['verify_2fa'])) {
    $user_code = trim($_POST['code_2fa'] ?? '');
    if (isset($_SESSION['2fa_code']) && $user_code == $_SESSION['2fa_code'] && isset($_SESSION['pending_reg'])) {
        require_once 'confidb.php';
        $reg = $_SESSION['pending_reg'];
        $stmt = $pdo->prepare("INSERT INTO users (emri, mbiemri, email, password, personal_number, telefoni, roli, photo_path, password_strength, mfa_enabled, password_changed_at) VALUES (?, ?, ?, ?, ?, ?, 'perdorues', ?, ?, ?, NOW())");
        if ($stmt->execute([$reg['emri'], $reg['mbiemri'], $reg['email'], $reg['password'], $reg['personal_number'], $reg['telefoni'], $reg['photo_path'], $reg['password_strength'], $reg['mfa_enabled']])) {
            $success = "Regjistrimi u krye me sukses! Tani mund të kyçeni.";
            if ($reg['password_strength'] < 3) {
                $success .= " ⚠️ Fjalëkalimi juaj është i dobët. Do të ketë masa të shtuar sigurie (MFA i detyrueshëm, login alerts, password expiry çdo 90 ditë).";
            }
            unset($_SESSION['pending_reg'], $_SESSION['2fa_code']);
        } else {
            $error = "Gabim gjatë regjistrimit. Ju lutemi provoni përsëri.";
        }
    } else {
        $error = "Kodi i verifikimit është i pasaktë!";
        $pending_2fa = true;
        // Mos e fshi $_SESSION['2fa_code'] këtu, lejo disa tentativa me të njëjtin kod
    }
}
?>
<?php
// Multilingual labels
$lang = $_GET['lang'] ?? $_COOKIE['lang'] ?? 'sq';
if (!in_array($lang, ['sq','sr','en'])) $lang = 'sq';
setcookie('lang', $lang, time()+60*60*24*30, '/');
$labels = [
    'sq' => [
        'title' => 'Regjistrohu si Përdorues i Thjeshtë',
        'name' => 'Emri:',
        'surname' => 'Mbiemri:',
        'email' => 'Email:',
        'password' => 'Fjalëkalimi:',
        'personal_number' => 'Numri Personal:',
        'phone' => 'Numri i Telefonit:',
        'photo' => 'Ngarko Foto të Letërnjoftimit/Pasaportës:',
        'register' => 'Regjistrohu',
        'login' => 'Keni llogari? Kyçuni këtu',
        'verify_title' => 'Kodi i Verifikimit 2FA',
        'verify_label' => 'Shkruani kodin e verifikimit (6 shifra):',
        'verify_btn' => 'Verifiko dhe Përfundo Regjistrimin',
        'resend' => 'Dërgo kodin përsëri',
        'timer' => 'Mund të kërkoni ri-dërgimin e kodit pas',
        'seconds' => 'sekondash.',
        'not_received' => 'Nuk morët kodin?',
        'dev_toggle' => 'Developer Registration',
    ],
    'sr' => [
        'title' => 'Registrujte se kao običan korisnik',
        'name' => 'Ime:',
        'surname' => 'Prezime:',
        'email' => 'Email:',
        'password' => 'Lozinka:',
        'personal_number' => 'JMBG:',
        'phone' => 'Broj telefona:',
        'photo' => 'Otpremite fotografiju lične karte/pasoša:',
        'register' => 'Registrujte se',
        'login' => 'Imate nalog? Prijavite se ovde',
        'verify_title' => '2FA verifikacioni kod',
        'verify_label' => 'Unesite verifikacioni kod (6 cifara):',
        'verify_btn' => 'Verifikujte i završite registraciju',
        'resend' => 'Pošaljite kod ponovo',
        'timer' => 'Možete ponovo zatražiti kod za',
        'seconds' => 'sekundi.',
        'not_received' => 'Niste dobili kod?',
        'dev_toggle' => 'Registracija za developere',
    ],
    'en' => [
        'title' => 'Register as Regular User',
        'name' => 'First Name:',
        'surname' => 'Last Name:',
        'email' => 'Email:',
        'password' => 'Password:',
        'personal_number' => 'Personal Number:',
        'phone' => 'Phone Number:',
        'photo' => 'Upload ID/Passport Photo:',
        'register' => 'Register',
        'login' => 'Already have an account? Login here',
        'verify_title' => '2FA Verification Code',
        'verify_label' => 'Enter the verification code (6 digits):',
        'verify_btn' => 'Verify and Complete Registration',
        'resend' => 'Resend Code',
        'timer' => 'You can request the code again in',
        'seconds' => 'seconds.',
        'not_received' => 'Didn\'t receive the code?',
        'dev_toggle' => 'Developer Registration',
    ]
];
$L = $labels[$lang];
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Regjistrohu | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(120deg, #e2eafc 0%, #f8fafc 100%);
            font-family: 'Montserrat', Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 380px;
            margin: 60px auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            padding: 36px 28px;
            text-align: center;
        }
        h2 {
            color: #2d6cdf;
            margin-bottom: 28px;
            font-size: 2rem;
            font-weight: 700;
        }
        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }
        label {
            display: block;
            margin-bottom: 6px;
            color: #2d6cdf;
            font-weight: 600;
        }
        input[type="email"], input[type="password"], input[type="text"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2eafc;
            border-radius: 8px;
            font-size: 1rem;
            background: #f8fafc;
            transition: border-color 0.2s;
        }
        input[type="email"]:focus, input[type="password"]:focus, input[type="text"]:focus {
            border-color: #2d6cdf;
            outline: none;
        }
        button[type="submit"] {
            background: #2d6cdf;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px 0;
            width: 100%;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }
        button[type="submit"]:hover {
            background: #184fa3;
        }
        .error {
            color: #d32f2f;
            background: #ffeaea;
            border-radius: 8px;
            padding: 10px;
            margin-top: 18px;
            font-size: 1rem;
        }
        .success {
            color: #2e7d32;
            background: #e8f5e9;
            border-radius: 8px;
            padding: 10px;
            margin-top: 18px;
            font-size: 1rem;
        }
        .login-link {
            margin-top: 22px;
            font-size: 0.98rem;
            color: #333;
        }
        .login-link a {
            color: #2d6cdf;
            text-decoration: none;
            font-weight: 600;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <form method="get" style="text-align:right;margin-bottom:10px;">
            <select name="lang" onchange="this.form.submit()" style="padding:4px 8px;border-radius:6px;">
                <option value="sq"<?php if($lang=='sq')echo' selected';?>>Shqip</option>
                <option value="sr"<?php if($lang=='sr')echo' selected';?>>Српски</option>
                <option value="en"<?php if($lang=='en')echo' selected';?>>English</option>
            </select>
        </form>
        <h2><?php echo htmlspecialchars($L['title']); ?></h2>
        <?php if ($pending_2fa): ?>
            <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:16px;margin-bottom:20px;">
                <h3 style="color:#856404;margin-top:0;"><?php echo htmlspecialchars($L['verify_title']); ?></h3>
                <p style="color:#856404;margin:8px 0;">Kodi juaj i verifikimit:</p>
                <div style="background:#fff;border:2px solid #ffc107;border-radius:6px;padding:12px;text-align:center;margin:12px 0;font-family:monospace;font-size:1.8rem;letter-spacing:4px;font-weight:bold;color:#333;">
                    <?php echo isset($_SESSION['2fa_code']) ? htmlspecialchars((string)$_SESSION['2fa_code']) : '<span style="color:#888;font-size:1rem;">-</span>'; ?>
                </div>
                <p style="color:#856404;font-size:0.9rem;margin:12px 0;">Ky kod u dërgua edhe në telefonin tuaj. Është i vlefshëm për 10 minuta.</p>
            </div>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="code_2fa"><?php echo htmlspecialchars($L['verify_label']); ?></label>
                    <input type="text" id="code_2fa" name="code_2fa" required maxlength="6" pattern="\d{6}" autofocus>
                </div>
                <button type="submit" name="verify_2fa"><?php echo htmlspecialchars($L['verify_btn']); ?></button>
            </form>
            <div id="2fa-timer" style="margin-top:12px;color:#888;font-size:0.98em;"></div>
            <button id="resend2fa" type="button" style="margin-top:8px;display:none;background:#2d6cdf;color:#fff;border:none;border-radius:8px;padding:8px 16px;cursor:pointer;"><?php echo htmlspecialchars($L['resend']); ?></button>
        <?php else: ?>
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="emri"><?php echo htmlspecialchars($L['name']); ?></label>
                <input type="text" id="emri" name="emri" required>
            </div>
            <div class="form-group">
                <label for="mbiemri"><?php echo htmlspecialchars($L['surname']); ?></label>
                <input type="text" id="mbiemri" name="mbiemri" required>
            </div>
            <div class="form-group">
                <label for="email"><?php echo htmlspecialchars($L['email']); ?></label>
                <input type="email" id="email" name="email" required autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password"><?php echo htmlspecialchars($L['password']); ?></label>
                <input type="password" id="password" name="password" required autocomplete="new-password" minlength="6">
                <div id="password-strength" style="margin-top:8px;font-size:0.85rem;"></div>
                <div id="password-warnings" style="margin-top:8px;font-size:0.85rem;color:#ff9800;"></div>
            </div>
            <div class="form-group">
                <label for="personal_number"><?php echo htmlspecialchars($L['personal_number']); ?></label>
                <input type="text" id="personal_number" name="personal_number" required maxlength="10" pattern="\d{10}">
            </div>
            <div class="form-group">
                <label for="telefoni"><?php echo htmlspecialchars($L['phone']); ?></label>
                <input type="text" id="telefoni" name="telefoni" required placeholder="+38344123456" maxlength="13" pattern="\+383[1-9]\d{7}">
            </div>
            <div class="form-group">
                <label for="photo"><?php echo htmlspecialchars($L['photo']); ?></label>
                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png" required>
            </div>
            <button type="submit"><?php echo htmlspecialchars($L['register']); ?></button>
        </form>
        <?php endif; ?>
        <?php if (isset($error) && $error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (isset($success) && $success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <div class="login-link">
            <?php
            $loginText = $L['login'];
            if ($lang == 'sq') {
                echo 'Keni llogari? <a href="login.php">Kyçuni këtu</a>';
            } else if ($lang == 'sr') {
                echo 'Imate nalog? <a href="login.php">Prijavite se ovde</a>';
            } else {
                echo 'Already have an account? <a href="login.php">Login here</a>';
            }
            ?>
        </div>
        
        <!-- Developer registration toggle -->
        <div style="margin-top:20px;text-align:center;">
            <button type="button" id="devRegToggle" style="background:none;border:none;color:#666;font-size:0.85rem;cursor:pointer;text-decoration:underline;">
                <?php echo htmlspecialchars($L['dev_toggle']); ?>
            </button>
        </div>
        
        <!-- Developer registration form (hidden by default) -->
        <div id="devRegForm" style="display:none;margin-top:24px;border-top:1px solid #e2eafc;padding-top:24px;">
            <h3 style="color:#333;font-size:1.2rem;margin-bottom:16px;">Developer Registration</h3>
            <form method="POST" action="" id="developerRegForm" enctype="multipart/form-data">
                <input type="hidden" name="dev_registration" value="1">
                
                <div class="form-group">
                    <label for="dev_name">Full Name:</label>
                    <input type="text" id="dev_name" name="dev_name" autocomplete="name">
                </div>
                
                <div class="form-group">
                    <label for="dev_email">Email:</label>
                    <input type="email" id="dev_email" name="dev_email" autocomplete="email">
                </div>
                
                <div class="form-group">
                    <label for="dev_password">Password:</label>
                    <input type="password" id="dev_password" name="dev_password" autocomplete="new-password">
                </div>
                
                <div class="form-group">
                    <label for="dev_personal_number">ID/Passport Number:</label>
                    <input type="text" id="dev_personal_number" name="dev_personal_number" required maxlength="10" pattern="\d{10}" placeholder="10 digits">
                </div>
                
                <div class="form-group">
                    <label for="dev_photo">Upload ID/Passport Photo:</label>
                    <input type="file" id="dev_photo" name="dev_photo" accept="image/jpeg,image/png" required>
                </div>
                
                <div class="form-group">
                    <label for="dev_phone">Phone Number:</label>
                    <input type="text" id="dev_phone" name="dev_phone" required placeholder="e.g. +38344123456" maxlength="13" pattern="\+383[1-9]\d{7}">
                </div>
                
                <div class="form-group">
                    <label for="dev_key">Developer Access Key:</label>
                    <input type="password" id="dev_key" name="dev_key" autocomplete="off"
                        style="font-family:monospace;letter-spacing:1px;">
                    <small style="display:block;margin-top:4px;color:#666;">Provided by system administrator</small>
                </div>
                
                <div class="form-group">
                    <label for="dev_github">GitHub Username (optional):</label>
                    <input type="text" id="dev_github" name="dev_github">
                </div>
                
                <button type="submit" style="background:#333;margin-top:8px;">
                    Register as Developer
                </button>
            </form>
        </div>
    </div>
    
    <script>
    // Password strength checker
    const passwordInput = document.getElementById('password');
    const strengthDiv = document.getElementById('password-strength');
    const warningsDiv = document.getElementById('password-warnings');
    
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const pwd = this.value;
            let strength = 0;
            let warnings = [];
            
            if (pwd.length >= 8) strength++;
            else if (pwd.length > 0) warnings.push('⚠️ Shumë i shkurtër');
            
            if (/[A-Z]/.test(pwd)) strength++;
            else if (pwd.length > 0) warnings.push('⚠️ Shtoji shkronja të mëdha');
            
            if (/[a-z]/.test(pwd)) strength++;
            else if (pwd.length > 0) warnings.push('⚠️ Shtoji shkronja të vogla');
            
            if (/\d/.test(pwd)) strength++;
            else if (pwd.length > 0) warnings.push('⚠️ Shtoji numra');
            
            if (/[^A-Za-z0-9]/.test(pwd)) strength++;
            else if (pwd.length > 0) warnings.push('⚠️ Shtoji simbole');
            
            // Display strength
            let strengthText = '';
            let strengthColor = '';
            if (pwd.length === 0) {
                strengthText = '';
            } else if (strength <= 2) {
                strengthText = '🔴 I dobët - Massat e sigurisë do të jenë shumë të larta';
                strengthColor = '#d32f2f';
            } else if (strength <= 3) {
                strengthText = '🟡 I moderuem';
                strengthColor = '#ff9800';
            } else if (strength <= 4) {
                strengthText = '🟢 I mirë';
                strengthColor = '#388e3c';
            } else {
                strengthText = '🟢 Shumë i mirë - Siguri maksimale';
                strengthColor = '#1b5e20';
            }
            
            strengthDiv.innerHTML = strengthText;
            strengthDiv.style.color = strengthColor;
            warningsDiv.innerHTML = warnings.join('<br>');
        });
    }
    
    // 2FA timer & resend logic
    let timer = 60;
    const timerDiv = document.getElementById('2fa-timer');
    const resendBtn = document.getElementById('resend2fa');
    function updateTimer() {
        if (timer > 0) {
            timerDiv.textContent = `<?php echo $L['timer']; ?> ${timer} <?php echo $L['seconds']; ?>`;
            resendBtn.style.display = 'none';
            timer--;
            setTimeout(updateTimer, 1000);
        } else {
            timerDiv.textContent = '<?php echo $L['not_received']; ?>';
            resendBtn.style.display = 'inline-block';
        }
    }
    if (timerDiv) updateTimer();
    if (resendBtn) {
        resendBtn.onclick = function() {
            resendBtn.disabled = true;
            resendBtn.textContent = '<?php echo $L['resend']; ?>...';
            // Ri-dërgo kodin me AJAX
            fetch(window.location.pathname + '?resend_2fa=1')
                .then(resp => resp.text())
                .then(result => {
                    resendBtn.disabled = false;
                    resendBtn.textContent = '<?php echo $L['resend']; ?>';
                    timer = 60;
                    updateTimer();
                    if (result === 'success') {
                        alert('<?php echo ($lang=='sq'?'Kodi u dërgua përsëri në telefon!':($lang=='sr'?'Kod je ponovo poslat na telefon!':'Code resent to your phone!')); ?>');
                    } else {
                        alert('<?php echo ($lang=='sq'?'Dërgimi i kodit dështoi!':($lang=='sr'?'Slanje koda nije uspelo!':'Failed to send code!')); ?>');
                    }
                });
        };
    }
    // Developer registration toggle functionality
    document.getElementById('devRegToggle').addEventListener('click', function() {
        const devForm = document.getElementById('devRegForm');
        if (devForm.style.display === 'none') {
            devForm.style.display = 'block';
            // Focus on the first input field
            setTimeout(() => document.getElementById('dev_name').focus(), 100);
        } else {
            devForm.style.display = 'none';
        }
    });
    
    // Developer registration quick access with special key combination (Ctrl+Shift+R)
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'r') {
            e.preventDefault();
            const devForm = document.getElementById('devRegForm');
            devForm.style.display = 'block';
            setTimeout(() => document.getElementById('dev_name').focus(), 100);
        }
    });
    </script>
</body>
</html>