<?php
// Dashboard i shpejtë për verifikimin e pagesave - 3 minuta
// filepath: d:\xampp\htdocs\noteria\payment_verification_admin.php

require_once 'payment_config.php';
require_once 'email_config.php';

// Siguria - vetëm admin mund ta qasë
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    // Për test, lejojmë qasje pa login - në produksion duhet hequr
    // exit('Access denied. Admin login required.');
}

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Proceson veprimet e admin
if ($_POST && isset($_POST['action'])) {
    $payment_id = intval($_POST['payment_id']);
    $action = $_POST['action'];
    $admin_notes = $_POST['admin_notes'] ?? '';
    
    if ($action === 'approve' || $action === 'reject') {
        $new_status = ($action === 'approve') ? 'verified' : 'rejected';
        
        // Përditëso statusin e pagesës
        $stmt = $pdo->prepare("
            UPDATE payment_logs 
            SET verification_status = ?, admin_notes = ?, verified_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$new_status, $admin_notes, $payment_id]);
        
        // Merr të dhënat e pagesës për email
        $stmt = $pdo->prepare("SELECT * FROM payment_logs WHERE id = ?");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($payment) {
            // Dërgo email konfirmimi
            $status_text = ($new_status === 'verified') ? 'approved' : 'rejected';
            sendPaymentVerificationEmail(
                $payment['office_email'], 
                $payment['office_name'], 
                $payment['transaction_id'], 
                $status_text
            );
            
            // Log audit
            $stmt = $pdo->prepare("
                INSERT INTO payment_audit_log (transaction_id, action, user_ip, user_agent, additional_data) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $audit_data = json_encode([
                'payment_id' => $payment_id,
                'status' => $new_status,
                'admin_notes' => $admin_notes,
                'admin_action' => true
            ]);
            $stmt->execute([
                $payment['transaction_id'],
                "payment_" . $action,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                $audit_data
            ]);
        }
        
        $success_message = "Pagesa u " . ($action === 'approve' ? 'aprovua' : 'refuzua') . " me sukses!";
    }
}

