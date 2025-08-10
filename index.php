<?php
// filepath: c:\xampp\htdocs\noteria\book.php
// Konfigurimi i raportimit të gabimeve
ini_set('display_errors', 0); // Mos shfaq gabime në faqe
ini_set('log_errors', 1);     // Log gabimet në server
ini_set('error_log', __DIR__ . '/error.log'); // Ruaj gabimet në error.log në këtë folder

// Inicializimi i sesionit në mënyrë të sigurt
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kontrolli i autentifikimit të përdoruesit
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Përfshirja e lidhjes me bazën e të dhënave
require_once 'db_connection.php';

// Shto këtë kod pas marrjes së të dhënave të përdoruesit
$pw_success = null;
$pw_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $pw_error = "Ju lutemi plotësoni të gjitha fushat.";
    } elseif ($new_password !== $confirm_password) {
        $pw_error = "Fjalëkalimet e reja nuk përputhen.";
    } else {
        // Kontrollo fjalëkalimin aktual
        require_once 'db_connection.php';
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($hashed_password);
        $stmt->fetch();
        $stmt->close();

        if (!password_verify($current_password, $hashed_password)) {
            $pw_error = "Fjalëkalimi aktual është i pasaktë.";
        } else {
            // Përditëso fjalëkalimin
            $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $new_hashed, $user_id);
            if ($stmt->execute()) {
                $pw_success = "Fjalëkalimi u ndryshua me sukses!";
            } else {
                $pw_error = "Ndodhi një gabim gjatë ndryshimit të fjalëkalimit.";
            }
            $stmt->close();
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profili i Përdoruesit | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(120deg, #f8fafc 0%, #e2eafc 100%);
            font-family: 'Montserrat', Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .profile-container {
            max-width: 400px;
            margin: 60px auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            padding: 32px 24px;
            text-align: center;
        }
        .profile-container h1 {
            color: #2d6cdf;
            margin-bottom: 24px;
            font-size: 2rem;
            font-weight: 700;
        }
        .profile-info {
            font-size: 1.1rem;
            color: #333;
            margin-bottom: 12px;
        }
        .profile-info strong {
            color: #2d6cdf;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #e2eafc;
            display: inline-block;
            margin-bottom: 20px;
            line-height: 80px;
            font-size: 2.5rem;
            color: #2d6cdf;
            font-weight: 700;
        }
        .pw-form { margin-top: 32px; text-align: left; }
        .pw-form label { color: #2d6cdf; font-weight: 600; }
        .pw-form input[type="password"] { width: 100%; padding: 8px 10px; border: 1px solid #e2eafc; border-radius: 8px; margin-bottom: 12px; }
        .pw-form button { background: #2d6cdf; color: #fff; border: none; border-radius: 8px; padding: 10px 0; width: 100%; font-size: 1rem; font-weight: 700; cursor: pointer; }
        .pw-form button:hover { background: #184fa3; }
        .pw-success { color: #388e3c; background: #eafaf1; border-radius: 8px; padding: 10px; margin-bottom: 18px; font-size: 1rem; }
        .pw-error { color: #d32f2f; background: #ffeaea; border-radius: 8px; padding: 10px; margin-bottom: 18px; font-size: 1rem; }
    </style>
</head>
<body>
    <div class="profile-container">
        <div class="profile-avatar">
            <?php echo strtoupper(substr(htmlspecialchars($user['name']), 0, 1)); ?>
        </div>
        <h1>Profili i Përdoruesit</h1>
        <div class="profile-info"><strong>ID:</strong> <?php echo htmlspecialchars($user['id']); ?></div>
        <div class="profile-info"><strong>Emri:</strong> <?php echo htmlspecialchars($user['name']); ?></div>
        <div class="profile-info"><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></div>

        <!-- Forma për ndryshimin e fjalëkalimit -->
        <div class="pw-form">
            <h2>Ndrysho Fjalëkalimin</h2>
            <?php if ($pw_success): ?>
                <div class="pw-success"><?php echo htmlspecialchars($pw_success); ?></div>
            <?php endif; ?>
            <?php if ($pw_error): ?>
                <div class="pw-error"><?php echo htmlspecialchars($pw_error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <label for="current_password">Fjalëkalimi aktual:</label>
                <input type="password" name="current_password" id="current_password" required>
                <label for="new_password">Fjalëkalimi i ri:</label>
                <input type="password" name="new_password" id="new_password" required>
                <label for="confirm_password">Përsërit fjalëkalimin e ri:</label>
                <input type="password" name="confirm_password" id="confirm_password" required>
                <button type="submit" name="change_password">Ndrysho Fjalëkalimin</button>
            </form>
        </div>
    </div>
</body>
</html>