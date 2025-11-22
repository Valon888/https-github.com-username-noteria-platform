<?php
// docusign_callback.php
// Ky file pranon authorization code nga DocuSign dhe e ridrejton te docusign_auth.php për të marrë access token.

function renderPage($content) {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>DocuSign Callback | Noteria</title>';
    echo '<link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">';
    echo '<style>
        body { background: linear-gradient(120deg, #e2eafc 0%, #f8fafc 100%); font-family: Montserrat, Arial, sans-serif; margin: 0; padding: 0; }
        .container { max-width: 520px; margin: 60px auto; background: #fff; border-radius: 18px; box-shadow: 0 8px 32px rgba(0,0,0,0.10); padding: 40px 32px; text-align: center; }
        h1 { color: #2d6cdf; font-size: 2.1rem; font-weight: 700; margin-bottom: 18px; }
        .desc { color: #444; font-size: 1.1rem; margin-bottom: 28px; }
        .loader { border: 6px solid #e2eafc; border-top: 6px solid #2d6cdf; border-radius: 50%; width: 48px; height: 48px; animation: spin 1s linear infinite; margin: 32px auto; }
        @keyframes spin { 100% { transform: rotate(360deg); } }
    </style></head><body><div class="container">' . $content . '</div></body></html>';
}

if (isset($_GET['code'])) {
    $code = urlencode($_GET['code']);
    $redirect = "docusign_auth.php?code=$code";
    $content = '<h1>Po përpunoj autorizimin…</h1>';
    $content .= '<div class="desc">Ju lutem prisni, po ridrejtoheni automatikisht për të marrë access token nga DocuSign.</div>';
    $content .= '<div class="loader"></div>';
    $content .= '<script>setTimeout(function(){ window.location.href = "' . $redirect . '"; }, 1200);</script>';
    renderPage($content);
    exit();
} else {
    $content = '<h1>Autorizimi nuk u gjet</h1>';
    $content .= '<div class="desc">Nuk u mor asnjë authorization code nga DocuSign.<br>Provo sërish procesin e autentikimit.</div>';
    renderPage($content);
    exit();
}