// Merr pagesat e papërpunuara me të dhëna të telefonit
$stmt = $pdo->query("
    SELECT *, 
           CASE 
               WHEN phone_verified = 1 THEN '✅ Verifikuar'
               WHEN phone_number IS NOT NULL THEN '📱 Në pritje'
               ELSE '❌ Pa telefon'
           END as phone_status,
           TIMESTAMPDIFF(SECOND, created_at, NOW()) as seconds_elapsed
    FROM payment_logs 
    WHERE verification_status = 'pending' 
    ORDER BY created_at DESC
");
$pending_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Merr statistikat e shpejta me të dhëna telefoni
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified,
        SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
        SUM(CASE WHEN phone_verified = 1 THEN 1 ELSE 0 END) as phone_verified_count,
        SUM(CASE WHEN phone_number IS NOT NULL AND phone_verified = 0 THEN 1 ELSE 0 END) as phone_pending_count,
        AVG(CASE WHEN phone_verified = 1 THEN TIMESTAMPDIFF(SECOND, created_at, phone_verified_at) ELSE NULL END) as avg_phone_verification_time
    FROM payment_logs
";
$stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚡ Verifikim i Shpejtë Pagesash - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { 
            max-width: 1400px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 15px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        .header h1 {
            font-size: 1.8rem;
            font-weight: 700;
        }
        .timer {
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 1.1rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 20px 30px;
            background: #f8f9fa;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .pending { color: #ffc107; }
        .verified { color: #28a745; }
        .rejected { color: #dc3545; }
        .total { color: #007bff; }
        .today { color: #17a2b8; }
        
        .main-content {
            padding: 30px;
        }
        .payment-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .payment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .payment-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .payment-id {
            font-weight: bold;
            color: #495057;
        }
        .payment-time {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .payment-body {
            padding: 20px;
        }
        .payment-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .info-item {
            display: flex;
            flex-direction: column;
        }
        .info-label {
            color: #6c757d;
            font-size: 0.8rem;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .info-value {
            color: #495057;
            font-weight: 500;
        }
        .payment-proof {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        .proof-link {
            color: #007bff;
            text-decoration: none;
            font-weight: 600;
        }
        .proof-link:hover {
            text-decoration: underline;
        }
        .action-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-approve {
            background: #28a745;
            color: white;
        }
        .btn-approve:hover {
            background: #218838;
            transform: translateY(-1px);
        }
        .btn-reject {
            background: #dc3545;
            color: white;
        }
        .btn-reject:hover {
            background: #c82333;
            transform: translateY(-1px);
        }
        .notes-textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-family: inherit;
            resize: vertical;
            min-height: 80px;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .no-payments {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        .no-payments h3 {
            margin-bottom: 10px;
            color: #28a745;
        }
        .refresh-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,123,255,0.3);
            transition: all 0.3s ease;
        }
        .refresh-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,123,255,0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚡ Verifikim i Shpejtë Pagesash</h1>
            <div class="timer" id="timer">
                Target: 3 min/pagesa
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number pending"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Në pritje</div>
            </div>
            <div class="stat-card">
                <div class="stat-number verified"><?php echo $stats['verified']; ?></div>
                <div class="stat-label">Të verifikuara</div>
            </div>
            <div class="stat-card">
                <div class="stat-number rejected"><?php echo $stats['rejected']; ?></div>
                <div class="stat-label">Të refuzuara</div>
            </div>
            <div class="stat-card">
                <div class="stat-number today"><?php echo $stats['today']; ?></div>
                <div class="stat-label">Sot</div>
            </div>
            <div class="stat-card">
                <div class="stat-number total"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Totali</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="stat-number" style="color: white;"><?php echo $stats['phone_verified_count']; ?></div>
                <div class="stat-label" style="color: white;">📱 SMS Verifikuar</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #ffa726 0%, #ff7043 100%);">
                <div class="stat-number" style="color: white;"><?php echo $stats['phone_pending_count']; ?></div>
                <div class="stat-label" style="color: white;">📱 SMS Në pritje</div>
            </div>
            <?php if ($stats['avg_phone_verification_time']): ?>
            <div class="stat-card" style="background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%);">
                <div class="stat-number" style="color: white;"><?php echo round($stats['avg_phone_verification_time']); ?>s</div>
                <div class="stat-label" style="color: white;">⚡ Kohë mesatare SMS</div>
            </div>
            <?php endif; ?>
        </div>

        <div class="main-content">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    ✅ <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($pending_payments)): ?>
                <div class="no-payments">
                    <h3>🎉 Të gjitha pagesat janë verifikuar!</h3>
                    <p>Nuk ka pagesa të reja për tu verifikuar në këtë moment.</p>
                    <p><small>Faqja do të përditësohet automatikisht çdo 30 sekonda.</small></p>
                </div>
            <?php else: ?>
                <?php foreach ($pending_payments as $payment): ?>
                    <div class="payment-card" id="payment-<?php echo $payment['id']; ?>">
                        <div class="payment-header">
                            <div class="payment-id">
                                Pagesa #<?php echo $payment['id']; ?> 
                                - <?php echo htmlspecialchars($payment['transaction_id']); ?>
                            </div>
                            <div class="payment-time">
                                <?php 
                                $created = new DateTime($payment['created_at']);
                                $now = new DateTime();
                                $diff = $now->diff($created);
                                
                                if ($diff->h > 0) {
                                    echo $diff->h . 'h ' . $diff->i . 'm para';
                                } else {
                                    echo $diff->i . ' minuta para';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="payment-body">
                            <div class="payment-info">
                                <div class="info-item">
                                    <div class="info-label">Zyra e Noterisë</div>
                                    <div class="info-value"><?php echo htmlspecialchars($payment['office_name']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Email</div>
                                    <div class="info-value"><?php echo htmlspecialchars($payment['office_email']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Metoda e Pagesës</div>
                                    <div class="info-value"><?php echo htmlspecialchars($payment['payment_method']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Shuma</div>
                                    <div class="info-value">€<?php echo number_format($payment['payment_amount'], 2); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">📱 Status Telefoni</div>
                                    <div class="info-value">
                                        <?php echo $payment['phone_status']; ?>
                                        <?php if ($payment['phone_number']): ?>
                                            <small style="display: block; color: #666;">
                                                <?php echo htmlspecialchars($payment['phone_number']); ?>
                                            </small>
                                        <?php endif; ?>
                                        <?php if ($payment['phone_verified_at']): ?>
                                            <small style="display: block; color: #4caf50;">
                                                Verifikuar më: <?php echo date('H:i:s', strtotime($payment['phone_verified_at'])); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">⏱️ Kohë e kaluar</div>
                                    <div class="info-value">
                                        <?php 
                                        $elapsed = $payment['seconds_elapsed'];
                                        $color = $elapsed > 180 ? '#f44336' : ($elapsed > 120 ? '#ff9800' : '#4caf50');
                                        echo "<span style='color: $color; font-weight: bold;'>";
                                        echo gmdate('i:s', $elapsed) . " (3:00 target)";
                                        echo "</span>";
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($payment['payment_details'])): ?>
                                <div class="info-item">
                                    <div class="info-label">Të dhënat e Pagesës</div>
                                    <div class="info-value"><?php echo htmlspecialchars($payment['payment_details']); ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($payment['file_path']) && file_exists($payment['file_path'])): ?>
                                <div class="payment-proof">
                                    <div class="info-label">📄 Dëshmi e Pagesës</div>
                                    <a href="<?php echo htmlspecialchars($payment['file_path']); ?>" 
                                       target="_blank" class="proof-link">
                                        📎 Shiko dëshminë e pagesës
                                    </a>
                                </div>
                            <?php endif; ?>

                            <form method="POST" class="action-form">
                                <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                
                                <div class="action-buttons">
                                    <button type="submit" name="action" value="approve" class="btn btn-approve">
                                        ✅ Aprovo Pagesën
                                    </button>
                                    <button type="submit" name="action" value="reject" class="btn btn-reject">
                                        ❌ Refuzo Pagesën
                                    </button>
                                </div>
                                
                                <textarea name="admin_notes" class="notes-textarea" 
                                          placeholder="Shënime administrative (opsionale)..."></textarea>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <button class="refresh-btn" onclick="location.reload()" title="Përditëso faqen">
        🔄
    </button>

    <script>
        // Auto-refresh çdo 30 sekonda
        setInterval(function() {
            location.reload();
        }, 30000);

        // Timer për çdo pagesa
        let startTime = Date.now();
        
        function updateTimer() {
            let elapsed = Math.floor((Date.now() - startTime) / 1000);
            let minutes = Math.floor(elapsed / 60);
            let seconds = elapsed % 60;
            
            document.getElementById('timer').innerHTML = 
                `Koha: ${minutes}:${seconds.toString().padStart(2, '0')} | Target: 3 min/pagesa`;
        }
        
        setInterval(updateTimer, 1000);

        // Konfirmim para veprimit
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const action = e.submitter.value;
                const actionText = action === 'approve' ? 'aprovuar' : 'refuzuar';
                
                if (!confirm(`A jeni të sigurt që doni ta ${actionText} këtë pagesa?`)) {
                    e.preventDefault();
                }
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                location.reload();
            }
        });
    </script>
</body>
</html>