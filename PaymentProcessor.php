/**
     * Call MCP server for payment or verification
     * @param string $endpoint MCP API endpoint (e.g. '/api/payment')
     * @param array $payload Data to send (will be encoded as JSON)
     * @param string $method HTTP method (default 'POST')
     * @return array Response from MCP server (decoded JSON or error)
     */
    public function callMcpServer($endpoint, $payload = [], $method = 'POST') {
        $baseUrl = $_ENV['MCP_SERVER_URL'] ?? 'http://localhost:8000';
        $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
    }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        if (!empty($payload)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $response = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            return ['success' => false, 'error' => $err];
        }
        if ($code < 200 || $code >= 300) {
            return ['success' => false, 'error' => 'MCP server error', 'status' => $code, 'body' => $response];
        }
        $data = json_decode($response, true);
        if ($data === null) {
            return ['success' => false, 'error' => 'Invalid JSON from MCP server', 'body' => $response];
        }
        return $data;
    }
<?php
/**
 * Advanced Payment Methods Integration
 * Support for: Stripe, Apple Pay, Google Pay, Visa, Mastercard
 */

class PaymentProcessor {
    private $conn;
    private $stripe_key;
    private $stripe_public_key;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
        // Payment gateway credentials
        $this->stripe_key = $_ENV['STRIPE_SECRET_KEY'] ?? 'sk_test_...';
        $this->stripe_public_key = $_ENV['STRIPE_PUBLIC_KEY'] ?? 'pk_test_...';
    }
    
    /**
     * Process payment with Stripe (supports all payment methods)
     */
    public function processStripePayment($amount, $currency, $payment_method, $user_id, $description = '') {
        // This would use Stripe SDK
        // For now, implementing the structure
        
        $transaction_id = 'txn_' . uniqid();
        $status = 'pending';
        
        try {
            // Validate amount
            if ($amount <= 0) {
                return ['success' => false, 'error' => 'Invalid amount'];
            }
            
            // Log payment attempt
            $this->logPaymentAttempt($transaction_id, $amount, $currency, $payment_method, $user_id);
            
            // Here you would call Stripe API
            // $stripe = new \Stripe\StripeClient($this->stripe_key);
            // $intent = $stripe->paymentIntents->create([...]);
            
            // For demo: simulate successful payment
            $status = 'completed';
            
            // Store in database
            $stmt = $this->conn->prepare("
                INSERT INTO payments 
                (user_id, amount, status, payment_method, transaction_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->bind_param("ddsss", $user_id, $amount, $status, $payment_method, $transaction_id);
            $stmt->execute();
            $stmt->close();
            
            return [
                'success' => true,
                'transaction_id' => $transaction_id,
                'amount' => $amount,
                'currency' => $currency
            ];
            
        } catch (Exception $e) {
            error_log("Payment Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Create Apple Pay payment intent
     */
    public function createApplePayIntent($amount, $currency, $user_id) {
        $intent_id = 'intent_apple_' . uniqid();
        
        $intent_data = [
            'amount' => $amount * 100, // Convert to cents
            'currency' => strtolower($currency),
            'payment_method_types' => ['apple_pay'],
            'success_url' => 'https://yourdomain.com/payment/success',
            'cancel_url' => 'https://yourdomain.com/payment/cancel'
        ];
        
        // Store intent for later verification
        $intent_json = json_encode($intent_data);
        $stmt = $this->conn->prepare("
            INSERT INTO payment_intents (intent_id, user_id, intent_data, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("sss", $intent_id, $user_id, $intent_json);
        $stmt->execute();
        $stmt->close();
        
        return [
            'success' => true,
            'intent_id' => $intent_id,
            'amount' => $amount,
            'currency' => $currency
        ];
    }
    
    /**
     * Create Google Pay payment intent
     */
    public function createGooglePayIntent($amount, $currency, $user_id) {
        $intent_id = 'intent_google_' . uniqid();
        
        $intent_data = [
            'amount' => $amount * 100,
            'currency' => strtoupper($currency),
            'payment_method_types' => ['google_pay'],
            'allowed_payment_methods' => ['CARD', 'TOKENIZED_CARD'],
            'merchant_id' => $_ENV['GOOGLE_MERCHANT_ID'] ?? 'YOUR_MERCHANT_ID'
        ];
        
        $intent_json = json_encode($intent_data);
        $stmt = $this->conn->prepare("
            INSERT INTO payment_intents (intent_id, user_id, intent_data, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("sss", $intent_id, $user_id, $intent_json);
        $stmt->execute();
        $stmt->close();
        
        return [
            'success' => true,
            'intent_id' => $intent_id,
            'amount' => $amount,
            'currency' => $currency
        ];
    }
    
    /**
     * Process card payment (Visa/Mastercard via Stripe)
     */
    public function processCardPayment($card_token, $amount, $currency, $user_id) {
        return $this->processStripePayment($amount, $currency, 'card', $user_id, 'Card payment');
    }
    
    /**
     * Process bank transfer
     */
    public function initiateBankTransfer($amount, $currency, $user_id, $iban) {
        $transfer_id = 'transfer_' . uniqid();
        
        $stmt = $this->conn->prepare("
            INSERT INTO bank_transfers 
            (user_id, transfer_id, amount, currency, iban, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->bind_param("ssdsss", $user_id, $transfer_id, $amount, $currency, $iban);
        $stmt->execute();
        $stmt->close();
        
        return [
            'success' => true,
            'transfer_id' => $transfer_id,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending'
        ];
    }
    
    /**
     * Refund payment
     */
    public function refundPayment($transaction_id, $amount = null) {
        // Get original payment
        $stmt = $this->conn->prepare("
            SELECT id, amount, status FROM payments 
            WHERE transaction_id = ?
        ");
        $stmt->bind_param("s", $transaction_id);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$payment) {
            return ['success' => false, 'error' => 'Payment not found'];
        }
        
        $refund_amount = $amount ?? $payment['amount'];
        
        // Check if payment can be refunded
        if ($payment['status'] !== 'completed') {
            return ['success' => false, 'error' => 'Only completed payments can be refunded'];
        }
        
        // Create refund
        $refund_id = 'refund_' . uniqid();
        $stmt = $this->conn->prepare("
            INSERT INTO refunds (payment_id, refund_id, amount, status, created_at)
            VALUES (?, ?, ?, 'completed', NOW())
        ");
        
        $stmt->bind_param("isd", $payment['id'], $refund_id, $refund_amount);
        $stmt->execute();
        $stmt->close();
        
        return [
            'success' => true,
            'refund_id' => $refund_id,
            'amount' => $refund_amount
        ];
    }
    
    /**
     * Get payment history
     */
    public function getPaymentHistory($user_id, $limit = 50) {
        $stmt = $this->conn->prepare("
            SELECT * FROM payments 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        
        $stmt->bind_param("si", $user_id, $limit);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Log payment attempt for audit trail
     */
    private function logPaymentAttempt($transaction_id, $amount, $currency, $method, $user_id) {
        $stmt = $this->conn->prepare("
            INSERT INTO payment_audit_log 
            (transaction_id, user_id, amount, currency, payment_method, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $stmt->bind_param("ssdsss", $transaction_id, $user_id, $amount, $currency, $method, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

?>
<?php
// Shembull thirrjeje nga PaymentProcessor
$result = $this->callMcpServer('/api/payment', [
    'amount' => 100,
    'currency' => 'EUR'
]);

if ($result['success']) {
    // Pagesa ose verifikimi u krye me sukses
    // $result përmban të dhënat nga MCP serveri
} else {
    // Ka ndodhur një gabim
    error_log('MCP Error: ' . $result['error']);
}
