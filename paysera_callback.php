<?php
/**
 * Paysera Callback Handler
 * 
 * This file handles callbacks from payment gateways (Paysera, Raiffeisen, BKT)
 * to update payment status in the database
 */

// Don't start a session in callback handler to ensure proper processing
require_once 'db_connection.php';

// Include payment processing functions
require_once 'paysera_pay.php';

// Log the callback for debugging purposes
$callbackData = file_get_contents('php://input');
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestHeaders = getallheaders();

$logEntry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $requestMethod,
    'data' => $callbackData,
    'headers' => $requestHeaders,
    'get_params' => $_GET,
    'post_params' => $_POST
];

// Log to file for debugging
error_log("Payment callback received: " . json_encode($logEntry, JSON_PRETTY_PRINT), 3, "payment_callbacks.log");

// Process Paysera callback
if (isset($_GET['data']) && isset($_GET['ss1']) && isset($_GET['ss2'])) {
    $data = $_GET['data'];
    $signature = $_GET['ss1'];
    
    // Verify the signature
    if (verifyPayseraSignature($data, $signature)) {
        // Decode the data
        $decodedData = json_decode(base64_decode($data), true);
        
        if (isset($decodedData['orderid']) && isset($decodedData['status'])) {
            $paymentId = $decodedData['orderid'];
            $status = ($decodedData['status'] == '1') ? 'completed' : 'failed';
            
            // Update payment status in database
            try {
                $conn = connectToDatabase();
                
                // Update payment status
                $query = "UPDATE payments SET status = ?, updated_at = NOW() WHERE transaction_id = ? OR id = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$status, $paymentId, $paymentId]);
                
                // Përditëso reservation payment status nëse ka
                if ($status === 'paid') {
                    $check_query = "SELECT user_id, reservation_id FROM payments WHERE transaction_id = ? OR id = ? LIMIT 1";
                    $check_stmt = $conn->prepare($check_query);
                    $check_stmt->execute([$paymentId, $paymentId]);
                    $result = $check_stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $payment_row = $result->fetch_assoc();
                        if ($payment_row['reservation_id']) {
                            $update_res = $conn->prepare("UPDATE reservations SET payment_status = 'paid', updated_at = NOW() WHERE id = ? AND user_id = ?");
                            $update_res->execute([$payment_row['reservation_id'], $payment_row['user_id']]);
                        }
                    }
                }
                
                // Log the successful update
                error_log("Payment status updated for $paymentId: $status");
                
                // Return success response to Paysera
                header('Content-Type: text/plain');
                echo 'OK';
                exit;
            } catch (Exception $e) {
                error_log("Error updating payment status: " . $e->getMessage());
                header('HTTP/1.1 500 Internal Server Error');
                exit;
            }
        }
    } else {
        // Invalid signature
        error_log("Invalid signature in Paysera callback");
        header('HTTP/1.1 403 Forbidden');
        exit;
    }
} 
// Process Raiffeisen callback (placeholder for real implementation)
else if (isset($_GET['raiffeisen'])) {
    // Implement Raiffeisen callback logic here
    $paymentId = $_GET['orderid'] ?? '';
    $status = $_GET['status'] ?? '';
    
    if ($paymentId && $status === 'success') {
        // Update payment status
        updatePaymentStatus($paymentId, 'completed');
        header('Content-Type: text/plain');
        echo 'OK';
        exit;
    }
}
// Process BKT callback (placeholder for real implementation)
else if (isset($_GET['bkt'])) {
    // Implement BKT callback logic here
    $paymentId = $_GET['reference'] ?? '';
    $status = $_GET['result'] ?? '';
    
    if ($paymentId && $status === 'success') {
        // Update payment status
        updatePaymentStatus($paymentId, 'completed');
        header('Content-Type: text/plain');
        echo 'OK';
        exit;
    }
}

// If we reach here, the callback wasn't recognized or processed correctly
header('HTTP/1.1 400 Bad Request');
echo 'Invalid callback data';
?>