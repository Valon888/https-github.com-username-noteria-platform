<?php
// sample_payment_data.php - Shton shembuj pagesash në databazë për testim
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Kërkohet autentifikim për API
require_once 'config.php';
session_start();

// Kontrolloni nëse përdoruesi është admin
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = '';

// Ndërto tabelën e payment_logs nëse nuk ekziston
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payment_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            office_email VARCHAR(255) NOT NULL,
            office_name VARCHAR(255) NOT NULL,
            phone_number VARCHAR(20) NOT NULL,
            operator VARCHAR(50),
            payment_method VARCHAR(50) NOT NULL,
            payment_amount DECIMAL(10,2) NOT NULL,
            payment_details TEXT,
            transaction_id VARCHAR(100) NOT NULL,
            verification_status VARCHAR(20) DEFAULT 'pending',
            file_path VARCHAR(255),
            numri_fiskal VARCHAR(20),
            numri_biznesit VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            verified_at TIMESTAMP NULL DEFAULT NULL,
            verified_by VARCHAR(100),
            UNIQUE(transaction_id)
        )
    ");
    
    // Kontrollo nëse ka të dhëna
    $stmt = $pdo->query("SELECT COUNT(*) FROM payment_logs");
    $count = $stmt->fetchColumn();
    
    if ($count === 0 && isset($_POST['generate'])) {
        // Lista e pagesave shembull
        $samplePayments = [
            [
                'office_email' => 'zyra1@noteria.com',
                'office_name' => 'Zyra Noteriale Prishtina',
                'phone_number' => '+38344123456',
                'operator' => 'Vala',
                'payment_method' => 'bank_transfer',
                'payment_amount' => 150.00,
                'payment_details' => 'IBAN: XK051212012345678906, Banka: ProCredit Bank, Numri Fiskal: 123456789',
                'transaction_id' => 'TXN_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)),
                'verification_status' => 'pending',
                'numri_fiskal' => '123456789',
                'numri_biznesit' => 'B1234567890'
            ],
            [
                'office_email' => 'zyra2@noteria.com',
                'office_name' => 'Zyra Noteriale Prizren',
                'phone_number' => '+38344987654',
                'operator' => 'IPKO',
                'payment_method' => 'paysera',
                'payment_amount' => 120.00,
                'payment_details' => 'Pagesë përmes Paysera',
                'transaction_id' => 'TXN_' . date('Ymd_His', strtotime('-1 day')) . '_' . bin2hex(random_bytes(4)),
                'verification_status' => 'verified',
                'verified_at' => date('Y-m-d H:i:s', strtotime('-12 hours')),
                'verified_by' => 'admin@noteria.com',
                'numri_fiskal' => '987654321',
                'numri_biznesit' => 'B0987654321'
            ],
            [
                'office_email' => 'zyra3@noteria.com',
                'office_name' => 'Zyra Noteriale Pejë',
                'phone_number' => '+38345123456',
                'operator' => 'Vala',
                'payment_method' => 'bank_transfer',
                'payment_amount' => 180.00,
                'payment_details' => 'IBAN: XK051212987654321098, Banka: Raiffeisen Bank, Numri Fiskal: 345678912',
                'transaction_id' => 'TXN_' . date('Ymd_His', strtotime('-3 day')) . '_' . bin2hex(random_bytes(4)),
                'verification_status' => 'rejected',
                'verified_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'verified_by' => 'admin@noteria.com',
                'numri_fiskal' => '345678912',
                'numri_biznesit' => 'B3456789120'
            ],
            [
                'office_email' => 'zyra4@noteria.com',
                'office_name' => 'Zyra Noteriale Gjakovë',
                'phone_number' => '+38345987654',
                'operator' => 'IPKO',
                'payment_method' => 'credit_card',
                'payment_amount' => 140.00,
                'payment_details' => 'Pagesë me kartë krediti',
                'transaction_id' => 'TXN_' . date('Ymd_His', strtotime('-5 day')) . '_' . bin2hex(random_bytes(4)),
                'verification_status' => 'pending',
                'numri_fiskal' => '456789123',
                'numri_biznesit' => 'B4567891230'
            ],
            [
                'office_email' => 'zyra5@noteria.com',
                'office_name' => 'Zyra Noteriale Ferizaj',
                'phone_number' => '+38349123456',
                'operator' => 'Vala',
                'payment_method' => 'bank_transfer',
                'payment_amount' => 160.00,
                'payment_details' => 'IBAN: XK051212456789123456, Banka: TEB Bank, Numri Fiskal: 567891234',
                'transaction_id' => 'TXN_' . date('Ymd_His', strtotime('-7 day')) . '_' . bin2hex(random_bytes(4)),
                'verification_status' => 'verified',
                'verified_at' => date('Y-m-d H:i:s', strtotime('-6 days')),
                'verified_by' => 'admin@noteria.com',
                'numri_fiskal' => '567891234',
                'numri_biznesit' => 'B5678912340'
            ]
        ];
        
        // Shto pagesat shembull në databazë
        $pdo->beginTransaction();
        try {
            foreach ($samplePayments as $payment) {
                $columns = implode(', ', array_keys($payment));
                $placeholders = ':' . implode(', :', array_keys($payment));
                
                $stmt = $pdo->prepare("INSERT INTO payment_logs ($columns) VALUES ($placeholders)");
                $stmt->execute($payment);
            }
            
            $pdo->commit();
            $message = 'U shtuan me sukses ' . count($samplePayments) . ' pagesa shembull në databazë.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Gabim gjatë shtimit të pagesave: ' . $e->getMessage();
        }
    } elseif ($count > 0) {
        $message = 'Ka tashmë ' . $count . ' pagesa në databazë. Nuk u shtuan pagesa të reja.';
    }
} catch (PDOException $e) {
    $message = 'Gabim në lidhjen me databazën: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gjeneruesi i të Dhënave Shembull | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f7fa;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 25px;
        }
        h1 {
            color: #1a56db;
            margin-bottom: 25px;
        }
        .message {
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info {
            background-color: #dbeafe;
            color: #1e40af;
            border-left: 5px solid #2563eb;
        }
        button {
            background-color: #1a56db;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 20px;
        }
        button:hover {
            background-color: #1e40af;
        }
        .links {
            margin-top: 30px;
        }
        .links a {
            display: inline-block;
            margin-right: 15px;
            color: #1a56db;
            text-decoration: none;
            font-weight: 500;
        }
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gjeneruesi i të Dhënave Shembull</h1>
        
        <?php if ($message): ?>
            <div class="message info">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="post">
            <p>Ky mjet shton të dhëna shembull për pagesat në databazë për të lehtësuar testimin e API.</p>
            <p>Të dhënat përfshijnë pesë pagesa me statuse të ndryshme (pending, verified, rejected).</p>
            
            <?php if (isset($count) && $count === 0): ?>
                <button type="submit" name="generate">Gjenero të dhëna shembull</button>
            <?php endif; ?>
        </form>
        
        <div class="links">
            <a href="api_client_test.php">Shko te Klienti i Testimit API</a>
            <a href="token_generator.php">Gjeneruesi i Token-ave API</a>
            <a href="mcp_api.php">API Kryesor</a>
        </div>
    </div>
</body>
</html>