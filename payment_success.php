<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'db_connection.php';

$user_id = $_SESSION['user_id'];
$transaction_id = $_GET['txn'] ?? null;

if (!$transaction_id) {
    header("Location: dashboard.php");
    exit();
}

// Get payment details
$stmt = $conn->prepare("
    SELECT p.*, u.email, u.emri, u.mbiemri
    FROM payments p
    JOIN users u ON p.user_id = u.id
    WHERE p.intent_id = ? AND p.user_id = ?
");
$stmt->bind_param("ss", $transaction_id, $user_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment) {
    header("Location: dashboard.php");
    exit();
}

// Get related service (reservation, subscription, etc)
$service_type = $payment['service_type'] ?? 'generic';
$service_id = $payment['service_id'] ?? null;

?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagesa u Pranua - Noteria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .success-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .success-header {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .success-icon {
            font-size: 4rem;
            margin-bottom: 16px;
            animation: scaleIn 0.6s ease-out 0.2s both;
        }
        
        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
        
        .success-header h1 {
            font-size: 2rem;
            margin-bottom: 8px;
        }
        
        .success-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .success-content {
            padding: 40px;
        }
        
        .receipt {
            background: #f5f7fa;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        
        .receipt-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 16px;
            align-items: center;
        }
        
        .receipt-row.total {
            border-top: 2px solid #ddd;
            padding-top: 16px;
            font-weight: 700;
            font-size: 1.2rem;
            color: #27ae60;
        }
        
        .receipt-label {
            color: #666;
            font-weight: 600;
        }
        
        .receipt-value {
            font-weight: 600;
            color: #333;
        }
        
        .details-section {
            margin-bottom: 24px;
        }
        
        .details-section h3 {
            color: #333;
            margin-bottom: 16px;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .detail-item {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
            padding: 12px;
            background: #f9f9f9;
            border-left: 4px solid #667eea;
            border-radius: 4px;
        }
        
        .detail-icon {
            color: #667eea;
            width: 24px;
            text-align: center;
        }
        
        .detail-text {
            flex: 1;
        }
        
        .detail-text strong {
            display: block;
            color: #333;
        }
        
        .detail-text span {
            color: #666;
            font-size: 0.9rem;
        }
        
        .actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 24px;
        }
        
        button, a {
            padding: 14px;
            border: none;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #ddd;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #ccc;
        }
        
        .info-box {
            background: #d4edda;
            color: #155724;
            padding: 16px;
            border-radius: 6px;
            border-left: 4px solid #27ae60;
        }
        
        .print-section {
            text-align: center;
            padding-top: 24px;
            border-top: 1px solid #ddd;
        }
        
        .print-section button {
            background: #f5f5f5;
            color: #333;
            width: 100%;
        }
        
        @media print {
            body {
                background: white;
            }
            .success-container {
                box-shadow: none;
            }
            .actions, .print-section {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-header">
            <div class="success-icon">✓</div>
            <h1>Pagesa u Pranua me Sukses!</h1>
            <p>Transaksioni juaj u përpunua me sukses</p>
        </div>
        
        <div class="success-content">
            <!-- Receipt -->
            <div class="receipt">
                <div class="receipt-row">
                    <span class="receipt-label">Sasia:</span>
                    <span class="receipt-value"><?php echo htmlspecialchars($payment['amount']); ?> <?php echo htmlspecialchars($payment['currency']); ?></span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Metoda:</span>
                    <span class="receipt-value"><?php echo htmlspecialchars(ucfirst($payment['payment_method'])); ?></span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Data:</span>
                    <span class="receipt-value"><?php echo date('d.m.Y H:i', strtotime($payment['created_at'])); ?></span>
                </div>
                <div class="receipt-row total">
                    <span>Gjithsej:</span>
                    <span><?php echo htmlspecialchars($payment['amount']); ?> <?php echo htmlspecialchars($payment['currency']); ?></span>
                </div>
            </div>
            
            <!-- Transaction Details -->
            <div class="details-section">
                <h3><i class="fas fa-info-circle"></i> Detajet e Transaksionit</h3>
                
                <div class="detail-item">
                    <div class="detail-icon"><i class="fas fa-ticket-alt"></i></div>
                    <div class="detail-text">
                        <strong>ID Referimi:</strong>
                        <span><?php echo htmlspecialchars($payment['intent_id']); ?></span>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="detail-text">
                        <strong>Statusi:</strong>
                        <span><?php echo ucfirst($payment['status']); ?></span>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-icon"><i class="fas fa-envelope"></i></div>
                    <div class="detail-text">
                        <strong>Konfirmimi po dërgohet në:</strong>
                        <span><?php echo htmlspecialchars($payment['email']); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Info Box -->
            <div class="info-box">
                <i class="fas fa-check"></i> Pagesa juaj u pranua. Mund ta kontrolloni statusin në profilein tuaj.
            </div>
            
            <!-- Actions -->
            <div class="actions" style="margin-top: 24px;">
                <a href="dashboard.php" class="btn-secondary">
                    <i class="fas fa-home"></i> Në Dashboard
                </a>
                <button onclick="window.print()" class="btn-primary">
                    <i class="fas fa-print"></i> Printo Kuponin
                </button>
            </div>
            
            <!-- What's Next -->
            <div class="details-section">
                <h3><i class="fas fa-arrow-right"></i> Hapi i Ardhshëm</h3>
                <div class="detail-item">
                    <div class="detail-icon"><i class="fas fa-tasks"></i></div>
                    <div class="detail-text">
                        <strong>Shërbimi juaj do të aktivizohet brenda 5 minutash</strong>
                        <span>Nëse nuk aktivizohet, kontaktoni suportin tonë</span>
                    </div>
                </div>
            </div>
            
            <!-- Support -->
            <div style="text-align: center; padding-top: 16px; border-top: 1px solid #ddd; margin-top: 16px;">
                <p style="color: #666; font-size: 0.9rem;">
                    Keni pyetje? <a href="mailto:support@noteria.al" style="color: #667eea; text-decoration: none;">Kontaktoni suportin</a>
                </p>
            </div>
        </div>
    </div>
    
    <script>
        // Redirect after 30 seconds if user doesn't click
        setTimeout(() => {
            if (!sessionStorage.getItem('paymentSuccessViewed')) {
                sessionStorage.setItem('paymentSuccessViewed', 'true');
            }
        }, 30000);
    </script>
</body>
</html>
