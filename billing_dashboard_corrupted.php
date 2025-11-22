<?php
/**
 * Dashboard për Menaxhimin e Sistemit të Faturimit Automatik
 * Automatic Billing System Management Dashboard
 */

session_start();
require_once 'config.php';
require_once 'confidb.php';

// Kontrollo autorizimin
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php?error=auth_required");
    exit();
}

$isAdmin = isset($_SESSION['admin_id']);
if (!$isAdmin) {
    header("Location: dashboard.php?error=admin_required");
    exit();
}

$message = '';
$messageType = '';

// Përditëso konfigurimet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_config'])) {
    try {
        $configs = [
            'billing_time' => $_POST['billing_time'],
            'billing_day' => $_POST['billing_day'],
            'standard_price' => $_POST['standard_price'],
            'premium_price' => $_POST['premium_price'],
            'due_days' => $_POST['due_days'],
            'email_notifications' => isset($_POST['email_notifications']) ? '1' : '0',
            'auto_billing_enabled' => isset($_POST['auto_billing_enabled']) ? '1' : '0'
        ];
        
        $updateStmt = $pdo->prepare("
            UPDATE billing_config 
            SET config_value = ?, updated_at = NOW() 
            WHERE config_key = ?
        ");
        
        foreach ($configs as $key => $value) {
            $updateStmt->execute([$value, $key]);
        }
        
        $message = "Konfigurimet u përditësuan me sukses!";
        $messageType = 'success';
        
    } catch (PDOException $e) {
        $message = "Gabim gjatë përditësimit: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Ekzekuto faturimin manual
if (isset($_GET['action']) && $_GET['action'] === 'manual_billing') {
    try {
        ob_start();
        include 'auto_billing_system.php';
        $output = ob_get_clean();
        
        $message = "Faturimi manual u ekzekutua me sukses! Kontrolloni log files për detaje.";
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = "Gabim gjatë faturimit manual: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Merr konfigurimet aktuale
try {
    $configStmt = $pdo->query("SELECT config_key, config_value FROM billing_config");
    $configs = [];
    while ($row = $configStmt->fetch(PDO::FETCH_ASSOC)) {
        $configs[$row['config_key']] = $row['config_value'];
    }
} catch (PDOException $e) {
    $message = "Gabim: Tabelat e sistemit të faturimit nuk janë krijuar. Ju lutemi ekzekutoni setup_billing_tables.php fillimisht.";
    $messageType = 'error';
    $configs = [
        'billing_time' => '07:00:00',
        'billing_day' => '1',
        'standard_price' => '150.00',
        'premium_price' => '200.00',
        'due_days' => '7',
        'email_notifications' => '1',
        'auto_billing_enabled' => '1'
    ];
}

// Merr statistikat e fundit
try {
    $statsStmt = $pdo->query("
        SELECT * FROM billing_statistics 
        ORDER BY billing_date DESC 
        LIMIT 12
    ");
    $recentStats = $statsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recentStats = [];
}

// Merr pagesat e fundit
try {
    $paymentsStmt = $pdo->query("
        SELECT 
            sp.*,
            n.emri,
            n.mbiemri,
            n.email
        FROM subscription_payments sp
        JOIN noteri n ON sp.noter_id = n.id
        ORDER BY sp.payment_date DESC 
        LIMIT 20
    ");
    $recentPayments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recentPayments = [];
}

// Statistikat e përgjithshme
try {
    $totalRevenue = $pdo->query("
        SELECT SUM(amount) FROM subscription_payments 
        WHERE status = 'completed'
    ")->fetchColumn() ?: 0;

    $monthlyRevenue = $pdo->query("
        SELECT SUM(amount) FROM subscription_payments 
        WHERE status = 'completed' 
        AND MONTH(payment_date) = MONTH(CURDATE()) 
        AND YEAR(payment_date) = YEAR(CURDATE())
    ")->fetchColumn() ?: 0;

    $pendingPayments = $pdo->query("
        SELECT COUNT(*) FROM subscription_payments 
        WHERE status = 'pending'
    ")->fetchColumn() ?: 0;

    $failedPayments = $pdo->query("
        SELECT COUNT(*) FROM subscription_payments 
        WHERE status = 'failed' 
        AND MONTH(payment_date) = MONTH(CURDATE()) 
        AND YEAR(payment_date) = YEAR(CURDATE())
    ")->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $totalRevenue = 0;
    $monthlyRevenue = 0;
    $pendingPayments = 0;
    $failedPayments = 0;
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistemi i Faturimit Automatik | Noteria</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a56db;
            --success: #16a34a;
            --danger: #dc2626;
            --warning: #f59e0b;
            --info: #0ea5e9;
            --light: #f9fafb;
            --dark: #1f2937;
            --card-bg: #ffffff;
            --border: #e5e7eb;
            --text: #374151;
            --heading: #111827;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--light);
            color: var(--text);
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            color: var(--heading);
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.revenue { background: var(--success); }
        .stat-icon.pending { background: var(--warning); }
        .stat-icon.failed { background: var(--danger); }
        .stat-icon.monthly { background: var(--info); }

        .stat-content h3 {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--heading);
        }

        .stat-content p {
            color: var(--text);
            font-size: 0.9rem;
        }

        .card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .card h2 {
            color: var(--heading);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--heading);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 86, 219, 0.1);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #1e40af;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--danger);
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: var(--light);
            font-weight: 600;
            color: var(--heading);
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-credit-card"></i> Sistemi i Faturimit Automatik</h1>
            <p>Menaxhimi dhe monitorimi i faturimit automatik për zyrat noteriale</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'error' ? 'error' : 'success'; ?>">
                <i class="fas fa-<?php echo $messageType === 'error' ? 'exclamation-circle' : 'check-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistikat -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon revenue">
                    <i class="fas fa-euro-sign"></i>
                </div>
                <div class="stat-content">
                    <h3>€<?php echo number_format($totalRevenue, 2); ?></h3>
                    <p>Të hyrat totale</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon monthly">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3>€<?php echo number_format($monthlyRevenue, 2); ?></h3>
                    <p>Të hyrat e muajit</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $pendingPayments; ?></h3>
                    <p>Pagesa në pritje</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon failed">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $failedPayments; ?></h3>
                    <p>Pagesa të dështuara (muaji)</p>
                </div>
            </div>
        </div>

        <!-- Konfigurimet -->
        <div class="card">
            <h2><i class="fas fa-cogs"></i> Konfigurimet e Sistemit</h2>
            
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Ora e faturimit</label>
                        <input type="time" name="billing_time" class="form-control" 
                               value="<?php echo $configs['billing_time'] ?? '07:00:00'; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Dita e muajit për faturim</label>
                        <select name="billing_day" class="form-control" required>
                            <?php for ($i = 1; $i <= 28; $i++): ?>
                                <option value="<?php echo $i; ?>" 
                                    <?php echo ($configs['billing_day'] ?? '1') == $i ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Çmimi standard (€)</label>
                        <input type="number" name="standard_price" class="form-control" step="0.01" min="0"
                               value="<?php echo $configs['standard_price'] ?? '150.00'; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Çmimi premium (€)</label>
                        <input type="number" name="premium_price" class="form-control" step="0.01" min="0"
                               value="<?php echo $configs['premium_price'] ?? '200.00'; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Ditët për të paguar</label>
                        <input type="number" name="due_days" class="form-control" min="1" max="30"
                               value="<?php echo $configs['due_days'] ?? '7'; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="email_notifications" id="email_notifications"
                               <?php echo ($configs['email_notifications'] ?? '1') == '1' ? 'checked' : ''; ?>>
                        <label for="email_notifications" class="form-label">Dërgo njoftimet email</label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="auto_billing_enabled" id="auto_billing_enabled"
                               <?php echo ($configs['auto_billing_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                        <label for="auto_billing_enabled" class="form-label">Aktivizo faturimin automatik</label>
                    </div>
                </div>

                <div class="action-buttons">
                    <button type="submit" name="update_config" class="btn btn-primary">
                        <i class="fas fa-save"></i> Ruaj Konfigurimet
                    </button>
                    
                    <a href="?action=manual_billing" class="btn btn-warning" 
                       onclick="return confirm('Jeni të sigurt që doni të ekzekutoni faturimin manual?')">
                        <i class="fas fa-play"></i> Ekzekuto Faturimin Manual
                    </a>
                    
                    <a href="admin_noters.php" class="btn btn-success">
                        <i class="fas fa-users"></i> Menaxho Noterët
                    </a>
                </div>
            </form>
        </div>

        <!-- Pagesat e fundit -->
        <div class="card">
            <h2><i class="fas fa-receipt"></i> Pagesat e Fundit</h2>
            
            <div class="table-responsive">
                <?php if (!empty($recentPayments)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Noter</th>
                                <th>Shuma</th>
                                <th>Statusi</th>
                                <th>Lloji</th>
                                <th>ID Transaksioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPayments as $payment): ?>
                                <tr>
                                    <td><?php echo date('d.m.Y H:i', strtotime($payment['payment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($payment['emri'] . ' ' . $payment['mbiemri']); ?></td>
                                    <td>€<?php echo number_format($payment['amount'], 2); ?></td>
                                    <td>
                                        <?php
                                        $statusClass = [
                                            'completed' => 'success',
                                            'pending' => 'warning',
                                            'failed' => 'danger',
                                            'cancelled' => 'info'
                                        ][$payment['status']] ?? 'info';
                                        ?>
                                        <span class="badge badge-<?php echo $statusClass; ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo ucfirst($payment['payment_type'] ?? 'automatic'); ?></td>
                                    <td style="font-family: monospace; font-size: 0.8rem;">
                                        <?php echo htmlspecialchars($payment['transaction_id']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Nuk ka pagesa të regjistruara.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistikat mujore -->
        <?php if (!empty($recentStats)): ?>
            <div class="card">
                <h2><i class="fas fa-chart-line"></i> Statistikat Mujore</h2>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Noterë të procesuar</th>
                                <th>Faturime të suksesshme</th>
                                <th>Faturime të dështuara</th>
                                <th>Shuma totale</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentStats as $stat): ?>
                                <tr>
                                    <td><?php echo date('d.m.Y', strtotime($stat['billing_date'])); ?></td>
                                    <td><?php echo $stat['total_noters_processed']; ?></td>
                                    <td class="text-success"><?php echo $stat['successful_charges']; ?></td>
                                    <td class="text-danger"><?php echo $stat['failed_charges']; ?></td>
                                    <td>€<?php echo number_format($stat['total_amount_charged'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>