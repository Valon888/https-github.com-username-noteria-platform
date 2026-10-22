<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';
use GeoIp2\Database\Reader;

try {
    // Bllokim IP sipas vendit dhe VPN për përdorim procedural (jo namespace, jo class)
    $userIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    // Whitelist për zhvilluesin (ndrysho IP ose vendos email nëse duhet)
    $developerWhitelist = [
        '127.0.0.1', // IP lokale
        '192.168.1.2', // IP e kompjuterit tënd
        '::1',        // IPv6 localhost
        // 'emai@lyti.tend' // ose email nëse ke autentikim
    ];
    // Heq testin pasi verifikuam që kodi po ekzekutohet
    // echo "<!-- Test: IP juaj është: $userIp -->";
    // echo "<h1>Testimi i whitelist - IP: $userIp</h1>";
    
    // Kontrollo nëse po vimë nga një redirect i mëparshëm për të parandaluar loop
    if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'dashboard.php') !== false) {
        // Nëse vimë nga dashboard, nuk bëjmë redirect përsëri
        return;
    }
    
    if (in_array($userIp, $developerWhitelist)) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        echo "<style>
        .dev-modal-bg {position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(44,62,80,0.18);z-index:9999;display:flex;align-items:center;justify-content:center;}
        .dev-modal {background:linear-gradient(120deg,#e0ffe0 0%,#b2ffb2 100%);border-radius:18px;box-shadow:0 8px 32px #b2ffb2a0;padding:36px 32px;max-width:480px;width:90%;font-family:Montserrat,sans-serif;color:#1a3a1a;border:2px solid #7be87b;text-align:center;animation:fadeIn 0.7s;position:relative;}
        .dev-modal h2 {font-size:2rem;font-weight:800;margin-bottom:10px;letter-spacing:-1px;}
        .dev-modal .dev-ip {font-size:1.1rem;margin-bottom:18px;}
        .dev-modal .dev-close {position:absolute;top:12px;right:18px;background:none;border:none;font-size:1.5rem;color:#2d6cdf;cursor:pointer;transition:color 0.2s;}
        .dev-modal .dev-close:hover {color:#1a3a1a;}
        .dev-modal .dev-info {color:#2d6cdf;font-weight:600;margin-bottom:8px;}
        @keyframes fadeIn {from{opacity:0;transform:translateY(-30px);}to{opacity:1;transform:translateY(0);}}
        </style>";
        echo "<div class='dev-modal-bg' id='devModalBg'>
            <div class='dev-modal'>
                <button class='dev-close' onclick=\"document.getElementById('devModalBg').style.display='none'\">&times;</button>
                <h2>👨‍💻 Akses Zhvilluesi</h2>
                <div class='dev-ip'>IP: <b>$userIp</b></div>";
        if (stripos($uri, 'video_call') !== false) {
            echo "<div class='dev-info'>Lejohet video thirrja për zhvilluesin.</div>";
            echo "</div></div>";
            return;
        }
        // Lista e faqeve që lejohen pa redirect
        $allowedPages = ['dashboard.php', 'login.php', 'index.php'];
        $currentPage = basename($_SERVER['PHP_SELF']);
        
        // Kontrollo nëse jemi në një faqe të lejuar
        if (in_array($currentPage, $allowedPages) || stripos($uri, 'dashboard.php') !== false || basename($uri) === 'SecurityHeaders.php') {
            echo "<div class='dev-info'>Aksesi i lejuar për faqen: {$currentPage}</div>";
            echo "</div></div>";
            return;
        } else {
            // Redirect vetëm nëse jemi në një faqe që nuk është e lejuar
            echo "<div class='dev-info'>Po ridrejtoheni në dashboard...</div>";
            echo "</div></div>";
            
            // Përdor redirect të thjeshtë pa JavaScript për të shmangur problemet
            header('Location: dashboard.php');
            exit;
        }
        echo "<div class='dev-info'>Jeni në dashboard ose në SecurityHeaders.php si zhvillues.</div>";
        echo "</div></div>";
        return;
    }
    $blockedCountries = [
        'RS','RU','CN','ES','GR','SK','RO','CY','IN','BR','AR','CU','VE','IR','IQ','SY','ZA','DZ','EG'
    ];
    $mmdbPath = __DIR__ . '/geoip/GeoLite2-Country.mmdb';
    if (file_exists($mmdbPath)) {
        try {
            $reader = new Reader($mmdbPath);
            $record = $reader->country($userIp);
            $countryCode = $record->country->isoCode;
            // Vetëm për përdoruesit nga vendet jo mike të Kosovës, blloko nëse është VPN
            if (in_array($countryCode, $blockedCountries)) {
                $vpnListPath = __DIR__ . '/vpn_blocklist.txt';
                $logPath = __DIR__ . '/blocked_attempts.log';
                $now = date('Y-m-d H:i:s');
                if (file_exists($vpnListPath)) {
                    $vpnList = file($vpnListPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    if (in_array($userIp, $vpnList)) {
                        // Log VPN block
                        $logMsg = "$now | IP: $userIp | Reason: VPN Block | Country: $countryCode\n";
                        file_put_contents($logPath, $logMsg, FILE_APPEND);
                        header('HTTP/1.1 403 Forbidden');
                        echo '<h2>Qasja u bllokua: U detektua përdorimi i VPN nga një vend i bllokuar.</h2>';
                        echo '<p>Nëse mendoni se kjo është gabim, ju lutemi kontaktoni <a href="mailto:support@noteria.com">support@noteria.com</a> dhe dërgoni këtë IP: <b>' . htmlspecialchars($userIp) . '</b>.</p>';
                        exit;
                    }
                }
                // Log country block
                $logMsg = "$now | IP: $userIp | Reason: Country Block | Country: $countryCode\n";
                file_put_contents($logPath, $logMsg, FILE_APPEND);
                header('HTTP/1.1 403 Forbidden');
                echo '<h2>Qasja u bllokua: Vendi juaj nuk lejohet në këtë platformë.</h2>';
                echo '<p>Nëse mendoni se kjo është gabim, ju lutemi kontaktoni <a href="mailto:support@noteria.com">support@noteria.com</a> dhe dërgoni këtë IP: <b>' . htmlspecialchars($userIp) . '</b>.</p>';
                exit;
            }
        } catch (Exception $e) {
            // Nëse ndodh ndonjë gabim, lejo aksesin
            echo '<b>GeoIP Exception:</b> ' . $e->getMessage();
        }
    }
} catch (Throwable $e) {
    echo '<b>Fatal Error:</b> ' . $e->getMessage();
}