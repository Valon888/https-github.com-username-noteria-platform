<?php
/**
 * Payment Confirmation Handler
 * 
 * This file handles payment confirmation callbacks from payment gateways
 * and updates the payment status in the database.
 */

require_once 'config.php';
require_once 'db_connection.php';

// Define SecurityHeaders class if not already defined
if (!class_exists('SecurityHeaders')) {
    class SecurityHeaders {
        public function setSecurityHeaders() {
            header('X-Frame-Options: DENY');
            header('X-Content-Type-Options: nosniff');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: no-referrer-when-downgrade');
            header('Content-Security-Policy: default-src \'self\'');
        }
    }
}

// Set security headers
$security = new SecurityHeaders();
$security->setSecurityHeaders();

// Initialize response
$response = [
    'status' => 'error',
    'message' => 'Invalid request'
];

// Log the callback data
function logPaymentCallback($paymentId, $logType, $data, $conn) {
    $logData = json_encode($data);
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare("INSERT INTO payment_logs (payment_id, log_type, log_data, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssss", $paymentId, $logType, $logData, $ipAddress);
    $stmt->execute();
}

// Verify the payment gateway IP address (example for Paysera)
function verifyPaymentGatewayIP($gateway) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // List of allowed IPs for different payment gateways
    $allowedIPs = [
        'paysera' => [
            '91.240.102.0/24',
            '88.119.0.0/16',
            '127.0.0.1', // For testing locally
        ],
        'raiffeisen' => [
            // Add Raiffeisen Bank IPs
            '127.0.0.1', // For testing locally
        ],
        'bkt' => [
            // Add BKT Bank IPs
            '127.0.0.1', // For testing locally
        ]
    ];
    
    if (!isset($allowedIPs[$gateway])) {
        return false;
    }
    
    foreach ($allowedIPs[$gateway] as $allowedIP) {
        if (strpos($allowedIP, '/') !== false) {
            // This is a CIDR notation, check if IP is in the range
            if (cidr_match($ip, $allowedIP)) {
                return true;
            }
        } else {
            // Direct IP comparison
            if ($ip === $allowedIP) {
                return true;
            }
        }
    }
    
    return false;
}

// Helper function to check if an IP is within a CIDR range
function cidr_match($ip, $cidr) {
    list($subnet, $mask) = explode('/', $cidr);
    
    $ipLong = ip2long($ip);
    $subnetLong = ip2long($subnet);
    $maskLong = ~((1 << (32 - $mask)) - 1);
    
    return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
}

// Verify signature for different payment gateways
function verifySignature($gateway, $data, $signature) {
    switch ($gateway) {
        case 'paysera':
            return verifyPayseraSignature($data, $signature);
        case 'raiffeisen':
            return verifyRaiffeisenSignature($data, $signature);
        case 'bkt':
            return verifyBktSignature($data, $signature);
        default:
            return false;
    }
}

function verifyPayseraSignature($data, $signature) {
    // Get Paysera project secret from configuration
    global $config;
    $projectPassword = $config['paysera']['password'];
    
    // Sort data alphabetically by key
    ksort($data);
    
    // Build the string to hash
    $dataString = '';
    foreach ($data as $key => $value) {
        if ($key !== 'signature') {
            $dataString .= $value;
        }
    }
    
    // Generate expected signature using MD5
    $expectedSignature = md5($dataString . $projectPassword);
    
    // Compare signatures
    return hash_equals($expectedSignature, $signature);
}

function verifyRaiffeisenSignature($data, $signature) {
    // Implement Raiffeisen Bank signature verification
    // This is a placeholder - actual implementation depends on Raiffeisen Bank API docs
    global $config;
    $secretKey = $config['raiffeisen']['secretKey'];
    
    // Example implementation
    $dataString = implode('', $data);
    $expectedSignature = hash_hmac('sha256', $dataString, $secretKey);
    
    return hash_equals($expectedSignature, $signature);
}

function verifyBktSignature($data, $signature) {
    // Implement BKT Bank signature verification
    // This is a placeholder - actual implementation depends on BKT API docs
    global $config;
    $secretKey = $config['bkt']['secretKey'];
    
    // Example implementation
    $dataString = implode('', $data);
    $expectedSignature = hash_hmac('sha256', $dataString, $secretKey);
    
    return hash_equals($expectedSignature, $signature);
}

