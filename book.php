<?php
// filepath: c:\xampp\htdocs\noteria\book.php
// Konfigurimi i raportimit të gabimeve
ini_set('display_errors', 0); // Mos shfaq gabime në faqe
ini_set('log_errors', 1);     // Log gabimet në server
ini_set('error_log', __DIR__ . '/error.log'); // Ruaj gabimet në error.log në këtë folder

session_start();
require_once 'config.php';

// Kontrollo nëse përdoruesi është i kyçur, nëse jo, ridrejto te faqja e kyçjes
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Nëse ke role, kontrollo rolin:
if (isset($role_needed) && $_SESSION['roli'] !== $role_needed) {
    echo "Nuk keni autorizim për këtë veprim.";
    exit();
}

// Merr zyrat nga databaza
$zyrat = $pdo->query("SELECT id, emri FROM zyrat")->fetchAll();

// Shfaq formën për caktim të termineve
echo "<!DOCTYPE html>
<html lang='sq'>
<head>
    <meta charset='UTF-8'>
    <title>Cakto Terminin Noterial</title>
    <link href='https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap' rel='stylesheet'>
    <style>
        body { font-family: 'Montserrat', Arial, sans-serif; background: linear-gradient(120deg, #e2eafc 0%, #f8fafc 100%); margin: 0; padding: 0; }
        .container { max-width: 420px; margin: 60px auto; background: #fff; border-radius: 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); padding: 36px 28px; text-align: center; }
        h2 { color: #2d6cdf; margin-bottom: 28px; font-size: 2rem; font-weight: 700; }
        .form-group { margin-bottom: 18px; text-align: left; }
        label { display: block; margin-bottom: 6px; color: #2d6cdf; font-weight: 600; }
        input, select { width: 100%; padding: 10px 12px; border: 1px solid #e2eafc; border-radius: 8px; font-size: 1rem; background: #f8fafc; transition: border-color 0.2s; }
        input:focus, select:focus { border-color: #2d6cdf; outline: none; }
        button[type='submit'] { background: #2d6cdf; color: #fff; border: none; border-radius: 8px; padding: 12px 0; width: 100%; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: background 0.2s; }
        button[type='submit']:hover { background: #184fa3; }
        .info { color: #184fa3; background: #e2eafc; border-radius: 8px; padding: 10px; margin-bottom: 18px; font-size: 1rem; }
    </style>
</head>
<body>
    <div class='container'>
        <h2>Cakto Terminin Noterial</h2>
        <div class='info'>Orari i termineve është deri në ora <strong>16:00</strong>. Pas kësaj ore, zyrat noteriale në Kosovë nuk punojnë. <br> Nuk mund të caktoni termine të Shtunën dhe të Dielën.</div>
        <form method='POST'>
            <div class='form-group'>
                <label for='zyra_id'>Zgjidh Zyrën Noteriale:</label>
                <select name='zyra_id' id='zyra_id' required>
                    <option value=''>Zgjidh zyrën</option>";
                    foreach ($zyrat as $zyra) {
                        echo "<option value='" . htmlspecialchars($zyra['id']) . "'>" . htmlspecialchars($zyra['emri']) . "</option>";
                    }
    echo "      </select>
            </div>
            <div class='form-group'>
                <label for='service'>Shërbimi Noterial:</label>
                <select name='service' id='service' required>
                    <option value=''>Zgjidh shërbimin</option>
                    <option value='Vertetim Dokumenti'>Vertetim Dokumenti</option>
                    <option value='Legalizim'>Legalizim</option>
                    <option value='Deklaratë'>Deklaratë</option>
                    <option value='Kontratë'>Kontratë</option>
                    <!-- Shto shërbime të tjera sipas nevojës -->
                </select>
            </div>
            <div class='form-group'>
                <label for='date'>Data:</label>
                <input type='date' name='date' id='date' required>
            </div>
            <div class='form-group'>
                <label for='time'>Ora:</label>
                <input type='time' name='time' id='time' required max='16:00'>
            </div>
            <button type='submit'>Cakto Terminin</button>
        </form>
    </div>
</body>
</html>";
exit();

// POST: Ruaj terminin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $zyra_id = $_POST['zyra_id'];
    $service = trim($_POST['service']);
    $date = $_POST['date'];
    $time = $_POST['time'];
    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId) {
        echo "<p style='color:red;'>Duhet të jeni i kyçur për të caktuar termin.</p>";
        exit();
    }

    if (empty($zyra_id)) {
        echo "<div style='color:#d32f2f; background:#ffeaea; border-radius:8px; padding:10px; max-width:420px; margin:40px auto; font-family:Montserrat;'>Ju lutemi zgjidhni zyrën noteriale!</div>";
        exit();
    }

    // Kontrollo orarin maksimal
    if ($time > '16:00') {
        echo "<div style='color:#d32f2f; background:#ffeaea; border-radius:8px; padding:10px; max-width:420px; margin:40px auto; font-family:Montserrat;'>Orari maksimal për termine është ora 16:00!</div>";
        exit();
    }

    // Kontrollo nëse data është e shtunë apo e diel
    $weekday = date('N', strtotime($date)); // 6 = e shtunë, 7 = e diel
    if ($weekday == 6 || $weekday == 7) {
        echo "<div style='color:#d32f2f; background:#ffeaea; border-radius:8px; padding:10px; max-width:420px; margin:40px auto; font-family:Montserrat;'>Zyrat noteriale nuk punojnë të Shtunën dhe të Dielën!</div>";
        exit();
    }

    // Kontrollo nëse termini është i lirë për atë zyrë
    $stmt = $pdo->prepare("SELECT id FROM reservations WHERE zyra_id = ? AND date = ? AND time = ?");
    $stmt->execute([$zyra_id, $date, $time]);
    if ($stmt->fetch()) {
        echo "<div style='color:#d32f2f; background:#ffeaea; border-radius:8px; padding:10px; max-width:420px; margin:40px auto; font-family:Montserrat;'>Ky orar është i zënë për këtë zyrë. Ju lutemi zgjidhni një orar tjetër!</div>";
    } else {
        $stmt = $pdo->prepare("INSERT INTO reservations (user_id, zyra_id, service, date, time) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$userId, $zyra_id, $service, $date, $time])) {
            echo "<div style='color:#388e3c; background:#eafaf1; border-radius:8px; padding:10px; max-width:420px; margin:40px auto; font-family:Montserrat;'>Termini u caktua me sukses!</div>";
        } else {
            echo "<div style='color:#d32f2f; background:#ffeaea; border-radius:8px; padding:10px; max-width:420px; margin:40px auto; font-family:Montserrat;'>Ndodhi një gabim gjatë caktimit të terminit.</div>";
        }
    }
}

// Në vend të shfaqjes së gabimit të detajuar:
try {
    // Kodi që mund të shkaktojë gabim
} catch (Exception $e) {
    error_log($e->getMessage()); // Log gabimin në server
    echo "<div style='color:#d32f2f; background:#ffeaea; border-radius:8px; padding:10px; max-width:420px; margin:40px auto; font-family:Montserrat;'>Ndodhi një gabim. Ju lutemi provoni përsëri ose kontaktoni administratorin.</div>";
}
