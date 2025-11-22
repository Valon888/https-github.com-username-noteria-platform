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
require_once __DIR__ . '/../lib-webtopay/WebToPay.php';

// Merr të dhënat e përdoruesit - fallback në session nëse kolonat nuk ekzistojnë
$user_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT roli, zyra_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $roli = $user['roli'] ?? $_SESSION['roli'] ?? null;
    $zyra_id = $user['zyra_id'] ?? $_SESSION['zyra_id'] ?? null;
} catch (PDOException $e) {
    // Kolonat roli ose zyra_id nuk ekzistojnë - përdor session values
    error_log("Notice: roli/zyra_id columns not in users table - using session values");
    $roli = $_SESSION['roli'] ?? null;
    $zyra_id = $_SESSION['zyra_id'] ?? null;
}

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
}// Genero një CSRF token nëse nuk ekziston
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

// Merr të gjitha zyrat për dropdown
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
                    <input type="hidden" name="service" value="Shërbimi i zgjedhur">
                    <input type="hidden" name="date" value="<?php echo date('Y-m-d'); ?>">
                    <input type="hidden" name="zyra_id" value="<?php echo $zyra_id; ?>">
                    <button type="submit" formaction="paysera_pay.php" formmethod="POST" style="background:#388e3c;margin-top:8px;">Paguaj Online</button>
                </form>
            </div>
        <?php endif; ?>
        <div class="logout">
            <a href="logout.php">Shkyçu</a>
        </div>
    </div>
</body>
</html>