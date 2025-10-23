<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Gjenero një CSRF token për siguri
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Lidhja dhe komunikimi me MCP Server për shërbime të jashtme (p.sh. pagesa online, SMS, etj.)
 * Ky shembull demonstron dërgimin e të dhënave të pagesës përmes një kërkese POST me cURL dhe trajtimin e përgjigjes.
 */

// Parametrat e konfigurimit për MCP
$apiUrl = 'https://mcp-server.example.com/api/payments'; // Endpoint për pagesa online
$apiKey = 'KETU_SHENO_API_KEY';

// Të dhënat e pagesës që do të dërgohen te MCP (ndryshoni sipas nevojës)
$paymentData = [
    'user_id'     => $_POST['user_id'] ?? 0,
    'amount'      => $_POST['amount'] ?? 0,
    'currency'    => $_POST['currency'] ?? 'EUR',
    'service'     => $_POST['service'] ?? '',
    'description' => $_POST['description'] ?? '',
    'return_url'  => 'https://noteria.com/dashboard.php?payment=success', // URL pas pagesës së suksesshme
    'cancel_url'  => 'https://noteria.com/dashboard.php?payment=cancel'   // URL në rast anulimi
];

// Inicializimi i cURL dhe konfigurimi i kërkesës për pagesë
$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($paymentData),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ],
]);

// Ekzekutimi i kërkesës dhe marrja e përgjigjes nga MCP
$response   = curl_exec($ch);
$httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Trajtimi i përgjigjes nga MCP për pagesën online
if ($httpCode === 200 && $response !== false) {
    $result = json_decode($response, true);
    // Nëse MCP kthen një URL për të vazhduar pagesën, ridrejto përdoruesin
    if (isset($result['payment_url'])) {
        header('Location: ' . $result['payment_url']);
        exit;
    } else {
        echo "<div style='color:#d32f2f;text-align:center;margin-top:40px;'>Nuk u gjenerua linku i pagesës. Ju lutemi provoni përsëri.</div>";
    }
} else {
    // Log ose shfaq mesazh gabimi në rast dështimi të kërkesës
    echo "<div style='color:#d32f2f;text-align:center;margin-top:40px;'>Gabim në komunikim me MCP Server. Ju lutemi kontaktoni mbështetjen.</div>";
}

?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagesa Online Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(120deg, #e2eafc 0%, #f8fafc 100%);
            font-family: 'Montserrat', Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .payment-container {
            max-width: 420px;
            margin: 60px auto;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(44,108,223,0.10);
            padding: 38px 32px 32px 32px;
            text-align: center;
        }
        h1 {
            color: #2d6cdf;
            font-size: 2.1rem;
            margin-bottom: 18px;
            font-weight: 700;
        }
        .desc {
            color: #184fa3;
            font-size: 1.08rem;
            margin-bottom: 28px;
        }
        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }
        label {
            display: block;
            margin-bottom: 7px;
            color: #2d6cdf;
            font-weight: 600;
        }
        input, select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2eafc;
            border-radius: 8px;
            font-size: 1rem;
            background: #f8fafc;
            margin-bottom: 2px;
            transition: border-color 0.2s;
        }
        input:focus, select:focus {
            border-color: #2d6cdf;
            outline: none;
        }
        button {
            background-color: #2d6cdf;
            color: white;
            padding: 13px 0;
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
        .info {
            color: #388e3c;
            background: #eafaf1;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 18px;
            font-size: 1rem;
            text-align: center;
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
        .card-icons {
            margin-bottom: 18px;
        }
        .card-icons img {
            height: 32px;
            margin: 0 6px;
            vertical-align: middle;
        }
        @media (max-width: 600px) {
            .payment-container { padding: 12px; }
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <h1>Pagesa Online</h1>
        <div class="desc">
            Plotësoni të dhënat për të kryer pagesën tuaj të sigurt përmes MCP Server.<br>
            <span style="color:#888;font-size:0.97em;">Të gjitha pagesat janë të enkriptuara dhe të mbrojtura.</span>
        </div>
        <div class="card-icons">
            <img src="https://upload.wikimedia.org/wikipedia/commons/0/04/Visa.svg" alt="Visa">
            <img src="https://upload.wikimedia.org/wikipedia/commons/0/09/Mastercard-logo.svg" alt="Mastercard">
            <img src="https://upload.wikimedia.org/wikipedia/commons/4/41/PayPal.svg" alt="PayPal">
            <img src="https://seeklogo.com/images/P/paysera-logo-6B2B6B7B3C-seeklogo.com.png" alt="Paysera" style="height:28px;">
        </div>
        <form method="POST" action="process_payment.php" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="form-group">
                <label for="shuma">Shuma për pagesë (€):</label>
                <input type="number" step="0.01" min="1" max="10000" name="shuma" id="shuma" required placeholder="P.sh. 25.00">
            </div>
            <div class="form-group">
                <label for="emri_bankes">Zgjidh bankën ose metodën:</label>
                <select name="emri_bankes" id="emri_bankes" required>
                    <option value="">Zgjidh...</option>
                    <option value="Banka Ekonomike">Banka Ekonomike</option>
                    <option value="ProCredit Bank">ProCredit Bank</option>
                    <option value="Raiffeisen Bank">Raiffeisen Bank</option>
                    <option value="Paysera">Paysera</option>
                    <option value="PayPal">PayPal</option>
                    <option value="Mastercard">Mastercard</option>
                    <option value="Visa">Visa</option>
                </select>
            </div>
            <div class="form-group">
                <label for="llogaria">Llogaria IBAN (opsionale):</label>
                <input type="text" name="llogaria" id="llogaria" maxlength="34" placeholder="P.sh. XK05 0000 0000 0000 0000">
            </div>
            <div class="form-group">
                <label for="pershkrimi">Përshkrimi i pagesës:</label>
                <input type="text" name="pershkrimi" id="pershkrimi" maxlength="120" required placeholder="P.sh. Pagesë për rezervim terminin noterial">
            </div>
            <button type="submit">Paguaj Online</button>
        </form>
        <div class="info" style="margin-top:18px;">
            Pas plotësimit të formës, do të ridrejtoheni automatikisht te sistemi i pagesave të MCP për përfundimin e transaksionit.
        </div>
    </div>
</body>
</html>
