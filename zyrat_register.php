<?php
// filepath: c:\xampp\htdocs\noteria\zyrat_register.php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
session_start();
require_once 'config.php';

$success = null;
$error = null;

// Lista e qyteteve të Kosovës
$qytetet = [
    "Prishtinë", "Mitrovicë", "Pejë", "Gjakovë", "Ferizaj", "Gjilan", "Prizren",
    "Vushtrri", "Fushë Kosovë", "Podujevë", "Suharekë", "Rahovec", "Drenas",
    "Malishevë", "Lipjan", "Deçan", "Istog", "Kamenicë", "Dragash", "Kaçanik",
    "Obiliq", "Klinë", "Viti", "Skenderaj", "Shtime", "Shtërpcë", "Novobërdë",
    "Mamushë", "Junik", "Hani i Elezit", "Zubin Potok", "Zveçan", "Leposaviq",
    "Graçanicë", "Ranillug", "Kllokot", "Parteš", "Mitrovicë e Veriut"
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $emri = trim($_POST["emri"]);
    $qyteti = $_POST["qyteti"] ?? '';
    $email = trim($_POST["email"]);
    $email2 = trim($_POST["email2"]);
    $telefoni = trim($_POST["telefoni"]);
    $shteti = "Kosova";
    $banka = trim($_POST["banka"] ?? '');
    $iban = trim($_POST["iban"] ?? '');
    $llogaria = trim($_POST["llogaria"] ?? '');
    $pagesa = trim($_POST["pagesa"] ?? '');

    // Validime
    if (
        empty($emri) || empty($qyteti) || empty($email) || empty($email2) || empty($telefoni) ||
        empty($banka) || empty($iban) || empty($llogaria) || empty($pagesa)
    ) {
        $error = "Ju lutemi plotësoni të gjitha fushat, përfshirë të dhënat bankare dhe pagesën.";
    } elseif (!in_array($qyteti, $qytetet)) {
        $error = "Qyteti i zgjedhur nuk është valid.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email-i nuk është valid.";
    } elseif ($email !== $email2) {
        $error = "Email-at nuk përputhen.";
    } elseif (!preg_match('/^\+383\d{8}$/', $telefoni)) {
        $error = "Numri i telefonit duhet të fillojë me +383 dhe të ketë gjithsej 12 shifra (p.sh. +38344123456).";
    } elseif (!preg_match('/^[A-Z0-9]{15,34}$/', $iban)) {
        $error = "IBAN nuk është valid.";
    } elseif (!preg_match('/^\d{8,20}$/', $llogaria)) {
        $error = "Numri i llogarisë duhet të përmbajë vetëm shifra (8-20 shifra).";
    } elseif (!is_numeric($pagesa) || $pagesa < 10) {
        $error = "Shuma e pagesës duhet të jetë numerike dhe të paktën 10€.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO zyrat (emri, qyteti, shteti, email, telefoni, banka, iban, llogaria, pagesa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$emri, $qyteti, $shteti, $email, $telefoni, $banka, $iban, $llogaria, $pagesa])) {
            $success = "Zyra u regjistrua me sukses dhe pagesa u pranua!";
        } else {
            $error = "Ndodhi një gabim gjatë regjistrimit.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Regjistro Zyrën | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <style>
        body { background: linear-gradient(120deg, #e2eafc 0%, #f8fafc 100%); font-family: 'Montserrat', Arial, sans-serif; margin: 0; padding: 0;}
        .container { max-width: 400px; margin: 60px auto; background: #fff; border-radius: 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); padding: 36px 28px; text-align: center;}
        h2 { color: #2d6cdf; margin-bottom: 28px; font-size: 2rem; font-weight: 700;}
        .form-group { margin-bottom: 18px; text-align: left;}
        label { display: block; margin-bottom: 6px; color: #2d6cdf; font-weight: 600;}
        input[type="text"], input[type="email"], input[type="number"], select { width: 100%; padding: 10px 12px; border: 1px solid #e2eafc; border-radius: 8px; font-size: 1rem; background: #f8fafc; transition: border-color 0.2s;}
        input[type="text"]:focus, input[type="email"]:focus, input[type="number"]:focus, select:focus { border-color: #2d6cdf; outline: none;}
        button[type="submit"] { background: #2d6cdf; color: #fff; border: none; border-radius: 8px; padding: 12px 0; width: 100%; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: background 0.2s;}
        button[type="submit"]:hover { background: #184fa3;}
        .success { color: #388e3c; background: #eafaf1; border-radius: 8px; padding: 10px; margin-bottom: 18px; font-size: 1rem;}
        .error { color: #d32f2f; background: #ffeaea; border-radius: 8px; padding: 10px; margin-bottom: 18px; font-size: 1rem;}
        .section-title { color: #184fa3; margin-top: 24px; margin-bottom: 12px; font-size: 1.1rem; font-weight: 700;}
    </style>
</head>
<body>
    <div class="container">
        <h2>Regjistro Zyrën</h2>
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="section-title">Të dhënat e zyrës</div>
            <div class="form-group">
                <label for="emri">Emri i Zyrës:</label>
                <input type="text" name="emri" id="emri" required>
            </div>
            <div class="form-group">
                <label for="qyteti">Qyteti:</label>
                <select name="qyteti" id="qyteti" required>
                    <option value="">Zgjidh qytetin</option>
                    <?php foreach ($qytetet as $q): ?>
                        <option value="<?php echo htmlspecialchars($q); ?>"><?php echo htmlspecialchars($q); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="shteti">Shteti:</label>
                <input type="text" name="shteti" id="shteti" value="Kosova" readonly>
            </div>
            <div class="form-group">
                <label for="email">Email-i:</label>
                <input type="email" name="email" id="email" required>
            </div>
            <div class="form-group">
                <label for="email2">Përsërit Email-in:</label>
                <input type="email" name="email2" id="email2" required>
            </div>
            <div class="form-group">
                <label for="telefoni">Numri i Telefonit (+383...):</label>
                <input type="text" name="telefoni" id="telefoni" placeholder="+38344123456" required>
            </div>
            <div class="section-title">Të dhënat bankare të zyrës</div>
            <div class="form-group">
                <label for="banka">Emri i Bankës:</label>
                <input type="text" name="banka" id="banka" required>
            </div>
            <div class="form-group">
                <label for="iban">IBAN:</label>
                <input type="text" name="iban" id="iban" required placeholder="p.sh. XK051212012345678906">
            </div>
            <div class="form-group">
                <label for="llogaria">Numri i Llogarisë:</label>
                <input type="text" name="llogaria" id="llogaria" required placeholder="Vetëm shifra">
            </div>
            <div class="section-title">Pagesa për platformën</div>
            <div class="form-group">
                <label for="pagesa">Shuma për pagesë (€):</label>
                <input type="number" name="pagesa" id="pagesa" min="10" step="0.01" required placeholder="p.sh. 20">
            </div>
            <button type="submit">Regjistro dhe Paguaj</button>
        </form>
    </div>
</body>
</html>