// Update payment status in database
function updatePaymentStatus($paymentId, $status, $gatewayReference = null, $conn) {
    $completionDate = ($status == 'completed') ? 'NOW()' : 'NULL';
    
    $stmt = $conn->prepare("
        UPDATE payments 
        SET status = ?, 
            completion_date = " . $completionDate . ", 
            meta_data = JSON_SET(IFNULL(meta_data, '{}'), '$.gateway_reference', ?)
        WHERE payment_id = ?
    ");
    
    $stmt->bind_param("sss", $status, $gatewayReference, $paymentId);
    $result = $stmt->execute();
    
    // If payment is completed, update video consultation status
    if ($status == 'completed') {
        updateVideoConsultationStatus($paymentId, $conn);
    }
    
    return $result;
}

// This function was incomplete in the original code
function updateVideoConsultationStatus($paymentId, $conn) {
    $stmt = $conn->prepare("
        UPDATE video_consultations 
        SET status = 'pending'
        WHERE payment_id = ? AND status = 'awaiting_payment'
    ");
    $stmt->bind_param("s", $paymentId);
    return $stmt->execute();
}

// Helper function to get database connection
function getDbConnection() {
    global $config;
    $conn = new mysqli(
        $config['db']['host'],
        $config['db']['user'],
        $config['db']['password'],
        $config['db']['database']
    );
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    return $conn;
}

// Main processing logic
try {
    // Connect to database
    $conn = getDbConnection();
    
    // Determine which payment gateway is calling back
    $gateway = '';
    
    // Check for Paysera callback
    if (isset($_POST['ss1']) && isset($_POST['ss2'])) {
        $gateway = 'paysera';
        $paymentData = $_POST;
        $paymentId = $paymentData['orderid'] ?? '';
        $signature = $paymentData['ss2'] ?? '';
        $status = ($paymentData['status'] == '1') ? 'completed' : 'failed';
        
    // Check for Raiffeisen callback
    } elseif (isset($_POST['REFNO'])) {
        $gateway = 'raiffeisen';
        $paymentData = $_POST;
        $paymentId = $paymentData['REFNO'] ?? '';
        $signature = $paymentData['SIGNATURE'] ?? '';
        $status = ($paymentData['RESULT'] == '0') ? 'completed' : 'failed';
        
    // Check for BKT callback
    } elseif (isset($_POST['RespCode'])) {
        $gateway = 'bkt';
        $paymentData = $_POST;
        $paymentId = $paymentData['OrderId'] ?? '';
        $signature = $paymentData['Signature'] ?? '';
        $status = ($paymentData['RespCode'] == '00') ? 'completed' : 'failed';
        
    } else {
        // Unknown payment gateway
        throw new Exception('Unknown payment gateway or invalid request format');
    }
    
    // Log the callback
    logPaymentCallback($paymentId, $gateway . '_callback', $paymentData, $conn);
    
    // Verify IP address
    if (!verifyPaymentGatewayIP($gateway)) {
        logPaymentCallback($paymentId, 'ip_verification_failed', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'gateway' => $gateway
        ], $conn);
        throw new Exception('Invalid IP address for ' . $gateway);
    }
    
    // Verify signature
    if (!verifySignature($gateway, $paymentData, $signature)) {
        logPaymentCallback($paymentId, 'signature_verification_failed', [
            'received_signature' => $signature,
            'gateway' => $gateway
        ], $conn);
        throw new Exception('Invalid signature for ' . $gateway);
    }
    
    // Update payment status
    $gatewayReference = '';
    switch ($gateway) {
        case 'paysera':
            $gatewayReference = $paymentData['requestid'] ?? '';
            break;
        case 'raiffeisen':
            $gatewayReference = $paymentData['TRANID'] ?? '';
            break;
        case 'bkt':
            $gatewayReference = $paymentData['TransactionId'] ?? '';
            break;
    }
    
    if (updatePaymentStatus($paymentId, $status, $gatewayReference, $conn)) {
        $response = [
            'status' => 'success',
            'message' => 'Payment status updated successfully',
            'payment_id' => $paymentId,
            'payment_status' => $status
        ];
        
        logPaymentCallback($paymentId, 'payment_updated', [
            'status' => $status,
            'gateway_reference' => $gatewayReference
        ], $conn);
    } else {
        throw new Exception('Failed to update payment status');
    }
    
    // Return success response expected by payment gateway
    switch ($gateway) {
        case 'paysera':
            echo 'OK';
            exit;
        case 'raiffeisen':
            echo json_encode(['result' => 'SUCCESS']);
            exit;
        case 'bkt':
            echo 'ACCEPTED';
            exit;
        default:
            echo json_encode($response);
            exit;
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    
    // Log the error
    if (isset($conn) && isset($paymentId)) {
        logPaymentCallback($paymentId ?? 'unknown', 'error', [
            'error_message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], $conn);
    }
    
    // Return appropriate error response based on gateway
    if (isset($gateway)) {
        switch ($gateway) {
            case 'paysera':
                echo 'Error: ' . $e->getMessage();
                exit;
            case 'raiffeisen':
                echo json_encode(['result' => 'ERROR', 'message' => $e->getMessage()]);
                exit;
            case 'bkt':
                echo 'ERROR';
                exit;
            default:
                echo json_encode($response);
                exit;
        }
    } else {
        echo json_encode($response);
    }
}
?>