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
                
                // First, get the user_id and service_type for this payment
                $query = "SELECT user_id, service_type FROM payments WHERE payment_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("s", $paymentId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $paymentData = $result->fetch_assoc();
                    $userId = $paymentData['user_id'];
                    $serviceType = $paymentData['service_type'];
                    
                    // Now update the payment status
                    $updateQuery = "UPDATE payments SET status = ?, completion_date = NOW() ";
                    
                    // If status is completed and service is video_consultation, set expiry time
                    if ($status === 'completed' && $serviceType === 'video_consultation') {
                        $updateQuery .= ", expiry_date = DATE_ADD(NOW(), INTERVAL 30 MINUTE) ";
                    }
                    
                    $updateQuery .= "WHERE payment_id = ?";
                    $stmt = $conn->prepare($updateQuery);
                    $stmt->bind_param("ss", $status, $paymentId);
                    $stmt->execute();
                    
                    // Log the successful update
                    error_log("Payment status updated for $paymentId: $status");
                }
                
                $conn->close();
                
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