<?php
// verify_invoice.php - Advanced Invoice Verification System
require_once __DIR__ . '/db_connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$is_cli = (php_sapi_name() === 'cli');
if (!$is_cli && session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$uuid = isset($_GET['uuid']) ? trim($_GET['uuid']) : '';
if ($uuid && !preg_match('/^[a-f0-9\-]{36}$/i', $uuid)) {
    $uuid = '';
}
$status = 'not_found';
$invoice = null;
$company = [
    'name' => 'E-Noteria SaaS',
    'address' => 'Rr. Dëshmorët e Kombit, Prishtinë, Kosovë',
    'phone' => '+383 44 123 456',
    'email' => 'info@enoteria.com',
    'logo' => 'images/pngwing.com (1).png',
];

// --- Advanced Rate Limiting, IP Ban, Honeypot, Logging ---
$ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$now = time();
$logfile = __DIR__ . '/verify_invoice.log';
$banfile = __DIR__ . '/verify_invoice_bans.json';

// --- IP Ban List ---
$bans = file_exists($banfile) ? json_decode(file_get_contents($banfile), true) : [];
if (isset($bans[$ip]) && $bans[$ip] > $now) {
    http_response_code(403);
    die('<div style="color:#842029;font-weight:700;font-size:1.2rem;">IP juaj është bllokuar për shkak të abuzimit të mundshëm. Kontaktoni platformën.</div>');
}

// --- Brutal Rate Limiting (per IP, per session, per minute, per UA) ---
if (!isset($_SESSION['verify_invoice_rate'])) $_SESSION['verify_invoice_rate'] = [];
if (!isset($_SESSION['verify_invoice_rate'][$ip])) {
    $_SESSION['verify_invoice_rate'][$ip] = [];
}
if (!isset($_SESSION['verify_invoice_rate'][$ip][$ua])) {
    $_SESSION['verify_invoice_rate'][$ip][$ua] = [];
}
// Remove old entries
foreach ($_SESSION['verify_invoice_rate'][$ip][$ua] as $k => $t) {
    if ($t < $now - 60) unset($_SESSION['verify_invoice_rate'][$ip][$ua][$k]);
}
$_SESSION['verify_invoice_rate'][$ip][$ua][] = $now;
if (count($_SESSION['verify_invoice_rate'][$ip][$ua]) > 10) {
    // Ban for 1 hour
    $bans[$ip] = $now + 3600;
    file_put_contents($banfile, json_encode($bans));
    file_put_contents($logfile, date('Y-m-d H:i:s') . " | BAN | IP: $ip | UA: $ua | UUID: $uuid\n", FILE_APPEND);
    http_response_code(429);
    die('<div style="color:#842029;font-weight:700;font-size:1.2rem;">Kërkesa të shumta të dyshimta. IP juaj është bllokuar për 1 orë.</div>');
}

// --- Honeypot Field (anti-bot) ---
if (isset($_GET['noteria_hp']) && $_GET['noteria_hp'] !== '') {
    $bans[$ip] = $now + 86400; // 24h ban
    file_put_contents($banfile, json_encode($bans));
    file_put_contents($logfile, date('Y-m-d H:i:s') . " | HONEYPOT BAN | IP: $ip | UA: $ua | UUID: $uuid\n", FILE_APPEND);
    http_response_code(403);
    die('<div style="color:#842029;font-weight:700;font-size:1.2rem;">Aksesi i ndaluar.</div>');
}

// --- Log all verification attempts with IP, UA, and UUID ---
file_put_contents($logfile, date('Y-m-d H:i:s') . " | IP: $ip | UA: $ua | UUID: $uuid\n", FILE_APPEND);

// --- Suporto Verification Code (përveç UUID) ---
$verification_code = isset($_GET['code']) ? trim($_GET['code']) : '';
if ($verification_code && !preg_match('/^[A-F0-9]{8}$/i', $verification_code)) {
    $verification_code = '';
}

// --- Admin Update Payment Status ---
$admin_msg = null;
if ($_POST && isset($_POST['update_status']) && isset($_POST['payment_id']) && isset($_POST['new_status'])) {
    // Basic admin check - customize this based on your user roles
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        $payment_id = (int)$_POST['payment_id'];
        $new_status = $_POST['new_status'] === 'completed' ? 'completed' : 'failed';
        $admin_conn = connectToDatabase();
        try {
            $admin_stmt = $admin_conn->prepare("UPDATE payments SET status = ?, updated_at = NOW() WHERE id = ?");
            $admin_stmt->bind_param("si", $new_status, $payment_id);
            $admin_stmt->execute();
            $admin_msg = "✓ Statusi i pagesës u përditësua në: " . ($new_status === 'completed' ? 'E PAGUAR' : 'DËSHTOI');
        } catch (Exception $e) {
            $admin_msg = "✗ Gabim: " . $e->getMessage();
        }
    }
}

