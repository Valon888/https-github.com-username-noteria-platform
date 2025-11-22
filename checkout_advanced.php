<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'db_connection.php';

$user_id = $_SESSION['user_id'];
$amount = $_GET['amount'] ?? 0;
$currency = $_GET['currency'] ?? 'EUR';
$service = $_GET['service'] ?? 'generic';

// Get user info
$stmt = $conn->prepare("SELECT email, emri, mbiemri FROM users WHERE id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Methods - Noteria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://js.stripe.com/v3/"></script>
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
            padding: 20px;
        }
        
        .checkout-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .content {
            padding: 40px;
        }
        
        .order-summary {
            background: #f5f7fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        
        .summary-row.total {
            border-top: 2px solid #ddd;
            padding-top: 12px;
            font-weight: 700;
            font-size: 1.2rem;
        }
        
        .payment-methods {
            margin-bottom: 30px;
        }
        
        .payment-methods h3 {
            margin-bottom: 16px;
            color: #333;
        }
        
        .method-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .method-btn {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
            color: #333;
            text-decoration: none;
        }
        
        .method-btn:hover {
            border-color: #667eea;
            background: #f5f7fa;
        }
        
        .method-btn.active {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .method-icon {
            font-size: 2rem;
            margin-bottom: 8px;
            display: block;
        }
        
        .method-name {
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        input[type="text"],
        input[type="email"],
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: inherit;
            font-size: 0.95rem;
        }
        
        input:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .card-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        
        #card-element {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: white;
        }
        
        .button-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        
        button {
            padding: 14px;
            border: none;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-pay {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        }
        
        .btn-cancel {
            background: #ddd;
            color: #333;
        }
        
        .btn-cancel:hover {
            background: #ccc;
        }
        
        .security-info {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 6px;
            margin-top: 20px;
            font-size: 0.9rem;
        }
        
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <div class="header">
            <h1><i class="fas fa-credit-card"></i> Pagesa</h1>
            <p>Zgjidh metodën e pagesës tuaj</p>
        </div>
        
        <div class="content">
            <!-- Order Summary -->
            <div class="order-summary">
                <div class="summary-row">
                    <span>Shërbimi:</span>
                    <span><?php echo htmlspecialchars(ucfirst($service)); ?></span>
                </div>
                <div class="summary-row">
                    <span>Sasia:</span>
                    <span><?php echo htmlspecialchars($amount); ?> <?php echo htmlspecialchars($currency); ?></span>
                </div>
                <div class="summary-row">
                    <span>Taksa (0%):</span>
                    <span>0 <?php echo htmlspecialchars($currency); ?></span>
                </div>
                <div class="summary-row total">
                    <span>Gjithsej:</span>
                    <span><?php echo htmlspecialchars($amount); ?> <?php echo htmlspecialchars($currency); ?></span>
                </div>
            </div>
            
            <!-- Payment Methods -->
            <form id="paymentForm" method="POST">
                <div class="payment-methods">
                    <h3><i class="fas fa-credit-card"></i> Zgjedh Metodën e Pagesës</h3>
                    
                    <div class="method-grid">
                        <button type="button" class="method-btn active" data-method="card">
                            <span class="method-icon"><i class="fas fa-credit-card"></i></span>
                            <span class="method-name">Kartë</span>
                        </button>
                        <button type="button" class="method-btn" data-method="apple-pay">
                            <span class="method-icon"><i class="fab fa-apple"></i></span>
                            <span class="method-name">Apple Pay</span>
                        </button>
                        <button type="button" class="method-btn" data-method="google-pay">
                            <span class="method-icon"><i class="fab fa-google"></i></span>
                            <span class="method-name">Google Pay</span>
                        </button>
                        <button type="button" class="method-btn" data-method="bank-transfer">
                            <span class="method-icon"><i class="fas fa-university"></i></span>
                            <span class="method-name">Transferim Bankar</span>
                        </button>
                    </div>
                </div>
                
                <!-- Card Payment -->
                <div id="card-payment" class="payment-section">
                    <h3>Detajet e Kartës</h3>
                    <div id="card-element"></div>
                    <div id="card-errors" style="color: #e74c3c; margin-top: 10px;"></div>
                </div>
                
                <!-- Apple Pay -->
                <div id="apple-pay" class="payment-section hidden">
                    <button type="button" id="apple-pay-button" style="width: 100%; background: #000; color: white; padding: 12px;">
                        Pay with Apple Pay
                    </button>
                </div>
                
                <!-- Google Pay -->
                <div id="google-pay" class="payment-section hidden">
                    <button type="button" id="google-pay-button" style="width: 100%; background: #fff; border: 1px solid #ddd; padding: 12px;">
                        <img src="https://www.gstatic.com/images/branding/product/1x/googleg_standard_color_128dp.png" height="20">
                        Pay with Google Pay
                    </button>
                </div>
                
                <!-- Bank Transfer -->
                <div id="bank-transfer" class="payment-section hidden">
                    <div class="form-group">
                        <label>IBAN:</label>
                        <input type="text" name="iban" placeholder="AL35 2121 1009 0000 0002 3569 8741" required>
                    </div>
                    <div class="form-group">
                        <label>Emri i Titullarit:</label>
                        <input type="text" name="account_holder" value="<?php echo htmlspecialchars($user['emri'] . ' ' . $user['mbiemri']); ?>" required>
                    </div>
                </div>
                
                <!-- Contact Info -->
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                
                <!-- Buttons -->
                <div class="button-group">
                    <button type="button" class="btn-cancel" onclick="history.back();">
                        <i class="fas fa-arrow-left"></i> Kthehu
                    </button>
                    <button type="submit" class="btn-pay">
                        <i class="fas fa-lock"></i> Pageso Tani
                    </button>
                </div>
                
                <div class="security-info">
                    <i class="fas fa-shield-alt"></i> Pagesa juaj është e sigurt dhe e enkriptuar me SSL 256-bit
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Payment method switching
        document.querySelectorAll('.method-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                
                // Update active button
                document.querySelectorAll('.method-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                // Hide all sections
                document.querySelectorAll('.payment-section').forEach(section => {
                    section.classList.add('hidden');
                });
                
                // Show selected section
                const method = btn.dataset.method;
                const section = document.getElementById(method);
                if (section) {
                    section.classList.remove('hidden');
                }
            });
        });
        
        // Form submission
        document.getElementById('paymentForm').addEventListener('submit', (e) => {
            e.preventDefault();
            alert('✅ Pagesa u pranua! Transaksioni po përpunohet...');
            // Here you would process the payment
        });
    </script>
</body>
</html>
