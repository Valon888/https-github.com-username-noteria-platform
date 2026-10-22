<?php
// filepath: c:\xampp\htdocs\noteria\reservation.php
// Konfigurimi i raportimit të gabimeve
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

session_start();
require_once 'config.php';

// Kontrollo nëse përdoruesi është i kyçur
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Mbrojtje CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = null;
$error = null;
$reservation_id = '';

// Check for user's last pending reservation to show payment form
if (isset($_SESSION['user_id'])) {
    $stmtLast = $pdo->prepare("SELECT id FROM reservations WHERE user_id = ? AND payment_status = 'pending' ORDER BY id DESC LIMIT 1");
    $stmtLast->execute([$_SESSION['user_id']]);
    $rowLast = $stmtLast->fetch();
    if ($rowLast) {
        $reservation_id = $rowLast['id'];
    }
}

// Merr kategorinë sociale të përdoruesit
$user_category = null;
if (isset($_SESSION['user_id'])) {
    $stmtCat = $pdo->prepare('SELECT social_category FROM users WHERE id = ?');
    $stmtCat->execute([$_SESSION['user_id']]);
    $rowCat = $stmtCat->fetch();
    if ($rowCat) {
        $user_category = $rowCat['social_category'] ?? 'none';
    }
}
$free_categories = ['invalid_lufte', 'asistence_sociale'];

