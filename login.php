<?php
require_once 'confidb.php';

// Konfigurimi i raportimit të gabimeve
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Fillimi i sigurt i sesionit
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);

session_start();

// Regjenero ID pas kyçjes
if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

// Gjenero captcha në çdo load të faqes
if (empty($_SESSION['captcha'])) {
    $_SESSION['captcha'] = rand(10000, 99999);
}

if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit();
}

$error = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST["password"]);
    $captcha_input = trim($_POST["captcha"] ?? '');
    $personal_number = trim($_POST["personal_number"] ?? '');
    $photo = $_FILES['photo'] ?? null;

    // Validimi i email-it
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email-i nuk është i vlefshëm.";
    } elseif (strlen($password) < 6) {
        $error = "Fjalëkalimi duhet të ketë të paktën 6 karaktere.";
    } elseif ($captcha_input !== strval($_SESSION['captcha'])) {
        $error = "Captcha nuk është i saktë!";
        $_SESSION['captcha'] = rand(10000, 99999); // Gjenero të ri vetëm pas gabimit
    } elseif (empty($personal_number) || !preg_match('/^\d{10}$/', $personal_number)) {
        $error = "Numri personal duhet të jetë 10 shifra!";
    } else {
        $komuna = substr($personal_number, 0, 2);
        $komunat_kosove = ['01','02','03','04','05','06','07','08','09','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24','25','26','27'];
        if (!in_array($komuna, $komunat_kosove)) {
            $error = "Numri personal nuk i përket Republikës së Kosovës!";
        }
    }

    if (!$error && $photo && $photo['error'] === UPLOAD_ERR_OK) {
        // Kontrollo formatin e fotos
        $allowed_types = ['image/jpeg', 'image/png'];
        if (!in_array($photo['type'], $allowed_types)) {
            $error = "Fotoja duhet të jetë në format JPG ose PNG!";
        } else {
            // Ruaj foton (shembull, ruaj në folderin uploads)
            $target_dir = __DIR__ . "/uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $target_file = $target_dir . basename($photo["name"]);
            move_uploaded_file($photo["tmp_name"], $target_file);

            $stmt = $pdo->prepare("SELECT id, emri, mbiemri, email, password, roli, personal_number FROM users WHERE email = ? AND personal_number = ?");
            $stmt->execute([$email, $personal_number]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user["password"])) {
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["emri"] = htmlspecialchars($user["emri"]);
                $_SESSION["mbiemri"] = htmlspecialchars($user["mbiemri"]);
                $_SESSION["email"] = htmlspecialchars($user["email"]);
                $_SESSION["roli"] = $user["roli"];
                $_SESSION['role'] = $user['role']; // Shto ketu
                unset($_SESSION['captcha']);

                // Regjistro aktivitetin e kyçjes
                log_activity($pdo, $_SESSION['user_id'], 'Kyçje', 'Kyçje e suksesshme me verifikim foto dhe numër personal');

                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Të dhënat nuk përputhen!";
                $_SESSION['captcha'] = rand(10000, 99999);
            }
        }
    }
}

function log_activity($pdo, $user_id, $action, $details = '') {
    $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, details) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $action, $details]);
}

// Lista e IP-ve të bllokuara (shto IP të dyshimta ose të njohura për VPN)
$blocked_ips = [
    '1.2.3.4',
    '5.6.7.8',
    // Shto IP të tjera sipas nevojës
];

// Merr IP-në e përdoruesit
$user_ip = $_SERVER['REMOTE_ADDR'];

// Kontrollo nëse IP është e bllokuar
if (in_array($user_ip, $blocked_ips)) {
    die("Ky IP është i bllokuar për shkak të aktivitetit të dyshimtë.");
}

// Shembull me IPQualityScore
$api_key = 'API_KEY_YT';
$ip = $_SERVER['REMOTE_ADDR'];
$response = file_get_contents("https://ipqualityscore.com/api/json/ip/$api_key/$ip");
$data = json_decode($response, true);
if ($data['vpn'] || $data['proxy']) {
    die("Ky IP është i bllokuar (VPN/Proxy nuk lejohet).");
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kyçuni | Noteria</title>
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
        .register-link {
            margin-top: 22px;
            font-size: 0.98rem;
            color: #333;
        }
        .register-link a {
            color: #2d6cdf;
            text-decoration: none;
            font-weight: 600;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
        .success {
            color: #2e7d32;
            background: #e8f5e9;
            border-radius: 8px;
            padding: 10px;
            margin-top: 18px;
            font-size: 1rem;
        }
        .captcha-group {
            margin-bottom: 18px;
            text-align: left;
        }
        .captcha-label {
            font-weight: 600;
            color: #2d6cdf;
            margin-bottom: 6px;
            display: block;
        }
        .captcha-code {
            font-size: 1.2rem;
            letter-spacing: 4px;
            background: #e2eafc;
            padding: 8px 16px;
            border-radius: 8px;
            display: inline-block;
            margin-bottom: 6px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Kyçuni në Noteria</h2>
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Fjalëkalimi:</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <div class="form-group">
                <label for="personal_number">Numri Personal i Letërnjoftimit/Pasaportës:</label>
                <input type="text" id="personal_number" name="personal_number" required maxlength="10" pattern="\d{9,10}">
            </div>
            <div class="form-group">
                <label for="photo">Ngarko Foto të Letërnjoftimit/Pasaportës:</label>
                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png" required>
            </div>
            <div class="captcha-group">
                <label class="captcha-label" for="captcha">Shkruani kodin:</label>
                <span class="captcha-code"><?php echo $_SESSION['captcha']; ?></span>
                <input type="text" id="captcha" name="captcha" required maxlength="5" pattern="\d{5}" autocomplete="off">
            </div>
            <button type="submit">Kyçu</button>
        </form>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php
        if (isset($_SESSION['success'])) {
            echo '<div class="success">'.htmlspecialchars($_SESSION['success']).'</div>';
            unset($_SESSION['success']);
        }
        ?>
        <div class="register-link">
            Nuk keni llogari? <a href="register.php">Regjistrohuni këtu</a>
        </div>
        <?php if ($_SESSION['role'] === 'admin'): ?>
    <!-- Kodi për adminin këtu -->
    <a href="admin_panel.php">Paneli i Administrimit</a>
<?php endif; ?>
    </div>
</body>
</html>
<?php
if ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
?>
