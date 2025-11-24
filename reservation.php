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
                    // Matje e kohës së verifikimit të dokumentit
                    $verify_start = microtime(true);
                    // ...këtu do të thirret API e verifikimit të dokumentit (simulohet me sleep(1))
                    // sleep(1); // Zëvendëso me thirrjen reale të API-së
                    // $api_response = ...
                    $verify_end = microtime(true);
                    $verify_time = round($verify_end - $verify_start, 4);
                    try {
                        $stmt = $pdo->prepare("INSERT INTO reservations (user_id, service, date, time) VALUES (?, ?, ?, ?)");
                        if ($stmt->execute([$userId, $service, $date, $time])) {
                            $success = "Rezervimi u krye me sukses! Koha e verifikimit të dokumentit: {$verify_time} sekonda.";
                            // Dërgo email njoftimi
                            if (file_exists('Phpmailer.php')) {
                                require_once 'Phpmailer.php';
                                // Kontrollo nëse funksioni sendMail ekziston, përndryshe definoje një version të thjeshtë
                                if (!function_exists('sendMail')) {
                                    function sendMail($to, $subject, $body) {
                                        // Shembull i thjeshtë duke përdorur mail()
                                        $headers  = "MIME-Version: 1.0\r\n";
                                        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                                        $headers .= "From: Noteria <no-reply@noteria.local>\r\n";
                                        return mail($to, $subject, $body, $headers);
                                    }
                                }
                                // Merr emailin e përdoruesit
                                $stmtUser = $pdo->prepare("SELECT email, emri FROM users WHERE id = ?");
                                $stmtUser->execute([$userId]);
                                $user = $stmtUser->fetch();
                                if ($user && function_exists('sendMail')) {
                                    $to = $user['email'];
                                    $name = $user['emri'];
                                    $subject = "Njoftim për rezervimin tuaj në Noteria";
                                    $body = "Përshëndetje $name,<br><br>Rezervimi juaj u krye me sukses.<br><b>Shërbimi:</b> $service<br><b>Data:</b> $date<br><b>Ora:</b> $time<br><br>Ju faleminderit që përdorët Noteria!";
                                    sendMail($to, $subject, $body);
                                }
                            }
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
<?php
$lang = $_GET['lang'] ?? $_COOKIE['lang'] ?? 'sq';
if (!in_array($lang, ['sq','sr','en'])) $lang = 'sq';
setcookie('lang', $lang, time()+60*60*24*30, '/');
$labels = [
    'sq' => [
        'reserve_title' => 'Rezervo Terminin Tuaj',
        'reserve_sub' => 'Siguro një termin noterial në zyrën më të afërt',
        'form_title' => 'Plotësoni Formularin',
        'service' => 'Shërbimi Noterial',
        'date' => 'Data e Rezervimit',
        'time' => 'Ora e Preferuar',
        'submit' => 'Rezervo Terminin',
        'offices' => 'Zyrat Noteriale',
        'office_name' => 'Emri i Zyrës',
        'city' => 'Qyteti',
        'country' => 'Shteti',
        'upload_doc' => 'Ngarko Dokument',
        'choose_lang' => 'Gjuha:',
    ],
    'sr' => [
        'reserve_title' => 'Rezervišite svoj termin',
        'reserve_sub' => 'Obezbedite notarski termin u najbližoj kancelariji',
        'form_title' => 'Popunite formular',
        'service' => 'Notarska usluga',
        'date' => 'Datum rezervacije',
        'time' => 'Željeno vreme',
        'submit' => 'Rezerviši termin',
        'offices' => 'Notarske kancelarije',
        'office_name' => 'Naziv kancelarije',
        'city' => 'Grad',
        'country' => 'Država',
        'upload_doc' => 'Otpremi dokument',
        'choose_lang' => 'Jezik:',
    ],
    'en' => [
        'reserve_title' => 'Book Your Appointment',
        'reserve_sub' => 'Book a notary appointment at the nearest office',
        'form_title' => 'Fill the Form',
        'service' => 'Notary Service',
        'date' => 'Reservation Date',
        'time' => 'Preferred Time',
        'submit' => 'Book Appointment',
        'offices' => 'Notary Offices',
        'office_name' => 'Office Name',
        'city' => 'City',
        'country' => 'Country',
        'upload_doc' => 'Upload Document',
        'choose_lang' => 'Language:',
    ]
];
$L = $labels[$lang];
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rezervo Terminin Noterial | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,500,600,700&display=swap" rel="stylesheet">
    <style>
        :root {
            font-size: 16px;
            font-family: 'Segoe UI', sans-serif;
            line-height: 1.5;
            color-scheme: light dark;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            background-color: #f9fafb;
            min-height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 960px;
            margin: 0 auto;
            padding: 1rem;
            box-sizing: border-box;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3749a3 100%);
            color: white;
            padding: clamp(1.5rem, 4vw, 3rem);
            text-align: center;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: clamp(1.5rem, 3vw + 1rem, 2.5rem);
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        .header p {
            font-size: clamp(0.9rem, 2vw, 1.1rem);
            opacity: 0.95;
        }

        /* Content */
        .content {
            padding: clamp(1rem, 2vw, 2rem);
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        /* Titles */
        h1 {
            font-size: clamp(1.5rem, 2vw + 1rem, 2.25rem);
            text-align: center;
            margin-bottom: 2rem;
            color: #1e3a8a;
        }

        h2 {
            font-size: clamp(1.3rem, 2vw, 1.8rem);
            color: #1e3a8a;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 0.5rem;
        }

        h3 {
            font-size: clamp(1rem, 1.5vw, 1.4rem);
            color: #3749a3;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
        }

        /* Form */
        form {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1e3a8a;
            font-size: clamp(0.875rem, 1vw, 1rem);
        }

        input,
        select {
            padding: 0.75rem;
            font-size: 1rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            width: 100%;
            box-sizing: border-box;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        input:hover,
        select:hover {
            border-color: #3749a3;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
            background: white;
        }

        /* Button */
        button[type="submit"],
        .button-primary {
            background-color: #1e3a8a;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0.75rem;
            font-size: clamp(0.9rem, 1vw, 1.1rem);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        button[type="submit"]:hover,
        .button-primary:hover {
            background-color: #3749a3;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(30, 58, 138, 0.3);
        }

        button[type="submit"]:active,
        .button-primary:active {
            transform: translateY(0);
        }

        /* Messages */
        .success, .error, .info {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            border-left: 4px solid;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border-color: #388e3c;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border-color: #d32f2f;
        }

        .info {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #1e3a8a;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        th, td {
            padding: clamp(0.75rem, 1vw, 1.25rem);
            text-align: left;
            border-bottom: 1px solid #e2eafc;
            font-size: clamp(0.875rem, 1vw, 1rem);
        }

        th {
            background: #f1f5f9;
            color: #1e3a8a;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        tr:hover {
            background: #f8fafc;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .no-data {
            color: #888;
            text-align: center;
            padding: 1.5rem;
            margin-top: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            font-size: 1rem;
        }

        /* Payment */
        .payment-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .payment-buttons button {
            background: white;
            color: #1e3a8a;
            border: 2px solid #1e3a8a;
            padding: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s ease;
            width: 100%;
        }

        .payment-buttons button:hover {
            background: #1e3a8a;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.3);
        }

        #payment-forms form {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1.5rem;
            display: none;
        }

        #payment-forms form h4 {
            color: #1e3a8a;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        .section {
            margin-bottom: 2rem;
        }

        /* Responsive */
        @media (min-width: 480px) {
            .container {
                padding: 1.5rem;
            }
        }

        @media (min-width: 768px) {
            form {
                flex-direction: row;
                flex-wrap: wrap;
                gap: 1rem;
            }

            .form-group {
                flex: 1 1 45%;
            }

            button[type="submit"],
            .button-primary {
                flex: 1 1 100%;
                margin-top: 1rem;
            }

            .form-row {
                grid-template-columns: repeat(2, 1fr);
            }

            .header {
                padding: 3rem 2rem;
            }

            .content {
                padding: 2rem;
            }

            .payment-buttons {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }

        @media (min-width: 1024px) {
            .container {
                padding: 2rem;
            }

            .content {
                padding: 2rem;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            }
        }
    </style>
</head>
<body>
    <div class="container" id="rezervo">
        <form method="get" style="text-align:right;margin-bottom:10px;">
            <label for="lang" style="font-weight:600;"> <?php echo $L['choose_lang']; ?> </label>
            <select name="lang" id="lang" onchange="this.form.submit()" style="padding:4px 8px;border-radius:6px;">
                <option value="sq"<?php if($lang=='sq')echo' selected';?>>Shqip</option>
                <option value="sr"<?php if($lang=='sr')echo' selected';?>>Српски</option>
                <option value="en"<?php if($lang=='en')echo' selected';?>>English</option>
            </select>
        </form>
        <div class="header">
            <h1><?php echo htmlspecialchars($L['reserve_title']); ?></h1>
            <p><?php echo htmlspecialchars($L['reserve_sub']); ?></p>
        </div>
        <div class="content">
        <div class="info">
            <strong>⏰ Orari i Shërbimit</strong>
            Terminët ofrohen deri në orën 16:00. Zyrat noteriale janë të mbyllura të Shtunën dhe të Dielën.
        </div>
        <?php if ($success): ?>
            <div class="success">
                <strong>✓ Rezervimi u krye me sukses!</strong>
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php
            // Shfaq statusin e pagesës për rezervimin më të fundit
            $reservation_id = '';
            $payment_status = '';
            if (isset($_SESSION['user_id'])) {
                $stmtLast = $pdo->prepare("SELECT id, payment_status FROM reservations WHERE user_id = ? ORDER BY id DESC LIMIT 1");
                $stmtLast->execute([$_SESSION['user_id']]);
                $rowLast = $stmtLast->fetch();
                if ($rowLast) {
                    $reservation_id = $rowLast['id'];
                    $payment_status = $rowLast['payment_status'];
                }
            }
            if ($reservation_id) {
                echo '<div class="section"><h3>Statusi i Pagesës</h3>';
                if ($payment_status === 'paid') {
                    echo '<span style="color:green;font-weight:600;">Pagesa e kryer me sukses për rezervimin #' . htmlspecialchars($reservation_id) . '.</span>';
                } elseif ($payment_status === 'pending') {
                    echo '<span style="color:orange;font-weight:600;">Pagesa në pritje për rezervimin #' . htmlspecialchars($reservation_id) . '.</span>';
                } elseif ($payment_status === 'failed') {
                    echo '<span style="color:red;font-weight:600;">Pagesa dështoi për rezervimin #' . htmlspecialchars($reservation_id) . '.</span>';
                } elseif ($payment_status === 'cancelled') {
                    echo '<span style="color:gray;font-weight:600;">Pagesa u anulua për rezervimin #' . htmlspecialchars($reservation_id) . '.</span>';
                } else {
                    echo '<span style="color:#333;">Nuk ka të dhëna për statusin e pagesës.</span>';
                }
                echo '</div>';
            }
            ?>
            <div class="section">
                <h3>Paguaj Online</h3>
                <div class="payment-buttons">
                    <button onclick="showPaymentForm('visa')" type="button">VISA</button>
                    <button onclick="showPaymentForm('mastercard')" type="button">MasterCard</button>
                    <button onclick="showPaymentForm('applepay')" type="button">Apple Pay</button>
                    <button onclick="showPaymentForm('raiffeisen')" type="button">Raiffeisen</button>
                    <button onclick="showPaymentForm('procredit')" type="button">ProCredit</button>
                    <button onclick="showPaymentForm('bpbb')" type="button">BPB</button>
                    <button onclick="showPaymentForm('teb')" type="button">TEB</button>
                    <button onclick="showPaymentForm('nlb')" type="button">NLB</button>
                </div>
                <div id="payment-forms">
                <form id="form-visa" style="display:none;" method="POST" action="process_payment.php" autocomplete="off">
                    <h4>Paguaj me VISA</h4>
                    <input type="hidden" name="payment_method" value="visa">
                    <div class="form-group"><label>Emri në Kartelë:</label><input type="text" name="card_name" required></div>
                    <div class="form-group"><label>Numri i Kartelës:</label><input type="text" name="card_number" required maxlength="19" pattern="[0-9 ]{16,19}" placeholder="1234 5678 9012 3456"></div>
                    <div class="form-group" style="display:flex;gap:12px;"><div style="flex:1;"><label>Skadenca:</label><input type="text" name="exp_date" required maxlength="5" pattern="(0[1-9]|1[0-2])\/([0-9]{2})" placeholder="MM/YY"></div><div style="flex:1;"><label>CVV:</label><input type="text" name="cvv" required maxlength="4" pattern="[0-9]{3,4}" placeholder="123"></div></div>
                    <button type="submit" style="background:#388e3c;">Paguaj me VISA</button>
                </form>
                <form id="form-mastercard" style="display:none;" method="POST" action="process_payment.php" autocomplete="off">
                    <h4>Paguaj me MasterCard</h4>
                    <input type="hidden" name="payment_method" value="mastercard">
                    <div class="form-group"><label>Emri në Kartelë:</label><input type="text" name="card_name" required></div>
                    <div class="form-group"><label>Numri i Kartelës:</label><input type="text" name="card_number" required maxlength="19" pattern="[0-9 ]{16,19}" placeholder="1234 5678 9012 3456"></div>
                    <div class="form-group" style="display:flex;gap:12px;"><div style="flex:1;"><label>Skadenca:</label><input type="text" name="exp_date" required maxlength="5" pattern="(0[1-9]|1[0-2])\/([0-9]{2})" placeholder="MM/YY"></div><div style="flex:1;"><label>CVV:</label><input type="text" name="cvv" required maxlength="4" pattern="[0-9]{3,4}" placeholder="123"></div></div>
                    <button type="submit" style="background:#388e3c;">Paguaj me MasterCard</button>
                </form>
                <form id="form-applepay" style="display:none;" method="POST" action="process_payment.php" autocomplete="off">
                    <h4>Paguaj me Apple Pay</h4>
                    <input type="hidden" name="payment_method" value="applepay">
                    <div class="form-group"><label>Apple ID:</label><input type="text" name="apple_id" required></div>
                    <button type="submit" style="background:#388e3c;">Paguaj me Apple Pay</button>
                </form>
                <form id="form-raiffeisen" style="display:none;" method="POST" action="process_payment.php" autocomplete="off">
                    <h4>Paguaj me Raiffeisen Bank</h4>
                    <input type="hidden" name="payment_method" value="raiffeisen">
                    <div class="form-group"><label>Numri i llogarisë:</label><input type="text" name="account_number" required></div>
                    <button type="submit" style="background:#388e3c;">Paguaj me Raiffeisen</button>
                </form>
                <form id="form-procredit" style="display:none;" method="POST" action="process_payment.php" autocomplete="off">
                    <h4>Paguaj me ProCredit Bank</h4>
                    <input type="hidden" name="payment_method" value="procredit">
                    <div class="form-group"><label>Numri i llogarisë:</label><input type="text" name="account_number" required></div>
                    <div class="form-group"><label>Shuma për pagesë (€):</label><input type="number" name="amount" min="10" step="0.01" required></div>
                    <button type="submit" style="background:#388e3c;">Paguaj me ProCredit</button>
                </form>
                <form id="form-bpbb" style="display:none;" method="POST" action="process_payment.php" autocomplete="off">
                    <h4>Paguaj me BPB</h4>
                    <input type="hidden" name="payment_method" value="bpbb">
                    <div class="form-group"><label>Numri i llogarisë:</label><input type="text" name="account_number" required></div>
                    <button type="submit" style="background:#388e3c;">Paguaj me BPB</button>
                </form>
                <form id="form-teb" style="display:none;" method="POST" action="process_payment.php" autocomplete="off">
                    <h4>Paguaj me TEB Bank</h4>
                    <input type="hidden" name="payment_method" value="teb">
                    <div class="form-group"><label>Numri i llogarisë:</label><input type="text" name="account_number" required></div>
                    <button type="submit" style="background:#388e3c;">Paguaj me TEB</button>
                </form>
                <form id="form-nlb" style="display:none;" method="POST" action="process_payment.php" autocomplete="off">
                    <h4>Paguaj me NLB Banka</h4>
                    <input type="hidden" name="payment_method" value="nlb">
                    <div class="form-group"><label>Numri i llogarisë:</label><input type="text" name="account_number" required></div>
                    <button type="submit" style="background:#388e3c;">Paguaj me NLB</button>
                </form>
                <form id="form-ekonomike" style="display:none;" method="POST" action="process_payment.php" autocomplete="off">
                    <h4>Paguaj me Banka Ekonomike</h4>
                    <input type="hidden" name="payment_method" value="ekonomike">
                    <div class="form-group"><label>Numri i llogarisë:</label><input type="text" name="account_number" required></div>
                    <button type="submit" style="background:#388e3c;">Paguaj me Ekonomike</button>
                </form>
                <form id="form-komerciale" style="display:none;" method="POST" action="process_payment.php" autocomplete="off">
                    <h4>Paguaj me Banka Komerciale</h4>
                    <input type="hidden" name="payment_method" value="komerciale">
                    <div class="form-group"><label>Numri i llogarisë:</label><input type="text" name="account_number" required></div>
                    <button type="submit" style="background:#388e3c;">Paguaj me Komerciale</button>
                </form>
                <form id="form-credins" style="display:none;" method="POST" action="process_payment.php" autocomplete="off">
                    <h4>Paguaj me Credins Bank</h4>
                    <input type="hidden" name="payment_method" value="credins">
                    <div class="form-group"><label>Numri i llogarisë:</label><input type="text" name="account_number" required></div>
                    <button type="submit" style="background:#388e3c;">Paguaj me Credins</button>
                </form>
                <form id="form-onefor" style="display:none;" method="POST" action="process_payment.php" autocomplete="off">
                    <h4>Paguaj me One For</h4>
                    <input type="hidden" name="payment_method" value="onefor">
                    <div class="form-group"><label>ID/Numri One For:</label><input type="text" name="onefor_id" required></div>
                    <button type="submit" style="background:#388e3c;">Paguaj me One For</button>
                </form>
                <form id="form-moneygram" style="display:none;" method="POST" action="process_payment.php" autocomplete="off">
                    <h4>Paguaj me MoneyGram</h4>
                    <input type="hidden" name="payment_method" value="moneygram">
                    <div class="form-group"><label>ID/Numri MoneyGram:</label><input type="text" name="moneygram_id" required></div>
                    <button type="submit" style="background:#388e3c;">Paguaj me MoneyGram</button>
                </div>
                <script>
                    function showPaymentForm(method) {
                        var forms = document.querySelectorAll('#payment-forms form');
                        forms.forEach(f => f.style.display = 'none');
                        var form = document.getElementById('form-' + method);
                        if (form) form.style.display = 'block';
                    }
                </script>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error">
                <strong>✗ Gabim në Rezervim</strong>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="section">
            <h2><?php echo htmlspecialchars($L['form_title']); ?></h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="form-group">
                    <label for="service"><?php echo htmlspecialchars($L['service']); ?></label>
                    <select name="service" id="service" required>
                        <option value="">-- Zgjidh shërbimin --</option>
                    <option value="Kontratë për Shitblerje të Veturës">Kontratë për Shitblerje të Veturës</option>
                    <option value="Kontratë shitblerjeje të pasurisë së paluajtshme">Kontratë shitblerjeje të pasurisë së paluajtshme</option>
                    <option value="Kontratë dhuratë">Kontratë dhuratë</option>
                    <option value="Kontratë qiraje">Kontratë qiraje</option>
                    <option value="Kontratë huaje">Kontratë huaje</option>
                    <option value="Kontratë pengu">Kontratë pengu</option>
                    <option value="Kontratë bashkëpronësie">Kontratë bashkëpronësie</option>
                    <option value="Kontratë ndarjeje të pasurisë">Kontratë ndarjeje të pasurisë</option>
                    <option value="Kontratë mirëmbajtjeje">Kontratë mirëmbajtjeje</option>
                    <option value="Kontratë përkujdesjeje">Kontratë përkujdesjeje</option>
                    <option value="Kontratë bartjeje të pronësisë">Kontratë bartjeje të pronësisë</option>
                    <option value="Kontratë bashkëpunimi">Kontratë bashkëpunimi</option>
                    <option value="Kontratë përfaqësimi">Kontratë përfaqësimi</option>
                    <option value="Kontratë shërbimi">Kontratë shërbimi</option>
                    <option value="Kontratë furnizimi">Kontratë furnizimi</option>
                    <option value="Kontratë prenotimi">Kontratë prenotimi</option>
                    <option value="Kontratë të tjera të lejuara me ligj">Kontratë të tjera të lejuara me ligj</option>
                    <option value="Autorizim për vozitje të automjetit">Autorizim për vozitje të automjetit</option>
                    <option value="Pëlqim prindëror për udhëtim jashtë vendit të fëmijës">Pëlqim prindëror për udhëtim jashtë vendit të fëmijës</option>
                    <option value="">Zgjidh shërbimin</option>
                    <option value="Hartimi i aktit noterial">Hartimi i aktit noterial</option>
                    <option value="Hartimi i testamentit">Hartimi i testamentit</option>
                    <option value="Legalizimi i dokumenteve">Legalizimi i dokumenteve</option>
                    <option value="Vertetimi i nënshkrimit">Vertetimi i nënshkrimit</option>
                    <option value="Vertetimi i kopjeve të dokumenteve">Vertetimi i kopjeve të dokumenteve</option>
                    <option value="Vertetimi i përkthimeve">Vertetimi i përkthimeve</option>
                    <option value="Lëshimi i vërtetimeve">Lëshimi i vërtetimeve</option>
                    <option value="Hartimi i kontratave të shitblerjes">Hartimi i kontratave të shitblerjes</option>
                    <option value="Hartimi i kontratave të dhuratës">Hartimi i kontratave të dhuratës</option>
                    <option value="Hartimi i kontratave të qirasë">Hartimi i kontratave të qirasë</option>
                    <option value="Hartimi i deklaratave">Hartimi i deklaratave</option>
                    <option value="Hartimi i prokurave">Hartimi i prokurave</option>
                    <option value="Hartimi i marrëveshjeve paramartesore">Hartimi i marrëveshjeve paramartesore</option>
                    <option value="Hartimi i marrëveshjeve të trashëgimisë">Hartimi i marrëveshjeve të trashëgimisë</option>
                    <option value="Hartimi i marrëveshjeve të ndarjes së pasurisë">Hartimi i marrëveshjeve të ndarjes së pasurisë</option>
                    <option value="Hartimi i marrëveshjeve të kujdestarisë">Hartimi i marrëveshjeve të kujdestarisë</option>
                    <option value="Hartimi i marrëveshjeve të tjera të lejuara me ligj">Hartimi i marrëveshjeve të tjera të lejuara me ligj</option>
                    <!-- Forma të autorizimeve, pëlqimeve, deklaratave nën betim dhe vërtetimeve -->
                    <option value="Autorizim për përfaqësim">Autorizim për përfaqësim</option>
                    <option value="Autorizim për tërheqje dokumentesh">Autorizim për tërheqje dokumentesh</option>
                    <option value="Autorizim për shitje automjeti">Autorizim për shitje automjeti</option>
                    <option value="Autorizim për udhëtim të mituri">Autorizim për udhëtim të mituri</option>
                    <option value="Pëlqim për udhëtim të mituri">Pëlqim për udhëtim të mituri</option>
                    <option value="Pëlqim për martesë">Pëlqim për martesë</option>
                    <option value="Deklaratë nën betim për gjendje civile">Deklaratë nën betim për gjendje civile</option>
                    <option value="Deklaratë nën betim për të ardhura">Deklaratë nën betim për të ardhura</option>
                    <option value="Deklaratë nën betim për banim">Deklaratë nën betim për banim</option>
                    <option value="Vërtetim i gjendjes familjare">Vërtetim i gjendjes familjare</option>
                    <option value="Vërtetim i të ardhurave">Vërtetim i të ardhurave</option>
                    <option value="Vërtetim i banimit">Vërtetim i banimit</option>
                    <option value="Vërtetim i papunësisë">Vërtetim i papunësisë</option>
                    <option value="Vërtetim i pronësisë">Vërtetim i pronësisë</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="date"><?php echo htmlspecialchars($L['date']); ?></label>
                        <input type="date" name="date" id="date" required>
                    </div>
                    <div class="form-group">
                        <label for="time"><?php echo htmlspecialchars($L['time']); ?></label>
                        <input type="time" name="time" id="time" required max="16:00">
                    </div>
                </div>
                <button type="submit"><?php echo htmlspecialchars($L['submit']); ?></button>
            </form>
        </div>
        <?php
        // Kontrollo nëse përdoruesi është administrator
        if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
            // Shfaq terminët e rezervuara në zyrën e administratorit
            ?>
            <div class="section">
                <h2>Terminet e Rezervuara</h2>
                <?php
                $stmt = $pdo->prepare("SELECT r.service, r.date, r.time, u.emri, u.mbiemri, u.email, r.document_path
                    FROM reservations r
                    JOIN users u ON r.user_id = u.id
                    WHERE r.zyra_id = ?
                    ORDER BY r.date DESC, r.time DESC");
                $stmt->execute([$zyra_id]);
                if ($stmt->rowCount() > 0) {
                    ?>
                    <table>
                        <tr>
                            <th>Shërbimi</th>
                            <th>Data</th>
                            <th>Ora</th>
                            <th>Përdoruesi</th>
                            <th>Email</th>
                            <th>Dokumenti</th>
                        </tr>
                        <?php
                        while ($row = $stmt->fetch()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['service']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['date']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['time']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['emri']) . " " . htmlspecialchars($row['mbiemri']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                            echo "<td>";
                            if (!empty($row['document_path'])) {
                                echo "<a href='" . htmlspecialchars($row['document_path']) . "' target='_blank' style='color:#2d6cdf;font-weight:600;text-decoration:none;'>↓ Shiko</a>";
                            } else {
                                echo "<span style='color:#888;'>—</span>";
                            }
                            echo "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </table>
                    <?php
                } else {
                    echo "<div class='no-data'>Nuk ka asnjë termin të rezervuar në këtë zyrë.</div>";
                }
                ?>
            </div>
            <?php
        }
        ?>
        
        <div class="section">
            <h2><?php echo htmlspecialchars($L['offices']); ?></h2>
            <table>
                <tr>
                    <th><?php echo htmlspecialchars($L['office_name']); ?></th>
                    <th><?php echo htmlspecialchars($L['city']); ?></th>
                    <th><?php echo htmlspecialchars($L['country']); ?></th>
                    <th style="text-align:center;"><?php echo htmlspecialchars($L['upload_doc']); ?></th>
                </tr>
                <?php foreach ($zyrat as $zyra): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($zyra['emri'] ?? ''); ?></strong></td>
                    <td><?php echo htmlspecialchars($zyra['qyteti'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($zyra['shteti'] ?? ''); ?></td>
                    <td style="text-align:center;">
                        <form action="uploads/upload_document.php" method="post" enctype="multipart/form-data" style="display:inline-block;">
                            <input type="hidden" name="zyra_id" value="<?php echo $zyra['id']; ?>">
                            <input type="file" name="document" required style="display:inline;width:auto;padding:6px;">
                            <button type="submit" style="background:#388e3c;color:#fff;border:none;border-radius:6px;padding:8px 16px;cursor:pointer;font-weight:600;text-transform:uppercase;font-size:0.85rem;">↑ <?php echo htmlspecialchars($L['upload_doc']); ?></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <div class="section">
            <h2>Pagesa Online e Sigurtë</h2>
            <div class="info">
                <strong>🔒 Siguria e Pagesës</strong>
                Të dhënat tuaja bankare ruhen dhe përpunohen përmes kanaleve të sigurta të bankave të licencuara në Kosovë dhe Evropë.
            </div>
            <form method="POST" action="process_payment.php" class="bank-form" id="form-bank-main">
                <div class="form-group">
                    <label for="emri_bankes">Zgjidh Bankën</label>
                    <select name="emri_bankes" id="emri_bankes" required>
                        <option value="">-- Zgjidh bankën --</option>
                        <option value="Banka Ekonomike">Banka Ekonomike</option>
                        <option value="Banka Kombëtare Tregtare">Banka Kombëtare Tregtare</option>
                        <option value="Banka Credins">Banka Credins</option>
                        <option value="Banka për Biznes">Banka për Biznes</option>
                        <option value="ProCredit Bank">ProCredit Bank</option>
                        <option value="Raiffeisen Bank">Raiffeisen Bank</option>
                        <option value="NLB Banka">NLB Banka</option>
                        <option value="TEB Banka">TEB Banka</option>
                        <option value="One For Kosovo">One For Kosovo</option>
                        <option value="Paysera">Paysera</option>
                        <option value="MoneyGram">MoneyGram</option>
                        <option value="Tinky">Tinky Diaspora</option>
                    </select>

                    <form id="form-tinky-dropdown" style="display:none; margin-top:20px; background: linear-gradient(135deg, #fff7f0 0%, #fffbf8 100%); padding: 28px; border-radius: 12px; border: 2px solid #ff6600;" method="POST" action="tinky_payment.php" autocomplete="off">
                        <input type="hidden" name="payment_method" value="tinky">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                        <?php
                        $reservation_id = '';
                        if (isset($_SESSION['user_id'])) {
                            $stmtLast = $pdo->prepare("SELECT id FROM reservations WHERE user_id = ? ORDER BY id DESC LIMIT 1");
                            $stmtLast->execute([$_SESSION['user_id']]);
                            $rowLast = $stmtLast->fetch();
                            if ($rowLast) $reservation_id = $rowLast['id'];
                        }
                        ?>
                        <input type="hidden" name="reservation_id" value="<?php echo htmlspecialchars($reservation_id); ?>">
                        <div class="form-group"><label style="color:#ff6600; font-weight:700;">👤 Emri i plotë <span style="color:red;">*</span></label><input type="text" name="payer_name" required style="border: 2px solid #ff6600;" minlength="3" placeholder="Emri dhe mbiemri juaj"></div>
                        <div class="form-group"><label style="color:#ff6600; font-weight:700;">🏦 IBAN i Bankës <span style="color:red;">*</span></label><input type="text" name="payer_iban" required style="border: 2px solid #ff6600;" maxlength="34" pattern="[A-Z0-9]{15,34}" placeholder="p.sh. XK05 0000 0000 0000 0000" title="IBAN duhet të jetë 15-34 karaktere alphanumerike"></div>
                        <div class="form-group"><label style="color:#ff6600; font-weight:700;">💵 Shuma për pagesë (€) <span style="color:red;">*</span></label><input type="number" name="amount" min="10" step="0.01" required style="border: 2px solid #ff6600;" placeholder="p.sh. 50.00" title="Minimumi është €10"></div>
                        <div class="form-group"><label style="color:#ff6600; font-weight:700;">📝 Përshkrimi i pagesës <span style="color:red;">*</span></label><input type="text" name="description" placeholder="p.sh. Pagesë për legalizim dokumenti" required style="border: 2px solid #ff6600;" maxlength="100"></div>
                        <div style="background:#fff; border-radius:8px; padding:12px; margin:16px 0; font-size:0.95em; color:#555; border-left: 4px solid #ff6600;"><strong>ℹ️ Informacion:</strong> Këto të dhëna do të përdoren vetëm për përfundimin e pagesës tuaj përmes Tinky.</div>
                        <button type="submit" style="background: linear-gradient(90deg, #ff6600 0%, #ffb347 100%); width:100%; padding:14px; font-weight:700; border-radius:8px; font-size:1.05em; transition: all 0.3s; border: none; cursor: pointer;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 20px #ff660044';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px #ff660033';">✓ PAGUAJ ONLINE</button>
                    </form>

                    <script>
                    // Shfaq formën Tinky kur zgjidhet nga dropdown-i i bankave
                    document.addEventListener('DOMContentLoaded', function() {
                        var bankSelect = document.getElementById('emri_bankes');
                        var tinkyForm = document.getElementById('form-tinky-dropdown');
                        var mainBankForm = document.getElementById('form-bank-main');
                        if (bankSelect && tinkyForm && mainBankForm) {
                            bankSelect.addEventListener('change', function() {
                                if (this.value === 'Tinky') {
                                    tinkyForm.style.display = 'block';
                                    mainBankForm.style.display = 'none';
                                } else {
                                    tinkyForm.style.display = 'none';
                                    mainBankForm.style.display = 'block';
                                }
                            });
                        }
                    });
                    </script>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="llogaria">Numri i Llogarisë IBAN</label>
                        <input type="text" name="llogaria" id="llogaria" maxlength="34" pattern="[A-Z0-9]{15,34}" required placeholder="p.sh. XK05 0000 0000 0000 0000">
                    </div>
                    <div class="form-group">
                        <label for="shuma">Shuma (€)</label>
                        <input type="number" name="shuma" id="shuma" min="1" step="0.01" required placeholder="0.00">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="pershkrimi">Përshkrimi i Pagesës</label>
                    <input type="text" name="pershkrimi" id="pershkrimi" maxlength="100" required placeholder="p.sh. Pagesë për legalizim dokumenti">
                </div>
                
                <button type="submit">Paguaj Online</button>
            </form>
        </div>
        
        </div>
    </div>
</body>
</html>
