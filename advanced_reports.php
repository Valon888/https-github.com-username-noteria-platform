<?php
/**
 * Advanced Analytics & Reporting Module
 * Raportet e detajuara për analitikën e pagesave
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['roli'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'confidb.php';

// Get report type
$reportType = $_GET['type'] ?? 'summary';
$period = $_GET['period'] ?? 'month'; // month, quarter, year

// ========== REVENUE ANALYSIS ==========
$revenueByMethod = $pdo->query("
    SELECT 
        payment_method,
        COUNT(*) as transactions,
        SUM(amount) as total_revenue,
        AVG(amount) as avg_amount,
        MIN(amount) as min_amount,
        MAX(amount) as max_amount,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful_transactions,
        ROUND(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) * 100 / COUNT(*), 2) as success_rate
    FROM subscription_payments
    WHERE MONTH(payment_date) = MONTH(CURDATE()) 
    AND YEAR(payment_date) = YEAR(CURDATE())
    GROUP BY payment_method
    ORDER BY total_revenue DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ========== NOTARY PERFORMANCE ==========
$notaryPerformance = $pdo->query("
    SELECT 
        n.id,
        CONCAT(n.emri, ' ', n.mbiemri) as noter,
        n.email,
        COUNT(sp.id) as total_payments,
        SUM(sp.amount) as total_revenue,
        AVG(sp.amount) as avg_payment,
        MAX(sp.payment_date) as last_payment,
        DATEDIFF(CURDATE(), MAX(sp.payment_date)) as days_since_last_payment,
        SUM(CASE WHEN sp.status = 'completed' THEN 1 ELSE 0 END) as completed_payments,
        SUM(CASE WHEN sp.status = 'failed' THEN 1 ELSE 0 END) as failed_payments
    FROM noteri n
    LEFT JOIN subscription_payments sp ON n.id = sp.noter_id
    WHERE n.aktiv = 1
    GROUP BY n.id
    ORDER BY total_revenue DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// ========== TREND ANALYSIS ==========
$monthlyTrends = $pdo->query("
    SELECT 
        DATE_FORMAT(payment_date, '%Y-%m') as month,
        COUNT(*) as transactions,
        SUM(amount) as revenue,
        SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as completed_revenue,
        ROUND(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) * 100 / COUNT(*), 2) as success_rate
    FROM subscription_payments
    WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
    ORDER BY month DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ========== HEALTH METRICS ==========
$totalActive = $pdo->query("SELECT COUNT(*) FROM noteri WHERE aktiv = 1")->fetchColumn();
$totalCompleted = $pdo->query("SELECT COUNT(*) FROM subscription_payments WHERE status = 'completed'")->fetchColumn();
$totalFailed = $pdo->query("SELECT COUNT(*) FROM subscription_payments WHERE status = 'failed'")->fetchColumn();
$totalPending = $pdo->query("SELECT COUNT(*) FROM subscription_payments WHERE status = 'pending'")->fetchColumn();

$totalRevenue = $pdo->query("SELECT SUM(amount) FROM subscription_payments WHERE status = 'completed'")->fetchColumn() ?: 0;
$avgPayment = $totalCompleted > 0 ? ($totalRevenue / $totalCompleted) : 0;

$churnRate = $totalActive > 0 ? round(($pdo->query("
    SELECT COUNT(DISTINCT n.id) FROM noteri n
    LEFT JOIN subscription_payments sp ON n.id = sp.noter_id
    WHERE n.aktiv = 1 AND DATEDIFF(CURDATE(), MAX(sp.payment_date)) > 60
")->fetchColumn() / $totalActive) * 100, 2) : 0;

// Build response
$report = [
    'type' => $reportType,
    'period' => $period,
    'generated_at' => date('Y-m-d H:i:s'),
    'metrics' => [
        'total_active_notaries' => $totalActive,
        'total_completed_payments' => $totalCompleted,
        'total_failed_payments' => $totalFailed,
        'total_pending_payments' => $totalPending,
        'total_revenue' => round($totalRevenue, 2),
        'average_payment' => round($avgPayment, 2),
        'churn_rate_percent' => $churnRate
    ],
    'revenue_by_method' => $revenueByMethod,
    'notary_performance' => $notaryPerformance,
    'monthly_trends' => $monthlyTrends
];

// Return JSON or HTML based on request
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

// HTML Report View
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raportet e Avancuara - Sistemi i Pagesave</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
            color: #333;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        header {
            margin-bottom: 2rem;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 1rem;
        }
        h1 {
            color: #667eea;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .date-range {
            color: #666;
            font-size: 0.9rem;
        }
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
        }
        .metric-value {
            font-size: 2rem;
            font-weight: 700;
            margin: 0.5rem 0;
        }
        .metric-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 2rem 0;
            font-size: 0.95rem;
        }
        th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
        }
        td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #eee;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .section {
            margin: 3rem 0;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #667eea;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: #e3f2fd;
            color: #0d47a1;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .badge.high {
            background: #fff3e0;
            color: #e65100;
        }
        .badge.low {
            background: #e8f5e9;
            color: #2e7d32;
        }
        @media print {
            body { background: white; }
            .container { box-shadow: none; }
            .metrics-grid { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fas fa-chart-bar"></i> Raportet e Avancuara - Analitika e Pagesave</h1>
            <div class="date-range">Gjeneruar: <?php echo date('d.m.Y H:i:s'); ?></div>
        </header>

        <!-- Metrics Overview -->
        <div class="metrics-grid">
            <div class="metric-card">
                <i class="fas fa-users" style="font-size: 1.5rem; margin-bottom: 0.5rem;"></i>
                <div class="metric-value"><?php echo $report['metrics']['total_active_notaries']; ?></div>
                <div class="metric-label">Noterë aktivë</div>
            </div>
            <div class="metric-card">
                <i class="fas fa-check-circle" style="font-size: 1.5rem; margin-bottom: 0.5rem;"></i>
                <div class="metric-value">€<?php echo number_format($report['metrics']['total_revenue'], 0); ?></div>
                <div class="metric-label">Të hyrat totale</div>
            </div>
            <div class="metric-card">
                <i class="fas fa-sync-alt" style="font-size: 1.5rem; margin-bottom: 0.5rem;"></i>
                <div class="metric-value"><?php echo $report['metrics']['total_completed_payments']; ?></div>
                <div class="metric-label">Pagesat e plotësuara</div>
            </div>
            <div class="metric-card">
                <i class="fas fa-exclamation-circle" style="font-size: 1.5rem; margin-bottom: 0.5rem;"></i>
                <div class="metric-value"><?php echo $report['metrics']['churn_rate_percent']; ?>%</div>
                <div class="metric-label">Norma Churn</div>
            </div>
        </div>

        <!-- Revenue by Payment Method -->
        <div class="section">
            <div class="section-title">
                <i class="fas fa-credit-card"></i> Të Hyrat sipas Metodës
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Metoda</th>
                        <th>Transaksione</th>
                        <th>Të hyrat</th>
                        <th>Mesatarea</th>
                        <th>Norma suksesi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['revenue_by_method'] as $method): ?>
                    <tr>
                        <td><strong><?php echo ucfirst(str_replace('_', ' ', $method['payment_method'])); ?></strong></td>
                        <td><?php echo $method['transactions']; ?></td>
                        <td><strong>€<?php echo number_format($method['total_revenue'], 2); ?></strong></td>
                        <td>€<?php echo number_format($method['avg_amount'], 2); ?></td>
                        <td><span class="badge"><?php echo $method['success_rate']; ?>%</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Notary Performance -->
        <div class="section">
            <div class="section-title">
                <i class="fas fa-user-tie"></i> Performanca e Notarëve (Top 20)
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Noter</th>
                        <th>Email</th>
                        <th>Pagesat</th>
                        <th>Të hyrat</th>
                        <th>Mesatarea</th>
                        <th>Dita pa pagim</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($report['notary_performance'], 0, 20) as $notary): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($notary['noter']); ?></strong></td>
                        <td><small><?php echo htmlspecialchars($notary['email']); ?></small></td>
                        <td><?php echo $notary['total_payments']; ?></td>
                        <td><strong>€<?php echo number_format($notary['total_revenue'], 2); ?></strong></td>
                        <td>€<?php echo number_format($notary['avg_payment'], 2); ?></td>
                        <td>
                            <span class="badge <?php echo ($notary['days_since_last_payment'] > 45) ? 'high' : 'low'; ?>">
                                <?php echo $notary['days_since_last_payment'] ?? '-'; ?> ditë
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Monthly Trends -->
        <div class="section">
            <div class="section-title">
                <i class="fas fa-chart-line"></i> Trendet Mujore
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Muaji</th>
                        <th>Transaksione</th>
                        <th>Të hyrat</th>
                        <th>Të hyrat të plotësuara</th>
                        <th>Norma suksesi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['monthly_trends'] as $trend): ?>
                    <tr>
                        <td><?php echo date('m/Y', strtotime($trend['month'] . '-01')); ?></td>
                        <td><?php echo $trend['transactions']; ?></td>
                        <td>€<?php echo number_format($trend['revenue'], 2); ?></td>
                        <td><strong>€<?php echo number_format($trend['completed_revenue'], 2); ?></strong></td>
                        <td><span class="badge"><?php echo $trend['success_rate']; ?>%</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="text-align: center; margin-top: 3rem; padding-top: 2rem; border-top: 2px solid #f0f0f0; color: #999;">
            <button onclick="window.print()" class="btn" style="background: #667eea; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                <i class="fas fa-print"></i> Printo Raportin
            </button>
            <p style="margin-top: 1rem; font-size: 0.9rem;">
                <a href="?format=json" style="color: #667eea; text-decoration: none;">Shkarko JSON</a> | 
                <a href="export_payments.php" style="color: #667eea; text-decoration: none;">Shkarko CSV</a>
            </p>
        </div>
    </div>
</body>
</html>

