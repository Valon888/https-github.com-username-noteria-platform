<?php
// filepath: c:\xampp\htdocs\noteria\dashboard.php
// Konfigurimi i raportimit të gabimeve
ini_set('display_errors', 0); // Mos shfaq gabime në faqe
ini_set('log_errors', 1);     // Log gabimet në server
ini_set('error_log', __DIR__ . '/error.log'); // Ruaj gabimet në error.log në këtë folder

// Session configuration is handled in config.php via session_helper.php
require_once 'config.php';

// ==========================================
// CHECK SESSION TIMEOUT
// ==========================================
checkSessionTimeout(getenv('SESSION_TIMEOUT') ?: 1800, 'login.php?message=session_expired');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ==========================================
// KONTROLLO AUTENTIFIKIMIN - LEJO TË GJITHË PËRDORUESIT
// ==========================================
// Të gjithë përdoruesit (admin, notary, user) mund të hyjnë në dashboard

require_once 'confidb.php';
require_once 'ad_helper.php';
if (!isset($pdo) || !$pdo) {
    die("<div style='color:red;text-align:center;margin-top:30px;'>Gabim në lidhjen me databazën. Ju lutemi kontaktoni administratorin.</div>");
}

// Merr të dhënat e përdoruesit
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT emri, mbiemri, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Merr rolin dhe zyra_id nga session nëse janë vendosur (për admin)
$roli = $_SESSION['roli'] ?? null;
$zyra_id = $_SESSION['zyra_id'] ?? null;

// Shto këtë për të shmangur warning për variablat e papërcaktuara
if (!isset($success)) $success = null;
if (!isset($error)) $error = null;
// Lidh automatikisht me zyrën e parë nëse nuk ka zyra_id
if (empty($zyra_id)) {
    $stmt = $pdo->query("SELECT id FROM zyrat ORDER BY id ASC LIMIT 1");
    $default_zyra = $stmt->fetch();
    if ($default_zyra) {
        $zyra_id = $default_zyra['id'];
        // Përpiqu të përditësoj përdoruesin në databazë - nëse kolona nuk ekziston, vazhdo
        try {
            $stmt = $pdo->prepare("UPDATE users SET zyra_id = ? WHERE id = ?");
            $stmt->execute([$zyra_id, $user_id]);
        } catch (PDOException $e) {
            // Kolona zyra_id nuk ekziston në tabelën users - vazhdo normalisht
            error_log("Notice: users table has no zyra_id column - " . $e->getMessage());
        }
    }
}

