<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('config.php');
require_once('db_connection.php');
require_once('paysera_pay.php'); // Përfshin funksionin connectToDatabase

// Kontrollo nëse përdoruesi është i identifikuar
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '';
$is_admin = isset($_SESSION['roli']) && $_SESSION['roli'] === 'admin';

if (empty($user_id) && !$is_admin) {
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Parametra të pagesës
$payment_status = 'pending';
$message = '';
$service_type = isset($_GET['service']) ? $_GET['service'] : '';
$room = isset($_GET['room']) ? $_GET['room'] : '';
$notary_id = isset($_GET['notary_id']) ? $_GET['notary_id'] : '';
$renew = isset($_GET['renew']) && $_GET['renew'] === 'true';
$amount = 15.00; // Çmimi për konsulencë video 30 minutëshe

// Verifikimi i pagesës nga API të bankave
if (isset($_GET['payment']) && $_GET['payment'] === 'success') {
    $conn = connectToDatabase();
    
    // Verifikojmë dhe përditësojmë statusin e pagesës
    // Në një implementim të plotë, do të verifikonim nënshkrimin dhe të dhënat nga Paysera/Raiffeisen/BKT
    $payment_id = isset($_GET['payment_id']) ? $_GET['payment_id'] : '';
    $payment_method = isset($_GET['method']) ? $_GET['method'] : 'paysera';
    
    // Verifikojmë pagesën në bazë të metodës së zgjedhur
    $payment_verified = false;
    
    if (!empty($payment_id)) {
        // Simulim i verifikimit të pagesës nga banka përkatëse
        switch ($payment_method) {
            case 'paysera':
                // Simulojmë një përgjigje pozitive nga Paysera
                // Në një implementim të plotë, do të përdorim API të vërtetë të Paysera
                $payment_verified = true;
                break;
                
            case 'raiffeisen':
                // Simulojmë një përgjigje pozitive nga Raiffeisen Bank
                // Në një implementim të plotë, do të përdorim API të vërtetë të Raiffeisen
                $payment_verified = true;
                break;
                
            case 'bkt':
                // Simulojmë një përgjigje pozitive nga BKT
                // Në një implementim të plotë, do të përdorim API të vërtetë të BKT
                $payment_verified = true;
                break;
                
            default:
                $payment_verified = false;
        }
        
        if ($payment_verified) {
            // Përditësimi i databazës me statusin e pagesës
            $update_query = "UPDATE payments SET status = 'completed', completion_date = NOW(), 
                            expiry_date = DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                            WHERE payment_id = ? AND user_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ss", $payment_id, $user_id);
            
            if ($stmt->execute()) {
                $payment_status = 'completed';
                $message = 'Pagesa u krye me sukses! Ju mund të filloni video konsulencën tani.';
                
                // Marrim të dhënat e pagesës nga databaza
                $select_query = "SELECT * FROM payments WHERE payment_id = ? AND user_id = ?";
                $stmt = $conn->prepare($select_query);
                $stmt->bind_param("ss", $payment_id, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $payment_data = $result->fetch_assoc();
                    $expiry_time = strtotime($payment_data['expiry_date']);
                    
                    // Ruaj në sesion statusin e pagesës për video thirrje
                    $_SESSION['video_payment'] = [
                        'status' => 'completed',
                        'expiry' => $expiry_time,
                        'payment_id' => $payment_id,
                        'service_type' => $payment_data['service_type']
                    ];
                    
                    // Regjistro edhe në log për debugging
                    error_log("Payment completed successfully for user $user_id: " . json_encode($_SESSION['video_payment']));
                }
            } else {
                $message = 'Ndodhi një gabim në përditësimin e statusit të pagesës. Ju lutemi kontaktoni administratorin.';
                error_log("Error updating payment status for payment_id $payment_id: " . $conn->error);
            }
        } else {
            $payment_status = 'failed';
            $message = 'Verifikimi i pagesës dështoi. Ju lutemi kontaktoni administratorin.';
            error_log("Payment verification failed for payment_id $payment_id with method $payment_method");
        }
    }
    
    // Nëse kemi një dhomë të specifikuar, ridrejtojmë në video thirrje
    if (!empty($room) && $payment_status === 'completed') {
        error_log("Payment completed for room: $room. Redirecting to video call.");
        header("Location: video_call.php?room=" . urlencode($room));
        exit;
    }
} elseif (isset($_GET['payment']) && $_GET['payment'] === 'cancel') {
    $payment_status = 'cancelled';
    $message = 'Pagesa u anulua. Ju mund të provoni përsëri ose të kontaktoni administratorin për ndihmë.';
    
    // Regjistrojmë anulimin e pagesës nëse kemi payment_id
    if (isset($_GET['payment_id']) && !empty($_GET['payment_id'])) {
        $payment_id = $_GET['payment_id'];
        
        try {
            $conn = connectToDatabase();
            $update_query = "UPDATE payments SET status = 'cancelled' WHERE payment_id = ? AND user_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ss", $payment_id, $user_id);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Error updating cancelled payment status: " . $e->getMessage());
        }
    }
} elseif ($service_type === 'video' && !isset($_GET['payment'])) {
    // Shfaqim formën e përzgjedhjes së metodës së pagesës
}

