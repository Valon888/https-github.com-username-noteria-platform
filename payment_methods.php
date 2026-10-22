<?php
/**
 * Faqja për Menaxhimin e Metodave të Pagesës
 * Payment Methods Management Page
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/payment_methods_error.log');

require_once __DIR__ . '/env_loader.php';
require_once 'confidb.php';
require_once 'payment_method_manager.php';
ini_set('display_errors', 0);

// Kontrollo nëse përdoruesi është i kyçur
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Merr detajet e zyrës së përdoruesit
$stmt = $pdo->prepare("
    SELECT z.*, u.email, u.emri, u.mbiemri
    FROM zyrat z
    JOIN users u ON u.zyra_id = z.id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$office = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$office) {
    die("Gabim: Nuk u gjet zyra juaj noteriale.");
}

$officeId = $office['id'];

// Inicializo menaxherin e metodave të pagesës
$paymentManager = new PaymentMethodManager($pdo);
$paymentMethods = $paymentManager->getPaymentMethods($officeId);

// Merr statusin e faturimit të fundit
$lastBilling = [];
$stmt = $pdo->prepare("
    SELECT * FROM billing_records
    WHERE office_id = ?
    ORDER BY billing_date DESC
    LIMIT 1
");
$stmt->execute([$officeId]);
$lastBilling = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menaxhimi i Metodave të Pagesës | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        :root {
            --primary: #2d6cdf;
            --primary-dark: #184fa3;
            --secondary: #10b981;
            --secondary-dark: #047857;
            --warning: #f59e0b;
            --danger: #dc2626;
            --light: #f8fafc;
            --dark: #1e293b;
            --gray: #64748b;
            --border: #e2eafc;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Montserrat', Arial, sans-serif;
            background: linear-gradient(120deg, #e2eafc 0%, #f8fafc 100%);
            color: var(--dark);
            line-height: 1.6;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px;
        }

        .header {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 32px;
            text-align: center;
        }

        .header h1 {
            color: var(--primary);
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .header p {
            color: var(--gray);
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 24px;
            margin-bottom: 24px;
        }

        .card h2 {
            color: var(--dark);
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 16px;
        }

        .billing-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .billing-item {
            background: var(--light);
            padding: 16px;
            border-radius: 8px;
            text-align: center;
        }

        .billing-item .amount {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }

        .billing-item .label {
            color: var(--gray);
            font-size: 14px;
        }

        .payment-methods {
            margin-top: 24px;
        }

        .payment-method {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 12px;
        }

        .payment-method.default {
            border-color: var(--secondary);
            background: #f0fdf4;
        }

        .payment-method-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-icon {
            font-size: 24px;
            color: var(--primary);
        }

        .card-details h4 {
            margin: 0;
            font-size: 16px;
        }

        .card-details p {
            margin: 4px 0 0 0;
            color: var(--gray);
            font-size: 14px;
        }

        .payment-actions {
            display: flex;
            gap: 8px;
        }

        .btn {
            display: inline-block;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--gray);
            color: white;
        }

        .btn-secondary:hover {
            background: #475569;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .btn-success {
            background: var(--secondary);
            color: white;
        }

        .btn-success:hover {
            background: var(--secondary-dark);
        }

        .add-payment-form {
            margin-top: 24px;
            padding: 20px;
            background: var(--light);
            border-radius: 8px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 16px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .alert.success {
            background: #d1fae5;
            color: var(--secondary-dark);
            border: 1px solid #a7f3d0;
        }

        .alert.error {
            background: #fee2e2;
            color: var(--danger);
            border: 1px solid #fecaca;
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .billing-info {
                grid-template-columns: 1fr;
            }

            .payment-method {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .payment-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-credit-card"></i> Menaxhimi i Metodave të Pagesës</h1>
            <p>Zyra Noteriale: <?php echo htmlspecialchars($office['emri']); ?></p>
        </div>

        <!-- Billing Information -->
        <div class="card">
            <h2><i class="fas fa-chart-line"></i> Informacioni i Faturimit</h2>
            <div class="billing-info">
                <div class="billing-item">
                    <div class="amount">€150</div>
                    <div class="label">Tarifa Mujore</div>
                </div>
                <div class="billing-item">
                    <div class="amount"><?php echo $lastBilling ? htmlspecialchars($lastBilling['status']) : 'N/A'; ?></div>
                    <div class="label">Statusi i Fundit</div>
                </div>
                <div class="billing-item">
                    <div class="amount"><?php echo $lastBilling ? date('d.m.Y', strtotime($lastBilling['billing_date'])) : 'N/A'; ?></div>
                    <div class="label">Faturimi i Fundit</div>
                </div>
                <div class="billing-item">
                    <div class="amount"><?php echo count($paymentMethods); ?></div>
                    <div class="label">Metoda Pagese</div>
                </div>
            </div>
        </div>

        <!-- Payment Methods -->
        <div class="card">
            <h2><i class="fas fa-credit-card"></i> Metodat e Pagesës së Ruajtura</h2>

            <?php if (empty($paymentMethods)): ?>
                <p style="color: var(--gray); text-align: center; padding: 40px;">
                    <i class="fas fa-info-circle" style="font-size: 48px; margin-bottom: 16px;"></i><br>
                    Nuk keni metoda pagese të ruajtura. Shtoni një për të aktivizuar faturimin automatik.
                </p>
            <?php else: ?>
                <div class="payment-methods">
                    <?php foreach ($paymentMethods as $method): ?>
                        <div class="payment-method <?php echo $method['is_default'] ? 'default' : ''; ?>">
                            <div class="payment-method-info">
                                <div class="card-icon">
                                    <i class="fab fa-cc-<?php echo strtolower($method['card_brand']); ?>"></i>
                                </div>
                                <div class="card-details">
                                    <h4><?php echo htmlspecialchars(ucfirst($method['card_brand'])); ?> ****<?php echo htmlspecialchars($method['card_last4']); ?></h4>
                                    <p>
                                        <?php if ($method['is_default']): ?>
                                            <span style="color: var(--secondary);"><i class="fas fa-star"></i> Default</span>
                                        <?php endif; ?>
                                        Shtuar më: <?php echo date('d.m.Y', strtotime($method['created_at'])); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="payment-actions">
                                <?php if (!$method['is_default']): ?>
                                    <button class="btn btn-success" onclick="setDefault(<?php echo $method['id']; ?>)">
                                        <i class="fas fa-star"></i> Bëje Default
                                    </button>
                                <?php endif; ?>
                                <button class="btn btn-danger" onclick="deleteMethod(<?php echo $method['id']; ?>)">
                                    <i class="fas fa-trash"></i> Fshi
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Add New Payment Method -->
            <div class="add-payment-form">
                <h3><i class="fas fa-plus"></i> Shto Metodë të Re Pagese</h3>
                <form id="payment-form">
                    <div class="form-group">
                        <label for="card-element">Detajet e Kartës</label>
                        <div id="card-element" style="padding: 12px; border: 2px solid var(--border); border-radius: 8px;"></div>
                        <div id="card-errors" style="color: var(--danger); font-size: 14px; margin-top: 8px;"></div>
                    </div>
                    <button type="submit" class="btn btn-primary" id="submit-button">
                        <i class="fas fa-save"></i> Ruaj Metodën e Pagesës
                    </button>
                </form>
            </div>
        </div>

        <!-- Alert Container -->
        <div id="alert-container"></div>
    </div>

    <script>
        // Inicializo Stripe
        const stripe = Stripe('<?php echo getenv('STRIPE_PUBLIC_KEY') ?: 'pk_test_...'; ?>');
        const elements = stripe.elements();

        // Krijo elementin e kartës
        const card = elements.create('card');
        card.mount('#card-element');

        // Trajto gabimet e kartës
        card.on('change', function(event) {
            const displayError = document.getElementById('card-errors');
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = '';
            }
        });

        // Trajto submit-in e formës
        const form = document.getElementById('payment-form');
        form.addEventListener('submit', async function(event) {
            event.preventDefault();

            const submitButton = document.getElementById('submit-button');
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Duke ruajtur...';

            try {
                // Krijo payment method
                const {error, paymentMethod} = await stripe.createPaymentMethod({
                    type: 'card',
                    card: card,
                });

                if (error) {
                    showAlert('Gabim: ' + error.message, 'error');
                    return;
                }

                // Dërgo në server
                const response = await fetch('payment_method_manager.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'save_payment_method',
                        payment_method_id: paymentMethod.id,
                        office_id: '<?php echo $officeId; ?>'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showAlert(result.message, 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showAlert(result.message, 'error');
                }

            } catch (error) {
                showAlert('Gabim teknik: ' + error.message, 'error');
            } finally {
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="fas fa-save"></i> Ruaj Metodën e Pagesës';
            }
        });

        // Funksioni për vendosjen si default
        async function setDefault(methodId) {
            if (!confirm('Jeni të sigurt që dëshironi ta vendosni këtë metodë si default?')) {
                return;
            }

            try {
                const response = await fetch('payment_method_manager.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'set_default_payment_method',
                        method_id: methodId,
                        office_id: '<?php echo $officeId; ?>'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showAlert(result.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(result.message, 'error');
                }

            } catch (error) {
                showAlert('Gabim teknik: ' + error.message, 'error');
            }
        }

        // Funksioni për fshirjen
        async function deleteMethod(methodId) {
            if (!confirm('Jeni të sigurt që dëshironi të fshini këtë metodë pagese?')) {
                return;
            }

            try {
                const response = await fetch('payment_method_manager.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'delete_payment_method',
                        method_id: methodId,
                        office_id: '<?php echo $officeId; ?>'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showAlert(result.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(result.message, 'error');
                }

            } catch (error) {
                showAlert('Gabim teknik: ' + error.message, 'error');
            }
        }

        // Funksioni për shfaqjen e alerteve
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alert-container');
            const alert = document.createElement('div');
            alert.className = `alert ${type}`;
            alert.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i> ${message}`;
            alertContainer.appendChild(alert);

            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
    </script>
</body>
</html>