// Gjenero një CSRF token nëse nuk ekziston
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function hasValidCsrfToken(): bool {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
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

        // Validimi i avancuar
        if (empty($service) || empty($date) || empty($time) || empty($zyra_id_selected)) {
            $error = "Ju lutemi plotësoni të gjitha fushat!";
        } elseif ($time > '16:00') {
            $error = "Orari maksimal për termine është ora 16:00!";
        } else {
            $weekday = date('N', strtotime($date));
            if ($date === '2026-03-20') {
                $error = "20 Mars 2026 është ditë pushimi për Ditën e parë të Fitër Bajramit!";
            } elseif ($weekday == 6 || $weekday == 7) {
                $error = "Zyrat noteriale nuk punojnë të Shtunën dhe të Dielën!";
            } else {
                $stmt = $pdo->prepare("SELECT id FROM reservations WHERE zyra_id = ? AND date = ? AND time = ?");
                $stmt->execute([$zyra_id_selected, $date, $time]);
                if ($stmt->rowCount() > 0) {
                    $error = "Ky orar është i zënë për këtë zyrë!";
                } else {
                    // Ruaj dokumentin nëse është ngarkuar
                    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
                        $file_type = mime_content_type($_FILES["document"]["tmp_name"]);
                        $file_size = $_FILES["document"]["size"];
                        if (!in_array($file_type, $allowed_types)) {
                            $error = "Formati i dokumentit nuk lejohet! Lejohen vetëm PDF, JPG, JPEG, PNG.";
                        } elseif ($file_size > 5 * 1024 * 1024) { // 5MB limit
                            $error = "Dokumenti është shumë i madh. Maksimumi lejohet 5MB.";
                        } else {
                            $target_dir = __DIR__ . "/uploads/";
                            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                            $filename = uniqid() . "_" . basename($_FILES["document"]["name"]);
                            $target_file = $target_dir . $filename;
                            if (move_uploaded_file($_FILES["document"]["tmp_name"], $target_file)) {
                                $document_path = "uploads/" . $filename;
                            }
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
    if (!hasValidCsrfToken()) {
        $error = "Veprimi i paautorizuar (CSRF)!";
    } else {
    $reservation_id = $_POST['reservation_id'];
    $zyra_id_fature = $_POST['zyra_id'];
    $nr_fatures = trim($_POST['nr_fatures']);
    $data_fatures = $_POST['data_fatures'];
    $shuma = $_POST['shuma'];
    $pershkrimi = trim($_POST['pershkrimi']);

    if ($reservation_id && $zyra_id_fature && $nr_fatures && $data_fatures && $shuma) {
        try {
            $stmt = $pdo->prepare("INSERT INTO fatura (reservation_id, zyra_id, nr_fatures, data_fatures, shuma, pershkrimi) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$reservation_id, $zyra_id_fature, $nr_fatures, $data_fatures, $shuma, $pershkrimi]);
        } catch (PDOException $e) {
            error_log("Notice: fatura table doesn't exist - " . $e->getMessage());
        }
    }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['approve']) || isset($_POST['reject'])) && isset($_POST['reservation_id'])) {
    if (!hasValidCsrfToken()) {
        $error = "Veprimi i paautorizuar (CSRF)!";
    } elseif ($roli === 'admin') {
        $reservation_id = (int) $_POST['reservation_id'];
        $newStatus = isset($_POST['approve']) ? 'aprovohet' : 'refuzohet';
        try {
            $stmt = $pdo->prepare("UPDATE reservations SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $reservation_id]);
            $success = $newStatus === 'aprovohet' ? 'Termini u aprovua me sukses.' : 'Termini u refuzua me sukses.';
        } catch (PDOException $e) {
            error_log("Reservation status update error: " . $e->getMessage());
            $error = "Nuk u përditësua statusi i terminit.";
        }
    } else {
        $error = "Qasje e paautorizuar.";
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

// Merr terminet e rezervuara për përdoruesin aktual (nëse nuk është admin)
if ($roli !== 'admin') {
    $stmt = $pdo->prepare("SELECT r.service, r.date, r.time, u.emri, u.mbiemri, u.email, r.document_path, r.status
        FROM reservations r
        JOIN users u ON r.user_id = u.id
        WHERE r.user_id = ?
        ORDER BY r.date DESC, r.time DESC");
    $stmt->execute([$user_id]);
    $user_terminet = $stmt->fetchAll();
}

// Merr njoftimet për përdoruesin e lidhur
$notifications = [];
try {
    $stmtNotif = $pdo->prepare("SELECT id, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmtNotif->execute([$user_id]);
    $notifications = $stmtNotif->fetchAll();
} catch (PDOException $e) {
    // Tabela notifications nuk ekziston, skip
    error_log("Notifications table missing: " . $e->getMessage());
}

// Merr terminet për kalendarin (për përdoruesin ose për të gjithë nëse është admin)
if ($roli === 'admin') {
    $stmt = $pdo->query("SELECT r.id, r.service, r.date, r.time, u.emri, u.mbiemri FROM reservations r JOIN users u ON r.user_id = u.id");
    $calendar_terminet = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("SELECT r.id, r.service, r.date, r.time FROM reservations r WHERE r.user_id = ?");
    $stmt->execute([$user_id]);
    $calendar_terminet = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Merr id e noterit të zyrës (për shembull, admini i zyrës)
$noter_id = null;
if (!empty($zyra_id)) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE zyra_id = ? AND roli = 'admin' LIMIT 1");
        $stmt->execute([$zyra_id]);
        $noter_id = $stmt->fetchColumn();
    } catch (PDOException $e) {
        // zyra_id column might not exist, skip
        error_log("zyra_id error: " . $e->getMessage());
    }
}

// Ruaj mesazhin nëse është dërguar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message']) && !empty($_POST['message'])) {
    if (!hasValidCsrfToken()) {
        $error = "Veprimi i paautorizuar (CSRF)!";
    } else {
    $msg = trim($_POST['message']);
    if ($noter_id && $msg) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $noter_id, $msg]);
    }
    }
}

// Ruaj mesazhin nga admini për klientin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message_admin'], $_POST['klient_id'], $_POST['message_admin'])) {
    if (hasValidCsrfToken() && $roli === 'admin') {
        $klient_id_msg = intval($_POST['klient_id']);
        $msg_admin     = trim($_POST['message_admin']);
        if ($klient_id_msg && $msg_admin) {
            $stmtAdmMsg = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)");
            $stmtAdmMsg->execute([$user_id, $klient_id_msg, $msg_admin]);
        }
    }
}

// Merr mesazhet mes përdoruesit dhe noterit
$messages = [];
if ($noter_id) {
    $stmt = $pdo->prepare("SELECT m.*, u.emri, u.mbiemri FROM messages m JOIN users u ON m.sender_id = u.id WHERE 
        (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?) 
        ORDER BY m.created_at ASC");
    $stmt->execute([$user_id, $noter_id, $noter_id, $user_id]);
    $messages = $stmt->fetchAll();
}

// Llogarit statistika të shpejta
$total_terminet = 0;
$total_dokumente = 0;
$total_pagesa = 0;

// Mesazh urimi për Ditën e parë të Fitër Bajramit
$show_bajram_greeting = (date('Y-m-d') === '2026-03-19' || date('Y-m-d') === '2026-03-20');
$bajram_greeting_title = 'Urime Fitër Bajramin!';
$bajram_greeting_text = 'Gëzuar Fitër Bajrami! Qoftë kjo festë e bekuar burim gëzimi, paqeje dhe shëndetit për ju dhe të gjithë të afërmit tuaj.';

try {
    // Llogarit terminet totale
    if ($roli === 'admin') {
        $stmt = $pdo->query("SELECT COUNT(*) FROM reservations");
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }
    $total_terminet = $stmt->fetchColumn();

    // Llogarit dokumentet e ngarkuara
    if ($roli === 'admin') {
        $stmt = $pdo->query("SELECT COUNT(*) FROM reservations WHERE document_path IS NOT NULL AND document_path != ''");
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE user_id = ? AND document_path IS NOT NULL AND document_path != ''");
        $stmt->execute([$user_id]);
    }
    $total_dokumente = $stmt->fetchColumn();

    // Llogarit pagesat e kryera
    if ($roli === 'admin') {
        $stmt = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'completed'");
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE user_id = ? AND status = 'completed'");
        $stmt->execute([$user_id]);
    }
    $total_pagesa = $stmt->fetchColumn();
} catch (PDOException $e) {
    // Nëse tabela nuk ekziston, vendos vlera parazgjedhje
    error_log("Statistics error: " . $e->getMessage());
    $total_terminet = 0;
    $total_dokumente = 0;
    $total_pagesa = 0;
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | e-Noteria</title>
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --gov-blue: #003366;
            --gov-gold: #c49a6c;
            --sidebar-w: 260px;
            --navbar-h: 64px;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; color: #333; margin: 0; }
        h1,h2,h3,h4,h5,h6 { font-family: 'Merriweather', serif; }

        /* â”€â”€ NAVBAR â”€â”€ */
        .top-navbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1030;
            height: var(--navbar-h);
            background: var(--gov-blue);
            border-bottom: 3px solid var(--gov-gold);
            display: flex; align-items: center;
            padding: 0 20px;
            box-shadow: 0 2px 16px rgba(0,0,0,.25);
        }
        .brand { color: #fff; font-family: 'Merriweather', serif; font-size: 1.3rem; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .brand img { height: 36px; width: auto; border-radius: 4px; }
        .brand-sub { font-size: .72rem; font-weight: 300; opacity: .75; letter-spacing: .5px; display: block; }
        .nav-right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
        .nav-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--gov-gold); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .9rem; color: var(--gov-blue); flex-shrink: 0; }
        .nav-user-info { line-height: 1.2; }
        .nav-user-name { font-size: .85rem; color: #fff; font-weight: 600; }
        .nav-user-role { font-size: .7rem; color: rgba(255,255,255,.6); }

        /* â”€â”€ SIDEBAR â”€â”€ */
        .sidebar {
            position: fixed; left: 0; top: var(--navbar-h); bottom: 0;
            width: var(--sidebar-w);
            background: #fff;
            border-right: 1px solid #e0e4ea;
            overflow-y: auto;
            z-index: 1020;
            box-shadow: 2px 0 8px rgba(0,0,0,.04);
            transition: transform .28s ease;
        }
        .sidebar-section {
            padding: 16px 20px 4px;
            font-size: .7rem; font-weight: 700;
            color: #b0b5c0; text-transform: uppercase; letter-spacing: 1.2px;
        }
        .sidebar-link {
            display: flex; align-items: center; gap: 11px;
            padding: 10px 20px;
            color: #555; font-size: .88rem; text-decoration: none;
            border-left: 3px solid transparent;
            transition: all .18s;
        }
        .sidebar-link i { width: 18px; text-align: center; color: #bbb; font-size: .9rem; transition: color .18s; }
        .sidebar-link:hover { background: #f3f6ff; color: var(--gov-blue); border-left-color: var(--gov-blue); }
        .sidebar-link:hover i { color: var(--gov-blue); }
        .sidebar-link.active { background: #eef2ff; color: var(--gov-blue); border-left-color: var(--gov-blue); font-weight: 600; }
        .sidebar-link.active i { color: var(--gov-blue); }
        .sidebar-link.link-danger { color: #c0392b; }
        .sidebar-link.link-danger i { color: #e74c3c; }
        .sidebar-link.link-danger:hover { background: #fff0f0; border-left-color: #e74c3c; }

        /* â”€â”€ MAIN CONTENT â”€â”€ */
        .main-content {
            margin-left: var(--sidebar-w);
            margin-top: var(--navbar-h);
            padding: 28px;
            min-height: calc(100vh - var(--navbar-h));
        }

        /* â”€â”€ PAGE HEADER (hero) â”€â”€ */
        .page-header {
            background: linear-gradient(135deg, var(--gov-blue) 0%, #00408c 100%);
            border-radius: 14px;
            padding: 28px 32px;
            color: white;
            margin-bottom: 24px;
            position: relative; overflow: hidden;
        }
        .page-header::after {
            content: ''; position: absolute; right: -40px; top: -40px;
            width: 220px; height: 220px; border-radius: 50%;
            background: rgba(255,255,255,.05);
        }
        .page-header::before {
            content: ''; position: absolute; right: 60px; bottom: -60px;
            width: 160px; height: 160px; border-radius: 50%;
            background: rgba(196,154,108,.12);
        }
        .page-header h1 { color: white; font-size: 1.6rem; margin-bottom: 4px; position: relative; }
        .page-header p { color: rgba(255,255,255,.7); margin: 0; font-size: .88rem; position: relative; }

        /* â”€â”€ FESTIVE GREETING â”€â”€ */
        @keyframes fg-shimmer {
            0%   { transform: translateX(-100%) skewX(-15deg); }
            100% { transform: translateX(380%)  skewX(-15deg); }
        }
        @keyframes fg-float {
            0%, 100% { transform: translateY(0); }
            50%       { transform: translateY(-8px); }
        }
        @keyframes fg-twinkle {
            0%, 100% { opacity: .9; }
            50%       { opacity: .15; }
        }
        .festive-greeting {
            background: linear-gradient(135deg, #0b1a35 0%, #16325c 50%, #0b1a35 100%);
            border: 1px solid rgba(212,175,55,.35);
            border-top: 3px solid #d4af37;
            border-radius: 18px;
            padding: 36px 44px 32px;
            margin-bottom: 28px;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 14px 44px rgba(0,0,0,.3), 0 0 80px rgba(212,175,55,.06);
        }
        .festive-greeting::before {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(ellipse at 50% 0%, rgba(212,175,55,.14) 0%, transparent 65%);
            pointer-events: none;
        }
        .festive-greeting::after {
            content: '';
            position: absolute;
            top: 0; left: -70%;
            width: 40%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.05), transparent);
            animation: fg-shimmer 5.5s ease-in-out infinite;
            pointer-events: none;
        }
        .fg-stars { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
        .fg-star  { position: absolute; color: rgba(255,255,255,.7); animation: fg-twinkle 2.5s infinite alternate; line-height: 1; }
        .fg-badge {
            display: inline-block;
            background: rgba(212,175,55,.12);
            border: 1px solid rgba(212,175,55,.3);
            color: #d4af37;
            font-size: .72rem;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            padding: 3px 14px;
            border-radius: 20px;
            margin-bottom: 16px;
            position: relative; z-index: 1;
        }
        .fg-icon {
            display: block;
            font-size: 2.8rem;
            color: #d4af37;
            text-shadow: 0 0 24px rgba(212,175,55,.6);
            animation: fg-float 3.5s ease-in-out infinite;
            margin-bottom: 14px;
            position: relative; z-index: 1;
        }
        .fg-title {
            font-family: 'Merriweather', serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: #d4af37;
            margin: 0 0 10px;
            letter-spacing: .8px;
            text-shadow: 0 0 22px rgba(212,175,55,.35);
            position: relative; z-index: 1;
        }
        .fg-divider {
            width: 90px; height: 1px;
            background: linear-gradient(90deg, transparent, #d4af37, transparent);
            margin: 0 auto 16px;
            position: relative; z-index: 1;
        }
        .fg-text {
            color: rgba(255,255,255,.82);
            font-size: .95rem;
            line-height: 1.8;
            margin: 0 auto;
            max-width: 560px;
            position: relative; z-index: 1;
        }

        /* â”€â”€ STAT CARDS â”€â”€ */
        .stat-card {
            border-radius: 14px; padding: 22px 20px;
            color: white; position: relative; overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,.1);
        }
        .stat-card .stat-icon { font-size: 3rem; opacity: .18; position: absolute; right: 14px; top: 50%; transform: translateY(-50%); }
        .stat-card .stat-number { font-size: 2.2rem; font-weight: 700; font-family: 'Inter', sans-serif; line-height: 1; margin-bottom: 4px; }
        .stat-card .stat-label { font-size: .82rem; opacity: .85; }
        .stat-blue  { background: linear-gradient(135deg, #003366, #0055b3); }
        .stat-gold  { background: linear-gradient(135deg, #8a6500, #c49a6c); }
        .stat-green { background: linear-gradient(135deg, #1a5c35, #28a745); }

        /* â”€â”€ GENERIC CARD â”€â”€ */
        .dash-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.06); border: 1px solid #e9ecef; }
        .dash-card-header {
            padding: 16px 20px; border-bottom: 1px solid #f2f2f2;
            display: flex; align-items: center; justify-content: space-between;
        }
        .dash-card-header h5 { margin: 0; font-size: .95rem; color: var(--gov-blue); }
        .dash-card-body { padding: 20px; }

        /* â”€â”€ QUICK ACTIONS â”€â”€ */
        .quick-action {
            display: flex; flex-direction: column; align-items: center;
            text-align: center; padding: 24px 16px; border-radius: 12px;
            text-decoration: none; transition: all .2s;
            border: 2px solid #eee; background: #fff;
            box-shadow: 0 2px 6px rgba(0,0,0,.04);
        }
        .quick-action:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,51,102,.13); border-color: var(--gov-blue); }
        .qa-icon { width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 10px; }
        .qa-label { font-weight: 600; font-size: .88rem; color: #333; }
        .qa-desc { font-size: .75rem; color: #999; margin-top: 3px; }

        /* â”€â”€ RESERVATION CTA â”€â”€ */
        .reservation-cta {
            background: linear-gradient(135deg, var(--gov-blue) 0%, #004a99 100%);
            border-radius: 14px; padding: 32px 28px; color: white;
            position: relative; overflow: hidden;
        }
        .reservation-cta::before { content: ''; position: absolute; right: -50px; bottom: -50px; width: 220px; height: 220px; border-radius: 50%; background: rgba(255,255,255,.06); }
        .reservation-cta::after  { content: ''; position: absolute; right: 80px; top: -60px; width: 140px; height: 140px; border-radius: 50%; background: rgba(196,154,108,.15); }

        /* â”€â”€ TABLE â”€â”€ */
        .dash-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .dash-table th {
            background: #f8f9fb; color: #777;
            font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .6px;
            padding: 11px 14px; border-bottom: 2px solid #ececec;
        }
        .dash-table td { padding: 12px 14px; border-bottom: 1px solid #f5f5f5; font-size: .88rem; vertical-align: middle; }
        .dash-table tr:last-child td { border-bottom: none; }
        .dash-table tr:hover td { background: #fafbff; }

        /* â”€â”€ STATUS BADGES â”€â”€ */
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: .72rem; font-weight: 600; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-danger  { background: #f8d7da; color: #721c24; }

        /* â”€â”€ SECTION TITLE â”€â”€ */
        .section-title {
            font-size: 1rem; color: var(--gov-blue); font-family: 'Merriweather', serif;
            margin-bottom: 16px; padding-bottom: 8px;
            border-bottom: 3px solid var(--gov-gold);
            display: inline-block;
        }

        /* â”€â”€ NOTIFICATIONS â”€â”€ */
        .notif-item { padding: 12px 0; border-bottom: 1px solid #f4f4f4; display: flex; gap: 12px; align-items: flex-start; }
        .notif-item:last-child { border-bottom: none; }
        .notif-dot { width: 36px; height: 36px; border-radius: 50%; background: #eef2ff; color: var(--gov-blue); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: .85rem; }
        .notif-unread .notif-text { font-weight: 700; }
        .notif-time { font-size: .72rem; color: #bbb; margin-top: 2px; }

        /* â”€â”€ PROFILE â”€â”€ */
        .profile-avatar {
            width: 76px; height: 76px; border-radius: 50%;
            background: var(--gov-blue); display: flex; align-items: center;
            justify-content: center; color: white; font-size: 1.8rem; font-weight: 700;
            margin: 0 auto 10px;
        }

        /* â”€â”€ NEWS â”€â”€ */
        .news-card { border-left: 4px solid var(--gov-gold); padding: 14px 16px; border-radius: 0 10px 10px 0; background: #fafbff; margin-bottom: 10px; border: 1px solid #eef0f8; border-left: 4px solid var(--gov-gold); }
        .news-card h6 { color: var(--gov-blue); font-size: .92rem; margin-bottom: 4px; }
        .news-card p { font-size: .83rem; color: #666; margin: 0; }
        .news-date { font-size: .72rem; color: #bbb; white-space: nowrap; }

        /* â”€â”€ MESSAGES â”€â”€ */
        .msg-wrap { max-height: 260px; overflow-y: auto; padding: 4px; margin-bottom: 14px; }
        .msg-bubble { display: inline-block; padding: 9px 14px; border-radius: 14px; font-size: .88rem; max-width: 78%; }
        .msg-sent { background: var(--gov-blue); color: #fff; border-bottom-right-radius: 3px; }
        .msg-recv { background: #f1f3f5; color: #333; border-bottom-left-radius: 3px; }
        .msg-time { font-size: .68rem; color: #aaa; margin-top: 2px; }

        /* â”€â”€ FORMS â”€â”€ */
        .form-control, .form-select { border-radius: 8px; border-color: #dee2e6; font-size: .9rem; }
        .form-control:focus, .form-select:focus { border-color: var(--gov-blue); box-shadow: 0 0 0 .2rem rgba(0,51,102,.1); }
        .form-label { font-size: .83rem; font-weight: 600; color: #555; margin-bottom: 5px; }
        .btn-primary { background: var(--gov-blue); border-color: var(--gov-blue); font-weight: 600; border-radius: 8px; }
        .btn-primary:hover, .btn-primary:focus { background: #002244; border-color: #002244; }
        .btn-warning { font-weight: 600; border-radius: 8px; }

        /* â”€â”€ FOOTER â”€â”€ */
        footer { background: #1a1f2e; color: #8b949e; padding: 28px 0 20px; margin-top: 40px; text-align: center; }
        footer a { color: #8b949e; text-decoration: none; transition: color .18s; }
        footer a:hover { color: var(--gov-gold); }

        /* â”€â”€ MOBILE â”€â”€ */
        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); box-shadow: 4px 0 20px rgba(0,0,0,.15); }
            .main-content { margin-left: 0; }
        }
        @media (max-width: 575.98px) {
            .main-content { padding: 14px; }
            .page-header { padding: 20px 18px; }
            .page-header h1 { font-size: 1.25rem; }
            .stat-card .stat-number { font-size: 1.7rem; }
        }
    </style>
</head>
<body>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• TOP NAVBAR â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<nav class="top-navbar">
    <button class="btn text-white d-lg-none me-2 p-1" id="sidebar-toggle" style="background:none;border:none;" aria-label="Menu">
        <i class="fas fa-bars fa-lg"></i>
    </button>
    <a href="dashboard.php" class="brand">
        <img src="images/pngwing.com%20(1).png" alt="Logo" onerror="this.style.display='none'">
        <div>
            <span>e-Noteria</span>
            <span class="brand-sub">Platformë SaaS Noteriale</span>
        </div>
    </a>
    <div class="nav-right">
        <!-- Notifications bell -->
        <div class="dropdown">
            <button class="btn text-white position-relative p-2" style="background:none;border:none;" data-bs-toggle="dropdown" aria-label="Njoftimet">
                <i class="fas fa-bell"></i>
                <?php if (!empty($notifications)): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.55rem;padding:3px 5px;">
                        <?php echo count($notifications); ?>
                    </span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="min-width:300px;border-radius:12px;">
                <li><h6 class="dropdown-header fw-bold" style="color:var(--gov-blue);">Njoftimet</h6></li>
                <?php if ($notifications): foreach (array_slice($notifications,0,5) as $n): ?>
                <li>
                    <a class="dropdown-item py-2<?php echo (!$n['is_read'])?' fw-bold':''; ?>" href="#">
                        <div class="small"><?php echo htmlspecialchars($n['message']); ?></div>
                        <div class="text-muted" style="font-size:.7rem;"><?php echo date('d.m.Y H:i',strtotime($n['created_at'])); ?></div>
                    </a>
                </li>
                <?php endforeach; else: ?>
                <li><span class="dropdown-item text-muted small">Nuk ka njoftime.</span></li>
                <?php endif; ?>
            </ul>
        </div>
        <!-- User menu -->
        <div class="dropdown">
            <button class="btn d-flex align-items-center gap-2 px-2" style="background:none;border:none;" data-bs-toggle="dropdown">
                <div class="nav-avatar"><?php echo strtoupper(substr($user['emri'] ?? 'U',0,1)); ?></div>
                <div class="nav-user-info d-none d-md-block text-start">
                    <div class="nav-user-name"><?php echo htmlspecialchars(($user['emri']??'').CHR(32).($user['mbiemri']??'')); ?></div>
                    <div class="nav-user-role"><?php echo htmlspecialchars($roli === 'admin' ? 'Administrator' : 'Klient'); ?></div>
                </div>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius:12px;min-width:180px;">
                <li><a class="dropdown-item py-2" href="#profili"><i class="fas fa-user-cog me-2 text-muted"></i>Profili Im</a></li>
                <li><hr class="dropdown-divider my-1"></li>
                <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Shkyçu</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- ── SIDEBAR ───────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">
    <nav style="padding: 8px 0 24px;">
        <div class="sidebar-section">Kryesore</div>
        <a href="#overview"   class="sidebar-link active"><i class="fas fa-th-large"></i>Pasqyra</a>
        <a href="reservation.php" class="sidebar-link"><i class="fas fa-calendar-plus"></i>Rezervo Termin</a>
        <a href="#terminet"   class="sidebar-link"><i class="fas fa-calendar-check"></i>Terminet e Mia</a>

        <div class="sidebar-section">Shërbime</div>
        <a href="invoice_list.php" class="sidebar-link"><i class="fas fa-file-invoice"></i>Faturat</a>
        <a href="#mesazhe"    class="sidebar-link"><i class="fas fa-comments"></i>Mesazhe</a>
        <a href="notification.php"   class="sidebar-link"><i class="fas fa-bell"></i>Njoftimet</a>

        <?php if ($roli === 'admin'): ?>
        <div class="sidebar-section">Administrim</div>
        <a href="#admin-terminet"  class="sidebar-link"><i class="fas fa-calendar-alt"></i>Të gjitha Terminet</a>
        <a href="#faturat-admin"   class="sidebar-link"><i class="fas fa-file-alt"></i>Faturat Fiskale</a>
        <a href="statistikat.php"     class="sidebar-link"><i class="fas fa-chart-bar"></i>Statistikat</a>
        <a href="#kalendar"        class="sidebar-link"><i class="fas fa-calendar"></i>Kalendari</a>
        <?php endif; ?>

        <div class="sidebar-section">Llogaria</div>
        <a href="#profili"    class="sidebar-link"><i class="fas fa-user-cog"></i>Profili Im</a>
        <a href="news.php"      class="sidebar-link"><i class="fas fa-newspaper"></i>Lajme</a>
        <a href="#video-kons" class="sidebar-link"><i class="fas fa-video"></i>Video Konsultim</a>
        <a href="logout.php"  class="sidebar-link link-danger"><i class="fas fa-sign-out-alt"></i>Shkyçu</a>
    </nav>
</aside>

<!-- Sidebar backdrop (mobile) -->
<div id="sidebar-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1015;"></div>

<!-- ── MAIN CONTENT ───────────────────────────────────────────── -->
<main class="main-content">

    <!-- ── OVERVIEW / HERO ───────────────────────────────────────── -->
    <section id="overview">
        <?php if ($show_bajram_greeting): ?>
            <div class="festive-greeting" role="status" aria-live="polite">
                <div class="fg-stars" aria-hidden="true">
                    <span class="fg-star" style="top:12%;left:7%;font-size:.55rem;animation-delay:0s">★</span>
                    <span class="fg-star" style="top:62%;left:5%;font-size:.38rem;animation-delay:.7s">★</span>
                    <span class="fg-star" style="top:30%;left:16%;font-size:.32rem;animation-delay:1.2s">★</span>
                    <span class="fg-star" style="top:80%;left:23%;font-size:.5rem;animation-delay:.4s">★</span>
                    <span class="fg-star" style="top:18%;left:29%;font-size:.38rem;animation-delay:1.9s">★</span>
                    <span class="fg-star" style="top:88%;left:42%;font-size:.32rem;animation-delay:1s">★</span>
                    <span class="fg-star" style="top:8%;right:6%;font-size:.55rem;animation-delay:.3s">★</span>
                    <span class="fg-star" style="top:52%;right:7%;font-size:.38rem;animation-delay:1.5s">★</span>
                    <span class="fg-star" style="top:24%;right:18%;font-size:.32rem;animation-delay:.9s">★</span>
                    <span class="fg-star" style="top:74%;right:25%;font-size:.5rem;animation-delay:1.3s">★</span>
                    <span class="fg-star" style="top:42%;right:13%;font-size:.35rem;animation-delay:.5s">★</span>
                    <span class="fg-star" style="top:91%;right:38%;font-size:.45rem;animation-delay:2s">★</span>
                </div>
                <span class="fg-badge">✦ 19 &amp; 20 Mars 2026 ✦</span>
                <i class="fas fa-moon fg-icon"></i>
                <h5 class="fg-title"><?php echo htmlspecialchars($bajram_greeting_title); ?></h5>
                <div class="fg-divider"></div>
                <p class="fg-text"><?php echo htmlspecialchars($bajram_greeting_text); ?></p>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h1><i class="fas fa-home me-2" style="opacity:.8;"></i>Mirë se vini, <?php echo htmlspecialchars($user['emri'] ?? 'Përdorues'); ?>!</h1>
                    <p><i class="far fa-calendar me-1"></i><?php echo date('l, d F Y'); ?> &bull; <?php echo htmlspecialchars($roli === 'admin' ? 'Administrator i Sistemit' : 'Klient'); ?></p>
                </div>
                <div class="col-auto d-none d-sm-block" style="position:relative;z-index:1;">
                    <a href="reservation.php" class="btn btn-warning fw-bold shadow px-4">
                        <i class="fas fa-calendar-plus me-2"></i>Rezervo Termin
                    </a>
                </div>
            </div>
        </div>

        <!-- Stat cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-4">
                <div class="stat-card stat-blue">
                    <i class="fas fa-calendar-check stat-icon"></i>
                    <div class="stat-number"><?php echo (int)$total_terminet; ?></div>
                    <div class="stat-label">Terminet Totale</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-4">
                <div class="stat-card stat-gold">
                    <i class="fas fa-file-upload stat-icon"></i>
                    <div class="stat-number"><?php echo (int)$total_dokumente; ?></div>
                    <div class="stat-label">Dokumentet e Ngarkuara</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-4">
                <div class="stat-card stat-green">
                    <i class="fas fa-file-invoice-dollar stat-icon"></i>
                    <div class="stat-number"><?php echo (int)$total_pagesa; ?></div>
                    <div class="stat-label">Faturat e Lëshuara</div>
                </div>
            </div>
        </div>
    </section>

    <?php if ($roli !== 'admin'): ?>

    <!-- ── QUICK ACTIONS (Non-Admin) ───────────────────────────────────────── -->
    <section class="mb-4">
        <div class="section-title"><i class="fas fa-bolt me-2"></i>Veprime të Shpejta</div>
        <div class="row g-3">
            <div class="col-6 col-sm-3">
                <a href="reservation.php" class="quick-action">
                    <div class="qa-icon" style="background:#eef2ff;color:var(--gov-blue);"><i class="fas fa-calendar-plus"></i></div>
                    <div class="qa-label">Rezervo Termin</div>
                    <div class="qa-desc">Cakto termin noterial</div>
                </a>
            </div>
            <div class="col-6 col-sm-3">
                <a href="invoice_list.php" class="quick-action">
                    <div class="qa-icon" style="background:#fff8ee;color:#8a6500;"><i class="fas fa-file-invoice"></i></div>
                    <div class="qa-label">Faturat</div>
                    <div class="qa-desc">Shiko faturat tuaja</div>
                </a>
            </div>
            <div class="col-6 col-sm-3">
                <a href="#mesazhe" class="quick-action">
                    <div class="qa-icon" style="background:#eafaf1;color:#1a5c35;"><i class="fas fa-comments"></i></div>
                    <div class="qa-label">Mesazhe</div>
                    <div class="qa-desc">Chat me noterin</div>
                </a>
            </div>
            <div class="col-6 col-sm-3">
                <a href="#profili" class="quick-action">
                    <div class="qa-icon" style="background:#fef0f0;color:#c0392b;"><i class="fas fa-user-cog"></i></div>
                    <div class="qa-label">Profili Im</div>
                    <div class="qa-desc">Ndrysho të dhënat</div>
                </a>
            </div>
        </div>
    </section>

    <!-- ── OFFICE INFO ───────────────────────────────────────── -->
    <?php if (!empty($zyra_id)):
        $stmtZ = $pdo->prepare("SELECT emri, qyteti, shteti FROM zyrat WHERE id = ?");
        $stmtZ->execute([$zyra_id]);
        $myZyra = $stmtZ->fetch();
    ?>
    <section class="mb-4" id="zyrat">
        <div class="section-title"><i class="fas fa-building me-2"></i>Zyra Juaj</div>
        <div class="dash-card">
            <div class="dash-card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div style="width:54px;height:54px;background:linear-gradient(135deg,var(--gov-blue),#004a99);border-radius:12px;display:flex;align-items:center;justify-content:center;color:white;font-size:1.4rem;flex-shrink:0;">
                        <i class="fas fa-landmark"></i>
                    </div>
                    <div>
                        <h5 class="mb-1" style="color:var(--gov-blue);"><?php echo htmlspecialchars($myZyra['emri'] ?? ''); ?></h5>
                        <p class="mb-0 text-muted small"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars(($myZyra['qyteti'] ?? '') . ', ' . ($myZyra['shteti'] ?? '')); ?></p>
                    </div>
                </div>
                <div class="fw-bold text-muted mb-2" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.6px;">Stafi i Zyrës</div>
                <?php
                $stmtS = $pdo->prepare("SELECT emri, mbiemri, email FROM users WHERE zyra_id = ?");
                $stmtS->execute([$zyra_id]);
                $staffRows = $stmtS->fetchAll();
                ?>
                <?php if ($staffRows): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($staffRows as $s): ?>
                    <div class="list-group-item d-flex align-items-center gap-2 px-0 border-0 py-2">
                        <div style="width:34px;height:34px;border-radius:50%;background:#eef2ff;color:var(--gov-blue);display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;flex-shrink:0;">
                            <?php echo strtoupper(substr($s['emri'],0,1)); ?>
                        </div>
                        <div>
                            <div style="font-size:.88rem;font-weight:600;"><?php echo htmlspecialchars($s['emri'].' '.$s['mbiemri']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($s['email']); ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">Nuk ka staf të regjistruar.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; // zyra_id ?>

    <!-- ── RESERVATION CTA ───────────────────────────────────────── -->
    <section class="mb-4">
        <div class="reservation-cta">
            <div class="row align-items-center" style="position:relative;z-index:1;">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div style="width:56px;height:56px;background:rgba(255,255,255,.15);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.7rem;flex-shrink:0;">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div>
                            <h4 class="mb-1" style="color:#fff;font-size:1.25rem;">Rezervo Termin Noterial</h4>
                            <p class="mb-0" style="color:rgba(255,255,255,.72);font-size:.88rem;">Siguroni termin në Zyrën Noteriale — shpejt, lehtë dhe regjistroni online.</p>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge" style="background:rgba(255,255,255,.15);padding:5px 11px;font-size:.75rem;"><i class="fas fa-check me-1"></i>Konfirmim i menjëhershëm</span>
                        <span class="badge" style="background:rgba(255,255,255,.15);padding:5px 11px;font-size:.75rem;"><i class="fas fa-lock me-1"></i>Pagesë e sigurt</span>
                        <span class="badge" style="background:rgba(255,255,255,.15);padding:5px 11px;font-size:.75rem;"><i class="fas fa-clock me-1"></i>E Hënë – E Premte 08:00–16:00</span>
                    </div>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <a href="reservation.php" class="btn btn-warning btn-lg px-5 fw-bold shadow-sm">
                        <i class="fas fa-calendar-plus me-2"></i>Rezervo Tani
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ── USER RESERVATIONS ───────────────────────────────────────── -->
    <section class="mb-4" id="terminet">
        <div class="section-title"><i class="fas fa-calendar-check me-2"></i>Terminet e Mia</div>
        <div class="dash-card">
            <div class="dash-card-header">
                <h5><i class="fas fa-list-ul me-2"></i>Lista e Termineve</h5>
                <a href="reservation.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                    <i class="fas fa-plus me-1"></i>Rezervo të ri
                </a>
            </div>
            <div class="dash-card-body p-0">
                <?php if (!empty($user_terminet)): ?>
                <div class="table-responsive">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Shërbimi</th><th>Data</th><th>Ora</th>
                                <th>Statusi</th><th>Dokumenti</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($user_terminet as $t): ?>
                        <tr>
                            <td class="fw-semibold" style="color:var(--gov-blue);"><?php echo htmlspecialchars($t['service']); ?></td>
                            <td><i class="fas fa-calendar-day me-1 text-muted"></i><?php echo date('d.m.Y', strtotime($t['date'])); ?></td>
                            <td><span class="badge bg-light text-dark border"><i class="far fa-clock me-1"></i><?php echo htmlspecialchars($t['time']); ?></span></td>
                            <td>
                                <?php if ($t['status'] === 'aprovohet'): ?>
                                    <span class="status-badge badge-success"><i class="fas fa-check me-1"></i>Aprovuar</span>
                                <?php elseif ($t['status'] === 'refuzohet'): ?>
                                    <span class="status-badge badge-danger"><i class="fas fa-times me-1"></i>Refuzuar</span>
                                <?php else: ?>
                                    <span class="status-badge badge-pending"><i class="fas fa-hourglass-half me-1"></i>Në pritje</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($t['document_path'])): ?>
                                    <a href="<?php echo htmlspecialchars($t['document_path']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-3"><i class="fas fa-file me-1"></i>Shiko</a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <p class="text-muted mb-3">Nuk keni asnjë termin të rezervuar.</p>
                    <a href="reservation.php" class="btn btn-primary px-4 rounded-pill"><i class="fas fa-calendar-plus me-2"></i>Rezervo Tani</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ── OFFICE RESERVATIONS (non-admin with office) ───────────────────────────────────────── -->
    <?php if (!empty($zyra_id)): ?>
    <section class="mb-4">
        <div class="section-title"><i class="fas fa-building me-2"></i>Terminet në Zyrën Time</div>
        <div class="dash-card">
            <div class="dash-card-body p-0">
                <?php
                $stmtZ2 = $pdo->prepare("SELECT r.service, r.date, r.time, u.emri, u.mbiemri, u.email, r.document_path FROM reservations r JOIN users u ON r.user_id = u.id WHERE r.zyra_id = ? ORDER BY r.date DESC, r.time DESC");
                $stmtZ2->execute([$zyra_id]);
                $officeTerminet = $stmtZ2->fetchAll();
                ?>
                <?php if ($officeTerminet): ?>
                <div class="table-responsive">
                    <table class="dash-table">
                        <thead><tr><th>Shërbimi</th><th>Data</th><th>Ora</th><th>Klienti</th><th>Dokumenti</th></tr></thead>
                        <tbody>
                        <?php foreach ($officeTerminet as $ot): ?>
                        <tr>
                            <td class="fw-semibold" style="color:var(--gov-blue);"><?php echo htmlspecialchars($ot['service']); ?></td>
                            <td><?php echo date('d.m.Y', strtotime($ot['date'])); ?></td>
                            <td><?php echo htmlspecialchars($ot['time']); ?></td>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($ot['emri'].' '.$ot['mbiemri']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($ot['email']); ?></small>
                            </td>
                            <td>
                                <?php if (!empty($ot['document_path'])): ?>
                                    <a href="<?php echo htmlspecialchars($ot['document_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3"><i class="fas fa-download me-1"></i>Shiko</a>
                                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4 text-muted"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>Nuk ka termine në këtë zyrë.</div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php else: // roli === 'admin' ?>

    <!-- ── ADMIN DASHBOARD (Admin Only) ───────────────────────────────────────── -->

    <!-- Admin: Office List -->
    <section class="mb-4" id="admin-zyrat">
        <div class="section-title"><i class="fas fa-building me-2"></i>Zyrat Noteriale</div>
        <?php
        $stmtZa = $pdo->query("SELECT id, emri, qyteti, shteti FROM zyrat ORDER BY id");
        $allZyrat = $stmtZa->fetchAll();
        ?>
        <div class="row g-3">
        <?php foreach ($allZyrat as $z): ?>
            <div class="col-md-6 col-xl-4">
                <div class="dash-card h-100">
                    <div class="dash-card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div style="width:48px;height:48px;background:linear-gradient(135deg,var(--gov-blue),#004a99);border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-size:1.2rem;flex-shrink:0;">
                                <i class="fas fa-landmark"></i>
                            </div>
                            <div>
                                <div class="fw-bold" style="color:var(--gov-blue);"><?php echo htmlspecialchars($z['emri']); ?></div>
                                <small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($z['qyteti'].', '.$z['shteti']); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </section>

    <!-- ── ADMIN: ALL RESERVATIONS ───────────────────────────────────────── -->
    <section class="mb-4" id="admin-terminet">
        <div class="section-title"><i class="fas fa-calendar-alt me-2"></i>Të Gjitha Terminet</div>
        <?php foreach ($allZyrat as $z):
            $stmtAt = $pdo->prepare("SELECT r.id, r.service, r.date, r.time, r.status, u.emri, u.mbiemri, u.email, r.document_path FROM reservations r JOIN users u ON r.user_id = u.id WHERE r.zyra_id = ? ORDER BY r.date DESC, r.time DESC");
            $stmtAt->execute([$z['id']]);
            $atRows = $stmtAt->fetchAll();
        ?>
        <div class="dash-card mb-3">
            <div class="dash-card-header">
                <h5><i class="fas fa-landmark me-2 text-muted"></i><?php echo htmlspecialchars($z['emri']); ?> <small class="text-muted fw-normal">(<?php echo htmlspecialchars($z['qyteti']); ?>)</small></h5>
                <span class="badge rounded-pill" style="background:var(--gov-blue);"><?php echo count($atRows); ?> termine</span>
            </div>
            <div class="dash-card-body p-0">
            <?php if ($atRows): ?>
                <div class="table-responsive">
                    <table class="dash-table">
                        <thead><tr><th>Shërbimi</th><th>Data</th><th>Ora</th><th>Klienti</th><th>Dok.</th><th>Veprimi</th></tr></thead>
                        <tbody>
                        <?php foreach ($atRows as $ar): ?>
                        <tr>
                            <td class="fw-semibold" style="color:var(--gov-blue);"><?php echo htmlspecialchars($ar['service']); ?></td>
                            <td><?php echo date('d.m.Y', strtotime($ar['date'])); ?></td>
                            <td><?php echo htmlspecialchars($ar['time']); ?></td>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($ar['emri'].' '.$ar['mbiemri']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($ar['email']); ?></small>
                            </td>
                            <td>
                                <?php if (!empty($ar['document_path'])): ?>
                                    <a href="<?php echo htmlspecialchars($ar['document_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-circle p-1" style="width:30px;height:30px;"><i class="fas fa-download" style="font-size:.7rem;"></i></a>
                                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                            </td>
                            <td>
                                <?php if (isset($ar['status']) && $ar['status'] === 'në pritje'): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'],ENT_QUOTES,'UTF-8'); ?>">
                                        <input type="hidden" name="reservation_id" value="<?php echo (int)$ar['id']; ?>">
                                        <button type="submit" name="approve" class="btn btn-sm btn-success me-1 rounded-pill px-3">Aprovo</button>
                                    </form>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'],ENT_QUOTES,'UTF-8'); ?>">
                                        <input type="hidden" name="reservation_id" value="<?php echo (int)$ar['id']; ?>">
                                        <button type="submit" name="reject" class="btn btn-sm btn-danger rounded-pill px-3">Refuzo</button>
                                    </form>
                                <?php elseif (isset($ar['status']) && $ar['status'] === 'aprovohet'): ?>
                                    <span class="status-badge badge-success"><i class="fas fa-check me-1"></i>Aprovuar</span>
                                <?php elseif (isset($ar['status']) && $ar['status'] === 'refuzohet'): ?>
                                    <span class="status-badge badge-danger"><i class="fas fa-times me-1"></i>Refuzuar</span>
                                <?php else: ?>
                                    <span class="status-badge badge-pending"><i class="fas fa-hourglass-half me-1"></i>Në pritje</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-3 text-muted small">Nuk ka termine për këtë zyrë.</div>
            <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </section>

    <!-- Admin: Fiscal Invoices -->
    <section class="mb-4" id="faturat-admin">
        <div class="section-title"><i class="fas fa-file-invoice me-2"></i>Faturat Fiskale</div>
        <?php foreach ($allZyrat as $z):
            $zTerminet = get_terminet_zyres($pdo, $z['id']);
        ?>
        <div class="dash-card mb-3">
            <div class="dash-card-header">
                <h5><?php echo htmlspecialchars($z['emri']); ?></h5>
            </div>
            <div class="dash-card-body">
            <?php if ($zTerminet): foreach ($zTerminet as $termin): ?>
                <div class="border rounded-3 p-3 mb-2" style="background:#fafbff;">
                    <div class="d-flex gap-2 mb-2 align-items-center flex-wrap">
                        <span class="fw-bold text-primary" style="font-size:.9rem;"><?php echo htmlspecialchars($termin['service']); ?></span>
                        <span class="text-muted small"><?php echo date('d.m.Y', strtotime($termin['date'])); ?> <?php echo htmlspecialchars($termin['time']); ?></span>
                    </div>
                    <form method="POST" class="row g-2 align-items-end">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'],ENT_QUOTES,'UTF-8'); ?>">
                        <input type="hidden" name="reservation_id" value="<?php echo (int)$termin['id']; ?>">
                        <input type="hidden" name="zyra_id" value="<?php echo (int)$z['id']; ?>">
                        <div class="col-sm-2"><input type="text" name="nr_fatures" class="form-control form-control-sm" placeholder="Nr. Faturës" required></div>
                        <div class="col-sm-2"><input type="date" name="data_fatures" class="form-control form-control-sm" required></div>
                        <div class="col-sm-2"><input type="number" step="0.01" name="shuma" class="form-control form-control-sm" placeholder="Shuma (€)" required></div>
                        <div class="col-sm-3"><input type="text" name="pershkrimi" class="form-control form-control-sm" placeholder="Përshkrimi"></div>
                        <div class="col-sm-3"><button type="submit" name="shto_fature" class="btn btn-success btn-sm w-100 rounded-pill"><i class="fas fa-save me-1"></i>Ruaj Faturën</button></div>
                    </form>
                    <?php try {
                        $stmtF = $pdo->prepare("SELECT id, nr_fatures, data_fatures, shuma FROM fatura WHERE reservation_id = ?");
                        $stmtF->execute([$termin['id']]);
                        while ($f = $stmtF->fetch()): ?>
                    <div class="mt-2 small" style="color:var(--gov-blue);">
                        <i class="fas fa-file-invoice me-1"></i>
                        Fatura <b><?php echo htmlspecialchars($f['nr_fatures']); ?></b> |
                        <?php echo htmlspecialchars($f['data_fatures']); ?> |
                        <b>€<?php echo htmlspecialchars($f['shuma']); ?></b>
                        <a href="fatura_pdf.php?fatura_id=<?php echo urlencode($f['id']); ?>" target="_blank" class="ms-2 btn btn-sm btn-outline-primary py-0 px-2 rounded-pill"><i class="fas fa-download me-1"></i>PDF</a>
                    </div>
                    <?php endwhile; } catch (PDOException $e) { error_log($e->getMessage()); } ?>
                </div>
            <?php endforeach; else: ?>
                <p class="text-muted small mb-0">Nuk ka termine për këtë zyrë.</p>
            <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </section>

    <!-- Admin: Stats -->
    <section class="mb-4" id="statistikat">
        <div class="section-title"><i class="fas fa-chart-bar me-2"></i>Statistikat</div>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="stat-card stat-blue"><i class="fas fa-calendar-check stat-icon"></i>
                    <div class="stat-number"><?php echo (int)$total_terminet; ?></div>
                    <div class="stat-label">Terminet Totale</div></div>
            </div>
            <div class="col-md-4">
                <div class="stat-card stat-gold"><i class="fas fa-file stat-icon"></i>
                    <div class="stat-number"><?php echo (int)$total_dokumente; ?></div>
                    <div class="stat-label">Dokumentet e Ngarkuara</div></div>
            </div>
            <div class="col-md-4">
                <div class="stat-card stat-green"><i class="fas fa-file-invoice stat-icon"></i>
                    <div class="stat-number"><?php echo (int)$total_pagesa; ?></div>
                    <div class="stat-label">Faturat e Lëshuara</div></div>
            </div>
        </div>
    </section>

    <!-- Admin: Calendar -->
    <section class="mb-4" id="kalendar">
        <div class="section-title"><i class="fas fa-calendar me-2"></i>Kalendari i Termineve</div>
        <div class="dash-card">
            <div class="dash-card-body"><div id="calendar-container" style="min-height:420px;"></div></div>
        </div>
    </section>

    <!-- Admin: Messages -->
    <section class="mb-4" id="mesazhe">
        <div class="section-title"><i class="fas fa-comments me-2"></i>Mesazhe me Klientët</div>
        <?php
        $stmtKl = $pdo->query("SELECT DISTINCT u.id, u.emri, u.mbiemri, m.created_at FROM users u JOIN messages m ON u.id = m.sender_id OR u.id = m.receiver_id WHERE u.roli != 'admin' ORDER BY m.created_at DESC LIMIT 10");
        $klientet = $stmtKl->fetchAll();
        ?>
        <?php if ($klientet): ?>
        <div class="row g-3">
        <?php foreach ($klientet as $klient):
            $klient_id = $klient['id'];
            $stmtMsg = $pdo->prepare("SELECT m.*, u.emri, u.mbiemri FROM messages m JOIN users u ON u.id = m.sender_id WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?) ORDER BY m.created_at ASC LIMIT 10");
            $stmtMsg->execute([$user_id, $klient_id, $klient_id, $user_id]);
            $msgs = $stmtMsg->fetchAll();
        ?>
        <div class="col-lg-6">
            <div class="dash-card">
                <div class="dash-card-header">
                    <h5><div class="nav-avatar me-2" style="width:28px;height:28px;font-size:.7rem;"><?php echo strtoupper(substr($klient['emri'],0,1)); ?></div><?php echo htmlspecialchars($klient['emri'].' '.$klient['mbiemri']); ?></h5>
                </div>
                <div class="dash-card-body">
                    <div class="msg-wrap">
                        <?php foreach ($msgs as $msg): $sent = ($msg['sender_id'] == $user_id); ?>
                        <div class="d-flex <?php echo $sent ? 'justify-content-end' : ''; ?> mb-2">
                            <div>
                                <div class="msg-bubble <?php echo $sent ? 'msg-sent' : 'msg-recv'; ?>"><?php echo nl2br(htmlspecialchars($msg['message_text'])); ?></div>
                                <div class="msg-time <?php echo $sent ? 'text-end' : ''; ?>"><?php echo date('d.m H:i', strtotime($msg['created_at'])); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <form method="post" class="d-flex gap-2">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'],ENT_QUOTES,'UTF-8'); ?>">
                        <input type="hidden" name="send_message_admin" value="1">
                        <input type="hidden" name="klient_id" value="<?php echo (int)$klient_id; ?>">
                        <input type="text" name="message_admin" class="form-control form-control-sm rounded-pill" placeholder="Shkruani..." maxlength="500" required>
                        <button type="submit" class="btn btn-primary btn-sm rounded-circle" style="width:36px;height:36px;flex-shrink:0;"><i class="fas fa-paper-plane" style="font-size:.75rem;"></i></button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="alert alert-secondary rounded-3">Nuk ka mesazhe aktualisht.</div>
        <?php endif; ?>
    </section>

    <!-- ── NOTIFICATIONS (all users) ───────────────────────────────────────── --> 
    <section class="mb-4" id="njoftime">
        <div class="section-title"><i class="fas fa-bell me-2"></i>Njoftimet</div>
        <div class="dash-card" style="max-width:680px;">
            <div class="dash-card-body">
            <?php if ($notifications): ?>
                <?php foreach ($notifications as $n): ?>
                <div class="notif-item <?php echo (!$n['is_read']) ? 'notif-unread' : ''; ?>">
                    <div class="notif-dot"><i class="fas fa-info-circle" style="font-size:.85rem;"></i></div>
                    <div class="flex-grow-1">
                        <div class="notif-text" style="font-size:.88rem;"><?php echo htmlspecialchars($n['message']); ?></div>
                        <div class="notif-time"><?php echo date('d.m.Y H:i', strtotime($n['created_at'])); ?></div>
                    </div>
                    <?php if (!$n['is_read']): ?><span class="badge rounded-pill" style="background:var(--gov-blue);font-size:.65rem;">E re</span><?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-bell-slash fa-2x mb-2 d-block"></i>
                    <p class="mb-0">Nuk ka njoftime të reja.</p>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- MESSAGES (non-admin users only) -->
    <?php if ($roli !== 'admin'): ?>
    <section class="mb-4" id="mesazhe">
        <div class="section-title"><i class="fas fa-comments me-2"></i>Mesazhe me Noterin</div>
        <div class="dash-card" style="max-width:680px;">
            <div class="dash-card-body">
                <?php if ($noter_id): ?>
                <div class="msg-wrap mb-3">
                    <?php foreach ($messages as $msg): $sent = ($msg['sender_id'] == $user_id); ?>
                    <div class="d-flex <?php echo $sent ? 'justify-content-end' : ''; ?> mb-2">
                        <div>
                            <div class="msg-bubble <?php echo $sent ? 'msg-sent' : 'msg-recv'; ?>"><?php echo nl2br(htmlspecialchars($msg['message_text'])); ?></div>
                            <div class="msg-time <?php echo $sent ? 'text-end' : ''; ?>"><?php echo date('d.m H:i', strtotime($msg['created_at'])); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!$messages): ?><div class="text-center text-muted small py-3">Filloni bisedën me noterin tuaj.</div><?php endif; ?>
                </div>
                <form method="post" class="d-flex gap-2">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'],ENT_QUOTES,'UTF-8'); ?>">
                    <input type="hidden" name="send_message" value="1">
                    <input type="text" name="message" class="form-control rounded-pill" placeholder="Shkruani mesazhin tuaj..." required>
                    <button type="submit" class="btn btn-primary rounded-circle" style="width:42px;height:42px;flex-shrink:0;"><i class="fas fa-paper-plane"></i></button>
                </form>
                <?php else: ?>
                    <div class="alert alert-secondary rounded-3">Nuk ka noter të lidhur me zyrën tuaj.</div>
                <?php endif; ?>
            </div>
        </div>
    </section>
        <?php endif; ?>
    <?php endif; // end of admin/non-admin split ?>
    
        <!-- Profile Section -->
        <section class="mb-4" id="profili">
        <div class="section-title"><i class="fas fa-user-cog me-2"></i>Profili Im</div>
        <div class="dash-card" style="max-width:520px;">
            <div class="dash-card-body">
                <?php
                $stmtP = $pdo->prepare("SELECT emri, mbiemri, email FROM users WHERE id = ?");
                $stmtP->execute([$user_id]);
                $userData = $stmtP->fetch();
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
                    if (!hasValidCsrfToken()) {
                        echo "<div class='alert alert-danger rounded-3'><i class='fas fa-lock me-2'></i>Veprimi i paautorizuar (CSRF)!</div>";
                    } else {
                        $emri_n   = trim($_POST['emri']     ?? '');
                        $mbiemri_n = trim($_POST['mbiemri'] ?? '');
                        $email_n  = trim($_POST['email']    ?? '');
                        $pass_n   = $_POST['password']      ?? '';
                        if ($emri_n && $mbiemri_n && $email_n) {
                            $sqlP = "UPDATE users SET emri=?, mbiemri=?, email=?";
                            $parP = [$emri_n, $mbiemri_n, $email_n];
                            if (!empty($pass_n)) { $sqlP .= ", password=?"; $parP[] = password_hash($pass_n, PASSWORD_DEFAULT); }
                            $sqlP .= " WHERE id=?";
                            $parP[] = $user_id;
                            $stmtPu = $pdo->prepare($sqlP);
                            $stmtPu->execute($parP);
                            echo "<div class='alert alert-success rounded-3'><i class='fas fa-check me-2'></i>Profili u përditësua me sukses!</div>";
                            $userData = ['emri' => $emri_n, 'mbiemri' => $mbiemri_n, 'email' => $email_n];
                        }
                    }
                }
                ?>
                <div class="text-center mb-4">
                    <div class="profile-avatar"><?php echo strtoupper(substr($userData['emri'] ?? 'U', 0, 1)); ?></div>
                    <h5 class="mb-1" style="color:var(--gov-blue);"><?php echo htmlspecialchars(($userData['emri']??'').' '.($userData['mbiemri']??'')); ?></h5>
                    <small class="text-muted"><?php echo htmlspecialchars($userData['email'] ?? ''); ?></small>
                </div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'],ENT_QUOTES,'UTF-8'); ?>">
                    <div class="mb-3">
                        <label class="form-label">Emri</label>
                        <input type="text" name="emri" class="form-control" value="<?php echo htmlspecialchars($userData['emri'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mbiemri</label>
                        <input type="text" name="mbiemri" class="form-control" value="<?php echo htmlspecialchars($userData['mbiemri'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fjalëkalimi i Ri <span class="text-muted fw-normal">(opsional)</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Lëre bosh nëse nuk ndryshon" autocomplete="new-password">
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary w-100 rounded-pill py-2">
                        <i class="fas fa-save me-2"></i>Ruaj Ndryshimet
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- News Section -->
    <section class="mb-4" id="lajme">
        <div class="section-title"><i class="fas fa-newspaper me-2"></i>Lajme & Informacione Ligjore</div>
        <?php
        if ($roli === 'admin' && isset($_POST['shto_lajm'])) {
            if (!hasValidCsrfToken()) {
                echo "<div class='alert alert-danger rounded-3'>CSRF Error!</div>";
            } else {
                $titulli_l    = trim($_POST['titulli']    ?? '');
                $permbajtja_l = trim($_POST['permbajtja'] ?? '');
                if ($titulli_l && $permbajtja_l) {
                    $stmtLins = $pdo->prepare("INSERT INTO lajme (titull, permbajtje) VALUES (?, ?)");
                    $stmtLins->execute([$titulli_l, $permbajtja_l]);
                    echo "<div class='alert alert-success rounded-3'><i class='fas fa-check me-2'></i>Lajmi u publikua!</div>";
                }
            }
        }
        if ($roli === 'admin'): ?>
        <div class="dash-card mb-3">
            <div class="dash-card-header"><h5><i class="fas fa-plus-circle me-2"></i>Publiko Lajm tÃ« Ri</h5></div>
            <div class="dash-card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'],ENT_QUOTES,'UTF-8'); ?>">
                    <div class="mb-2"><input type="text" name="titulli" class="form-control" placeholder="Titulli i lajmit" required></div>
                    <div class="mb-2"><textarea name="permbajtja" class="form-control" rows="3" placeholder="PÃ«rmbajtja..." required></textarea></div>
                    <button type="submit" name="shto_lajm" class="btn btn-primary rounded-pill px-4"><i class="fas fa-paper-plane me-2"></i>Publiko Lajmin</button>
                </form>
            </div>
        </div>
        <?php endif;
        try {
            $stmtLq = $pdo->query("SELECT * FROM lajme ORDER BY data_publikimit DESC LIMIT 5");
            $dbLajme = $stmtLq->fetchAll();
        } catch (PDOException $e) { $dbLajme = []; }
        $allLajme = $dbLajme ?: [
            ['titull' => 'Rezervim Online i Termineve', 'permbajtje' => 'Tani mund të rezervoni termin noterial nga shtëpia, shpejt dhe lehtë pa pritje.', 'data_publikimit' => '2025-08-01'],
            ['titull' => 'Pagesa të Sigurta Online', 'permbajtje' => 'Platforma jonë mbështet pagesa përmes bankave kryesore dhe Paysera.', 'data_publikimit' => '2025-07-28'],
            ['titull' => 'Njoftime të Menjëhershme', 'permbajtje' => 'Çdo ndryshim në statusin e dokumenteve njoftohet automatikisht.', 'data_publikimit' => '2025-07-20'],
        ];
        foreach ($allLajme as $lajm): ?>
        <div class="news-card">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <h6 class="mb-1"><?php echo htmlspecialchars($lajm['titull'] ?? ''); ?></h6>
                <span class="news-date flex-shrink-0"><?php echo isset($lajm['data_publikimit']) ? date('d.m.Y', strtotime($lajm['data_publikimit'])) : ''; ?></span>
            </div>
            <p><?php echo nl2br(htmlspecialchars($lajm['permbajtje'] ?? '')); ?></p>
        </div>
        <?php endforeach; ?>
    </section>

    <!-- Video Consultation Section -->
    <section class="mb-4" id="video-kons">
        <div class="section-title"><i class="fas fa-video me-2"></i>Video Konsultim</div>
        <div class="dash-card" style="max-width:520px;">
            <div class="dash-card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div style="width:50px;height:50px;background:linear-gradient(135deg,#1a5c35,#28a745);border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-size:1.3rem;flex-shrink:0;">
                        <i class="fas fa-video"></i>
                    </div>
                    <div>
                        <h6 class="mb-1" style="color:var(--gov-blue);">Nis Video Thirrje</h6>
                        <p class="mb-0 text-muted small">Konsultohuni me noterin nga shtëpia, shpejt dhe sigurt.</p>
                    </div>
                </div>
                <div id="video-call-warning" style="display:none;" class="alert alert-warning rounded-3 mb-3">
                    <i class="fas fa-exclamation-triangle me-2"></i><strong>Kujdes:</strong> Thirrja do të incizohet për qëllime ligjore.
                    <div class="mt-2">
                        <a id="video-call-link" href="https://meet.jit.si/noteria_<?php echo (int)$user_id; ?>" target="_blank" class="btn btn-success btn-sm rounded-pill px-3">
                            <i class="fas fa-video me-1"></i>Hyni në Video Thirrje
                        </a>
                    </div>
                </div>
                <button id="video-call-btn" class="btn btn-outline-success rounded-pill px-4">
                    <i class="fas fa-video me-2"></i>Nis Video Thirrje
                </button>
            </div>
        </div>
    </section>

</main><!-- /.main-content -->

<!-- Footer -->
<footer class="footer mt-auto py-3" style="background:#002244;">
    <div class="container">
        <div class="mb-2" style="font-family:'Merriweather',serif;font-size:1.15rem;color:#fff;">e-Noteria</div>
        <p class="mb-3" style="font-size:.83rem;">Platformë SaaS për zyrat noteriale &mdash; Republika e Kosovës</p>
        <div class="mb-2">
            <a href="terms.php" class="me-3 small">Kushtet e Përdorimit</a>
            <a href="privatesia.php" class="me-3 small">Politika e Privatësisë</a>
            <a href="ndihma.php" class="small">Ndihma</a>
        </div>
        <small>&copy; <?php echo date('Y'); ?> e-Noteria &mdash; Krijuar nga <strong style="color:#fff;">Valon Sadiku</strong></small>
    </div>
</footer>

<script src="/assets/bootstrap/bootstrap.bundle.min.js"></script>
<script src="/assets/js/fullcalendar.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    /* link active state on sidebar + auto-close on mobile */
    var sidebar   = document.getElementById('sidebar');
    var backdrop  = document.getElementById('sidebar-backdrop');
    var toggleBtn = document.getElementById('sidebar-toggle');
    function openSidebar()  { sidebar.classList.add('open');    backdrop.style.display = 'block'; }
    function closeSidebar() { sidebar.classList.remove('open'); backdrop.style.display = 'none';  }
    if (toggleBtn) toggleBtn.addEventListener('click', function () { sidebar.classList.contains('open') ? closeSidebar() : openSidebar(); });
    if (backdrop)  backdrop.addEventListener('click', closeSidebar);

    /* Close sidebar when a link inside it is clicked (mobile) */
    sidebar.querySelectorAll('.sidebar-link').forEach(function (link) {
        link.addEventListener('click', function () { if (window.innerWidth < 992) closeSidebar(); });
    });

    /* â”€â”€ ACTIVE SIDEBAR LINK on scroll â”€â”€ */
    var sections = document.querySelectorAll('section[id], main section[id]');
    var sidebarLinks = document.querySelectorAll('.sidebar-link[href^="#"]');
    window.addEventListener('scroll', function () {
        var scrollY = window.scrollY + 100;
        sections.forEach(function (sec) {
            if (sec.offsetTop <= scrollY && sec.offsetTop + sec.offsetHeight > scrollY) {
                sidebarLinks.forEach(function (l) { l.classList.remove('active'); });
                var active = document.querySelector('.sidebar-link[href="#' + sec.id + '"]');
                if (active) active.classList.add('active');
            }
        });
    });

    /* â”€â”€ VIDEO CALL â”€â”€ */
    var vcBtn = document.getElementById('video-call-btn');
    if (vcBtn) {
        vcBtn.addEventListener('click', function () {
            document.getElementById('video-call-warning').style.display = 'block';
            this.style.display = 'none';
        });
    }

    /* Kalendari i Termineve me FullCalendar */
    var calEl = document.getElementById('calendar-container');
    if (calEl && typeof FullCalendar !== 'undefined') {
        var events = <?php echo json_encode(array_map(function ($t) {
            return [
                'title' => $t['service'] . (isset($t['emri']) ? ' â€” ' . $t['emri'] . ' ' . $t['mbiemri'] : ''),
                'start' => $t['date'] . 'T' . $t['time'],
                'allDay' => false,
                'backgroundColor' => '#003366',
                'borderColor' => '#c49a6c',
            ];
        }, $calendar_terminet)); ?>;
        var cal = new FullCalendar.Calendar(calEl, {
            initialView: 'dayGridMonth',
            locale: 'sq',
            headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
            events: events,
            height: 500,
        });
        cal.render();
    }

    /*  Kontrolli i Orarit */
    var zyraS = document.getElementById('zyra_id');
    var dateI = document.getElementById('date');
    var timeI = document.getElementById('time');
    if (zyraS && dateI && timeI) {
        function checkSlot() {
            if (!zyraS.value || !dateI.value || !timeI.value) return;
            fetch('check_slot.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'zyra_id=' + encodeURIComponent(zyraS.value) + '&date=' + encodeURIComponent(dateI.value) + '&time=' + encodeURIComponent(timeI.value),
            }).then(function (r) { return r.json(); }).then(function (d) {
                var el = document.getElementById('slot-status');
                if (!el) { el = document.createElement('div'); el.id = 'slot-status'; el.style.marginTop = '5px'; timeI.parentNode.appendChild(el); }
                if (d.status === 'busy')      { el.className = 'text-danger small fw-bold'; el.textContent = 'â›” Ky orar është i zënë!'; }
                else if (d.status === 'free') { el.className = 'text-success small fw-bold'; el.textContent = '… Orari është i lirë.'; }
                else                          { el.textContent = ''; }
            }).catch(function () {});
        }
        zyraS.addEventListener('change', checkSlot);
        dateI.addEventListener('change', checkSlot);
        timeI.addEventListener('change', checkSlot);
    }

    /* â”€â”€ AUTO-SCROLL message boxes to bottom â”€â”€ */
    document.querySelectorAll('.msg-wrap').forEach(function (el) { el.scrollTop = el.scrollHeight; });

});
</script>
</body>
</html>
<?php
// Ensure the PHP block is closed and all structures are terminated.
?>