<?php
// Admin Authentication Check
session_start();
if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header("Location: admin_login.php");
    exit();
}

require_once 'db_connection.php';
require_once 'AuditTrail.php';

$audit_trail = new AuditTrail($conn);

// Get date range for report
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Get compliance data
$security_events = $audit_trail->getSecurityEvents(100);
$compliance_report = $audit_trail->generateComplianceReport($start_date . ' 00:00:00', $end_date . ' 23:59:59');

// Count event types
$event_summary = [];
foreach ($compliance_report as $event) {
    $action = $event['action'] ?? 'Unknown';
    if (!isset($event_summary[$action])) {
        $event_summary[$action] = ['total' => 0, 'warning' => 0, 'critical' => 0];
    }
    $event_summary[$action]['total'] += $event['count'];
    if ($event['severity'] === 'warning') {
        $event_summary[$action]['warning'] += $event['count'];
    } elseif ($event['severity'] === 'critical') {
        $event_summary[$action]['critical'] += $event['count'];
    }
}

// Export to CSV if requested
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="compliance_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Action', 'Total Events', 'Warnings', 'Critical', 'Period']);
    
    foreach ($event_summary as $action => $data) {
        fputcsv($output, [
            $action,
            $data['total'],
            $data['warning'],
            $data['critical'],
            "$start_date to $end_date"
        ]);
    }
    
    fclose($output);
    exit();
}

?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compliance Report - Noteria Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.8rem;
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            display: grid;
            grid-template-columns: auto auto auto auto;
            gap: 12px;
            align-items: center;
        }
        
        .filters input,
        .filters button {
            padding: 10px 16px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .filters button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
        }
        
        .filters button:hover {
            transform: translateY(-2px);
        }
        
        .export-btn {
            background: #27ae60;
            margin-left: auto;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-left: 4px solid;
        }
        
        .summary-card.info {
            border-left-color: #3498db;
        }
        
        .summary-card.warning {
            border-left-color: #f39c12;
        }
        
        .summary-card.critical {
            border-left-color: #e74c3c;
        }
        
        .summary-card .label {
            font-size: 0.85rem;
            color: #999;
            margin-bottom: 8px;
        }
        
        .summary-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
        }
        
        .events-table {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            overflow-x: auto;
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
            border-bottom: 2px solid #ddd;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
        
        .severity-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
        }
        
        .severity-info {
            background: #d4e8fc;
            color: #0651d8;
        }
        
        .severity-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .severity-critical {
            background: #f8d7da;
            color: #721c24;
        }
        
        .footer {
            text-align: center;
            color: #999;
            padding: 20px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1><i class="fas fa-clipboard-check"></i> Compliance & Audit Report</h1>
                <p>Platform compliance and security audit trail</p>
            </div>
        </div>
        
        <!-- Date Filters -->
        <form method="GET" class="filters">
            <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" required>
            <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" required>
            <button type="submit"><i class="fas fa-search"></i> Filter</button>
            <a href="?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&export=csv" class="export-btn" style="text-decoration: none; padding: 10px 16px; border-radius: 6px; color: white; cursor: pointer; display: inline-block;">
                <i class="fas fa-download"></i> Export CSV
            </a>
        </form>
        
        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card info">
                <div class="label"><i class="fas fa-info-circle"></i> Total Events</div>
                <div class="value"><?php echo array_sum(array_map(function($e) { return $e['total']; }, $event_summary)); ?></div>
            </div>
            
            <div class="summary-card warning">
                <div class="label"><i class="fas fa-exclamation-triangle"></i> Warnings</div>
                <div class="value"><?php echo array_sum(array_map(function($e) { return $e['warning']; }, $event_summary)); ?></div>
            </div>
            
            <div class="summary-card critical">
                <div class="label"><i class="fas fa-exclamation-circle"></i> Critical</div>
                <div class="value"><?php echo array_sum(array_map(function($e) { return $e['critical']; }, $event_summary)); ?></div>
            </div>
            
            <div class="summary-card info">
                <div class="label"><i class="fas fa-calendar"></i> Period</div>
                <div class="value" style="font-size: 1rem;"><?php echo $start_date; ?><br><?php echo $end_date; ?></div>
            </div>
        </div>
        
        <!-- Events Table -->
        <div class="events-table">
            <h2 style="margin-bottom: 20px;"><i class="fas fa-list"></i> Event Summary</h2>
            <table>
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Total Events</th>
                        <th>Info</th>
                        <th>Warnings</th>
                        <th>Critical</th>
                        <th>Severity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($event_summary as $action => $data): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($action); ?></strong></td>
                        <td><?php echo number_format($data['total']); ?></td>
                        <td><?php echo number_format($data['total'] - $data['warning'] - $data['critical']); ?></td>
                        <td><?php echo number_format($data['warning']); ?></td>
                        <td><?php echo number_format($data['critical']); ?></td>
                        <td>
                            <?php if ($data['critical'] > 0): ?>
                                <span class="severity-badge severity-critical">🔴 Critical</span>
                            <?php elseif ($data['warning'] > 0): ?>
                                <span class="severity-badge severity-warning">🟡 Warning</span>
                            <?php else: ?>
                                <span class="severity-badge severity-info">🟢 OK</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Recent Security Events -->
        <div class="events-table">
            <h2 style="margin-bottom: 20px;"><i class="fas fa-shield-alt"></i> Recent Security Events</h2>
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Action</th>
                        <th>User</th>
                        <th>IP Address</th>
                        <th>Severity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($security_events, 0, 20) as $event): ?>
                    <tr>
                        <td><?php echo date('d.m.Y H:i:s', strtotime($event['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($event['action']); ?></td>
                        <td><?php echo htmlspecialchars($event['user_id'] ?? 'System'); ?></td>
                        <td><?php echo htmlspecialchars($event['ip_address'] ?? 'Unknown'); ?></td>
                        <td>
                            <span class="severity-badge severity-<?php echo $event['severity']; ?>">
                                <?php echo strtoupper($event['severity']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="footer">
            <p>Report generated on <?php echo date('d.m.Y H:i:s'); ?> | Confidential - For Compliance Use Only</p>
        </div>
    </div>
</body>
</html>
