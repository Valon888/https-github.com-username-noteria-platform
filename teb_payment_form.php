<?php
// teb_payment_form.php
// Formë profesionale për pagesë me TEB Bank jashtë WordPress/WooCommerce

// Konfigurimi i të dhënave të TEB (vendosi sipas bankës)
$clientId = 'VENDOS_CLIENT_ID';
$storeKey = 'VENDOS_STORE_KEY';
$companyName = 'Emri i Kompanisë';
$paymentSubmitUrl = 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate'; // ose prodhim
$currency = '978'; // 978 = EUR, 840 = USD
$lang = 'sq';
$storeType = '3D_PAY_HOSTING';
$tranType = 'Auth';
$refreshTime = 5;

// Përpunim i formës së klientit me validime të avancuara dhe të dhëna të plota
$errorMsg = '';
$emri = '';
$mbiemri = '';
$email = '';
$telefoni = '';
$adresa = '';
$qyteti = '';
$kodi_postar = '';
$shuma = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emri = trim($_POST['emri'] ?? '');
    $mbiemri = trim($_POST['mbiemri'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefoni = trim($_POST['telefoni'] ?? '');
    $adresa = trim($_POST['adresa'] ?? '');
    $qyteti = trim($_POST['qyteti'] ?? '');
    $kodi_postar = trim($_POST['kodi_postar'] ?? '');
    $shuma = trim($_POST['shuma'] ?? '');

    // Validime të avancuara
    if (strlen($emri) < 2 || !preg_match('/^[a-zA-ZëËçÇ\s]+$/u', $emri)) {
        $errorMsg = 'Ju lutem shkruani emrin e saktë.';
    } elseif (strlen($mbiemri) < 2 || !preg_match('/^[a-zA-ZëËçÇ\s]+$/u', $mbiemri)) {
        $errorMsg = 'Ju lutem shkruani mbiemrin e saktë.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = 'Ju lutem shkruani një email të vlefshëm.';
    } elseif (strlen($telefoni) < 7 || !preg_match('/^[0-9+\s]+$/', $telefoni)) {
        $errorMsg = 'Ju lutem shkruani një numër telefoni të vlefshëm.';
    } elseif (strlen($adresa) < 4) {
        $errorMsg = 'Ju lutem shkruani adresën.';
    } elseif (strlen($qyteti) < 2) {
        $errorMsg = 'Ju lutem shkruani qytetin.';
    } elseif (strlen($kodi_postar) < 3) {
        $errorMsg = 'Ju lutem shkruani kodin postar.';
    } elseif (!is_numeric($shuma) || floatval($shuma) < 1) {
        $errorMsg = 'Shuma minimale për pagesë është 1€.';
    }

    if (!$errorMsg) {
        $orderId = uniqid('order_');
        $amount = number_format(floatval($shuma), 2, '.', '');
        $okUrl = 'teb_payment_callback.php';
        $failUrl = 'teb_payment_callback.php';
        $randomString = bin2hex(random_bytes(10));
        $billToName = $emri . ' ' . $mbiemri;
        $billToCompany = $companyName;

        // Gjenero hash sipas kërkesave të TEB
        $hashString = $amount . '|' . $billToCompany . '|' . $billToName . '|' . $okUrl . '|' . $clientId . '|' . $currency . '|' . $failUrl . '|ver3|1|' . $lang . '|' . $okUrl . '|' . $orderId . '|' . $refreshTime . '|' . $randomString . '|' . $okUrl . '|' . $storeType . '|' . $tranType . '|' . $storeKey;
        $hash = base64_encode(pack('H*', hash('sha512', $hashString)));

        echo '<!DOCTYPE html><html lang="sq"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>Duke u lidhur me TEB Bank…</title>';
        echo '<style>body{background:#f8fafc;font-family:Montserrat,Arial,sans-serif;text-align:center;padding-top:80px;} .loader{border:6px solid #e2eafc;border-top:6px solid #2d6cdf;border-radius:50%;width:48px;height:48px;animation:spin 1s linear infinite;margin:32px auto;}@keyframes spin{100%{transform:rotate(360deg);}} .msg{font-size:1.2rem;color:#2d6cdf;margin-bottom:18px;}</style>';
        echo '</head><body>';
        echo '<div class="msg">Po lidhemi me TEB Bank, ju lutem prisni…</div><div class="loader"></div>';
        echo '<form id="tebAutoForm" method="POST" action="' . htmlspecialchars($paymentSubmitUrl) . '">' .
            '<input type="hidden" name="clientid" value="' . htmlspecialchars($clientId) . '">' .
            '<input type="hidden" name="amount" value="' . htmlspecialchars($amount) . '">' .
            '<input type="hidden" name="BillToCompany" value="' . htmlspecialchars($billToCompany) . '">' .
            '<input type="hidden" name="BillToName" value="' . htmlspecialchars($billToName) . '">' .
            '<input type="hidden" name="okUrl" value="' . htmlspecialchars($okUrl) . '">' .
            '<input type="hidden" name="failUrl" value="' . htmlspecialchars($failUrl) . '">' .
            '<input type="hidden" name="currency" value="' . htmlspecialchars($currency) . '">' .
            '<input type="hidden" name="lang" value="' . htmlspecialchars($lang) . '">' .
            '<input type="hidden" name="rnd" value="' . htmlspecialchars($randomString) . '">' .
            '<input type="hidden" name="storetype" value="' . htmlspecialchars($storeType) . '">' .
            '<input type="hidden" name="trantype" value="' . htmlspecialchars($tranType) . '">' .
            '<input type="hidden" name="orderId" value="' . htmlspecialchars($orderId) . '">' .
            '<input type="hidden" name="refreshTime" value="' . htmlspecialchars($refreshTime) . '">' .
            '<input type="hidden" name="hashAlgorithm" value="ver3">' .
            '<input type="hidden" name="instalment" value="1">' .
            '<input type="hidden" name="shopurl" value="' . htmlspecialchars($okUrl) . '">' .
            '<input type="hidden" name="hash" value="' . htmlspecialchars($hash) . '">' .
            '<input type="hidden" name="email" value="' . htmlspecialchars($email) . '">' .
            '<input type="hidden" name="telefoni" value="' . htmlspecialchars($telefoni) . '">' .
            '<input type="hidden" name="adresa" value="' . htmlspecialchars($adresa) . '">' .
            '<input type="hidden" name="qyteti" value="' . htmlspecialchars($qyteti) . '">' .
            '<input type="hidden" name="kodi_postar" value="' . htmlspecialchars($kodi_postar) . '">' .
            '</form>';
        echo '<script>setTimeout(function(){document.getElementById("tebAutoForm").submit();}, 1200);</script>';
        echo '</body></html>';
        exit();
    }
}

?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagesë me TEB Bank</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <style>
        body { background: linear-gradient(120deg, #e2eafc 0%, #f8fafc 100%); font-family: Montserrat, Arial, sans-serif; margin: 0; padding: 0; }
        .container { max-width: 420px; margin: 60px auto; background: #fff; border-radius: 18px; box-shadow: 0 8px 32px rgba(0,0,0,0.10); padding: 40px 32px; text-align: center; }
        h1 { color: #2d6cdf; font-size: 2rem; font-weight: 700; margin-bottom: 18px; }
        label { display: block; margin: 18px 0 6px 0; color: #2d6cdf; font-weight: 600; text-align:left; }
        input[type="text"], input[type="number"], input[type="email"] { width: 100%; padding: 10px; border-radius: 8px; border: 1.5px solid #e2eafc; background: #f8fafc; font-size: 1.1rem; margin-bottom: 12px; }
        .pay-btn { background: #2d6cdf; color: #fff; border: none; border-radius: 8px; padding: 12px 32px; font-size: 1.1rem; font-weight: 700; cursor: pointer; margin-top: 18px; transition: background 0.2s; }
        .pay-btn:hover { background: #184fa3; }
        .desc { color: #444; font-size: 1.05rem; margin-bottom: 18px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Pagesë me TEB Bank</h1>
    <div class="desc">Plotëso të dhënat dhe kliko <b>Paguaj</b> për të vazhduar te faqja e bankës.</div>
    <?php if ($errorMsg): ?>
        <div style="background:#ffeaea;color:#d32f2f;border-radius:8px;padding:12px 8px;margin-bottom:18px;font-size:1.05rem;">
            <?php echo htmlspecialchars($errorMsg); ?>
        </div>
    <?php endif; ?>
    <form method="POST" action="">
        <label for="emri">Emri</label>
        <input type="text" name="emri" id="emri" required placeholder="Shkruaj emrin" value="<?php echo htmlspecialchars($emri); ?>">
        <label for="mbiemri">Mbiemri</label>
        <input type="text" name="mbiemri" id="mbiemri" required placeholder="Shkruaj mbiemrin" value="<?php echo htmlspecialchars($mbiemri); ?>">
        <label for="email">Email</label>
        <input type="email" name="email" id="email" required placeholder="Shkruaj email-in" value="<?php echo htmlspecialchars($email); ?>">
        <label for="telefoni">Telefoni</label>
        <input type="text" name="telefoni" id="telefoni" required placeholder="Shkruaj numrin e telefonit" value="<?php echo htmlspecialchars($telefoni); ?>">
        <label for="adresa">Adresa</label>
        <input type="text" name="adresa" id="adresa" required placeholder="Shkruaj adresën" value="<?php echo htmlspecialchars($adresa); ?>">
        <label for="qyteti">Qyteti</label>
        <select name="qyteti" id="qyteti" required style="width:100%;padding:10px;border-radius:8px;border:1.5px solid #e2eafc;background:#f8fafc;font-size:1.1rem;margin-bottom:12px;">
            <option value="">Zgjidh qytetin</option>
            <?php
            $qytetet = [
                'Prishtinë','Ferizaj','Mitrovicë','Pejë','Gjakovë','Prizren','Gjilan','Vushtrri','Podujevë','Fushë Kosovë','Suharekë','Drenas','Rahovec','Malishevë','Lipjan','Viti','Istog','Kamenicë','Deçan','Dragash','Klinë','Obiliq','Kaçanik','Skenderaj','Shtime','Shtërpcë','Novobërdë','Mamushë','Zubin Potok','Zveçan','Leposaviq','Graçanicë','Ranillug','Parteš','Kllokot'
            ];
            foreach ($qytetet as $q) {
                $selected = ($qyteti === $q) ? 'selected' : '';
                echo "<option value=\"$q\" $selected>$q</option>";
            }
            ?>
        </select>
        <label for="kodi_postar">Kodi Postal</label>
        <input type="text" name="kodi_postar" id="kodi_postar" required placeholder="Shkruaj kodin postar" value="<?php echo htmlspecialchars($kodi_postar); ?>">
        <label for="shuma">Shuma (€)</label>
        <input type="number" name="shuma" id="shuma" min="1" step="0.01" required placeholder="Shkruaj shumën" value="<?php echo htmlspecialchars($shuma); ?>">
        <button type="submit" class="pay-btn">Paguaj</button>
    </form>
</div>
</body>
</html>
