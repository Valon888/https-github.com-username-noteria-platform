<?php
require_once __DIR__ . '/vendor/autoload.php';
use Firebase\JWT\JWT;

session_start();

// 1. Kontrollo nëse përdoruesi është i kyçur
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Kontrollo IP dhe user-agent për hijacking session
if (!isset($_SESSION['ip_check'])) {
    $_SESSION['ip_check'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['ua_check'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
} else {
    if ($_SESSION['ip_check'] !== $_SERVER['REMOTE_ADDR'] ||
        ($_SESSION['ua_check'] ?? '') !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
        session_destroy();
        die('Seanca juaj është mbyllur për arsye sigurie.');
    }
}

// 3. Rate limiting (max 5 thirrje video në 10 min)
if (!isset($_SESSION['video_call_times'])) $_SESSION['video_call_times'] = [];
$_SESSION['video_call_times'] = array_filter(
    $_SESSION['video_call_times'],
    fn($t) => $t > (time() - 600)
);
if (count($_SESSION['video_call_times']) >= 5) {
    die('Keni tejkaluar kufirin e thirrjeve video. Provoni më vonë.');
}
$_SESSION['video_call_times'][] = time();

// 4. Lejo vetëm role të caktuara
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';
$allowed_roles = ['admin', 'zyrtar', 'user'];
if (!in_array($user_role, $allowed_roles)) {
    echo "Nuk keni të drejtë për video thirrje.";
    exit();
}

// 5. Gjenero emër dhome të rastësishëm, unik për përdoruesit (jo të guess-ueshëm)
$room = isset($_GET['room']) && preg_match('/^[a-zA-Z0-9_]{8,32}$/', $_GET['room'])
    ? $_GET['room']
    : 'noteria_' . hash('sha256', $user_id . session_id() . date('YmdH'));

// 6. Parametrat për JWT
$jitsi_app_id = 'EMRI_I_APLIKACIONIT_TUAJ';
$jitsi_secret = 'SEKRETI_JUAJ_SUPER_I_FSHEHTE';
$jitsi_domain = 'domena.jitsi.tua.com'; // Ndrysho me domenin tënd të Jitsi

// 7. Payload për token (me kufizime të forta)
$payload = [
    "aud" => "jitsi",
    "iss" => $jitsi_app_id,
    "sub" => $jitsi_domain,
    "room" => $room,
    "exp" => time() + 1800, // 30 min vlefshmëri
    "nbf" => time() - 10,
    "context" => [
        "user" => [
            "name" => $_SESSION['emri'] ?? 'Përdorues',
            "email" => $_SESSION['email'] ?? '',
            "id" => $user_id,
        ],
        "features" => [
            "livestreaming" => false,
            "recording" => false,
            "outbound-call" => false,
            "transcription" => false
        ]
    ]
];

// 8. Gjenero JWT token
$jwt = JWT::encode($payload, $jitsi_secret, 'HS256');

// 9. CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 10. CSP & X-Frame-Options headers për clickjacking
header("Content-Security-Policy: frame-ancestors 'self' https://$jitsi_domain;");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: no-referrer");
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");

?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <title>Video Thirrje | Noteria</title>
    <style>
        body { margin:0; padding:0; }
        #jitsi-meet { width: 100vw; height: 100vh; border: 0; }
        .secure-warning { background: #ffeaea; color: #d32f2f; padding: 12px; border-radius: 8px; margin: 16px; text-align: center; font-weight: 600; }
        .logout-btn { position: fixed; top: 10px; right: 10px; background: #f44336; color: white; padding: 10px; border-radius: 5px; text-decoration:none; z-index:9999;}
    </style>
</head>
<body>
    <div class="secure-warning">
        <span>
            Video thirrja është private dhe e monitoruar. Mos ndani linkun me persona të paautorizuar.<br>
            Të gjitha veprimet mund të regjistrohen për siguri.
        </span>
    </div>
    <iframe id="jitsi-meet"
        src="https://<?php echo $jitsi_domain; ?>/<?php echo htmlspecialchars($room); ?>?jwt=<?php echo $jwt; ?>"
        allow="camera; microphone; fullscreen; display-capture"
        style="width:100vw; height:100vh; border:0;">
    </iframe>
    <a href="logout.php" class="logout-btn">Dil</a>
</body>
</html>
<?php
// Përpunim POST për përfundim thirrjeje
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF attack detected!');
    }
    // Këtu mund të regjistrosh përfundimin e thirrjes në audit log ose DB
}
// Vetëm për testim!
// $_SESSION['role'] = 'user';
$_SESSION['role'] = $user['roli'] ?? $user['role'];
?>