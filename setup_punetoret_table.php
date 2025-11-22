<?php
// filepath: d:\xampp\htdocs\noteria\setup_punetoret_table.php
// Script to set up the punetoret table and add num_punetore column to zyrat table

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
require_once 'config.php';

$success_messages = [];
$error_messages = [];

// Step 1: Check if num_punetore column exists in zyrat table
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM `zyrat` LIKE 'num_punetore'");
    $column_exists = ($stmt->rowCount() > 0);
    
    if (!$column_exists) {
        $pdo->exec("ALTER TABLE `zyrat` ADD COLUMN `num_punetore` int(11) NOT NULL DEFAULT 1 COMMENT 'Numri i punëtorëve në zyrë' AFTER `adresa`");
        $success_messages[] = "Kolona 'num_punetore' u shtua me sukses në tabelën 'zyrat'.";
    } else {
        $success_messages[] = "Kolona 'num_punetore' tashmë ekziston në tabelën 'zyrat'.";
    }
} catch (PDOException $e) {
    $error_messages[] = "Gabim gjatë kontrollit/shtimit të kolonës 'num_punetore': " . $e->getMessage();
}

// Step 2: Create punetoret table if it doesn't exist
try {
    $table_exists = false;
    $stmt = $pdo->query("SHOW TABLES LIKE 'punetoret'");
    $table_exists = ($stmt->rowCount() > 0);
    
    if (!$table_exists) {
        $sql = "CREATE TABLE `punetoret` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `zyra_id` int(11) NOT NULL,
            `emri` varchar(100) NOT NULL,
            `mbiemri` varchar(100) NOT NULL,
            `email` varchar(255) NOT NULL,
            `telefoni` varchar(20) DEFAULT NULL,
            `pozita` varchar(50) NOT NULL,
            `password` varchar(255) NOT NULL,
            `active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `last_login` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `email` (`email`),
            KEY `zyra_id` (`zyra_id`),
            CONSTRAINT `punetoret_ibfk_1` FOREIGN KEY (`zyra_id`) REFERENCES `zyrat` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        $success_messages[] = "Tabela 'punetoret' u krijua me sukses.";
        
        // Create index on email
        $pdo->exec("CREATE INDEX idx_punetoret_email ON punetoret(email)");
        $success_messages[] = "Indeksi për email-in u krijua me sukses.";
    } else {
        $success_messages[] = "Tabela 'punetoret' tashmë ekziston.";
    }
} catch (PDOException $e) {
    $error_messages[] = "Gabim gjatë krijimit të tabelës 'punetoret': " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfigurimi i Sistemit | Noteria</title>
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
        
        .code-block {
            background-color: #f1f1f1;
            padding: 15px;
            border-left: 5px solid var(--primary);
            margin: 20px 0;
            border-radius: 5px;
            font-family: monospace;
            white-space: pre-wrap;
            overflow-x: auto;
        }
        
        .instructions {
            margin-top: 30px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        
        .instructions h2 {
            color: var(--primary-dark);
            margin-top: 0;
        }
        
        .instructions ol {
            padding-left: 20px;
        }
        
        .instructions li {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-database"></i> Konfigurimi i Tabelave të Databazës</h1>
        
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
        
        <div class="instructions">
            <h2>Hapat e Ardhshëm</h2>
            <ol>
                <li>Sigurohuni që tabela <strong>zyrat</strong> të ketë kolonën <strong>num_punetore</strong>.</li>
                <li>Sigurohuni që tabela <strong>punetoret</strong> të jetë krijuar me sukses.</li>
                <li>Tani mund të regjistroni zyra noteriale duke deklaruar numrin e punëtorëve.</li>
                <li>Përdorni faqen <strong>manage_employees.php</strong> për të menaxhuar punëtorët për secilën zyrë.</li>
            </ol>
        </div>
        
        <div class="actions">
            <a href="zyrat_register.php" class="btn">
                <i class="fas fa-building"></i> Regjistro Zyrë të Re
            </a>
            <a href="manage_employees.php" class="btn">
                <i class="fas fa-users"></i> Menaxho Punëtorët
            </a>
            <a href="dashboard.php" class="btn">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </div>
    </div>
</body>
</html>