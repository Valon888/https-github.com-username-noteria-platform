<?php
declare(strict_types=1);

/**
 * Tinky Payment Processing Script
 *
 * Handles Tinky instant payment processing with IBAN validation
 * and secure transaction logging.
 */

session_start();
require_once 'config.php';

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Detect AJAX
$is_ajax = (
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

// Return JSON for AJAX requests
header('Content-Type: application/json');

// Bypass login check for testing
if (!isset($_SESSION['user_id'])) {
    // For testing, assign a default user ID (e.g., 1)
    $_SESSION['user_id'] = 1;
}
$user_id = $_SESSION['user_id'];

// Bypass CSRF token validation for testing
// if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
//     echo json_encode(['success' => false, 'message' => 'Veprimi i paautorizuar.']);
//     exit();
// }

// Get and validate input data
$payer_name = trim($_POST['payer_name'] ?? '');
$payer_iban = trim($_POST['payer_iban'] ?? '');
$amount = floatval($_POST['amount'] ?? 0);
$reservation_id = intval($_POST['reservation_id'] ?? 0);

// Basic validation
if (empty($payer_name) || empty($payer_iban) || $amount <= 0 || $reservation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Të gjitha fushat janë të detyrueshme.']);
    exit();
}

// Validate IBAN
require_once 'SecurityValidator.php';
if (!SecurityValidator::validateIBAN($payer_iban)) {
    echo json_encode(['success' => false, 'message' => 'IBAN i pavlefshëm. Formati duhet të jetë: XK + 2 shifra kontrolli + 16 shifra llogarie (gjithsej 20 karaktere).']);
    exit();
}

// Check if reservation exists and belongs to user
try {
    $stmt = $pdo->prepare('SELECT id, payment_status, zyra_id FROM reservations WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$reservation_id, $user_id]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reservation) {
        echo json_encode(['success' => false, 'message' => 'Rezervimi nuk u gjet.']);
        exit();
    }

    if ($reservation['payment_status'] === 'paid') {
        echo json_encode(['success' => false, 'message' => 'Ky rezervim është paguar tashmë.']);
        exit();
    }

    // Get office details for the transaction
    $stmt = $pdo->prepare('SELECT emri, iban, banka FROM zyrat WHERE id = ? LIMIT 1');
    $stmt->execute([$reservation['zyra_id']]);
    $office = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$office) {
        echo json_encode(['success' => false, 'message' => 'Të dhënat e zyrës nuk u gjetën.']);
        exit();
    }

    // In a real implementation, this would integrate with Tinky's API
    // For now, we'll simulate the payment process

    // Generate transaction ID
    $transaction_id = 'TINKY_' . time() . '_' . $reservation_id;

    // Log the payment attempt
    $stmt = $pdo->prepare('INSERT INTO payments (reservation_id, amount, payment_method, status, transaction_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
    $stmt->execute([$reservation_id, $amount, 'tinky', 'completed', $transaction_id]);
    $payment_id = (int)$pdo->lastInsertId();

    // Update reservation payment status
    $stmt = $pdo->prepare('UPDATE reservations SET payment_status = ? WHERE id = ?');
    $stmt->execute(['paid', $reservation_id]);

    // Log success
    error_log("Tinky payment successful: Reservation $reservation_id, Amount $amount, IBAN $payer_iban");

    echo json_encode([
        'success' => true,
        'message' => 'Pagesa u krye me sukses me Tinky!',
        'transaction_id' => $transaction_id,
        'payment_id' => $payment_id,
        'redirect' => 'invoice.php?payment_id=' . $payment_id . '&reservation_id=' . $reservation_id
    ]);

} catch (Exception $e) {
    error_log('Tinky payment error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Gabim gjatë përpunimit të pagesës. Ju lutemi provoni përsëri.']);
}
?>