<?php
$client_id = 'b2ea85b5-fb0c-4bba-8205-242722337617';
$client_secret = '8390e808-28b2-4baa-bd7f-4272f6d64e04';
$redirect_uri = 'http://localhost/docusign_callback.php';
$scope = 'signature';

function renderPage($content) {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>DocuSign Auth | Noteria</title>';
    echo '<link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">';
    echo '<style>
        body { background: linear-gradient(120deg, #e2eafc 0%, #f8fafc 100%); font-family: Montserrat, Arial, sans-serif; margin: 0; padding: 0; }
        .container { max-width: 520px; margin: 60px auto; background: #fff; border-radius: 18px; box-shadow: 0 8px 32px rgba(0,0,0,0.10); padding: 40px 32px; text-align: center; }
        h1 { color: #2d6cdf; font-size: 2.1rem; font-weight: 700; margin-bottom: 18px; }
        .desc { color: #444; font-size: 1.1rem; margin-bottom: 28px; }
        .success { color: #2e7d32; background: #e8f5e9; border-radius: 8px; padding: 16px; margin-bottom: 18px; font-size: 1.1rem; }
        .error { color: #d32f2f; background: #ffeaea; border-radius: 8px; padding: 16px; margin-bottom: 18px; font-size: 1.1rem; }
        textarea { width: 100%; min-height: 120px; font-size: 1.1rem; border-radius: 8px; border: 1.5px solid #e2eafc; background: #f8fafc; padding: 12px; margin-top: 10px; }
        .info { background: #e3f2fd; color: #1976d2; border-radius: 8px; padding: 12px; margin-bottom: 18px; font-size: 1rem; }
        .step { margin: 18px 0 8px 0; color: #2d6cdf; font-weight: 600; }
        .copy-btn { background: #2d6cdf; color: #fff; border: none; border-radius: 8px; padding: 10px 24px; font-size: 1rem; font-weight: 700; cursor: pointer; margin-top: 12px; transition: background 0.2s; }
        .copy-btn:hover { background: #184fa3; }
    </style></head><body><div class="container">' . $content . '</div></body></html>';
}

if (!isset($_GET['code'])) {
    $auth_url = "https://account-d.docusign.com/oauth/auth?response_type=code&scope=$scope&client_id=$client_id&redirect_uri=$redirect_uri";
    $content = '<h1>Autentikohu me DocuSign</h1>';
    $content .= '<div class="desc">Kliko butonin më poshtë për të filluar procesin e autorizimit me DocuSign Sandbox.<br><br><b>Pas autorizimit, do të marrësh access token për thirrjet API.</b></div>';
    $content .= '<a href="' . htmlspecialchars($auth_url) . '" class="copy-btn">Autentikohu me DocuSign</a>';
    $content .= '<div class="info">Kjo është për ambient sandbox/testim. Për prodhim, përdor <b>account.docusign.com</b>.</div>';
    renderPage($content);
    exit();
}

$code = $_GET['code'];
$data = [
    'grant_type' => 'authorization_code',
    'code' => $code,
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri' => $redirect_uri
];

$ch = curl_init('https://account-d.docusign.com/oauth/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
$response = curl_exec($ch);
curl_close($ch);
$token = json_decode($response, true);

if (isset($token['access_token'])) {
    $content = '<h1>Access Token i marrë me sukses!</h1>';
    $content .= '<div class="success">Kopjo access token më poshtë dhe përdore për thirrjet e tjera DocuSign API.<br><b>Tokeni është valid për 1 orë.</b></div>';
    $content .= '<textarea id="tokenArea" readonly>' . htmlspecialchars($token['access_token']) . '</textarea>';
    $content .= '<button class="copy-btn" onclick="copyToken()">Kopjo Token</button>';
    $content .= '<div class="info">Ruaje këtë token në mënyrë të sigurt. Mos e shpërndaj publikisht.</div>';
    $content .= '<script>function copyToken(){var t=document.getElementById("tokenArea");t.select();document.execCommand("copy");alert("Tokeni u kopjua!");}</script>';
    renderPage($content);
} else {
    $content = '<h1>Gabim gjatë autentikimit</h1>';
    $content .= '<div class="error">' . htmlspecialchars($response) . '</div>';
    $content .= '<a href="docusign_auth.php" class="copy-btn">Provo përsëri</a>';
    renderPage($content);
}