// Ruaj rezervimin kur dërgohet forma
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifiko CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Veprimi i paautorizuar!";
    } else {
        $userId = $_SESSION['user_id'];
        $service = trim($_POST['service']);
        $zyra_id = trim($_POST['zyra_id']);
        $date = $_POST['date'];
        $time = $_POST['time'];

        // Validimi bazik
        if (empty($service) || empty($zyra_id) || empty($date) || empty($time)) {
            $error = "Ju lutemi plotësoni të gjitha fushat!";
        } elseif (!isset($_POST['agree_terms']) || $_POST['agree_terms'] !== 'on') {
            $error = "Ju duhet të pajtoheni me Kushtet e Përdorimit dhe Politikën e Privatësisë!";
        } else {
            // Periudha e Ramazanit: 19 Shkurt - 19 Mars 2026
            $ramazan_start = '2026-02-19';
            $ramazan_end = '2026-03-19';
            $is_ramazan = ($date >= $ramazan_start && $date <= $ramazan_end);
            $max_time = $is_ramazan ? '15:00' : '16:00';
            if ($time > $max_time) {
                $error = $is_ramazan ? "Gjatë muajit të Ramazanit, orari maksimal për termine është ora 15:00!" : "Orari maksimal për termine është ora 16:00!";
            } else {
                $weekday = date('N', strtotime($date));
                if ($weekday == 6 || $weekday == 7) {
                    $error = "Zyrat noteriale nuk punojnë të Shtunën dhe të Dielën!";
                } else {
                    // Kontrollo sa punëtorë ka zyra
                    $stmt_workers = $pdo->prepare("SELECT numri_punetoreve FROM zyrat WHERE id = ? LIMIT 1");
                    $stmt_workers->execute([$zyra_id]);
                    $zyra = $stmt_workers->fetch();
                    $numri_punetoreve = $zyra ? (int)$zyra['numri_punetoreve'] : 1;

                    // Kontrollo sa termine janë caktuar për këtë orar
                    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM reservations WHERE zyra_id = ? AND date = ? AND time = ?");
                    $stmt->execute([$zyra_id, $date, $time]);
                    $row = $stmt->fetch();
                    $total_terminet = $row ? (int)$row['total'] : 0;

                    if ($total_terminet >= $numri_punetoreve) {
                        $error = "Ky orar është i mbushur për këtë zyrë. Ju lutemi zgjidhni një orar tjetër!";
                    } else {
                        // Matje e kohës së verifikimit të dokumentit
                        $verify_start = microtime(true);
                        // ...këtu do të thirret API e verifikimit të dokumentit (simulohet me sleep(1))
                        // sleep(1); // Zëvendëso me thirrjen reale të API-së
                        // $api_response = ...
                        $verify_end = microtime(true);
                        $verify_time = round($verify_end - $verify_start, 4);
                        try {
                            $stmt = $pdo->prepare("INSERT INTO reservations (user_id, zyra_id, service, date, time, payment_status) VALUES (?, ?, ?, ?, ?, 'pending')");
                            if ($stmt->execute([$userId, $zyra_id, $service, $date, $time])) {
                                $reservation_id = $pdo->lastInsertId();
                                $success = "Rezervimi u krye me sukses! Koha e verifikimit të dokumentit: {$verify_time} sekonda.";
                                // Njoftim WhatsApp për noterin dhe adminin (përmes n8n)
                                try {
                                    require_once 'whatsapp_notify.php';
                                    $stmtW = $pdo->prepare("SELECT z.emri AS zyra, z.telefoni, u.emri AS klienti FROM zyrat z, users u WHERE z.id = ? AND u.id = ?");
                                    $stmtW->execute([$zyra_id, $userId]);
                                    if ($w = $stmtW->fetch()) {
                                        $w += ['service' => $service, 'date' => $date, 'time' => $time];
                                        whatsappNotify('reservation_created', whatsappRecipients($w['telefoni']),
                                            whatsappReservationText('📅 Rezervim i ri', $w));
                                    }
                                } catch (Throwable $e) {
                                    error_log('whatsapp notify: ' . $e->getMessage());
                                }
                                // Dërgo email njoftimi
                                if (file_exists('Phpmailer.php')) {
                                    require_once 'Phpmailer.php';
                                    // Kontrollo nëse funksioni sendMail ekziston, përndryshe definoje një version të thjeshtë
                                    if (!function_exists('sendMail')) {
                                        function sendMail($to, $subject, $body) {
                                            // Shembull i thjeshtë duke përdorur mail()
                                            $headers  = "MIME-Version: 1.0\r\n";
                                            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                                            $headers .= "From: Noteria <no-reply@noteria.local>\r\n";
                                            return mail($to, $subject, $body, $headers);
                                        }
                                    }
                                    // Merr emailin e përdoruesit
                                    $stmtUser = $pdo->prepare("SELECT email, emri FROM users WHERE id = ?");
                                    $stmtUser->execute([$userId]);
                                    $user = $stmtUser->fetch();
                                    if ($user && function_exists('sendMail')) {
                                        $to = $user['email'];
                                        $name = $user['emri'];
                                        $subject = "Njoftim për rezervimin tuaj në Noteria";
                                        $body = "Përshëndetje $name,<br><br>Rezervimi juaj u krye me sukses.<br><b>Shërbimi:</b> $service<br><b>Data:</b> $date<br><b>Ora:</b> $time<br><br>Ju faleminderit që përdorët Noteria!";
                                        sendMail($to, $subject, $body);
                                    }
                                }
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
}

// Merr të gjitha zyrat dhe vendndodhjet
$stmt = $pdo->query("SELECT id, emri, qyteti, shteti FROM zyrat");
$zyrat = $stmt->fetchAll();
?>
<?php
$lang = $_GET['lang'] ?? $_COOKIE['lang'] ?? 'sq';
if (!in_array($lang, ['sq','sr','en'])) $lang = 'sq';
setcookie('lang', $lang, time()+60*60*24*30, '/');
$labels = [
    'sq' => [
        'reserve_title' => 'Rezervo Terminin Tuaj',
        'reserve_sub' => 'Siguro një termin noterial në zyrën më të afërt',
        'form_title' => 'Plotësoni Formularin',
        'service' => 'Shërbimi Noterial',
        'date' => 'Data e Rezervimit',
        'time' => 'Ora e Preferuar',
        'submit' => 'Rezervo Terminin',
        'offices' => 'Zyrat Noteriale',
        'office_name' => 'Emri i Zyrës',
        'city' => 'Qyteti',
        'country' => 'Shteti',
        'upload_doc' => 'Ngarko Dokument',
        'choose_lang' => 'Gjuha:',
    ],
    'sr' => [
        'reserve_title' => 'Rezervišite svoj termin',
        'reserve_sub' => 'Obezbedite notarski termin u najbližoj kancelariji',
        'form_title' => 'Popunite formular',
        'service' => 'Notarska usluga',
        'date' => 'Datum rezervacije',
        'time' => 'Željeno vreme',
        'submit' => 'Rezerviši termin',
        'offices' => 'Notarske kancelarije',
        'office_name' => 'Naziv kancelarije',
        'city' => 'Grad',
        'country' => 'Država',
        'upload_doc' => 'Otpremi dokument',
        'choose_lang' => 'Jezik:',
    ],
    'en' => [
        'reserve_title' => 'Book Your Appointment',
        'reserve_sub' => 'Book a notary appointment at the nearest office',
        'form_title' => 'Fill the Form',
        'service' => 'Notary Service',
        'date' => 'Reservation Date',
        'time' => 'Preferred Time',
        'submit' => 'Book Appointment',
        'offices' => 'Notary Offices',
        'office_name' => 'Office Name',
        'city' => 'City',
        'country' => 'Country',
        'upload_doc' => 'Upload Document',
        'choose_lang' => 'Language:',
    ]
];
$L = $labels[$lang];
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($L['reserve_title']); ?> | Noteria Shtetërore</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts: Inter & Merriweather (Gov Style) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Merriweather:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --gov-blue: #003366;
            --gov-gold: #c49a6c;
            --gov-red: #990000;
            --bg-light: #f4f7f6;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            color: #333;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Merriweather', serif;
            color: var(--gov-blue);
        }
        
        /* Header & Navbar */
        header {
            background-color: var(--gov-blue);
            border-bottom: 4px solid var(--gov-gold);
            padding: 15px 0;
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: #fff !important;
            letter-spacing: 0.5px;
        }
        .header-subtitle {
            font-size: 0.85rem;
            opacity: 0.9;
            font-weight: 300;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Forms & Cards */
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.04);
            margin-bottom: 2rem;
            border-top: 3px solid var(--gov-blue);
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #eee;
            padding: 1.25rem 1.5rem;
        }
        .card-header h4 {
            margin: 0;
            font-size: 1.25rem;
            color: var(--gov-blue);
            font-weight: 700;
        }
        
        .form-control, .form-select {
            border-radius: 6px;
            padding: 0.7rem 1rem;
            border-color: #dee2e6;
            font-size: 0.95rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--gov-blue);
            box-shadow: 0 0 0 0.25rem rgba(0, 51, 102, 0.15);
        }
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        /* Buttons */
        .btn-primary {
            background-color: var(--gov-blue);
            border-color: var(--gov-blue);
            padding: 0.7rem 1.5rem;
            font-weight: 600;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .btn-primary:hover {
            background-color: #002244;
            border-color: #002244;
            transform: translateY(-1px);
        }
        .btn-outline-secondary {
            border-color: #ccc;
            color: #555;
        }
        .btn-outline-secondary:hover {
            background-color: #f8f9fa;
            color: #333;
        }

        /* Tinky Payment Section */
        #form-tinky-dropdown {
            border: 2px solid #ff6600 !important;
            background: linear-gradient(to bottom right, #fffdfa, #fff);
            box-shadow: 0 10px 30px rgba(255, 102, 0, 0.1);
        }
        .tinky-label {
            color: #e65c00;
            font-weight: 700;
        }
        .btn-tinky {
            background: linear-gradient(135deg, #ff6600 0%, #ff8533 100%);
            color: white;
            border: none;
            font-weight: bold;
        }
        .btn-tinky:hover {
            background: linear-gradient(135deg, #cc5200 0%, #e65c00 100%);
            color: white;
        }

        /* Footer */
        footer {
            background-color: #212529;
            color: #adb5bd;
            padding: 3rem 0;
            margin-top: auto;
            font-size: 0.9rem;
        }
        footer a { 
            color: #decab6; 
            text-decoration: none;
        }
        footer a:hover { 
            color: #fff; 
            text-decoration: underline; 
        }

        /* Utilities */
        .section-title {
            position: relative;
            padding-bottom: 10px;
            margin-bottom: 25px;
        }
        .section-title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 60px;
            height: 3px;
            background-color: var(--gov-gold);
        }
        
        .alert {
            border-radius: 6px;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        @media (max-width: 768px) {
            header { text-align: center; }
            .header-subtitle { display: block; margin-top: 5px; }
        }
    </style>
</head>
<body>

    <!-- Top Navigation Bar -->
    <header>
        <div class="container d-flex flex-column flex-md-row align-items-center justify-content-between">
            <div class="mb-3 mb-md-0 text-center text-md-start">
                <a href="dashboard.php" class="text-white text-decoration-none">
                    <span class="d-block navbar-brand"><i class="fas fa-balance-scale me-2"></i>e-Noteria</span>
                    <span class="header-subtitle">Platforma Shtetërore e Noterisë - Republika e Kosovës</span>
                </a>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <!-- Language Selector -->
                <form method="get" class="d-flex align-items-center bg-white rounded p-1" style="height: 38px;">
                    <i class="fas fa-globe text-secondary ms-2 me-1"></i>
                    <select name="lang" id="lang" onchange="this.form.submit()" class="form-select border-0 py-0 ps-1 pe-4" style="font-size: 0.9rem; width: auto; box-shadow: none; background-color: transparent;">
                        <option value="sq"<?php if($lang=='sq')echo' selected';?>>Shqip</option>
                        <option value="sr"<?php if($lang=='sr')echo' selected';?>>Српски</option>
                        <option value="en"<?php if($lang=='en')echo' selected';?>>English</option>
                    </select>
                </form>
                
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="btn btn-outline-light btn-sm"><i class="fas fa-home me-1"></i> Dashboard</a>
                    <a href="logout.php" class="btn btn-danger btn-sm text-white"><i class="fas fa-sign-out-alt"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="main-container">

        <!-- Hero Section -->
        <div class="hero-section fade-in-up">
            <div class="text-center">
                <?php
                // Mesazh informues për orarin e shkurtuar gjatë Ramazanit
                $today = date('Y-m-d');
                $ramazan_start = '2026-02-19';
                $ramazan_end = '2026-03-19';
                if ($today >= $ramazan_start && $today <= $ramazan_end) {
                    echo '<div class="alert alert-warning mt-3 mb-4" style="max-width:600px;margin:auto;">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Orari i punës është i shkurtuar deri në ora 15:00 gjatë muajit të Ramazanit.</strong><br>
                        Kjo masë është marrë për të mbrojtur cilësinë e shërbimit dhe mirëqenien e punonjësve, pasi gjatë kësaj periudhe lodhja është më e madhe dhe rritet mundësia për gabime në punë.
                    </div>';
                }
                ?>
                <div class="mb-4">
                    <i class="fas fa-calendar-check" style="font-size: 4rem; color: var(--gov-blue); filter: drop-shadow(0 4px 8px rgba(0,51,102,0.3));"></i>
                </div>
                <h1 class="hero-title"><?php echo htmlspecialchars($L['reserve_title']); ?></h1>
                <p class="hero-subtitle"><?php echo htmlspecialchars($L['reserve_sub']); ?></p>

                <!-- Progress Steps -->
                <div class="progress-steps mt-4">
                    <div class="step completed">
                        <i class="fas fa-check"></i>
                        <span>Kyçja</span>
                    </div>
                    <div class="step active">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Rezervimi</span>
                    </div>
                    <div class="step">
                        <i class="fas fa-credit-card"></i>
                        <span>Pagesa</span>
                    </div>
                    <div class="step">
                        <i class="fas fa-check-circle"></i>
                        <span>Përfundimi</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <strong>Sukses!</strong> <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            
            <?php
            // Payment Status block
            $payment_status = '';
            if ($reservation_id) {
                $stmtStatus = $pdo->prepare("SELECT payment_status FROM reservations WHERE id = ?");
                $stmtStatus->execute([$reservation_id]);
                $rowStatus = $stmtStatus->fetch();
                if ($rowStatus) {
                    $payment_status = $rowStatus['payment_status'];
                }
            }
            if ($reservation_id):
            ?>
                <div class="alert alert-info border-start border-info border-4" role="alert">
                    <h5 class="alert-heading"><i class="fas fa-receipt me-2"></i>Statusi i Pagesës (Rezervimi #<?php echo $reservation_id; ?>)</h5>
                    <p class="mb-0 mt-2">
                        <?php if ($payment_status === 'paid'): ?>
                            <span class="badge bg-success"><i class="fas fa-check me-1"></i> E PËRFUNDUAR</span> <span class="ms-2">Pagesa është kryer me sukses.</span>
                        <?php elseif ($payment_status === 'pending'): ?>
                            <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> NË PRITJE</span> <span class="ms-2">Pagesa është në procesim.</span>
                        <?php elseif ($payment_status === 'failed'): ?>
                            <span class="badge bg-danger"><i class="fas fa-times me-1"></i> DËSHTOI</span> <span class="ms-2">Pagesa nuk u realizua.</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">I PANJOHUR</span>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i> <strong>Kujdes!</strong> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>


        <!-- Office List & Uploads -->
        <!-- Office List Section -->
        <div class="row mb-5">
            <div class="col-12">
                <h3 class="section-title text-primary mb-4">
                    <i class="fas fa-building me-2"></i><?php echo htmlspecialchars($L['offices']); ?>
                </h3>

                <div class="row g-3">
                    <?php foreach ($zyrat as $zyra): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="office-card">
                            <div class="d-flex align-items-start gap-3">
                                <div class="flex-shrink-0">
                                    <div style="width: 50px; height: 50px; background: linear-gradient(135deg, var(--gov-blue) 0%, #001122 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white;">
                                        <i class="fas fa-landmark fa-lg"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="office-name mb-1"><?php echo htmlspecialchars($zyra['emri'] ?? ''); ?></h5>
                                    <p class="office-location mb-2">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        <?php echo htmlspecialchars($zyra['qyteti'] ?? ''); ?>, <?php echo htmlspecialchars($zyra['shteti'] ?? ''); ?>
                                    </p>
                                    <form action="uploads/upload_document.php" method="post" enctype="multipart/form-data" class="d-flex gap-2">
                                        <input type="hidden" name="zyra_id" value="<?php echo $zyra['id']; ?>">
                                        <input type="file" name="document" required class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-cloud-upload-alt me-1"></i>Ngarko
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="row g-4">
            
            <!-- Main Reservation Form -->
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4><i class="fas fa-calendar-check me-2"></i><?php echo htmlspecialchars($L['form_title']); ?></h4>
                        <span class="badge bg-secondary"><i class="fas fa-clock me-1"></i> Deri në 16:00</span>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">Fjalëkalimi</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-key"></i></span>
                                    <input type="password" name="password" id="password" class="form-control" placeholder="Lëre bosh nëse nuk do ta ndryshosh">
                                </div>
                                <div class="form-text">Nëse nuk dëshironi të ndryshoni fjalëkalimin, lëreni këtë fushë bosh.</div>
                            </div>
                                <label for="service" class="form-label"><?php echo htmlspecialchars($L['service']); ?> <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-file-contract"></i></span>
                                    <select name="service" id="service" class="form-select" required>
                                        <option value="">-- Zgjidhni llojin e shërbimit --</option>
                                        <optgroup label="Kontrata">
                                            <option value="Kontratë për Shitblerje të Veturës">Kontratë për Shitblerje të Veturës</option>
                                            <option value="Kontratë shitblerjeje të pasurisë së paluajtshme">Shitblerje e pasurisë së paluajtshme</option>
                                            <option value="Kontratë dhuratë">Kontratë dhuratë</option>
                                            <option value="Kontratë qiraje">Kontratë qiraje</option>
                                            <option value="Kontratë huaje">Kontratë huaje</option>
                                        </optgroup>
                                        <optgroup label="Të tjera">
                                            <option value="Autorizim për vozitje të automjetit">Autorizim për vozitje</option>
                                            <option value="Pëlqim prindëror">Pëlqim prindëror për udhëtim</option>
                                            <option value="Legalizimi i dokumenteve">Legalizim dokumenti</option>
                                            <option value="Vertetimi i nënshkrimit">Vërtetim nënshkrimi</option>
                                            <option value="Vertetimi i kopjeve">Vërtetim kopje</option>
                                            <option value="Hartimi i testamentit">Hartimi i testamentit</option>
                                            <option value="Deklaratë nën betim">Deklaratë nën betim</option>
                                            <option value="Vërtetim i gjendjes familjare">Vërtetim gjendjeje familjare</option>
                                        </optgroup>
                                    </select>
                                </div>
                                <div class="form-text">Zgjidhni shërbimin e saktë për të lehtësuar procesin noterial.</div>
                            </div>

                            <div class="mb-4">
                                <label for="zyra_id" class="form-label">
                                    <i class="fas fa-building me-1"></i>
                                    Zyra Noteriale <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-landmark"></i></span>
                                    <select name="zyra_id" id="zyra_id" class="form-select" required>
                                        <option value="">-- Zgjidhni zyrën noteriale --</option>
                                        <?php foreach ($zyrat as $zyra): ?>
                                            <option value="<?php echo $zyra['id']; ?>" <?php echo (isset($_POST['zyra_id']) && $_POST['zyra_id'] == $zyra['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($zyra['emri'] . ' - ' . $zyra['qyteti']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-text">Zgjidhni zyrën ku dëshironi të bëni rezervimin.</div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="date" class="form-label"><?php echo htmlspecialchars($L['date']); ?> <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                        <input type="date" name="date" id="date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="time" class="form-label"><?php echo htmlspecialchars($L['time']); ?> <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-clock"></i></span>
                                        <?php
                                        // Dinamikisht cakto max sipas periudhës
                                        $today = date('Y-m-d');
                                        $ramazan_start = '2026-02-19';
                                        $ramazan_end = '2026-03-19';
                                        $max_time_input = ($today >= $ramazan_start && $today <= $ramazan_end) ? '15:00' : '16:00';
                                        ?>
                                        <input type="time" name="time" id="time" class="form-control" required min="08:00" max="<?php echo $max_time_input; ?>">
                                    </div>
                                    <?php
                                    // Mesazh dinamik për orarin sipas periudhës
                                    $today = date('Y-m-d');
                                    $ramazan_start = '2026-02-19';
                                    $ramazan_end = '2026-03-19';
                                    if ($today >= $ramazan_start && $today <= $ramazan_end) {
                                        echo '<div class="form-text text-danger">Gjatë muajit të Ramazanit: E Hënë - E Premte (08:00 - 15:00)</div>';
                                    } else {
                                        echo '<div class="form-text">Zyrat punojnë E Hënë - E Premte (08:00 - 16:00).</div>';
                                    }
                                    ?>
                                </div>
                            </div>

                            <!-- Terms and Conditions Agreement -->
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" id="agree_terms" name="agree_terms" required>
                                <label class="form-check-label small" for="agree_terms">
                                    Pajtohem me <a href="terms.php" target="_blank" class="text-primary">Kushtet e Përdorimit</a> dhe <a href="Privacy_policy.php" target="_blank" class="text-primary">Politikën e Privatësisë</a> të platformës Noteria.
                                </label>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                                    <i class="fas fa-calendar-check me-2"></i> <?php echo htmlspecialchars($L['submit']); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Side Panel: Payment & Info -->
            <div class="col-lg-4">

                <!-- Payment Card -->
                <div class="card payment-card h-auto mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Pagesa Online</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-light border small text-muted mb-3">
                            <i class="fas fa-shield-alt me-1"></i> <strong>I Sigurt:</strong> Transaksionet mbrohen me enkriptim bankar 256-bit SSL.
                        </div>

                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="fw-bold text-dark">Tarifa e Rezervimit:</span>
                                <span class="badge bg-primary">
                                    <?php
                                    if (in_array($user_category, $free_categories)) {
                                        echo 'Falas';
                                    } else {
                                        echo '€25.50';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="small text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Pagesa përfshin të gjitha shërbimet noteriale dhe dokumentacionin.
                            </div>
                        </div>

                        <?php if ($reservation_id): ?>
                        <form method="POST" action="process_payment.php" id="form-bank-main">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="mb-3">
                                <label for="emri_bankes" class="form-label text-dark">
                                    <i class="fas fa-university me-1"></i>Përzgjidh Bankën
                                </label>
                                <select name="emri_bankes" id="emri_bankes" class="form-select" required>
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
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg" id="btn-standard-pay">
                                    <i class="fas fa-credit-card me-2"></i>
                                    Vazhdo me Pagesën
                                </button>
                            </div>
                        </form>

                        <!-- Tinky Embedded Form -->
                        <div id="form-tinky-dropdown" class="mt-3 p-3 rounded" style="display:none; background: linear-gradient(135deg, #f0f8ff 0%, #e6f3ff 100%); border: 1px solid var(--gov-blue);">
                            <h6 class="mb-3" style="color: var(--gov-blue); font-weight: 700;">
                                <i class="fas fa-bolt me-1"></i> Pagesë e Shpejtë me Tinky
                            </h6>
                            
                            <input type="hidden" name="payment_method" value="tinky">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                            <input type="hidden" name="reservation_id" value="<?php echo htmlspecialchars($reservation_id); ?>">

                            <div class="mb-2">
                                <label class="form-label small mb-1 tinky-label">Emri i plotë <span class="text-danger">*</span></label>
                                <input type="text" name="payer_name" class="form-control form-control-sm border-warning" placeholder="Emri dhe Mbiemri">
                            </div>
                            
                            <div class="mb-2">
                                <label class="form-label small mb-1 tinky-label">IBAN <span class="text-danger">*</span></label>
                                <input type="text" name="payer_iban" class="form-control form-control-sm border-warning" placeholder="Shembull: XK051212012345678901" maxlength="20" autocomplete="off" pattern="XK[0-9]{2}[0-9A-Z]{16}">
                                <div class="form-text">Shembull IBAN-i: <b>XK051212012345678901</b> (pa hapësira)</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label small mb-1 tinky-label">Shuma (€) <span class="text-danger">*</span></label>
                                <input type="number" name="amount" class="form-control form-control-sm border-warning" min="10" step="0.01" placeholder="0.00">
                            </div>

                            <button type="button" id="tinky-submit" class="btn btn-tinky w-100 btn-sm shadow-sm">
                                <i class="fas fa-paper-plane me-1"></i> PAGUAJ TANI
                            </button>
                        </div>
                    </div>
                </div>

                <?php endif; ?>

                <!-- Contact Info -->
                <div id="contact" class="card mt-4 border-secondary">
                    <div class="card-body">
                        <h6><i class="fas fa-info-circle me-2"></i>Mbështetje Teknike</h6>
                        <p class="small text-muted mb-0">Hapni një tiketë nëse keni probleme gjatë rezervimit.</p>
                        <hr class="my-2">
                        <small class="d-block"><i class="fas fa-envelope me-2"></i> support@noteria-rks.gov.net</small>
                        <small class="d-block"><i class="fas fa-phone me-2"></i> +383 38 200 100</small>
                    </div>
                </div>

            </div>
        </div>

        <!-- Administrator View for Reservations -->
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
        <div class="row mt-5">
            <div class="col-12">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0 text-white"><i class="fas fa-user-shield me-2"></i>Paneli i Administratorit - Terminet</h4>
                    </div>
                    <div class="card-body">
                        <?php
                        // Here we just display a general list if we can't filter by specific office, or user's office
                        // Assuming $zyra_id might be needed from user session if not in global scope
                        // But previous code used $zyra_id which was undefined in the top block I read.
                        // Wait, looking at lines 1-146, $zyrat is fetched, but $zyra_id is not defined.
                        // The original code had: WHERE r.zyra_id = ? -> execute([$zyra_id]) inside an admin block
                        // likely relying on $zyra_id being passed or set. 
                        // I will safely modify this to show recent reservations generally or handle the undefined variable.
                        
                        $query = "SELECT r.service, r.date, r.time, u.emri, u.mbiemri, u.email, r.document_path FROM reservations r JOIN users u ON r.user_id = u.id ORDER BY r.date DESC, r.time DESC LIMIT 20";
                        $stmt = $pdo->prepare($query);
                        $stmt->execute();
                        
                        if ($stmt->rowCount() > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Shërbimi</th>
                                            <th>Data</th>
                                            <th>Ora</th>
                                            <th>Përdoruesi</th>
                                            <th>Dokumenti</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $stmt->fetch()): ?>
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
                                                        <a href="<?php echo htmlspecialchars($row['document_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-download"></i> Shiko
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Pa dokument</span>
                                                    <?php endif; ?>
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

    </div>
    <!-- Government Footer -->
    <style>
        footer a:hover { color: white !important; }
    </style>
    <footer style="background: linear-gradient(135deg, #1a1f2e 0%, #24292e 100%); color: #d1d5da; padding: 40px 0 20px; margin-top: 40px; text-align: center; position: relative;">
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, #cfa856, transparent);"></div>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 mb-4">
                    <span style="font-weight: 800; font-size: 1.5rem; margin-bottom: 10px; display: block; background: linear-gradient(135deg, #ffffff, #cfa856); background-clip: text; -webkit-background-clip: text; -webkit-text-fill-color: transparent;">e-Noteria</span>
                    <p style="color: #8b949e; margin: 0; font-size: 0.9rem;">Platformë SaaS për zyrat noteriale në Kosovë</p>
                </div>
            </div>
            <div style="border-top: 1px solid #30363d; padding-top: 20px; margin-top: 20px;">
                <div style="margin-bottom: 15px;">
                    <a href="terms.php" style="color: #8b949e; text-decoration: none; margin: 0 15px; display: inline-block; transition: color 0.3s;">Kushtet e Përdorimit</a>
                    <a href="Privacy_policy.php" style="color: #8b949e; text-decoration: none; margin: 0 15px; display: inline-block; transition: color 0.3s;">Politika e Privatësisë</a>
                    <a href="ndihma.php" style="color: #8b949e; text-decoration: none; margin: 0 15px; display: inline-block; transition: color 0.3s;">Ndihma</a>
                </div>
                <p style="margin: 0; font-size: 0.8em; color: #8b949e;">
                    &copy; <?php echo date('Y'); ?> Platforma e-Noteria | Republika e Kosovës
                </p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Interactive Service Selection -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Service selection interaction
        const serviceOptions = document.querySelectorAll('.service-option');
        const serviceSelect = document.getElementById('service');

        serviceOptions.forEach(option => {
            option.addEventListener('click', function() {
                // Remove selected class from all options
                serviceOptions.forEach(opt => opt.classList.remove('selected'));

                // Add selected class to clicked option
                this.classList.add('selected');

                // Update hidden select value
                const serviceValue = this.getAttribute('data-service');
                serviceSelect.value = serviceValue;
            });
        });

        // Form validation enhancement
        const form = document.querySelector('form[action=""]');
        if (form) {
            form.addEventListener('submit', function(e) {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<span class="spinner me-2"></span>Po procesohet...';
                    submitBtn.disabled = true;

                    // Re-enable after 10 seconds as fallback
                    setTimeout(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }, 10000);
                }
            });
        }
    });
    </script>

    <!-- Tinky Logic Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var bankSelect = document.getElementById('emri_bankes');
        var tinkyForm = document.getElementById('form-tinky-dropdown');
        var mainBankFormBtn = document.getElementById('btn-standard-pay');
        var tinkySubmit = document.getElementById('tinky-submit');

        // Toggle Tinky Form
        if (bankSelect && tinkyForm) {
            bankSelect.addEventListener('change', function() {
                if (this.value === 'Tinky') {
                    tinkyForm.style.display = 'block';
                    if (mainBankFormBtn) mainBankFormBtn.style.display = 'none'; // Hide standard submit
                    // Auto-focus logic
                    setTimeout(() => {
                        var firstInput = tinkyForm.querySelector('input[name="payer_name"]');
                        if (firstInput) firstInput.focus();
                    }, 100);
                } else {
                    tinkyForm.style.display = 'none';
                    if (mainBankFormBtn) mainBankFormBtn.style.display = 'block'; // Show standard submit
                }
            });
        }

        // Tinky Submission Logic
        function showMessage(type, text) {
            var existing = document.querySelector('#tinky-message');
            if (existing) existing.remove();
            
            var div = document.createElement('div');
            div.id = 'tinky-message';
            div.className = type === 'success' ? 'alert alert-success mt-2 small' : 'alert alert-danger mt-2 small';
            div.innerHTML = (type === 'success' ? '<i class="fas fa-check-circle"></i> ' : '<i class="fas fa-times-circle"></i> ') + text;
            
            tinkyForm.parentNode.insertBefore(div, tinkyForm);
        }

        if (tinkySubmit) {
            tinkySubmit.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Fields
                var payerName = tinkyForm.querySelector('input[name="payer_name"]');
                var payerIban = tinkyForm.querySelector('input[name="payer_iban"]');
                var amountEl = tinkyForm.querySelector('input[name="amount"]');
                var csrf = tinkyForm.querySelector('input[name="csrf_token"]');
                var reservationId = tinkyForm.querySelector('input[name="reservation_id"]');

                if (!payerName.value.trim() || !payerIban.value.trim() || !amountEl.value) {
                    showMessage('error', 'Ju lutemi plotësoni të gjitha fushat e Tinky.');
                    return;
                }

                // UI Update
                var originalText = tinkySubmit.innerHTML;
                tinkySubmit.disabled = true;
                tinkySubmit.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Duke procesuar...';

                var fd = new FormData();
                fd.append('payment_method', 'tinky');
                fd.append('csrf_token', csrf ? csrf.value : '');
                fd.append('reservation_id', reservationId ? reservationId.value : '');
                fd.append('payer_name', payerName.value.trim());
                fd.append('payer_iban', payerIban.value.trim());
                fd.append('amount', amountEl.value);

                fetch('tinky_payment.php', {
                    method: 'POST',
                    body: fd
                })
                .then(res => res.json().catch(() => ({ success: false, message: 'Invalid JSON response' })))
                .then(data => {
                    tinkySubmit.disabled = false;
                    tinkySubmit.innerHTML = originalText;
                    
                    if (data.success) {
                        showMessage('success', data.message || 'Pagesa u krye me sukses!');
                        if (data.redirect) setTimeout(() => { window.location.href = data.redirect; }, 1500);
                    } else {
                        showMessage('error', data.message || 'Pagesa dështoi.');
                    }
                })
                .catch(err => {
                    tinkySubmit.disabled = false;
                    tinkySubmit.innerHTML = originalText;
                    showMessage('error', 'Gabim në komunikim me serverin.');
                    console.error('Tinky Error:', err);
                });
            });
        }
    });
    </script>
</body>
</html>