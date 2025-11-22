<?php
// Admin Authentication Check
session_start();
if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header("Location: admin_login.php");
    exit();
}

require_once 'db_connection.php';

// Get analytics data
$analytics = [];

// 1. User Statistics
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_30d,
    SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as active_users_7d
FROM users");
$stmt->execute();
$analytics['users'] = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 2. Revenue Statistics
$stmt = $conn->prepare("SELECT 
    SUM(amount) as total_revenue,
    COUNT(*) as total_payments,
    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN amount ELSE 0 END) as revenue_30d,
    AVG(amount) as avg_payment_value,
    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as completed_revenue,
    SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_revenue
FROM payments");
$stmt->execute();
$analytics['revenue'] = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 3. Video Call Statistics
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total_calls,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_calls,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_calls,
    AVG(TIMESTAMPDIFF(SECOND, start_time, end_time)) as avg_call_duration_seconds,
    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as calls_30d
FROM video_calls");
$stmt->execute();
$analytics['calls'] = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 4. Reservation Statistics
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total_reservations,
    COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled
FROM reservations");
$stmt->execute();
$analytics['reservations'] = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 5. Subscription Statistics
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total_subscriptions,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_subs,
    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_subs,
    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_subs_30d
FROM subscriptions");
$stmt->execute();
$analytics['subscriptions'] = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 6. E-Signature Statistics
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total_envelopes,
    COUNT(CASE WHEN status = 'signed' THEN 1 END) as signed,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN status = 'declined' THEN 1 END) as declined,
    AVG(TIMESTAMPDIFF(HOUR, created_at, signed_at)) as avg_signature_time_hours
FROM docusign_envelopes WHERE signed_at IS NOT NULL");
$stmt->execute();
$analytics['signatures'] = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 7. Conversion Funnel
$total_users = $analytics['users']['total_users'] ?? 0;
$active_users = $analytics['users']['active_users_7d'] ?? 0;
$total_payments = $analytics['revenue']['total_payments'] ?? 0;
$total_calls = $analytics['calls']['total_calls'] ?? 0;

$analytics['funnel'] = [
    'registered_users' => $total_users,
    'active_users_7d' => $active_users,
    'users_made_payment' => $total_payments,
    'users_made_call' => $total_calls,
    'activation_rate' => $total_users > 0 ? round(($active_users / $total_users) * 100, 2) : 0,
    'conversion_rate' => $total_users > 0 ? round(($total_payments / $total_users) * 100, 2) : 0
];

