<?php
// Script to create both zyrat and punetoret tables from scratch
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
require_once 'config.php';

$success_messages = [];
$error_messages = [];

try {
    // Create or fix zyrat table
    $pdo->exec("DROP TABLE IF EXISTS punetoret"); // Drop punetoret first due to foreign key
    $pdo->exec("DROP TABLE IF EXISTS zyrat");
    
    $sql_zyrat = "CREATE TABLE `zyrat` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `emri` varchar(255) NOT NULL,
      `qyteti` varchar(100) NOT NULL,
      `shteti` varchar(100) NOT NULL DEFAULT 'Kosova',
      `email` varchar(255) NOT NULL,
      `telefoni` varchar(20) NOT NULL,
      `nr_fiskal` varchar(20) NOT NULL COMMENT 'Numri fiskal nga ATK',
      `nr_biznesi` varchar(20) NOT NULL COMMENT 'Numri i biznesit nga ARBK',
      `data_regjistrimit` date NOT NULL,
      `lloji_biznesit` varchar(50) NOT NULL,
      `adresa` varchar(255) NOT NULL,
      `num_punetore` int(11) NOT NULL DEFAULT 1 COMMENT 'Numri i punëtorëve në zyrë',
      `banka` varchar(100) NOT NULL,
      `iban` varchar(34) NOT NULL,
      `llogaria` varchar(20) NOT NULL,
      `pagesa` decimal(10,2) NOT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
      `active` tinyint(1) NOT NULL DEFAULT 1,
      PRIMARY KEY (`id`),
      UNIQUE KEY `email` (`email`),
      KEY `qyteti` (`qyteti`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql_zyrat);
    $success_messages[] = "Tabela 'zyrat' u krijua me sukses me të gjitha kolonat e nevojshme.";
    
    // Create punetoret table
    $sql_punetoret = "CREATE TABLE `punetoret` (
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
    
    $pdo->exec($sql_punetoret);
    $success_messages[] = "Tabela 'punetoret' u krijua me sukses me të gjitha kolonat e nevojshme.";
    
    // Create users table if it doesn't exist (for admin login)
    $sql_users = "CREATE TABLE IF NOT EXISTS `users` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `username` varchar(100) NOT NULL,
      `email` varchar(255) NOT NULL,
      `password` varchar(255) NOT NULL,
      `role` enum('admin','user') NOT NULL DEFAULT 'user',
      `zyra_id` int(11) DEFAULT NULL,
      `active` tinyint(1) NOT NULL DEFAULT 1,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `last_login` timestamp NULL DEFAULT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `username` (`username`),
      UNIQUE KEY `email` (`email`),
      KEY `zyra_id` (`zyra_id`),
      CONSTRAINT `users_ibfk_1` FOREIGN KEY (`zyra_id`) REFERENCES `zyrat` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql_users);
    $success_messages[] = "Tabela 'users' u krijua me sukses (nëse nuk ekzistonte).";
    
    // Add an admin user if users table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $admin_password = password_hash("admin123", PASSWORD_DEFAULT); // Default password, should be changed
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute(["admin", "admin@noteria.com", $admin_password]);
        $success_messages[] = "U krijua përdoruesi administrativ paraprakisht (username: admin, password: admin123). Ju lutemi ndryshoni fjalëkalimin menjëherë pas kyçjes!";
    }
    
} catch (PDOException $e) {
    $error_messages[] = "Gabim: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Krijimi i Databazës | Noteria</title>
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
            --warning: #f57c00;
            --warning-light: #fff3e0;
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
        
        .warning {
            background-color: var(--warning-light);
            color: var(--warning);
            border-left: 5px solid var(--warning);
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
            margin: 0 10px 10px;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .notes {
            margin-top: 30px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        
        .notes h2 {
            color: var(--primary-dark);
            margin-top: 0;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-database"></i> Krijimi i Databazës së Plateformës</h1>
        
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
        
        <div class="message warning">
            <i class="fas fa-exclamation-triangle"></i>
            <p><strong>Kujdes:</strong> Ky script ka krijuar tabela të reja në databazë dhe ka fshirë të gjitha të dhënat e mëparshme të tabelave 'zyrat' dhe 'punetoret'. Nëse keni pasur të dhëna të rëndësishme, ju lutemi rivendosni databazën nga një kopje rezervë.</p>
        </div>
        
        <div class="notes">
            <h2>Informacion:</h2>
            <p>Janë krijuar me sukses këto tabela:</p>
            <ul>
                <li><strong>zyrat</strong> - për të ruajtur të dhënat e zyrave noteriale</li>
                <li><strong>punetoret</strong> - për të ruajtur të dhënat e punëtorëve të secilës zyrë</li>
                <li><strong>users</strong> - për të ruajtur përdoruesit e sistemit (administratorët)</li>
            </ul>
            <p>Tani mund të vazhdoni me regjistrimin e zyrave noteriale dhe menaxhimin e punëtorëve.</p>
        </div>
        
        <div class="actions">
            <a href="index.php" class="btn">
                <i class="fas fa-home"></i> Faqja Kryesore
            </a>
            <a href="login.php" class="btn">
                <i class="fas fa-sign-in-alt"></i> Kyçu
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