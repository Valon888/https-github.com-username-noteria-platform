<?php
declare(strict_types=1);

/**
 * Payment Processing Script for Noteria
 *
 * Handles secure online payments with AJAX support, rate limiting,
 * duplicate prevention, and comprehensive error handling.
 *
 * @author Noteria Development Team
 * @version 2.0
 * @date 2026-01-05
 */

// Prevent any output before JSON
ob_start();

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-XSS-Protection: 1; mode=block');
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// Detect AJAX early (so fatal errors can still return JSON)
$is_ajax = (
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

// Return JSON even on fatal errors for AJAX requests
register_shutdown_function(function () use ($is_ajax) {
    $error = error_get_last();
    if (!$error) {
        return;
    }

    $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array($error['type'], $fatal_types, true)) {
        return;
    }

    error_log('Fatal error in process_payment.php: ' . ($error['message'] ?? '') . ' in ' . ($error['file'] ?? '') . ':' . ($error['line'] ?? 0));

    if ($is_ajax) {
        if (ob_get_length()) {
            @ob_clean();
        }
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gabim serveri gjatë pagesës. Ju lutemi provoni përsëri.']);
    }
});

// Return JSON on uncaught exceptions for AJAX requests
set_exception_handler(function (Throwable $e) use ($is_ajax) {
    error_log('Uncaught exception in process_payment.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

    if ($is_ajax) {
        if (ob_get_length()) {
            @ob_clean();
        }
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Ndodhi një gabim gjatë përpunimit të pagesës. Ju lutemi provoni përsëri.']);
        exit();
    }

    throw $e;
});

/**
 * Sanitizes a string input by trimming and removing HTML tags.
 * @param string $input The input string
 * @return string Sanitized string
 */
function sanitizeString(string $input): string {
    return trim(strip_tags($input));
}

/**
 * Validates CSRF token from POST data.
 * @return bool True if valid, false otherwise
 */
function validateCsrf(): bool {
    return isset($_POST['csrf_token']) && $_POST['csrf_token'] === ($_SESSION['csrf_token'] ?? '');
}

/**
 * Logs payment attempts to the database.
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param string $bank_name Bank name
 * @param float $amount Payment amount
 * @param string $status Status
 * @param string $message Optional message
 * @param int|null $reservation_id Related reservation identifier
 */
function logPaymentAttempt(PDO $pdo, int $user_id, string $bank_name, float $amount, string $status, string $message = '', ?int $reservation_id = null): void {
    // Only allow valid ENUM values for status
    $valid_statuses = ['pending', 'completed', 'failed', 'refunded'];
    if (!in_array($status, $valid_statuses, true)) {
        $status = 'pending';
    }
    
    // Generate transaction ID
    $transaction_id = 'LOG_' . date('YmdHis') . '_' . strtoupper(substr(md5(uniqid()), 0, 8));
    
    $payment_data = json_encode([
        'user_id' => $user_id,
        'reservation_id' => $reservation_id,
        'bank_name' => $bank_name,
        'amount' => $amount,
        'status' => $status,
        'message' => $message
    ]);
    try {
        // Insert with valid columns from payment_logs schema
        $stmt = $pdo->prepare("
            INSERT INTO payment_logs 
            (transaction_id, amount, payment_method, payment_status, payment_data, created_at) 
            VALUES (?, ?, 'bank_transfer', ?, ?, NOW())
        ");
        $stmt->execute([$transaction_id, $amount, $status, $payment_data]);
    } catch (Throwable $e) {
        error_log("Failed to log payment attempt: " . $e->getMessage());
    }
}

/**
 * Checks rate limiting for payment attempts.
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return int Number of attempts in the last hour
 */
function checkRateLimit(PDO $pdo, int $user_id): int {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempts
            FROM payment_logs
            WHERE log_data LIKE ?
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute(['%"user_id":' . $user_id . '%']);
        return (int)($stmt->fetch()['attempts'] ?? 0);
    } catch (Throwable $e) {
        error_log('Rate limit check failed (payment_logs missing?): ' . $e->getMessage());
        return 0;
    }
}

/**
 * Checks for duplicate payments within 5 minutes.
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param float $amount Payment amount
 * @param string $bank_name Bank name
 * @return int Number of recent payments
 */
function checkDuplicatePayment(PDO $pdo, int $user_id, float $amount, string $bank_name): int {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as recent_payments
            FROM payments
            WHERE user_id = ?
            AND amount = ?
            AND payment_method = ?
            AND status = 'completed'
            AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
        $stmt->execute([$user_id, $amount, $bank_name]);
        return (int)($stmt->fetch()['recent_payments'] ?? 0);
    } catch (Throwable $e) {
        error_log('Duplicate payment check failed: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Retrieves the latest reservation for a user or a specific reservation.
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int|null $reservation_id Reservation ID to target
 * @return array|null Reservation data or null if not found
 */
function findReservationForUser(PDO $pdo, int $user_id, ?int $reservation_id = null): ?array {
    try {
        if ($reservation_id) {
            $stmt = $pdo->prepare('SELECT id, status, payment_status, zyra_id, service, date, time FROM reservations WHERE id = ? AND user_id = ? LIMIT 1');
            $stmt->execute([$reservation_id, $user_id]);
        } else {
            $stmt = $pdo->prepare('SELECT id, status, payment_status, zyra_id, service, date, time FROM reservations WHERE user_id = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$user_id]);
        }

        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        return $reservation ?: null;
    } catch (Throwable $e) {
        error_log('Failed to load reservation for payment: ' . $e->getMessage());
        return null;
    }
}

/**
 * Fetches the office bank details for payment routing.
 * @param PDO $pdo Database connection
 * @param int $zyra_id Office ID
 * @return array|null Office bank details or null if not found
 */
function getOfficePaymentDetails(PDO $pdo, int $zyra_id): ?array {
    try {
        $stmt = $pdo->prepare('SELECT id, emri, banka, iban, llogaria, email FROM zyrat WHERE id = ? LIMIT 1');
        $stmt->execute([$zyra_id]);
        $office = $stmt->fetch(PDO::FETCH_ASSOC);
        return $office ?: null;
    } catch (Throwable $e) {
        error_log('Failed to fetch office payment details: ' . $e->getMessage());
        return null;
    }
}

/**
 * Logs payment routing to specific office.
 * @param PDO $pdo Database connection
 * @param int $payment_id Payment ID
 * @param int $zyra_id Office ID
 * @param array $office_details Office payment details
 * @return void
 */
function logPaymentRouting(PDO $pdo, int $payment_id, int $zyra_id, array $office_details): void {
    try {
        // Generate transaction ID for routing log
        $transaction_id = 'ROUTE_' . date('YmdHis') . '_' . strtoupper(substr(md5(uniqid()), 0, 8));
        
        $stmt = $pdo->prepare("
            INSERT INTO payment_logs
            (office_email, office_name, transaction_id, amount, payment_method, payment_status, payment_data, created_at)
            VALUES (?, ?, ?, ?, 'bank_transfer', 'pending', ?, NOW())
        ");
        $payment_data = json_encode([
            'payment_id' => $payment_id,
            'zyra_id' => $zyra_id,
            'office_name' => $office_details['emri'],
            'bank' => $office_details['banka'],
            'iban' => $office_details['iban'],
            'llogaria' => $office_details['llogaria']
        ]);
        $stmt->execute([
            $office_details['email'],
            $office_details['emri'],
            $transaction_id,
            0.00, // Amount will be updated when payment completes
            $payment_data
        ]);
    } catch (Throwable $e) {
        error_log('Failed to log payment routing: ' . $e->getMessage());
    }
}

/**
 * Updates reservation payment details and status.
 * @param PDO $pdo Database connection
 * @param int $reservation_id Reservation ID
 * @param int $user_id User ID
 * @param string $status New payment status (pending|paid|failed)
 * @param float|null $amount Optional amount to store
 * @param string|null $payment_method Optional payment method description
 */
function setReservationPaymentStatus(PDO $pdo, int $reservation_id, int $user_id, string $status, ?float $amount = null, ?string $payment_method = null): void {
    $allowedStatuses = ['pending', 'paid', 'failed'];
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'pending';
    }

    $fields = ['payment_status = ?'];
    $params = [$status];

    if ($amount !== null) {
        $fields[] = 'amount = ?';
        $params[] = $amount;
    }

    if ($payment_method !== null) {
        $fields[] = 'payment_method = ?';
        $params[] = $payment_method;
    }

    $fields[] = 'updated_at = NOW()';
    $sql = 'UPDATE reservations SET ' . implode(', ', $fields) . ' WHERE id = ? AND user_id = ?';
    $params[] = $reservation_id;
    $params[] = $user_id;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } catch (Throwable $e) {
        error_log('Failed to update reservation payment status: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Validates payment input data.
 * @param array $data Input data
 * @return array Array of error messages
 */
function validatePaymentData(array $data): array {
    $errors = [];

    if (empty($data['reservation_id']) || (int)$data['reservation_id'] <= 0) {
        $errors[] = 'Rezervimi nuk u gjet. Ju lutemi krijoni ose zgjidhni një rezervim përpara pagesës';
    }

    if (empty($data['bank_name'])) {
        $errors[] = 'Emri i bankës është i detyrueshëm';
    }

    if (empty($data['payer_name']) || strlen($data['payer_name']) < 3) {
        $errors[] = 'Emri dhe mbiemri duhet të jetë së paku 3 karaktere';
    }

    if (empty($data['payer_iban']) || !preg_match('/^XK\d{2}\d{16}$/', $data['payer_iban'])) {
        $errors[] = 'IBAN i pavlefshëm. Formati duhet të jetë: XK + 2 shifra kontrolli + 16 shifra llogarie (gjithsej 20 karaktere, vetëm numra pas XK)';
    }

    if ($data['amount'] < 10 || $data['amount'] > 10000) {
        $errors[] = 'Shuma duhet të jetë ndërmjet 10€ dhe 10,000€';
    }

    if (empty($data['description']) || strlen($data['description']) < 5) {
        $errors[] = 'Përshkrimi duhet të jetë së paku 5 karaktere';
    }

    return $errors;
}

/**
 * Processes the payment by inserting into database and routing to office bank account.
 * @param PDO $pdo Database connection
 * @param array $data Payment data
 * @return array Result with success status and transaction ID
 */
function processPayment(PDO $pdo, array $data): array {
    $transaction_id = 'PAY_' . date('YmdHis') . '_' . strtoupper(substr(md5(uniqid()), 0, 8));

    try {
        $pdo->beginTransaction();

        // Fetch office bank details for payment routing
        $office_details = getOfficePaymentDetails($pdo, (int)($data['zyra_id'] ?? 0));
        if (!$office_details) {
            throw new Exception('Nuk mund të ngarkohen detalet e bankës për zyrën. Ju lutemi kontaktoni administratorin.');
        }

        // Generate UUID për secilin payment
        function generate_payment_uuid() {
            return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
        }
        $payment_uuid = generate_payment_uuid();
        
        $stmt = $pdo->prepare("
            INSERT INTO payments
            (user_id, amount, payment_method, status, transaction_id, reservation_id, uuid, created_at, updated_at)
            VALUES (?, ?, ?, 'pending', ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $data['user_id'],
            $data['amount'],
            $data['bank_name'],
            $transaction_id,
            $data['reservation_id'] ?? null,
            $payment_uuid
        ]);

        $payment_id = (int)$pdo->lastInsertId();

        // Log payment routing to office
        logPaymentRouting($pdo, $payment_id, (int)($data['zyra_id'] ?? 0), $office_details);

        setReservationPaymentStatus($pdo, (int)$data['reservation_id'], (int)$data['user_id'], 'pending', (float)$data['amount'], $data['bank_name']);

        // Simulate payment processing (replace with real bank API)
        // In production, this would call the actual bank API with the office's IBAN
        $payment_success = true;
        $bank_routing_info = [
            'destination_iban' => $office_details['iban'],
            'destination_bank' => $office_details['banka'],
            'destination_account' => $office_details['llogaria'],
            'destination_office' => $office_details['emri'],
            'destination_email' => $office_details['email']
        ];

        if ($payment_success) {
            $stmt = $pdo->prepare("UPDATE payments SET status = 'paid', updated_at = NOW() WHERE transaction_id = ?");
            $stmt->execute([$transaction_id]);

            setReservationPaymentStatus($pdo, (int)$data['reservation_id'], (int)$data['user_id'], 'paid', (float)$data['amount'], $data['bank_name']);

            // Update payment routing log with completion details
            try {
                $stmt = $pdo->prepare("
                    UPDATE payment_logs
                    SET payment_status = 'completed',
                        amount = ?,
                        payment_data = ?
                    WHERE office_email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
                    LIMIT 1
                ");
                $updated_payment_data = json_encode(array_merge(
                    $bank_routing_info,
                    [
                        'transaction_id' => $transaction_id,
                        'amount' => $data['amount'],
                        'status' => 'completed',
                        'routed_at' => date('Y-m-d H:i:s')
                    ]
                ));
                $stmt->execute([$data['amount'], $updated_payment_data, $office_details['email']]);
            } catch (Throwable $e) {
                // Ignore logging errors to prevent transaction rollback
                error_log('Failed to update payment routing log: ' . $e->getMessage());
            }

            $pdo->commit();

            return [
                'success' => true,
                'transaction_id' => $transaction_id,
                'payment_id' => $payment_id,
                'reservation_id' => (int)$data['reservation_id'],
                'office_name' => $office_details['emri'],
                'office_iban' => $office_details['iban']
            ];
        }

        $stmt = $pdo->prepare("UPDATE payments SET status = 'failed', updated_at = NOW() WHERE transaction_id = ?");
        $stmt->execute([$transaction_id]);

        setReservationPaymentStatus($pdo, (int)$data['reservation_id'], (int)$data['user_id'], 'failed', (float)$data['amount'], $data['bank_name']);

        // Update payment routing log with failure details
        try {
            $stmt = $pdo->prepare("
                UPDATE payment_logs
                SET payment_status = 'failed',
                    amount = ?
                WHERE office_email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
                LIMIT 1
            ");
            $stmt->execute([$data['amount'], $office_details['email'] ?? null]);
        } catch (Throwable $e) {
            error_log('Failed to update payment routing log on failure: ' . $e->getMessage());
        }

        $pdo->commit();

        return [
            'success' => false,
            'transaction_id' => $transaction_id,
            'reservation_id' => (int)$data['reservation_id']
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log("Payment processing error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Konfigurimi i raportimit të gabimeve
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

session_start();
require_once 'config.php';
require_once 'confidb.php';

// Ensure errors are not displayed to break JSON
ini_set('display_errors', 0);

// Clean buffer before checking AJAX
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    ob_clean(); // Clear any previous output (whitespace, warnings)
}

// Kontrollo nëse PDO është i disponueshëm
if (!isset($pdo)) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Gabim në lidhjen me bazën e të dhënave']);
        exit();
    }
    die('Database connection failed');
}



// Ensure clean buffer for all AJAX responses
if ($is_ajax) {
    ob_clean();
    ini_set('display_errors', 0);
}

// Kontrollo nëse përdoruesi është i kyçur
if (!isset($_SESSION['user_id'])) {
    if ($is_ajax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Ju lutemi kyçuni për të vazhduar']);
        exit();
    }
    header("Location: login.php");
    exit();
}

// Ngatëro user_id pas verifikimit
$user_id = (int)$_SESSION['user_id'];

// Wrap the whole payment flow so uncaught DB errors return JSON (not blank 500)
try {

// Kontrollo nëse është dërguar forma me POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST)) {
    if ($is_ajax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Kërkesa e pavlefshme']);
        exit();
    }
    echo "<h1>Konfirmimi i Pagesës</h1>";
    echo "<p style='color:#d32f2f;'>Nuk ka informacion mbi pagesën. Ju lutemi përdorni butonin 'Paguaj Online' nga rezervimi.</p>";
    exit();
}

// Validate CSRF
if (!validateCsrf()) {
    if ($is_ajax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Veprimi i paautorizuar.']);
        exit();
    }
    echo "<h1>Veprimi i Paautorizuar</h1>";
    echo "<p>Token CSRF i pavlefshëm.</p>";
    exit();
}

// Rate limiting për pagesa (maksimumi 5 pagesa në orë për përdorues)
$max_attempts = 5;
$attempts = checkRateLimit($pdo, $user_id);

if ($attempts >= $max_attempts) {
    if ($is_ajax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Keni arritur limitin maksimal të përpjekjeve për pagesa. Ju lutemi prisni 1 orë dhe provoni përsëri.']);
        exit();
    }
    echo "<div style='color:red; padding:20px; border:1px solid red; margin:20px;'>";
    echo "<h3>Limit i arritur</h3>";
    echo "<p>Keni arritur limitin maksimal të përpjekjeve për pagesa. Ju lutemi prisni 1 orë dhe provoni përsëri.</p>";
    echo "<a href='javascript:history.back()'>Kthehu prapa</a>";
    echo "</div>";
    exit();
}

// Pastrimi dhe validimi i të dhënave
require_once 'SecurityValidator.php';

$bank_name = sanitizeString($_POST['bank'] ?? $_POST['emri_bankes'] ?? '');
$payer_name = sanitizeString($_POST['payer_name'] ?? '');
$payer_iban = strtoupper(sanitizeString($_POST['payer_iban'] ?? $_POST['llogaria'] ?? ''));
$amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_FLOAT, ['options' => ['default' => 0.0, 'min_range' => 0]]);
$description = sanitizeString($_POST['description'] ?? $_POST['pershkrimi'] ?? '');
$reservation_id = filter_var($_POST['reservation_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;

// Validate IBAN using SecurityValidator
if (!SecurityValidator::validateIBAN($payer_iban)) {
    $message = 'IBAN i pavlefshëm. Formati duhet të jetë: XK + 2 shifra kontrolli + 16 shifra llogarie (gjithsej 20 karaktere).';
    if ($is_ajax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit();
    }
    echo "<div style='color:red; padding:20px; border:1px solid red; margin:20px;'>";
    echo "<h3>Gabim në të dhëna</h3>";
    echo "<p>$message</p>";
    echo "<a href='javascript:history.back()'>Kthehu prapa</a>";
    echo "</div>";
    exit();
}

$reservation = findReservationForUser($pdo, $user_id, $reservation_id > 0 ? $reservation_id : null);

if (!$reservation) {
    $message = 'Rezervimi nuk u gjet. Ju lutemi krijoni një rezervim përpara se të kryeni pagesë';
    if ($is_ajax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit();
    }
    echo "<div style='color:red; padding:20px; border:1px solid red; margin:20px;'>";
    echo "<h3>Rezervimi mungon</h3>";
    echo "<p>$message.</p>";
    echo "<a href='reservation.php'>Kthehu te rezervimet</a>";
    echo "</div>";
    exit();
}

$reservation_id = (int)$reservation['id'];
$zyra_id = (int)($reservation['zyra_id'] ?? 0);

if (isset($reservation['status']) && $reservation['status'] === 'cancelled') {
    $message = 'Rezervimi është anuluar dhe nuk mund të përpunohet pagesa';
    if ($is_ajax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit();
    }
    echo "<div style='color:red; padding:20px; border:1px solid red; margin:20px;'>";
    echo "<h3>Rezervim i anuluar</h3>";
    echo "<p>$message.</p>";
    echo "<a href='reservation.php'>Kthehu te rezervimet</a>";
    echo "</div>";
    exit();
}

if (($reservation['payment_status'] ?? 'pending') === 'paid') {
    $message = 'Rezervimi është paguar tashmë';
    if ($is_ajax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit();
    }
    echo "<div style='color:orange; padding:20px; border:1px solid orange; margin:20px;'>";
    echo "<h3>Pagesë e përfunduar</h3>";
    echo "<p>$message.</p>";
    echo "<a href='dashboard.php'>Kthehu në panel</a>";
    echo "</div>";
    exit();
}

// Kontrollo për pagesa të dyfishuara (brenda 5 minutave të fundit)
$recent_payments = checkDuplicatePayment($pdo, $user_id, $amount, $bank_name);

if ($recent_payments > 0) {
    if ($is_ajax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Një pagesë e ngjashme është kryer së fundmi. Ju lutemi prisni 5 minuta para se të provoni përsëri.']);
        exit();
    }
    echo "<div style='color:orange; padding:20px; border:1px solid orange; margin:20px;'>";
    echo "<h3>Pagesë e dyfishuar</h3>";
    echo "<p>Një pagesë e ngjashme është kryer së fundmi. Ju lutemi prisni 5 minuta para se të provoni përsëri.</p>";
    echo "<a href='javascript:history.back()'>Kthehu prapa</a>";
    echo "</div>";
    exit();
}

// Validime të detyrueshme
$errors = validatePaymentData([
    'reservation_id' => $reservation_id,
    'bank_name' => $bank_name,
    'payer_name' => $payer_name,
    'payer_iban' => $payer_iban,
    'amount' => $amount,
    'description' => $description
]);

if (!empty($errors)) {
    // Regjistro përpjekjen e dështuar
    logPaymentAttempt($pdo, $user_id, $bank_name, $amount, 'failed', implode('. ', $errors), $reservation_id);

    if ($is_ajax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
        exit();
    }
    // Për form normale, trego gabimet
    echo "<div style='color:red; padding:20px; border:1px solid red; margin:20px;'>";
    echo "<h3>Gabime në formë:</h3><ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul><a href='javascript:history.back()'>Kthehu prapa</a></div>";
    exit();
}

// Llogarit TVSH dhe provizionin
$tvsh_rate = 0.12;
$prov_rate = 0.02;
$tvsh = round($amount * $tvsh_rate, 2);
$provizioni = round($amount * $prov_rate, 2);
$pa_tvsh = round($amount - $tvsh, 2);
$currency = 'EUR';

// Initialize variables for success page
$emri = '';
$mbiemri = '';
$transaction_id = '';

// Regjistro pagesën në databazë
$result = processPayment($pdo, [
    'user_id' => $user_id,
    'reservation_id' => $reservation_id,
    'zyra_id' => $zyra_id,
    'amount' => $amount,
    'bank_name' => $bank_name,
    'payer_name' => $payer_name,
    'payer_iban' => $payer_iban,
    'description' => $description
]);

$transaction_id = $result['transaction_id'] ?? '';

if ($result['success']) {
    // Merr të dhënat e përdoruesit për faqen e suksesit
    $user_stmt = $pdo->prepare('SELECT emri, mbiemri FROM users WHERE id = ?');
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $emri = $user['emri'] ?? '';
        $mbiemri = $user['mbiemri'] ?? '';
    }



    // Gjenero PDF automatikisht pas pagesës (fatura)
    require_once __DIR__ . '/vendor/autoload.php'; // mPDF
    $pdf_path = '';
    try {
        $pdfDir = __DIR__ . '/pdfs/';
        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }
        $pdfFile = $pdfDir . 'fatura_' . $reservation_id . '_' . $transaction_id . '.pdf';
        $mpdf = new \Mpdf\Mpdf(['tempDir' => __DIR__ . '/tmp']);
        $mpdf->SetTitle('Fatura e Pagesës - Noteria');
        $mpdf->WriteHTML('<h2>Fatura e Pagesës</h2>' .
            '<table style="width:100%;border-collapse:collapse;">'
            . '<tr><td><b>Emri:</b></td><td>' . htmlspecialchars($emri) . '</td></tr>'
            . '<tr><td><b>Mbiemri:</b></td><td>' . htmlspecialchars($mbiemri) . '</td></tr>'
            . '<tr><td><b>Banka:</b></td><td>' . htmlspecialchars($bank_name) . '</td></tr>'
            . '<tr><td><b>ID Transaksionit:</b></td><td>' . htmlspecialchars($transaction_id) . '</td></tr>'
            . '<tr><td><b>Shuma:</b></td><td>' . htmlspecialchars(number_format($amount,2)) . ' EUR</td></tr>'
            . '<tr><td><b>Shuma pa TVSH:</b></td><td>' . htmlspecialchars(number_format($pa_tvsh,2)) . ' EUR</td></tr>'
            . '<tr><td><b>TVSH (12%):</b></td><td>' . htmlspecialchars(number_format($tvsh,2)) . ' EUR</td></tr>'
            . '<tr><td><b>Provizion:</b></td><td>' . htmlspecialchars(number_format($provizioni,2)) . ' EUR</td></tr>'
            . '<tr><td><b>IBAN:</b></td><td>' . htmlspecialchars($payer_iban) . '</td></tr>'
            . '<tr><td><b>Përshkrimi:</b></td><td>' . htmlspecialchars($description) . '</td></tr>'
            . '<tr><td><b>Data:</b></td><td>' . date('d.m.Y H:i') . '</td></tr>'
            . '</table>'
        );
        $mpdf->Output($pdfFile, 'F');
        $pdf_path = '/pdfs/' . basename($pdfFile);
        // Update reservation with invoice path
        $stmtUpd = $pdo->prepare("UPDATE reservations SET invoice_path = ? WHERE id = ?");
        $stmtUpd->execute([$pdf_path, $reservation_id]);
    } catch (Throwable $e) {
        error_log('PDF generation failed: ' . $e->getMessage());
    }

    // Regjistro në log
    error_log("Payment successful: $transaction_id - Amount: $amount€ - Bank: $bank_name");
    logPaymentAttempt($pdo, $user_id, $bank_name, $amount, 'completed', '', $reservation_id);

    // Regenerate CSRF token after successful payment
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // Dërgo njoftim real-time
    require_once 'notifications_helper.php';
    createNotification($pdo, $user_id, 'Pagesa u krye', "Pagesa prej $amount EUR u krye me sukses.", 'success');

    if ($is_ajax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Pagesa u krye me sukses!',
            'transaction_id' => $transaction_id,
            'amount' => $amount,
            'bank' => $bank_name,
            'office_name' => $result['office_name'] ?? null,
            'office_iban' => $result['office_iban'] ?? null,
            'reservation_id' => $reservation_id,
            'payment_id' => $result['payment_id'] ?? null,
            'routing_info' => 'Pagesa është dërguar drejt llogarisë bankare të zyrës: ' . ($result['office_name'] ?? 'Zyrë noteriale'),
            'pdf_path' => $pdf_path
        ]);
        exit();
    }
    // Nëse nuk është AJAX, trego faqen e suksesit (vazhdo në HTML poshtë)
} else {
    // Regjistro dështimin
        $error_msg = $result['error'] ?? 'Payment processing failed';
        error_log("Payment failed for user $user_id: $error_msg");
        logPaymentAttempt($pdo, $user_id, $bank_name, $amount, 'failed', $error_msg, $reservation_id);

        if ($is_ajax) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Pagesa dështoi: ' . $error_msg,
                'reservation_id' => $reservation_id,
                'debug_info' => [
                    'error' => $error_msg,
                    'amount' => $amount,
                    'bank' => $bank_name
                ]
            ]);
            exit();
        }
        
        // Nëse nuk është AJAX dhe pagesa dështoi, trego error
        echo "<div style='color:red; padding:20px; border:1px solid red; margin:20px;'>";
        echo "<h3>Pagesa Dështoi</h3>";
        echo "<p>Pagesa juaj dështoi në përpunim. Ju lutemi provoni përsëri më vonë.</p>";
        echo "<p style='color:#666; font-size:0.9em;'>Detajet: " . htmlspecialchars($error_msg) . "</p>";
    exit();
}

} catch (Throwable $e) {
    error_log('Unhandled payment flow error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Initialize variables to avoid undefined errors
    $user_id = (int)($user_id ?? 0);
    $bank_name = $bank_name ?? 'unknown';
    $amount = $amount ?? 0.0;
    $reservation_id = isset($reservation_id) ? (int)$reservation_id : null;
    
    // Regjistro gabimin
    logPaymentAttempt($pdo, $user_id, $bank_name, $amount, 'failed', $e->getMessage(), $reservation_id);

    if ($is_ajax) {
        if (ob_get_length()) {
            @ob_clean();
        }
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Ndodhi një gabim gjatë përpunimit të pagesës. Ju lutemi provoni përsëri.']);
        exit();
    }

    echo "<div style='color:red; padding:20px; border:1px solid red; margin:20px;'>";
    echo "<h3>Gabim në përpunimin e pagesës</h3>";
    echo "<p>Ndodhi një gabim teknik. Ju lutemi provoni përsëri më vonë.</p>";
    echo "<a href='javascript:history.back()'>Kthehu prapa</a>";
    echo "</div>";
    exit();
}

// Nëse nuk është AJAX, trego faqen e suksesit
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagesa e Kryer me Sukses | Noteria</title>
</head>
<body>
    <h1>Pagesa u Krye me Sukses!</h1>
    <p>Faleminderit për besimin tuaj.</p>
    <p>Pagesa juaj është procesuar me sukses përmes sistemit të sigurt të bankës <?php echo htmlspecialchars($bank_name); ?>.</p>
    <p>Një konfirmim do të dërgohet edhe në numrin tuaj të telefonit.</p>
    
    <h2>Detajet e Pagesës:</h2>
    <ul>
        <li><b>Emri dhe mbiemri:</b> <?php echo htmlspecialchars($emri . ' ' . $mbiemri); ?></li>
        <li><b>Banka:</b> <?php echo htmlspecialchars($bank_name); ?></li>
        <li><b>ID e Transaksionit:</b> <?php echo htmlspecialchars($transaction_id); ?></li>
        <li><b>Shuma totale:</b> <?php echo htmlspecialchars(number_format($amount, 2)); ?> EUR</li>
        <li><b>Shuma pa TVSH:</b> <?php echo htmlspecialchars(number_format($pa_tvsh, 2)); ?> EUR</li>
        <li><b>TVSH (12%):</b> <?php echo htmlspecialchars(number_format($tvsh, 2)); ?> EUR</li>
        <li><b>Provizion platforme (2%):</b> <?php echo htmlspecialchars(number_format($provizioni, 2)); ?> EUR</li>
        <li><b>IBAN:</b> <?php echo htmlspecialchars($payer_iban); ?></li>
        <li><b>Përshkrimi:</b> <?php echo htmlspecialchars($description); ?></li>
        <li><b>Data:</b> <?php echo date('d.m.Y H:i'); ?></li>
        <?php if (!empty($pdf_path)): ?>
        <li><b>Dokumenti i rezervimit:</b> <a href="<?php echo htmlspecialchars($pdf_path); ?>" target="_blank">Shkarko PDF</a></li>
        <?php endif; ?>
    </ul>
    
    <?php if (!empty($result['payment_id'])): ?>
        <script>
            // Redirect to invoice after 3 seconds
            setTimeout(function() {
                window.location.href = 'invoice.php?payment_id=<?php echo (int)$result['payment_id']; ?>&reservation_id=<?php echo (int)$reservation_id; ?>';
            }, 3000);
        </script>
        <div style="margin-top:20px; color:#0033A0; font-weight:bold;">Ju do të ridrejtoheni automatikisht te fatura juaj...</div>
        <a href="invoice.php?payment_id=<?php echo (int)$result['payment_id']; ?>&reservation_id=<?php echo (int)$reservation_id; ?>" class="btn btn-primary" style="margin-top:10px;">Shiko Faturën Tani</a>
    <?php endif; ?>
    <a href="dashboard.php">Kthehu në Panelin e Përdoruesit</a>
</body>
</html>