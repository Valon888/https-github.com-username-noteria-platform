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
if (!isset($pdo) || !$pdo) {
    die("<div style='color:red;text-align:center;margin-top:30px;'>Gabim në lidhjen me databazën. Ju lutemi kontaktoni administratorin.</div>");
}

// Merr të dhënat e përdoruesit, përfshirë rolin dhe zyra_id
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT roli, zyra_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$roli = isset($user['roli']) ? $user['roli'] : null;
$zyra_id = $user['zyra_id'];

// Shto këtë për të shmangur warning për variablat e papërcaktuara
if (!isset($success)) $success = null;
if (!isset($error)) $error = null;
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
            if ($weekday == 6 || $weekday == 7) {
                $error = "Zyrat noteriale nuk punojnë të Shtunën dhe të Dielën!";
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

// Merr terminet e rezervuara në zyrën tuaj për përdoruesin e zakonshëm
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
$stmtNotif = $pdo->prepare("SELECT id, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmtNotif->execute([$user_id]);
$notifications = $stmtNotif->fetchAll();

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
$stmt = $pdo->prepare("SELECT id FROM users WHERE zyra_id = ? AND roli = 'admin' LIMIT 1");
$stmt->execute([$zyra_id]);
$noter_id = $stmt->fetchColumn();

// Ruaj mesazhin nëse është dërguar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message']) && !empty($_POST['message'])) {
    $msg = trim($_POST['message']);
    if ($noter_id && $msg) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $noter_id, $msg]);
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

        /* Stilet për seksionin e reklamave */
        .ads-section {
            background: linear-gradient(90deg,#e2eafc 0%,#f8fafc 100%);
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(44,108,223,0.07);
            padding: 28px 18px 18px 18px;
            margin-bottom: 36px;
            margin-top: 10px;
        }
        .ads-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: #2d6cdf;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
        }
        .ads-cards {
            display: flex;
            gap: 22px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .ad-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(44,108,223,0.06);
            overflow: hidden;
            width: 300px;
            min-width: 220px;
            display: flex;
            flex-direction: column;
            margin-bottom: 12px;
            transition: transform 0.18s;
        }
        .ad-card:hover {
            transform: translateY(-6px) scale(1.03);
            box-shadow: 0 8px 24px rgba(44,108,223,0.13);
        }
        .ad-img {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }
        .ad-content {
            padding: 16px 14px 18px 14px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .ad-content h4 {
            margin: 0 0 8px 0;
            color: #184fa3;
            font-size: 1.08rem;
            font-weight: 700;
        }
        .ad-content p {
            margin: 0 0 12px 0;
            color: #444;
            font-size: 0.98rem;
        }
        .ad-btn {
            background: #2d6cdf;
            color: #fff;
            border-radius: 7px;
            padding: 8px 18px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            text-align: center;
            transition: background 0.18s;
            display: inline-block;
        }
        .ad-btn:hover {
            background: #184fa3;
        }
        @media (max-width: 900px) {
            .ads-cards { flex-direction: column; gap: 18px; align-items: center; }
            .ad-card { width: 98%; min-width: 0; }
        }

        /* Stilet për seksionin e reklamave në anën e djathtë */
        .ads-sidebar {
            position: fixed;
            top: 40px;
            right: 32px;
            width: 340px;
            z-index: 100;
            background: linear-gradient(90deg,#e2eafc 0%,#f8fafc 100%);
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(44,108,223,0.13);
            padding: 22px 14px 14px 14px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .ads-sidebar .ads-title {
            font-size: 1.18rem;
            font-weight: 700;
            color: #2d6cdf;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
        }
        .ads-sidebar .ads-cards {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .ads-sidebar .ad-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(44,108,223,0.06);
            overflow: hidden;
            width: 100%;
            display: flex;
            flex-direction: column;
            margin-bottom: 0;
            transition: transform 0.18s;
        }
        .ads-sidebar .ad-card:hover {
            transform: translateY(-4px) scale(1.03);
            box-shadow: 0 8px 24px rgba(44,108,223,0.13);
        }
        .ads-sidebar .ad-img {
            width: 100%;
            height: 90px;
            object-fit: cover;
        }
        .ads-sidebar .ad-content {
            padding: 12px 10px 14px 10px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .ads-sidebar .ad-content h4 {
            margin: 0 0 6px 0;
            color: #184fa3;
            font-size: 1rem;
            font-weight: 700;
        }
        .ads-sidebar .ad-content p {
            margin: 0 0 8px 0;
            color: #444;
            font-size: 0.95rem;
        }
        .ads-sidebar .ad-btn {
            background: #2d6cdf;
            color: #fff;
            border-radius: 7px;
            padding: 7px 14px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.98rem;
            text-align: center;
            transition: background 0.18s;
            display: inline-block;
        }
        .ads-sidebar .ad-btn:hover {
            background: #184fa3;
        }
        @media (max-width: 1200px) {
            .ads-sidebar { display: none; }
        }
    </style>
    <!-- FullCalendar CSS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css" rel="stylesheet">
<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.js"></script>
</head>
<body>
    <div class="container">
        <h1>Mirë se erdhët në Panelin e Kontrollit</h1>
        <?php if ($roli !== 'admin'): ?>
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
                        // Merr vendndodhjen (qyteti dhe shteti)
                        $stmtLoc = $pdo->prepare("SELECT qyteti, shteti FROM zyrat WHERE id = ?");
                        $stmtLoc->execute([$zyra_id]);
                        $vendndodhja = $stmtLoc->fetch();
                        $qyteti = $vendndodhja['qyteti'] ?? '';
                        $shteti = $vendndodhja['shteti'] ?? '';

                        echo "<p><strong>Zyra:</strong> " . htmlspecialchars($zyra['emri']) . "</p>";
                        echo "<p><strong>Vendndodhja:</strong> ";
                        if ($qyteti) {
                            echo htmlspecialchars($qyteti) . ", ";
                        }
                        echo htmlspecialchars($shteti) . "</p>";

                        $stmt = $pdo->prepare("SELECT emri, mbiemri, email FROM users WHERE zyra_id = ?");
                        $stmt->execute([$zyra_id]);
                        echo "<ul>";
                        while ($row = $stmt->fetch()) {
                            echo "<li>" . htmlspecialchars($row['emri']) . " " . htmlspecialchars($row['mbiemri']) . " - " . htmlspecialchars($row['email']) . "</li>";
                        }
                        echo "</ul>";

                        echo "<h3 style='margin-top:32px;'>Terminet e rezervuara në zyrën tuaj</h3>";
                        $stmt = $pdo->prepare("SELECT r.service, r.date, r.time, u.emri, u.mbiemri, u.email, r.document_path
    FROM reservations r
    JOIN users u ON r.user_id = u.id
    WHERE r.zyra_id = ?
    ORDER BY r.date DESC, r.time DESC");
                        $stmt->execute([$zyra_id]);
                        if ($stmt->rowCount() > 0) {
                            echo "<table>";
                            echo "<tr>
                                    <th>Shërbimi</th>
                                    <th>Data</th>
                                    <th>Ora</th>
                                    <th>Përdoruesi</th>
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
                                echo "</td>
        </tr>";
                            }
                            echo "</table>";
                        } else {
                            echo "<div class='no-data'>Nuk ka asnjë termin të rezervuar në këtë zyrë.</div>";
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
            <div class="zyra-section">
                <h2>Terminet e mia të rezervuara</h2>
                <?php
                if (!empty($user_terminet)) {
                    echo "<table>";
                    echo "<tr>
                            <th>Shërbimi</th>
                            <th>Data</th>
                            <th>Ora</th>
                            <th>Statusi</th>
                            <th>Dokumenti</th>
                          </tr>";
                    foreach ($user_terminet as $row) {
                        echo "<tr>
                            <td>" . htmlspecialchars($row['service']) . "</td>
                            <td>" . htmlspecialchars($row['date']) . "</td>
                            <td>" . htmlspecialchars($row['time']) . "</td>
                            <td>";
                        if ($row['status'] === 'aprovohet') {
                            echo "<span style='color:#388e3c;font-weight:600;'>Termini është aprovuar!</span>";
                        } elseif ($row['status'] === 'refuzohet') {
                            echo "<span style='color:#d32f2f;font-weight:600;'>Termini është refuzuar!</span>";
                        } else {
                            echo "<span style='color:#888;'>Në pritje të aprovimit</span>";
                        }
                        echo "</td>
                            <td>";
                        if (!empty($row['document_path'])) {
                            echo "<a href='" . htmlspecialchars($row['document_path']) . "' target='_blank' style='color:#2d6cdf;font-weight:600;'>Shiko dokumentin</a>";
                        } else {
                            echo "<span style='color:#888;'>Nuk ka dokument</span>";
                        }
                        echo "</td>
                        </tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<div class='no-data'>Nuk keni asnjë termin të rezervuar.</div>";
                }
                ?>
            </div>
        <?php else: ?>
            <div class="admin-section">
                <h2>Menaxhimi i Zyrave Noteriale</h2>
                <?php
                echo "<h3>Lista e Zyrave:</h3><ul>";
                foreach ($zyrat as $zyra) {
                    // Merr vendndodhjen për secilën zyrë
                    $stmtLoc = $pdo->prepare("SELECT qyteti, shteti FROM zyrat WHERE id = ?");
                    $stmtLoc->execute([$zyra['id']]);
                    $vendndodhja = $stmtLoc->fetch();
                    $qyteti = $vendndodhja['qyteti'] ?? '';
                    $shteti = $vendndodhja['shteti'] ?? '';
                    echo "<li><strong>ID:</strong> " . htmlspecialchars($zyra['id']) . " - <strong>Emri:</strong> " . htmlspecialchars($zyra['emri']) . " <span style='color:#184fa3;font-size:0.97em;'>(";
                    if ($qyteti) echo htmlspecialchars($qyteti) . ", ";
                    echo htmlspecialchars($shteti) . ")</span></li>";
                }
                echo "</ul>";

                // Lista e përdoruesve sipas zyrave
                $stmt = $pdo->query("SELECT z.id, z.emri AS zyra_emri, z.qyteti, z.shteti, u.emri, u.mbiemri, u.email FROM zyrat z LEFT JOIN users u ON u.zyra_id = z.id ORDER BY z.id");
                $current_zyra = null;
                while ($row = $stmt->fetch()) {
                    if ($current_zyra !== $row['zyra_emri']) {
                        if ($current_zyra !== null) echo "</ul>";
                        echo "<h3>Zyra: " . htmlspecialchars($row['zyra_emri']) . " <span style='color:#184fa3;font-size:0.97em;'>(";
                        if ($row['qyteti']) echo htmlspecialchars($row['qyteti']) . ", ";
                        echo htmlspecialchars($shteti) . ")</span></h3><ul>";
                        $current_zyra = $row['zyra_emri'];
                    }
                    if ($row['emri']) {
                        echo "<li>" . htmlspecialchars($row['emri'] . " " . $row['mbiemri']) . " - " . htmlspecialchars($row['email']) . "</li>";
                    }
                }
                if ($current_zyra !== null) echo "</ul>";
                ?>
            </div>
            <div class="admin-section">
                <h2>Terminet dhe Dokumentet për çdo Zyrë</h2>
                <?php
                foreach ($zyrat as $zyra) {
                    // Merr vendndodhjen për secilën zyrë
                    $stmtLoc = $pdo->prepare("SELECT qyteti, shteti FROM zyrat WHERE id = ?");
                    $stmtLoc->execute([$zyra['id']]);
                    $vendndodhja = $stmtLoc->fetch();
                    $qyteti = $vendndodhja['qyteti'] ?? '';
                    $shteti = $vendndodhja['shteti'] ?? '';

                    echo "<h3 style='margin-top:24px;'>Terminet për zyrën: " . htmlspecialchars($zyra['emri']) . " <span style='color:#184fa3;font-size:0.97em;'>(";
                    if ($qyteti) echo htmlspecialchars($qyteti) . ", ";
                    echo htmlspecialchars($shteti) . ")</span></h3>";
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
                                <th>Statusi</th>
                              </tr>";
                        while ($row = $stmt->fetch()) {
                            echo "<tr>
                                    <td>" . htmlspecialchars($row['service']) . "</td>
                                    <td>" . htmlspecialchars($row['date']) . "</td>
                                    <td>" . htmlspecialchars($row['time']) . "</td>
                                    <td>" . htmlspecialchars($row['emri'] . " " . $row['mbiemri']) . "</td>
                                    <td>" . htmlspecialchars($row['email']) . "</td>
                                    <td>";
                            if (!empty($row['document_path'])) {
                                echo "<a href='" . htmlspecialchars($row['document_path']) . "' target='_blank' style='color:#2d6cdf;font-weight:600;'>Shiko dokumentin</a>";
                            } else {
                                echo "<span style='color:#888;'>Nuk ka dokument</span>";
                            }
                            echo "</td>
<td>";
                            if (isset($row['status']) && $row['status'] === 'në pritje') {
                                echo '<form method="post" style="display:inline;">
                                    <input type="hidden" name="reservation_id" value="' . htmlspecialchars($row['id']) . '">
                                    <button type="submit" name="approve" style="background:#388e3c;color:#fff;padding:4px 12px;border-radius:6px;">Aprovo</button>
                                </form>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="reservation_id" value="' . htmlspecialchars($row['id']) . '">
                                    <button type="submit" name="reject" style="background:#d32f2f;color:#fff;padding:4px 12px;border-radius:6px;">Refuzo</button>
                                </form>';
                            } elseif (isset($row['status']) && $row['status'] === 'aprovohet') {
                                echo '<span style="color:#388e3c;font-weight:600;">Aprovuar</span>';
                            } elseif (isset($row['status']) && $row['status'] === 'refuzohet') {
                                echo '<span style="color:#d32f2f;font-weight:600;">Refuzuar</span>';
                            }
                            echo "</td>
                              </tr>";
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
                    // Merr vendndodhjen për secilën zyrë
                    $stmtLoc = $pdo->prepare("SELECT qyteti, shteti FROM zyrat WHERE id = ?");
                    $stmtLoc->execute([$zyra['id']]);
                    $vendndodhja = $stmtLoc->fetch();
                    $qyteti = $vendndodhja['qyteti'] ?? '';
                    $shteti = $vendndodhja['shteti'] ?? '';

                    echo "<h3 style='margin-top:24px;'>Faturat për zyrën: " . htmlspecialchars($zyra['emri']) . " <span style='color:#184fa3;font-size:0.97em;'>(";
                    if ($qyteti) echo htmlspecialchars($qyteti) . ", ";
                    echo htmlspecialchars($shteti) . ")</span></h3>";
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
            <!-- Kalendar i Termineve për Noterët -->
            <div class="admin-section">
    <h2>Kalendar i Termineve</h2>
    <div id="kalendar" style="background:#fff; border-radius:12px; box-shadow:0 2px 8px #2d6cdf22; padding:12px;"></div>
</div>
            <div class="admin-section">
    <h2>Statistika të Shpejta</h2>
    <?php
    $total_terminet = $pdo->query("SELECT COUNT(*) FROM reservations")->fetchColumn();
    $total_dokumente = $pdo->query("SELECT COUNT(*) FROM reservations WHERE document_path IS NOT NULL AND document_path != ''")->fetchColumn();
    $total_pagesa = $pdo->query("SELECT COUNT(*) FROM fatura")->fetchColumn();
    ?>
    <ul>
        <li><b>Terminet totale:</b> <?php echo $total_terminet; ?></li>
        <li><b>Dokumente të ngarkuara:</b> <?php echo $total_dokumente; ?></li>
        <li><b>Fatura të lëshuara:</b> <?php echo $total_pagesa; ?></li>
    </ul>
</div>
        <?php endif; ?>
        <!-- Seksioni i njoftimeve dhe statistikave -->
        <div class="zyra-section" id="njoftime">
            <h2>Njoftimet e fundit</h2>
            <?php if ($notifications): ?>
                <ul>
                    <?php foreach ($notifications as $notif): ?>
                        <li style="margin-bottom:7px;<?php if(!$notif['is_read']) echo 'font-weight:bold;'; ?>">
                            <?php echo htmlspecialchars($notif['message']); ?>
                            <span style="color:#888;font-size:0.95em;">(<?php echo date('d.m.Y H:i', strtotime($notif['created_at'])); ?>)</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="info">Nuk ka njoftime të reja.</div>
            <?php endif; ?>

            <h2 style="margin-top:32px;">Statistika të Shpejta</h2>
            <ul>
                <li><b>Terminet totale:</b> <?php echo $total_terminet; ?></li>
                <li><b>Dokumente të ngarkuara:</b> <?php echo $total_dokumente; ?></li>
                <li><b>Fatura të lëshuara:</b> <?php echo $total_pagesa; ?></li>
            </ul>
        </div>

        <!-- Buton për video thirrje -->
        <button id="video-call-btn" style="background:#2d6cdf;color:#fff;padding:8px 18px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;margin-bottom:16px;cursor:pointer;">Nis Video Thirrje</button>
        <div id="video-call-warning" style="display:none;margin-bottom:16px;background:#ffeaea;color:#d32f2f;padding:12px;border-radius:8px;font-weight:600;text-align:center;">
            Thirrja e juaj do të inxhizohet për përdorim të brendshëm dhe qëllime ligjore.
            <br>
            <a id="video-call-link" href="https://meet.jit.si/noteria_<?php echo $user_id; ?>" target="_blank" style="display:inline-block;margin-top:10px;background:#388e3c;color:#fff;padding:8px 18px;border-radius:8px;text-decoration:none;font-weight:600;">Vazhdo në Video Thirrje</a>
        </div>
        <div class="logout">
            <a href="logout.php">Shkyçu</a>
        </div>

        <div class="zyra-section">
    <h2>Profili Im</h2>
    <?php
    // Merr të dhënat aktuale
    $stmt = $pdo->prepare("SELECT emri, mbiemri, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $userData = $stmt->fetch();
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        $emri = trim($_POST['emri']);
        $mbiemri = trim($_POST['mbiemri']);
        $email = trim($_POST['email']);
        $pass = $_POST['password'];
        if ($emri && $mbiemri && $email) {
            $sql = "UPDATE users SET emri=?, mbiemri=?, email=?";
            $params = [$emri, $mbiemri, $email];
            if (!empty($pass)) {
                $sql .= ", password=?";
                $params[] = password_hash($pass, PASSWORD_DEFAULT);
            }
            $sql .= " WHERE id=?";
            $params[] = $user_id;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo "<div class='success'>Profili u përditësua me sukses!</div>";
        }
    }
    ?>
    <form method="post">
        <label>Emri:</label>
        <input type="text" name="emri" value="<?php echo htmlspecialchars($userData['emri']); ?>" required>
        <label>Mbiemri:</label>
        <input type="text" name="mbiemri" value="<?php echo htmlspecialchars($userData['mbiemri']); ?>" required>
        <label>Email:</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" required>
        <label>Fjalëkalimi i ri (opsional):</label>
        <input type="password" name="password" placeholder="Lëre bosh nëse nuk do ta ndryshosh">
        <button type="submit" name="update_profile">Ruaj Ndryshimet</button>
    </form>
</div>

    <div class="zyra-section">
    <h2>Lajme & Informacione Ligjore</h2>
    <?php
    // Vetëm admini mund të shtojë lajm
    if ($roli === 'admin' && isset($_POST['shto_lajm'])) {
        $titulli = trim($_POST['titulli']);
        $permbajtja = trim($_POST['permbajtja']);
        if ($titulli && $permbajtja) {
            $stmt = $pdo->prepare("INSERT INTO lajme (titulli, permbajtja) VALUES (?, ?)");
            $stmt->execute([$titulli, $permbajtja]);
            echo "<div class='success'>Lajmi u publikua!</div>";
        }
    }
    if ($roli === 'admin') {
    ?>
    <form method="post" style="margin-bottom:18px;">
        <input type="text" name="titulli" placeholder="Titulli i lajmit" required>
        <textarea name="permbajtja" placeholder="Përmbajtja..." required style="width:100%;height:60px;"></textarea>
        <button type="submit" name="shto_lajm">Publiko Lajmin</button>
    </form>
    <?php } ?>
    <?php
    $stmt = $pdo->query("SELECT * FROM lajme ORDER BY created_at DESC LIMIT 5");
    while ($lajm = $stmt->fetch()) {
        echo "<div style='margin-bottom:14px;'><b>" . htmlspecialchars($lajm['titulli']) . "</b> <span style='color:#888;font-size:0.95em;'>(" . date('d.m.Y', strtotime($lajm['created_at'])) . ")</span><br>";
        echo nl2br(htmlspecialchars($lajm['permbajtja'])) . "</div>";
    }
    ?>
</div>

<div class="admin-section" id="mesazhe">
    <h2>Mesazhe me Noterin</h2>
    <?php
    // Vetëm admini sheh këtë seksion
    if ($roli === 'admin') {
        // Merr klientët e fundit që kanë dërguar mesazhe
        $stmt = $pdo->query("SELECT DISTINCT u.id, u.emri, u.mbiemri
            FROM users u
            JOIN messages m ON u.id = m.sender_id OR u.id = m.receiver_id
            WHERE u.roli != 'admin' AND u.zyra_id = $zyra_id
            ORDER BY m.created_at DESC
            LIMIT 10");
        $klientet = $stmt->fetchAll();

        if ($klientet) {
            foreach ($klientet as $klient) {
                $klient_id = $klient['id'];
                echo "<div style='margin-bottom:16px;'>";
                echo "<strong>" . htmlspecialchars($klient['emri'] . ' ' . $klient['mbiemri']) . "</strong> <br>";

                // Shfaq mesazhet e fundit mes adminit dhe klientit
                $stmtMsg = $pdo->prepare("SELECT m.*, u.emri, u.mbiemri
                    FROM messages m
                    JOIN users u ON u.id = IF(m.sender_id = ?, m.receiver_id, m.sender_id)
                    WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
                    ORDER BY m.created_at DESC
                    LIMIT 5");
                $stmtMsg->execute([$klient_id, $user_id, $klient_id, $klient_id, $user_id]);
                $messages = $stmtMsg->fetchAll();

                if ($messages) {
                    foreach ($messages as $msg) {
                        $bg_color = $msg['sender_id'] == $user_id ? '#eafaf1' : '#f1e7e7';
                        echo "<div style='background:$bg_color;padding:10px 14px;border-radius:8px;margin-bottom:8px;'>";
                        echo "<strong style='color:#184fa3;'>" . htmlspecialchars($msg['emri'] . ' ' . $msg['mbiemri']) . ":</strong> ";
                        echo nl2br(htmlspecialchars($msg['message']));
                        echo "<div style='font-size:0.85em;color:#666;margin-top:4px;'>" . date('d.m.Y H:i', strtotime($msg['created_at'])) . "</div>";
                        echo "</div>";
                    }
                } else {
                    echo "<div style='color:#888;'>Nuk ka mesazhe.</div>";
                }

                // Forma për dërgimin e mesazheve
                echo "<form method='post' style='display:flex;gap:8px;margin-top:10px;'>";
                echo "<input type='hidden' name='send_message_admin' value='1'>";
                echo "<input type='hidden' name='klient_id' value='" . $klient_id . "'>";
                echo "<input type='text' name='message_admin' placeholder='Shkruani përgjigjen tuaj...' style='flex:1;padding:8px;border-radius:6px;border:1px solid #e2eafc;' maxlength='500' required>";
                echo "<button type='submit' style='background:#2d6cdf;color:#fff;padding:8px 18px;border-radius:8px;font-weight:600;'>Dërgo</button>";
                echo "</form>";

                echo "</div>";
            }
        } else {
            echo "<div style='color:#888;'>Nuk ka klientë të rinj për të shfaqur.</div>";
        }
    } else {
        echo "<div class='error'>Qasja e pamjaftueshme. Vetëm admini mund të shohë këtë seksion.</div>";
    }
    ?>
</div>

<?php
// Ruaj mesazhin nga admini për klientin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message_admin'], $_POST['klient_id'], $_POST['message_admin'])) {
    $klient_id = intval($_POST['klient_id']);
    $msg = trim($_POST['message_admin']);
    if ($klient_id && $msg) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $klient_id, $msg]);
        // Rifresko faqen për të parë mesazhet e reja
        header("Location: " . $_SERVER['PHP_SELF'] . "?klient_id=" . $klient_id);
        exit();
    }
}
?>
    <!-- Seksioni promovues për platformën Noteria -->
<div class="ads-section">
    <div class="ads-title">
        <span style="margin-right:10px;">&#128081;</span> Zbuloni fuqinë e Noteria.com!
    </div>
    <div class="ads-cards">
        <div class="ad-card">
            <img src="https://images.unsplash.com/photo-1519125323398-675f0ddb6308?auto=format&fit=crop&w=400&q=80" class="ad-img" alt="Rezervim Online">
            <div class="ad-content">
                <h4>Rezervim Online i Termineve</h4>
                <p>Rezervoni termin noterial nga shtëpia, pa pritje dhe pa stres. Zgjidhni zyrën, datën dhe orën që ju përshtatet!</p>
                <a href="reservation.php#rezervo" class="ad-btn">Rezervo tani</a>
            </div>
        </div>
        <div class="ad-card">
            <img src="https://images.unsplash.com/photo-1515378791036-0648a3ef77b2?auto=format&fit=crop&w=400&q=80" class="ad-img" alt="Pagesa Online">
            <div class="ad-content">
                <h4>Pagesa të Sigurta Online</h4>
                <p>Kryeni pagesat për shërbimet noteriale përmes bankave të ndryshme ose Paysera, shpejt dhe sigurt.</p>
                <a href="#pagesa" class="ad-btn">Mëso më shumë</a>
            </div>
        </div>
        <div class="ad-card">
            <img src="https://images.unsplash.com/photo-1461749280684-dccba630e2f6?auto=format&fit=crop&w=400&q=80" class="ad-img" alt="Njoftime & Statistika">
            <div class="ad-content">
                <h4>Njoftime & Statistika</h4>
                <p>Merrni njoftime të menjëhershme për statusin e termineve, dokumenteve dhe shikoni statistikat tuaja në çdo kohë.</p>
                <a href="#njoftime" class="ad-btn">Shiko njoftimet</a>
            </div>
        </div>
        <div class="ad-card">
            <img src="https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=400&q=80" class="ad-img" alt="Chat & Mbështetje">
            <div class="ad-content">
                <h4>Chat me Noterin & Mbështetje</h4>
                <p>Komunikoni direkt me noterin ose merrni ndihmë nga ekipi ynë për çdo pyetje apo problem.</p>
                <a href="#mesazhe" class="ad-btn">Kontakto tani</a>
            </div>
        </div>
        <div class="ad-card">
            <img src="https://images.unsplash.com/photo-1465101046530-73398c7f28ca?auto=format&fit=crop&w=400&q=80" class="ad-img" alt="Lajme Ligjore">
            <div class="ad-content">
                <h4>Lajme & Informacione Ligjore</h4>
                <p>Qëndroni të informuar me lajmet dhe ndryshimet më të fundit ligjore, direkt nga platforma Noteria.</p>
                <a href="#lajme" class="ad-btn">Lexo lajmet</a>
            </div>
        </div>
    </div>
</div>
<footer style="text-align:center; margin-top:40px; color:#888; font-size:1rem;">
    <a href="Privacy_policy.php" style="color:#2d6cdf; text-decoration:underline;">Politika e Privatësisë</a>
</footer>
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
document.getElementById('video-call-btn').onclick = function() {
    document.getElementById('video-call-warning').style.display = 'block';
    this.style.display = 'none';
};
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('kalendar');
    if (!calendarEl) return;

    // Terminet nga PHP
    var events = <?php echo json_encode(array_map(function($t) {
        return [
            'title' => $t['service'] . (isset($t['emri']) ? ' - ' . $t['emri'] . ' ' . $t['mbiemri'] : ''),
            'start' => $t['date'] . 'T' . $t['time'],
            'allDay' => false
        ];
    }, $calendar_terminet)); ?>;

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'sq',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: events,
        height: 550
    });
    calendar.render();
});
</script>
<!-- Vendos këtu kodin e tawk.to -->
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
<!-- Seksioni i pagesës online -->
<div class="zyra-section" id="pagesa">
    <h2>Pagesa Online</h2>
    <p>
        Kryeni pagesat për shërbimet noteriale në mënyrë të sigurt përmes bankave të ndryshme, Paysera ose MoneyGram. Zgjidhni bankën tuaj të preferuar gjatë rezervimit të terminit dhe ndiqni udhëzimet për të përfunduar pagesën online.
    </p>
    <ul>
        <li>Mbështet të gjitha bankat kryesore në Kosovë</li>
        <li>Pagesa të shpejta dhe të sigurta</li>
        <li>Konfirmim automatik i pagesës</li>
    </ul>
    <a href="reservation.php#rezervo" class="ad-btn">Paguaj tani</a>
</div>
<!-- Seksioni i lajmeve dhe informacioneve ligjore -->
<div class="zyra-section" id="lajme">
    <h2>Lajme & Informacione Ligjore</h2>
    <?php
    // Simulo lajme të ndryshme për platformën Noteria.com
    $lajmet = [
        [
            'titulli' => 'Noteria.com lançon rezervimin online të termineve!',
            'permbajtja' => 'Tani mund të rezervoni termin tuaj noterial nga shtëpia, shpejt dhe lehtë.',
            'created_at' => '2025-08-01'
        ],
        [
            'titulli' => 'Pagesa të sigurta online për çdo shërbim',
            'permbajtja' => 'Platforma jonë mbështet pagesa përmes të gjitha bankave kryesore, Paysera dhe MoneyGram.',
            'created_at' => '2025-07-28'
        ],
        [
            'titulli' => 'Njoftime të menjëhershme për statusin e dokumenteve',
            'permbajtja' => 'Çdo ndryshim në statusin e dokumenteve tuaja do të njoftohet automatikisht në panelin tuaj.',
            'created_at' => '2025-07-20'
        ],
        [
            'titulli' => 'Chat i drejtpërdrejtë me noterin tuaj',
            'permbajtja' => 'Komunikoni shpejt me noterin për çdo pyetje apo paqartësi.',
            'created_at' => '2025-07-15'
        ],
        [
            'titulli' => 'Kalendar i integruar për menaxhimin e termineve',
            'permbajtja' => 'Shikoni të gjitha terminet tuaja të rezervuara në një kalendar të thjeshtë për përdorim.',
            'created_at' => '2025-07-10'
        ],
        [
            'titulli' => 'Statistika të shpejta për përdoruesit dhe adminët',
            'permbajtja' => 'Monitoroni numrin e termineve, dokumenteve dhe pagesave në kohë reale.',
            'created_at' => '2025-07-05'
        ],
        [
            'titulli' => 'Politika e privatësisë e përditësuar',
            'permbajtja' => 'Lexoni versionin më të ri të politikës së privatësisë për të kuptuar si mbrohen të dhënat tuaja.',
            'created_at' => '2025-07-01'
        ],
        [
            'titulli' => 'Mbështetje teknike 24/7 për të gjithë përdoruesit',
            'permbajtja' => 'Ekipi ynë është gjithmonë i gatshëm të ndihmojë për çdo problem teknik.',
            'created_at' => '2025-06-28'
        ],
        [
            'titulli' => 'Dokumentet tuaja të sigurta në cloud',
            'permbajtja' => 'Të gjitha dokumentet ruhen në mënyrë të enkriptuar dhe të sigurt.',
            'created_at' => '2025-06-20'
        ],
        [
            'titulli' => 'Video thirrje me noterin',
            'permbajtja' => 'Nisni video thirrje të sigurta për konsultime të shpejta ligjore.',
            'created_at' => '2025-06-15'
        ],
        [
            'titulli' => 'Platforma Noteria.com fiton çmimin për inovacion digjital',
            'permbajtja' => 'Jemi krenarë që jemi vlerësuar për inovacionin në shërbimet noteriale online.',
            'created_at' => '2025-06-10'
        ],
        [
            'titulli' => 'Pyetjet më të shpeshta (FAQ) tani edhe më të detajuara',
            'permbajtja' => 'Gjeni përgjigje për çdo pyetje rreth platformës Noteria.com.',
            'created_at' => '2025-06-05'
        ],
        [
            'titulli' => 'Risi: Pagesa me kartë bankare',
            'permbajtja' => 'Tani mund të paguani edhe me kartë bankare për çdo shërbim noterial.',
            'created_at' => '2025-06-01'
        ],
        [
            'titulli' => 'Njoftim: Orari i ri i zyrave noteriale',
            'permbajtja' => 'Zyrat tona janë të hapura nga ora 08:00 deri në 16:00, nga e hëna në të premte.',
            'created_at' => '2025-05-28'
        ],
        [
            'titulli' => 'Siguria e të dhënave tuaja është prioriteti ynë',
            'permbajtja' => 'Zbatojmë standardet më të larta të sigurisë për mbrojtjen e informacionit tuaj.',
            'created_at' => '2025-05-20'
        ],
        [
            'titulli' => 'Noteria.com tani edhe në gjuhën angleze',
            'permbajtja' => 'Platforma është e disponueshme për përdoruesit ndërkombëtarë.',
            'created_at' => '2025-05-15'
        ],
        [
            'titulli' => 'Lajm: Integrimi me sistemet shtetërore',
            'permbajtja' => 'Noteria.com është integruar me sistemet shtetërore për verifikim të shpejtë të dokumenteve.',
            'created_at' => '2025-05-10'
        ]
    ];

    foreach ($lajmet as $lajm) {
        echo "<div style='margin-bottom:14px;'><b>" . htmlspecialchars($lajm['titulli']) . "</b> <span style='color:#888;font-size:0.95em;'>(" . date('d.m.Y', strtotime($lajm['created_at'])) . ")</span><br>";
        echo nl2br(htmlspecialchars($lajm['permbajtja'])) . "</div>";
    }
    ?>
</div>
</body>
</html>