// Gjenerimi i ID të pagesës për një pagesë të re
if (($service_type === 'video' && empty($_GET['payment'])) || $renew) {
    $payment_id = 'NOTER_' . uniqid() . '_' . substr(md5($user_id . time()), 0, 8);
    
    try {
        // Përdorim funksionin connectToDatabase() nga paysera_pay.php
        $conn = connectToDatabase();
        
        // Regjistrojmë pagesën e re në databazë
        $insert_query = "INSERT INTO payments (payment_id, user_id, amount, currency, service_type, status, creation_date) 
                        VALUES (?, ?, ?, 'EUR', 'video_consultation', 'pending', NOW())";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("ssd", $payment_id, $user_id, $amount);
        $stmt->execute();
        
        error_log("Payment record created successfully: $payment_id for user $user_id");
    } catch (Exception $e) {
        error_log("Error creating payment record: " . $e->getMessage());
        // Krijojmë një lidhje alternative me databazën nëse ka probleme
        include_once 'db_connection.php';
        // $conn variabla duhet të jetë e disponueshme nga db_connection.php
    }
    
    // Nëse kemi një notary_id dhe room, krijojmë një video call të menjëhershme
    if (!empty($notary_id) && !empty($room)) {
        try {
            // Sigurohemi që kemi lidhje me databazën
            if (!isset($conn) || $conn->connect_error) {
                $conn = connectToDatabase();
            }
            
            // Kontrollojmë nëse ekziston tabela video_calls
            $tableCheckQuery = "SHOW TABLES LIKE 'video_calls'";
            $result = $conn->query($tableCheckQuery);
            
            if ($result->num_rows === 0) {
                // Krijojmë tabelën nëse nuk ekziston
                $createTableQuery = "CREATE TABLE video_calls (
                    id INT(11) AUTO_INCREMENT PRIMARY KEY,
                    user_id VARCHAR(255) NOT NULL,
                    notary_id VARCHAR(255) NOT NULL,
                    call_datetime DATETIME NOT NULL,
                    room_id VARCHAR(255) NOT NULL,
                    subject VARCHAR(255) NOT NULL,
                    status VARCHAR(20) NOT NULL,
                    notification_status VARCHAR(20) DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX (user_id),
                    INDEX (notary_id),
                    INDEX (room_id),
                    INDEX (status)
                )";
                
                $conn->query($createTableQuery);
                error_log("Created video_calls table in database");
            }
            
            // Regjistrojmë thirrjen
            $insert_call = "INSERT INTO video_calls (user_id, notary_id, call_datetime, room_id, subject, status) 
                          VALUES (?, ?, NOW(), ?, 'Video thirrje e menjëhershme', 'pending')";
            $call_stmt = $conn->prepare($insert_call);
            $call_stmt->bind_param("sss", $user_id, $notary_id, $room);
            $call_stmt->execute();
            
            error_log("Video call created successfully: Room ID: $room, User: $user_id, Notary: $notary_id");
        } catch (Exception $e) {
            error_log("Error creating video call: " . $e->getMessage());
        }
    }
}

