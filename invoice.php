<?php
// invoice.php - Faturë elektronike profesionale, moderne, me QR, metadata, watermark
require_once __DIR__ . '/db_connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$lang = $_GET['lang'] ?? 'sq';
$payment_id = $_GET['payment_id'] ?? '';
$reservation_id = isset($_GET['reservation_id']) && is_numeric($_GET['reservation_id']) ? (int)$_GET['reservation_id'] : 0;
if (is_numeric($payment_id)) {
    $payment_id = (int)$payment_id;
}
$user_id = $_SESSION['user_id'] ?? null;

$company = [
    'name' => 'E-Noteria SaaS',
    'address' => 'Rr. Dëshmorët e Kombit, Prishtinë, Kosovë',
    'phone' => '+383 44 123 456',
    'email' => 'info@enoteria.com',
    'logo' => 'images/pngwing.com (1).png',
];

$client = ['name' => '', 'address' => '', 'email' => ''];
$invoice = [
    'number' => '',
    'date' => '',
    'due' => '',
    'uuid' => '',
    'verification_code' => '',
    'verification_url' => '',
    'items' => [],
    'notes' => 'Ju falënderojmë nga zemra për besimin dhe bashkëpunimin tuaj me platformën tonë noteriale. Shërbimi juaj është prioriteti ynë dhe jemi të nderuar që na zgjodhët për nevojat tuaja ligjore. Për çdo pyetje apo ndihmë shtesë, ekipi ynë është gjithmonë në dispozicionin tuaj.',
];

$subtotal = 0;
$tax = 0;
$total = 0;

// Helper for UUID
function generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function getServiceBasePrice(string $service): float {
    $service_prices = [
        'Kontratë Shitblerjeje' => 50,
        'Kontratë dhuratë' => 40,
        'Kontratë qiraje' => 30,
        'Kontratë furnizimi' => 35,
        'Kontratë përkujdesjeje' => 40,
        'Kontratë prenotimi' => 30,
        'Kontratë të tjera të lejuara me ligj' => 50,
        'Autorizim për vozitje të automjetit' => 10,
        'Legalizim' => 25,
        'Vertetim Dokumenti' => 15,
        'Deklaratë' => 20,
        'Këmbimet Pronash' => 30,
    ];

    return (float)($service_prices[$service] ?? 0);
}

