<?php
session_start();
require_once 'config.php';
require_once 'confidb.php';

// Kontrollo nëse përdoruesi është i kyçur
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT roli, zyra_id FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$roli = $user['roli'];
$zyra_id = $user['zyra_id'];

$success = null;
$error = null;

// Ruaj aplikimin në konkurs nga përdorues i thjeshtë
if ($roli !== 'zyra' && $roli !== 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apliko_konkurs'])) {
    $konkurs_id = intval($_POST['konkurs_id'] ?? 0);
    $emri = trim($_POST['emri'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefoni = trim($_POST['telefoni'] ?? '');
    $mesazhi = trim($_POST['mesazhi'] ?? '');
    // Lista e fjalëve të ndaluara
    $banWords = [
        // Shqip
        "qir", "qirje", "qif", "qifsha", "qifsh", "qifem", "qifet", "qifemi", "qifeni", "qifeni", "qifsha", "qifshin",
        "pidh", "pidhi", "pidha", "pidhar", "pidharë", "pidharja", "pidharin", "pidhat", "pidhin", "pidhar", "pidharin",
        "mut", "muti", "muter", "mutra", "mutrat", "mutin", "mutave", "mutera", "mutera", "mutera",
        "buth", "buthi", "buthat", "buthin", "buthave", "buthash", "buthash", "buthash",
        "k*rv", "kerv", "kurv", "kurva", "kurvat", "kurvash", "kurvave", "kurvëri", "kurveri", "kurveria", "kurverit",
        "t'qifsha", "t'qifsha nanen", "t'qifsha motren", "t'qifsha ropt", "t'qifsha familjen", "t'qifsha grun", "t'qifsha burrin",
        "rrot kari", "kari", "kar", "karet", "karin", "karit", "karash", "karash", "karash",
        "byth", "bytha", "bythen", "bythes", "bythash", "bythave", "bythqim", "bythqimi", "bythqimash",
        "pall", "palla", "pallim", "pallin", "pallova", "pallon", "palloj", "pallojme", "palloni",
        "leshi", "lesh", "leshko", "leshkat", "leshkat", "leshkat",
        "pick", "picka", "picken", "pickes", "pickash", "pickave",
        "robt", "ropt", "ropt e shpis", "robt e shpis", "robt e familjes", "ropt e familjes",
        "nanen", "nana", "motren", "motra", "babën", "babai", "babën", "babai",
        // Serbisht
        "pička", "picka", "kurac", "kurcem", "kurcemu", "kurcemom", "kurcemu", "kurcemom", "kurcemu", "kurcemom",
        "jebem", "jebati", "jebac", "jebacu", "jebiga", "jebote", "jebemti", "jebem ti", "jebem ti mater", "jebem ti majku",
        "govno", "guzica", "guzicu", "guzice", "guzici", "guzicom", "guzicu", "guzice", "guzici", "guzicom",
        "picka", "picku", "picke", "picki", "pickom", "picku", "picke", "picki", "pickom",
        "kurva", "kurve", "kurvi", "kurvo", "kurvama", "kurvama", "kurvama",
        "pizda", "pizde", "pizdi", "pizdo", "pizdama", "pizdama", "pizdama",
        "sisa", "sise", "sisu", "sisi", "sisom", "sisu", "sise", "sisi", "sisom",
        "majku ti jebem", "mater ti jebem", "jebem ti mater", "jebem ti majku",
        // Anglisht
        "fuck", "fucking", "fucker", "motherfucker", "motherfuckers", "fucked", "fucks", "fuk", "fuking",
        "shit", "shitty", "shitting", "shitted", "shits",
        "bitch", "bitches", "bitching", "bitchy",
        "cunt", "cunts", "cunting",
        "dick", "dicks", "dicking", "dicked",
        "asshole", "assholes", "assholic",
        "bastard", "bastards", "bastardly",
        "slut", "sluts", "slutty",
        "whore", "whores", "whoring",
        "pussy", "pussies", "pussying",
        "cock", "cocks", "cocking", "cocked",
        "jerk", "jerks", "jerking", "jerked",
        "douche", "douchebag", "douchebags",
        "bollocks", "bugger", "wanker", "tosser", "prick", "twat", "arsehole", "arse", "arseholes",
        "motherfucker", "motherfuckers", "faggot", "faggots", "fag", "fags", "homo", "homos", "gay", "gays"
    ];
    function normalizeText($text) {
        $text = strtolower($text);
        $text = str_replace(['*', '3', '@', '!', '$', '0'], ['e', 'e', 'a', 'i', 's', 'o'], $text);
        return $text;
    }
    function containsBanWord($text, $banWords) {
        $normalized = normalizeText($text);
        foreach ($banWords as $word) {
            $pattern = "/\\b" . preg_quote($word, '/') . "\\b/i";
            if (preg_match($pattern, $normalized)) {
                return true;
            }
        }
        return false;
    }
    function logBlockedAttempt($text, $userId = 'anonim') {
        $log = date('Y-m-d H:i:s') . " | User: $userId | Text: $text\n";
        file_put_contents('blocked_attempts.log', $log, FILE_APPEND);
    }
    // Kontrollo të gjitha fushat tekstuale për fjalë të ndaluara
    $fieldsToCheck = [$emri, $mesazhi];
    $hasBanWord = false;
    foreach ($fieldsToCheck as $field) {
        if (containsBanWord($field, $banWords)) {
            $hasBanWord = true;
            break;
        }
    }
    if ($hasBanWord) {
        logBlockedAttempt($emri . ' | ' . $mesazhi, $_SESSION['user_id'] ?? 'anonim');
        $aplikim_error = '❌ Përmbajtja përmban fjalë të papërshtatshme. Ju lutemi rishikoni tekstin.';
    } elseif ($konkurs_id && $emri && $email && $telefoni && $mesazhi) {
        $stmt = $pdo->prepare('INSERT INTO aplikimet_konkurs (konkurs_id, user_id, emri, email, telefoni, mesazhi) VALUES (?, ?, ?, ?, ?, ?)');
        if ($stmt->execute([$konkurs_id, $user_id, $emri, $email, $telefoni, $mesazhi])) {
            $aplikim_sukses = true;
        } else {
            $aplikim_error = 'Gabim gjatë aplikimit!';
        }
    } else {
        $aplikim_error = 'Ju lutemi plotësoni të gjitha fushat!';
    }
}

// Ruaj konkursin nëse është dërguar forma
if ($roli === 'zyra' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['posto_konkurs'])) {
    $pozita = trim($_POST['pozita'] ?? '');
    $pershkrimi = trim($_POST['pershkrimi'] ?? '');
    $afati = $_POST['afati'] ?? '';
    if ($pozita && $pershkrimi && $afati) {
        $stmt = $pdo->prepare('INSERT INTO konkurset (zyra_id, pozita, pershkrimi, afati) VALUES (?, ?, ?, ?)');
        if ($stmt->execute([$zyra_id, $pozita, $pershkrimi, $afati])) {
            $success = 'Konkursi u postua me sukses!';
        } else {
            $error = 'Gabim gjatë postimit të konkursit!';
        }
    } else {
        $error = 'Ju lutemi plotësoni të gjitha fushat!';
    }
}

?><!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Paneli i Zyrës Noteriale | Shpallje Pune</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            background: linear-gradient(120deg, #e2eafc 0%, #f8fafc 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        .container {
            max-width: 700px;
            margin: 48px auto 0 auto;
            padding: 36px 32px;
            border-radius: 22px;
            background: #fff;
            box-shadow: 0 8px 32px rgba(44,108,223,0.13);
        }
        h1 {
            color: #2d6cdf;
            margin-bottom: 32px;
            font-size: 2.2rem;
            font-weight: 800;
            text-align: center;
            letter-spacing: 1px;
        }
        .konkurs-section {
            background: #f8fafc;
            border-radius: 18px;
            padding: 32px 28px;
            margin-bottom: 36px;
            box-shadow: 0 4px 24px rgba(44,108,223,0.10);
            animation: fadeInUp 0.7s cubic-bezier(.39,.575,.56,1) both;
        }
        .konkurs-section h2 {
            color: #184fa3;
            margin-bottom: 22px;
            font-size: 1.5rem;
            font-weight: 800;
        }
        .form-group {
            margin-bottom: 22px;
        }
        label {
            display: block;
            margin-bottom: 10px;
            color: #2d6cdf;
            font-weight: 700;
            font-size: 1.08rem;
        }
        input[type="text"], input[type="date"], textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #e2eafc;
            border-radius: 10px;
            font-size: 1.08rem;
            background: #f8fafc;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        input:focus, textarea:focus {
            border-color: #2d6cdf;
            outline: none;
            box-shadow: 0 0 0 2px #e2eafc;
        }
        button {
            background: linear-gradient(90deg, #2d6cdf 60%, #184fa3 100%);
            color: white;
            padding: 14px 0;
            border: none;
            border-radius: 50px;
            width: 100%;
            font-size: 1.15rem;
            font-weight: 800;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
            margin-top: 10px;
            box-shadow: 0 4px 16px rgba(44,108,223,0.10);
        }
        button:hover {
            background: #184fa3;
            transform: translateY(-2px) scale(1.01);
        }
        .success {
            color: #388e3c;
            background: #eafaf1;
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 22px;
            font-size: 1.08rem;
            text-align: center;
            border-left: 5px solid #388e3c;
        }
        .error {
            color: #d32f2f;
            background: #ffeaea;
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 22px;
            font-size: 1.08rem;
            text-align: center;
            border-left: 5px solid #d32f2f;
        }
        .konkurset-list {
            margin-top: 24px;
        }
        .konkurs-item {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(44,108,223,0.07);
            padding: 18px 20px;
            margin-bottom: 18px;
            border-left: 6px solid #2d6cdf;
        }
        .konkurs-item h3 {
            margin: 0 0 8px 0;
            color: #184fa3;
            font-size: 1.18rem;
            font-weight: 700;
        }
        .konkurs-item .afati {
            color: #2d6cdf;
            font-size: 0.98rem;
            font-weight: 600;
        }
        .konkurs-item .pershkrimi {
            color: #333;
            font-size: 1.04rem;
            margin: 8px 0 0 0;
        }
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(40px); }
            100% { opacity: 1; transform: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-bullhorn"></i> Shpallje Pune nga Zyra Noteriale</h1>
        <?php if ($roli === 'zyra'): ?>
        <div class="konkurs-section">
            <h2><i class="fas fa-plus-circle"></i> Posto Konkurs të Ri</h2>
            <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="pozita">Pozita e Punës</label>
                    <input type="text" name="pozita" id="pozita" required placeholder="P.sh. Asistent Noterial, Sekretar...">
                </div>
                <div class="form-group">
                    <label for="pershkrimi">Përshkrimi i Detyrave</label>
                    <textarea name="pershkrimi" id="pershkrimi" rows="4" required placeholder="Përshkruani detyrat dhe kërkesat për këtë pozitë..."></textarea>
                </div>
                <div class="form-group">
                    <label for="afati">Afati i Aplikimit</label>
                    <input type="date" name="afati" id="afati" required>
                </div>
                <button type="submit" name="posto_konkurs"><i class="fas fa-paper-plane"></i> Posto Konkursin</button>
            </form>
        </div>
        <?php endif; ?>
        <div class="konkurset-list">
            <h2><i class="fas fa-list"></i> Konkursët e Shpallur</h2>
            <?php
            $stmt = $pdo->query('SELECT k.id, k.pozita, k.pershkrimi, k.afati, k.created_at, z.emri AS zyra_emri FROM konkurset k JOIN zyrat z ON k.zyra_id = z.id ORDER BY k.created_at DESC');
            if ($stmt->rowCount() > 0) {
                while ($row = $stmt->fetch()) {
                    echo '<div class="konkurs-item">';
                    echo '<h3>' . htmlspecialchars($row['pozita']) . ' <span style="font-size:0.95em;font-weight:400;color:#184fa3;">(' . htmlspecialchars($row['zyra_emri']) . ')</span></h3>';
                    echo '<div class="afati"><i class="fas fa-calendar-alt"></i> Afati i aplikimit: ' . htmlspecialchars($row['afati']) . '</div>';
                    echo '<div class="pershkrimi">' . nl2br(htmlspecialchars($row['pershkrimi'])) . '</div>';
                    echo '<div style="font-size:0.92em;color:#888;margin-top:6px;">Publikuar më: ' . htmlspecialchars($row['created_at']) . '</div>';
                    if ($roli !== 'zyra' && $roli !== 'admin') {
                        echo '<div class="apliko-section" style="margin-top:18px;">';
                        if (isset($aplikim_sukses) && $aplikim_sukses && isset($_POST['konkurs_id']) && $_POST['konkurs_id'] == $row['id']) {
                            echo '<div class="success">Aplikimi u dërgua me sukses!</div>';
                        } elseif (isset($aplikim_error) && isset($_POST['konkurs_id']) && $_POST['konkurs_id'] == $row['id']) {
                            echo '<div class="error">' . htmlspecialchars($aplikim_error) . '</div>';
                        }
                        echo '<form method="POST" class="apliko-form" enctype="multipart/form-data" style="background:#f8fafc;padding:18px 16px;border-radius:12px;box-shadow:0 2px 8px rgba(44,108,223,0.07);">';
                        echo '<input type="hidden" name="konkurs_id" value="' . $row['id'] . '">';
                        echo '<div class="form-group"><label>Emri</label><input type="text" name="emri" required pattern="[A-Za-zÇçËë\s]{2,}" placeholder="Emri"></div>';
                        echo '<div class="form-group"><label>Mbiemri</label><input type="text" name="mbiemri" required pattern="[A-Za-zÇçËë\s]{2,}" placeholder="Mbiemri"></div>';
                        echo '<div class="form-group"><label>Email</label><input type="email" name="email" required placeholder="Email-i juaj"></div>';
                        echo '<div class="form-group"><label>Telefoni</label><input type="text" name="telefoni" required pattern="\+383[1-9]\d{7}" placeholder="p.sh. +38344123456"></div>';
                        echo '<div class="form-group"><label>Data e lindjes</label><input type="date" name="datelindja" required></div>';
                        echo '<div class="form-group"><label>Adresa</label><input type="text" name="adresa" required placeholder="Adresa e plotë"></div>';
                        echo '<div class="form-group"><label>Arsimi</label><input type="text" name="arsimi" required placeholder="P.sh. Bachelor në Drejtësi"></div>';
                        echo '<div class="form-group"><label>Përvoja e punës</label><input type="text" name="pervoja" required placeholder="P.sh. 2 vite si asistent noterial"></div>';
                        echo '<div class="form-group"><label>CV (PDF)</label><input type="file" name="cv" accept="application/pdf" required></div>';
                        echo '<div class="form-group"><label>Letër motivimi</label><textarea name="mesazhi" rows="4" required placeholder="Pse po aplikoni për këtë pozitë? Përshkruani motivimin tuaj..."></textarea></div>';
                        echo '<button type="submit" name="apliko_konkurs" style="background:#2d6cdf;color:#fff;padding:12px 0;width:100%;border:none;border-radius:8px;font-size:1.1rem;font-weight:700;box-shadow:0 2px 8px rgba(44,108,223,0.08);transition:background 0.2s;"><i class="fas fa-paper-plane"></i> Apliko</button>';
                        echo '</form>';
                        echo '</div>';
                    }
                    echo '</div>';
                }
            } else {
                echo '<div class="error">Nuk ka asnjë konkurs të shpallur.</div>';
            }
            ?>
        </div>

    </div>
</body>
</html>
<?php
// filepath: c:\xampp\htdocs\noteria\dashboard.php
// Konfigurimi i raportimit të gabimeve
ini_set('display_errors', 0); // Mos shfaq gabime në faqe
ini_set('log_errors', 1);     // Log gabimet në server
ini_set('error_log', __DIR__ . '/error.log'); // Ruaj gabimet në error.log në këtë folder

session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'confidb.php';

// Merr të dhënat e përdoruesit, përfshirë rolin dhe zyra_id
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT roli, zyra_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$roli = $user['roli'];
$zyra_id = $user['zyra_id'];

// Lidh automatikisht me zyrën e parë nëse nuk ka zyra_id
if (empty($zyra_id)) {
    $stmt = $pdo->query("SELECT id FROM zyrat ORDER BY id ASC LIMIT 1");
    $default_zyra = $stmt->fetch();
    if ($default_zyra) {
        $zyra_id = $default_zyra['id'];
        // Përditëso përdoruesin në databazë
        $stmt = $pdo->prepare("UPDATE users SET zyra_id = ? WHERE id = ?");
        $stmt->execute([$zyra_id, $user_id]);
    }
}

// Genero një CSRF token nëse nuk ekziston
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Merr shërbimet noteriale
$sherbimet = [
    "Vertetim Dokumenti",
    "Legalizim",
    "Deklaratë",
    "Kontratë"
    // Shto shërbime të tjera sipas nevojës
];

$zyrat = $pdo->query("SELECT id, emri FROM zyrat")->fetchAll();

$success = null;
$error = null;

// Rezervimi i terminit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_notary'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Veprimi i paautorizuar (CSRF)!";
    } else {
        $service = trim($_POST['service'] ?? '');
        $date = $_POST['date'] ?? '';
        $time = $_POST['time'] ?? '';
        $zyra_id_selected = $_POST['zyra_id'] ?? '';
        $document_path = null;

        // Validimi bazik
        if (empty($service) || empty($date) || empty($time) || empty($zyra_id_selected)) {
            $error = "Ju lutemi plotësoni të gjitha fushat!";
        } elseif ($time > '16:00') {
            $error = "Orari maksimal për termine është ora 16:00!";
        } else {
            $weekday = date('N', strtotime($date));
            if ($weekday == 6 || $weekday == 7) {
                $error = "Zyrat noteriale nuk punojnë të Shtunën dhe të Dielën!";
            } else {
                // Kontrollo nëse termini është i lirë
                $stmt = $pdo->prepare("SELECT id FROM reservations WHERE zyra_id = ? AND date = ? AND time = ?");
                $stmt->execute([$zyra_id_selected, $date, $time]);
                if ($stmt->fetch()) {
                    $error = "Ky orar është i zënë për këtë zyrë. Ju lutemi zgjidhni një orar tjetër!";
                } else {
                    // Ruaj dokumentin nëse është ngarkuar
                    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                        $target_dir = __DIR__ . "/uploads/";
                        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                        $filename = uniqid() . "_" . basename($_FILES["document"]["name"]);
                        $target_file = $target_dir . $filename;
                        if (move_uploaded_file($_FILES["document"]["tmp_name"], $target_file)) {
                            $document_path = "uploads/" . $filename;
                        }
                    }
                    try {
                        $stmt = $pdo->prepare("INSERT INTO reservations (user_id, zyra_id, service, date, time, document_path) VALUES (?, ?, ?, ?, ?, ?)");
                        if ($stmt->execute([$user_id, $zyra_id_selected, $service, $date, $time, $document_path])) {
                            $success = "Termini u rezervua me sukses!";
                        } else {
                            $error = "Ndodhi një gabim gjatë rezervimit.";
                        }
                    } catch (Exception $e) {
                        error_log($e->getMessage());
                        $error = "Ndodhi një gabim. Ju lutemi provoni përsëri.";
                    }
                }
            }
        }
    }
}

