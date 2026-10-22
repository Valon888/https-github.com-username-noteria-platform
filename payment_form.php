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
    $shuma = floatval($_POST['shuma'] ?? 0);
    $pershkrimi = trim($_POST['pershkrimi'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? 'bank_transfer');
    
    // Validation
    if (!$emri_i_plot) {
        $error = "Emri i plotë është i detyrueshëm.";
    } elseif ($shuma <= 0) {
        $error = "Shuma duhet të jetë më e madhe se 0.";
    } elseif (!$pershkrimi) {
        $error = "Përshkrimi i pagesës është i detyrueshëm.";
    } elseif ($payment_method === 'bank_transfer') {
        $iban = trim($_POST['iban'] ?? '');
        if (!$iban || strlen($iban) < 15) {
            $error = "IBAN duhet të jetë i vlefshëm (min 15 karaktere).";
        }
    } elseif ($payment_method === 'card') {
        $card_number = trim($_POST['card_number'] ?? '');
        $card_holder = trim($_POST['card_holder'] ?? '');
        $expiry = trim($_POST['expiry'] ?? '');
        $cvv = trim($_POST['cvv'] ?? '');
        
        if (!$card_number || strlen(str_replace(' ', '', $card_number)) < 13) {
            $error = "Numri i kartës duhet të jetë i vlefshëm (min 13 shifra).";
        } elseif (!$card_holder) {
            $error = "Emri në kartë është i detyrueshëm.";
        } elseif (!$expiry || !preg_match('/^\d{2}\/\d{2}$/', $expiry)) {
            $error = "Data e skadimit duhet të jetë në formatin MM/YY.";
        } elseif (!$cvv || !preg_match('/^\d{3,4}$/', $cvv)) {
            $error = "CVV duhet të ketë 3-4 shifra.";
        }
    }
    
    if (!$error) {
        // Save payment request to database
        try {
            // Use existing payments table structure
            $stmt = $pdo->prepare("
                INSERT INTO payments (user_id, amount, payment_method, status, created_at)
                VALUES (?, ?, ?, 'pending', NOW())
            ");
            
            $result = $stmt->execute([
                $_SESSION['user_id'],
                $shuma,
                $payment_method
            ]);
            
            if ($result) {
                $payment_id = $pdo->lastInsertId();
                
                // Store full details in audit log
                if ($payment_method === 'bank_transfer') {
                    $iban = trim($_POST['iban'] ?? '');
                    $details = "Emri: $emri_i_plot | IBAN: $iban | Përshkrim: $pershkrimi";
                    $log_detail = 'Kërkesë pagese (Transferti Bankar): €' . $shuma . ' | Emri: ' . $emri_i_plot . ' | IBAN: ' . substr($iban, -4) . ' | Përshkrim: ' . $pershkrimi;
                } else {
                    $card_number = trim($_POST['card_number'] ?? '');
                    $details = "Emri: $emri_i_plot | Karta: ****" . substr($card_number, -4) . " | Përshkrim: $pershkrimi";
                    $log_detail = 'Kërkesë pagese (Kartë Krediti): €' . $shuma . ' | Emri: ' . $emri_i_plot . ' | Karta: ****' . substr($card_number, -4) . ' | Përshkrim: ' . $pershkrimi;
                }
                
                $message = "Kërkesa për pagesë u dërgua me sukses! Ju do të kontaktohemi brenda 24 orësh.";
                $payment_submitted = true;
                
                // Log activity with full details
                log_activity($pdo, $_SESSION['user_id'], 'payment_request', $log_detail);
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
            padding: 0;
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            overflow-x: hidden;
        }

        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            padding: 0 8px;
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
            appearance: textfield;
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
        @media (max-width: 430px) {
            body {
                padding: 0;
            }

            /* Uses safe-area values on notched phones */
            .container {
                width: calc(100% - 1rem);
                max-width: 446px;
                margin: 0 auto;
                padding: 0.5rem;
                box-sizing: border-box;
                padding-left: max(0.5rem, env(safe-area-inset-left));
                padding-right: max(0.5rem, env(safe-area-inset-right));
            }

            /* Header section optimization */
            .header {
                padding: clamp(0.8rem, 3.6vw, 1.25rem);
                margin-bottom: 0.75rem;
                border-radius: 6px;
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
            }

            .header h1 {
                font-size: clamp(1.15rem, 4.4vw, 1.6rem);
                margin-bottom: 0.2rem;
                line-height: 1.25;
            }

            .header p {
                font-size: clamp(0.88rem, 2.2vw, 1rem);
                line-height: 1.35;
            }

            /* Content area optimization */
            .content {
                padding: 0.65rem;
                border-radius: 6px;
                box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
            }

            /* Set inputs to 16px to avoid iOS zooming on focus */
            input, select, textarea, label {
                font-size: 16px; /* key: prevents iOS auto-zoom */
            }

            input, select, textarea {
                padding: 0.55rem;
                border-radius: 6px;
                border: 1px solid #cbd5e1;
                background: #ffffff;
                transition: all 0.18s ease;
                width: 100%;
                box-sizing: border-box;
            }

            input:focus, select:focus, textarea:focus {
                border-color: #1e3a8a;
                box-shadow: 0 0 0 2px rgba(30, 58, 138, 0.08);
                outline: none;
            }

            /* Tappable controls */
            button, .button-primary, .payment-buttons button {
                -webkit-tap-highlight-color: rgba(0,0,0,0);
                touch-action: manipulation;
                padding: 0.55rem 0.75rem;
                min-height: 44px;
                min-width: 44px;
                font-size: clamp(0.9rem, 2.2vw, 1rem);
                border-radius: 8px;
            }

            /* Payment buttons grid for mobile */
            .payment-buttons {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.6rem;
            }
            .payment-buttons button {
                width: 100%;
            }

            /* Form groups spacing and layout */
            .form-row {
                gap: 0.5rem;
                grid-template-columns: 1fr;
            }
            .form-group { margin-bottom: 0.8rem; }

            /* Messages, tables */
            .success, .error, .info {
                padding: 0.7rem;
                font-size: 0.95rem;
            }
            table { font-size: 0.85rem; }
            th, td { padding: 0.5rem; }

            /* Tinky overlay / dropdown adjustments */
            #form-tinky-dropdown {
                padding: 12px !important;
                margin: 10px 0 !important;
                border-radius: 10px !important;
                -webkit-overflow-scrolling: touch;
                overflow-y: auto !important;
                overflow-x: hidden !important;
                max-height: 80vh !important;
                background: #fff;
            }
            #form-tinky-dropdown .form-group { margin-bottom: 0.6rem !important; }
            #form-tinky-dropdown label { font-size: 0.95rem !important; }
            #form-tinky-dropdown input, #form-tinky-dropdown select {
                font-size: 16px !important;
                padding: 0.5rem !important;
            }
            #form-tinky-dropdown button {
                padding: 0.55rem 0.65rem !important;
                font-size: 1rem !important;
                min-height: 40px !important;
            }

            /* Prevent overflow or weird zoom on numeric inputs */
            input[type="number"] {
                -moz-appearance: textfield;
                appearance: textfield;
            }
        }

        /* Samsung Galaxy S20 / Pixel — exact width targeting for refined spacing */
        @media (width: 412px) and (max-height: 920px) {
            .container { width: calc(100% - 1rem); padding: 0.45rem; }
            .header h1 { font-size: 1.45rem; }
            .content { padding: 0.6rem; }
            #form-tinky-dropdown { max-height: 78vh !important; }
        }

        /* iPhone XR specific (414x896) */
        @media (width: 414px) and (height: 896px) {
            .container { width: calc(100% - 1rem); padding: 0.5rem; }
            .header { padding: 1rem; }
            .header h1 { font-size: 1.5rem; }
            #form-tinky-dropdown { max-height: 78vh !important; }
        }

        /* iPhone 14 Pro Max (430x932) */
        @media (width: 430px) and (height: 932px) {
            .container { width: calc(100% - 1rem); padding: 0.55rem; }
            .header h1 { font-size: 1.6rem; }
            #form-tinky-dropdown { max-height: 80vh !important; }
        }

        /* Build-up for larger screens — keep layout readable and center-aligned */
        @media (min-width: 480px) {
            .container { padding: 1.25rem; }
        }
        
        /* Regular Mobile Devices (426px - 768px): iPhones 12-14, Pixels, Galaxy */
        @media (min-width: 426px) and (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            .container {
                padding: 0 12px;
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
                        <p>✓ Pagesa do të përfundohet brenda 3 minutash</p>
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
                        <strong>Informacion:</strong> Këto të dhëna do të përdoren vetëm për përfundimin e pagesës tuaj përmes kartës krediti ose transferti bankar.
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: flex; gap: 10px; margin-bottom: 10px; cursor: pointer;">
                            <input type="radio" name="payment_method" value="bank_transfer" checked> 
                            <span><strong>💳 Transferti Bankar</strong></span>
                        </label>
                        <label style="display: flex; gap: 10px; cursor: pointer;">
                            <input type="radio" name="payment_method" value="card"> 
                            <span><strong>💰 Kartë Krediti/Debiti</strong></span>
                        </label>
                    </div>
                    
                    <form method="POST" id="paymentForm">
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
                        
                        <!-- Bank Transfer Fields -->
                        <div id="bankTransferFields">
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
                                >
                                <div class="form-helper">
                                    <i class="fas fa-lock"></i> Të dhëna të sigurta dhe të enkriptuara
                                </div>
                            </div>
                        </div>
                        
                        <!-- Card Payment Fields -->
                        <div id="cardPaymentFields" style="display: none;">
                            <div class="form-group">
                                <label for="card_number">
                                    💳 Numri i Kartës <span class="required">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="card_number" 
                                    name="card_number"
                                    placeholder="1234 5678 9012 3456"
                                    value="<?php echo htmlspecialchars($_POST['card_number'] ?? ''); ?>"
                                    inputmode="numeric"
                                    pattern="[0-9\s]{13,22}"
                                    maxlength="23"
                                >
                                <div class="form-helper">
                                    <i class="fas fa-lock"></i> Të dhënat e kartës janë të enkriptuara
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="card_holder">
                                    👤 Emri në Kartë <span class="required">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="card_holder" 
                                    name="card_holder"
                                    placeholder="EMRI MBIEMRI"
                                    value="<?php echo htmlspecialchars($_POST['card_holder'] ?? ''); ?>"
                                    autocomplete="cc-name"
                                >
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                <div class="form-group">
                                    <label for="expiry">
                                        📅 Skadim <span class="required">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="expiry" 
                                        name="expiry"
                                        placeholder="MM/YY"
                                        value="<?php echo htmlspecialchars($_POST['expiry'] ?? ''); ?>"
                                        inputmode="numeric"
                                        pattern="\d{2}/\d{2}"
                                        maxlength="5"
                                    >
                                    <div class="form-helper">
                                        <i class="fas fa-calendar"></i> Format: MM/YY
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="cvv">
                                        🔒 CVV <span class="required">*</span>
                                    </label>
                                    <input 
                                        type="password" 
                                        id="cvv" 
                                        name="cvv"
                                        placeholder="123"
                                        inputmode="numeric"
                                        pattern="[0-9]{3,4}"
                                        maxlength="4"
                                    >
                                    <div class="form-helper">
                                        <i class="fas fa-lock"></i> 3-4 shifra
                                    </div>
                                </div>
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
                    
                    <script>
                        const paymentMethodInputs = document.querySelectorAll('input[name="payment_method"]');
                        const bankTransferFields = document.getElementById('bankTransferFields');
                        const cardPaymentFields = document.getElementById('cardPaymentFields');
                        const ibanInput = document.getElementById('iban');
                        const cardInputs = document.querySelectorAll('#cardPaymentFields input');
                        
                        function togglePaymentFields() {
                            const selectedMethod = document.querySelector('input[name="payment_method"]:checked').value;
                            if (selectedMethod === 'bank_transfer') {
                                bankTransferFields.style.display = 'block';
                                cardPaymentFields.style.display = 'none';
                                ibanInput.setAttribute('required', '');
                                cardInputs.forEach(input => input.removeAttribute('required'));
                            } else {
                                bankTransferFields.style.display = 'none';
                                cardPaymentFields.style.display = 'block';
                                ibanInput.removeAttribute('required');
                                cardInputs.forEach(input => input.setAttribute('required', ''));
                            }
                        }
                        
                        paymentMethodInputs.forEach(input => {
                            input.addEventListener('change', togglePaymentFields);
                        });
                        
                        // Format card number with spaces
                        document.getElementById('card_number').addEventListener('input', function(e) {
                            let value = e.target.value.replace(/\s/g, '');
                            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
                            e.target.value = formattedValue;
                        });
                        
                        // Format expiry date
                        document.getElementById('expiry').addEventListener('input', function(e) {
                            let value = e.target.value.replace(/\D/g, '');
                            if (value.length >= 2) {
                                value = value.slice(0, 2) + '/' + value.slice(2, 4);
                            }
                            e.target.value = value;
                        });
                        
                        // Allow only digits for CVV
                        document.getElementById('cvv').addEventListener('input', function(e) {
                            e.target.value = e.target.value.replace(/\D/g, '');
                        });
                    </script>
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