function fetchReservationInvoiceData(PDO $conn, int $reservationId): ?array {
    if ($reservationId <= 0) {
        return null;
    }

    $stmtRes = $conn->prepare(" 
        SELECT
            r.id as res_id,
            r.user_id,
            r.service,
            r.date as reservation_date,
            r.time as reservation_time,
            r.payment_method,
            u.emri,
            u.mbiemri,
            u.email as user_email,
            u.address as user_address,
            z.emri as zyra_emri,
            z.qyteti as zyra_qyteti,
            z.shteti as zyra_shteti,
            z.email as zyra_email,
            z.telefoni as zyra_telefoni,
            pun.emri as punonjesi_emri,
            pun.mbiemri as punonjesi_mbiemri
        FROM reservations r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN zyrat z ON r.zyra_id = z.id
        LEFT JOIN punonjesit pun ON r.punonjesi_id = pun.id
        WHERE r.id = ?
        LIMIT 1
    ");
    $stmtRes->bindValue(1, $reservationId, PDO::PARAM_INT);
    $stmtRes->execute();
    $resRow = $stmtRes->fetch(PDO::FETCH_ASSOC);

    return $resRow ?: null;
}

if ($payment_id) {
    $conn = connectToDatabase();
    
    // Fetch complete data from payments, users, and reservations (with employee details)
    $stmt = $conn->prepare(" 
        SELECT 
            p.id,
            p.user_id,
            p.reservation_id,
            p.amount,
            p.created_at,
            p.uuid,
            p.payment_method as payment_method_col,
            p.transaction_id,
            u.emri,
            u.mbiemri,
            u.email as user_email,
            u.address as user_address,
            r.id as res_id,
            r.user_id as reservation_user_id,
            r.service,
            r.date as reservation_date,
            r.time as reservation_time,
            r.zyra_id,
            r.punonjesi_id,
            r.payment_method as reservation_payment_method,
            z.emri as zyra_emri,
            z.qyteti as zyra_qyteti,
            z.shteti as zyra_shteti,
            z.email as zyra_email,
            z.telefoni as zyra_telefoni,
            pun.emri as punonjesi_emri,
            pun.mbiemri as punonjesi_mbiemri
        FROM payments p
        LEFT JOIN reservations r ON p.reservation_id = r.id
        LEFT JOIN users u ON u.id = COALESCE(p.user_id, r.user_id)
        LEFT JOIN zyrat z ON r.zyra_id = z.id
        LEFT JOIN punonjesit pun ON r.punonjesi_id = pun.id
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->bindValue(1, $payment_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug logging
    if ($row) {
        error_log("Invoice Payment ID: " . $payment_id . " => Reservation ID: " . ($row['reservation_id'] ?? 'null') . " => Service: " . ($row['service'] ?? 'null'));
    }
    
    if ($row) {
        $resolvedReservation = null;
        $paymentReservationId = !empty($row['reservation_id']) ? (int)$row['reservation_id'] : 0;
        $requestedReservationId = $reservation_id > 0 ? (int)$reservation_id : 0;

        // 1) Use payment-linked reservation as canonical source
        if ($paymentReservationId > 0) {
            $resolvedReservation = fetchReservationInvoiceData($conn, $paymentReservationId);
        }

        // 2) If payment has no reservation_id, use explicit URL reservation_id as fallback
        if (!$resolvedReservation && $requestedReservationId > 0) {
            $resolvedReservation = fetchReservationInvoiceData($conn, $requestedReservationId);
        }

        // 3) If still unresolved, try reservation joined in the current query
        if (!$resolvedReservation && !empty($row['res_id'])) {
            $resolvedReservation = [
                'res_id' => $row['res_id'],
                'service' => $row['service'],
                'reservation_date' => $row['reservation_date'],
                'reservation_time' => $row['reservation_time'],
                'payment_method' => $row['reservation_payment_method'],
                'emri' => $row['emri'],
                'mbiemri' => $row['mbiemri'],
                'user_email' => $row['user_email'],
                'user_address' => $row['user_address'],
                'zyra_emri' => $row['zyra_emri'],
                'zyra_qyteti' => $row['zyra_qyteti'],
                'zyra_shteti' => $row['zyra_shteti'],
                'zyra_email' => $row['zyra_email'],
                'zyra_telefoni' => $row['zyra_telefoni'],
                'punonjesi_emri' => $row['punonjesi_emri'],
                'punonjesi_mbiemri' => $row['punonjesi_mbiemri'],
            ];
        }

        if ($paymentReservationId > 0 && $requestedReservationId > 0 && $paymentReservationId !== $requestedReservationId) {
            error_log('Invoice reservation mismatch ignored: payment_id=' . (int)$row['id'] . ', payment_reservation_id=' . $paymentReservationId . ', requested_reservation_id=' . $requestedReservationId);
        }

        // Client information from users table
        $client['name'] = trim(($row['emri'] ?? '') . ' ' . ($row['mbiemri'] ?? ''));
        $client['email'] = $row['user_email'] ?? '';
        $client['address'] = $row['user_address'] ?? '';

        if ($resolvedReservation) {
            $client['name'] = trim(($resolvedReservation['emri'] ?? $client['name']) . ' ' . ($resolvedReservation['mbiemri'] ?? ''));
            $client['email'] = $resolvedReservation['user_email'] ?? $client['email'];
            $client['address'] = $resolvedReservation['user_address'] ?? $client['address'];
        }
        
        // Invoice metadata
        $invoice['number'] = $row['id'];
        $invoice['date'] = isset($row['created_at']) ? date('d.m.Y', strtotime($row['created_at'])) : '';
        $invoice['due'] = isset($row['created_at']) ? date('d.m.Y', strtotime($row['created_at'] . ' +15 days')) : '';

        // Generate or retrieve UUID
        $uuid = $row['uuid'] ?? '';
        if (!$uuid || strlen($uuid) !== 36) {
            $uuid = generate_uuid();
            try {
                $update = $conn->prepare("UPDATE payments SET uuid = ? WHERE id = ?");
                $update->execute([$uuid, $row['id']]);
            } catch (PDOException $e) {
                error_log("Error updating UUID: " . $e->getMessage());
            }
        }
        $invoice['uuid'] = $uuid;
        $invoice['verification_code'] = strtoupper(substr(md5($invoice['number'].$invoice['uuid']),0,8));
        $invoice['verification_url'] = 'https://noteria.com/verify_invoice.php?uuid=' . urlencode($invoice['uuid']) . '&code=' . urlencode($invoice['verification_code']);

        // Build invoice description from reservation data (if reservation exists)
        $desc_parts = [];
        
        if (!empty($resolvedReservation['res_id'])) {  // Check if reservation data exists
            // Service
            if (!empty($resolvedReservation['service'])) {
                $desc_parts[] = ['label' => 'Shërbimi Noterial', 'value' => htmlspecialchars($resolvedReservation['service'])];
            }
            
            // Office
            if (!empty($resolvedReservation['zyra_emri'])) {
                $zyra = htmlspecialchars($resolvedReservation['zyra_emri']);
                if (!empty($resolvedReservation['zyra_qyteti'])) $zyra .= ', ' . htmlspecialchars($resolvedReservation['zyra_qyteti']);
                if (!empty($resolvedReservation['zyra_shteti'])) $zyra .= ', ' . htmlspecialchars($resolvedReservation['zyra_shteti']);
                $desc_parts[] = ['label' => 'Zyra Noteriale', 'value' => $zyra];
            }
            
            // Employee (Noterit) - if exists
            $emp_name = trim(($resolvedReservation['punonjesi_emri'] ?? '') . ' ' . ($resolvedReservation['punonjesi_mbiemri'] ?? ''));
            if (!empty($emp_name)) {
                $desc_parts[] = ['label' => 'Noterit', 'value' => htmlspecialchars($emp_name)];
            }
            
            // Reservation Date
            if (!empty($resolvedReservation['reservation_date'])) {
                $desc_parts[] = ['label' => 'Data e Rezervimit', 'value' => date('d.m.Y', strtotime($resolvedReservation['reservation_date']))];
            }
            
            // Reservation Time
            if (!empty($resolvedReservation['reservation_time'])) {
                $desc_parts[] = ['label' => 'Ora e Rezervimit', 'value' => htmlspecialchars($resolvedReservation['reservation_time'])];
            }
        }
        
        // Payment Method (prioritize reservation_payment_method)
        $payment_method = $resolvedReservation['payment_method'] ?? $row['reservation_payment_method'] ?? $row['payment_method_col'] ?? '';
        if (!empty($payment_method)) {
            $payment_label = $payment_method;
            // Map payment method names to user-friendly labels
            if ($payment_method == 'bank_transfer') $payment_label = 'Transferim Bankar';
            if (str_contains($payment_method, 'card')) $payment_label = 'Kartë Krediti';
            if ($payment_method == 'tinky') $payment_label = 'Tinky';
            $desc_parts[] = ['label' => 'Metoda e Pagesës', 'value' => htmlspecialchars($payment_label)];
        }
        
        // Transaction ID
        if (!empty($row['transaction_id'])) {
            $desc_parts[] = ['label' => 'ID Transaksioni', 'value' => htmlspecialchars($row['transaction_id'])];
        }
        
        // Verification Code
        if (!empty($uuid)) {
            $desc_parts[] = ['label' => 'Kodi Verifikimi', 'value' => htmlspecialchars($uuid)];
        }

        // Add invoice item with all details
        $itemAmount = (float)($row['amount'] ?? 0);
        $resolvedService = (string)($resolvedReservation['service'] ?? $row['service'] ?? '');
        if ($itemAmount <= 0 && $resolvedService !== '') {
            $itemAmount = getServiceBasePrice($resolvedService);
        }
        if ($resolvedService !== '' && empty($desc_parts)) {
            $desc_parts[] = ['label' => 'Shërbimi Noterial', 'value' => htmlspecialchars($resolvedService)];
        }
        $invoice['items'][] = ['desc_parts' => $desc_parts, 'qty' => 1, 'price' => $itemAmount];
        
        // Calculate totals
        $subtotal = $itemAmount;
        $tax = $subtotal * 0.18;
        $total = $subtotal + $tax;
    } else {
        // Fallback: treat payment_id as reservation_id and build invoice from real reservation data
        $resLookupId = $reservation_id > 0 ? $reservation_id : $payment_id;
        $resRow = fetchReservationInvoiceData($conn, (int)$resLookupId);

        if ($resRow) {
            $client['name'] = trim(($resRow['emri'] ?? '') . ' ' . ($resRow['mbiemri'] ?? ''));
            $client['email'] = $resRow['user_email'] ?? '';
            $client['address'] = $resRow['user_address'] ?? '';

            $invoice['number'] = 'RES-' . (int)$resRow['res_id'];
            $invoice['date'] = !empty($resRow['reservation_date']) ? date('d.m.Y', strtotime($resRow['reservation_date'])) : date('d.m.Y');
            $invoice['due'] = date('d.m.Y', strtotime('+15 days'));
            $invoice['uuid'] = generate_uuid();
            $invoice['verification_code'] = strtoupper(substr(md5($invoice['number'].$invoice['uuid']),0,8));
            $invoice['verification_url'] = 'https://noteria.com/verify_invoice.php?uuid=' . urlencode($invoice['uuid']) . '&code=' . urlencode($invoice['verification_code']);

            $desc_parts = [];
            if (!empty($resRow['service'])) {
                $desc_parts[] = ['label' => 'Shërbimi Noterial', 'value' => htmlspecialchars($resRow['service'])];
            }
            if (!empty($resRow['zyra_emri'])) {
                $zyra = htmlspecialchars($resRow['zyra_emri']);
                if (!empty($resRow['zyra_qyteti'])) $zyra .= ', ' . htmlspecialchars($resRow['zyra_qyteti']);
                if (!empty($resRow['zyra_shteti'])) $zyra .= ', ' . htmlspecialchars($resRow['zyra_shteti']);
                $desc_parts[] = ['label' => 'Zyra Noteriale', 'value' => $zyra];
            }
            $emp_name = trim(($resRow['punonjesi_emri'] ?? '') . ' ' . ($resRow['punonjesi_mbiemri'] ?? ''));
            if (!empty($emp_name)) {
                $desc_parts[] = ['label' => 'Noterit', 'value' => htmlspecialchars($emp_name)];
            }
            if (!empty($resRow['reservation_date'])) {
                $desc_parts[] = ['label' => 'Data e Rezervimit', 'value' => date('d.m.Y', strtotime($resRow['reservation_date']))];
            }
            if (!empty($resRow['reservation_time'])) {
                $desc_parts[] = ['label' => 'Ora e Rezervimit', 'value' => htmlspecialchars($resRow['reservation_time'])];
            }
            if (!empty($resRow['payment_method'])) {
                $desc_parts[] = ['label' => 'Metoda e Pagesës', 'value' => htmlspecialchars($resRow['payment_method'])];
            }

            $itemAmount = getServiceBasePrice((string)($resRow['service'] ?? ''));
            $invoice['items'][] = ['desc_parts' => $desc_parts, 'qty' => 1, 'price' => $itemAmount];
            $subtotal = $itemAmount;
            $tax = $subtotal * 0.18;
            $total = $subtotal + $tax;
        }
    }
}

if (!$invoice['number']) {
    if ($payment_id && $user_id) {
        try {
            $conn = connectToDatabase();
            $stmtLatestRes = $conn->prepare(" 
                SELECT r.id, r.service, r.date, r.time, u.emri, u.mbiemri, u.email as user_email, u.address as user_address
                FROM reservations r
                LEFT JOIN users u ON r.user_id = u.id
                WHERE r.user_id = ?
                ORDER BY r.id DESC
                LIMIT 1
            ");
            $stmtLatestRes->execute([(int)$user_id]);
            $latestRes = $stmtLatestRes->fetch(PDO::FETCH_ASSOC);

            if ($latestRes) {
                $client['name'] = trim(($latestRes['emri'] ?? '') . ' ' . ($latestRes['mbiemri'] ?? ''));
                $client['email'] = $latestRes['user_email'] ?? '';
                $client['address'] = $latestRes['user_address'] ?? '';
                $invoice['number'] = 'RES-' . (int)$latestRes['id'];
                $invoice['date'] = !empty($latestRes['date']) ? date('d.m.Y', strtotime($latestRes['date'])) : date('d.m.Y');
                $invoice['due'] = date('d.m.Y', strtotime('+15 days'));
                $invoice['uuid'] = generate_uuid();
                $invoice['verification_code'] = strtoupper(substr(md5($invoice['number'].$invoice['uuid']),0,8));
                $invoice['verification_url'] = 'https://noteria.com/verify_invoice.php?uuid=' . urlencode($invoice['uuid']) . '&code=' . urlencode($invoice['verification_code']);

                $service = (string)($latestRes['service'] ?? 'Shërbim noterial');
                $invoice['items'] = [
                    ['desc_parts' => [['label' => 'Shërbimi', 'value' => $service]], 'qty' => 1, 'price' => getServiceBasePrice($service)],
                ];

                $subtotal = (float)$invoice['items'][0]['price'];
                $tax = $subtotal * 0.18;
                $total = $subtotal + $tax;
            }
        } catch (PDOException $e) {
            error_log('Invoice latest reservation fallback failed: ' . $e->getMessage());
        }
    }

    if (!$invoice['number']) {
        $client['name'] = 'Klienti';
        $client['address'] = '';
        $client['email'] = '';
        $invoice['number'] = 'INV-NOT-FOUND';
        $invoice['date'] = date('d.m.Y');
        $invoice['due'] = date('d.m.Y', strtotime('+15 days'));
        $invoice['uuid'] = generate_uuid();
        $invoice['verification_code'] = strtoupper(substr(md5($invoice['number'].$invoice['uuid']),0,8));
        $invoice['verification_url'] = 'https://noteria.com/verify_invoice.php?uuid=' . urlencode($invoice['uuid']) . '&code=' . urlencode($invoice['verification_code']);
        $invoice['items'] = [
            ['desc_parts' => [['label' => 'Shërbimi', 'value' => 'Shërbimi nuk u gjet për këtë pagesë']], 'qty' => 1, 'price' => 0],
        ];
        $subtotal = 0;
        $tax = 0;
        $total = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faturë | <?php echo $company['name']; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
    <style>
        /* Watermark */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-25deg);
            font-size: 6rem;
            color: rgba(200,169,110,0.05);
            font-family: 'Playfair Display', serif;
            z-index: 0;
            pointer-events: none;
            user-select: none;
            font-weight: 800;
            letter-spacing: 8px;
        }
        :root {
            --navy: #0A1F44;
            --gold: #C8A96E;
            --gold-light: #E8D5A3;
            --cream: #FAF8F4;
            --smoke: #F2EFE9;
            --ink: #1C2B3A;
            --muted: #6B7A8D;
            --divider: #DDD8CF;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #ECEAE4;
            font-family: 'DM Sans', sans-serif;
            color: var(--ink);
            min-height: 100vh;
            padding: 48px 24px;
        }

        /* ── TOOLBAR ─────────────────────────────── */
        .toolbar {
            max-width: 860px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-print {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            background: linear-gradient(135deg, var(--navy) 0%, #1a3a6a 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(10,31,68,0.2);
        }
        .btn-print:hover { 
            background: linear-gradient(135deg, #142B5A 0%, #0d1f3f 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(10,31,68,0.3);
        }
        .btn-print:active {
            transform: translateY(0);
        }

        /* ── INVOICE WRAPPER ─────────────────────── */
        .invoice {
            max-width: 860px;
            margin: 0 auto;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(10,31,68,0.16), 0 8px 24px rgba(10,31,68,0.08);
        }

        /* ── HEADER BAND ─────────────────────────── */
        .invoice-header {
            background: linear-gradient(135deg, var(--navy) 0%, #1a3a6a 100%);
            padding: 48px 52px 40px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
            overflow: hidden;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.1);
        }
        .qr-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 10px;
            margin-top: 18px;
        }
        .qr-meta .qr-box {
            background: #fff;
            border-radius: 8px;
            padding: 8px;
            box-shadow: 0 2px 8px rgba(10,31,68,0.08);
            display: inline-block;
        }
        .qr-meta .meta-list {
            font-size: 0.78rem;
            color: var(--gold-light);
            margin-top: 6px;
            text-align: right;
        }
        .qr-meta .meta-list strong {
            color: var(--gold);
            font-weight: 700;
        }
        .qr-meta .verify-link {
            margin-top: 4px;
            font-size: 0.8rem;
            color: var(--gold);
            text-decoration: underline;
            word-break: break-all;
        }
        .digital-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: linear-gradient(135deg, #EAFAF1 0%, #d5f0e3 100%);
            color: #1A7A4A;
            border: 2px solid #A3E4C0;
            border-radius: 24px;
            padding: 8px 18px;
            font-size: 0.87rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-top: 12px;
            margin-bottom: 0;
            box-shadow: 0 2px 8px rgba(26,122,74,0.15);
        }
        .digital-badge i {
            color: #1A7A4A;
        }

        /* decorative geometry */
        .invoice-header::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 220px; height: 220px;
            border-radius: 50%;
            border: 40px solid rgba(200,169,110,0.12);
        }
        .invoice-header::after {
            content: '';
            position: absolute;
            bottom: -30px; right: 120px;
            width: 100px; height: 100px;
            border-radius: 50%;
            border: 20px solid rgba(200,169,110,0.08);
        }

        .header-left { display: flex; flex-direction: column; gap: 16px; z-index: 1; }

        .brand-row {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .coat-of-arms img {
            height: 80px;
            filter: drop-shadow(0 3px 6px rgba(0,0,0,0.15));
            transition: transform 0.3s ease;
        }


        .brand-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.05rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: 0.5px;
            line-height: 1.3;
        }
        .brand-subtitle {
            font-size: 0.78rem;
            color: var(--gold-light);
            font-weight: 400;
            letter-spacing: 0.3px;
            margin-top: 2px;
        }

        .divider-gold {
            width: 48px;
            height: 2px;
            background: linear-gradient(90deg, var(--gold), transparent);
            border-radius: 2px;
        }

        .company-contact {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.6);
            line-height: 1.7;
        }
        .company-contact a { color: rgba(255,255,255,0.6); text-decoration: none; }

        .header-right { text-align: right; z-index: 1; }

        .invoice-label {
            font-family: 'Playfair Display', serif;
            font-size: 3rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: 2px;
            line-height: 1;
        }

        .invoice-label span {
            color: var(--gold);
        }

        .invoice-number {
            margin-top: 14px;
            font-size: 0.78rem;
            font-weight: 500;
            color: var(--gold-light);
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .invoice-dates {
            margin-top: 8px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: flex-end;
        }
        .date-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.82rem;
            color: rgba(255,255,255,0.75);
        }
        .date-item .label { font-weight: 600; color: var(--gold-light); }

        /* ── GOLD ACCENT BAR ─────────────────────── */
        .accent-bar {
            height: 4px;
            background: linear-gradient(90deg, var(--gold) 0%, var(--gold-light) 50%, transparent 100%);
        }

        /* ── BODY ────────────────────────────────── */
        .invoice-body { 
            padding: 48px 52px;
            background: linear-gradient(180deg, #fff 0%, #fafbfd 100%);
        }

        /* ── PARTIES ─────────────────────────────── */
        .parties {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 44px;
        }

        .party-card {
            background: linear-gradient(135deg, var(--cream) 0%, #fbfaf6 100%);
            border-radius: 8px;
            padding: 28px 28px;
            border-top: 4px solid var(--gold);
            position: relative;
            box-shadow: 0 2px 8px rgba(10,31,68,0.06);
            transition: box-shadow 0.3s, transform 0.3s;
        }

        .party-card:hover {
            box-shadow: 0 8px 20px rgba(10,31,68,0.1);
            transform: translateY(-2px);
        }

        .party-label {
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .party-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 6px;
        }

        .party-info {
            font-size: 0.84rem;
            color: var(--muted);
            line-height: 1.8;
        }

        /* ── TABLE ───────────────────────────────── */
        .invoice-table-wrap { margin-bottom: 0; }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
        }

        .invoice-table thead tr {
            background: linear-gradient(90deg, var(--navy) 0%, #1a3a6a 100%);
        }

        .invoice-table thead th {
            padding: 16px 20px;
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: var(--gold-light);
            text-align: left;
        }

        .invoice-table thead th:last-child,
        .invoice-table thead th:nth-child(3) { text-align: right; }
        .invoice-table thead th:nth-child(2) { text-align: center; }

        .invoice-table tbody tr {
            border-bottom: 1px solid var(--divider);
            transition: background 0.15s, box-shadow 0.15s;
        }
        .invoice-table tbody tr:hover { 
            background: #fafbfd;
            box-shadow: inset 0 0 8px rgba(45,108,223,0.05);
        }
        .invoice-table tbody tr:last-child { border-bottom: 3px solid var(--navy); }

        .invoice-table tbody td {
            padding: 20px 20px;
            vertical-align: top;
            font-size: 0.9rem;
        }

        .item-desc-parts { display: flex; flex-direction: column; gap: 8px; }
        .desc-line { display: flex; gap: 8px; align-items: baseline; }
        .desc-key {
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--gold);
            letter-spacing: 1.2px;
            text-transform: uppercase;
            min-width: 100px;
            flex-shrink: 0;
        }
        .desc-val { 
            font-size: 0.92rem; 
            color: var(--ink);
            font-weight: 600;
        }

        .qty-cell { text-align: center; font-weight: 600; color: var(--navy); }
        .price-cell { text-align: right; color: var(--muted); }
        .total-cell { text-align: right; font-weight: 700; color: var(--navy); }

        /* ── TOTALS ──────────────────────────────── */
        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-top: 0;
            border-top: none;
        }

        .totals-table {
            min-width: 300px;
        }

        .totals-table td {
            padding: 10px 20px;
            font-size: 0.9rem;
        }

        .totals-table .t-label { color: var(--muted); font-weight: 500; text-align: left; }
        .totals-table .t-val { text-align: right; font-weight: 600; color: var(--ink); }

        .totals-table .grand-row {
            background: linear-gradient(90deg, var(--navy) 0%, #1a3a6a 100%);
            box-shadow: 0 4px 12px rgba(10,31,68,0.2);
        }
        .totals-table .grand-row td {
            color: #fff;
            padding: 16px 20px;
        }
        .totals-table .grand-row .t-label {
            font-family: 'Playfair Display', serif;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--gold-light);
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }
        .totals-table .grand-row .t-val {
            font-family: 'Playfair Display', serif;
            font-size: 1.35rem;
            color: var(--gold);
            font-weight: 800;
        }

        /* ── NOTES + SIGNATURE ───────────────────── */
        .bottom-section {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 48px;
            margin-top: 48px;
            padding-top: 40px;
            border-top: 2px solid var(--divider);
            align-items: end;
        }


        .notes-label {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--navy);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .notes-text {
            font-size: 0.88rem;
            color: var(--muted);
            line-height: 1.9;
            font-style: italic;
            max-width: 440px;
            background: linear-gradient(135deg, #fafbfd 0%, #f5f8fb 100%);
            padding: 16px;
            border-radius: 6px;
            border-left: 3px solid var(--gold);
        }
        .notes-sign {
            font-size: 0.88rem;
            color: var(--navy);
            font-weight: 700;
            font-style: normal;
            margin-top: 12px;
        }

        .signature-box { 
            text-align: center; 
            min-width: 240px;
            background: linear-gradient(135deg, #fafbfd 0%, #f5f8fb 100%);
            padding: 24px;
            border-radius: 8px;
            border: 2px solid var(--divider);
        }

        .sig-line {
            width: 240px;
            height: 2px;
            background: linear-gradient(90deg, var(--navy) 0%, transparent 100%);
            margin: 0 auto 14px;
        }

        .sig-label {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--navy);
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .sig-date {
            font-size: 0.78rem;
            color: var(--muted);
            margin-top: 8px;
        }

        /* ── FOOTER STRIP ────────────────────────── */
        .invoice-footer {
            background: linear-gradient(90deg, var(--smoke) 0%, #f5f2eb 100%);
            border-top: 2px solid var(--divider);
            padding: 24px 52px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 48px;
        }

        .footer-brand {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--navy);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .footer-legal {
            font-size: 0.75rem;
            color: var(--muted);
            text-align: right;
            line-height: 1.6;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: linear-gradient(135deg, #1A7A4A 0%, #2a9d5f 100%);
            color: #fff;
            border: 2px solid #1A7A4A;
            border-radius: 24px;
            padding: 7px 18px;
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.8px;
            box-shadow: 0 4px 12px rgba(26,122,74,0.25);
        }
        .status-badge::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 0 8px rgba(255,255,255,0.6);
        }

        /* ── PRINT ───────────────────────────────── */
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .invoice { box-shadow: none; border-radius: 0; }
            .invoice-table tbody tr:hover { background: none; }
        }
    </style>
</head>
<body>

<!-- Toolbar -->
<div class="toolbar no-print">
    <button class="btn-print" onclick="window.print()">
        <i class="fas fa-print"></i>
        Printo Faturën
    </button>
</div>

<!-- Invoice -->
<div class="invoice">

    <!-- Watermark -->
    <div class="watermark no-print">Faturë Elektronike</div>
    <!-- Header -->
    <div class="invoice-header">
        <div class="header-left">
            <div class="brand-row">
                <div class="coat-of-arms">
                    <img src="images/pngwing.com%20(1).png" alt="e-Noteria Logo" style="height: 80px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1)); width: auto;">
                </div>
                <div class="brand-text">
                    <div class="brand-title" style="font-size: 1.3rem;">e-Noteria</div>
                    <div class="brand-subtitle" style="font-size: 0.85rem;">Platforma Digjitale e Noterisë</div>
                    <div class="brand-subtitle" style="margin-top:4px; color: var(--gold); font-weight:700; font-size: 0.9rem;">Republika e Kosovës</div>
                </div>
            </div>
            <div class="divider-gold" style="margin-top: 8px;"></div>
            <div class="company-contact" style="margin-top: 12px;">
                <i class="fas fa-map-marker-alt" style="width:14px; color:var(--gold); margin-right:8px;"></i><strong style="color:#fff;">Selia Qendrore:</strong> <?php echo $company['address']; ?><br>
                <i class="fas fa-phone" style="width:14px; color:var(--gold); margin-right:8px;"></i><strong style="color:#fff;">Tel:</strong> <?php echo $company['phone']; ?><br>
                <i class="fas fa-envelope" style="width:14px; color:var(--gold); margin-right:8px;"></i><strong style="color:#fff;">Email:</strong> <a href="mailto:<?php echo $company['email']; ?>"><?php echo $company['email']; ?></a>
            </div>
        </div>
        <div class="header-right">
            <div class="invoice-label">FATU<span>RË</span></div>
            <div class="invoice-number"># <?php echo $invoice['number']; ?></div>
            <div class="invoice-dates">
                <div class="date-item">
                    <span class="label">📅 Lëshuar:</span>
                    <span style="font-weight: 600;"><?php echo $invoice['date']; ?></span>
                </div>
                <div class="date-item">
                    <span class="label">⏰ Afati:</span>
                    <span style="font-weight: 600;"><?php echo $invoice['due']; ?></span>
                </div>
                <div style="margin-top:14px;">
                    <?php 
                    if ($payment_id && isset($row['status'])) {
                        if ($row['status'] === 'paid') {
                            echo '<span class="status-badge" style="background: linear-gradient(135deg, #1A7A4A 0%, #2a9d5f 100%); border: none; font-size: 0.8rem; padding: 7px 16px;">✓ E Paguar</span>';
                        } else {
                            echo '<span class="status-badge" style="background: linear-gradient(135deg, #D97706 0%, #F59E0B 100%); border: none; font-size: 0.8rem; padding: 7px 16px;">⚠ E Pa Paguar</span>';
                        }
                    } else {
                        echo '<span class="status-badge" style="background: linear-gradient(135deg, #1A7A4A 0%, #2a9d5f 100%); border: none; font-size: 0.8rem; padding: 7px 16px;">✓ E Paguar</span>';
                    }
                    ?>
                </div>
            </div>
            <div class="qr-meta">
                <div class="qr-box" style="box-shadow: 0 4px 12px rgba(200,169,110,0.2);">
                    <canvas id="qr-code" width="100" height="100"></canvas>
                </div>
                <div class="meta-list" style="margin-top: 10px; font-size: 0.75rem;">
                    <div style="margin-bottom: 4px;"><strong style="color: var(--gold);">UUID:</strong><br><span style="word-break: break-all; font-size: 0.7rem;"><?php echo substr($invoice['uuid'], 0, 8) . '...' . substr($invoice['uuid'], -8); ?></span></div>
                    <div><strong style="color: var(--gold);">Kod:</strong> <?php echo $invoice['verification_code']; ?></div>
                </div>
                <a class="verify-link" href="verify_invoice.php?uuid=<?php echo urlencode($invoice['uuid']); ?>&code=<?php echo urlencode($invoice['verification_code']); ?>" target="_blank" style="margin-top: 8px;">🔐 Verifiko</a>
                <span class="digital-badge" style="margin-top: 10px; font-size: 0.75rem; padding: 6px 12px;"><i class="fas fa-certificate" style="width: 12px;"></i> E Verifikuar</span>
            </div>
        </div>
    </div>

    <!-- Gold accent -->
    <div class="accent-bar"></div>

    <!-- Body -->
    <div class="invoice-body">

        <!-- Parties -->
        <div class="parties">
            <div class="party-card">
                <div class="party-label"><i class="fas fa-user-tie" style="margin-right:6px;"></i>Faturuar për</div>
                <div class="party-name"><?php echo $client['name']; ?></div>
                <div class="party-info">
                    <?php if ($client['address']): ?><?php echo $client['address']; ?><br><?php endif; ?>
                    <?php if ($client['email']): ?><i class="fas fa-envelope" style="margin-right:5px; font-size:0.75rem;"></i><?php echo $client['email']; ?><?php endif; ?>
                </div>
            </div>
            <div class="party-card issuer">
                <div class="party-label"><i class="fas fa-building-columns" style="margin-right:6px;"></i>Lëshuar nga</div>
                <div class="party-name"><?php echo $company['name']; ?></div>
                <div class="party-info">
                    <?php echo $company['address']; ?><br>
                    <i class="fas fa-envelope" style="margin-right:5px; font-size:0.75rem;"></i><?php echo $company['email']; ?>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="invoice-table-wrap">
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th style="width:36px;">#</th>
                        <th>Përshkrimi i Shërbimit</th>
                        <th style="width:80px; text-align:center;">Sasia</th>
                        <th style="width:120px; text-align:right;">Çmimi</th>
                        <th style="width:130px; text-align:right;">Totali</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($invoice['items'] as $i => $item): ?>
                    <tr>
                        <td style="color:var(--muted); font-size:0.8rem; font-weight:700; padding-top:24px;"><?php echo str_pad($i+1, 2, '0', STR_PAD_LEFT); ?></td>
                        <td>
                            <?php if (!empty($item['desc_parts'])): ?>
                                <div class="item-desc-parts">
                                    <?php foreach($item['desc_parts'] as $part): ?>
                                        <div class="desc-line">
                                            <span class="desc-key"><?php echo $part['label']; ?></span>
                                            <span class="desc-val"><?php echo $part['value']; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <?php echo $item['desc'] ?? ''; ?>
                            <?php endif; ?>
                        </td>
                        <td class="qty-cell"><?php echo $item['qty']; ?></td>
                        <td class="price-cell">€<?php echo number_format($item['price'], 2); ?></td>
                        <td class="total-cell">€<?php echo number_format($item['qty'] * $item['price'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Totals -->
            <div class="totals-section">
                <table class="totals-table">
                    <tr>
                        <td class="t-label">Nëntotali</td>
                        <td class="t-val">€<?php echo number_format($subtotal, 2); ?></td>
                    </tr>
                    <tr>
                        <td class="t-label">TVSH (18%)</td>
                        <td class="t-val">€<?php echo number_format($tax, 2); ?></td>
                    </tr>
                    <tr class="grand-row">
                        <td class="t-label">Totali për Pagesë</td>
                        <td class="t-val">€<?php echo number_format($total, 2); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Notes & Signature -->
        <div class="bottom-section">
            <div class="notes-box">
                <div class="notes-label"><i class="fas fa-quote-left" style="margin-right:6px; color:var(--gold);"></i>Shënim</div>
                <div class="notes-text"><?php echo $invoice['notes']; ?></div>
                <div class="notes-sign">— e-Noteria | Republika e Kosovës</div>
            </div>
            <div class="signature-box">
                <div class="sig-line"></div>
                <div class="sig-label">Noteri / Përfaqësuesi i Autorizuar</div>
                <div class="sig-date">Data: <?php echo date('d.m.Y'); ?></div>
                <span class="digital-badge" style="margin-top:18px;"><i class="fas fa-shield-check"></i> Nënshkruar Elektronikisht</span>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="invoice-footer">
        <div class="footer-brand">
            <i class="fas fa-shield-halved" style="color:var(--gold); margin-right:6px;"></i>
            e-Noteria &nbsp;·&nbsp; Platforma Noteriale Zyrtare
        </div>
        <div class="footer-legal">
            Faturë e lëshuar në përputhje me legjislacionin e Republikës së Kosovës<br>
            NUI/NF · TVSH Nr. · Ministria e Drejtësisë
        </div>
    </div>
</div>

<script>
// QR code for verification
window.addEventListener('DOMContentLoaded', function() {
    var qr = new QRious({
        element: document.getElementById('qr-code'),
        value: <?php echo json_encode($invoice['verification_url']); ?>,
        size: 90,
        background: 'white',
        foreground: '#0A1F44',
        level: 'H'
    });
});
</script>
</body>
</html>