// Shto faturën
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shto_fature'])) {
    $reservation_id = $_POST['reservation_id'];
    $zyra_id_fature = $_POST['zyra_id'];
    $nr_fatures = trim($_POST['nr_fatures']);
    $data_fatures = $_POST['data_fatures'];
    $shuma = $_POST['shuma'];
    $pershkrimi = trim($_POST['pershkrimi']);

    if ($reservation_id && $zyra_id_fature && $nr_fatures && $data_fatures && $shuma) {
        $stmt = $pdo->prepare("INSERT INTO fatura (reservation_id, zyra_id, nr_fatures, data_fatures, shuma, pershkrimi) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$reservation_id, $zyra_id_fature, $nr_fatures, $data_fatures, $shuma, $pershkrimi]);
    }
}

// Merr terminet e rezervuara për zyrën e zgjedhur (për admin ose për përdorues të lidhur me zyrë)
function get_terminet_zyres($pdo, $zyra_id) {
    $stmt = $pdo->prepare("SELECT r.id, r.service, r.date, r.time, u.emri, u.mbiemri, u.email, r.document_path
        FROM reservations r
        JOIN users u ON r.user_id = u.id
        WHERE r.zyra_id = ?
        ORDER BY r.date DESC, r.time DESC");
    $stmt->execute([$zyra_id]);
    return $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Paneli i Kontrollit | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            background: linear-gradient(120deg, #e2eafc 0%, #f8fafc 100%);
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 950px;
            margin: 40px auto;
            padding: 32px 24px;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 8px 32px rgba(44,108,223,0.08);
        }
        h1 {
            color: #2d6cdf;
            margin-bottom: 28px;
            font-size: 2.2rem;
            font-weight: 700;
            text-align: center;
        }
        .logout {
            margin-top: 24px;
            text-align: right;
        }
        .logout a {
            color: #2d6cdf;
            text-decoration: none;
            font-weight: 600;
            padding: 8px 18px;
            border-radius: 8px;
            background: #e2eafc;
            transition: background 0.2s;
        }
        .logout a:hover {
            background: #c7d6f7;
        }
        .admin-section, .zyra-section {
            background: #f8fafc;
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 32px;
            box-shadow: 0 2px 12px rgba(44,108,223,0.04);
        }
        .admin-section h2, .zyra-section h2 {
            color: #184fa3;
            margin-bottom: 18px;
            font-size: 1.5rem;
            font-weight: 700;
        }
        .admin-section ul, .zyra-section ul {
            padding-left: 18px;
        }
        .admin-section li, .zyra-section li {
            margin-bottom: 6px;
            font-size: 1rem;
        }
        .error {
            color: #d32f2f;
            background: #ffeaea;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 18px;
            font-size: 1rem;
            text-align: center;
        }
        .success {
            color: #388e3c;
            background: #eafaf1;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 18px;
            font-size: 1rem;
            text-align: center;
        }
        .info {
            color: #184fa3;
            background: #e2eafc;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 18px;
            font-size: 1rem;
            text-align: center;
        }
        .form-group {
            margin-bottom: 18px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #2d6cdf;
            font-weight: 600;
        }
        select, input[type="date"], input[type="time"], input[type="file"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2eafc;
            border-radius: 8px;
            font-size: 1rem;
            background: #f8fafc;
            transition: border-color 0.2s;
        }
        select:focus, input:focus {
            border-color: #2d6cdf;
            outline: none;
        }
        button {
            background-color: #2d6cdf;
            color: white;
            padding: 12px 0;
            border: none;
            border-radius: 8px;
            width: 100%;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 8px;
        }
        button:hover {
            background-color: #184fa3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(44,108,223,0.04);
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background: #e2eafc;
            color: #184fa3;
            font-weight: 700;
        }
        tr:nth-child(even) {
            background: #f8fafc;
        }
        tr:hover td {
            background: #e2eafc;
        }
        .no-data {
            color: #888;
            margin-top: 12px;
            text-align: center;
        }
        @media (max-width: 700px) {
            .container { padding: 8px; }
            .admin-section, .zyra-section { padding: 10px; }
            table, th, td { font-size: 0.95rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Mirë se erdhët në Panelin e Kontrollit</h1>
        <?php if ($roli === 'admin'): ?>
            <div class="admin-section">
                <h2>Menaxhimi i Zyrave Noteriale</h2>
                <?php
                echo "<h3>Lista e Zyrave:</h3><ul>";
                foreach ($zyrat as $zyra) {
                    echo "<li><strong>ID:</strong> " . htmlspecialchars($zyra['id']) . " - <strong>Emri:</strong> " . htmlspecialchars($zyra['emri']) . "</li>";
                }
                echo "</ul>";

                // Lista e përdoruesve sipas zyrave
                $stmt = $pdo->query("SELECT z.id, z.emri AS zyra_emri, u.emri, u.mbiemri, u.email FROM zyrat z LEFT JOIN users u ON u.zyra_id = z.id ORDER BY z.id");
                $current_zyra = null;
                while ($row = $stmt->fetch()) {
                    if ($current_zyra !== $row['zyra_emri']) {
                        if ($current_zyra !== null) echo "</ul>";
                        echo "<h3>Zyra: " . htmlspecialchars($row['zyra_emri']) . "</h3><ul>";
                        $current_zyra = $row['zyra_emri'];
                    }
                    if ($row['emri']) {
                        echo "<li>" . htmlspecialchars($row['emri']) . " " . htmlspecialchars($row['mbiemri']) . " - " . htmlspecialchars($row['email']) . "</li>";
                    }
                }
                if ($current_zyra !== null) echo "</ul>";
                ?>
            </div>
            <div class="admin-section">
                <h2>Terminet dhe Dokumentet për çdo Zyrë</h2>
                <?php
                foreach ($zyrat as $zyra) {
                    echo "<h3 style='margin-top:24px;'>Terminet për zyrën: " . htmlspecialchars($zyra['emri']) . "</h3>";
                    $stmt = $pdo->prepare("SELECT r.service, r.date, r.time, u.emri, u.mbiemri, u.email, r.document_path
                        FROM reservations r
                        JOIN users u ON r.user_id = u.id
                        WHERE r.zyra_id = ?
                        ORDER BY r.date DESC, r.time DESC");
                    $stmt->execute([$zyra['id']]);
                    if ($stmt->rowCount() > 0) {
                        echo "<table>";
                        echo "<tr>
                                <th>Shërbimi</th>
                                <th>Data</th>
                                <th>Ora</th>
                                <th>Pala</th>
                                <th>Email</th>
                                <th>Dokumenti</th>
                              </tr>";
                        while ($row = $stmt->fetch()) {
                            echo "<tr>
                                    <td>" . htmlspecialchars($row['service']) . "</td>
                                    <td>" . htmlspecialchars($row['date']) . "</td>
                                    <td>" . htmlspecialchars($row['time']) . "</td>
                                    <td>" . htmlspecialchars($row['emri']) . " " . htmlspecialchars($row['mbiemri']) . "</td>
                                    <td>" . htmlspecialchars($row['email']) . "</td>
                                    <td>";
                            if (!empty($row['document_path'])) {
                                echo "<a href='" . htmlspecialchars($row['document_path']) . "' target='_blank' style='color:#2d6cdf;font-weight:600;'>Shiko dokumentin</a>";
                            } else {
                                echo "<span style='color:#888;'>Nuk ka dokument</span>";
                            }
                            echo "</td></tr>";
                        }
                        echo "</table>";
                    } else {
                        echo "<div class='no-data'>Nuk ka asnjë termin të rezervuar në këtë zyrë.</div>";
                    }
                }
                ?>
            </div>
            <div class="admin-section">
                <h2>Faturat Fiskale për të gjitha zyrat</h2>
                <?php
                foreach ($zyrat as $zyra) {
                    echo "<h3 style='margin-top:24px;'>Faturat për zyrën: " . htmlspecialchars($zyra['emri']) . "</h3>";
                    $terminet = get_terminet_zyres($pdo, $zyra['id']);
                    if (count($terminet) > 0) {
                        foreach ($terminet as $termin) {
                            echo "<div style='margin-bottom:12px; padding:10px; background:#f8fafc; border-radius:8px;'>";
                            echo "<strong>Lënda:</strong> " . htmlspecialchars($termin['service']) . " | <strong>Data:</strong> " . htmlspecialchars($termin['date']) . " | <strong>Ora:</strong> " . htmlspecialchars($termin['time']);
                            // Forma për faturë fiskale
                            echo '<form method="POST" style="display:flex;gap:8px;align-items:center;margin-top:8px;">
                                <input type="hidden" name="reservation_id" value="' . $termin['id'] . '">
                                <input type="hidden" name="zyra_id" value="' . $zyra['id'] . '">
                                <input type="text" name="nr_fatures" placeholder="Nr. Faturës" required style="width:110px;">
                                <input type="date" name="data_fatures" required>
                                <input type="number" step="0.01" name="shuma" placeholder="Shuma (€)" required style="width:90px;">
                                <input type="text" name="pershkrimi" placeholder="Përshkrimi" style="width:140px;">
                                <button type="submit" name="shto_fature" style="background:#388e3c;color:#fff;padding:6px 16px;border-radius:6px;">Ruaj Faturën</button>
                            </form>';
                            // Shfaq faturat ekzistuese për këtë lëndë
                            $stmtF = $pdo->prepare("SELECT * FROM fatura WHERE reservation_id = ?");
                            $stmtF->execute([$termin['id']]);
                            while ($f = $stmtF->fetch()) {
                                $pdf_url = "fatura_pdf.php?fatura_id=" . $f['id'];
                                echo "<div style='margin-top:4px; color:#184fa3; font-size:0.97em;'>";
                                echo "Fatura: <b>" . htmlspecialchars($f['nr_fatures']) . "</b> | Data: " . htmlspecialchars($f['data_fatures']) . " | Shuma: <b>" . htmlspecialchars($f['shuma']) . "€</b> | ";
                                echo "<a href='$pdf_url' target='_blank' style='color:#2d6cdf;font-weight:600;'>Shkarko Faturën (PDF)</a>";
                                echo "</div>";
                            }
                            echo "</div>";
                        }
                    } else {
                        echo "<div class='no-data'>Nuk ka asnjë termin të rezervuar në këtë zyrë.</div>";
                    }
                }
                ?>
            </div>
        <?php else: ?>
            <?php if ($roli === 'zyra'): ?>
            <div class="zyra-section">
                <h2><i class="fas fa-euro-sign"></i> Pagesat e reja online</h2>
                <?php
                // Shfaq pagesat e fundit të bëra për këtë zyrë (nga paysera_gateway.php)
                $stmt = $pdo->prepare("SELECT p.id, p.user_id, p.service, p.date, p.time, p.amount, p.transaction_id, u.emri, u.mbiemri, u.email, p.created_at FROM payments p JOIN users u ON p.user_id = u.id WHERE p.zyra_id = ? ORDER BY p.created_at DESC LIMIT 10");
                $stmt->execute([$zyra_id]);
                if ($stmt->rowCount() > 0) {
                    echo '<table><tr><th>Paguesi</th><th>Shërbimi</th><th>Data</th><th>Ora</th><th>Shuma</th><th>ID Transaksionit</th><th>Koha</th></tr>';
                    while ($row = $stmt->fetch()) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['emri'] . ' ' . $row['mbiemri']) . '<br><span style="font-size:0.95em;color:#888;">' . htmlspecialchars($row['email']) . '</span></td>';
                        echo '<td>' . htmlspecialchars($row['service']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['date']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['time']) . '</td>';
                        echo '<td>' . number_format($row['amount'],2) . ' €</td>';
                        echo '<td style="font-size:0.97em;">' . htmlspecialchars($row['transaction_id']) . '</td>';
                        echo '<td style="font-size:0.97em;">' . htmlspecialchars($row['created_at']) . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<div class="no-data">Nuk ka pagesa të reja online për zyrën tuaj.</div>';
                }
                ?>
            </div>
            <?php endif; ?>
            <div class="zyra-section">
                <h2>Informacioni i Zyrës Suaj</h2>
                <?php
                if (empty($zyra_id)) {
                    echo "<div class='error'><strong>Përdoruesi nuk është i lidhur me asnjë zyrë. Kontaktoni administratorin!</strong></div>";
                } else {
                    $stmt = $pdo->prepare("SELECT emri FROM zyrat WHERE id = ?");
                    $stmt->execute([$zyra_id]);
                    $zyra = $stmt->fetch();
                    if ($zyra) {
                        echo "<p><strong>Zyra:</strong> " . htmlspecialchars($zyra['emri']) . "</p>";
                        $stmt = $pdo->prepare("SELECT emri, mbiemri, email FROM users WHERE zyra_id = ?");
                        $stmt->execute([$zyra_id]);
                        echo "<ul>";
                        while ($row = $stmt->fetch()) {
                            echo "<li>" . htmlspecialchars($row['emri']) . " " . htmlspecialchars($row['mbiemri']) . " - " . htmlspecialchars($row['email']) . "</li>";
                        }
                        echo "</ul>";

                        echo "<h3 style='margin-top:32px;'>Terminet e mia të rezervuara</h3>";
                        $stmt = $pdo->prepare("SELECT service, date, time FROM reservations WHERE user_id = ?");
                        $stmt->execute([$user_id]);
                        if ($stmt->rowCount() > 0) {
                            echo "<table>";
                            echo "<tr><th>Shërbimi</th><th>Data</th><th>Ora</th></tr>";
                            while ($row = $stmt->fetch()) {
                                echo "<tr><td>" . htmlspecialchars($row['service']) . "</td><td>" . htmlspecialchars($row['date']) . "</td><td>" . htmlspecialchars($row['time']) . "</td></tr>";
                            }
                            echo "</table>";
                        } else {
                            echo "<div class='no-data'>Nuk keni asnjë termin të rezervuar.</div>";
                        }
                    } else {
                        echo "<div class='error'><strong>Zyra nuk u gjet! Kontaktoni administratorin.</strong></div>";
                    }
                }
                ?>
            </div>
            <div class="zyra-section">
                <h2>Rezervo Terminin Noterial Online</h2>
                <div class="info">Orari i termineve është deri në ora <strong>16:00</strong>. Nuk mund të rezervoni të Shtunën dhe të Dielën.</div>
                <?php if ($success): ?>
                    <div class="success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="book_notary" value="1">
                    <div class="form-group">
                        <label for="zyra_id">Zgjidh Zyrën Noteriale:</label>
                        <select name="zyra_id" id="zyra_id" required>
                            <option value="">Zgjidh zyrën</option>
                            <?php foreach ($zyrat as $zyra): ?>
                                <option value="<?php echo htmlspecialchars($zyra['id']); ?>"><?php echo htmlspecialchars($zyra['emri']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="service">Shërbimi Noterial:</label>
                        <select name="service" id="service" required>
                            <option value="">Zgjidh shërbimin</option>
                            <?php foreach ($sherbimet as $sherbimi): ?>
                                <option value="<?php echo htmlspecialchars($sherbimi); ?>"><?php echo htmlspecialchars($sherbimi); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date">Data:</label>
                        <input type="date" name="date" id="date" required>
                    </div>
                    <div class="form-group">
                        <label for="time">Ora:</label>
                        <input type="time" name="time" id="time" required max="16:00">
                    </div>
                    <div class="form-group">
                        <label for="document">Ngarko Dokumentin (PDF, JPG, JPEG, PNG):</label>
                        <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                    <button type="submit">Rezervo Terminin</button>
                </form>
                <?php if ($success && isset($service, $date, $time, $zyra_id_selected)): ?>
                    <form method="POST" action="paysera_pay.php">
                        <input type="hidden" name="service" value="<?php echo htmlspecialchars($service); ?>">
                        <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
                        <input type="hidden" name="time" value="<?php echo htmlspecialchars($time); ?>">
                        <input type="hidden" name="zyra_id" value="<?php echo htmlspecialchars($zyra_id_selected); ?>">
                        <button type="submit" style="background:#388e3c;margin-top:8px;">Paguaj Online</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="logout">
            <a href="logout.php">Shkyçu</a>
        </div>
    </div>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const zyraSelect = document.getElementById('zyra_id');
    const dateInput = document.getElementById('date');
    const timeInput = document.getElementById('time');
    const form = zyraSelect.closest('form');
    let infoDiv = document.createElement('div');
    infoDiv.id = 'slot-status';
    infoDiv.style.marginTop = '8px';
    infoDiv.style.fontWeight = 'bold';
    infoDiv.style.fontSize = '1rem';
    form.appendChild(infoDiv);

    function checkSlot() {
        const zyra_id = zyraSelect.value;
        const date = dateInput.value;
        const time = timeInput.value;
        if (zyra_id && date && time) {
            fetch('check_slot.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `zyra_id=${encodeURIComponent(zyra_id)}&date=${encodeURIComponent(date)}&time=${encodeURIComponent(time)}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'busy') {
                    infoDiv.textContent = 'Ky orar është i zënë për këtë zyrë!';
                    infoDiv.style.color = '#d32f2f';
                } else if (data.status === 'free') {
                    infoDiv.textContent = 'Orari është i lirë.';
                    infoDiv.style.color = '#388e3c';
                } else {
                    infoDiv.textContent = '';
                }
            });
        } else {
            infoDiv.textContent = '';
        }
    }

    zyraSelect.addEventListener('change', checkSlot);
    dateInput.addEventListener('change', checkSlot);
    timeInput.addEventListener('change', checkSlot);
});
</script>
</body>
</html>