if ($uuid || $verification_code) {
    $conn = connectToDatabase();
    
    if ($uuid) {
        // Kërko sipas UUID
        $stmt = $conn->prepare("
            SELECT p.*, u.emri, u.mbiemri, u.email as user_email, u.address as user_address, r.service, r.date as reservation_date, r.time as reservation_time, r.zyra_id, z.emri as zyra_emri, z.qyteti as zyra_qyteti, z.shteti as zyra_shteti
            FROM payments p
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN reservations r ON p.reservation_id = r.id
            LEFT JOIN zyrat z ON r.zyra_id = z.id
            WHERE p.uuid = ?
        ");
        $stmt->bindValue(1, $uuid);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($verification_code) {
        // Kërko sipas Verification Code - gjenerohet njejt si në invoice.php: md5(payment_id + uuid)
        $stmt = $conn->prepare("
            SELECT p.*, u.emri, u.mbiemri, u.email as user_email, u.address as user_address, r.service, r.date as reservation_date, r.time as reservation_time, r.zyra_id, z.emri as zyra_emri, z.qyteti as zyra_qyteti, z.shteti as zyra_shteti
            FROM payments p
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN reservations r ON p.reservation_id = r.id
            LEFT JOIN zyrat z ON r.zyra_id = z.id
        ");
        $stmt->execute();
        $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $row = null;
        foreach ($allRows as $check_row) {
            // Gjenero kodin e verifikimit njejt si në invoice.php
            $generated_code = strtoupper(substr(md5($check_row['id'] . $check_row['uuid']), 0, 8));
            if ($generated_code === strtoupper($verification_code)) {
                $row = $check_row;
                break;
            }
        }
    }
    
    if ($row) {
        $invoice = $row;
        $status = ($row['status'] ?? '') === 'paid' ? 'valid_paid' : 'valid_unpaid';
    } else {
        $status = 'not_found';
    }
} else if (isset($_GET['uuid']) || isset($_GET['code'])) {
    $status = 'invalid_uuid';
}

function statusBadge($status) {
    if ($status === 'valid_paid') {
        return '<span style="background:#EAFAF1;color:#1A7A4A;border:1px solid #A3E4C0;border-radius:20px;padding:6px 18px;font-weight:700;font-size:1rem;"><i class="fas fa-check-circle"></i> Faturë e Paguar dhe e Verifikuar</span>';
    } elseif ($status === 'valid_unpaid') {
        return '<span style="background:#FFF3CD;color:#856404;border:1px solid #FFECB5;border-radius:20px;padding:6px 18px;font-weight:700;font-size:1rem;"><i class="fas fa-exclamation-circle"></i> Faturë e Pa Paguar, por e Verifikuar</span>';
    } elseif ($status === 'invalid_uuid') {
        return '<span style="background:#F8D7DA;color:#842029;border:1px solid #F5C2C7;border-radius:20px;padding:6px 18px;font-weight:700;font-size:1rem;"><i class="fas fa-times-circle"></i> UUID i pavlefshëm</span>';
    } else {
        return '<span style="background:#F8D7DA;color:#842029;border:1px solid #F5C2C7;border-radius:20px;padding:6px 18px;font-weight:700;font-size:1rem;"><i class="fas fa-times-circle"></i> Faturë e pa gjetur</span>';
    }
}

?><!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikimi i Faturës | <?php echo $company['name']; ?></title>
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <style>
        body { background: #f8fafc; font-family: 'DM Sans', Arial, sans-serif; color: #1C2B3A; margin: 0; padding: 0; }
        .verify-container { max-width: 600px; margin: 48px auto; background: #fff; border-radius: 12px; box-shadow: 0 8px 32px rgba(10,31,68,0.10); padding: 36px 32px; }
        .verify-header { display: flex; align-items: center; gap: 18px; margin-bottom: 18px; }
        .verify-header img { height: 60px; }
        .verify-title { font-size: 2rem; font-weight: 800; color: #0A1F44; }
        .verify-badge { margin: 18px 0; }
        .verify-details { margin: 24px 0 0 0; }
        .verify-details table { width: 100%; border-collapse: collapse; }
        .verify-details th, .verify-details td { text-align: left; padding: 8px 0; font-size: 1rem; }
        .verify-details th { color: #6B7A8D; font-weight: 600; width: 160px; }
        .verify-details td { color: #1C2B3A; }
        .verify-footer { margin-top: 32px; color: #6B7A8D; font-size: 0.95rem; text-align: right; }
        .verify-certificate { margin-top: 32px; background: #f1f5f9; border-radius: 8px; padding: 18px 20px; border: 1px solid #E8D5A3; }
        .verify-certificate strong { color: #0A1F44; }
        .verify-certificate .fa-shield-check { color: #10B981; margin-right: 6px; }
        .verify-uuid { font-size: 0.98rem; color: #C8A96E; font-family: monospace; }
        .verify-actions { margin-top: 24px; text-align: center; }
        .verify-actions a { display: inline-block; background: #0A1F44; color: #fff; padding: 10px 28px; border-radius: 6px; font-weight: 700; text-decoration: none; margin: 0 8px; transition: background 0.2s; }
        .verify-actions a:hover { background: #C8A96E; color: #0A1F44; }
        .admin-panel { margin-top: 32px; background: #fff3cd; border-radius: 8px; padding: 18px 20px; border: 2px solid #FFC107; }
        .admin-panel h3 { color: #856404; margin: 0 0 12px 0; font-size: 1.1rem; }
        .admin-panel form { display: flex; gap: 12px; align-items: flex-end; }
        .admin-panel select { padding: 8px 12px; border: 1px solid #856404; border-radius: 6px; font-weight: 600; cursor: pointer; background: #fff; }
        .admin-panel button { padding: 8px 20px; background: #856404; color: #fff; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; transition: background 0.2s; }
        .admin-panel button:hover { background: #704202; }
    </style>
</head>
<body>
<div class="verify-container">
    <div class="verify-header">
        <img src="<?php echo $company['logo']; ?>" alt="Logo">
        <div>
            <div class="verify-title">Verifikimi i Faturës</div>
            <div class="verify-uuid">
                <?php 
                if ($uuid) { 
                    echo 'UUID: ' . htmlspecialchars($uuid);
                    if ($invoice && isset($invoice['id'])) {
                        $code = strtoupper(substr(md5($invoice['id'] . $uuid), 0, 8));
                        echo '<br>Kodi Verifikimi: <strong style="color: #C8A96E;">' . htmlspecialchars($code) . '</strong>';
                    }
                } elseif ($verification_code) {
                    echo 'Kodi Verifikimi: <strong style="color: #C8A96E;">' . htmlspecialchars($verification_code) . '</strong>';
                } else {
                    echo 'Për verifikimin, përdorni UUID ose Kodin e Verifikimit.';
                }
                ?>
            </div>
        </div>
    </div>
    <div class="verify-badge">
        <?php echo statusBadge($status); ?>
    </div>
    <?php if ($admin_msg): ?>
        <div style="margin: 18px 0; padding: 12px 16px; background: <?php echo strpos($admin_msg, '✓') !== false ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo strpos($admin_msg, '✓') !== false ? '#155724' : '#721c24'; ?>; border-radius: 6px; font-weight: 700;">
            <?php echo htmlspecialchars($admin_msg); ?>
        </div>
    <?php endif; ?>
    <?php if ($invoice): ?>
        <div class="verify-details">
            <table>
                <tr><th>Numri Faturës:</th><td><?php echo htmlspecialchars($invoice['id']); ?></td></tr>
                <tr><th>Emri Klientit:</th><td><?php echo htmlspecialchars(trim(($invoice['emri'] ?? '') . ' ' . ($invoice['mbiemri'] ?? ''))); ?></td></tr>
                <tr><th>Adresa Klientit:</th><td><?php echo htmlspecialchars($invoice['user_address'] ?? ''); ?></td></tr>
                <tr><th>Email Klientit:</th><td><?php echo htmlspecialchars($invoice['user_email'] ?? ''); ?></td></tr>
                <tr><th>Shërbimi:</th><td><?php echo htmlspecialchars($invoice['service'] ?? ''); ?></td></tr>
                <tr><th>Data Rezervimi:</th><td><?php echo isset($invoice['reservation_date']) ? date('d.m.Y', strtotime($invoice['reservation_date'])) : ''; ?></td></tr>
                <tr><th>Ora Rezervimi:</th><td><?php echo htmlspecialchars($invoice['reservation_time'] ?? ''); ?></td></tr>
                <tr><th>Zyra:</th><td><?php 
                    $zyra = htmlspecialchars($invoice['zyra_emri'] ?? '');
                    if (!empty($invoice['zyra_qyteti'])) $zyra .= ', ' . htmlspecialchars($invoice['zyra_qyteti']);
                    if (!empty($invoice['zyra_shteti'])) $zyra .= ', ' . htmlspecialchars($invoice['zyra_shteti']);
                    echo $zyra;
                ?></td></tr>
                <tr><th>Shuma Neto:</th><td>€<?php echo number_format($invoice['amount'], 2); ?></td></tr>
                <tr><th>TVSH (18%):</th><td>€<?php echo number_format($invoice['amount'] * 0.18, 2); ?></td></tr>
                <tr><th>Shuma Totale:</th><td><strong>€<?php echo number_format($invoice['amount'] * 1.18, 2); ?></strong></td></tr>
                <tr><th>Data e Lëshimit:</th><td><?php echo isset($invoice['created_at']) ? date('d.m.Y H:i', strtotime($invoice['created_at'])) : ''; ?></td></tr>
                <tr><th>UUID:</th><td style="font-family: monospace; font-size: 0.9rem;"><?php echo htmlspecialchars($invoice['uuid']); ?></td></tr>
            </table>
        </div>
        <div class="verify-certificate">
            <i class="fas fa-shield-check"></i>
            <strong>Kjo faturë është verifikuar elektronikisht nga Platforma Noteriale Zyrtare.</strong><br>
            Data e verifikimit: <?php echo date('d.m.Y H:i'); ?>
        </div>
        <div class="verify-actions">
            <a href="invoice.php?payment_id=<?php echo isset($invoice['id']) ? urlencode($invoice['id']) : ''; ?>&lang=sq" target="_blank"><i class="fas fa-file-invoice"></i> Shiko Faturën Plotë</a>
            <a href="#" onclick="window.print();return false;"><i class="fas fa-print"></i> Printo Certifikatën</a>
        </div>
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin' && isset($invoice['id'])): ?>
            <div class="admin-panel">
                <h3>🔧 Panel Admin - Përditëso Statusin e Pagesës</h3>
                <form method="POST">
                    <input type="hidden" name="payment_id" value="<?php echo htmlspecialchars($invoice['id']); ?>">
                    <input type="hidden" name="update_status" value="1">
                    <select name="new_status">
                        <option value="unpaid" <?php echo ($invoice['status'] === 'unpaid' ? 'selected' : ''); ?>>E Pa Paguar</option>
                        <option value="paid" <?php echo ($invoice['status'] === 'paid' ? 'selected' : ''); ?>>E Paguar</option>
                    </select>
                    <button type="submit">Përditëso Statusin</button>
                </form>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div style="margin:32px 0 0 0; color:#842029; font-weight:600; font-size:1.1rem;">
            Kërkesa për verifikim u procesua.<br>
            Nëse UUID ose Kodi i Verifikimit është i saktë dhe ekziston, të dhënat do të shfaqen këtu.<br>
            Për arsye sigurie, të dhënat nuk shfaqen për të dhëna të pavlefshme ose të panjohura.<br>
            <br>
            <strong>💡 Si të verifikoni faturën:</strong><br>
            <small>
                • Përdorni UUID direktisht: <code>verify_invoice.php?uuid=YOUR_UUID</code><br>
                • Ose përdorni Kodin e Verifikimit: <code>verify_invoice.php?code=YOUR_CODE</code><br>
                Shembulli: <code>verify_invoice.php?code=1F0D99F7</code>
            </small><br><br>
            Nëse besoni se kjo është një gabim, kontaktoni platformën.
        </div>
    <?php endif; ?>
    <div class="verify-footer">
        &copy; <?php echo date('Y'); ?> <?php echo $company['name']; ?> | Platforma Noteriale Zyrtare
    </div>
</div>
</body>
<!-- Honeypot anti-bot field (hidden from users) -->
<form method="get" style="display:none;">
    <input type="text" name="noteria_hp" value="">
</form>
</html>
