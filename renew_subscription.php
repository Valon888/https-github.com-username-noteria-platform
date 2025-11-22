<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Define logPayment if not already included
if (!function_exists('logPayment')) {
    function logPayment($pdo, $zyra_id, $amount, $description, $payment_method) {
        $stmt = $pdo->prepare("INSERT INTO payment_logs (zyra_id, amount, description, payment_method, log_date) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)");
        $stmt->execute([$zyra_id, $amount, $description, $payment_method]);
    }
}

require_once 'includes/payment_functions.php';

// Kontrollo login dhe rolin
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$roli = $_SESSION['roli'] ?? '';

// Vetëm zyrat noteriale mund të aksesojnë këtë faqe
if ($roli !== 'zyra') {
    header('Location: dashboard.php');
    exit;
}

$zyra_id = $_SESSION['zyra_id'] ?? null;

// Kontrollo nëse ka zyra_id
if (!$zyra_id) {
    header('Location: dashboard.php?error=nozyra');
    exit;
}

// Merr të dhënat e abonimit aktual
$stmt = $pdo->prepare("SELECT 
    s.id, 
    s.start_date, 
    s.expiry_date, 
    s.status, 
    s.payment_status,
    DATEDIFF(s.expiry_date, CURDATE()) as days_left
    FROM subscription s
    WHERE s.zyra_id = ? 
    ORDER BY s.expiry_date DESC 
    LIMIT 1");
$stmt->execute([$zyra_id]);
$subscription = $stmt->fetch();

// Llogarit datën e re të skadimit
$newStartDate = null;
$newExpiryDate = null;

if ($subscription && $subscription['status'] === 'active') {
    // Nëse ka aboniment aktiv, vazhdo nga data e skadimit aktual
    $newStartDate = $subscription['expiry_date'];
    $newExpiryDate = date('Y-m-d', strtotime($subscription['expiry_date'] . ' +1 month'));
} else {
    // Nëse nuk ka aboniment aktiv, fillo nga sot
    $newStartDate = date('Y-m-d');
    $newExpiryDate = date('Y-m-d', strtotime('+1 month'));
}

// Vepro me kërkesën e pagesës
$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['renew'])) {
    // Simulojmë pagesën e suksesshme (në një sistem real do të integrohej me gateway pagese)
    // Krijo regjistrim të ri ose përditëso ekzistuesin
    
    if ($subscription && $subscription['status'] === 'active') {
        // Përditëso abonimin ekzistues
        $stmt = $pdo->prepare("UPDATE subscription SET 
            expiry_date = ?,
            payment_status = 'paid',
            payment_date = CURRENT_TIMESTAMP
            WHERE id = ?");
        $stmt->execute([$newExpiryDate, $subscription['id']]);
    } else {
        // Krijo aboniment të ri
        $stmt = $pdo->prepare("INSERT INTO subscription 
            (zyra_id, start_date, expiry_date, status, payment_status, payment_date) 
            VALUES (?, ?, ?, 'active', 'paid', CURRENT_TIMESTAMP)");
        $stmt->execute([$zyra_id, $newStartDate, $newExpiryDate]);
    }
    
    // Regjistro pagesën
    $stmt = $pdo->prepare("INSERT INTO payments 
        (zyra_id, amount, payment_date, payment_method, description, status) 
        VALUES (?, 150.00, CURRENT_TIMESTAMP, ?, 'Pagesa për abonimin mujor', 'completed')");
    $stmt->execute([$zyra_id, $_POST['payment_method']]);
    
    $successMessage = "Abonimi juaj u rinovua me sukses deri më " . date('d.m.Y', strtotime($newExpiryDate));
    
    // Logu i pagesës
    logPayment($pdo, $zyra_id, 150.00, 'Rinovim abonimi', $_POST['payment_method']);
    
    // Në një sistem real do të dërgohej email konfirmimi
    // sendPaymentConfirmation($email, $newExpiryDate);
}
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rinovimi i Abonimit - Noteria</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .subscription-container {
            max-width: 800px;
            margin: 40px auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 30px;
        }
        
        .subscription-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .subscription-header h1 {
            font-size: 1.8rem;
            color: #184fa3;
            margin-bottom: 10px;
        }
        
        .subscription-info {
            background: #f0f7ff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .subscription-details {
            margin: 20px 0;
            display: flex;
            flex-wrap: wrap;
        }
        
        .detail-item {
            width: 50%;
            padding: 10px 0;
            display: flex;
            align-items: center;
        }
        
        .detail-item i {
            color: #2d6cdf;
            margin-right: 10px;
            min-width: 24px;
        }
        
        .payment-methods {
            margin: 30px 0;
        }
        
        .payment-methods h2 {
            font-size: 1.3rem;
            margin-bottom: 20px;
            color: #333;
        }
        
        .payment-options {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .payment-option {
            flex: 1;
            min-width: 150px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .payment-option.selected {
            border-color: #2d6cdf;
            background: #f0f7ff;
        }
        
        .payment-option img {
            height: 40px;
            margin-bottom: 10px;
        }
        
        .payment-button {
            background: linear-gradient(90deg, #2d6cdf 0%, #184fa3 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 15px 30px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }
        
        .payment-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(45,108,223,0.25);
        }
        
        .success-message {
            background: #dcfce7;
            border-left: 5px solid #16a34a;
            color: #166534;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .error-message {
            background: #fee2e2;
            border-left: 5px solid #dc2626;
            color: #991b1b;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #2d6cdf;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link i {
            margin-right: 6px;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .detail-item {
                width: 100%;
            }
            
            .payment-options {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="subscription-container">
        <div class="subscription-header">
            <h1><i class="fas fa-sync-alt"></i> Rinovimi i Abonimit</h1>
            <p>Vazhdoni abonimin tuaj për të përdorur shërbimet e platformës Noteria</p>
        </div>
        
        <?php if (!empty($errorMessage)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i> <?php echo $errorMessage; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($successMessage)): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i> <?php echo $successMessage; ?>
            <p style="margin-top: 10px;">Ju faleminderit për vazhdimin e abonimit. Ju mund të ktheheni në <a href="dashboard.php">panelin kryesor</a>.</p>
        </div>
        <?php else: ?>
        
        <div class="subscription-info">
            <h2>Detajet e Abonimit</h2>
            <div class="subscription-details">
                <div class="detail-item">
                    <i class="far fa-calendar"></i>
                    <div>
                        <strong>Data e fillimit:</strong><br>
                        <?php echo date('d.m.Y', strtotime($newStartDate)); ?>
                    </div>
                </div>
                <div class="detail-item">
                    <i class="far fa-calendar-check"></i>
                    <div>
                        <strong>Data e skadimit:</strong><br>
                        <?php echo date('d.m.Y', strtotime($newExpiryDate)); ?>
                    </div>
                </div>
                <div class="detail-item">
                    <i class="fas fa-tag"></i>
                    <div>
                        <strong>Çmimi:</strong><br>
                        150.00 €
                    </div>
                </div>
                <div class="detail-item">
                    <i class="fas fa-clock"></i>
                    <div>
                        <strong>Periudha:</strong><br>
                        1 muaj
                    </div>
                </div>
            </div>
            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #cce0ff;">
                <p><i class="fas fa-info-circle" style="color: #2d6cdf;"></i> Abonimi juaj do të rinovohet për 1 muaj nga <?php echo $subscription && $subscription['status'] === 'active' ? 'data e skadimit aktual' : 'data e sotme'; ?>.</p>
            </div>
        </div>
        
        <form method="post" id="payment-form">
            <div class="payment-methods">
                <h2>Zgjidhni metodën e pagesës</h2>
                <div class="payment-options">
                    <div class="payment-option" data-method="credit_card">
                        <img src="images/credit-card.png" alt="Kartelë krediti">
                        <div>Kartelë krediti</div>
                    </div>
                    <div class="payment-option" data-method="bank_transfer">
                        <img src="images/bank-transfer.png" alt="Transfertë bankare">
                        <div>Transfertë bankare</div>
                    </div>
                    <div class="payment-option" data-method="paypal">
                        <img src="images/paypal.png" alt="PayPal">
                        <div>PayPal</div>
                    </div>
                </div>
                <input type="hidden" name="payment_method" id="payment_method" value="credit_card">
                
                <button type="submit" name="renew" class="payment-button">
                    <i class="fas fa-lock"></i> Paguaj tani 150.00 €
                </button>
            </div>
        </form>
        
        <?php endif; ?>
        
        <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Kthehu në panel</a>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Përzgjedhja e metodës së pagesës
        const paymentOptions = document.querySelectorAll('.payment-option');
        const paymentMethodInput = document.getElementById('payment_method');
        
        paymentOptions.forEach(option => {
            option.addEventListener('click', function() {
                // Hiq klasën selected nga të gjitha opsionet
                paymentOptions.forEach(opt => opt.classList.remove('selected'));
                
                // Shto klasën selected te opsioni i klikuar
                this.classList.add('selected');
                
                // Përditëso vlerën e fushës së fshehur
                paymentMethodInput.value = this.getAttribute('data-method');
            });
        });
        
        // Selekto opsionin e parë si default
        if (paymentOptions.length > 0) {
            paymentOptions[0].classList.add('selected');
        }
    });
    </script>
</body>
</html>