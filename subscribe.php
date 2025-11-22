<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
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

// Merr të dhënat e zyrës
$stmt = $pdo->prepare("SELECT emri, email, telefon, adresa FROM zyrat WHERE id = ?");
$stmt->execute([$zyra_id]);
$zyra = $stmt->fetch();

// Llogarit datën e fillimit dhe skadimit
$startDate = date('Y-m-d');
$expiryDate = date('Y-m-d', strtotime('+1 month'));

// Vepro me kërkesën e pagesës
$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscribe'])) {
    // Simulojmë pagesën e suksesshme (në një sistem real do të integrohej me gateway pagese)
    
    // Krijo abonimin
    $stmt = $pdo->prepare("INSERT INTO subscription 
        (zyra_id, start_date, expiry_date, status, payment_status, payment_date) 
        VALUES (?, ?, ?, 'active', 'paid', CURRENT_TIMESTAMP)");
    $stmt->execute([$zyra_id, $startDate, $expiryDate]);
    
    // Regjistro pagesën
    $stmt = $pdo->prepare("INSERT INTO payments 
        (zyra_id, amount, payment_date, payment_method, description, status) 
        VALUES (?, 150.00, CURRENT_TIMESTAMP, ?, 'Pagesa për abonimin mujor', 'completed')");
    $stmt->execute([$zyra_id, $_POST['payment_method']]);
    
    $successMessage = "Abonimi juaj u aktivizua me sukses deri më " . date('d.m.Y', strtotime($expiryDate));
    
    // Dërgimi i faturës elektronike
    if (isset($zyra['email']) && !empty($zyra['email'])) {
        // Në një sistem real do të gjenerohej fatura dhe do të dërgohej me email
        // generateElectronicInvoice($zyra_id, 150.00, 'Abonement mujor', $startDate, $expiryDate);
    }
}
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abonohu në Platformë - Noteria</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .subscription-container {
            max-width: 900px;
            margin: 40px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 25px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .subscription-header {
            background: linear-gradient(135deg, #2d6cdf 0%, #184fa3 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .subscription-header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .subscription-content {
            padding: 30px;
        }
        
        .plan-details {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .plan-feature {
            flex: 1;
            min-width: 250px;
            background: #f8fafc;
            border-radius: 10px;
            padding: 20px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .plan-feature i {
            font-size: 1.5rem;
            color: #2d6cdf;
            margin-top: 3px;
        }
        
        .feature-content h3 {
            margin-top: 0;
            margin-bottom: 8px;
            color: #1e293b;
        }
        
        .feature-content p {
            margin: 0;
            color: #64748b;
            line-height: 1.5;
        }
        
        .price-section {
            background: #f0f7ff;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .price-amount {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
        }
        
        .price-period {
            color: #64748b;
            font-size: 1.1rem;
            margin-top: 5px;
        }
        
        .subscription-form {
            margin-top: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #334155;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 1rem;
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
        
        .subscription-footer {
            padding: 20px 30px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 0.9rem;
            text-align: center;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .plan-feature {
                min-width: 100%;
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
            <h1><i class="fas fa-star"></i> Abonohu në Platformën Noteria</h1>
            <p>Qasje e plotë në të gjitha funksionet për zyrën tuaj noteriale</p>
        </div>
        
        <div class="subscription-content">
            <?php if (!empty($errorMessage)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $errorMessage; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($successMessage)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> <?php echo $successMessage; ?>
                <p style="margin-top: 10px;">Ju faleminderit për abonimin. Ju mund të ktheheni në <a href="dashboard.php">panelin kryesor</a>.</p>
            </div>
            <?php else: ?>
            
            <div class="price-section">
                <div class="price-amount">150 €</div>
                <div class="price-period">për çdo muaj</div>
            </div>
            
            <div class="plan-details">
                <div class="plan-feature">
                    <i class="fas fa-check-circle"></i>
                    <div class="feature-content">
                        <h3>Qasje e plotë</h3>
                        <p>Të gjitha funksionet e platformës për zyrën tuaj noteriale pa kufizime</p>
                    </div>
                </div>
                <div class="plan-feature">
                    <i class="fas fa-calendar"></i>
                    <div class="feature-content">
                        <h3>Kohëzgjatja 1 muaj</h3>
                        <p>Nga <?php echo date('d.m.Y'); ?> deri më <?php echo date('d.m.Y', strtotime('+1 month')); ?></p>
                    </div>
                </div>
                <div class="plan-feature">
                    <i class="fas fa-sync-alt"></i>
                    <div class="feature-content">
                        <h3>Rinovim i thjeshtë</h3>
                        <p>Mundësi për të rinovuar automatikisht ose manualisht para skadimit</p>
                    </div>
                </div>
                <div class="plan-feature">
                    <i class="fas fa-headset"></i>
                    <div class="feature-content">
                        <h3>Mbështetje teknike</h3>
                        <p>Mbështetje prioritare për të gjitha nevojat tuaja teknike</p>
                    </div>
                </div>
            </div>
            
            <form method="post" id="subscription-form">
                <div class="form-group">
                    <label for="zyra">Zyra Noteriale:</label>
                    <input type="text" id="zyra" name="zyra" value="<?php echo htmlspecialchars($zyra['emri'] ?? ''); ?>" readonly>
                </div>
                
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
                    
                    <button type="submit" name="subscribe" class="payment-button">
                        <i class="fas fa-lock"></i> Paguaj tani 150.00 €
                    </button>
                </div>
            </form>
            
            <?php endif; ?>
            
            <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Kthehu në panel</a>
        </div>
        
        <div class="subscription-footer">
            <p>Për çdo pyetje rreth abonimit, kontaktoni mbështetjen tonë teknike në <strong>support@noteria.al</strong></p>
        </div>
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