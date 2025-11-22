<?php
/**
 * Create Database Tables Script
 * 
 * This script creates missing database tables needed for the Noteria system.
 * It checks if each table exists before attempting to create it.
 * 
 * @version 1.0
 * @date September 2025
 */

require_once 'config.php';

// Funksion për të kontrolluar nëse tabela ekziston
function tableExists($pdo, $table) {
    try {
        $result = $pdo->query("SELECT 1 FROM $table LIMIT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Lista e tabelave që duhet të krijohen dhe definicionet e tyre SQL
$tables = [
    'login_attempts' => "
        CREATE TABLE `login_attempts` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) DEFAULT NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            `user_agent` TEXT,
            `attempt_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `successful` TINYINT(1) NOT NULL DEFAULT 0,
            `username_attempt` VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    
    'payment_logs' => "
        CREATE TABLE `payment_logs` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `noter_id` INT(11) DEFAULT NULL,
            `payment_id` INT(11) DEFAULT NULL,
            `amount` DECIMAL(10,2) NOT NULL,
            `status` VARCHAR(50) NOT NULL,
            `log_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `payment_method` VARCHAR(50) DEFAULT NULL,
            `transaction_id` VARCHAR(100) DEFAULT NULL,
            `phone_number` VARCHAR(20) DEFAULT NULL,
            `operator` VARCHAR(50) DEFAULT NULL,
            `file_path` VARCHAR(255) DEFAULT NULL,
            `numri_fiskal` VARCHAR(20) DEFAULT NULL,
            `numri_biznesit` VARCHAR(20) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `noter_id` (`noter_id`),
            KEY `payment_id` (`payment_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    
    'session_logs' => "
        CREATE TABLE `session_logs` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) DEFAULT NULL,
            `session_id` VARCHAR(255) NOT NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            `user_agent` TEXT,
            `login_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `logout_time` DATETIME DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `user_type` VARCHAR(20) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `session_id` (`session_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    
    'uploaded_files' => "
        CREATE TABLE `uploaded_files` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `noter_id` INT(11) DEFAULT NULL,
            `file_name` VARCHAR(255) NOT NULL,
            `file_path` VARCHAR(255) NOT NULL,
            `file_size` INT(11) NOT NULL,
            `file_type` VARCHAR(100) NOT NULL,
            `upload_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `status` ENUM('pending', 'completed', 'rejected') NOT NULL DEFAULT 'pending',
            `description` TEXT,
            PRIMARY KEY (`id`),
            KEY `noter_id` (`noter_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    "
];

$tableResults = [];

// Përpiqu të krijosh secilën tabelë nëse nuk ekziston
foreach ($tables as $tableName => $tableSQL) {
    try {
        if (!tableExists($pdo, $tableName)) {
            $pdo->exec($tableSQL);
            $tableResults[$tableName] = [
                'success' => true,
                'message' => "Tabela '{$tableName}' u krijua me sukses."
            ];
        } else {
            $tableResults[$tableName] = [
                'success' => true,
                'message' => "Tabela '{$tableName}' ekziston tashmë."
            ];
        }
    } catch (PDOException $e) {
        $tableResults[$tableName] = [
            'success' => false,
            'message' => "Gabim gjatë krijimit të tabelës '{$tableName}': " . $e->getMessage()
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Krijimi i Tabelave - Noteria Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-light: #3b82f6;
            --primary-dark: #1d4ed8;
            --secondary: #64748b;
            --success: #10b981;
            --info: #06b6d4;
            --warning: #f59e0b;
            --danger: #ef4444;
            --light: #f3f4f6;
            --dark: #1f2937;
            --body-bg: #f9fafb;
            --card-bg: #ffffff;
            --text: #4b5563;
            --text-light: #6b7280;
            --text-dark: #374151;
            --border: #e5e7eb;
            --heading: #111827;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --transition: all 0.3s ease;
            --radius: 0.5rem;
            --radius-sm: 0.25rem;
            --radius-lg: 0.75rem;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            font-size: 0.95rem;
            line-height: 1.6;
            color: var(--text);
            background-color: var(--body-bg);
            padding: 2rem;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 2rem;
        }
        
        h1 {
            color: var(--heading);
            font-weight: 600;
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        h1 i {
            color: var(--primary);
        }
        
        .step {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .step-header {
            background-color: var(--light);
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 1px solid var(--border);
        }
        
        .step-title {
            font-weight: 600;
            color: var(--heading);
            font-size: 1.1rem;
        }
        
        .step-icon-success {
            color: var(--success);
        }
        
        .step-icon-warning {
            color: var(--warning);
        }
        
        .step-icon-error {
            color: var(--danger);
        }
        
        .step-body {
            padding: 1.5rem;
        }
        
        .step-message {
            margin-bottom: 1rem;
        }
        
        .code-block {
            background: var(--dark);
            color: white;
            padding: 1rem;
            border-radius: var(--radius-sm);
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 200px;
            overflow-y: auto;
            margin-top: 1rem;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border-left: 4px solid #16a34a;
        }
        
        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }
        
        .alert-warning {
            background-color: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }
        
        .btn-container {
            margin-top: 2rem;
            text-align: center;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-size: 0.95rem;
            font-weight: 500;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            cursor: pointer;
            border: 1px solid transparent;
            border-radius: var(--radius-sm);
            transition: var(--transition);
            text-decoration: none;
        }
        
        .btn-primary {
            color: white;
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-database"></i> Krijimi i Tabelave të Databazës</h1>
        
        <?php
        $allSuccess = true;
        $anyCreated = false;
        
        foreach ($tableResults as $tableName => $result) {
            $isCreated = strpos($result['message'], 'u krijua') !== false;
            if ($isCreated) {
                $anyCreated = true;
            }
            
            if (!$result['success']) {
                $allSuccess = false;
            }
            
            $iconClass = $result['success'] ? 
                ($isCreated ? 'step-icon-success' : 'step-icon-warning') : 
                'step-icon-error';
                
            $icon = $result['success'] ? 
                ($isCreated ? 'fa-check-circle' : 'fa-info-circle') : 
                'fa-times-circle';
        ?>
        <div class="step">
            <div class="step-header">
                <i class="fas <?php echo $icon; ?> <?php echo $iconClass; ?>"></i>
                <div class="step-title">Tabela: <?php echo $tableName; ?></div>
            </div>
            <div class="step-body">
                <div class="step-message"><?php echo $result['message']; ?></div>
                
                <?php if ($isCreated): ?>
                <div class="code-block"><?php echo trim(preg_replace('/\s+/', ' ', $tables[$tableName])); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php } ?>
        
        <?php if ($allSuccess && $anyCreated): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <div>Të gjitha tabelat u krijuan me sukses! Tani mund të përdorni statistikat dhe funksionet e avancuara të sistemit.</div>
        </div>
        <?php elseif ($allSuccess && !$anyCreated): ?>
        <div class="alert alert-success">
            <i class="fas fa-info-circle"></i>
            <div>Të gjitha tabelat e kërkuara ekzistojnë tashmë në databazë.</div>
        </div>
        <?php else: ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <div>Disa tabela nuk u krijuan për shkak të gabimeve. Ju lutem kontrolloni mesazhet e mësipërme.</div>
        </div>
        <?php endif; ?>
        
        <div class="btn-container">
            <a href="statistikat.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Kthehu tek Statistikat
            </a>
        </div>
    </div>
</body>
</html>