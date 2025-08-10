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
    </style>
</head>
<body>
    <div class="container">
        <h2>Rezervo Terminin Noterial</h2>
        <div class="info">Orari i termineve është deri në ora <strong>16:00</strong>. Nuk mund të rezervoni të Shtunën dhe të Dielën.</div>
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
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
    </div>
</body>
</html>
