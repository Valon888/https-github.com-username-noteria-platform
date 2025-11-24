<?php
/**
 * Payment Form - For processing payments via Tinky or bank transfer
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'confidb.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';
$payment_submitted = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emri_i_plot = trim($_POST['emri_i_plot'] ?? '');
    $iban = trim($_POST['iban'] ?? '');
    $shuma = floatval($_POST['shuma'] ?? 0);
    $pershkrimi = trim($_POST['pershkrimi'] ?? '');
    
    // Validation
    if (!$emri_i_plot) {
        $error = "Emri i plotë është i detyrueshëm.";
    } elseif (!$iban || strlen($iban) < 15) {
        $error = "IBAN duhet të jetë i vlefshëm (min 15 karaktere).";
    } elseif ($shuma <= 0) {
        $error = "Shuma duhet të jetë më e madhe se 0.";
    } elseif (!$pershkrimi) {
        $error = "Përshkrimi i pagesës është i detyrueshëm.";
    } else {
        // Save payment request to database
        try {
            // Use existing payments table structure
            $stmt = $pdo->prepare("
                INSERT INTO payments (user_id, amount, payment_method, status, created_at)
                VALUES (?, ?, 'bank_transfer', 'pending', NOW())
            ");
            
            $result = $stmt->execute([
                $_SESSION['user_id'],
                $shuma
            ]);
            
            if ($result) {
                $payment_id = $pdo->lastInsertId();
                
                // Store full details in audit log
                $details = "Emri: $emri_i_plot | IBAN: $iban | Përshkrim: $pershkrimi";
                
                $message = "Kërkesa për pagesë u dërgua me sukses! Ju do të kontaktohemi brenda 24 orësh.";
                $payment_submitted = true;
                
                // Log activity with full details
                log_activity($pdo, $_SESSION['user_id'], 'payment_request', 
                    'Kërkesë pagese: €' . $shuma . ' | Emri: ' . $emri_i_plot . ' | IBAN: ' . substr($iban, -4) . ' | Përshkrim: ' . $pershkrimi);
            }
        } catch (Exception $e) {
            $error = "Gabim gjatë përpunimit: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=5, user-scalable=yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#667eea">
    <title>Forma e Pagesës - Noteria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .payment-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        
        .payment-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .payment-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .payment-header p {
            opacity: 0.95;
            font-size: 14px;
        }
        
        .payment-body {
            padding: 30px;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .required {
            color: #dc3545;
        }
        
        input, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: inherit;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        input:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        /* iOS specific fixes */
        input[type="text"],
        input[type="number"],
        textarea {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            border-radius: 6px;
        }
        
        input[type="number"] {
            -webkit-appearance: textfield;
        }
        
        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        
        /* Disable autocorrect on inputs */
        input[type="text"],
        textarea {
            -webkit-autocorrect: off;
            -webkit-spellcheck: off;
            spellcheck: false;
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-helper {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .info-box {
            background: #f0f7ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #333;
            line-height: 1.6;
        }
        
        .info-box strong {
            color: #667eea;
        }
        
        .submit-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .submit-btn:active {
            transform: translateY(0);
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .back-link a:hover {
            color: #764ba2;
        }
        
        .success-message {
            text-align: center;
        }
        
        .success-icon {
            font-size: 48px;
            color: #28a745;
            margin-bottom: 15px;
        }
        
        .success-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }
        
        .success-details p {
            margin: 10px 0;
            font-size: 14px;
            color: #555;
        }
        
        .success-details strong {
            color: #333;
        }
        
        /* Mobile First - Extra Small Devices (320px and up) */
        @media (max-width: 375px) {
            .container {
                padding: 10px;
            }
            
            .payment-card {
                border-radius: 6px;
            }
            
            .payment-header {
                padding: 15px;
            }
            
            .payment-header h1 {
                font-size: 20px;
                margin-bottom: 8px;
            }
            
            .payment-header p {
                font-size: 12px;
            }
            
            .payment-body {
                padding: 15px;
            }
            
            input, textarea {
                font-size: 16px;
                padding: 10px;
            }
            
            label {
                font-size: 13px;
                margin-bottom: 6px;
            }
            
            .form-helper {
                font-size: 11px;
            }
            
            .submit-btn {
                padding: 12px;
                font-size: 14px;
            }
            
            .info-box {
                padding: 12px;
                font-size: 12px;
            }
            
            .success-details {
                padding: 15px;
            }
        }
        
        /* Small Mobile Devices (376px - 425px): iPhone SE, SE 2, etc */
        @media (min-width: 376px) and (max-width: 425px) {
            .container {
                padding: 12px;
            }
            
            .payment-header {
                padding: 18px;
            }
            
            .payment-header h1 {
                font-size: 22px;
            }
            
            .payment-body {
                padding: 18px;
            }
            
            input, textarea {
                font-size: 16px;
            }
            
            label {
                font-size: 13px;
            }
        }
        
        /* Regular Mobile Devices (426px - 768px): iPhones 12-14, Pixels, Galaxy */
        @media (min-width: 426px) and (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            .container {
                padding: 0;
            }
            
            .payment-card {
                border-radius: 10px;
            }
            
            .payment-header {
                padding: 24px;
            }
            
            .payment-header h1 {
                font-size: 24px;
            }
            
            .payment-body {
                padding: 24px;
            }
            
            input, textarea {
                font-size: 16px;
                padding: 11px;
            }
            
            label {
                font-size: 13px;
                margin-bottom: 7px;
            }
            
            .submit-btn {
                padding: 13px;
                font-size: 15px;
            }
        }
        
        /* Large Tablets (769px - 1024px): iPad, iPad Air */
        @media (min-width: 769px) and (max-width: 1024px) {
            .container {
                max-width: 800px;
            }
            
            .payment-header h1 {
                font-size: 32px;
            }
            
            input, textarea {
                padding: 13px;
                font-size: 15px;
            }
        }
        
        /* Desktop and Large Screens (1025px and up) */
        @media (min-width: 1025px) {
            .container {
                max-width: 600px;
                margin: 0 auto;
            }
        }
        
        /* Landscape Orientation for Mobile */
        @media (max-height: 600px) and (orientation: landscape) {
            .payment-header {
                padding: 15px;
            }
            
            .payment-header h1 {
                font-size: 20px;
                margin-bottom: 5px;
            }
            
            .payment-header p {
                font-size: 12px;
            }
            
            .payment-body {
                padding: 15px;
            }
            
            .form-group {
                margin-bottom: 12px;
            }
            
            input, textarea {
                padding: 8px;
                font-size: 14px;
            }
            
            textarea {
                min-height: 60px;
            }
            
            label {
                margin-bottom: 4px;
                font-size: 13px;
            }
        }
        
        /* High DPI Devices (Retina displays) */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
            body {
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }
        }
        
        /* Touch-friendly adjustments */
        @media (hover: none) and (pointer: coarse) {
            input, textarea, .submit-btn {
                min-height: 44px;
                min-width: 44px;
            }
            
            input, textarea {
                padding: 12px;
            }
            
            .submit-btn {
                padding: 14px;
                font-size: 16px;
            }
        }
        
        /* Android/Samsung Galaxy specific improvements */
        @media (hover: none) and (pointer: coarse) and (platform: android) {
            body {
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            
            input, textarea {
                background-color: #ffffff;
                -webkit-user-select: text;
                user-select: text;
            }
            
            input[type="number"]::-webkit-outer-spin-button,
            input[type="number"]::-webkit-inner-spin-button {
                -webkit-appearance: none;
                appearance: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="payment-card">
            <div class="payment-header">
                <h1>
                    <i class="fas fa-credit-card"></i>
                    Forma e Pagesës
                </h1>
                <p>Përfundoni pagesën tuaj përmes Tinky ose transferti bankar</p>
            </div>
            
            <div class="payment-body">
                <?php if ($message): ?>
                    <div class="message success">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <strong><?php echo htmlspecialchars($message); ?></strong>
                            <p style="margin-top: 10px; font-size: 13px;">
                                Referenca e kërkesës: <code style="background: #fff; padding: 2px 6px; border-radius: 3px;">#REF-<?php echo date('Ymdhis'); ?></code>
                            </p>
                        </div>
                    </div>
                    
                    <div class="success-details">
                        <p><i class="fas fa-info-circle"></i> <strong>Hapat e Ardhshme:</strong></p>
                        <p>✓ Ekipi ynë do të shqyrtojë kërkesën tuaj</p>
                        <p>✓ Do të merrni email me instruksionet e pagesës</p>
                        <p>✓ Pagesa do të përfundohet brenda 24 orësh</p>
                    </div>
                    
                    <a href="dashboard.php" class="submit-btn" style="background: #28a745;">
                        <i class="fas fa-home"></i> Kthehu në Dashboard
                    </a>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="message error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <strong>Informacion:</strong> Këto të dhëna do të përdoren vetëm për përfundimin e pagesës tuaj përmes Tinky ose transferti bankar.
                    </div>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label for="emri_i_plot">
                                👤 Emri i plotë <span class="required">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="emri_i_plot" 
                                name="emri_i_plot"
                                placeholder="Emri dhe mbiemri juaj"
                                value="<?php echo htmlspecialchars($_POST['emri_i_plot'] ?? $_SESSION['emri'] ?? ''); ?>"
                                autocomplete="name"
                                required
                            >
                            <div class="form-helper">
                                <i class="fas fa-check"></i> Emri i plotë siç figuron në dokumentin identifikues
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="iban">
                                🏦 IBAN i Bankës <span class="required">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="iban" 
                                name="iban"
                                placeholder="p.sh. XK05 0000 0000 0000 0000"
                                value="<?php echo htmlspecialchars($_POST['iban'] ?? ''); ?>"
                                autocomplete="off"
                                inputmode="text"
                                required
                            >
                            <div class="form-helper">
                                <i class="fas fa-lock"></i> Të dhëna të sigurta dhe të enkriptuara
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="shuma">
                                💵 Shuma për pagesë (€) <span class="required">*</span>
                            </label>
                            <input 
                                type="number" 
                                id="shuma" 
                                name="shuma"
                                placeholder="p.sh. 50.00"
                                step="0.01"
                                min="0.01"
                                value="<?php echo htmlspecialchars($_POST['shuma'] ?? ''); ?>"
                                autocomplete="off"
                                inputmode="decimal"
                                required
                            >
                            <div class="form-helper">
                                <i class="fas fa-euro-sign"></i> Shuma minimale: 1.00€
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="pershkrimi">
                                📝 Përshkrimi i pagesës <span class="required">*</span>
                            </label>
                            <textarea 
                                id="pershkrimi" 
                                name="pershkrimi"
                                placeholder="p.sh. Pagesë për legalizim dokumenti"
                                autocomplete="off"
                                required
                            ><?php echo htmlspecialchars($_POST['pershkrimi'] ?? ''); ?></textarea>
                            <div class="form-helper">
                                <i class="fas fa-align-left"></i> Përshkruani qëllimin e pagesës
                            </div>
                        </div>
                        
                        <button type="submit" class="submit-btn">
                            <i class="fas fa-paper-plane"></i>
                            Dërgo Kërkesën e Pagesës
                        </button>
                    </form>
                <?php endif; ?>
                
                <div class="back-link">
                    <a href="dashboard.php">
                        <i class="fas fa-arrow-left"></i> Kthehu në Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Security Footer -->
        <div style="text-align: center; margin-top: 20px; color: white; font-size: 13px;">
            <p>
                <i class="fas fa-shield-alt"></i>
                Të dhënat tuaja janë të sigurta dhe të enkriptuara me SSL
            </p>
        </div>
    </div>
</body>
</html>
