<?php
// subscription_reports.php - Faqja për raportet dhe statistikat e abonimeve
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';

// Kontrollo autorizimin (vetëm administratorët mund ta aksesojnë këtë faqe)
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Merr statistikat për dashboard
try {
    // Totali i të ardhurave nga pagesat e abonimeve
    $stmt = $pdo->prepare("
        SELECT 
            SUM(amount) as total_revenue,
            COUNT(*) as total_payments
        FROM 
            subscription_payments
        WHERE 
            status = 'completed'
    ");
    $stmt->execute();
    $totalStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Të ardhurat sipas muajve për vitin aktual
    $stmt = $pdo->prepare("
        SELECT 
            MONTH(payment_date) as month,
            SUM(amount) as monthly_revenue,
            COUNT(*) as payment_count
        FROM 
            subscription_payments
        WHERE 
            status = 'completed'
            AND YEAR(payment_date) = YEAR(CURRENT_DATE())
        GROUP BY 
            MONTH(payment_date)
        ORDER BY 
            month ASC
    ");
    $stmt->execute();
    $monthlyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Numri i noterëve aktivë me abonim
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as active_noters
        FROM 
            noteri
        WHERE 
            status = 'active'
            AND subscription_status = 'active'
    ");
    $stmt->execute();
    $activeNoters = $stmt->fetchColumn();
    
    // Numri i noterëve joaktivë/pezulluar
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as inactive_noters
        FROM 
            noteri
        WHERE 
            status = 'active'
            AND (subscription_status = 'inactive' OR subscription_status = 'pending')
    ");
    $stmt->execute();
    $inactiveNoters = $stmt->fetchColumn();
    
    // Numri i pagesave të dështuara për muajin aktual
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as failed_count
        FROM 
            subscription_payments
        WHERE 
            status = 'failed'
            AND MONTH(payment_date) = MONTH(CURRENT_DATE())
            AND YEAR(payment_date) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute();
    $failedPayments = $stmt->fetchColumn();
    
    // Numri i noterëve me çmime të personalizuara
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as custom_price_count
        FROM 
            noteri
        WHERE 
            custom_price IS NOT NULL
    ");
    $stmt->execute();
    $customPriceCount = $stmt->fetchColumn();
    
    // Të ardhurat për muajin aktual
    $stmt = $pdo->prepare("
        SELECT 
            SUM(amount) as current_month_revenue,
            COUNT(*) as current_month_count
        FROM 
            subscription_payments
        WHERE 
            status = 'completed'
            AND MONTH(payment_date) = MONTH(CURRENT_DATE())
            AND YEAR(payment_date) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute();
    $currentMonthStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Shuma mesatare e abonimit
    $stmt = $pdo->prepare("
        SELECT 
            AVG(amount) as avg_amount
        FROM 
            subscription_payments
        WHERE 
            status = 'completed'
            AND YEAR(payment_date) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute();
    $avgAmount = $stmt->fetchColumn();
    
    // Ndërtimi i të dhënave për grafikë
    $monthNames = [
        1 => 'Janar', 2 => 'Shkurt', 3 => 'Mars', 4 => 'Prill', 
        5 => 'Maj', 6 => 'Qershor', 7 => 'Korrik', 8 => 'Gusht', 
        9 => 'Shtator', 10 => 'Tetor', 11 => 'Nëntor', 12 => 'Dhjetor'
    ];
    
    $chartData = [];
    $chartLabels = [];
    $chartValues = [];
    
    foreach ($monthNames as $monthNum => $monthName) {
        $found = false;
        foreach ($monthlyStats as $stat) {
            if ($stat['month'] == $monthNum) {
                $chartLabels[] = $monthName;
                $chartValues[] = $stat['monthly_revenue'];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $chartLabels[] = $monthName;
            $chartValues[] = 0;
        }
    }
    
} catch (PDOException $e) {
    $errorMessage = "Gabim në marrjen e të dhënave: " . $e->getMessage();
}

// Merr raportin për pagesat e abonimeve sipas statusit dhe metodës së pagesës
try {
    $stmt = $pdo->prepare("
        SELECT 
            status,
            payment_method,
            COUNT(*) as count,
            SUM(amount) as total_amount
        FROM 
            subscription_payments
        GROUP BY 
            status, payment_method
        ORDER BY 
            status ASC, count DESC
    ");
    $stmt->execute();
    $paymentMethodStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Përpuno të dhënat për grafik
    $paymentStatusData = [];
    $statusLabels = [];
    $statusValues = [];
    
    foreach ($paymentMethodStats as $stat) {
        if (!isset($paymentStatusData[$stat['status']])) {
            $paymentStatusData[$stat['status']] = 0;
        }
        $paymentStatusData[$stat['status']] += $stat['count'];
    }
    
    foreach ($paymentStatusData as $status => $count) {
        $statusLabels[] = ucfirst($status);
        $statusValues[] = $count;
    }
    
} catch (PDOException $e) {
    $errorMessage = "Gabim në marrjen e të dhënave të pagesave: " . $e->getMessage();
}

// Merr statistikat e abonimeve për vitin aktual dhe krahasimin me vitin e kaluar
try {
    // Të ardhurat për vitin aktual
    $stmt = $pdo->prepare("
        SELECT 
            SUM(amount) as current_year_revenue
        FROM 
            subscription_payments
        WHERE 
            status = 'completed'
            AND YEAR(payment_date) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute();
    $currentYearRevenue = $stmt->fetchColumn();
    
    // Të ardhurat për vitin e kaluar
    $stmt = $pdo->prepare("
        SELECT 
            SUM(amount) as last_year_revenue
        FROM 
            subscription_payments
        WHERE 
            status = 'completed'
            AND YEAR(payment_date) = YEAR(CURRENT_DATE()) - 1
    ");
    $stmt->execute();
    $lastYearRevenue = $stmt->fetchColumn();
    
    // Numri i pagesave për vitin aktual
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as current_year_count
        FROM 
            subscription_payments
        WHERE 
            status = 'completed'
            AND YEAR(payment_date) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute();
    $currentYearCount = $stmt->fetchColumn();
    
    // Numri i pagesave për vitin e kaluar
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as last_year_count
        FROM 
            subscription_payments
        WHERE 
            status = 'completed'
            AND YEAR(payment_date) = YEAR(CURRENT_DATE()) - 1
    ");
    $stmt->execute();
    $lastYearCount = $stmt->fetchColumn();
    
    // Llogarit ndryshimet në përqindje
    $revenueChange = 0;
    $countChange = 0;
    
    if ($lastYearRevenue > 0) {
        $revenueChange = (($currentYearRevenue - $lastYearRevenue) / $lastYearRevenue) * 100;
    }
    
    if ($lastYearCount > 0) {
        $countChange = (($currentYearCount - $lastYearCount) / $lastYearCount) * 100;
    }
    
} catch (PDOException $e) {
    $errorMessage = "Gabim në marrjen e të dhënave krahasuese: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raportet e Abonimeve | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            --info-color: #0ea5e9;
        }
        
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: var(--light-bg);
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .panel {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        h1 {
            color: var(--primary-color);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
        }
        
        h1 i {
            margin-right: 12px;
        }
        
        h2 {
            color: var(--heading-color);
            margin-top: 30px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        h2 i {
            margin-right: 10px;
            color: var(--primary-color);
        }
        
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 5px solid #dc2626;
        }
        
        .button {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
            border: none;
            text-decoration: none;
            font-family: inherit;
            font-weight: 500;
            font-size: 1rem;
            cursor: pointer;
            margin-right: 10px;
            transition: background-color 0.2s;
        }
        
        .button:hover {
            background-color: var(--primary-hover);
        }
        
        .button i {
            margin-right: 6px;
        }
        
        .button-secondary {
            background-color: var(--secondary-color);
        }
        
        .button-secondary:hover {
            background-color: #4b5563;
        }
        
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-label {
            color: var(--secondary-color);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-icon {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .stat-primary .stat-value, .stat-primary .stat-icon { color: var(--primary-color); }
        .stat-success .stat-value, .stat-success .stat-icon { color: var(--success-color); }
        .stat-warning .stat-value, .stat-warning .stat-icon { color: var(--warning-color); }
        .stat-danger .stat-value, .stat-danger .stat-icon { color: var(--danger-color); }
        .stat-info .stat-value, .stat-info .stat-icon { color: var(--info-color); }
        
        .stat-footer {
            margin-top: 15px;
            font-size: 0.9rem;
            color: var(--secondary-color);
        }
        
        .chart-container {
            position: relative;
            height: 400px;
            margin: 30px 0;
        }
        
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }
        
        .col {
            flex: 1;
            padding: 0 15px;
            min-width: 300px;
        }
        
        .table-container {
            margin: 20px 0;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            text-align: left;
        }
        
        th {
            background-color: #f1f5f9;
            color: var(--heading-color);
            font-weight: 600;
        }
        
        .change-positive {
            color: var(--success-color);
        }
        
        .change-negative {
            color: var(--danger-color);
        }
        
        .comparison-card {
            display: flex;
            align-items: center;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .comparison-data {
            flex: 1;
        }
        
        .comparison-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .comparison-values {
            display: flex;
            align-items: flex-end;
        }
        
        .comparison-current {
            font-size: 1.8rem;
            font-weight: bold;
        }
        
        .comparison-previous {
            font-size: 1.2rem;
            color: var(--secondary-color);
            margin-left: 10px;
        }
        
        .comparison-change {
            font-size: 1.2rem;
            font-weight: bold;
            padding: 8px 15px;
            border-radius: 8px;
            margin-left: 15px;
        }
        
        .report-section {
            margin-bottom: 40px;
        }
        
        .divider {
            height: 1px;
            background-color: var(--border-color);
            margin: 40px 0;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .row {
                flex-direction: column;
            }
            
            .toolbar {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .toolbar div {
                margin-top: 15px;
            }
            
            .comparison-card {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .comparison-change {
                margin-left: 0;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="toolbar">
            <h1><i class="fas fa-chart-line"></i> Raportet e Abonimeve</h1>
            
            <div>
                <a href="subscription_payments.php" class="button">
                    <i class="fas fa-file-invoice-dollar"></i> Pagesat
                </a>
                <a href="subscription_settings.php" class="button button-secondary">
                    <i class="fas fa-cog"></i> Konfigurimet
                </a>
                <a href="dashboard.php" class="button button-secondary">
                    <i class="fas fa-arrow-left"></i> Kthehu
                </a>
            </div>
        </div>
        
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-error">
                <?php echo $errorMessage; ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistikat kryesore -->
        <div class="panel">
            <h2><i class="fas fa-tachometer-alt"></i> Pasqyrë e përgjithshme</h2>
            
            <div class="stats-grid">
                <div class="stat-card stat-primary">
                    <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
                    <div class="stat-label">Të ardhurat totale</div>
                    <div class="stat-value">
                        <?php echo isset($totalStats['total_revenue']) ? number_format($totalStats['total_revenue'], 2) : '0.00'; ?> €
                    </div>
                    <div class="stat-footer">
                        Nga <?php echo isset($totalStats['total_payments']) ? number_format($totalStats['total_payments']) : '0'; ?> pagesa
                    </div>
                </div>
                
                <div class="stat-card stat-success">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-label">Të ardhurat e muajit</div>
                    <div class="stat-value">
                        <?php echo isset($currentMonthStats['current_month_revenue']) ? number_format($currentMonthStats['current_month_revenue'], 2) : '0.00'; ?> €
                    </div>
                    <div class="stat-footer">
                        Nga <?php echo isset($currentMonthStats['current_month_count']) ? number_format($currentMonthStats['current_month_count']) : '0'; ?> pagesa
                    </div>
                </div>
                
                <div class="stat-card stat-info">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-label">Noterë aktivë</div>
                    <div class="stat-value">
                        <?php echo isset($activeNoters) ? number_format($activeNoters) : '0'; ?>
                    </div>
                    <div class="stat-footer">
                        <?php echo isset($inactiveNoters) ? number_format($inactiveNoters) : '0'; ?> joaktivë/pezulluar
                    </div>
                </div>
                
                <div class="stat-card stat-warning">
                    <div class="stat-icon"><i class="fas fa-tag"></i></div>
                    <div class="stat-label">Shuma mesatare</div>
                    <div class="stat-value">
                        <?php echo isset($avgAmount) ? number_format($avgAmount, 2) : '0.00'; ?> €
                    </div>
                    <div class="stat-footer">
                        <?php echo isset($customPriceCount) ? number_format($customPriceCount) : '0'; ?> noterë me çmim të personalizuar
                    </div>
                </div>
            </div>
            
            <!-- Krahasimi i vitit aktual me vitin e kaluar -->
            <div class="row" style="margin-top: 30px;">
                <div class="col">
                    <div class="comparison-card">
                        <div class="comparison-data">
                            <div class="comparison-title">Të ardhurat vjetore</div>
                            <div class="comparison-values">
                                <div class="comparison-current">
                                    <?php echo isset($currentYearRevenue) ? number_format($currentYearRevenue, 2) : '0.00'; ?> €
                                </div>
                                <div class="comparison-previous">
                                    vs <?php echo isset($lastYearRevenue) ? number_format($lastYearRevenue, 2) : '0.00'; ?> €
                                </div>
                            </div>
                        </div>
                        <?php if (isset($revenueChange)): ?>
                            <div class="comparison-change <?php echo $revenueChange >= 0 ? 'change-positive' : 'change-negative'; ?>">
                                <?php echo $revenueChange >= 0 ? '+' : ''; ?><?php echo number_format($revenueChange, 1); ?>%
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col">
                    <div class="comparison-card">
                        <div class="comparison-data">
                            <div class="comparison-title">Numri i pagesave</div>
                            <div class="comparison-values">
                                <div class="comparison-current">
                                    <?php echo isset($currentYearCount) ? number_format($currentYearCount) : '0'; ?>
                                </div>
                                <div class="comparison-previous">
                                    vs <?php echo isset($lastYearCount) ? number_format($lastYearCount) : '0'; ?>
                                </div>
                            </div>
                        </div>
                        <?php if (isset($countChange)): ?>
                            <div class="comparison-change <?php echo $countChange >= 0 ? 'change-positive' : 'change-negative'; ?>">
                                <?php echo $countChange >= 0 ? '+' : ''; ?><?php echo number_format($countChange, 1); ?>%
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Grafiku i të ardhurave mujore -->
        <div class="panel">
            <h2><i class="fas fa-chart-bar"></i> Të ardhurat sipas muajve (<?php echo date('Y'); ?>)</h2>
            
            <div class="chart-container">
                <canvas id="monthlyRevenueChart"></canvas>
            </div>
        </div>
        
        <!-- Grafiku i statusit të pagesave -->
        <div class="row">
            <div class="col">
                <div class="panel">
                    <h2><i class="fas fa-chart-pie"></i> Statusi i pagesave</h2>
                    
                    <div class="chart-container" style="height: 350px;">
                        <canvas id="paymentStatusChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col">
                <div class="panel">
                    <h2><i class="fas fa-table"></i> Pagesat sipas metodës</h2>
                    
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Statusi</th>
                                    <th>Metoda e pagesës</th>
                                    <th>Numri</th>
                                    <th>Shuma</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (isset($paymentMethodStats) && count($paymentMethodStats) > 0): ?>
                                    <?php foreach ($paymentMethodStats as $stat): ?>
                                        <tr>
                                            <td><?php echo ucfirst(htmlspecialchars($stat['status'])); ?></td>
                                            <td><?php echo ucfirst(htmlspecialchars($stat['payment_method'])); ?></td>
                                            <td><?php echo number_format($stat['count']); ?></td>
                                            <td><?php echo number_format($stat['total_amount'], 2); ?> €</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4">Nuk ka të dhëna për pagesat.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="divider"></div>
        
        <!-- Linqet për raportet e tjera dhe veprime -->
        <div class="panel">
            <h2><i class="fas fa-file-download"></i> Eksporto raportet</h2>
            
            <p>Zgjidhni një nga opsionet e mëposhtme për të eksportuar raportet e abonimeve:</p>
            
            <div style="margin: 20px 0;">
                <a href="subscription_reports_export.php?type=monthly&format=csv" class="button">
                    <i class="fas fa-file-csv"></i> Raporti mujor (CSV)
                </a>
                
                <a href="subscription_reports_export.php?type=yearly&format=csv" class="button">
                    <i class="fas fa-file-csv"></i> Raporti vjetor (CSV)
                </a>
                
                <a href="subscription_reports_export.php?type=detailed&format=csv" class="button">
                    <i class="fas fa-file-csv"></i> Raport i detajuar (CSV)
                </a>
                
                <a href="#" class="button button-secondary" onclick="printReport()">
                    <i class="fas fa-print"></i> Printo raportin aktual
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Grafiku i të ardhurave mujore
        const monthlyRevenueChart = new Chart(
            document.getElementById('monthlyRevenueChart'),
            {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($chartLabels); ?>,
                    datasets: [{
                        label: 'Të ardhurat (EUR)',
                        data: <?php echo json_encode($chartValues); ?>,
                        backgroundColor: 'rgba(26, 86, 219, 0.7)',
                        borderColor: '#1a56db',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value + ' €';
                                }
                            }
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Të ardhurat mujore nga abonimet për vitin <?php echo date('Y'); ?>'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.raw.toFixed(2) + ' €';
                                }
                            }
                        }
                    }
                }
            }
        );
        
        // Grafiku i statusit të pagesave
        const paymentStatusChart = new Chart(
            document.getElementById('paymentStatusChart'),
            {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($statusLabels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($statusValues); ?>,
                        backgroundColor: [
                            '#16a34a', // completed - green
                            '#f59e0b', // pending - yellow
                            '#dc2626', // failed - red
                            '#6b7280'  // test - gray
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        title: {
                            display: true,
                            text: 'Pagesat sipas statusit'
                        }
                    }
                }
            }
        );
        
        // Funksion për printimin e raportit
        function printReport() {
            window.print();
        }
    </script>
</body>
</html>