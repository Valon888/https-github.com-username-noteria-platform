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

// Ruaj rezervimin kur dërgohet forma
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifiko CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Veprimi i paautorizuar!";
    } else {
        $userId = $_SESSION['user_id'];
        $service = trim($_POST['service']);
        $date = $_POST['date'];
        $time = $_POST['time'];

        // Validimi bazik
        if (empty($service) || empty($date) || empty($time)) {
            $error = "Ju lutemi plotësoni të gjitha fushat!";
        } elseif ($time > '16:00') {
            $error = "Orari maksimal për termine është ora 16:00!";
        } else {
            $weekday = date('N', strtotime($date));
            if ($weekday == 6 || $weekday == 7) {
                $error = "Zyrat noteriale nuk punojnë të Shtunën dhe të Dielën!";
            } else {
                // Kontrollo nëse termini është i lirë
                $stmt = $pdo->prepare("SELECT id FROM reservations WHERE date = ? AND time = ?");
                $stmt->execute([$date, $time]);
                if ($stmt->fetch()) {
                    $error = "Ky orar është i zënë. Ju lutemi zgjidhni një orar tjetër!";
                } else {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO reservations (user_id, service, date, time) VALUES (?, ?, ?, ?)");
                        if ($stmt->execute([$userId, $service, $date, $time])) {
                            $success = "Rezervimi u krye me sukses!";
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

// Merr të gjitha zyrat dhe vendndodhjet
$stmt = $pdo->query("SELECT id, emri, qyteti, shteti FROM zyrat");
$zyrat = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <title>Rezervo Terminin Noterial | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Montserrat', Arial, sans-serif; margin: 0; padding: 0; }
        .container { max-width: 420px; margin: 60px auto; background: #fff; border-radius: 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); padding: 36px 28px; text-align: center; }
        h2 { color: #2d6cdf; margin-bottom: 28px; font-size: 2rem; font-weight: 700; }
        .form-group { margin-bottom: 18px; text-align: left; }
        label { display: block; margin-bottom: 6px; color: #2d6cdf; font-weight: 600; }
        input, select { width: 100%; padding: 10px 12px; border: 1px solid #e2eafc; border-radius: 8px; font-size: 1rem; background: #f8fafc; transition: border-color 0.2s; }
        input:focus, select:focus { border-color: #2d6cdf; outline: none; }
        button[type="submit"] { background: #2d6cdf; color: #fff; border: none; border-radius: 8px; padding: 12px 0; width: 100%; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: background 0.2s; }
        button[type="submit"]:hover { background: #184fa3; }
        .success { color: #388e3c; background: #eafaf1; border-radius: 8px; padding: 10px; margin-bottom: 18px; font-size: 1rem; }
        .error { color: #d32f2f; background: #ffeaea; border-radius: 8px; padding: 10px; margin-bottom: 18px; font-size: 1rem; }
        .info { color: #184fa3; background: #e2eafc; border-radius: 8px; padding: 10px; margin-bottom: 18px; font-size: 1rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px 15px; border: 1px solid #e2eafc; text-align: left; }
        th { background: #f1f5f9; color: #2d6cdf; font-weight: 600; }
        tr:nth-child(even) { background: #f8fafc; }
        .no-data { color: #888; padding: 10px; margin-top: 10px; font-size: 1rem; }
    </style>
</head>
<body>
    <div class="container" id="rezervo">
        <h2>Rezervo Terminin Noterial</h2>
        <div class="info">Orari i termineve është deri në ora <strong>16:00</strong>. Nuk mund të rezervoni të Shtunën dhe të Dielën.</div>
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <!-- Butoni për pagesë online për MCP -->
            <form method="POST" action="mcp_api.php" style="margin-bottom:18px;">
                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($_SESSION['user_id']); ?>">
                <input type="hidden" name="amount" value="25.50"><!-- ose merrni shumën nga rezervimi -->
                <input type="hidden" name="currency" value="EUR">
                <input type="hidden" name="service" value="<?php echo htmlspecialchars($service); ?>">
                <input type="hidden" name="description" value="Pagesë online për rezervim terminin noterial">
                <button type="submit" style="background:#388e3c;margin-top:8px;">Paguaj Online</button>
            </form>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="form-group">
                <label for="service">Shërbimi Noterial:</label>
                <select name="service" id="service" required>
                    <option value="">Zgjidh shërbimin</option>
                    <option value="Vertetim Dokumenti">Vertetim Dokumenti</option>
                    <option value="Legalizim">Legalizim</option>
                    <option value="Deklaratë">Deklaratë</option>
                    <option value="Kontratë">Kontratë</option>
                    <!-- Shto shërbime të tjera sipas nevojës -->
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
            <button type="submit">Rezervo</button>
        </form>
        <?php
        // Kontrollo nëse përdoruesi është administrator
        if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
            // Shfaq terminët e rezervuara në zyrën e administratorit
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
        }
        ?>
        <h3 style="margin-top:32px;">Zyrat Noteriale dhe Vendndodhjet</h3>
        <table>
            <tr>
                <th>Emri i Zyrës</th>
                <th>Qyteti</th>
                <th>Shteti</th>
                <th>Ngarko Dokument</th>
            </tr>
            <?php foreach ($zyrat as $zyra): ?>
            <tr>
                <td><?php echo htmlspecialchars($zyra['emri']); ?></td>
                <td><?php echo htmlspecialchars($zyra['qyteti']); ?></td>
                <td><?php echo htmlspecialchars($zyra['shteti']); ?></td>
                <td>
                    <form action="/noteria/uploads/upload_document.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="zyra_id" value="<?php echo $zyra['id']; ?>">
                        <input type="file" name="document" required>
                        <button type="submit" style="background:#2d6cdf;color:#fff;border:none;border-radius:6px;padding:6px 12px;cursor:pointer;">Ngarko</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <h3 style="margin-top:32px;">Pagesa Online e Sigurtë</h3>
        <form method="POST" action="process_payment.php" class="bank-form" style="margin-bottom:32px;">
            <div class="form-group">
                <label for="emri_bankes">Emri i Bankës:</label>
                <select name="emri_bankes" id="emri_bankes" required>
                    <option value="">Zgjidh bankën</option>
                    <option value="Banka Ekonomike">Banka Ekonomike</option>
                    <option value="Banka Kombëtare Tregtare">Banka Kombëtare Tregtare</option>
                    <option value="Banka Credins">Banka Credins</option>
                    <option value="Banka për Biznes">Banka për Biznes</option>
                    <option value="ProCredit Bank">ProCredit Bank</option>
                    <option value="Raiffeisen Bank">Raiffeisen Bank</option>
                    <option value="NLB Banka">NLB Banka</option>
                    <option value="TEB Banka">TEB Banka</option>
                    <!-- Shto banka të tjera sipas nevojës -->
                    <option value="One For">One For Kosovo</option>
                    <option value="Paysera">Paysera</option>
                    <option value="MoneyGram">MoneyGram</option>
                </select>
            </div>
            <div class="form-group">
                <label for="llogaria">Numri i Llogarisë IBAN:</label>
                <input type="text" name="llogaria" id="llogaria" maxlength="34" pattern="[A-Z0-9]{15,34}" required placeholder="p.sh. XK05 0000 0000 0000 0000">
            </div>
            <div class="form-group">
                <label for="shuma">Shuma (€):</label>
                <input type="number" name="shuma" id="shuma" min="1" step="0.01" required placeholder="Shkruani shumën">
            </div>
            <div class="form-group">
                <label for="pershkrimi">Përshkrimi i Pagesës:</label>
                <input type="text" name="pershkrimi" id="pershkrimi" maxlength="100" required placeholder="p.sh. Pagesë për legalizim dokumenti">
            </div>
            <button type="submit">Paguaj Online</button>
        </form>
        <style>
            .bank-form .form-group { margin-bottom: 16px; text-align: left; }
            .bank-form label { color: #2d6cdf; font-weight: 600; }
            .bank-form input, .bank-form select {
                width: 100%; padding: 9px 12px; border: 1px solid #e2eafc; border-radius: 8px; font-size: 1rem; background: #f8fafc;
            }
            .bank-form input:focus, .bank-form select:focus { border-color: #2d6cdf; outline: none; }
            .bank-form button[type="submit"] { background: #2d6cdf; color: #fff; border: none; border-radius: 8px; padding: 12px 0; width: 100%; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: background 0.2s; }
            .bank-form button[type="submit"]:hover { background: #184fa3; }
        </style>
        <div class="info" style="margin-bottom:24px;">
            <b>Vëmendje:</b> Të dhënat tuaja bankare ruhen dhe përpunohen në mënyrë të sigurtë. Pagesa procesohen përmes kanaleve të sigurta të bankave të licencuara në Kosovë.
        </div>
    </div>
</body>
</html>
