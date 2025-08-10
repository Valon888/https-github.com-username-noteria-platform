<?php
// filepath: c:\xampp\htdocs\noteria\book.php
// Konfigurimi i raportimit të gabimeve
ini_set('display_errors', 0); // Mos shfaq gabime në faqe
ini_set('log_errors', 1);     // Log gabimet në server
ini_set('error_log', __DIR__ . '/error.log'); // Ruaj gabimet në error.log në këtë folder

session_start();
require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $emri = $_POST["emri"];
    $mbiemri = $_POST["mbiemri"];
    $email = $_POST["email"];
    $password = $_POST["password"];
    $zyra_id = $_POST["zyra_id"];
    $roli = $_POST["roli"];
    $personal_number = $_POST["personal_number"];

    // Kontrollo nëse email-i ekziston
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $error = "Ky email është regjistruar më parë!";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        // Ruaj $password_hash në databazë, jo fjalëkalimin origjinal
        $stmt = $pdo->prepare("INSERT INTO users (emri, mbiemri, email, password, zyra_id, roli, personal_number) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$emri, $mbiemri, $email, $password_hash, $zyra_id, $roli, $personal_number]);
        $_SESSION['success'] = "Regjistrimi u krye me sukses!";
        header("Location: login.php");
        exit();
    }
}

// Merr zyrat për dropdown
$zyrat = $pdo->query("SELECT id, emri FROM zyrat")->fetchAll();
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Regjistrohu | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(120deg, #e2eafc 0%, #f8fafc 100%);
            font-family: 'Montserrat', Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 400px;
            margin: 60px auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            padding: 36px 28px;
            text-align: center;
        }
        h2 {
            color: #2d6cdf;
            margin-bottom: 28px;
            font-size: 2rem;
            font-weight: 700;
        }
        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }
        label {
            display: block;
            margin-bottom: 6px;
            color: #2d6cdf;
            font-weight: 600;
        }
        input[type="text"], input[type="email"], input[type="password"], select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2eafc;
            border-radius: 8px;
            font-size: 1rem;
            background: #f8fafc;
            transition: border-color 0.2s;
        }
        input:focus, select:focus {
            border-color: #2d6cdf;
            outline: none;
        }
        button[type="submit"] {
            background: #2d6cdf;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px 0;
            width: 100%;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }
        button[type="submit"]:hover {
            background: #184fa3;
        }
        .login-link {
            margin-top: 22px;
            font-size: 0.98rem;
            color: #333;
        }
        .login-link a {
            color: #2d6cdf;
            text-decoration: none;
            font-weight: 600;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Regjistrohu në Noteria</h2>
        <?php if (isset($error)): ?>
            <div class="error" style="color:#d32f2f; background:#ffeaea; border-radius:8px; padding:10px; margin-bottom:18px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="emri">Emri:</label>
                <input type="text" id="emri" name="emri" required>
            </div>
            <div class="form-group">
                <label for="mbiemri">Mbiemri:</label>
                <input type="text" id="mbiemri" name="mbiemri" required>
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Fjalëkalimi:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="zyra_id">Zyra:</label>
                <select name="zyra_id" id="zyra_id" required>
                    <?php foreach ($zyrat as $zyra): ?>
                        <option value="<?php echo $zyra['id']; ?>"><?php echo htmlspecialchars($zyra['emri']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="roli">Roli:</label>
                <select name="roli" id="roli" required>
                    <option value="user">Përdorues</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label for="personal_number">Numri Personal:</label>
                <input type="text" id="personal_number" name="personal_number" required maxlength="10" pattern="\d{9,10}">
            </div>
            <button type="submit">Regjistrohu</button>
        </form>
        <div class="login-link">
            Keni llogari? <a href="login.php">Kyçuni këtu</a>
        </div>
    </div>
</body>
</html>