// Integrim me API të bankave - linku i pagesës
if ($payment_status === 'pending') {
    // Integrimi me Paysera
    $paysera_payment_url = "https://www.paysera.com/pay?data=" . base64_encode(json_encode([
        'amount' => $amount,
        'currency' => 'EUR',
        'description' => 'Konsulencë video me noter - 30 minuta',
        'orderid' => $payment_id ?? 'ORDER_' . time(),
        'accepturl' => 'https://' . $_SERVER['HTTP_HOST'] . '/payment_confirmation.php?payment=success&payment_id=' . ($payment_id ?? '') . '&room=' . urlencode($room),
        'cancelurl' => 'https://' . $_SERVER['HTTP_HOST'] . '/payment_confirmation.php?payment=cancel'
    ])) . "&sign=" . md5('PAYSERA_PROJECT_PASSWORD_HERE'); // Zëvendësoni me fjalëkalimin tuaj të projektit Paysera
    
    // Integrimi me Raiffeisen Bank (simulim)
    $raiffeisen_payment_url = "https://e-banking.raiffeisen.al/payment?orderid=" . ($payment_id ?? 'ORDER_' . time()) . 
                              "&amount=" . $amount . "&description=KonsulenceVideoNoteria&return=" . 
                              urlencode('https://' . $_SERVER['HTTP_HOST'] . '/payment_confirmation.php?payment=success&payment_id=' . ($payment_id ?? '') . '&room=' . urlencode($room));
    
    // Integrimi me BKT (simulim)
    $bkt_payment_url = "https://e-banking.bkt.com.al/payments?reference=" . ($payment_id ?? 'ORDER_' . time()) . 
                      "&amount=" . $amount . "&description=KonsulenceVideoNoteria&success=" . 
                      urlencode('https://' . $_SERVER['HTTP_HOST'] . '/payment_confirmation.php?payment=success&payment_id=' . ($payment_id ?? '') . '&room=' . urlencode($room));
}

