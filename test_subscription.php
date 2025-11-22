<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Simulojmë një përdorues të loguar me rol 'zyra'
$_SESSION['user_id'] = 2; // ID e noterit të Tiranës nga install.php
$_SESSION['roli'] = 'zyra';
$_SESSION['zyra_id'] = 1; // ID e zyrës së Tiranës

// Përdoruesit e autorizuar për të testuar
$allowedIps = ['127.0.0.1', '::1'];

// Kontrollojmë nëse përdoruesi është i autorizuar
if (!in_array($_SERVER['REMOTE_ADDR'], $allowedIps)) {
    die('Qasje e ndaluar!');
}
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testo Funksionalitetin e Abonimit - Noteria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #2d6cdf;
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        h2 {
            color: #184fa3;
            margin-top: 30px;
        }
        .test-section {
            margin-bottom: 40px;
            padding: 20px;
            background-color: #f8f9fa;
            border-left: 4px solid #2d6cdf;
            border-radius: 4px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #2d6cdf;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: background-color 0.2s;
        }
        .btn:hover {
            background-color: #184fa3;
        }
        .btn-secondary {
            background-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .btn-success {
            background-color: #28a745;
        }
        .btn-success:hover {
            background-color: #218838;
        }
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .btn-warning:hover {
            background-color: #e0a800;
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            background-color: #e9ecef;
            border-radius: 4px;
            white-space: pre-wrap;
            font-family: monospace;
            font-size: 14px;
            max-height: 300px;
            overflow-y: auto;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #dee2e6;
            text-align: left;
        }
        th {
            background-color: #e9ecef;
            font-weight: 600;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-tools"></i> Test i Funksionalitetit të Abonimit</h1>
        
        <div class="test-section">
            <h2><i class="fas fa-info-circle"></i> Informacion i Përdoruesit Aktual</h2>
            <?php
            $userId = $_SESSION['user_id'];
            $zyraId = $_SESSION['zyra_id'];
            
            $stmt = $pdo->prepare("SELECT u.emri, u.mbiemri, u.email, u.roli, z.emri as zyra_emri 
                                  FROM users u 
                                  LEFT JOIN zyrat z ON u.zyra_id = z.id 
                                  WHERE u.id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            echo "<p><strong>Përdoruesi:</strong> " . htmlspecialchars($user['emri']) . " " . htmlspecialchars($user['mbiemri']) . "</p>";
            echo "<p><strong>Email:</strong> " . htmlspecialchars($user['email']) . "</p>";
            echo "<p><strong>Roli:</strong> " . htmlspecialchars($user['roli']) . "</p>";
            echo "<p><strong>Zyra:</strong> " . htmlspecialchars($user['zyra_emri']) . "</p>";
            ?>
        </div>
        
        <div class="test-section">
            <h2><i class="fas fa-calendar-check"></i> Abonimi Aktual</h2>
            <?php
            // Shfaq abonimin aktual
            $stmt = $pdo->prepare("SELECT 
                s.id, 
                s.start_date, 
                s.expiry_date, 
                s.status, 
                s.payment_status,
                DATEDIFF(s.expiry_date, CURDATE()) as days_left
                FROM subscription s
                WHERE s.zyra_id = ? 
                ORDER BY s.expiry_date DESC 
                LIMIT 1");
            $stmt->execute([$zyraId]);
            $subscription = $stmt->fetch();
            
            if ($subscription) {
                echo "<table>";
                echo "<tr><th>ID</th><td>" . $subscription['id'] . "</td></tr>";
                echo "<tr><th>Data e Fillimit</th><td>" . $subscription['start_date'] . "</td></tr>";
                echo "<tr><th>Data e Skadimit</th><td>" . $subscription['expiry_date'] . "</td></tr>";
                echo "<tr><th>Statusi</th><td>" . $subscription['status'] . "</td></tr>";
                echo "<tr><th>Statusi i Pagesës</th><td>" . $subscription['payment_status'] . "</td></tr>";
                echo "<tr><th>Ditë të Mbetura</th><td>" . $subscription['days_left'] . "</td></tr>";
                echo "</table>";
                
                echo "<div class='action-buttons'>";
                echo "<a href='dashboard.php' class='btn'><i class='fas fa-tachometer-alt'></i> Shiko në Panel</a>";
                echo "<a href='renew_subscription.php' class='btn btn-success'><i class='fas fa-sync-alt'></i> Testo Rinovimin</a>";
                echo "</div>";
            } else {
                echo "<p>Nuk u gjet asnjë abonim aktiv për zyrën tuaj.</p>";
                echo "<div class='action-buttons'>";
                echo "<a href='subscribe.php' class='btn btn-success'><i class='fas fa-plus-circle'></i> Testo Abonimin e Ri</a>";
                echo "</div>";
            }
            ?>
        </div>
        
        <div class="test-section">
            <h2><i class="fas fa-history"></i> Historiku i Pagesave</h2>
            <?php
            $stmt = $pdo->prepare("SELECT id, amount, payment_date, payment_method, description, status 
                                  FROM payments 
                                  WHERE zyra_id = ? 
                                  ORDER BY payment_date DESC");
            $stmt->execute([$zyraId]);
            $payments = $stmt->fetchAll();
            
            if (count($payments) > 0) {
                echo "<table>";
                echo "<tr>
                        <th>ID</th>
                        <th>Shuma</th>
                        <th>Data</th>
                        <th>Metoda</th>
                        <th>Përshkrimi</th>
                        <th>Statusi</th>
                      </tr>";
                      
                foreach ($payments as $payment) {
                    echo "<tr>";
                    echo "<td>" . $payment['id'] . "</td>";
                    echo "<td>" . number_format($payment['amount'], 2) . " €</td>";
                    echo "<td>" . $payment['payment_date'] . "</td>";
                    echo "<td>" . $payment['payment_method'] . "</td>";
                    echo "<td>" . $payment['description'] . "</td>";
                    echo "<td>" . $payment['status'] . "</td>";
                    echo "</tr>";
                }
                
                echo "</table>";
            } else {
                echo "<p>Nuk u gjetën pagesa për zyrën tuaj.</p>";
            }
            ?>
        </div>
        
        <div class="test-section">
            <h2><i class="fas fa-cogs"></i> Veprime për Testim</h2>
            
            <div class="action-buttons">
                <?php if ($subscription && $subscription['status'] === 'active'): ?>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="modify_date">
                    <button type="submit" name="shorten_expiry" class="btn btn-warning">
                        <i class="fas fa-clock"></i> Shkurto Skadimin (5 ditë)
                    </button>
                </form>
                
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="modify_status">
                    <button type="submit" name="set_expired" class="btn btn-danger">
                        <i class="fas fa-times-circle"></i> Vendos si të Skaduar
                    </button>
                </form>
                <?php else: ?>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="create_test">
                    <button type="submit" name="create_subscription" class="btn btn-success">
                        <i class="fas fa-plus-circle"></i> Krijo Abonim Test
                    </button>
                </form>
                <?php endif; ?>
                
                <a href="install.php" class="btn btn-secondary">
                    <i class="fas fa-database"></i> Rinstalo Tabelat
                </a>
            </div>
            
            <?php
            // Përpuno veprimet e testimit
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $result = "";
                
                if (isset($_POST['action']) && $_POST['action'] === 'modify_date' && isset($_POST['shorten_expiry'])) {
                    if ($subscription) {
                        $newExpiryDate = date('Y-m-d', strtotime('+5 days'));
                        
                        $stmt = $pdo->prepare("UPDATE subscription SET expiry_date = ? WHERE id = ?");
                        $stmt->execute([$newExpiryDate, $subscription['id']]);
                        
                        $result = "Abonimi u përditësua. Data e re e skadimit: " . $newExpiryDate;
                        $resultClass = "success";
                    } else {
                        $result = "Gabim: Nuk u gjet abonim për të modifikuar!";
                        $resultClass = "error";
                    }
                }
                
                if (isset($_POST['action']) && $_POST['action'] === 'modify_status' && isset($_POST['set_expired'])) {
                    if ($subscription) {
                        $stmt = $pdo->prepare("UPDATE subscription SET status = 'expired' WHERE id = ?");
                        $stmt->execute([$subscription['id']]);
                        
                        $result = "Abonimi u vendos si i skaduar.";
                        $resultClass = "success";
                    } else {
                        $result = "Gabim: Nuk u gjet abonim për të modifikuar!";
                        $resultClass = "error";
                    }
                }
                
                if (isset($_POST['action']) && $_POST['action'] === 'create_test' && isset($_POST['create_subscription'])) {
                    $startDate = date('Y-m-d');
                    $expiryDate = date('Y-m-d', strtotime('+30 days'));
                    
                    $stmt = $pdo->prepare("INSERT INTO subscription 
                        (zyra_id, start_date, expiry_date, status, payment_status, payment_date) 
                        VALUES (?, ?, ?, 'active', 'paid', CURRENT_TIMESTAMP)");
                    $stmt->execute([$zyraId, $startDate, $expiryDate]);
                    
                    $result = "Abonim test u krijua me sukses. Skadimi: " . $expiryDate;
                    $resultClass = "success";
                }
                
                if (!empty($result)) {
                    echo "<div class='result $resultClass'>$result</div>";
                    echo "<script>setTimeout(function() { window.location.reload(); }, 2000);</script>";
                }
            }
            ?>
        </div>
        
        <p><a href="dashboard.php" class="btn"><i class="fas fa-arrow-left"></i> Kthehu në Panel</a></p>
    </div>
</body>
</html>