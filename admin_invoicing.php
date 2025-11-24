<?php
/**
 * Admin Invoicing and Subscription Management
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'confidb.php';
require_once 'pricing_helper.php';

// Check if admin
if ($_SESSION['roli'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';
$view = $_GET['view'] ?? 'invoices';

// Handle subscription renewal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $payment_id = intval($_POST['payment_id'] ?? 0);
    
    if ($action === 'renew_subscription') {
        $stmt = $pdo->prepare("
            UPDATE ad_payments 
            SET status = 'paid', paid_at = NOW(), 
                start_date = end_date,
                end_date = DATE_ADD(end_date, INTERVAL 1 MONTH),
                next_payment_date = DATE_ADD(end_date, INTERVAL 1 MONTH)
            WHERE id = ?
        ");
        
        if ($stmt->execute([$payment_id])) {
            $message = "Abonimento u rinovua me sukses!";
        } else {
            $error = "Gabim gjatë rinovimit të abonimit.";
        }
    } elseif ($action === 'mark_paid') {
        if (markSubscriptionAsPaid($pdo, $payment_id)) {
            $message = "Pagesa u shënua si e përfunduar.";
        } else {
            $error = "Gabim gjatë përditësimit.";
        }
    } elseif ($action === 'suspend') {
        $stmt = $pdo->prepare("
            UPDATE ad_payments 
            SET status = 'suspended', end_date = NOW()
            WHERE id = ?
        ");
        
        if ($stmt->execute([$payment_id])) {
            $message = "Abonimento u pezullua.";
        } else {
            $error = "Gabim gjatë pezullimit.";
        }
    }
}

// Get all subscriptions
$subscriptions = [];
try {
    $stmt = $pdo->prepare("
        SELECT ap.*, pp.name as plan_name, pp.price as monthly_price, adv.company_name, adv.email
        FROM ad_payments ap
        LEFT JOIN pricing_plans pp ON pp.id = ap.plan_id
        LEFT JOIN advertisers adv ON adv.id = ap.advertiser_id
        ORDER BY ap.created_at DESC
    ");
    $stmt->execute();
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching subscriptions: " . $e->getMessage());
}

// Get summary stats
$stats = [];
try {
    // Total revenue
    $stmt = $pdo->query("SELECT SUM(amount) as total FROM ad_payments WHERE status = 'paid'");
    $stats['total_revenue'] = $stmt->fetchColumn() ?? 0;
    
    // Active subscriptions
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM ad_payments 
        WHERE status = 'paid' AND end_date > NOW()
    ");
    $stats['active_subscriptions'] = $stmt->fetchColumn() ?? 0;
    
    // Pending payments
    $stmt = $pdo->query("SELECT COUNT(*) FROM ad_payments WHERE status = 'pending'");
    $stats['pending_payments'] = $stmt->fetchColumn() ?? 0;
    
    // Monthly revenue
    $stmt = $pdo->query("
        SELECT SUM(amount) FROM ad_payments 
        WHERE status = 'paid' AND MONTH(paid_at) = MONTH(NOW()) AND YEAR(paid_at) = YEAR(NOW())
    ");
    $stats['monthly_revenue'] = $stmt->fetchColumn() ?? 0;
} catch (Exception $e) {
    error_log("Error fetching stats: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menaxhim i Faturavendimeve - Noteria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
            background: #f5f7fa;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        
        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-card .subtitle {
            font-size: 13px;
            color: #999;
            margin-top: 5px;
        }
        
        .message {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
            font-size: 13px;
            text-transform: uppercase;
        }
        
        .table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .table tr:hover {
            background: #fafbfc;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #e7e7e7;
            color: #666;
        }
        
        .badge.paid { background: #d4edda; color: #155724; }
        .badge.pending { background: #fff3cd; color: #856404; }
        .badge.suspended { background: #f8d7da; color: #721c24; }
        
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 5px;
        }
        
        .action-btn.primary {
            background: #667eea;
            color: white;
        }
        
        .action-btn.primary:hover {
            background: #764ba2;
        }
        
        .action-btn.danger {
            background: #dc3545;
            color: white;
        }
        
        .action-btn.danger:hover {
            background: #c82333;
        }
        
        .action-btn.success {
            background: #28a745;
            color: white;
        }
        
        .action-btn.success:hover {
            background: #218838;
        }
        
        .tabs {
            display: flex;
            gap: 0;
            margin-bottom: 20px;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .tab-btn {
            flex: 1;
            padding: 15px;
            border: none;
            background: #f8f9fa;
            cursor: pointer;
            font-weight: 500;
            color: #666;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }
        
        .tab-btn.active {
            background: white;
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab-btn:hover {
            background: white;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1><i class="fas fa-receipt"></i> Menaxhim i Faturavedimeve</h1>
            <p>Administroni abonimet dhe faturimet e reklamimit</p>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- STATS SECTION -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-euro-sign"></i> Të ardhurat Totale</h3>
                <div class="value">€<?php echo number_format($stats['total_revenue'], 2); ?></div>
                <div class="subtitle">Të gjitha pagesës e përfunduar</div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-calendar"></i> Të Ardhura të Muajit</h3>
                <div class="value">€<?php echo number_format($stats['monthly_revenue'], 2); ?></div>
                <div class="subtitle"><?php echo date('F Y'); ?></div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-check-circle"></i> Abonime Aktive</h3>
                <div class="value"><?php echo $stats['active_subscriptions']; ?></div>
                <div class="subtitle">Abonime të paguara</div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-clock"></i> Në Pritje</h3>
                <div class="value"><?php echo $stats['pending_payments']; ?></div>
                <div class="subtitle">Pagesë në pritje</div>
            </div>
        </div>
        
        <!-- TABLE SECTION -->
        <div class="table-container">
            <div style="padding: 15px 20px; background: #f8f9fa; border-bottom: 1px solid #ddd;">
                <h2 style="margin: 0; font-size: 18px;">
                    <i class="fas fa-list"></i> Të Gjitha Abonimet
                </h2>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Biznes</th>
                        <th>Email</th>
                        <th>Paketa</th>
                        <th>Çmim Mujor</th>
                        <th>Status</th>
                        <th>Periudha</th>
                        <th>Paguesa e Fundit</th>
                        <th>Aksion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscriptions as $sub): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($sub['company_name'] ?? 'N/A'); ?></strong></td>
                        <td><?php echo htmlspecialchars($sub['email'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($sub['plan_name'] ?? 'N/A'); ?></td>
                        <td>€<?php echo number_format($sub['monthly_price'] ?? 0, 2); ?></td>
                        <td>
                            <span class="badge <?php echo strtolower($sub['status']); ?>">
                                <?php 
                                $status_label = [
                                    'paid' => 'E paguar',
                                    'pending' => 'Në pritje',
                                    'suspended' => 'E pezulluar',
                                    'expired' => 'Skaduar'
                                ];
                                echo $status_label[$sub['status']] ?? ucfirst($sub['status']);
                                ?>
                            </span>
                        </td>
                        <td>
                            <small>
                                <?php echo date('d.m.Y', strtotime($sub['start_date'] ?? 'now')); ?> - 
                                <?php echo date('d.m.Y', strtotime($sub['end_date'] ?? 'now')); ?>
                            </small>
                        </td>
                        <td>
                            <small>
                                <?php echo $sub['paid_at'] ? date('d.m.Y', strtotime($sub['paid_at'])) : '-'; ?>
                            </small>
                        </td>
                        <td>
                            <?php if ($sub['status'] === 'pending'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="payment_id" value="<?php echo $sub['id']; ?>">
                                <input type="hidden" name="action" value="mark_paid">
                                <button type="submit" class="action-btn success" title="Shëno si e paguar">
                                    <i class="fas fa-check"></i> Paguaj
                                </button>
                            </form>
                            <?php elseif ($sub['status'] === 'paid' && strtotime($sub['end_date']) < time()): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="payment_id" value="<?php echo $sub['id']; ?>">
                                <input type="hidden" name="action" value="renew_subscription">
                                <button type="submit" class="action-btn primary" title="Rinovoj abonimin">
                                    <i class="fas fa-sync"></i> Rinovoj
                                </button>
                            </form>
                            <?php endif; ?>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="payment_id" value="<?php echo $sub['id']; ?>">
                                <input type="hidden" name="action" value="suspend">
                                <button type="submit" class="action-btn danger" title="Pezullo abonimin" onclick="return confirm('Jeni i sigurt?')">
                                    <i class="fas fa-ban"></i> Pezullo
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($subscriptions)): ?>
            <div style="padding: 40px; text-align: center; color: #999;">
                <i class="fas fa-inbox" style="font-size: 48px; opacity: 0.3; margin-bottom: 20px; display: block;"></i>
                <p>Nuk ka abonime të regjistruara</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- HELP SECTION -->
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; text-align: center; margin-top: 40px;">
            <h2 style="color: white; margin-bottom: 10px;">Ndihmë me Menaxhimin e Abonim?</h2>
            <p style="margin-bottom: 15px; opacity: 0.9;">Këto janë karakteristikat e menaxhimit të faturavedimeve</p>
            <ul style="list-style: none; text-align: center; opacity: 0.95;">
                <li>✓ Shikoni të ardhurat dhe statistikat në kohë reale</li>
                <li>✓ Menaxhoni statusin e pagesave të bizneseve</li>
                <li>✓ Rinovoni abonim automatikisht kur skadojnë</li>
                <li>✓ Pezulloni abonim për jo-paguesa</li>
            </ul>
        </div>
    </div>
</body>
</html>
