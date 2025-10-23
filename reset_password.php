<?php
// Shto këtë në fillim të çdo faqeje që do të monitorosh
$logfile = __DIR__ . '/traffic.log';
$entry = date('Y-m-d H:i:s') . " | IP: " . $_SERVER['REMOTE_ADDR'] . " | URL: " . $_SERVER['REQUEST_URI'] . "\n";
file_put_contents($logfile, $entry, FILE_APPEND);

require_once 'confidb.php';
use PHPMailer\PHPMailer\PHPMailer;
require 'vendor/autoload.php';

// --- Mbrojtje ndaj sulmeve DDoS dhe abuzimeve me rate limiting ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$ip = $_SERVER['REMOTE_ADDR'];
$now = time();

// Rate limiting për IP (max 5 kërkesa në 15 minuta)
if (!isset($_SESSION['reset_ddos'])) {
    $_SESSION['reset_ddos'] = [];
}
if (!isset($_SESSION['reset_ddos'][$ip])) {
    $_SESSION['reset_ddos'][$ip] = [];
}
// Fshij kërkesat më të vjetra se 15 minuta
$_SESSION['reset_ddos'][$ip] = array_filter(
    $_SESSION['reset_ddos'][$ip],
    function($timestamp) use ($now) { return ($now - $timestamp) < 900; }
);
if (count($_SESSION['reset_ddos'][$ip]) >= 5) {
    die('<div style="color:red;font-weight:bold;text-align:center;margin-top:40px;">Shumë tentativa nga IP juaj! Provo përsëri pas 15 minutash.</div>');
}
$_SESSION['reset_ddos'][$ip][] = $now;

// --- Fund i mbrojtjes DDoS ---

// Shto IP të dyshimta në një file ose DB dhe blloko ato IP
$blocked_ips_file = __DIR__ . '/blocked_ips.txt';
if (file_exists($blocked_ips_file)) {
    $blocked_ips = file($blocked_ips_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
} else {
    $blocked_ips = [];
}
if (in_array($ip, $blocked_ips)) {
    die('IP juaj është bllokuar për shkak të aktivitetit të dyshimtë.');
}

// Kontrollo IP-në me një API të jashtme (shembull)
// $mcp_api_key = 'API_KEY_YT';
// $response = file_get_contents("https://mcp.example.com/api/check_ip?ip=$ip&key=$mcp_api_key");
// $data = json_decode($response, true);
// if ($data['malicious'] ?? false) {
//     die('IP juaj është bllokuar nga sistemi i mbrojtjes cloud.');
// }

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);

    // Validimi i email-it
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email-i nuk është i vlefshëm.";
    } else {
        // Gjenero një fjalëkalim të ri
        $new_password = bin2hex(random_bytes(4)); // Fjalëkalim i rastësishëm 8 shifra
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Ruaj fjalëkalimin e ri në databazë
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        if ($stmt->execute([$hashed_password, $email])) {
            // Simulo dërgimin e emailit duke e shfaqur fjalëkalimin e ri në faqe
            $success = "Fjalëkalimi i ri është: <b>$new_password</b> <br>(Ky mesazh shfaqet vetëm për testim lokal. Në prodhim, përdor email!)";
        } else {
            $error = "Gabim gjatë rivendosjes së fjalëkalimit.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rivendos Fjalëkalimin | Noteria</title>
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
        input[type="email"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2eafc;
            border-radius: 8px;
            font-size: 1rem;
            background: #f8fafc;
            transition: border-color 0.2s;
        }
        input[type="email"]:focus {
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
            color: #388e3c;
            background: #e8f5e9;
            border-radius: 8px;
            padding: 10px;
            margin-top: 18px;
            font-size: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Rivendos Fjalëkalimin</h2>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php elseif (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type="submit">Rivendos Fjalëkalimin</button>
        </form>
    </div>
</body>
<!-- Start of Tawk.to Script -->
<script type="text/javascript">
var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
(function(){
var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
s1.async=true;
s1.src='https://embed.tawk.to/67e3334b071c7e190d74f3b5/1j34rhim2';
s1.charset='UTF-8';
s1.setAttribute('crossorigin','*');
s0.parentNode.insertBefore(s1,s0);
})();
</script>
<!-- End of Tawk.to Script -->
</html>