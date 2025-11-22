<?php
// test_subscription_system.php - Skript për testimin dhe verifikimin e sistemit të abonimeve
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';

// Kontrollo autorizimin (vetëm administratorët)
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Funksionet helper për testim
function checkTable($pdo, $tableName) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function checkColumn($pdo, $tableName, $columnName) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$tableName` LIKE '$columnName'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function createRequiredTables($pdo) {
    $queries = [];
    $results = [];
    
    // Tabela për planet e abonimit
    if (!checkTable($pdo, 'subscription_plans')) {
        $queries['subscription_plans'] = "
            CREATE TABLE `subscription_plans` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(100) NOT NULL,
                `price` decimal(10,2) NOT NULL,
                `description` text,
                `active` tinyint(1) NOT NULL DEFAULT '1',
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
    }
    
    // Tabela për abonimet e përdoruesve
    if (!checkTable($pdo, 'user_subscriptions')) {
        $queries['user_subscriptions'] = "
            CREATE TABLE `user_subscriptions` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `plan_id` int(11) NOT NULL,
                `start_date` date NOT NULL,
                `end_date` date NOT NULL,
                `status` enum('active','expired','cancelled') NOT NULL DEFAULT 'active',
                `auto_renew` tinyint(1) NOT NULL DEFAULT '1',
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`),
                KEY `plan_id` (`plan_id`),
                CONSTRAINT `user_subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                CONSTRAINT `user_subscriptions_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
    }
    
    // Tabela për pagesat e abonimeve
    if (!checkTable($pdo, 'subscription_payments')) {
        $queries['subscription_payments'] = "
            CREATE TABLE `subscription_payments` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `subscription_id` int(11) NOT NULL,
                `amount` decimal(10,2) NOT NULL,
                `payment_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `payment_method` varchar(50) NOT NULL,
                `status` enum('completed','pending','failed') NOT NULL DEFAULT 'pending',
                `transaction_id` varchar(100) DEFAULT NULL,
                `notes` text,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `subscription_id` (`subscription_id`),
                CONSTRAINT `subscription_payments_ibfk_1` FOREIGN KEY (`subscription_id`) REFERENCES `user_subscriptions` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
    }
    
    // Shtim i kolonave në tabelën users nëse nevojiten
    if (checkTable($pdo, 'users')) {
        if (!checkColumn($pdo, 'users', 'subscription_end_date')) {
            $queries['alter_users_add_subscription'] = "
                ALTER TABLE `users` 
                ADD COLUMN `subscription_end_date` date DEFAULT NULL,
                ADD COLUMN `subscription_status` enum('active','inactive','trial') DEFAULT 'inactive';
            ";
        }
    }
    
    // Ekzekutimi i të gjitha query-ve
    foreach ($queries as $name => $query) {
        try {
            $pdo->exec($query);
            $results[$name] = [
                'status' => 'success',
                'message' => 'Tabela u krijua me sukses'
            ];
        } catch (PDOException $e) {
            $results[$name] = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    return [
        'queries_executed' => count($queries),
        'results' => $results
    ];
}

function createSampleData($pdo) {
    $results = [];
    
    try {
        // Kontrollo nëse ekzistojnë të dhëna në tabelën subscription_plans
        $checkPlans = $pdo->query("SELECT COUNT(*) FROM subscription_plans");
        if ($checkPlans->fetchColumn() == 0) {
            // Shto plane abonimi demo
            $planQueries = [
                "INSERT INTO `subscription_plans` (`name`, `price`, `description`, `active`) 
                 VALUES ('Basic', 9.99, 'Plani bazë me funksionalitete themelore', 1)",
                
                "INSERT INTO `subscription_plans` (`name`, `price`, `description`, `active`) 
                 VALUES ('Standard', 19.99, 'Plani standard me më shumë opsione', 1)",
                
                "INSERT INTO `subscription_plans` (`name`, `price`, `description`, `active`) 
                 VALUES ('Premium', 29.99, 'Plani premium me të gjitha funksionalitetet e disponueshme', 1)"
            ];
            
            foreach ($planQueries as $i => $query) {
                $pdo->exec($query);
            }
            
            $results['plans'] = [
                'status' => 'success',
                'message' => 'U shtuan 3 plane demo abonimi'
            ];
        } else {
            $results['plans'] = [
                'status' => 'skipped',
                'message' => 'Planet e abonimit ekzistojnë tashmë'
            ];
        }
        
        // Kontrollo nëse ekzistojnë përdorues për testimim
        $checkUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE username LIKE 'test_%'");
        if ($checkUsers->fetchColumn() < 3) {
            // Krijo disa përdorues test nëse nuk ekzistojnë
            $userQueries = [
                "INSERT INTO `users` (`username`, `password`, `email`, `roli`, `emri`, `mbiemri`, `created_at`) 
                 VALUES ('test_user1', '".password_hash('testpass', PASSWORD_DEFAULT)."', 'test1@example.com', 'user', 'Test', 'User1', NOW())",
                
                "INSERT INTO `users` (`username`, `password`, `email`, `roli`, `emri`, `mbiemri`, `created_at`) 
                 VALUES ('test_user2', '".password_hash('testpass', PASSWORD_DEFAULT)."', 'test2@example.com', 'user', 'Test', 'User2', NOW())",
                
                "INSERT INTO `users` (`username`, `password`, `email`, `roli`, `emri`, `mbiemri`, `created_at`) 
                 VALUES ('test_user3', '".password_hash('testpass', PASSWORD_DEFAULT)."', 'test3@example.com', 'user', 'Test', 'User3', NOW())"
            ];
            
            foreach ($userQueries as $query) {
                try {
                    $pdo->exec($query);
                } catch (PDOException $e) {
                    // Ignore duplicate entry errors
                }
            }
            
            $results['users'] = [
                'status' => 'success',
                'message' => 'U shtuan përdorues test'
            ];
        } else {
            $results['users'] = [
                'status' => 'skipped',
                'message' => 'Përdoruesit test ekzistojnë tashmë'
            ];
        }
        
        // Krijo disa abonime për përdoruesit test
        $testUsers = $pdo->query("SELECT id FROM users WHERE username LIKE 'test_%' LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
        $plans = $pdo->query("SELECT id FROM subscription_plans LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($testUsers) && !empty($plans)) {
            // Kontrollo nëse përdoruesit test kanë abonime
            $checkSubscriptions = $pdo->query("
                SELECT COUNT(*) FROM user_subscriptions 
                WHERE user_id IN (" . implode(',', $testUsers) . ")
            ");
            
            if ($checkSubscriptions->fetchColumn() == 0) {
                // Krijo abonime test
                $today = date('Y-m-d');
                $nextMonth = date('Y-m-d', strtotime('+1 month'));
                $lastMonth = date('Y-m-d', strtotime('-1 month'));
                
                $subQueries = [
                    // Abonim aktiv për përdoruesin e parë
                    "INSERT INTO `user_subscriptions` 
                     (`user_id`, `plan_id`, `start_date`, `end_date`, `status`, `auto_renew`) 
                     VALUES ({$testUsers[0]}, {$plans[0]}, '$today', '$nextMonth', 'active', 1)",
                    
                    // Abonim që skadon sot për përdoruesin e dytë
                    "INSERT INTO `user_subscriptions` 
                     (`user_id`, `plan_id`, `start_date`, `end_date`, `status`, `auto_renew`) 
                     VALUES ({$testUsers[1]}, {$plans[1]}, '$lastMonth', '$today', 'active', 0)",
                    
                    // Abonim i skaduar për përdoruesin e tretë
                    "INSERT INTO `user_subscriptions` 
                     (`user_id`, `plan_id`, `start_date`, `end_date`, `status`, `auto_renew`) 
                     VALUES ({$testUsers[2]}, {$plans[2]}, '$lastMonth', '$lastMonth', 'expired', 0)"
                ];
                
                foreach ($subQueries as $query) {
                    $pdo->exec($query);
                    $subId = $pdo->lastInsertId();
                    
                    // Krijo një pagesë për këtë abonim
                    $paymentQuery = "
                        INSERT INTO `subscription_payments` 
                        (`subscription_id`, `amount`, `payment_date`, `payment_method`, `status`) 
                        VALUES ($subId, " . ($subId * 10) . ", '$today', 'credit_card', 'completed')
                    ";
                    $pdo->exec($paymentQuery);
                }
                
                $results['subscriptions'] = [
                    'status' => 'success',
                    'message' => 'U shtuan abonime dhe pagesa test'
                ];
            } else {
                $results['subscriptions'] = [
                    'status' => 'skipped',
                    'message' => 'Abonimet test ekzistojnë tashmë'
                ];
            }
        }
        
    } catch (PDOException $e) {
        $results['error'] = [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
    
    return $results;
}

// Ekzekuto testet bazuar në veprimin e kërkuar
$action = $_GET['action'] ?? '';
$result = [];

switch ($action) {
    case 'create_tables':
        $result = createRequiredTables($pdo);
        break;
        
    case 'sample_data':
        $result = createSampleData($pdo);
        break;
        
    case 'check_structure':
        $tables = [
            'subscription_plans',
            'user_subscriptions',
            'subscription_payments'
        ];
        
        $structure = [];
        foreach ($tables as $table) {
            $exists = checkTable($pdo, $table);
            $structure[$table] = [
                'exists' => $exists,
                'columns' => []
            ];
            
            if ($exists) {
                try {
                    $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($columns as $column) {
                        $structure[$table]['columns'][] = $column;
                    }
                } catch (PDOException $e) {
                    $structure[$table]['error'] = $e->getMessage();
                }
            }
        }
        
        $result = [
            'status' => 'success',
            'structure' => $structure
        ];
        break;
        
    case 'test_process':
        // Testo procesin e rinovimit të abonimit
        try {
            // Merr një abonim aktiv për testim
            $stmt = $pdo->query("
                SELECT us.*, u.email, u.username, sp.name as plan_name, sp.price
                FROM user_subscriptions us
                JOIN users u ON us.user_id = u.id
                JOIN subscription_plans sp ON us.plan_id = sp.id
                WHERE us.status = 'active'
                AND us.end_date >= CURRENT_DATE
                LIMIT 1
            ");
            
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($subscription) {
                // Simuloni rinovimin e abonimit
                $new_end_date = date('Y-m-d', strtotime('+1 month', strtotime($subscription['end_date'])));
                
                // Përditëso abonimin
                $updateStmt = $pdo->prepare("
                    UPDATE user_subscriptions 
                    SET end_date = :new_end_date,
                        updated_at = NOW()
                    WHERE id = :subscription_id
                ");
                
                $updateStmt->execute([
                    'new_end_date' => $new_end_date,
                    'subscription_id' => $subscription['id']
                ]);
                
                // Krijo një pagesë të re
                $paymentStmt = $pdo->prepare("
                    INSERT INTO subscription_payments 
                    (subscription_id, amount, payment_date, payment_method, status, notes)
                    VALUES 
                    (:subscription_id, :amount, NOW(), 'test', 'completed', 'Testim i rinovimit të abonimit')
                ");
                
                $paymentStmt->execute([
                    'subscription_id' => $subscription['id'],
                    'amount' => $subscription['price']
                ]);
                
                $result = [
                    'status' => 'success',
                    'message' => "Abonimi u rinovua me sukses për përdoruesin {$subscription['username']} deri më {$new_end_date}",
                    'details' => [
                        'subscription_id' => $subscription['id'],
                        'user' => $subscription['username'],
                        'plan' => $subscription['plan_name'],
                        'old_end_date' => $subscription['end_date'],
                        'new_end_date' => $new_end_date,
                        'amount' => $subscription['price']
                    ]
                ];
            } else {
                $result = [
                    'status' => 'error',
                    'message' => "Nuk u gjet asnjë abonim aktiv për testim"
                ];
            }
        } catch (PDOException $e) {
            $result = [
                'status' => 'error',
                'message' => "Gabim në testimin e procesit të rinovimit: " . $e->getMessage()
            ];
        }
        break;
        
    case 'test_expiry':
        // Testo procesin e skadimit të abonimit
        try {
            // Merr një abonim që skadon sot
            $today = date('Y-m-d');
            $stmt = $pdo->query("
                SELECT us.*, u.email, u.username, sp.name as plan_name
                FROM user_subscriptions us
                JOIN users u ON us.user_id = u.id
                JOIN subscription_plans sp ON us.plan_id = sp.id
                WHERE us.end_date = '$today'
                AND us.status = 'active'
                AND us.auto_renew = 0
                LIMIT 1
            ");
            
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($subscription) {
                // Përditëso statusin në skaduar
                $updateStmt = $pdo->prepare("
                    UPDATE user_subscriptions 
                    SET status = 'expired',
                        updated_at = NOW()
                    WHERE id = :subscription_id
                ");
                
                $updateStmt->execute([
                    'subscription_id' => $subscription['id']
                ]);
                
                $result = [
                    'status' => 'success',
                    'message' => "Abonimi për përdoruesin {$subscription['username']} u skadua me sukses",
                    'details' => [
                        'subscription_id' => $subscription['id'],
                        'user' => $subscription['username'],
                        'plan' => $subscription['plan_name'],
                        'end_date' => $subscription['end_date']
                    ]
                ];
            } else {
                $result = [
                    'status' => 'error',
                    'message' => "Nuk u gjet asnjë abonim që skadon sot dhe nuk ka rinovim automatik"
                ];
            }
        } catch (PDOException $e) {
            $result = [
                'status' => 'error',
                'message' => "Gabim në testimin e procesit të skadimit: " . $e->getMessage()
            ];
        }
        break;
        
    default:
        // Nuk u specifikua asnjë veprim
        $result = [
            'status' => 'info',
            'message' => 'Zgjidhni një veprim për të ekzekutuar'
        ];
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testimi i Sistemit të Abonimeve | Noteria</title>
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
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
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
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
            margin-top: 30px;
        }
        
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 6px;
        }
        
        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border-left: 5px solid var(--success-color);
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 5px solid var(--danger-color);
        }
        
        .alert-info {
            background-color: #dbeafe;
            color: #1e40af;
            border-left: 5px solid var(--primary-color);
        }
        
        .button {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
            font-size: 1rem;
            margin-right: 10px;
            margin-bottom: 10px;
            transition: background-color 0.2s;
        }
        
        .button:hover {
            background-color: var(--primary-hover);
        }
        
        .button i {
            margin-right: 8px;
        }
        
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            border-top: 3px solid var(--primary-color);
        }
        
        .card-success {
            border-top-color: var(--success-color);
        }
        
        .card-error {
            border-top-color: var(--danger-color);
        }
        
        .card-warning {
            border-top-color: var(--warning-color);
        }
        
        .card h3 {
            margin-top: 0;
            font-size: 1.2rem;
        }
        
        .table-container {
            overflow-x: auto;
            margin: 20px 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        th {
            background-color: #f1f5f9;
            font-weight: 600;
        }
        
        tr:hover {
            background-color: #f8fafc;
        }
        
        .code-block {
            background-color: #f1f5f9;
            padding: 15px;
            border-radius: 6px;
            font-family: monospace;
            overflow-x: auto;
            margin: 15px 0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
        }
        
        .status-success {
            background-color: var(--success-color);
        }
        
        .status-error {
            background-color: var(--danger-color);
        }
        
        .status-warning {
            background-color: var(--warning-color);
        }
        
        .status-info {
            background-color: var(--primary-color);
        }
        
        .status-skipped {
            background-color: var(--secondary-color);
        }
        
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .card-grid {
                grid-template-columns: 1fr;
            }
            
            .toolbar {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .button-group {
                display: flex;
                flex-wrap: wrap;
                margin-top: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="toolbar">
            <h1><i class="fas fa-vial"></i> Testimi i Sistemit të Abonimeve</h1>
            
            <div class="button-group">
                <a href="subscription_settings.php" class="button">
                    <i class="fas fa-cog"></i> Konfigurimet
                </a>
                <a href="subscription_payments.php" class="button button-secondary">
                    <i class="fas fa-arrow-left"></i> Kthehu
                </a>
            </div>
        </div>
        
        <div class="panel">
            <h2>Veprimet e Testimit</h2>
            
            <div>
                <a href="?action=check_structure" class="button">
                    <i class="fas fa-database"></i> Kontrollo Strukturën
                </a>
                <a href="?action=create_tables" class="button">
                    <i class="fas fa-table"></i> Krijo Tabelat
                </a>
                <a href="?action=sample_data" class="button">
                    <i class="fas fa-file-import"></i> Gjenero të Dhëna Demo
                </a>
                <a href="?action=test_process" class="button">
                    <i class="fas fa-sync"></i> Testo Rinovimin
                </a>
                <a href="?action=test_expiry" class="button">
                    <i class="fas fa-calendar-times"></i> Testo Skadimin
                </a>
            </div>
            
            <?php if ($action && isset($result['status'])): ?>
                <div class="alert alert-<?php echo $result['status']; ?>">
                    <?php echo htmlspecialchars($result['message'] ?? 'Veprimi u ekzekutua'); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($action === 'check_structure' && isset($result['structure'])): ?>
                <h2>Struktura e Tabelave</h2>
                
                <?php foreach ($result['structure'] as $tableName => $tableData): ?>
                    <div class="card <?php echo $tableData['exists'] ? 'card-success' : 'card-error'; ?> ">
                        <h3>
                            <?php echo htmlspecialchars($tableName); ?>
                            <?php if ($tableData['exists']): ?>
                                <span class="status-badge status-success">Ekziston</span>
                            <?php else: ?>
                                <span class="status-badge status-error">Nuk Ekziston</span>
                            <?php endif; ?>
                        </h3>
                        
                        <?php if ($tableData['exists']): ?>
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Field</th>
                                            <th>Type</th>
                                            <th>Null</th>
                                            <th>Key</th>
                                            <th>Default</th>
                                            <th>Extra</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tableData['columns'] as $column): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($column['Field']); ?></td>
                                                <td><?php echo htmlspecialchars($column['Type']); ?></td>
                                                <td><?php echo htmlspecialchars($column['Null']); ?></td>
                                                <td><?php echo htmlspecialchars($column['Key']); ?></td>
                                                <td><?php echo htmlspecialchars($column['Default'] ?? 'NULL'); ?></td>
                                                <td><?php echo htmlspecialchars($column['Extra']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p>Tabela nuk ekziston. Kliko në butonin "Krijo Tabelat" për ta krijuar.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if ($action === 'create_tables'): ?>
                <h2>Rezultatet e Krijimit të Tabelave</h2>
                
                <div class="card-grid">
                    <?php foreach ($result['results'] as $tableName => $tableResult): ?>
                        <div class="card <?php echo $tableResult['status'] === 'success' ? 'card-success' : 'card-error'; ?>">
                            <h3><?php echo htmlspecialchars($tableName); ?></h3>
                            <div class="status-badge <?php echo 'status-' . $tableResult['status']; ?>">
                                <?php echo ucfirst($tableResult['status']); ?>
                            </div>
                            <p><?php echo htmlspecialchars($tableResult['message']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($action === 'sample_data'): ?>
                <h2>Rezultatet e Gjenerimit të të Dhënave</h2>
                
                <div class="card-grid">
                    <?php foreach ($result as $category => $categoryResult): ?>
                        <div class="card <?php 
                            if ($categoryResult['status'] === 'success') echo 'card-success';
                            elseif ($categoryResult['status'] === 'error') echo 'card-error';
                            elseif ($categoryResult['status'] === 'skipped') echo 'card-warning';
                        ?>">
                            <h3><?php echo htmlspecialchars(ucfirst($category)); ?></h3>
                            <div class="status-badge status-<?php echo $categoryResult['status']; ?>">
                                <?php echo ucfirst($categoryResult['status']); ?>
                            </div>
                            <p><?php echo htmlspecialchars($categoryResult['message']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($action === 'test_process' && isset($result['details'])): ?>
                <h2>Detajet e Testimit të Rinovimit</h2>
                
                <div class="table-container">
                    <table>
                        <tr>
                            <th>ID Abonimi</th>
                            <td><?php echo htmlspecialchars($result['details']['subscription_id']); ?></td>
                        </tr>
                        <tr>
                            <th>Përdoruesi</th>
                            <td><?php echo htmlspecialchars($result['details']['user']); ?></td>
                        </tr>
                        <tr>
                            <th>Plani</th>
                            <td><?php echo htmlspecialchars($result['details']['plan']); ?></td>
                        </tr>
                        <tr>
                            <th>Data e mëparshme e përfundimit</th>
                            <td><?php echo htmlspecialchars($result['details']['old_end_date']); ?></td>
                        </tr>
                        <tr>
                            <th>Data e re e përfundimit</th>
                            <td><?php echo htmlspecialchars($result['details']['new_end_date']); ?></td>
                        </tr>
                        <tr>
                            <th>Shuma e paguar</th>
                            <td><?php echo htmlspecialchars($result['details']['amount']); ?> EUR</td>
                        </tr>
                    </table>
                </div>
            <?php endif; ?>
            
            <?php if ($action === 'test_expiry' && isset($result['details'])): ?>
                <h2>Detajet e Testimit të Skadimit</h2>
                
                <div class="table-container">
                    <table>
                        <tr>
                            <th>ID Abonimi</th>
                            <td><?php echo htmlspecialchars($result['details']['subscription_id']); ?></td>
                        </tr>
                        <tr>
                            <th>Përdoruesi</th>
                            <td><?php echo htmlspecialchars($result['details']['user']); ?></td>
                        </tr>
                        <tr>
                            <th>Plani</th>
                            <td><?php echo htmlspecialchars($result['details']['plan']); ?></td>
                        </tr>
                        <tr>
                            <th>Data e skadimit</th>
                            <td><?php echo htmlspecialchars($result['details']['end_date']); ?></td>
                        </tr>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="panel">
            <h2>Udhëzime për Konfigurimin e Cron Job</h2>
            
            <p>Për të aktivizuar procesin automatik të rinovimit të abonimeve, ju duhet të konfiguroni një cron job që ekzekuton skriptin <code>subscription_processor.php</code> çdo ditë.</p>
            
            <h3>Shembull Cron Job për Linux/Unix</h3>
            
            <div class="code-block">
                # Ekzekuto skriptin e përpunimit të abonimeve çdo ditë në orën 2:00 të mëngjesit
                0 2 * * * php <?php echo realpath(__DIR__); ?>/subscription_processor.php?token=YXV0b21hdGljX3N1YnNjcmlwdGlvbl90b2tlbg== >> <?php echo realpath(__DIR__); ?>/subscription_cron.log 2>&1
            </div>
            
            <h3>Shembull Scheduled Task për Windows</h3>
            
            <div class="code-block">
                schtasks /create /tn "Noteria Subscription Processing" /tr "php <?php echo realpath(__DIR__); ?>\subscription_processor.php?token=YXV0b21hdGljX3N1YnNjcmlwdGlvbl90b2tlbg==" /sc daily /st 02:00
            </div>
            
            <p>Sigurohuni që të vendosni token-in e saktë për autorizim kur ekzekutoni skriptin nga një cron job.</p>
        </div>
    </div>
</body>
</html>