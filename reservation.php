<?php
// filepath: reservation.php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Gjeneroni CSRF token vetëm nëse nuk ekziston
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// -------------------------
// VARIABLAT
// -------------------------
$success        = null;
$error          = null;
$reservation_id = '';

// Merr rezervimin e fundit "pending" të përdoruesit
$stmtLast = $pdo->prepare("SELECT id FROM reservations WHERE user_id = ? AND payment_status = 'pending' ORDER BY id DESC LIMIT 1");
$stmtLast->execute([$_SESSION['user_id']]);
$rowLast = $stmtLast->fetch();
if ($rowLast) {
    $reservation_id = $rowLast['id'];
}

// -------------------------
// RUAJ REZERVIMIN (POST)
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_notary'])) {
    // Verifiko CSRF token
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        // Log unauthorized action in audit_log
        try {
            $userId = $_SESSION['user_id'] ?? 0;
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $stmtAudit = $pdo->prepare("INSERT INTO audit_log (user_id, action, details, ip_address, created_at) VALUES (?, 'csrf_failure', ?, ?, NOW())");
            $stmtAudit->execute([
                $userId,
                'CSRF token mismatch during reservation POST',
                $ip
            ]);
        } catch (PDOException $e) {
            error_log('Audit log insert failed: ' . $e->getMessage());
        }
        // Mos shfaq mesazh për përdoruesin, vetëm log
        $error = null;
    } else {
        $userId  = $_SESSION['user_id'];
        $service = trim($_POST['service']  ?? '');
        $zyra_id = trim($_POST['zyra_id']  ?? '');
        $date    = $_POST['date']  ?? '';
        $time    = $_POST['time']  ?? '';
        $payment_method = trim($_POST['payment_method'] ?? 'card');
        $punonjesi_id = !empty($_POST['punonjesi_id']) ? (int)$_POST['punonjesi_id'] : null;

        if (empty($service) || empty($zyra_id) || empty($date) || empty($time)) {
            $error = "Ju lutemi plotësoni të gjitha fushat e detyrueshme!";
        } else {
            // Kontrollo orarin e Ramazanit
            $ramazan_start = '2026-02-19';
            $ramazan_end   = '2026-03-18';
            if ($date >= $ramazan_start && $date <= $ramazan_end) {
                if ($time > '15:00') $error = "Orari maksimal gjatë Ramazanit është ora 15:00!";
            } else {
                if ($time > '16:00') $error = "Orari maksimal është ora 16:00!";
            }

            // Kontrollo ditët zyrtare të pushimit
            if (!$error && $date === '2026-03-20') {
                $error = "20 Mars 2026 është ditë zyrtare pushimi (Dita e parë e Fitër Bajramit). Zyrat noteriale janë të mbyllura.";
            }

            // Kontrollo ditën e punës
            if (!$error) {
                $weekday = date('N', strtotime($date));
                if ($weekday == 6 || $weekday == 7) $error = "Zyrat noteriale nuk punojnë të Shtunën dhe të Dielën!";
            }

            // Kontrollo nëse orari është i zënë
            if (!$error) {
                $stmtChk = $pdo->prepare("SELECT id FROM reservations WHERE zyra_id = ? AND date = ? AND time = ?");
                $stmtChk->execute([$zyra_id, $date, $time]);
                if ($stmtChk->rowCount() > 0) $error = "Ky orar është i zënë për këtë zyrë!";
            }

            // Validate that selected employee belongs to the selected office and is active
            if (!$error && $punonjesi_id) {
                $employeeFound = false;

                // Try with punonjesit table first (newer schema)
                try {
                    $stmtEmpCheck = $pdo->prepare("SELECT id FROM punonjesit WHERE id = ? AND zyra_id = ? AND statusi = 'aktiv'");
                    $stmtEmpCheck->execute([$punonjesi_id, $zyra_id]);
                    $employeeFound = $stmtEmpCheck->rowCount() > 0;
                } catch (PDOException $e) {
                    error_log("Employee check failed on punonjesit table: " . $e->getMessage());
                }

                // Try with punetoret table (legacy schema) only if still not found
                if (!$employeeFound) {
                    try {
                        $stmtEmpCheck = $pdo->prepare("SELECT id FROM punetoret WHERE id = ? AND zyra_id = ? AND active = 1");
                        $stmtEmpCheck->execute([$punonjesi_id, $zyra_id]);
                        $employeeFound = $stmtEmpCheck->rowCount() > 0;
                    } catch (PDOException $e) {
                        error_log("Legacy employee check skipped (punetoret table missing or invalid): " . $e->getMessage());
                    }
                }

                if (!$employeeFound) {
                    error_log("Selected employee not available for reservation; continuing without employee assignment. user_id=" . (int)$userId . ", zyra_id=" . (int)$zyra_id . ", requested_employee_id=" . (int)$punonjesi_id);
                    $punonjesi_id = null;
                }
            }

            // Ruaj rezervimin
            if (!$error) {
                try {
                    // Try to insert with punonjesi_id and payment_method columns
                    $stmtIns = $pdo->prepare("INSERT INTO reservations (user_id, zyra_id, punonjesi_id, service, date, time, payment_method, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                    if ($stmtIns->execute([$userId, $zyra_id, $punonjesi_id, $service, $date, $time, $payment_method])) {
                        $reservation_id = $pdo->lastInsertId();
                        $success = "Termini u rezervua me sukses! Tani mund të kryeni pagesën.";
                        // Rifresko CSRF token pas veprimit të suksesshëm
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    } else {
                        $error = "Ndodhi një gabim gjatë rezervimit. Provoni përsëri.";
                    }
                } catch (PDOException $e) {
                    // If payment_method column doesn't exist, try without it
                    error_log("Payment method insert failed: " . $e->getMessage() . ". Retrying without payment_method column.");
                    try {
                        $stmtIns = $pdo->prepare("INSERT INTO reservations (user_id, zyra_id, punonjesi_id, service, date, time, payment_status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                        if ($stmtIns->execute([$userId, $zyra_id, $punonjesi_id, $service, $date, $time])) {
                            $reservation_id = $pdo->lastInsertId();
                            // Store payment_method in session for later use
                            $_SESSION['last_payment_method'] = $payment_method;
                            $success = "Termini u rezervua me sukses! Tani mund të kryeni pagesën.";
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        } else {
                            $error = "Ndodhi një gabim gjatë rezervimit. Provoni përsëri.";
                        }
                    } catch (PDOException $e2) {
                        error_log("Reservation insert error: " . $e2->getMessage());
                        $error = "Ndodhi një gabim. Ju lutemi provoni përsëri.";
                    }
                }
            }
        }
    }
}

// Merr zyrat
$stmt  = $pdo->query("SELECT id, emri, qyteti, shteti FROM zyrat");
$zyrat = $stmt->fetchAll();

