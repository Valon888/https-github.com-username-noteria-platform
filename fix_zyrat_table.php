<?php
// Script to update the zyrat table structure to add missing columns
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
require_once 'config.php';

$success_messages = [];
$error_messages = [];

try {
    // Get current columns in the table
    $stmt = $pdo->query("SHOW COLUMNS FROM zyrat");
    $existing_columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_columns[] = $row['Field'];
    }
    
    // Define the columns that should exist in the table
    $required_columns = [
        'id' => "ALTER TABLE zyrat ADD COLUMN id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'emri' => "ALTER TABLE zyrat ADD COLUMN emri VARCHAR(255) NOT NULL",
        'qyteti' => "ALTER TABLE zyrat ADD COLUMN qyteti VARCHAR(100) NOT NULL",
        'shteti' => "ALTER TABLE zyrat ADD COLUMN shteti VARCHAR(100) NOT NULL DEFAULT 'Kosova'",
        'email' => "ALTER TABLE zyrat ADD COLUMN email VARCHAR(255) NOT NULL",
        'telefoni' => "ALTER TABLE zyrat ADD COLUMN telefoni VARCHAR(20) NOT NULL",
        'nr_fiskal' => "ALTER TABLE zyrat ADD COLUMN nr_fiskal VARCHAR(20) NOT NULL COMMENT 'Numri fiskal nga ATK'",
        'nr_biznesi' => "ALTER TABLE zyrat ADD COLUMN nr_biznesi VARCHAR(20) NOT NULL COMMENT 'Numri i biznesit nga ARBK'",
        'data_regjistrimit' => "ALTER TABLE zyrat ADD COLUMN data_regjistrimit DATE NOT NULL",
        'lloji_biznesit' => "ALTER TABLE zyrat ADD COLUMN lloji_biznesit VARCHAR(50) NOT NULL",
        'adresa' => "ALTER TABLE zyrat ADD COLUMN adresa VARCHAR(255) NOT NULL",
        'num_punetore' => "ALTER TABLE zyrat ADD COLUMN num_punetore INT(11) NOT NULL DEFAULT 1 COMMENT 'Numri i punëtorëve në zyrë'",
        'banka' => "ALTER TABLE zyrat ADD COLUMN banka VARCHAR(100) NOT NULL",
        'iban' => "ALTER TABLE zyrat ADD COLUMN iban VARCHAR(34) NOT NULL",
        'llogaria' => "ALTER TABLE zyrat ADD COLUMN llogaria VARCHAR(20) NOT NULL",
        'pagesa' => "ALTER TABLE zyrat ADD COLUMN pagesa DECIMAL(10,2) NOT NULL",
        'created_at' => "ALTER TABLE zyrat ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
        'updated_at' => "ALTER TABLE zyrat ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP",
        'active' => "ALTER TABLE zyrat ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1"
    ];
    
    // Add missing columns
    foreach ($required_columns as $column => $sql) {
        if (!in_array($column, $existing_columns)) {
            try {
                $pdo->exec($sql);
                $success_messages[] = "Kolona '$column' u shtua me sukses.";
            } catch (PDOException $e) {
                $error_messages[] = "Gabim gjatë shtimit të kolonës '$column': " . $e->getMessage();
            }
        } else {
            $success_messages[] = "Kolona '$column' tashmë ekziston.";
        }
    }
    
    // Check if table was empty (assuming we just created the table)
    $stmt = $pdo->query("SELECT COUNT(*) FROM zyrat");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $success_messages[] = "Tabela 'zyrat' është e zbrazët dhe gati për të regjistruar zyra të reja.";
    } else {
        $success_messages[] = "Tabela 'zyrat' përmban $count zyra të regjistruara.";
    }
    
} catch (PDOException $e) {
    $error_messages[] = "Gabim i përgjithshëm: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Përditësimi i Strukturës së Tabelës | Noteria</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2d6cdf;
            --primary-dark: #184fa3;
            --primary-light: #e2eafc;
            --success: #388e3c;
            --success-light: #eafaf1;
            --error: #d32f2f;
            --error-light: #ffeaea;
            --dark: #333;
            --white: #fff;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            line-height: 1.6;
            background-color: #f5f7fa;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 40px auto;
            background: var(--white);
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        h1 {
            color: var(--primary-dark);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        
        .success {
            background-color: var(--success-light);
            color: var(--success);
            border-left: 5px solid var(--success);
        }
        
        .error {
            background-color: var(--error-light);
            color: var(--error);
            border-left: 5px solid var(--error);
        }
        
        .message i {
            margin-right: 10px;
        }
        
        .message p {
            margin: 5px 0;
        }
        
        .actions {
            margin-top: 30px;
            text-align: center;
        }
        
        .btn {
            display: inline-block;
            background: var(--primary);
            color: var(--white);
            padding: 12px 25px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            margin: 0 10px;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-database"></i> Përditësimi i Strukturës së Tabelës</h1>
        
        <?php if (!empty($success_messages)): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i>
                <?php foreach ($success_messages as $msg): ?>
                    <p><?php echo htmlspecialchars($msg); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_messages)): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <?php foreach ($error_messages as $msg): ?>
                    <p><?php echo htmlspecialchars($msg); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="actions">
            <a href="setup_punetoret_table.php" class="btn">
                <i class="fas fa-users"></i> Konfiguro Tabelën e Punëtorëve
            </a>
            <a href="zyrat_register.php" class="btn">
                <i class="fas fa-building"></i> Regjistro Zyrë
            </a>
            <a href="dashboard.php" class="btn">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </div>
    </div>
</body>
</html>