// Kontrollojmë nëse është një post submission nga forma e pagesës
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
    
    if ($selected_method === 'paysera') {
        header("Location: " . $paysera_payment_url);
        exit;
    } elseif ($selected_method === 'raiffeisen') {
        header("Location: " . $raiffeisen_payment_url);
        exit;
    } elseif ($selected_method === 'bkt') {
        header("Location: " . $bkt_payment_url);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <title>Noteria | Konfirmimi i Pagesës për Video Konsulencë</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css?family=Montserrat:700,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            font-family: 'Montserrat', Arial, sans-serif;
            color: #fff;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 2.8rem;
            font-weight: 700;
            color: #ffeb3b;
            letter-spacing: 3px;
            margin-bottom: 10px;
            text-shadow: 0 0 15px rgba(255, 235, 59, 0.7);
        }
        .subtitle {
            font-size: 1.3rem;
            color: rgba(255, 255, 255, 0.8);
        }
        .payment-card {
            background: rgba(255,255,255,0.1);
            box-shadow: 0 8px 32px 0 rgba(0,0,0,0.37);
            backdrop-filter: blur(10px);
            border-radius: 18px;
            border: 1px solid rgba(255,255,255,0.18);
            padding: 30px;
            margin-bottom: 30px;
        }
        .payment-status {
            text-align: center;
            font-size: 1.4rem;
            margin-bottom: 20px;
            font-weight: 700;
        }
        .status-completed {
            color: #66bb6a;
        }
        .status-pending {
            color: #ffc107;
        }
        .status-cancelled {
            color: #f44336;
        }
        .payment-message {
            text-align: center;
            margin-bottom: 25px;
            font-size: 1.1rem;
        }
        .payment-details {
            background: rgba(0,0,0,0.2);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .detail-label {
            font-weight: 600;
            color: rgba(255,255,255,0.7);
        }
        .detail-value {
            font-weight: 700;
            color: #fff;
        }
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-block;
            padding: 12px 25px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: linear-gradient(45deg, #1976d2, #42a5f5);
            color: white;
            box-shadow: 0 5px 15px rgba(33, 150, 243, 0.4);
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(33, 150, 243, 0.6);
        }
        .btn-success {
            background: linear-gradient(45deg, #43a047, #66bb6a);
            color: white;
            box-shadow: 0 5px 15px rgba(67, 160, 71, 0.4);
        }
        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(67, 160, 71, 0.6);
        }
        .payment-methods {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 30px;
        }
        .payment-method-card {
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            padding: 15px;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        .payment-method-card:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        .payment-method-card.selected {
            border-color: #42a5f5;
            box-shadow: 0 0 15px rgba(33, 150, 243, 0.6);
        }
        .payment-logo {
            width: 80px;
            height: 50px;
            background: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            padding: 5px;
        }
        .payment-logo img {
            max-width: 100%;
            max-height: 100%;
        }
        .payment-method-info {
            flex: 1;
        }
        .payment-method-name {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 4px;
        }
        .payment-method-desc {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.7);
        }
        .payment-form {
            margin-top: 25px;
        }
        .form-title {
            font-size: 1.2rem;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
        }
        .spinner {
            border: 5px solid rgba(255,255,255,0.1);
            border-top: 5px solid #42a5f5;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 2s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .info-box {
            background: rgba(33, 150, 243, 0.1);
            border: 1px solid rgba(33, 150, 243, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            text-align: center;
            font-size: 0.9rem;
            color: #81d4fa;
        }
        .info-box i {
            margin-right: 5px;
            color: #42a5f5;
        }
        .submit-btn {
            background: linear-gradient(45deg, #43a047, #66bb6a);
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(67, 160, 71, 0.4);
            margin-top: 20px;
            width: 100%;
        }
        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(67, 160, 71, 0.6);
        }
        @media (max-width: 600px) {
            .logo { font-size: 2rem; }
            .subtitle { font-size: 1.1rem; }
            .action-buttons { flex-direction: column; }
            .btn { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">NOTERIA</div>
            <div class="subtitle">Konfirmimi i Pagesës për Video Konsulencë</div>
        </div>
        
        <div class="payment-card">
            <?php if ($payment_status === 'completed'): ?>
                <div class="payment-status status-completed">
                    <i class="fas fa-check-circle"></i> Pagesa u Konfirmua
                </div>
                <div class="payment-message"><?php echo $message; ?></div>
                
                <?php if (!empty($room)): ?>
                    <div class="spinner"></div>
                    <div class="payment-message">Po ju ridrejtojmë tek video konsulenca...</div>
                <?php endif; ?>
                
                <div class="payment-details">
                    <div class="detail-row">
                        <span class="detail-label">Shërbimi:</span>
                        <span class="detail-value">Konsulencë video me noter</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Kohëzgjatja:</span>
                        <span class="detail-value">30 minuta</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Çmimi:</span>
                        <span class="detail-value"><?php echo number_format($amount, 2); ?> EUR</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Statusi:</span>
                        <span class="detail-value">Paguar</span>
                    </div>
                </div>
                
            <?php elseif ($payment_status === 'cancelled'): ?>
                <div class="payment-status status-cancelled">
                    <i class="fas fa-times-circle"></i> Pagesa u Anulua
                </div>
                <div class="payment-message"><?php echo $message; ?></div>
                
            <?php else: ?>
                <div class="payment-status status-pending">
                    <i class="fas fa-clock"></i> Në pritje të pagesës
                </div>
                <div class="payment-message">Ju lutemi zgjidhni metodën e pagesës për të vazhduar me konsulencën video.</div>
                
                <div class="payment-details">
                    <div class="detail-row">
                        <span class="detail-label">Shërbimi:</span>
                        <span class="detail-value">Konsulencë video me noter</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Kohëzgjatja:</span>
                        <span class="detail-value">30 minuta</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Çmimi:</span>
                        <span class="detail-value"><?php echo number_format($amount, 2); ?> EUR</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Numri i porosisë:</span>
                        <span class="detail-value"><?php echo isset($payment_id) ? $payment_id : 'N/A'; ?></span>
                    </div>
                </div>
                
                <form method="post" class="payment-form">
                    <div class="form-title">Zgjidhni metodën e pagesës:</div>
                    
                    <div class="payment-methods">
                        <div class="payment-method-card">
                            <input type="radio" name="payment_method" value="paysera" id="paysera" style="display: none;" checked>
                            <label for="paysera" style="display: flex; width: 100%; cursor: pointer; align-items: center;">
                                <div class="payment-logo">
                                    <img src="https://www.paysera.com/img/logo.png" alt="Paysera">
                                </div>
                                <div class="payment-method-info">
                                    <div class="payment-method-name">Paysera</div>
                                    <div class="payment-method-desc">Paguaj me kartelë krediti, transfertë bankare ose Paysera wallet</div>
                                </div>
                            </label>
                        </div>
                        
                        <div class="payment-method-card">
                            <input type="radio" name="payment_method" value="raiffeisen" id="raiffeisen" style="display: none;">
                            <label for="raiffeisen" style="display: flex; width: 100%; cursor: pointer; align-items: center;">
                                <div class="payment-logo">
                                    <img src="https://www.raiffeisen.al/sites/default/files/raiffeisen_logo.png" alt="Raiffeisen Bank">
                                </div>
                                <div class="payment-method-info">
                                    <div class="payment-method-name">Raiffeisen Bank</div>
                                    <div class="payment-method-desc">Paguaj direkt përmes llogarisë bankare Raiffeisen</div>
                                </div>
                            </label>
                        </div>
                        
                        <div class="payment-method-card">
                            <input type="radio" name="payment_method" value="bkt" id="bkt" style="display: none;">
                            <label for="bkt" style="display: flex; width: 100%; cursor: pointer; align-items: center;">
                                <div class="payment-logo">
                                    <img src="https://www.bkt.com.al/bktWeb/images/logo/logoTop.png" alt="BKT">
                                </div>
                                <div class="payment-method-info">
                                    <div class="payment-method-name">BKT</div>
                                    <div class="payment-method-desc">Paguaj direkt përmes llogarisë bankare BKT</div>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-lock" style="margin-right: 8px;"></i>
                        Paguaj tani <?php echo number_format($amount, 2); ?> EUR
                    </button>
                </form>
                
                <div class="info-box">
                    <i class="fas fa-shield-alt"></i>
                    Pagesat procesohen në mënyrë të sigurt përmes partnerëve tanë të licencuar bankarë.
                    Të gjitha transaksionet janë të enkriptuara dhe të mbrojtura sipas standardeve më të larta të sigurisë.
                </div>
            <?php endif; ?>
        </div>
        
        <div class="action-buttons">
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-home"></i> Kthehu te Kryefaqja
            </a>
            
            <?php if ($payment_status === 'completed' && !empty($room)): ?>
                <a href="video_call.php?room=<?php echo urlencode($room); ?>" class="btn btn-success">
                    <i class="fas fa-video"></i> Fillo Konsulencën
                </a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Selektimi i metodës së pagesës
            const paymentCards = document.querySelectorAll('.payment-method-card');
            const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
            
            paymentCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Gjeji radio brenda kartës
                    const radio = this.querySelector('input[type="radio"]');
                    if (radio) {
                        // Selekto radion
                        radio.checked = true;
                        
                        // Hiq klasën "selected" nga të gjitha kartat
                        paymentCards.forEach(c => c.classList.remove('selected'));
                        
                        // Shto klasën "selected" te karta e klikuar
                        this.classList.add('selected');
                    }
                });
            });
            
            // Selekto kartën e parë për default
            if (paymentCards.length > 0 && paymentRadios.length > 0) {
                paymentCards[0].classList.add('selected');
                paymentRadios[0].checked = true;
            }
        });
    </script>
</body>
</html>