// Service pricing map - base prices (TVSH will be added on frontend)
$service_prices = [
    'Kontratë Shitblerjeje' => 50,
    'Kontratë dhuratë' => 40,
    'Kontratë qiraje' => 30,
    'Kontratë furnizimi' => 35,
    'Kontratë përkujdesjeje' => 40,
    'Kontratë prenotimi' => 30,
    'Kontratë të tjera të lejuara me ligj' => 50,
    'Autorizim për vozitje të automjetit' => 10,
    'Legalizim' => 25,
    'Vertetim Dokumenti' => 15,
    'Deklaratë' => 20,
    'Këmbimet Pronash' => 30
];
$service_prices_json = json_encode($service_prices);

// Gjuha
$lang = $_GET['lang'] ?? $_COOKIE['lang'] ?? 'sq';
if (!in_array($lang, ['sq','sr','en'])) $lang = 'sq';
setcookie('lang', $lang, time()+60*60*24*30, '/');

$labels = [
    'sq' => [
        'reserve_title' => 'Rezervo Terminin Tuaj',
        'reserve_sub'   => 'Siguro një termin noterial në zyrën më të afërt',
        'form_title'    => 'Plotësoni Formularin',
        'service'       => 'Shërbimi Noterial',
        'date'          => 'Data e Rezervimit',
        'time'          => 'Ora e Preferuar',
        'submit'        => 'Rezervo Terminin',
        'offices'       => 'Zyrat Noteriale',
        'upload_doc'    => 'Ngarko Dokument',
        'choose_lang'   => 'Gjuha:',
    ],
    'sr' => [
        'reserve_title' => 'Rezervišite svoj termin',
        'reserve_sub'   => 'Obezbedite notarski termin u najbližoj kancelariji',
        'form_title'    => 'Popunite formular',
        'service'       => 'Notarska usluga',
        'date'          => 'Datum rezervacije',
        'time'          => 'Željeno vreme',
        'submit'        => 'Rezerviši termin',
        'offices'       => 'Notarske kancelarije',
        'upload_doc'    => 'Otpremi dokument',
        'choose_lang'   => 'Jezik:',
    ],
    'en' => [
        'reserve_title' => 'Book Your Appointment',
        'reserve_sub'   => 'Book a notary appointment at the nearest office',
        'form_title'    => 'Fill the Form',
        'service'       => 'Notary Service',
        'date'          => 'Reservation Date',
        'time'          => 'Preferred Time',
        'submit'        => 'Book Appointment',
        'offices'       => 'Notary Offices',
        'upload_doc'    => 'Upload Document',
        'choose_lang'   => 'Language:',
    ],
];
$L = $labels[$lang];

// Ditar Ramazanit për template
$today         = date('Y-m-d');
$ramazan_start = '2026-02-19';
$ramazan_end   = '2026-03-18';
$is_ramazan    = ($today >= $ramazan_start && $today <= $ramazan_end);
$max_time      = $is_ramazan ? '15:00' : '16:00';

