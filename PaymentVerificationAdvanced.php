<?php
// Klasa e avancuar për verifikim të pagesave me siguri të shtuar
// filepath: d:\xampp\htdocs\noteria\PaymentVerificationAdvanced.php

class PaymentVerificationAdvanced {
    private $pdo;
    private $config;
    private $logger;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->config = include 'payment_config.php';
        $this->initializeLogger();
    }
    
    private function initializeLogger() {
        $log_dir = dirname($this->config['logging']['file']);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        $this->logger = new Logger($this->config['logging']['file']);
    }
    
    // Verifikimi i transaksionit me siguri të shtuar
    public function verifyTransaction($payment_data) {
        $transaction_id = $payment_data['transaction_id'];
        $amount = floatval($payment_data['amount']);
        $method = $payment_data['method'];
        $email = $payment_data['email'];
        
        try {
            // 1. Kontrolli i limiteve të sigurisë
            if (!$this->checkSecurityLimits($email, $amount)) {
                throw new Exception('Tejkalohen limitet e sigurisë');
            }
            
            // 2. Kontrolli i duplikatëve
            if ($this->checkDuplicateTransaction($email, $amount, $transaction_id)) {
                throw new Exception('Transaksion duplikat i zbuluar');
            }
            
            // 3. Verifikimi sipas metodës së pagesës
            $verification_result = false;
            switch ($method) {
                case 'bank_transfer':
                    $verification_result = $this->verifyBankTransfer($payment_data);
                    break;
                case 'paypal':
                    $verification_result = $this->verifyPayPalPayment($payment_data);
                    break;
                case 'card':
                    $verification_result = $this->verifyCreditCardPayment($payment_data);
                    break;
                default:
                    throw new Exception('Metodë pagese e panjohur');
            }
            
            // 4. Log-imi i rezultatit
            $this->logTransaction($transaction_id, $verification_result ? 'verified' : 'failed', $payment_data);
            
            return $verification_result;
            
        } catch (Exception $e) {
            $this->logger->error("Gabim në verifikim: " . $e->getMessage(), $payment_data);
            $this->logTransaction($transaction_id, 'error', $payment_data, $e->getMessage());
            return false;
        }
    }
    
    // Kontrolli i duplikatëve të transaksioneve
    private function checkDuplicateTransaction($email, $amount, $transaction_id) {
        try {
            $rules = $this->config['security_rules'];
            $hours = $rules['duplicate_check_hours'];
            
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM payment_logs 
                WHERE office_email = ? AND amount = ? AND transaction_id != ?
                AND status IN ('completed', 'pending')
                AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
            ");
            $stmt->execute([$email, $amount, $transaction_id, $hours]);
            
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            // Nëse tabela nuk ekziston, kthe false (mos blloko regjistrimin)
            if ($e->getCode() == '42S02') {
                return false;
            }
            throw $e;
        }
    }
    
    // Verifikimi i pagesës me kartë krediti
    private function verifyCreditCardPayment($payment_data) {
        $transaction_id = $payment_data['transaction_id'];
        $amount = $payment_data['amount'];
        $card_processor = $payment_data['card_processor'] ?? 'stripe';
        
        if ($card_processor === 'stripe') {
            return $this->verifyStripePayment($transaction_id, $amount);
        } elseif ($card_processor === 'square') {
            return $this->verifySquarePayment($transaction_id, $amount);
        }
        
        throw new Exception('Procesor i kartës së panjohur');
    }
    
    // Verifikimi i pagesës Stripe
    private function verifyStripePayment($transaction_id, $amount) {
        $config = $this->config['card_processors']['stripe'];
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $config['endpoint'] . '/charges/' . $transaction_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $config['secret_key']
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($http_code === 200) {
            $charge_data = json_decode($response, true);
            return $charge_data['paid'] === true && 
                   ($charge_data['amount'] / 100) == $amount; // Stripe uses cents
        }
        
        return false;
    }
    
    // Verifikimi i pagesës Square
    private function verifySquarePayment($transaction_id, $amount) {
        $config = $this->config['card_processors']['square'];
        $environment = $config['environment'] === 'production' ? 'connect.squareup.com' : 'connect.squareupsandbox.com';
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://{$environment}/v2/payments/{$transaction_id}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $config['access_token'],
                'Square-Version: 2023-10-18'
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($http_code === 200) {
            $payment_data = json_decode($response, true);
            return $payment_data['payment']['status'] === 'COMPLETED' &&
                   ($payment_data['payment']['amount_money']['amount'] / 100) == $amount;
        }
        
        return false;
    }
    
    // Kontrolli i limiteve të sigurisë
    private function checkSecurityLimits($email, $amount) {
        $rules = $this->config['security_rules'];
        
        // Kontrolli i shumës
        if ($amount < $rules['min_amount_per_transaction'] || 
            $amount > $rules['max_amount_per_transaction']) {
            return false;
        }
        
        try {
            // Kontrolli i transaksioneve ditore
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM payment_logs 
                WHERE office_email = ? AND DATE(created_at) = CURDATE()
            ");
            $stmt->execute([$email]);
            $daily_count = $stmt->fetchColumn();
            
            if ($daily_count >= $rules['max_daily_transactions_per_email']) {
                return false;
            }
            
            // Kontrolli i përpjekjeve të dështuara
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM payment_logs 
                WHERE office_email = ? AND status = 'failed' 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$email]);
            $failed_attempts = $stmt->fetchColumn();
            
            if ($failed_attempts >= $rules['max_verification_attempts']) {
                return false;
            }
        } catch (PDOException $e) {
            // Nëse tabela nuk ekziston, thirr setup automatik
            if ($e->getCode() == '42S02') {
                $this->logger->error("Payment tables not found: " . $e->getMessage());
                throw new Exception('Tabelat e pagesave nuk janë të krijuara. Ju lutemi ekzekutoni setup_payment_tables.php');
            }
            $this->logger->error("Database error in security limits: " . $e->getMessage());
            return false;
        }
        
        return true;
    }
    
    // Verifikimi i transferit bankar
    private function verifyBankTransfer($payment_data) {
        $bank_name = $payment_data['bank'];
        $iban = $payment_data['iban'];
        $amount = $payment_data['amount'];
        $transaction_id = $payment_data['transaction_id'];
        
        // Validimi i IBAN-it me algoritmin mod-97
        if (!$this->validateIBANAdvanced($iban)) {
            throw new Exception('IBAN nuk është i vlefshëm');
        }
        
        // Kontrolli nëse banka është e konfiguruar
        if (!isset($this->config['bank_apis'][$bank_name])) {
            throw new Exception('Banka nuk është e mbështetur');
        }
        
        $bank_config = $this->config['bank_apis'][$bank_name];
        
        // Thirrja e API-së së bankës me retry logic
        return $this->callBankAPI($bank_config, [
            'iban' => $iban,
            'amount' => $amount,
            'transaction_id' => $transaction_id,
            'timestamp' => time()
        ]);
    }
    
    // Thirrja e API-së së bankës me retry dhe timeout
    private function callBankAPI($bank_config, $data) {
        $attempts = 0;
        $max_attempts = $bank_config['retry_attempts'];
        
        while ($attempts < $max_attempts) {
            $attempts++;
            
            try {
                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL => $bank_config['endpoint'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($data),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $bank_config['api_key'],
                        'X-Request-ID: ' . uniqid()
                    ],
                    CURLOPT_TIMEOUT => $bank_config['timeout'],
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2
                ]);
                
                $response = curl_exec($curl);
                $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($curl);
                curl_close($curl);
                
                if ($curl_error) {
                    throw new Exception("cURL Error: " . $curl_error);
                }
                
                if ($http_code === 200) {
                    $response_data = json_decode($response, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $response_data['verified'] ?? false;
                    }
                    throw new Exception("Response JSON i pavlefshëm");
                }
                
                if ($http_code >= 500) {
                    // Server error - retry
                    $this->logger->warning("Server error {$http_code}, përpjekja {$attempts}");
                    if ($attempts < $max_attempts) {
                        sleep(pow(2, $attempts)); // Exponential backoff
                        continue;
                    }
                }
                
                throw new Exception("HTTP Error: " . $http_code);
                
            } catch (Exception $e) {
                $this->logger->error("API call failed (përpjekja {$attempts}): " . $e->getMessage());
                if ($attempts >= $max_attempts) {
                    throw $e;
                }
                sleep(1);
            }
        }
        
        return false;
    }
    
    // Validimi i avancuar i IBAN-it
    public function validateIBANAdvanced($iban) {
        $iban = preg_replace('/\s+/', '', strtoupper($iban));
        $rules = $this->config['validation_rules']['iban'];
        
        // Kontrolli i gjatësisë
        if (strlen($iban) < $rules['min_length'] || strlen($iban) > $rules['max_length']) {
            return false;
        }
        
        // Kontrolli i kodit të vendit
        if (substr($iban, 0, 2) !== $rules['required_country_code']) {
            return false;
        }
        
        // Kontrolli i karaktereve
        if (!preg_match('/^[A-Z0-9]+$/', $iban)) {
            return false;
        }
        
        // Algoritmi mod-97 për verifikim
        if ($rules['check_mod97']) {
            return $this->checkMod97($iban);
        }
        
        return true;
    }
    
    // Implementimi i algoritmit mod-97 për IBAN
    private function checkMod97($iban) {
        // Zhvendosja e 4 karaktereve të para në fund
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        
        // Konvertimi i shkronjave në numra
        $numeric = '';
        for ($i = 0; $i < strlen($rearranged); $i++) {
            $char = $rearranged[$i];
            if (ctype_alpha($char)) {
                $numeric .= (ord($char) - ord('A') + 10);
            } else {
                $numeric .= $char;
            }
        }
        
        // Kontrolli mod-97
        return bcmod($numeric, '97') === '1';
    }
    
    // Verifikimi i pagesës PayPal
    private function verifyPayPalPayment($payment_data) {
        $config = $this->config['paypal'];
        $transaction_id = $payment_data['transaction_id'];
        
        // Merr access token
        $access_token = $this->getPayPalAccessToken($config);
        if (!$access_token) {
            throw new Exception('Nuk mund të merret access token nga PayPal');
        }
        
        // Verifiko transaksionin
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $config['endpoint'] . '/v1/payments/payment/' . $transaction_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $access_token,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($http_code === 200) {
            $payment_info = json_decode($response, true);
            return $payment_info['state'] === 'approved';
        }
        
        return false;
    }
    
    // Merr access token nga PayPal
    private function getPayPalAccessToken($config) {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $config['endpoint'] . '/v1/oauth2/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_USERPWD => $config['client_id'] . ':' . $config['client_secret'],
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Language: en_US'
            ]
        ]);
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($http_code === 200) {
            $data = json_decode($response, true);
            return $data['access_token'] ?? null;
        }
        
        return null;
    }
    
    // Log-imi i transaksioneve
    private function logTransaction($transaction_id, $status, $data, $error = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO payment_logs 
                (transaction_id, office_email, amount, payment_method, status, api_response, payment_data, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $transaction_id,
                $data['email'],
                $data['amount'],
                $data['method'],
                $status,
                $error,
                json_encode($data)
            ]);
        } catch (PDOException $e) {
            $this->logger->error("Failed to log transaction: " . $e->getMessage());
        }
    }
    
    // Gjenerimi i ID-së së transaksionit me kriptografi të sigurt
    public function generateSecureTransactionId() {
        $timestamp = date('Ymd_His');
        $random = bin2hex(random_bytes(8));
        $checksum = substr(hash('sha256', $timestamp . $random), 0, 8);
        
        return "TXN_{$timestamp}_{$random}_{$checksum}";
    }
}

// Klasa për log-im
class Logger {
    private $file_path;
    
    public function __construct($file_path) {
        $this->file_path = $file_path;
    }
    
    public function info($message, $context = []) {
        $this->log('INFO', $message, $context);
    }
    
    public function warning($message, $context = []) {
        $this->log('WARNING', $message, $context);
    }
    
    public function error($message, $context = []) {
        $this->log('ERROR', $message, $context);
    }
    
    private function log($level, $message, $context) {
        $timestamp = date('Y-m-d H:i:s');
        $context_str = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $log_entry = "[{$timestamp}] {$level}: {$message}{$context_str}" . PHP_EOL;
        
        file_put_contents($this->file_path, $log_entry, FILE_APPEND | LOCK_EX);
    }
}
?>