// 8. Revenue by Service (30 days)
$stmt = $conn->prepare("SELECT 
    'Video Call' as service, 
    COUNT(*) as count, 
    0 as amount
FROM video_calls WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
UNION ALL
SELECT 
    'Reservation' as service,
    COUNT(*) as count,
    SUM(COALESCE(amount, 0)) as amount
FROM reservations WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
UNION ALL
SELECT 
    'Subscription' as service,
    COUNT(*) as count,
    SUM(COALESCE(amount, 0)) as amount
FROM subscriptions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt->execute();
$service_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Daily revenue for chart (last 30 days)
$stmt = $conn->prepare("SELECT 
    DATE(created_at) as date,
    COUNT(*) as transactions,
    SUM(amount) as daily_revenue
FROM payments 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
AND status = 'completed'
GROUP BY DATE(created_at)
ORDER BY date DESC");
$stmt->execute();
$daily_revenue = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Analytics - Noteria Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 2rem;
            margin-bottom: 8px;
        }
        
        .header p {
            opacity: 0.9;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
        }
        
        .stat-card.revenue {
            border-left-color: #27ae60;
        }
        
        .stat-card.calls {
            border-left-color: #3498db;
        }
        
        .stat-card.users {
            border-left-color: #e74c3c;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #999;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 4px;
        }
        
        .stat-change {
            font-size: 0.85rem;
            color: #27ae60;
        }
        
        .stat-change.negative {
            color: #e74c3c;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .chart-container h3 {
            margin-bottom: 20px;
            color: #333;
            font-size: 1.1rem;
        }
        
        .chart-wrapper {
            position: relative;
            height: 300px;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .table-container h3 {
            margin-bottom: 20px;
            color: #333;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f5f7fa;
            padding: 12px;
            text-align: left;
            font-weight: 700;
            color: #333;
            border-bottom: 2px solid #e2eafc;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #e2eafc;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
        
        .funnel-item {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .funnel-bar {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
            height: 30px;
            display: flex;
            align-items: center;
            padding: 0 12px;
            color: white;
            font-weight: 600;
            min-width: 200px;
        }
        
        .funnel-label {
            margin-left: 12px;
            font-weight: 600;
            color: #333;
            min-width: 150px;
        }
        
        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .stat-card .stat-value {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-chart-line"></i> Advanced Analytics Dashboard</h1>
            <p>Statistika dhe analiza në kohë reale të platformës Noteria</p>
        </div>
        
        <!-- Key Metrics -->
        <div class="stats-grid">
            <div class="stat-card users">
                <div class="stat-label"><i class="fas fa-users"></i> Përdoruesit Totalë</div>
                <div class="stat-value"><?php echo number_format($analytics['users']['total_users'] ?? 0); ?></div>
                <div class="stat-change">
                    <?php echo number_format($analytics['users']['new_users_30d'] ?? 0); ?> të rinj në 30 ditë
                </div>
            </div>
            
            <div class="stat-card revenue">
                <div class="stat-label"><i class="fas fa-euro-sign"></i> Të Ardhura Totale</div>
                <div class="stat-value">€<?php echo number_format($analytics['revenue']['total_revenue'] ?? 0, 2); ?></div>
                <div class="stat-change">
                    €<?php echo number_format($analytics['revenue']['revenue_30d'] ?? 0, 2); ?> në 30 ditë
                </div>
            </div>
            
            <div class="stat-card calls">
                <div class="stat-label"><i class="fas fa-phone"></i> Video Thirrje</div>
                <div class="stat-value"><?php echo number_format($analytics['calls']['total_calls'] ?? 0); ?></div>
                <div class="stat-change">
                    Ø <?php echo round(($analytics['calls']['avg_call_duration_seconds'] ?? 0) / 60); ?> min
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label"><i class="fas fa-pen-fancy"></i> E-Nënshkrime</div>
                <div class="stat-value"><?php echo number_format($analytics['signatures']['total_envelopes'] ?? 0); ?></div>
                <div class="stat-change">
                    ✓ <?php echo number_format($analytics['signatures']['signed'] ?? 0); ?> nënshkruar
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label"><i class="fas fa-calendar"></i> Rezervimet</div>
                <div class="stat-value"><?php echo number_format($analytics['reservations']['total_reservations'] ?? 0); ?></div>
                <div class="stat-change">
                    ✓ <?php echo number_format($analytics['reservations']['completed'] ?? 0); ?> të përfunduara
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label"><i class="fas fa-gem"></i> Abonimet</div>
                <div class="stat-value"><?php echo number_format($analytics['subscriptions']['active_subs'] ?? 0); ?></div>
                <div class="stat-change">
                    + <?php echo number_format($analytics['subscriptions']['new_subs_30d'] ?? 0); ?> në 30 ditë
                </div>
            </div>
        </div>
        
        <!-- Conversion Funnel -->
        <div class="table-container">
            <h3><i class="fas fa-filter"></i> Conversion Funnel</h3>
            <div>
                <div class="funnel-item">
                    <div class="funnel-bar" style="width: 100%;">
                        <?php echo number_format($analytics['funnel']['registered_users']); ?> Përdorues
                    </div>
                    <div class="funnel-label">Regjistruar</div>
                </div>
                
                <div class="funnel-item">
                    <div class="funnel-bar" style="width: <?php echo $analytics['funnel']['activation_rate']; ?>%;">
                        <?php echo number_format($analytics['funnel']['active_users_7d']); ?>
                    </div>
                    <div class="funnel-label">Aktiv (7d): <?php echo $analytics['funnel']['activation_rate']; ?>%</div>
                </div>
                
                <div class="funnel-item">
                    <div class="funnel-bar" style="width: <?php echo $analytics['funnel']['conversion_rate']; ?>%;">
                        <?php echo number_format($analytics['funnel']['users_made_payment']); ?>
                    </div>
                    <div class="funnel-label">Pagesa: <?php echo $analytics['funnel']['conversion_rate']; ?>%</div>
                </div>
                
                <div class="funnel-item">
                    <div class="funnel-bar" style="width: <?php echo min(100, ($analytics['funnel']['users_made_call'] / max(1, $analytics['funnel']['registered_users'])) * 100); ?>%;">
                        <?php echo number_format($analytics['funnel']['users_made_call']); ?>
                    </div>
                    <div class="funnel-label">Video Thirrje: <?php echo round(($analytics['funnel']['users_made_call'] / max(1, $analytics['funnel']['registered_users'])) * 100, 2); ?>%</div>
                </div>
            </div>
        </div>
        
        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-container">
                <h3>Të Ardhurat - 30 ditë të fundit</h3>
                <div class="chart-wrapper">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
            
            <div class="chart-container">
                <h3>Shërbimeve Sipas Tipit</h3>
                <div class="chart-wrapper">
                    <canvas id="servicesChart"></canvas>
                </div>
            </div>
            
            <div class="chart-container">
                <h3>Rezervimet sipas Statusit</h3>
                <div class="chart-wrapper">
                    <canvas id="reservationsChart"></canvas>
                </div>
            </div>
            
            <div class="chart-container">
                <h3>E-Nënshkrime sipas Statusit</h3>
                <div class="chart-wrapper">
                    <canvas id="signaturesChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Service Breakdown Table -->
        <div class="table-container">
            <h3><i class="fas fa-list"></i> Performanca e Shërbimeve (30 ditë)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Shërbimi</th>
                        <th>Numri</th>
                        <th>Të Ardhura</th>
                        <th>Mesatarja për Transaksion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($service_data as $service): ?>
                    <tr>
                        <td><?php echo $service['service']; ?></td>
                        <td><?php echo number_format($service['count']); ?></td>
                        <td>€<?php echo number_format($service['amount'] ?? 0, 2); ?></td>
                        <td>€<?php echo number_format($service['count'] > 0 ? ($service['amount'] ?? 0) / $service['count'] : 0, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($d) { return date('d.m', strtotime($d['date'])); }, array_reverse($daily_revenue))); ?>,
                datasets: [{
                    label: 'Të Ardhura Ditore (€)',
                    data: <?php echo json_encode(array_map(function($d) { return (float)$d['daily_revenue']; }, array_reverse($daily_revenue))); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '€' + value.toFixed(0);
                            }
                        }
                    }
                }
            }
        });
        
        // Services Chart
        const servicesCtx = document.getElementById('servicesChart').getContext('2d');
        new Chart(servicesCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_map(function($s) { return $s['service']; }, $service_data)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_map(function($s) { return (int)$s['count']; }, $service_data)); ?>,
                    backgroundColor: [
                        '#667eea',
                        '#764ba2',
                        '#3498db'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Reservations Chart
        const reservCtx = document.getElementById('reservationsChart').getContext('2d');
        new Chart(reservCtx, {
            type: 'pie',
            data: {
                labels: ['Përfunduar', 'Në Pritur', 'Anuluar', 'Konfirmuar'],
                datasets: [{
                    data: [
                        <?php echo $analytics['reservations']['completed'] ?? 0; ?>,
                        <?php echo $analytics['reservations']['pending'] ?? 0; ?>,
                        <?php echo $analytics['reservations']['cancelled'] ?? 0; ?>,
                        <?php echo $analytics['reservations']['confirmed'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        '#27ae60',
                        '#f39c12',
                        '#e74c3c',
                        '#3498db'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Signatures Chart
        const sigCtx = document.getElementById('signaturesChart').getContext('2d');
        new Chart(sigCtx, {
            type: 'bar',
            data: {
                labels: ['Nënshkruar', 'Në Pritur', 'Refuzuar'],
                datasets: [{
                    label: 'Numri',
                    data: [
                        <?php echo $analytics['signatures']['signed'] ?? 0; ?>,
                        <?php echo $analytics['signatures']['pending'] ?? 0; ?>,
                        <?php echo $analytics['signatures']['declined'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        '#27ae60',
                        '#f39c12',
                        '#e74c3c'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