// Mesazh urimi për Ditën e parë të Fitër Bajramit
$show_bajram_greeting = ($today === '2026-03-19' || $today === '2026-03-20');
$bajram_greeting_title = 'Urime Fitër Bajramin!';
$bajram_greeting_text = 'Gëzuar Fitër Bajrami! Qoftë kjo festë e bekuar burim gëzimi, paqeje dhe shëndetit për ju dhe të gjithë të afërmit tuaj.';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($L['reserve_title']); ?> | Noteria Shtetërore</title>
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Merriweather:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        :root { --gov-blue:#003366; --gov-gold:#c49a6c; --bg-light:#f4f7f6; }
        body { font-family:'Inter',sans-serif; background:var(--bg-light); color:#333; display:flex; flex-direction:column; min-height:100vh; }
        h1,h2,h3,h4,h5,h6 { font-family:'Merriweather',serif; color:var(--gov-blue); }
        header { background:var(--gov-blue); border-bottom:4px solid var(--gov-gold); padding:15px 0; color:white; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
        .navbar-brand { font-weight:700; font-size:1.5rem; color:#fff !important; letter-spacing:.5px; }
        .header-subtitle { font-size:.85rem; opacity:.9; font-weight:300; text-transform:uppercase; letter-spacing:1px; }
        .card { border:none; border-radius:8px; box-shadow:0 4px 6px rgba(0,0,0,0.04); margin-bottom:2rem; border-top:3px solid var(--gov-blue); }
        .card-header { background:#fff; border-bottom:1px solid #eee; padding:1.25rem 1.5rem; }
        .card-header h4 { margin:0; font-size:1.25rem; color:var(--gov-blue); font-weight:700; }
        .form-control,.form-select { border-radius:6px; padding:.7rem 1rem; border-color:#dee2e6; font-size:.95rem; }
        .form-control:focus,.form-select:focus { border-color:var(--gov-blue); box-shadow:0 0 0 .25rem rgba(0,51,102,.15); }
        .form-label { font-weight:600; color:#495057; margin-bottom:.5rem; font-size:.9rem; }
        .btn-primary { background:var(--gov-blue); border-color:var(--gov-blue); padding:.7rem 1.5rem; font-weight:600; border-radius:6px; transition:all .2s; }
        .btn-primary:hover { background:#002244; border-color:#002244; transform:translateY(-1px); }
        .section-title { position:relative; padding-bottom:10px; margin-bottom:25px; }
        .section-title::after { content:''; position:absolute; left:0; bottom:0; width:60px; height:3px; background:var(--gov-gold); }
        /* ── Festive Greeting – Fitër Bajrami ── */
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
            max-width: 860px;
            margin: 0 auto 30px;
            background: linear-gradient(135deg, #0b1a35 0%, #16325c 50%, #0b1a35 100%);
            border: 1px solid rgba(212,175,55,.35);
            border-top: 3px solid #d4af37;
            border-radius: 18px;
            padding: 36px 44px 32px;
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
        /* ── Weekend Block Notice ── */
        .weekend-block-notice {
            background: linear-gradient(135deg, #0f1e10 0%, #1a3a1e 50%, #0f1e10 100%);
            border: 1px solid rgba(56,161,105,.35);
            border-top: 3px solid #38a169;
            border-radius: 16px;
            padding: 28px 36px 24px;
            margin-bottom: 24px;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 36px rgba(0,0,0,.28), 0 0 60px rgba(56,161,105,.05);
        }
        .weekend-block-notice::before {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(ellipse at 50% 0%, rgba(56,161,105,.12) 0%, transparent 65%);
            pointer-events: none;
        }
        .weekend-block-notice::after {
            content: '';
            position: absolute; top: 0; left: -70%;
            width: 40%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.04), transparent);
            animation: fg-shimmer 5.5s ease-in-out infinite;
            pointer-events: none;
        }
        .wbn-badge {
            display: inline-block;
            background: rgba(56,161,105,.12);
            border: 1px solid rgba(56,161,105,.35);
            color: #68d391;
            font-size: .7rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 3px 14px;
            border-radius: 20px;
            margin-bottom: 14px;
            position: relative; z-index: 1;
        }
        .wbn-icon {
            display: block;
            font-size: 2.4rem;
            color: #68d391;
            text-shadow: 0 0 20px rgba(56,161,105,.5);
            margin-bottom: 10px;
            position: relative; z-index: 1;
        }
        .wbn-title {
            font-family: 'Merriweather', serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: #68d391;
            margin: 0 0 8px;
            text-shadow: 0 0 18px rgba(56,161,105,.3);
            position: relative; z-index: 1;
        }
        .wbn-divider {
            width: 70px; height: 1px;
            background: linear-gradient(90deg, transparent, #38a169, transparent);
            margin: 0 auto 12px;
            position: relative; z-index: 1;
        }
        .wbn-text {
            color: rgba(255,255,255,.8);
            font-size: .9rem;
            line-height: 1.7;
            margin: 0;
            position: relative; z-index: 1;
        }
        .wbn-days {
            display: inline-flex;
            gap: 10px;
            margin-top: 12px;
            position: relative; z-index: 1;
        }
        .wbn-day {
            background: rgba(56,161,105,.15);
            border: 1px solid rgba(56,161,105,.3);
            color: #68d391;
            border-radius: 8px;
            padding: 4px 14px;
            font-size: .8rem;
            font-weight: 600;
        }
        .holiday-block-notice {
            background: linear-gradient(135deg, #0b1a35 0%, #16325c 50%, #0b1a35 100%);
            border: 1px solid rgba(212,175,55,.35);
            border-top: 3px solid #d4af37;
            border-radius: 16px;
            padding: 28px 36px 24px;
            margin-bottom: 24px;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 36px rgba(0,0,0,.28), 0 0 60px rgba(212,175,55,.05);
        }
        .holiday-block-notice::before {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(ellipse at 50% 0%, rgba(212,175,55,.12) 0%, transparent 65%);
            pointer-events: none;
        }
        .holiday-block-notice::after {
            content: '';
            position: absolute; top: 0; left: -70%;
            width: 40%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.04), transparent);
            animation: fg-shimmer 5.5s ease-in-out infinite;
            pointer-events: none;
        }
        .hbn-badge {
            display: inline-block;
            background: rgba(212,175,55,.12);
            border: 1px solid rgba(212,175,55,.3);
            color: #d4af37;
            font-size: .7rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 3px 14px;
            border-radius: 20px;
            margin-bottom: 14px;
            position: relative; z-index: 1;
        }
        .hbn-icon {
            display: block;
            font-size: 2.4rem;
            color: #d4af37;
            text-shadow: 0 0 20px rgba(212,175,55,.55);
            margin-bottom: 10px;
            position: relative; z-index: 1;
        }
        .hbn-title {
            font-family: 'Merriweather', serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: #d4af37;
            margin: 0 0 8px;
            text-shadow: 0 0 18px rgba(212,175,55,.3);
            position: relative; z-index: 1;
        }
        .hbn-divider {
            width: 70px; height: 1px;
            background: linear-gradient(90deg, transparent, #d4af37, transparent);
            margin: 0 auto 12px;
            position: relative; z-index: 1;
        }
        .hbn-text {
            color: rgba(255,255,255,.8);
            font-size: .9rem;
            line-height: 1.7;
            margin: 0;
            position: relative; z-index: 1;
        }
        /* inline holiday notice inside form-text area */
        .holiday-inline-notice {
            background: linear-gradient(135deg, #0b1a35 0%, #16325c 100%);
            border: 1px solid rgba(212,175,55,.4);
            border-left: 3px solid #d4af37;
            border-radius: 10px;
            padding: 12px 16px;
            margin-top: 8px;
            color: rgba(255,255,255,.85);
            font-size: .82rem;
            line-height: 1.55;
        }
        .holiday-inline-notice .hin-icon { color: #d4af37; margin-right: 6px; }
        .holiday-inline-notice strong { color: #d4af37; }
        /* inline weekend notice inside form-text area */
        .weekend-inline-notice {
            background: linear-gradient(135deg, #0f1e10 0%, #1a3a1e 100%);
            border: 1px solid rgba(56,161,105,.4);
            border-left: 3px solid #38a169;
            border-radius: 10px;
            padding: 12px 16px;
            margin-top: 8px;
            color: rgba(255,255,255,.85);
            font-size: .82rem;
            line-height: 1.55;
        }
        .weekend-inline-notice .win-icon { color: #68d391; margin-right: 6px; }
        .weekend-inline-notice strong { color: #68d391; }
        .alert { border-radius:6px; border:none; box-shadow:0 4px 6px rgba(0,0,0,0.05); }
        .office-card { background:#fff; border-radius:10px; padding:18px; box-shadow:0 2px 8px rgba(0,51,102,0.06); border-left:4px solid var(--gov-blue); height:100%; }
        .office-name { font-weight:700; color:var(--gov-blue); font-size:1rem; }
        .office-location { color:#888; font-size:.9rem; }
        .progress-steps { display:flex; justify-content:center; gap:32px; margin:18px 0; flex-wrap:wrap; }
        .step { display:flex; flex-direction:column; align-items:center; gap:5px; font-size:.85rem; color:#aaa; }
        .step i { width:36px; height:36px; display:flex; align-items:center; justify-content:center; border-radius:50%; background:#eee; font-size:1rem; }
        .step.active i { background:var(--gov-blue); color:#fff; }
        .step.completed i { background:#28a745; color:#fff; }
        #form-tinky-dropdown { border:2px solid #ff6600 !important; background:linear-gradient(to bottom right,#fffdfa,#fff); box-shadow:0 10px 30px rgba(255,102,0,0.1); }
        .tinky-label { color:#e65c00; font-weight:700; }
        .btn-tinky { background:linear-gradient(135deg,#ff6600 0%,#ff8533 100%); color:white; border:none; font-weight:bold; }
        .btn-tinky:hover { background:linear-gradient(135deg,#cc5200 0%,#e65c00 100%); color:white; }
        footer { background:linear-gradient(135deg,#1a1f2e 0%,#24292e 100%); color:#d1d5da; padding:40px 0 20px; margin-top:auto; text-align:center; position:relative; }
        .main-container { max-width:1200px; margin:30px auto; padding:0 20px; flex:1; }
        @media(max-width:768px){ header{text-align:center;} .progress-steps{gap:12px;} }
    </style>
</head>
<body>
    <!-- Logo shown only in header below -->

<!-- Header -->
<header>
    <div class="container d-flex flex-column flex-md-row align-items-center justify-content-between">
        <div class="mb-3 mb-md-0 text-center text-md-start d-flex align-items-center gap-3">
            <img src="images/pngwing.com%20(1).png" alt="Logo" style="height:50px;width:auto;">
            <a href="dashboard.php" class="text-white text-decoration-none">
                <span class="d-block navbar-brand"><i class="fas fa-balance-scale me-2"></i>e-Noteria</span>
                <span class="header-subtitle">Platformë SaaS për shërbimet noteriale në Kosovë</span>
            </a>
        </div>
        <div class="d-flex align-items-center gap-3">
            <form method="get" class="d-flex align-items-center bg-white rounded p-1" style="height:38px;">
                <i class="fas fa-globe text-secondary ms-2 me-1"></i>
                <select name="lang" onchange="this.form.submit()" class="form-select border-0 py-0 ps-1 pe-4" style="font-size:.9rem;width:auto;box-shadow:none;background:transparent;">
                    <option value="sq"<?php if($lang==='sq')echo' selected';?>>Shqip</option>
                    <option value="sr"<?php if($lang==='sr')echo' selected';?>>Српски</option>
                    <option value="en"<?php if($lang==='en')echo' selected';?>>English</option>
                </select>
            </form>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm"><i class="fas fa-home me-1"></i> Dashboard</a>
            <a href="logout.php" class="btn btn-danger btn-sm text-white"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
</header>

<div class="main-container">

    <!-- Hero -->
    <div class="text-center py-4">
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

        <?php if ($is_ramazan): ?>
            <div class="alert alert-warning mt-3 mb-4" style="max-width:600px;margin:auto;">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Orari i punës është i shkurtuar deri në ora 15:00 gjatë muajit të Ramazanit.</strong><br>
                <small>Kjo masë merret për të mbrojtur cilësinë e shërbimit dhe mirëqenien e punonjësve.</small>
            </div>
        <?php endif; ?>
        <i class="fas fa-calendar-check" style="font-size:4rem;color:var(--gov-blue);filter:drop-shadow(0 4px 8px rgba(0,51,102,0.3));"></i>
        <h1 class="mt-3"><?php echo htmlspecialchars($L['reserve_title']); ?></h1>
        <p class="text-muted"><?php echo htmlspecialchars($L['reserve_sub']); ?></p>
        <div class="progress-steps mt-3">
            <div class="step completed"><i class="fas fa-check"></i><span>Kyçja</span></div>
            <div class="step active"><i class="fas fa-calendar-alt"></i><span>Rezervimi</span></div>
            <div class="step"><i class="fas fa-credit-card"></i><span>Pagesa</span></div>
            <div class="step"><i class="fas fa-check-circle"></i><span>Përfundimi</span></div>
        </div>
    </div>

    <!-- ALERTS — mesazhi ruhet si tekst i pastër, htmlspecialchars bëhet këtu -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <strong>Sukses!</strong> <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php if ($reservation_id):
            try {
                $stmtStatus = $pdo->prepare("SELECT payment_status FROM reservations WHERE id = ?");
                $stmtStatus->execute([$reservation_id]);
                $rowStatus    = $stmtStatus->fetch();
                $pay_status   = $rowStatus['payment_status'] ?? '';
            } catch (PDOException $e) { $pay_status = ''; }
        ?>
        <div class="alert alert-info border-start border-info border-4" role="alert">
            <h5 class="alert-heading"><i class="fas fa-receipt me-2"></i>Statusi i Pagesës (Rezervimi #<?php echo (int)$reservation_id; ?>)</h5>
            <p class="mb-0 mt-2">
                <?php if ($pay_status === 'paid'): ?>
                    <span class="badge bg-success"><i class="fas fa-check me-1"></i> E PËRFUNDUAR</span> Pagesa është kryer me sukses.
                <?php elseif ($pay_status === 'pending'): ?>
                    <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> NË PRITJE</span> Pagesa është në procesim.
                <?php elseif ($pay_status === 'failed'): ?>
                    <span class="badge bg-danger"><i class="fas fa-times me-1"></i> DËSHTOI</span> Pagesa nuk u realizua.
                <?php else: ?>
                    <span class="badge bg-secondary">I PANJOHUR</span>
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($error): ?>
        <?php if (strpos($error, 'Fitër Bajrami') !== false || strpos($error, 'ditë zyrtare pushimi') !== false): ?>
        <div class="holiday-block-notice" role="alert">
            <span class="hbn-badge">&#10022; Ditë Zyrtare Pushimi &#10022;</span>
            <i class="fas fa-moon hbn-icon"></i>
            <h5 class="hbn-title">Fitër Bajrami &mdash; 20 Mars 2026</h5>
            <div class="hbn-divider"></div>
            <p class="hbn-text"><i class="fas fa-building me-1" style="color:#d4af37;"></i> Zyrat noteriale janë të mbyllura sot.<br>Ju lutemi zgjidhni një datë tjetër pune për rezervimin tuaj.</p>
        </div>
        <?php elseif (strpos($error, 'Shtun') !== false || strpos($error, 'Diel') !== false): ?>
        <div class="weekend-block-notice" role="alert">
            <span class="wbn-badge">&#9633; Fund-javë &#9633;</span>
            <i class="fas fa-umbrella-beach wbn-icon"></i>
            <h5 class="wbn-title">Zyrat Noteriale Nuk Punojnë në Fund-javë</h5>
            <div class="wbn-divider"></div>
            <p class="wbn-text">Data e zgjedhur është ditë pushimi &mdash; zyrat janë të mbyllura.<br>Rezervimet pranohen vetëm në ditët e punës.</p>
            <div class="wbn-days">
                <span class="wbn-day"><i class="fas fa-calendar-check me-1"></i>E Hënë</span>
                <span class="wbn-day">E Martë</span>
                <span class="wbn-day">E Mërkurë</span>
                <span class="wbn-day">E Enjtë</span>
                <span class="wbn-day">E Premte</span>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Kujdes!</strong> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Zyrat -->
    <div class="row mb-5">
        <div class="col-12">
            <h3 class="section-title text-primary mb-4"><i class="fas fa-building me-2"></i><?php echo htmlspecialchars($L['offices']); ?></h3>
            <div class="row g-3">
                <?php foreach ($zyrat as $zyra): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="office-card">
                        <div class="d-flex align-items-start gap-3">
                            <div style="width:50px;height:50px;background:linear-gradient(135deg,var(--gov-blue) 0%,#001122 100%);border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;flex-shrink:0;">
                                <i class="fas fa-landmark fa-lg"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="office-name mb-1"><?php echo htmlspecialchars($zyra['emri'] ?? ''); ?></h5>
                                <p class="office-location mb-2"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars(($zyra['qyteti'] ?? '') . ', ' . ($zyra['shteti'] ?? '')); ?></p>
                                <form action="uploads/upload_document.php" method="post" enctype="multipart/form-data" class="d-flex gap-2">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="zyra_id" value="<?php echo (int)$zyra['id']; ?>">
                                    <input type="file" name="document" required class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png">
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-cloud-upload-alt me-1"></i>Ngarko</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="row g-4" id="rezervo">

        <!-- Forma kryesore -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-calendar-check me-2"></i><?php echo htmlspecialchars($L['form_title']); ?></h4>
                    <span class="badge bg-secondary"><i class="fas fa-clock me-1"></i> Deri në <?php echo $max_time; ?></span>
                </div>
                <div class="card-body p-4">
                    <form method="POST" id="reservation-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="book_notary" value="1">

                        <div class="mb-4">
                            <label for="service" class="form-label"><?php echo htmlspecialchars($L['service']); ?> <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-file-contract"></i></span>
                                <select name="service" id="service" class="form-select" required>
                                    <option value="">-- Zgjidhni llojin e shërbimit --</option>
                                    <optgroup label="Kontrata">
                                        <option value="Kontratë Shitblerjeje">Shitblerje e pasurisë</option>
                                        <option value="Kontratë dhuratë">Kontratë dhuratë</option>
                                        <option value="Kontratë qiraje">Kontratë qiraje</option>
                                        <option value="Kontratë furnizimi">Kontratë furnizimi</option>
                                        <option value="Kontratë përkujdesjeje">Kontratë përkujdesjeje</option>
                                        <option value="Kontratë prenotimi">Kontratë prenotimi</option>
                                        <option value="Kontratë të tjera të lejuara me ligj">Kontrata të tjera</option>
                                    </optgroup>
                                    <optgroup label="Të tjera">
                                        <option value="Autorizim për vozitje të automjetit">Autorizim për vozitje</option>
                                        <option value="Legalizim">Legalizim dokumenti</option>
                                        <option value="Vertetim Dokumenti">Vërtetim dokumenti</option>
                                        <option value="Deklaratë">Deklaratë nën betim</option>
                                    </optgroup>
                                </select>
                            </div>
                            <div class="form-text">Zgjidhni shërbimin e saktë për të lehtësuar procesin noterial.</div>
                        </div>

                        <div class="mb-4">
                            <label for="zyra_id" class="form-label"><i class="fas fa-building me-1"></i>Zyra Noteriale <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-landmark"></i></span>
                                <select name="zyra_id" id="zyra_id" class="form-select" required>
                                    <option value="">-- Zgjidhni zyrën noteriale --</option>
                                    <?php foreach ($zyrat as $zyra): ?>
                                        <option value="<?php echo (int)$zyra['id']; ?>" <?php if(isset($_POST['zyra_id']) && $_POST['zyra_id'] == $zyra['id']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($zyra['emri'] . ' - ' . $zyra['qyteti']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="date" class="form-label"><?php echo htmlspecialchars($L['date']); ?> <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="date" name="date" id="date" class="form-control" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_POST['date'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="time" class="form-label"><?php echo htmlspecialchars($L['time']); ?> <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-clock"></i></span>
                                    <input type="time" name="time" id="time" class="form-control" required min="08:00" max="<?php echo $max_time; ?>" value="<?php echo htmlspecialchars($_POST['time'] ?? ''); ?>">
                                </div>
                                <div class="form-text <?php echo $is_ramazan ? 'text-danger' : ''; ?>">
                                    <?php if ($is_ramazan): ?>
                                        Gjatë Ramazanit: E Hënë–E Premte (08:00–15:00)
                                    <?php else: ?>
                                        Zyrat punojnë E Hënë–E Premte (08:00–16:00).
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Available Employees Section -->
                        <div class="mb-4">
                            <label class="form-label"><i class="fas fa-user-tie me-1"></i>Punonjësit e Lirë <span class="badge bg-info ms-2" id="employee-status-badge">Zgjidhni zyrën, datën dhe orën</span></label>
                            <div id="employees-container" class="alert alert-secondary py-3" style="display: none;">
                                <div id="loading-spinner" class="text-center" style="display: none;">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    <span>Duke ngarkuar punonjësit...</span>
                                </div>
                                <div id="no-employees-alert" class="alert alert-warning mb-0" style="display: none;">
                                    <i class="fas fa-info-circle me-2"></i>Nuk ka punonjës të lirë në këtë orë. Ju lutemi zgjidhni një orë tjetër.
                                </div>
                                <div id="employees-list" class="row g-2"></div>
                            </div>
                            <input type="hidden" name="punonjesi_id" id="punonjesi_id" value="">
                        </div>

                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" id="agree_terms" name="agree_terms" required>
                            <label class="form-check-label small" for="agree_terms">
                                Pajtohem me <a href="terms.php" target="_blank">Kushtet e Përdorimit</a> dhe <a href="privatesia.php" target="_blank">Politikën e Privatësisë</a>.
                            </label>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg shadow-sm" id="submit-btn">
                                <i class="fas fa-calendar-check me-2"></i><?php echo htmlspecialchars($L['submit']); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Panel anësor: Pagesa -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Pagesa Online</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-light border small text-muted mb-3">
                        <i class="fas fa-shield-alt me-1"></i> <strong>I Sigurt:</strong> Enkriptim bankar 256-bit SSL.
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold">Tarifa e Shërbimit:</span>
                            <span class="badge bg-primary" id="service-price-badge">€0.00</span>
                        </div>
                        <div class="small text-muted"><i class="fas fa-info-circle me-1"></i>Pagesa përfshin shërbimin noterial + 18% TVSH.</div>
                        <div class="small text-muted mt-2" id="price-breakdown" style="display:none;">
                            <div>Çmimi bazë: <span id="base-price">€0.00</span></div>
                            <div>TVSH (18%): <span id="tax-amount">€0.00</span></div>
                            <hr style="margin: 5px 0;">
                            <div><strong>Total: <span id="total-price">€0.00</span></strong></div>
                        </div>
                    </div>

                    <?php if ($reservation_id): ?>
                        <?php $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); ?>
                    <form method="POST" action="process_payment.php" id="form-bank-main">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="reservation_id" value="<?php echo (int)$reservation_id; ?>">
                        <input type="hidden" name="zyra_id" id="form_zyra_id" value="">
                        <div class="mb-3">
                            <label for="bank_select" class="form-label"><i class="fas fa-university me-1"></i>Përzgjidh Bankën</label>
                            <select name="emri_bankes" id="bank_select" class="form-select" required>
                                <option value="">-- Zgjidhni Bankën Tuaj --</option>
                                <option value="Banka Ekonomike">🏦 Banka Ekonomike</option>
                                <option value="Banka Kombëtare Tregtare">🏦 BKT</option>
                                <option value="Banka Credins">🏦 Credins Bank</option>
                                <option value="ProCredit Bank">🏦 ProCredit Bank</option>
                                <option value="Raiffeisen Bank">🏦 Raiffeisen Bank</option>
                                <option value="NLB Banka">🏦 NLB Banka</option>
                                <option value="TEB Banka">🏦 TEB Banka</option>
                                <option value="Paysera">💳 Paysera</option>
                                <option value="Tinky">⚡ Tinky (Pagesë e shpejtë)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="payer_name" class="form-label">Emri dhe Mbiemri</label>
                            <input type="text" class="form-control" id="payer_name" name="payer_name" placeholder="Shkruani emrin e plotë" required value="<?php echo htmlspecialchars(($_SESSION['user_name'] ?? '')); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="payer_iban" class="form-label">IBAN</label>
                            <input type="text" class="form-control text-uppercase" id="payer_iban" name="payer_iban" placeholder="XK051212012345678901" maxlength="20" required pattern="^XK\d{18}$">
                            <div class="form-text">Format: <b>XK + 18 shifra</b> (p.sh. XK051212012345678901)</div>
                        </div>
                        <div class="mb-3">
                            <label for="amount" class="form-label">Shuma me TVSH (€)</label>
                            <input type="number" class="form-control" id="amount" name="amount" min="10" step="0.01" value="0.00" readonly>
                            <div class="form-text">Shuma dinamike bazuar në shërbimin e zgjedhur</div>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Përshkrimi</label>
                            <textarea class="form-control" id="description" name="description" rows="2" placeholder="p.sh. Shërbim noterial - Rezervim" required minlength="5"></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg" id="btn-standard-pay">
                                <i class="fas fa-credit-card me-2"></i>Vazhdo me Pagesën
                            </button>
                        </div>
                    </form>

                    <!-- Forma Tinky -->
                    <div id="form-tinky-dropdown" class="mt-3 p-3 rounded" style="display:none;">
                        <h6 class="mb-3" style="color:var(--gov-blue);font-weight:700;"><i class="fas fa-bolt me-1"></i> Pagesë e Shpejtë me Tinky</h6>
                        <input type="hidden" name="payment_method" value="tinky">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="reservation_id" value="<?php echo (int)$reservation_id; ?>">
                        <div class="mb-2">
                            <label class="form-label small mb-1 tinky-label">Emri i plotë <span class="text-danger">*</span></label>
                            <input type="text" name="tinky_payer_name" class="form-control form-control-sm border-warning" placeholder="Emri dhe Mbiemri">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-1 tinky-label">IBAN <span class="text-danger">*</span></label>
                            <input type="text" name="tinky_payer_iban" class="form-control form-control-sm border-warning" placeholder="XK051212012345678901" maxlength="20">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small mb-1 tinky-label">Shuma me TVSH (€) <span class="text-danger">*</span></label>
                            <input type="number" name="tinky_amount" class="form-control form-control-sm border-warning" id="tinky_amount" min="10" step="0.01" placeholder="0.00" readonly>
                        </div>
                        <button type="button" id="tinky-submit" class="btn btn-tinky w-100 btn-sm shadow-sm">
                            <i class="fas fa-paper-plane me-1"></i> PAGUAJ TANI
                        </button>
                    </div>
                    <?php else: ?>
                        <div class="alert alert-secondary text-center small">
                            <i class="fas fa-info-circle me-1"></i> Pasi të rezervoni terminin, mund të kryeni pagesën këtu.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Kontakti -->
            <div class="card mt-3 border-secondary">
                <div class="card-body">
                    <h6><i class="fas fa-info-circle me-2"></i>Mbështetje Teknike</h6>
                    <p class="small text-muted mb-0">Hapni një tiketë nëse keni probleme gjatë rezervimit.</p>
                    <hr class="my-2">
                    <small class="d-block"><i class="fas fa-envelope me-2"></i>support@e-noteria.com</small>
                    <small class="d-block"><i class="fas fa-phone me-2"></i>+383 38 200 100</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Paneli i Administratorit -->
    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
    <div class="row mt-5">
        <div class="col-12">
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0 text-white"><i class="fas fa-user-shield me-2"></i>Paneli i Administratorit - Terminet</h4>
                </div>
                <div class="card-body">
                    <?php
                    $stmtAdmin = $pdo->query("SELECT r.service, r.date, r.time, u.emri, u.mbiemri, u.email, r.document_path
                        FROM reservations r JOIN users u ON r.user_id = u.id
                        ORDER BY r.date DESC, r.time DESC LIMIT 20");
                    if ($stmtAdmin->rowCount() > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped align-middle">
                                <thead class="table-dark">
                                    <tr><th>Shërbimi</th><th>Data</th><th>Ora</th><th>Përdoruesi</th><th>Dokumenti</th></tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $stmtAdmin->fetch()): ?>
                                    <tr>
                                        <td class="fw-bold text-primary"><?php echo htmlspecialchars($row['service']); ?></td>
                                        <td><?php echo date('d.m.Y', strtotime($row['date'])); ?></td>
                                        <td><span class="badge bg-light text-dark border"><i class="far fa-clock"></i> <?php echo htmlspecialchars($row['time']); ?></span></td>
                                        <td>
                                            <div><?php echo htmlspecialchars($row['emri'] . ' ' . $row['mbiemri']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($row['email']); ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['document_path'])): ?>
                                                <a href="<?php echo htmlspecialchars($row['document_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-download"></i> Shiko</a>
                                            <?php else: ?><span class="text-muted small">Pa dokument</span><?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">Nuk ka asnjë termin të rezervuar.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /.main-container -->

<!-- Footer -->
<footer>
    <div style="position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,#cfa856,transparent);"></div>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 mb-4">
                <span style="font-weight:800;font-size:1.5rem;display:block;background:linear-gradient(135deg,#fff,#cfa856);background-clip:text;-webkit-background-clip:text;-webkit-text-fill-color:transparent;">e-Noteria</span>
                <p style="color:#8b949e;margin:0;font-size:.9rem;">Platformë SaaS për zyrat noteriale në Kosovë</p>
            </div>
        </div>
        <div style="border-top:1px solid #30363d;padding-top:20px;margin-top:20px;">
            <div style="margin-bottom:15px;">
                <a href="terms.php" style="color:#8b949e;text-decoration:none;margin:0 15px;">Kushtet e Përdorimit</a>
                <a href="Privacy_policy.php" style="color:#8b949e;text-decoration:none;margin:0 15px;">Politika e Privatësisë</a>
                <a href="ndihma.php" style="color:#8b949e;text-decoration:none;margin:0 15px;">Ndihma</a>
            </div>
            <p style="margin:0;font-size:.8em;color:#8b949e;">&copy; <?php echo date('Y'); ?> e-Noteria | Republika e Kosovës</p>
        </div>
    </div>
</footer>

<script src="/assets/bootstrap/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // Submit button feedback
    // Service pricing data
    const servicePrices = <?php echo $service_prices_json; ?>;
    const TAX_RATE = 0.18; // 18% TVSH

    // Function to calculate and update prices
    function updatePriceDisplay() {
        const serviceSelect = document.getElementById('service');
        const selectedService = serviceSelect ? serviceSelect.value : '';
        
        // Safe element references with null checks
        const priceBadge = document.getElementById('service-price-badge');
        const amountField = document.getElementById('amount');
        const tinkyAmountField = document.getElementById('tinky_amount');
        const priceBreakdown = document.getElementById('price-breakdown');
        const basePrice_elem = document.getElementById('base-price');
        const taxAmount_elem = document.getElementById('tax-amount');
        const totalPrice_elem = document.getElementById('total-price');
        
        if (!selectedService || !servicePrices[selectedService]) {
            // Reset to zero if no service selected
            if (priceBadge) priceBadge.textContent = '€0.00';
            if (amountField) amountField.value = '0.00';
            if (tinkyAmountField) tinkyAmountField.value = '0.00';
            if (priceBreakdown) priceBreakdown.style.display = 'none';
            return;
        }
        
        const basePrice = servicePrices[selectedService];
        const taxAmount = basePrice * TAX_RATE;
        const totalPrice = basePrice + taxAmount;
        
        // Update badge and form fields with null checks
        if (priceBadge) priceBadge.textContent = '€' + totalPrice.toFixed(2);
        if (amountField) amountField.value = totalPrice.toFixed(2);
        if (tinkyAmountField) tinkyAmountField.value = totalPrice.toFixed(2);
        
        // Update price breakdown with null checks
        if (basePrice_elem) basePrice_elem.textContent = '€' + basePrice.toFixed(2);
        if (taxAmount_elem) taxAmount_elem.textContent = '€' + taxAmount.toFixed(2);
        if (totalPrice_elem) totalPrice_elem.textContent = '€' + totalPrice.toFixed(2);
        if (priceBreakdown) priceBreakdown.style.display = 'block';
    }

    // Listen for service selection changes
    const serviceSelect = document.getElementById('service');
    if (serviceSelect) {
        serviceSelect.addEventListener('change', updatePriceDisplay);
        // Initial calculation on page load
        updatePriceDisplay();
    }

    const form = document.getElementById('reservation-form');
    const submitBtn = document.getElementById('submit-btn');
    if (form && submitBtn) {
        form.addEventListener('submit', function () {
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Po procesohet...';
            submitBtn.disabled = true;
            setTimeout(() => {
                submitBtn.innerHTML = '<i class="fas fa-calendar-check me-2"></i><?php echo htmlspecialchars($L['submit']); ?>';
                submitBtn.disabled = false;
            }, 10000);
        });
    }

    // Sync zyra_id from main form to payment form
    const zyraSelect = document.getElementById('zyra_id');
    const formZyraInput = document.getElementById('form_zyra_id');
    if (zyraSelect && formZyraInput) {
        zyraSelect.addEventListener('change', function () {
            formZyraInput.value = this.value;
        });
        // Also set initial value if zyra is selected
        if (zyraSelect.value) {
            formZyraInput.value = zyraSelect.value;
        }
    }

    // Tinky toggle
    var bankSelect = document.getElementById('emri_bankes');
    var tinkyForm  = document.getElementById('form-tinky-dropdown');
    var mainBtn    = document.getElementById('btn-standard-pay');

    if (bankSelect && tinkyForm) {
        bankSelect.addEventListener('change', function () {
            if (this.value === 'Tinky') {
                tinkyForm.style.display = 'block';
                if (mainBtn) mainBtn.style.display = 'none';
                setTimeout(() => {
                    var first = tinkyForm.querySelector('input[name="tinky_payer_name"]');
                    if (first) first.focus();
                }, 100);
            } else {
                tinkyForm.style.display = 'none';
                if (mainBtn) mainBtn.style.display = 'block';
            }
        });
    }

    // Tinky submit
    function showTinkyMessage(type, text) {
        var existing = document.getElementById('tinky-message');
        if (existing) existing.remove();
        var div = document.createElement('div');
        div.id = 'tinky-message';
        div.className = type === 'success' ? 'alert alert-success mt-2 small' : 'alert alert-danger mt-2 small';
        div.innerHTML = (type === 'success' ? '<i class="fas fa-check-circle"></i> ' : '<i class="fas fa-times-circle"></i> ') + text;
        if (tinkyForm) tinkyForm.parentNode.insertBefore(div, tinkyForm);
    }

    var tinkySubmit = document.getElementById('tinky-submit');
    if (tinkySubmit && tinkyForm) {
        tinkySubmit.addEventListener('click', function () {
            var name   = tinkyForm.querySelector('input[name="tinky_payer_name"]');
            var iban   = tinkyForm.querySelector('input[name="tinky_payer_iban"]');
            var amount = tinkyForm.querySelector('input[name="tinky_amount"]');
            var csrf   = tinkyForm.querySelector('input[name="csrf_token"]');
            var resId  = tinkyForm.querySelector('input[name="reservation_id"]');

            if (!name.value.trim() || !iban.value.trim() || !amount.value) {
                showTinkyMessage('error', 'Ju lutemi plotësoni të gjitha fushat e Tinky.');
                return;
            }

            var orig = tinkySubmit.innerHTML;
            tinkySubmit.disabled = true;
            tinkySubmit.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Duke procesuar...';

            var fd = new FormData();
            fd.append('payment_method', 'tinky');
            fd.append('csrf_token',     csrf   ? csrf.value   : '');
            fd.append('reservation_id', resId  ? resId.value  : '');
            fd.append('payer_name',     name.value.trim());
            fd.append('payer_iban',     iban.value.trim());
            fd.append('amount',         amount.value);

            fetch('tinky_payment.php', { method:'POST', body:fd })
                .then(r => r.json().catch(() => ({ success:false, message:'Përgjigje e pavlefshme nga serveri.' })))
                .then(data => {
                    tinkySubmit.disabled = false;
                    tinkySubmit.innerHTML = orig;
                    if (data.success) {
                        showTinkyMessage('success', data.message || 'Pagesa u krye me sukses!');
                        if (data.redirect) setTimeout(() => { window.location.href = data.redirect; }, 1500);
                    } else {
                        showTinkyMessage('error', data.message || 'Pagesa dështoi.');
                    }
                })
                .catch(() => {
                    tinkySubmit.disabled = false;
                    tinkySubmit.innerHTML = orig;
                    showTinkyMessage('error', 'Gabim në komunikim me serverin.');
                });
        });
    }

    // Update hours display based on selected date
    var dateInput = document.getElementById('date');
    var timeInput = document.getElementById('time');
    // zyraSelect is already declared above with const
    
    function updateHoursDisplay() {
        if (!dateInput) return;
        
        var selectedDate = dateInput.value; // Format: YYYY-MM-DD
        var ramazanEndDate = '2026-03-18';
        var isRamadan = selectedDate && selectedDate <= ramazanEndDate;

        // Blloko ditët zyrtare të pushimit
        var officialHolidays = ['2026-03-20'];
        if (selectedDate && officialHolidays.includes(selectedDate)) {
            if (timeInput) { timeInput.value = ''; timeInput.disabled = true; }
            var timeInputCol = timeInput ? timeInput.closest('.col-md-6') : null;
            var hoursDisplay = timeInputCol ? timeInputCol.querySelector('.form-text') : null;
            if (hoursDisplay) {
                hoursDisplay.className = 'form-text holiday-inline-notice';
                hoursDisplay.innerHTML =
                    '<span class="hin-icon"><i class="fas fa-moon"></i></span>' +
                    '<strong>Ditë Zyrtare Pushimi &mdash; Fitër Bajrami</strong><br>' +
                    'Zyrat noteriale janë të mbyllura më 20 Mars 2026.<br>' +
                    '<span style="opacity:.75">Ju lutemi zgjidhni një datë tjetër pune.</span>';
            }
            var container = document.getElementById('employees-container');
            if (container) container.style.display = 'none';
            return;
        }

        // Blloko fund-javën (e shtunë = 6, e diel = 0)
        if (selectedDate) {
            var parts = selectedDate.split('-');
            var selDateObj = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
            var dayOfWeek = selDateObj.getDay(); // 0=diel, 6=e shtunë
            if (dayOfWeek === 0 || dayOfWeek === 6) {
                if (timeInput) { timeInput.value = ''; timeInput.disabled = true; }
                var timeInputCol2 = timeInput ? timeInput.closest('.col-md-6') : null;
                var hoursDisplay2 = timeInputCol2 ? timeInputCol2.querySelector('.form-text') : null;
                var dayName = dayOfWeek === 6 ? 'e Shtunë' : 'e Diel';
                if (hoursDisplay2) {
                    hoursDisplay2.className = 'form-text weekend-inline-notice';
                    hoursDisplay2.innerHTML =
                        '<span class="win-icon"><i class="fas fa-umbrella-beach"></i></span>' +
                        '<strong>Fund-javë &mdash; ' + dayName + '</strong><br>' +
                        'Zyrat noteriale janë të mbyllura në fund-javë.<br>' +
                        '<span style="opacity:.75">Rezervimet pranohen E Hënë &ndash; E Premte.</span>';
                }
                var container2 = document.getElementById('employees-container');
                if (container2) container2.style.display = 'none';
                return;
            }
        }

        // Reset form-text style when switching away from blocked date
        var timeInputColReset = timeInput ? timeInput.closest('.col-md-6') : null;
        var hoursDisplayReset = timeInputColReset ? timeInputColReset.querySelector('.form-text') : null;
        if (hoursDisplayReset && (hoursDisplayReset.classList.contains('holiday-inline-notice') || hoursDisplayReset.classList.contains('weekend-inline-notice'))) {
            hoursDisplayReset.className = 'form-text';
        }

        // Ribëj normale nëse ndryshon data
        if (timeInput) timeInput.disabled = false;
        
        // Update max time attribute
        if (timeInput) {
            timeInput.max = isRamadan ? '15:00' : '16:00';
        }
        
        // Find the form-text element that's near the time input
        var timeInputCol = timeInput ? timeInput.closest('.col-md-6') : null;
        var hoursDisplay = timeInputCol ? timeInputCol.querySelector('.form-text') : null;
        
        // Update the displayed message
        if (hoursDisplay) {
            if (isRamadan) {
                hoursDisplay.classList.add('text-danger');
                hoursDisplay.innerHTML = 'Gjatë Ramazanit: E Hënë–E Premte (08:00–15:00)';
            } else {
                hoursDisplay.classList.remove('text-danger');
                hoursDisplay.innerHTML = 'Zyrat punojnë E Hënë–E Premte (08:00–16:00).';
            }
        }
        
        // Load available employees
        loadAvailableEmployees();
    }
    
    function loadAvailableEmployees() {
        var zyraId = zyraSelect ? zyraSelect.value : '';
        var date = dateInput ? dateInput.value : '';
        var time = timeInput ? timeInput.value : '';
        
        var container = document.getElementById('employees-container');
        var loadingSpinner = document.getElementById('loading-spinner');
        var noEmployeesAlert = document.getElementById('no-employees-alert');
        var employeesList = document.getElementById('employees-list');
        var statusBadge = document.getElementById('employee-status-badge');
        
        // Hide container if required fields are empty
        if (!zyraId || !date || !time) {
            if (container) container.style.display = 'none';
            if (statusBadge) statusBadge.textContent = 'Zgjidhni zyrën, datën dhe orën';
            return;
        }
        
        // Show container and loading spinner
        if (container) container.style.display = 'block';
        if (loadingSpinner) loadingSpinner.style.display = 'block';
        if (noEmployeesAlert) noEmployeesAlert.style.display = 'none';
        if (employeesList) employeesList.innerHTML = '';
        
        // Fetch available employees
        fetch('api/get_available_employees.php?zyra_id=' + encodeURIComponent(zyraId) + '&date=' + encodeURIComponent(date) + '&time=' + encodeURIComponent(time))
            .then(response => response.json())
            .then(data => {
                loadingSpinner.style.display = 'none';
                
                if (data.success && data.employees && data.employees.length > 0) {
                    noEmployeesAlert.style.display = 'none';
                    statusBadge.textContent = data.count + ' punonjës i lirë';
                    
                    // Display employee cards
                    data.employees.forEach(function(employee) {
                        var card = document.createElement('div');
                        card.className = 'col-12 col-sm-6';
                        card.innerHTML = `
                            <div class="card employee-card p-3 cursor-pointer" style="cursor: pointer; transition: all 0.3s;" 
                                 data-employee-id="${employee.id}" 
                                 data-employee-name="${employee.emri} ${employee.mbiemri}">
                                <div class="d-flex align-items-center">
                                    <div class="w-100">
                                        <h6 class="mb-1 fw-bold text-primary">
                                            <i class="fas fa-user-check me-2"></i>${employee.emri} ${employee.mbiemri}
                                        </h6>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-briefcase"></i> ${employee.pozita}
                                        </small>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-envelope"></i> ${employee.email}
                                        </small>
                                        ${employee.telefoni ? `<small class="text-muted d-block"><i class="fas fa-phone"></i> ${employee.telefoni}</small>` : ''}
                                    </div>
                                    <div class="ms-2">
                                        <i class="fas fa-check-circle text-success fa-lg" style="display: none;" class="employee-check"></i>
                                    </div>
                                </div>
                            </div>
                        `;
                        employeesList.appendChild(card);
                        
                        // Add click handler
                        card.querySelector('.employee-card').addEventListener('click', function() {
                            selectEmployee(employee.id, employee.emri + ' ' + employee.mbiemri, this);
                        });
                    });
                } else {
                    noEmployeesAlert.style.display = 'block';
                    statusBadge.textContent = 'Nuk ka punonjës të lirë';
                    employeesList.innerHTML = '';
                }
            })
            .catch(error => {
                loadingSpinner.style.display = 'none';
                console.error('Error fetching employees:', error);
                noEmployeesAlert.textContent = '⚠️ Gabim në ngarkimin e punonjësve. Provoni më vonë.';
                noEmployeesAlert.style.display = 'block';
            });
    }
    
    function selectEmployee(employeeId, employeeName, cardElement) {
        var input = document.getElementById('punonjesi_id');
        var allCards = document.querySelectorAll('.employee-card');
        
        // Remove selection from all cards
        allCards.forEach(function(card) {
            card.classList.remove('border-success', 'border-2');
            card.style.backgroundColor = '';
            var checkIcon = card.querySelector('.employee-check');
            if (checkIcon) checkIcon.style.display = 'none';
        });
        
        // Mark the selected card
        cardElement.classList.add('border-success', 'border-2');
        cardElement.style.backgroundColor = '#f0f8f0';
        var checkIcon = cardElement.querySelector('.employee-check');
        if (checkIcon) checkIcon.style.display = 'block';
        
        // Save to hidden input
        if (input) input.value = employeeId;
    }
    
    if (dateInput) {
        dateInput.addEventListener('change', updateHoursDisplay);
        dateInput.addEventListener('input', updateHoursDisplay);
    }
    
    if (timeInput) {
        timeInput.addEventListener('change', loadAvailableEmployees);
        timeInput.addEventListener('input', loadAvailableEmployees);
    }
    
    if (zyraSelect) {
        zyraSelect.addEventListener('change', loadAvailableEmployees);
    }
    
    // Load employees on page load if all fields are set
    loadAvailableEmployees();
}); // <-- ADD THIS LINE - closes the DOMContentLoaded event listener
</script>
</body>
</html>

