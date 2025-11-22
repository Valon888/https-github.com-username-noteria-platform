<?php
// noter_subscription.php - Faqja për të parë detajet e abonimit për noterët
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';

// Kontrollo autorizimin
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['roli'] !== 'noteri' && $_SESSION['roli'] !== 'admin')) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$roli = $_SESSION['roli'];

// Merr të dhënat e noterit
$noterId = $user_id;
if ($roli === 'admin' && isset($_GET['id'])) {
    $noterId = $_GET['id'];
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            n.id, n.emri, n.mbiemri, n.email, n.telefoni, n.adresa, n.qyteti, 
            n.statusi, n.subscription_status, n.account_number, n.bank_name,
            COALESCE(n.custom_price, s.subscription_price) AS subscription_price
        FROM 
            noteri n
        LEFT JOIN 
            system_settings s ON 1=1
        WHERE 
            n.id = ?
        LIMIT 1
    ");
    $stmt->execute([$noterId]);
    $noter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$noter) {
        die("Noteri nuk u gjet!");
    }
    
    // Merr pagesat e abonimeve
    $stmt = $pdo->prepare("
        SELECT 
            id, amount, payment_date, status, reference, transaction_id, 
            payment_method, description
        FROM 
            subscription_payments
        WHERE 
            noter_id = ?
        ORDER BY 
            payment_date DESC
        LIMIT 10
    ");
    $stmt->execute([$noterId]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Merr konfigurimet e përgjithshme të abonimeve
    $stmt = $pdo->query("SELECT * FROM system_settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings) {
        $settings = [
            'subscription_price' => 25.00,
            'payment_day' => 1,
            'subscription_frequency' => 'monthly',
            'grace_period' => 3
        ];
    }
    
    // Llogarit datën e ardhshme të pagesës
    $nextPaymentDay = $settings['payment_day'];
    $today = new DateTime();
    $nextPayment = new DateTime();
    $nextPayment->setDate($today->format('Y'), $today->format('m'), $nextPaymentDay);
    
    // Nëse data e pagesës ka kaluar për këtë muaj, kalo në muajin tjetër
    if ($today->format('j') >= $nextPaymentDay) {
        $nextPayment->modify('+1 month');
    }
    
} catch (PDOException $e) {
    die("Gabim në marrjen e të dhënave: " . $e->getMessage());
}

// Procesi për të ndryshuar statusin e abonimit (vetëm për admin)
if ($roli === 'admin' && isset($_POST['update_status'])) {
    try {
        $newStatus = $_POST['subscription_status'];
        $stmt = $pdo->prepare("UPDATE noteri SET subscription_status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $noterId]);
        
        $message = "Statusi i abonimit u përditësua me sukses!";
        $messageType = "success";
        
        // Përditëso të dhënat e noterit pas ndryshimit
        $noter['subscription_status'] = $newStatus;
        
    } catch (PDOException $e) {
        $message = "Gabim në përditësimin e statusit: " . $e->getMessage();
        $messageType = "error";
    }
}

// Procesi për të ndryshuar detajet e pagesës (vetëm për noter ose admin)
if (isset($_POST['update_payment_details'])) {
    try {
        $accountNumber = $_POST['account_number'];
        $bankName = $_POST['bank_name'];
        
        $stmt = $pdo->prepare("UPDATE noteri SET account_number = ?, bank_name = ? WHERE id = ?");
        $stmt->execute([$accountNumber, $bankName, $noterId]);
        
        $message = "Detajet e pagesës u përditësuan me sukses!";
        $messageType = "success";
        
        // Përditëso të dhënat e noterit pas ndryshimit
        $noter['account_number'] = $accountNumber;
        $noter['bank_name'] = $bankName;
        
    } catch (PDOException $e) {
        $message = "Gabim në përditësimin e detajeve të pagesës: " . $e->getMessage();
        $messageType = "error";
    }
}

?><!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abonimi Im | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #1a56db;
            --primary-hover: #1e40af;
            --secondary-color: #6b7280;
            --light-bg: #f8fafc;
            --border-color: #e2e8f0;
            --text-color: #374151;
            --heading-color: #1e293b;
            --success-color: #16a34a;
            --warning-color: #f59e0b;
            --danger-color: #dc2626;
        }
        
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: var(--light-bg);
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 15px 0;
            margin-bottom: 30px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .logo {
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
        }
        
        .logo i {
            margin-right: 10px;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
        }
        
        .user-info {
            margin-right: 20px;
            font-size: 0.9rem;
            color: var(--secondary-color);
        }
        
        .panel {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        h1, h2, h3 {
            color: var(--heading-color);
            margin-top: 0;
        }
        
        h1 {
            font-size: 1.8rem;
            margin-bottom: 20px;
            color: var(--primary-color);
        }
        
        h2 {
            font-size: 1.4rem;
            margin-bottom: 15px;
        }
        
        h3 {
            font-size: 1.2rem;
            margin-bottom: 10px;
        }
        
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border-left: 5px solid #16a34a;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 5px solid #dc2626;
        }
        
        .alert-info {
            background-color: #dbeafe;
            color: #1e40af;
            border-left: 5px solid #2563eb;
        }
        
        .subscription-info {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-box {
            flex: 1;
            min-width: 250px;
            background-color: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            border-left: 5px solid var(--primary-color);
        }
        
        .info-box h3 {
            margin-top: 0;
            color: var(--primary-color);
            font-size: 1.2rem;
        }
        
        .info-item {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--heading-color);
            margin-bottom: 5px;
        }
        
        .info-value {
            color: var(--text-color);
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .status-active {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .status-inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        th {
            font-weight: 600;
            color: var(--heading-color);
            background-color: #f9fafb;
        }
        
        tr:hover {
            background-color: #f9fafb;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-success {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .badge-warning {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .badge-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .badge-info {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .button {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-family: inherit;
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            margin-right: 10px;
            transition: background-color 0.2s;
            border: none;
        }
        
        .button:hover {
            background-color: var(--primary-hover);
        }
        
        .button i {
            margin-right: 6px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--heading-color);
        }
        
        input[type="text"],
        input[type="email"],
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background-color: white;
            font-family: inherit;
            font-size: 1rem;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(26, 86, 219, 0.2);
        }
        
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.07);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--heading-color);
        }
        
        .payment-info {
            background-color: #f0f9ff;
            border-left: 5px solid #0ea5e9;
            padding: 15px;
            margin-top: 20px;
            border-radius: 4px;
        }
        
        .payment-info p:last-child {
            margin-bottom: 0;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .user-menu {
                margin-top: 15px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-file-invoice"></i>
                Abonimi Im
            </div>
            <div class="user-menu">
                <div class="user-info">
                    <strong><?php echo htmlspecialchars($noter['emri'] . ' ' . $noter['mbiemri']); ?></strong> | <?php echo date('d.m.Y'); ?>
                </div>
                <a href="dashboard.php" class="button">
                    <i class="fas fa-arrow-left"></i> Kthehu te paneli
                </a>
            </div>
        </div>
    </header>
    
    <div class="container">
        <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <h1>
            <i class="fas fa-user-tie"></i>
            <?php echo htmlspecialchars($noter['emri'] . ' ' . $noter['mbiemri']); ?>
            <span style="font-size: 1rem; color: #6b7280;">
                (<?php echo htmlspecialchars($noter['email']); ?>)
            </span>
        </h1>
        
        <div class="subscription-info">
            <div class="info-box">
                <h3>Detajet e abonimit</h3>
                
                <div class="info-item">
                    <div class="info-label">Statusi i abonimit</div>
                    <div class="info-value">
                        <?php
                            $statusClass = '';
                            switch ($noter['subscription_status']) {
                                case 'active':
                                    $statusClass = 'status-active';
                                    $statusText = 'Aktiv';
                                    break;
                                case 'inactive':
                                    $statusClass = 'status-inactive';
                                    $statusText = 'Jo aktiv';
                                    break;
                                default:
                                    $statusClass = 'status-pending';
                                    $statusText = 'Në pritje';
                            }
                        ?>
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <?php echo $statusText; ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Çmimi i abonimit</div>
                    <div class="info-value">
                        <?php echo number_format($noter['subscription_price'], 2); ?> €
                        <?php if (isset($noter['custom_price'])): ?>
                            <span style="font-size: 0.8rem; color: #6b7280;">(Çmim i personalizuar)</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Frekuenca e pagesës</div>
                    <div class="info-value">
                        <?php
                            switch ($settings['subscription_frequency']) {
                                case 'monthly':
                                    echo 'Mujore';
                                    break;
                                case 'quarterly':
                                    echo 'Çdo tre muaj';
                                    break;
                                case 'annually':
                                    echo 'Vjetore';
                                    break;
                                default:
                                    echo 'Mujore';
                            }
                        ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Pagesa e ardhshme</div>
                    <div class="info-value">
                        <?php echo $nextPayment->format('d.m.Y'); ?>
                        (Dita <?php echo $settings['payment_day']; ?> e çdo muaji)
                    </div>
                </div>
            </div>
            
            <div class="info-box">
                <h3>Detajet e pagesës</h3>
                
                <div class="info-item">
                    <div class="info-label">Numri i llogarisë</div>
                    <div class="info-value">
                        <?php echo !empty($noter['account_number']) ? htmlspecialchars($noter['account_number']) : '<span style="color: #dc2626;">Nuk është konfiguruar</span>'; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Banka</div>
                    <div class="info-value">
                        <?php echo !empty($noter['bank_name']) ? htmlspecialchars($noter['bank_name']) : '<span style="color: #dc2626;">Nuk është konfiguruar</span>'; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Metoda e pagesës</div>
                    <div class="info-value">
                        Debitim direkt
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Statusi i llogarisë</div>
                    <div class="info-value">
                        <?php
                            if ($noter['statusi'] === 'active') {
                                echo '<span class="status-badge status-active">Aktiv</span>';
                            } else {
                                echo '<span class="status-badge status-inactive">Jo aktiv</span>';
                            }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="panel">
            <h2>Pagesat e abonimit</h2>
            
            <?php if (!empty($payments)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Data</th>
                            <th>Shuma</th>
                            <th>Statusi</th>
                            <th>Referenca</th>
                            <th>Përshkrimi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo $payment['id']; ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($payment['payment_date'])); ?></td>
                                <td><?php echo number_format($payment['amount'], 2); ?> €</td>
                                <td>
                                    <?php 
                                        $statusClass = '';
                                        switch ($payment['status']) {
                                            case 'completed':
                                                $statusClass = 'badge-success';
                                                $statusText = 'Kompletuar';
                                                break;
                                            case 'pending':
                                                $statusClass = 'badge-warning';
                                                $statusText = 'Në pritje';
                                                break;
                                            case 'failed':
                                                $statusClass = 'badge-danger';
                                                $statusText = 'Dështuar';
                                                break;
                                            case 'test':
                                                $statusClass = 'badge-info';
                                                $statusText = 'Test';
                                                break;
                                            default:
                                                $statusClass = '';
                                                $statusText = $payment['status'];
                                        }
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($payment['reference']); ?></td>
                                <td><?php echo htmlspecialchars($payment['description']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Nuk ka pagesa të regjistruara ende.</p>
            <?php endif; ?>
        </div>
        
        <div class="panel">
            <h2>Menaxhimi i abonimit</h2>
            
            <?php if ($roli === 'admin'): ?>
                <div class="card">
                    <div class="card-title">Ndrysho statusin e abonimit</div>
                    <form method="post">
                        <div class="form-group">
                            <label for="subscription_status">Statusi i abonimit</label>
                            <select name="subscription_status" id="subscription_status" required>
                                <option value="active" <?php echo $noter['subscription_status'] === 'active' ? 'selected' : ''; ?>>Aktiv</option>
                                <option value="inactive" <?php echo $noter['subscription_status'] === 'inactive' ? 'selected' : ''; ?>>Jo aktiv</option>
                            </select>
                        </div>
                        <button type="submit" name="update_status" class="button">
                            <i class="fas fa-save"></i> Ruaj ndryshimet
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-title">Përditëso detajet e pagesës</div>
                <form method="post">
                    <div class="form-group">
                        <label for="account_number">Numri i llogarisë</label>
                        <input type="text" name="account_number" id="account_number" value="<?php echo htmlspecialchars($noter['account_number'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="bank_name">Banka</label>
                        <input type="text" name="bank_name" id="bank_name" value="<?php echo htmlspecialchars($noter['bank_name'] ?? ''); ?>" required>
                    </div>
                    <button type="submit" name="update_payment_details" class="button">
                        <i class="fas fa-save"></i> Ruaj detajet e pagesës
                    </button>
                </form>
            </div>
            
            <div class="payment-info">
                <h3>Informacion për pagesat automatike</h3>
                <p>Pagesat e abonimit procesohen automatikisht në ditën <?php echo $settings['payment_day']; ?> të çdo muaji.</p>
                <p>Pagesa e ardhshme do të procesohet më datë <?php echo $nextPayment->format('d.m.Y'); ?>.</p>
                <p>Nëse keni pyetje ose nevojë për asistencë, ju lutem na kontaktoni në <strong>support@noteria.al</strong>.</p>
            </div>
        </div>
    </div>
</body>
</html>