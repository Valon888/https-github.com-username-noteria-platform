<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Inicializo variablat për të shmangur warning
$incident = '';
$room = '';
$username = '';
$telefoni = '';
$vendbanimi = '';
$adresa = '';
$komuna = '';

$errors = [];
$success = false;

// Pastrim input-i
function clean_input($key, $default = '') {
    return htmlspecialchars(trim($_POST[$key] ?? $default), ENT_QUOTES, 'UTF-8');
}

// Kontroll për fjalë të ndaluara
function contains_banned_words($text) {
    $banned = [
        "pidh", "pidhi", "kari", "kar", "byth", "rrot", "qir", "qifsh", "pall", "kurv", "lavir",
        "prostitut", "bastard", "idiot", "budall", "mut", "shurr", "lesh", "gomar"
    ];
    $text = mb_strtolower($text, 'UTF-8');
    foreach ($banned as $word) {
        if (preg_match('/\b' . preg_quote($word, '/') . '\b/', $text)) {
            return true;
        }
    }
    return false;
}

// Kontroll për simbole të pazakonta (shumë simbole jo-alfanumerike)
function has_unusual_symbols($text) {
    // Lejon shkronja, numra, hapësira dhe disa shenja pikësimi
    $allowed = '/[a-zA-Z0-9\s\.\,\!\?\-\_\(\)\:\;\'\"]+/u';
    $clean = preg_replace($allowed, '', $text);
    // Nëse ka më shumë se 8 simbole të pazakonta, kthe true
    return (mb_strlen($clean, 'UTF-8') > 8);
}

// Kontroll për orar të pazakontë (p.sh. 00:00-05:00)
function is_unusual_time() {
    $hour = (int)date('G');
    return ($hour >= 0 && $hour < 5);
}

// Kontroll për geolokacion të pazakontë (p.sh. jashtë Kosovës)
function is_unusual_geolocation($ip) {
    // Përdor një API falas për IP geolocation (ip-api.com)
    $geo = @json_decode(@file_get_contents("http://ip-api.com/json/" . $ip), true);
    if (!$geo || !isset($geo['countryCode'])) return false; // Nëse dështoi, mos blloko
    // Lejo vetëm IP nga Kosova (XK) ose Shqipëria (AL)
    return !in_array($geo['countryCode'], ['XK', 'AL']);
}

