<?php
/**
 * Faqja e administrimit të kodeve unike të përdoruesve
 * Lejon gjenimin e 1M+ kodeve për përdoruese spesifikë ose të gjithë përdoruesit
 */

require_once 'config.php';
require_once 'confidb.php';

session_start();

// Kontrollo autentifikim admin
if (!isset($_SESSION['user_id']) || $_SESSION['roli'] !== 'admin') {
    header('Location: admin_login.php');
    exit;
}

// Variabla për mesazhe
$message = '';
$message_type = '';

// Kontrollo nëse ka kërkesë për gjenerimin e kodeve
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'generate_for_user' && isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        $count = isset($_POST['count']) ? intval($_POST['count']) : 1000000;
        
        // Kontrollo nëse përdoruesi ekziston
        $stmt = $pdo->prepare("SELECT emri, mbiemri, email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $message = "Përdoruesi nuk u gjet";
            $message_type = 'error';
        } else {
            // Ekzekuto komandën përmes CLI
            $output = shell_exec("cd " . __DIR__ . " && php generate_user_codes.php $user_id $count 2>&1");
            
            if (strpos($output, 'Përfunduar') !== false) {
                $message = "Kodet e unike u gjeneron me sukses për {$user['emri']} {$user['mbiemri']}";
                $message_type = 'success';
            } else {
                $message = "Ka ndodhur një gabim: " . $output;
                $message_type = 'error';
            }
        }
        
    } elseif ($_POST['action'] === 'generate_for_all') {
        $count = isset($_POST['count']) ? intval($_POST['count']) : 1000000;
        
        // Gjenero kode për të gjithë përdoruesit
        $stmt = $pdo->query("SELECT id FROM users WHERE status = 'aktiv'");
        $users = $stmt->fetchAll();
        
        $success_count = 0;
        foreach ($users as $user) {
            $output = shell_exec("cd " . __DIR__ . " && php generate_user_codes.php {$user['id']} $count 2>&1");
            if (strpos($output, 'Përfunduar') !== false) {
                $success_count++;
            }
        }
        
        $message = "Kode u gjeneron për $success_count përdorues";
        $message_type = 'success';
    }
}

// Merr statistikat e kodeve
$stmt = $pdo->query("
    SELECT 
        u.id,
        u.emri,
        u.mbiemri,
        u.email,
        COUNT(uuc.id) as total_codes,
        SUM(CASE WHEN uuc.used = 0 THEN 1 ELSE 0 END) as available_codes,
        SUM(CASE WHEN uuc.used = 1 THEN 1 ELSE 0 END) as used_codes
    FROM users u
    LEFT JOIN user_unique_codes uuc ON u.id = uuc.user_id
    WHERE u.status = 'aktiv'
    GROUP BY u.id
    ORDER BY total_codes DESC
    LIMIT 50
");
$user_stats = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrim i Kodeve Unike - Noteria</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 8px; padding: 30px; }
        
        h1 { color: #333; margin-bottom: 30px; }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            display: none;
        }
        .message.success {
            display: block;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            display: block;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .section {
            margin-bottom: 40px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 4px;
            border-left: 4px solid #007bff;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        button {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        button:hover {
            background: #0056b3;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table th {
            background: #007bff;
            color: white;
            padding: 12px;
            text-align: left;
        }
        table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        table tr:hover {
            background: #f9f9f9;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-box {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 15px;
            border-radius: 4px;
            text-align: center;
        }
        .stat-box .number {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        .stat-box .label {
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚙️ Administrim i Kodeve Unike të Përdoruesve</h1>
        
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="section">
            <h2>Gjenero Kode për Përdorues Spesifik</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="user_id">Përdoruesi:</label>
                    <select name="user_id" id="user_id" required>
                        <option value="">-- Zgjedh përdoruesin --</option>
                        <?php foreach ($user_stats as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo $user['emri'] . ' ' . $user['mbiemri'] . ' (' . $user['total_codes'] . ' kode)'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="count">Numri i Kodeve:</label>
                    <input type="number" name="count" id="count" value="1000000" min="1000" max="10000000">
                </div>
                
                <button type="submit" name="action" value="generate_for_user">
                    🔄 Gjenero Kode
                </button>
            </form>
        </div>
        
        <div class="section">
            <h2>Gjenero Kode për Të Gjithë Përdoruesit</h2>
            <form method="POST" onsubmit="return confirm('Kjo mund të zgjasë disa minuta. Jeni i sigurt?');">
                <div class="form-group">
                    <label for="count_all">Numri i Kodeve për Secilin Përdorues:</label>
                    <input type="number" name="count" id="count_all" value="1000000" min="1000" max="10000000">
                </div>
                
                <button type="submit" name="action" value="generate_for_all">
                    🔄 Gjenero për Të Gjithë
                </button>
            </form>
        </div>
        
        <div class="section">
            <h2>📊 Statistikat e Kodeve</h2>
            
            <div class="stats">
                <div class="stat-box">
                    <div class="label">Përdorues Aktiv</div>
                    <div class="number"><?php echo count($user_stats); ?></div>
                </div>
                <div class="stat-box">
                    <div class="label">Kodet Totale</div>
                    <div class="number">
                        <?php 
                        $total = array_sum(array_column($user_stats, 'total_codes'));
                        echo number_format($total);
                        ?>
                    </div>
                </div>
                <div class="stat-box">
                    <div class="label">Kodet në Dispozicion</div>
                    <div class="number">
                        <?php 
                        $available = array_sum(array_column($user_stats, 'available_codes'));
                        echo number_format($available);
                        ?>
                    </div>
                </div>
                <div class="stat-box">
                    <div class="label">Kodet e Përdorur</div>
                    <div class="number">
                        <?php 
                        $used = array_sum(array_column($user_stats, 'used_codes'));
                        echo number_format($used);
                        ?>
                    </div>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Emri</th>
                        <th>Email</th>
                        <th>Kodet Totale</th>
                        <th>Në Dispozicion</th>
                        <th>Të Përdorur</th>
                        <th>Përqindja Përdorimi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($user_stats as $user): ?>
                        <tr>
                            <td><?php echo $user['emri'] . ' ' . $user['mbiemri']; ?></td>
                            <td><?php echo $user['email']; ?></td>
                            <td><?php echo number_format($user['total_codes']); ?></td>
                            <td><?php echo number_format($user['available_codes']); ?></td>
                            <td><?php echo number_format($user['used_codes']); ?></td>
                            <td>
                                <?php 
                                $percentage = $user['total_codes'] > 0 
                                    ? round(($user['used_codes'] / $user['total_codes']) * 100, 2)
                                    : 0;
                                echo $percentage . '%';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 30px; text-align: center;">
            <a href="admin_dashboard.php" style="color: #007bff; text-decoration: none;">← Kthehu në Dashboard</a>
        </div>
    </div>
</body>
</html>
?>
