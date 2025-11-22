<?php
// api_monitor.php - Mjeti për monitorimin e trafikut dhe përdorimit të API
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';
session_start();

// Kontrollo nëse përdoruesi është i autentifikuar si admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Funksion për të formatuar numrat e mëdhenj
function formatNumber($num) {
    if ($num > 1000000) {
        return round($num / 1000000, 2) . "M";
    }
    if ($num > 1000) {
        return round($num / 1000, 2) . "K";
    }
    return $num;
}

// Funksion për të formatuar kohen në milisekonda
function formatTime($ms) {
    if ($ms < 1) {
        return round($ms * 1000) . "μs";
    }
    if ($ms < 1000) {
        return round($ms) . "ms";
    }
    return round($ms / 1000, 2) . "s";
}

// Merr statistikat për endpoints
try {
    // Merr top endpoints sipas trafikut
    $stmt = $pdo->query("
        SELECT 
            endpoint, 
            COUNT(*) as numRequests, 
            AVG(response_time) as avgTime, 
            MAX(response_time) as maxTime,
            MIN(response_time) as minTime,
            SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errors,
            MAX(timestamp) as lastAccess
        FROM api_logs 
        GROUP BY endpoint 
        ORDER BY numRequests DESC
    ");
    $endpoints = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Merr top klientët (IP adresat)
    $stmt = $pdo->query("
        SELECT 
            client_ip, 
            COUNT(*) as numRequests,
            AVG(response_time) as avgTime,
            MAX(timestamp) as lastAccess
        FROM api_logs 
        GROUP BY client_ip 
        ORDER BY numRequests DESC
        LIMIT 10
    ");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Merr statistikat e përgjithshme
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as totalRequests,
            AVG(response_time) as avgResponseTime,
            MAX(response_time) as maxResponseTime,
            SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as totalErrors,
            COUNT(DISTINCT client_ip) as uniqueClients,
            MAX(timestamp) as lastRequest,
            MIN(timestamp) as firstRequest
        FROM api_logs
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Merr statistikat për metodat HTTP
    $stmt = $pdo->query("
        SELECT 
            method, 
            COUNT(*) as count 
        FROM api_logs 
        GROUP BY method 
        ORDER BY count DESC
    ");
    $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Merr statistikat për kodet e statusit
    $stmt = $pdo->query("
        SELECT 
            status_code, 
            COUNT(*) as count 
        FROM api_logs 
        GROUP BY status_code 
        ORDER BY count DESC
    ");
    $statusCodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Merr të dhënat e trafikut për 24 orët e fundit (për grafik)
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(timestamp, '%H:00') as hour,
            COUNT(*) as requests,
            AVG(response_time) as avgTime,
            SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errors
        FROM api_logs
        WHERE timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY DATE_FORMAT(timestamp, '%Y-%m-%d %H')
        ORDER BY timestamp
    ");
    $hourlyTraffic = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Gabim në marrjen e të dhënave: " . $e->getMessage();
}

// Për pastrimin e logs nga admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    $daysToKeep = isset($_POST['days_to_keep']) ? intval($_POST['days_to_keep']) : 30;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM api_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$daysToKeep]);
        $rowsDeleted = $stmt->rowCount();
        $success = "U fshinë me sukses $rowsDeleted regjistrime më të vjetra se $daysToKeep ditë.";
        
        // Refresh të dhënat pas pastrimit
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (PDOException $e) {
        $error = "Gabim në pastrimin e regjistrimeve: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Monitori | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #1a56db;
            --secondary-color: #6b7280;
            --success-color: #16a34a;
            --warning-color: #f59e0b;
            --danger-color: #dc2626;
            --light-bg: #f8fafc;
            --border-color: #e2e8f0;
            --text-color: #374151;
        }
        
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            margin: 0;
            padding: 20px;
            background-color: var(--light-bg);
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
            color: #2563eb;
            font-size: 1.4rem;
            margin-top: 30px;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .message {
            padding: 12px 15px;
            margin: 20px 0;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .success {
            background-color: #dcfce7;
            color: #166534;
            border-left: 5px solid #16a34a;
        }
        
        .error {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 5px solid #dc2626;
        }
        
        .warning {
            background-color: #fef3c7;
            color: #92400e;
            border-left: 5px solid #f59e0b;
        }
        
        .info {
            background-color: #dbeafe;
            color: #1e40af;
            border-left: 5px solid #2563eb;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-value {
            font-size: 2.2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin: 10px 0;
        }
        
        .stat-label {
            font-size: 1rem;
            color: var(--secondary-color);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-icon {
            font-size: 2rem;
            color: var(--primary-color);
            opacity: 0.8;
        }
        
        .chart-container {
            margin-top: 30px;
            position: relative;
            height: 400px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0 30px;
            font-size: 0.95rem;
        }
        
        th, td {
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            text-align: left;
        }
        
        th {
            background-color: #f1f5f9;
            font-weight: 600;
            color: var(--text-color);
        }
        
        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        
        tr:hover {
            background-color: #f1f5f9;
        }
        
        .table-container {
            overflow-x: auto;
            margin-bottom: 30px;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
            text-align: center;
            min-width: 80px;
        }
        
        .badge-success { background-color: var(--success-color); }
        .badge-warning { background-color: var(--warning-color); }
        .badge-danger { background-color: var(--danger-color); }
        .badge-primary { background-color: var(--primary-color); }
        .badge-secondary { background-color: var(--secondary-color); }
        
        .method-badge {
            display: inline-block;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
        }
        
        .method-get { background-color: #22c55e; }
        .method-post { background-color: #3b82f6; }
        .method-put { background-color: #f59e0b; }
        .method-delete { background-color: #ef4444; }
        .method-patch { background-color: #8b5cf6; }
        
        .two-cols {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .two-cols {
                grid-template-columns: 1fr;
            }
        }
        
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .button {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s;
        }
        
        .button:hover {
            background-color: #1e40af;
            transform: translateY(-2px);
        }
        
        .button i {
            margin-right: 8px;
        }
        
        .form-inline {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        input[type="number"] {
            padding: 8px 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            width: 80px;
        }
        
        .tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 25px;
        }
        
        .tab {
            padding: 12px 20px;
            cursor: pointer;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            font-weight: 600;
            color: var(--secondary-color);
            transition: all 0.2s;
        }
        
        .tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .refresh {
            color: var(--secondary-color);
            display: block;
            text-align: right;
            margin: 10px 0;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-chart-line"></i> Monitori i API</h1>
        
        <div class="toolbar">
            <div>
                <a href="api_client_test.php" class="button">
                    <i class="fas fa-flask"></i> Test Client
                </a>
                <a href="api_docs.php" class="button" style="background-color: var(--secondary-color);">
                    <i class="fas fa-book"></i> Dokumentimi
                </a>
            </div>
            
            <form method="post" class="form-inline">
                <label for="days_to_keep">Ruaj logs për:</label>
                <input type="number" id="days_to_keep" name="days_to_keep" value="30" min="1" max="365">
                <label>ditë</label>
                <button type="submit" name="clear_logs" class="button" style="background-color: var(--danger-color);" onclick="return confirm('Jeni i sigurt se dëshironi të pastroni logset e vjetra?');">
                    <i class="fas fa-trash"></i> Pastro logs e vjetra
                </button>
            </form>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Statistikat kryesore -->
        <div class="grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="stat-value"><?php echo formatNumber($stats['totalRequests'] ?? 0); ?></div>
                <div class="stat-label">Kërkesa totale</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-value"><?php echo formatNumber($stats['totalErrors'] ?? 0); ?></div>
                <div class="stat-label">Gabime totale</div>
                <div style="font-size: 0.9rem; color: var(--secondary-color);">
                    <?php 
                    if (isset($stats['totalRequests']) && $stats['totalRequests'] > 0) {
                        $errorRate = round(($stats['totalErrors'] / $stats['totalRequests']) * 100, 2);
                        echo "($errorRate% e kërkesave)";
                    }
                    ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-tachometer-alt"></i>
                </div>
                <div class="stat-value"><?php echo formatTime($stats['avgResponseTime'] ?? 0); ?></div>
                <div class="stat-label">Koha mesatare</div>
                <div style="font-size: 0.9rem; color: var(--secondary-color);">
                    Max: <?php echo formatTime($stats['maxResponseTime'] ?? 0); ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo formatNumber($stats['uniqueClients'] ?? 0); ?></div>
                <div class="stat-label">Klientë unikë</div>
            </div>
        </div>
        
        <!-- Grafiku i trafikut -->
        <div class="panel">
            <h2>Trafiku i API (24 orët e fundit)</h2>
            <div class="chart-container">
                <canvas id="trafficChart"></canvas>
            </div>
            <span class="refresh">Përditësimi i fundit: <?php echo date('Y-m-d H:i:s'); ?></span>
        </div>
        
        <!-- Tabs për të dhënat e detajuara -->
        <div class="panel">
            <div class="tabs">
                <button class="tab active" data-tab="endpoints">
                    <i class="fas fa-link"></i> Endpoints
                </button>
                <button class="tab" data-tab="clients">
                    <i class="fas fa-user-friends"></i> Klientët
                </button>
                <button class="tab" data-tab="methods">
                    <i class="fas fa-code"></i> Metodat
                </button>
                <button class="tab" data-tab="status">
                    <i class="fas fa-info-circle"></i> Kodet e statusit
                </button>
            </div>
            
            <!-- Endpoints Tab -->
            <div class="tab-content active" id="endpoints-tab">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Endpoint</th>
                                <th>Kërkesa</th>
                                <th>Koha mesatare</th>
                                <th>Koha min/max</th>
                                <th>Gabime</th>
                                <th>Përdorimi i fundit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($endpoints)): ?>
                                <?php foreach ($endpoints as $endpoint): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($endpoint['endpoint']); ?></td>
                                        <td><?php echo formatNumber($endpoint['numRequests']); ?></td>
                                        <td><?php echo formatTime($endpoint['avgTime']); ?></td>
                                        <td><?php echo formatTime($endpoint['minTime']) . ' / ' . formatTime($endpoint['maxTime']); ?></td>
                                        <td>
                                            <?php
                                            $errorPercent = ($endpoint['numRequests'] > 0) ? 
                                                round(($endpoint['errors'] / $endpoint['numRequests']) * 100, 2) : 0;
                                            
                                            $badgeClass = 'badge-success';
                                            if ($errorPercent > 5) $badgeClass = 'badge-warning';
                                            if ($errorPercent > 20) $badgeClass = 'badge-danger';
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>">
                                                <?php echo $endpoint['errors']; ?> (<?php echo $errorPercent; ?>%)
                                            </span>
                                        </td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($endpoint['lastAccess'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">Nuk u gjetën të dhëna për endpoints.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Clients Tab -->
            <div class="tab-content" id="clients-tab">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>IP Adresa</th>
                                <th>Numri i kërkesave</th>
                                <th>Koha mesatare</th>
                                <th>Aksesi i fundit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($clients)): ?>
                                <?php foreach ($clients as $client): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($client['client_ip']); ?></td>
                                        <td><?php echo formatNumber($client['numRequests']); ?></td>
                                        <td><?php echo formatTime($client['avgTime']); ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($client['lastAccess'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center;">Nuk u gjetën të dhëna për klientët.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Methods Tab -->
            <div class="tab-content" id="methods-tab">
                <div class="two-cols">
                    <div>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Metoda</th>
                                        <th>Numri i kërkesave</th>
                                        <th>Përqindja</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($methods)): ?>
                                        <?php foreach ($methods as $method): ?>
                                            <tr>
                                                <td>
                                                    <?php
                                                    $methodClass = 'method-' . strtolower($method['method']);
                                                    if (!in_array(strtolower($method['method']), ['get', 'post', 'put', 'delete', 'patch'])) {
                                                        $methodClass = 'method-get'; // Default
                                                    }
                                                    ?>
                                                    <span class="method-badge <?php echo $methodClass; ?>">
                                                        <?php echo htmlspecialchars($method['method']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatNumber($method['count']); ?></td>
                                                <td>
                                                    <?php 
                                                    if ($stats['totalRequests'] > 0) {
                                                        echo round(($method['count'] / $stats['totalRequests']) * 100, 2) . '%';
                                                    } else {
                                                        echo '0%';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" style="text-align: center;">Nuk u gjetën të dhëna për metodat.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div>
                        <canvas id="methodsChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Status Codes Tab -->
            <div class="tab-content" id="status-tab">
                <div class="two-cols">
                    <div>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Kodi i Statusit</th>
                                        <th>Numri i kërkesave</th>
                                        <th>Përqindja</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($statusCodes)): ?>
                                        <?php foreach ($statusCodes as $status): ?>
                                            <tr>
                                                <td>
                                                    <?php
                                                    $badgeClass = 'badge-success';
                                                    if ($status['status_code'] >= 300 && $status['status_code'] < 400) $badgeClass = 'badge-warning';
                                                    if ($status['status_code'] >= 400) $badgeClass = 'badge-danger';
                                                    ?>
                                                    <span class="badge <?php echo $badgeClass; ?>">
                                                        <?php echo htmlspecialchars($status['status_code']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatNumber($status['count']); ?></td>
                                                <td>
                                                    <?php 
                                                    if ($stats['totalRequests'] > 0) {
                                                        echo round(($status['count'] / $stats['totalRequests']) * 100, 2) . '%';
                                                    } else {
                                                        echo '0%';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" style="text-align: center;">Nuk u gjetën të dhëna për kodet e statusit.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div>
                        <canvas id="statusChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Inicializimi i tab-ave
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Heq klasën 'active' nga të gjithë tabs
                document.querySelectorAll('.tab').forEach(t => {
                    t.classList.remove('active');
                });
                
                // Heq klasën 'active' nga të gjithë përmbajtjet e tab-ave
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                
                // Shton klasën 'active' për tab-in e klikuar
                this.classList.add('active');
                
                // Shton klasën 'active' për përmbajtjen përkatëse të tab-it
                const tabId = this.getAttribute('data-tab') + '-tab';
                document.getElementById(tabId).classList.add('active');
            });
        });
        
        // Grafiku i trafikut (24 orët e fundit)
        const trafficData = <?php echo json_encode($hourlyTraffic); ?>;
        const hours = trafficData.map(item => item.hour);
        const requestsData = trafficData.map(item => parseInt(item.requests));
        const avgTimeData = trafficData.map(item => parseFloat(item.avgTime));
        const errorData = trafficData.map(item => parseInt(item.errors));
        
        const trafficCtx = document.getElementById('trafficChart').getContext('2d');
        new Chart(trafficCtx, {
            type: 'line',
            data: {
                labels: hours,
                datasets: [
                    {
                        label: 'Numri i kërkesave',
                        data: requestsData,
                        borderColor: 'rgb(26, 86, 219)',
                        backgroundColor: 'rgba(26, 86, 219, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Koha mesatare (ms)',
                        data: avgTimeData,
                        borderColor: 'rgb(245, 158, 11)',
                        backgroundColor: 'rgba(245, 158, 11, 0)',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        tension: 0.4,
                        yAxisID: 'y1'
                    },
                    {
                        label: 'Gabime',
                        data: errorData,
                        borderColor: 'rgb(220, 38, 38)',
                        backgroundColor: 'rgba(220, 38, 38, 0.5)',
                        borderWidth: 1,
                        type: 'bar',
                        yAxisID: 'y'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Ora'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Numri i kërkesave'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                        title: {
                            display: true,
                            text: 'Koha mesatare (ms)'
                        }
                    }
                }
            }
        });
        
        // Grafiku i metodave
        const methodsData = <?php echo json_encode($methods); ?>;
        const methodLabels = methodsData.map(item => item.method);
        const methodCounts = methodsData.map(item => parseInt(item.count));
        const methodColors = methodsData.map(item => {
            const method = item.method.toLowerCase();
            if (method === 'get') return 'rgb(34, 197, 94)';
            if (method === 'post') return 'rgb(59, 130, 246)';
            if (method === 'put') return 'rgb(245, 158, 11)';
            if (method === 'delete') return 'rgb(239, 68, 68)';
            if (method === 'patch') return 'rgb(139, 92, 246)';
            return 'rgb(107, 114, 128)';
        });
        
        const methodsCtx = document.getElementById('methodsChart').getContext('2d');
        new Chart(methodsCtx, {
            type: 'doughnut',
            data: {
                labels: methodLabels,
                datasets: [{
                    data: methodCounts,
                    backgroundColor: methodColors,
                    borderColor: 'white',
                    borderWidth: 1
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
                        text: 'Kërkesa sipas metodave HTTP'
                    }
                }
            }
        });
        
        // Grafiku i kodeve të statusit
        const statusData = <?php echo json_encode($statusCodes); ?>;
        const statusLabels = statusData.map(item => item.status_code);
        const statusCounts = statusData.map(item => parseInt(item.count));
        const statusColors = statusData.map(item => {
            const code = parseInt(item.status_code);
            if (code < 300) return 'rgb(34, 197, 94)';
            if (code < 400) return 'rgb(245, 158, 11)';
            if (code < 500) return 'rgb(239, 68, 68)';
            return 'rgb(107, 114, 128)';
        });
        
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusCounts,
                    backgroundColor: statusColors,
                    borderColor: 'white',
                    borderWidth: 1
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
                        text: 'Kërkesa sipas kodeve të statusit'
                    }
                }
            }
        });
        
        // Përditëso automatikisht faqen çdo 60 sekonda
        setTimeout(function() {
            location.reload();
        }, 60000);
    </script>
</body>
</html>