// Rate limiting
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rate_file = sys_get_temp_dir() . '/noteria_' . md5($ip);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Honeypot kontroll ---
    if (!empty($_POST['website'])) {
        $errors[] = "Raportimi u refuzua (dyshim për bot).";
    }

    // --- User agent kontroll ---
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (empty($user_agent) || preg_match('/(bot|curl|spider|python|scrapy|wget|libwww)/i', $user_agent)) {
        $errors[] = "Raportimi u refuzua (user agent i dyshimtë).";
    }

    // --- Blacklist IP/email ---
    $blacklisted_ips = ['127.0.0.2', '192.0.2.1']; // Shto IP të padëshiruara këtu
    $blacklisted_emails = ['test@tempmail.com', 'abuse@mailinator.com'];
    if (in_array($ip, $blacklisted_ips)) {
        $errors[] = "IP juaj është në listën e zezë.";
    }
    if (isset($_POST['username']) && in_array(strtolower($_POST['username']), $blacklisted_emails)) {
        $errors[] = "Emaili është në listën e zezë.";
    }

    // --- Copypaste detection (raportime identike nga IP të ndryshme në 10 min) ---
    $log_file = __DIR__ . '/raportime_log.json';
    $log = file_exists($log_file) ? json_decode(file_get_contents($log_file), true) : [];
    $recent_copypaste = 0;
    $now = time();
    foreach ($log as $entry) {
        if ($entry['incident'] === $incident && $entry['ip'] !== $ip && ($now - $entry['time']) < 600) {
            $recent_copypaste++;
        }
    }
    if ($recent_copypaste > 2) {
        $errors[] = "Ky përshkrim është përdorur nga shumë përdorues së fundmi (dyshim për spam/copypaste).";
    }

    // --- Toxicity check bazik (fjalë të rënda ose kërcënuese) ---
    $toxic_words = ['vras', 'kërcën', 'dhun', 'bomb', 'terror', 'pedofil', 'ngacm', 'gjak', 'vdekje', 'plag', 'shkatërr', 'sulmoj'];
    foreach ($toxic_words as $tw) {
        if (stripos($incident, $tw) !== false) {
            $errors[] = "Përshkrimi përmban fjalë të rrezikshme ose kërcënuese (toxic).";
            break;
        }
    }
    $now = time();
    if (file_exists($rate_file) && ($now - (int)file_get_contents($rate_file)) < 60) {
        $errors[] = "Ju mund të raportoni vetëm një herë çdo 60 sekonda.";
    }

    $room = clean_input('room');
    $username = clean_input('username', 'Anonim');
    $incident = clean_input('incident');
    $telefoni = clean_input('telefoni');
    $vendbanimi = clean_input('vendbanimi');
    $adresa = clean_input('adresa');
    $komuna = clean_input('komuna');

    // Validime bazë
    if (!preg_match('/^[a-zA-Z0-9_\-]{8,64}$/', $room)) {
        $errors[] = "Dhoma është e pavlefshme.";
    }
    if ($username !== 'Anonim' && !preg_match('/^.{3,32}$/', $username)) {
        $errors[] = "Emri i përdoruesit duhet të jetë 3-32 karaktere.";
    }
    if (strlen($incident) < 15 || strlen($incident) > 1000) {
        $errors[] = "Përshkrimi duhet të jetë 15-1000 karaktere.";
    }
    if (contains_banned_words($incident)) {
        $errors[] = "Përshkrimi përmban fjalë të ndaluara.";
    }

    // Validime për të dhënat personale
    if (empty($telefoni) || !preg_match('/^\+?\d{8,15}$/', $telefoni)) {
        $errors[] = "Numri i telefonit është i detyrueshëm dhe duhet të jetë i vlefshëm.";
    }
    if (empty($vendbanimi) || strlen($vendbanimi) < 2) {
        $errors[] = "Vendbanimi është i detyrueshëm.";
    }
    if (empty($adresa) || strlen($adresa) < 2) {
        $errors[] = "Adresa e banimit është e detyrueshme.";
    }
    if (empty($komuna) || strlen($komuna) < 2) {
        $errors[] = "Komuna është e detyrueshme.";
    }

    // --- Verifikime të avancuara të vërtetësisë ---
    // 1. Kontrollo nëse përshkrimi është raportuar nga i njëjti IP në 24 orët e fundit
    $log_file = __DIR__ . '/raportime_log.json';
    $log = file_exists($log_file) ? json_decode(file_get_contents($log_file), true) : [];
    $now = time();
    $found_duplicate = false;
    $recent_count = 0;
    foreach ($log as $entry) {
        if ($entry['ip'] === $ip && $entry['incident'] === $incident && ($now - $entry['time']) < 86400) {
            $found_duplicate = true;
        }
        if ($entry['ip'] === $ip && ($now - $entry['time']) < 86400) {
            $recent_count++;
        }
    }
    if ($found_duplicate) {
        $errors[] = "Ky përshkrim është raportuar tashmë nga ky IP në 24 orët e fundit.";
    }
    // 2. Kontrollo për spam: më shumë se 5 raportime nga i njëjti IP në 24 orë
    if ($recent_count > 5) {
        $errors[] = "Keni arritur limitin e raportimeve për 24 orë nga ky IP.";
    }
    // 3. Kontrollo nëse përshkrimi është tekst i pavlefshëm (vetëm karaktere të përsëritura ose nonsense)
    if (preg_match('/^(.)\1{9,}$/', $incident)) {
        $errors[] = "Përshkrimi duket i pavlefshëm (karaktere të përsëritura).";
    }
    if (levenshtein($incident, str_repeat($incident[0], strlen($incident))) < 5) {
        $errors[] = "Përshkrimi duket i pavlefshëm (tekst i përsëritur).";
    }

    // Kontroll për simbole të pazakonta
    if (has_unusual_symbols($incident)) {
        $errors[] = "Përshkrimi përmban shumë simbole të pazakonta.";
    }

    // Kontroll për orar të pazakontë
    if (is_unusual_time()) {
        $errors[] = "Raportimi në këtë orar është i kufizuar për arsye sigurie (00:00-05:00).";
    }

    // Kontroll për geolokacion të pazakontë
    if (is_unusual_geolocation($ip)) {
        $errors[] = "Raportimi nga vendndodhja juaj është i kufizuar.";
    }

    // Dërgimi i emailit
    if (empty($errors)) {
        // Ruaj raportimin në log për verifikime të ardhshme
        $log[] = [
            'ip' => $ip,
            'room' => $room,
            'incident' => $incident,
            'username' => $username,
            'telefoni' => $telefoni,
            'vendbanimi' => $vendbanimi,
            'adresa' => $adresa,
            'komuna' => $komuna,
            'time' => $now
        ];
        file_put_contents($log_file, json_encode($log));
        // --- SIMULIM: Ruaj emailin në file lokal në vend që të dërgohet ---
        $datetime = date('Y-m-d H:i:s');
        $sim_email = "Nga: Raportues Noteria\n";
        $sim_email .= "Për: Policia e Kosovës\n";
        $sim_email .= "Subjekti: Raportim Incidenti\n";
        $sim_email .= "Data/Koha: $datetime\n";
        $sim_email .= "\n--- Të dhëna personale ---\n";
        $sim_email .= "Emri i përdoruesit: $username\n";
        $sim_email .= "Telefoni: $telefoni\n";
        $sim_email .= "Vendbanimi: $vendbanimi\n";
        $sim_email .= "Adresa e banimit: $adresa\n";
        $sim_email .= "Komuna: $komuna\n";
        $sim_email .= "\n--- Përmbajtja ---\n";
        $sim_email .= "Dhoma: $room\n";
        $sim_email .= "IP: $ip\n";
        $sim_email .= "Data/Koha: $datetime\n";
        $sim_email .= "\nPërshkrimi i incidentit:\n$incident\n";
        file_put_contents(__DIR__ . '/raport_email_simulim.txt', $sim_email);
        file_put_contents($rate_file, $now);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <title>Raporto Incident te Policia e Kosovës</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; background: #f7fafd; margin: 0; padding: 0; }
        .container { max-width: 480px; margin: 60px auto; background: #fff; border-radius: 10px; box-shadow: 0 4px 24px rgba(0,0,0,0.09); padding: 32px; }
        h2 { color: #0052cc; }
        label { font-weight: bold; display: block; margin-top: 18px; }
        textarea, input[type="text"], input[type="email"], select { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #b3d4fc; margin-top: 6px; font-size: 1rem; }
        button { background: #0052cc; color: #fff; border: none; border-radius: 6px; padding: 12px 28px; font-size: 1.1rem; margin-top: 24px; cursor: pointer; }
        button:hover { background: #003d99; }
        .error { background: #ffe6e6; color: #b71c1c; border: 1px solid #ffb3b3; border-radius: 6px; padding: 10px 16px; margin-bottom: 18px; }
        .success { background: #e6ffed; color: #1a7f37; border: 1px solid #b7eb8f; border-radius: 6px; padding: 10px 16px; margin-bottom: 18px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Raporto Incident te Policia e Kosovës</h2>
        <?php if ($success): ?>
            <div class="success">Raporti u dërgua me sukses tek Policia e Kosovës.</div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $err) echo htmlspecialchars($err) . "<br>"; ?>
            </div>
        <?php endif; ?>
        <form method="post" action="" autocomplete="off">
            <label for="room">Dhoma (Room ID):</label>
            <input type="text" id="room" name="room" required maxlength="64" pattern="[a-zA-Z0-9_\-]{8,64}" value="<?php echo htmlspecialchars($room); ?>">

            <label for="username">Emri i përdoruesit (opsionale):</label>
            <input type="text" id="username" name="username" maxlength="32" placeholder="Anonim" value="<?php echo htmlspecialchars($username); ?>">

            <label for="telefoni">Telefoni:</label>
            <input type="text" id="telefoni" name="telefoni" required maxlength="15" placeholder="+383..." value="<?php echo htmlspecialchars($telefoni); ?>">

            <label for="vendbanimi">Vendbanimi:</label>
            <input type="text" id="vendbanimi" name="vendbanimi" required maxlength="64" value="<?php echo htmlspecialchars($vendbanimi); ?>">

            <label for="adresa">Adresa e banimit:</label>
            <input type="text" id="adresa" name="adresa" required maxlength="128" value="<?php echo htmlspecialchars($adresa); ?>">

            <label for="komuna">Komuna:</label>
            <select id="komuna" name="komuna" required>
                <option value="">Zgjidh komunën...</option>
                <?php
                $komunat = [
                    "Deçan", "Dragash", "Ferizaj", "Fushë Kosovë", "Gjakovë", "Gjilan", "Gllogoc", "Graçanicë",
                    "Hani i Elezit", "Istog", "Junik", "Kamenicë", "Kaçanik", "Klina", "Kllokot", "Leposaviq",
                    "Lipjan", "Malishevë", "Mamushë", "Mitrovicë", "Novobërdë", "Obiliq", "Partesh", "Pejë",
                    "Podujevë", "Prishtinë", "Prizren", "Rahovec", "Ranillug", "Shtërpcë", "Shtime", "Skenderaj",
                    "Suharekë", "Viti", "Vushtrri", "Zubin Potok", "Zveçan"
                ];
                foreach ($komunat as $k) {
                    $selected = ($komuna === $k) ? 'selected' : '';
                    echo "<option value=\"" . htmlspecialchars($k) . "\" $selected>" . htmlspecialchars($k) . "</option>";
                }
                ?>
            </select>

            <label for="incident">Përshkrimi i incidentit:</label>
            <textarea id="incident" name="incident" rows="6" required minlength="15" maxlength="1000" placeholder="Përshkruani incidentin, ngjarjen ose sjelljen e dyshimtë..."><?php echo htmlspecialchars($incident); ?></textarea>

            <!-- Honeypot fushë e fshehur për të kapur bot-et -->
            <input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off">

            <button type="submit">Dërgo Raportin</button>
        </form>
    </div>
</body>
</html>