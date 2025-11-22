<?php
// Sistemi i verifikimit të telefonave për Kosovë - 3 minuta
// filepath: d:\xampp\htdocs\noteria\PhoneVerificationAdvanced.php

class PhoneVerificationAdvanced {
    private $pdo;
    private $config;
    
    public function __construct($pdo, $config = null) {
        $this->pdo = $pdo;
        $this->config = $config ?? $this->getDefaultConfig();
    }
    
    private function getDefaultConfig() {
        return [
            'demo_mode' => getenv('SMS_DEMO_MODE') !== false ? true : true, // Aktivizuar për zhvillim
            'sms_providers' => [
                'demo' => [
                    'enabled' => true,
                    'name' => 'Demo Provider',
                    'always_success' => true
                ],
                'twilio' => [
                    'account_sid' => getenv('TWILIO_ACCOUNT_SID') ?: 'test_sid',
                    'auth_token' => getenv('TWILIO_AUTH_TOKEN') ?: 'test_token',
                    'from_number' => getenv('TWILIO_FROM_NUMBER') ?: '+15005550006',
                    'endpoint' => 'https://api.twilio.com/2010-04-01/Accounts/',
                    'timeout' => 30
                ],
                'infobip' => [
                    'api_key' => getenv('INFOBIP_API_KEY') ?: 'test_key',
                    'base_url' => 'https://api.infobip.com',
                    'sender' => 'Noteria',
                    'timeout' => 30
                ],
                'ipko' => [
                    // Provider lokal për Kosovë
                    'api_key' => getenv('IPKO_SMS_API_KEY') ?: 'test_key',
                    'endpoint' => 'https://sms.ipko.com/api/send',
                    'sender' => 'NOTERIA',
                    'timeout' => 20
                ],
                'vala' => [
                    // Operatori mobil Vala për Kosovë
                    'api_key' => getenv('VALA_API_KEY') ?: 'test_vala_key',
                    'api_secret' => getenv('VALA_API_SECRET') ?: 'test_vala_secret',
                    'sender' => 'NOTERIA',
                    'endpoint' => 'https://api.vala.com/sms/send',
                    'base_url' => 'https://api.vala.com',
                    'timeout' => 25
                ]
            ],
            'verification_settings' => [
                'code_length' => 6,
                'code_expiry_minutes' => 3, // Synkronizuar me 3-minutëshin
                'max_attempts' => 3,
                'cooldown_minutes' => 1,
                'max_daily_sends' => 5,
                'allowed_country_codes' => ['+383', '+377', '+381'] // Kosovë, pri, Serbi
            ],
            'templates' => [
                'verification_code' => 'Kodi juaj i verifikimit për Noteria: {code}. I vlefshëm për 3 minuta. Mos e ndani me askënd!',
                'payment_confirmed' => 'Pagesa juaj u verifikua! Transaction ID: {transaction_id}. Mirë se erdhët në Noteria Platform!'
            ]
        ];
    }
    
    // Gjenerimi i kodit të verifikimit
    public function generateVerificationCode($phone_number, $transaction_id = null) {
        try {
            // Kontrolli i telefonit
            if (!$this->isValidKosovaPhone($phone_number)) {
                throw new Exception('Numri i telefonit nuk është valid për Kosovë');
            }
            
            // Kontrolli i limiteve ditore
            if (!$this->checkDailyLimits($phone_number)) {
                throw new Exception('Keni tejkaluar limitin ditor të SMS-ve');
            }
            
            // Gjenerimi i kodit 6-shifror
            $code = sprintf("%06d", mt_rand(100000, 999999));
            $expires_at = date('Y-m-d H:i:s', time() + ($this->config['verification_settings']['code_expiry_minutes'] * 60));
            
            // Ruajtja në databazë
            $stmt = $this->pdo->prepare("
                INSERT INTO phone_verification_codes 
                (phone_number, verification_code, transaction_id, expires_at, created_at) 
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                verification_code = VALUES(verification_code),
                expires_at = VALUES(expires_at),
                attempts = 0,
                is_used = 0,
                created_at = NOW()
            ");
            
            $stmt->execute([$phone_number, $code, $transaction_id, $expires_at]);
            
            // Dërgimi i SMS-it
            $sms_result = $this->sendSMS($phone_number, $code);
            
            if ($sms_result['success']) {
                // Logimi i suksesit
                $this->logPhoneVerification($phone_number, 'code_sent', $transaction_id, $code);
                
                return [
                    'success' => true,
                    'message' => 'Kodi i verifikimit u dërgua në telefon brenda 3 minutave',
                    'expires_in_minutes' => $this->config['verification_settings']['code_expiry_minutes'],
                    'provider_used' => $sms_result['provider']
                ];
            } else {
                throw new Exception('Dështoi dërgimi i SMS-it: ' . $sms_result['error']);
            }
            
        } catch (Exception $e) {
            $this->logPhoneVerification($phone_number, 'error', $transaction_id, null, $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Verifikimi i kodit të marrë
    public function verifyCode($phone_number, $entered_code, $transaction_id = null) {
        try {
            // Marrim kodin nga databaza
            $stmt = $this->pdo->prepare("
                SELECT id, verification_code, expires_at, attempts, is_used 
                FROM phone_verification_codes 
                WHERE phone_number = ? AND transaction_id = ? AND expires_at > NOW()
                ORDER BY created_at DESC LIMIT 1
            ");
            
            $stmt->execute([$phone_number, $transaction_id]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$record) {
                throw new Exception('Kodi ka skaduar ose nuk është gjetur');
            }
            
            if ($record['is_used']) {
                throw new Exception('Kodi është përdorur tashmë');
            }
            
            // Përditësimi i tentativave
            $attempts = $record['attempts'] + 1;
            $this->updateVerificationAttempts($record['id'], $attempts);
            
            if ($attempts > $this->config['verification_settings']['max_attempts']) {
                throw new Exception('Keni tejkaluar numrin maksimal të tentativave');
            }
            
            // Kontrolli i kodit
            if ($record['verification_code'] === $entered_code) {
                // Sukses - shëno si të verifikuar
                $this->markAsVerified($record['id']);
                $this->logPhoneVerification($phone_number, 'verified_success', $transaction_id, $entered_code);
                
                // Dërgoji SMS konfirmimi
                $this->sendConfirmationSMS($phone_number, $transaction_id);
                
                return [
                    'success' => true,
                    'message' => 'Telefoni u verifikua me sukses!',
                    'verified_at' => date('Y-m-d H:i:s')
                ];
            } else {
                $this->logPhoneVerification($phone_number, 'wrong_code', $transaction_id, $entered_code);
                
                $remaining = $this->config['verification_settings']['max_attempts'] - $attempts;
                throw new Exception("Kodi është i gabuar. Keni edhe $remaining tentativa të mbetura.");
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Dërgimi i SMS-it
    private function sendSMS($phone_number, $code) {
        $message = str_replace('{code}', $code, $this->config['templates']['verification_code']);
        
        // Nëse jemi në demo mode, simuloj suksesin
        if ($this->config['demo_mode']) {
            error_log("DEMO SMS: Phone: $phone_number, Code: $code, Message: $message");
            return [
                'success' => true,
                'provider' => 'demo',
                'message_id' => 'demo_' . time(),
                'demo_mode' => true
            ];
        }
        
        // Provoj me provider-ë të ndryshëm (lokalë para ndërkombëtarë)
        $providers = ['ipko', 'vala', 'infobip', 'twilio']; // Fillo me provider-ët lokalë
        
        foreach ($providers as $provider) {
            try {
                $result = $this->sendWithProvider($provider, $phone_number, $message);
                if ($result['success']) {
                    return [
                        'success' => true,
                        'provider' => $provider,
                        'message_id' => $result['message_id'] ?? null
                    ];
                }
            } catch (Exception $e) {
                continue; // Provo provider-in tjetër
            }
        }
        
        return [
            'success' => false,
            'error' => 'Të gjithë provider-ët SMS dështuan'
        ];
    }
    
    // Dërgimi përmes provider-it specifik
    private function sendWithProvider($provider, $phone_number, $message) {
        $config = $this->config['sms_providers'][$provider];
        
        switch ($provider) {
            case 'ipko':
                return $this->sendWithIPKO($config, $phone_number, $message);
            case 'vala':
                return $this->sendWithVala($config, $phone_number, $message);
            case 'infobip':
                return $this->sendWithInfobip($config, $phone_number, $message);
            case 'twilio':
                return $this->sendWithTwilio($config, $phone_number, $message);
            default:
                throw new Exception("Provider i panjohur: $provider");
        }
    }
    
    // IPKO SMS (provider lokal për Kosovë)
    private function sendWithIPKO($config, $phone_number, $message) {
        $data = [
            'api_key' => $config['api_key'],
            'sender' => $config['sender'],
            'to' => $phone_number,
            'message' => $message,
            'type' => 'verification'
        ];
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $config['endpoint'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $config['api_key']
            ],
            CURLOPT_TIMEOUT => $config['timeout']
        ]);
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($http_code === 200) {
            $result = json_decode($response, true);
            return [
                'success' => $result['success'] ?? false,
                'message_id' => $result['message_id'] ?? null
            ];
        }
        
        throw new Exception("IPKO SMS error: HTTP $http_code");
    }
    
    // Vala SMS (operator mobil për Kosovë)
    private function sendWithVala($config, $phone_number, $message) {
        $data = [
            'api_key' => $config['api_key'],
            'api_secret' => $config['api_secret'],
            'sender' => $config['sender'],
            'to' => $phone_number,
            'message' => $message,
            'type' => 'text',
            'priority' => 'high' // Për verifikim të shpejtë
        ];
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $config['endpoint'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data), // Vala përdor form data
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: Noteria-SMS/1.0'
            ],
            CURLOPT_TIMEOUT => $config['timeout'],
            CURLOPT_SSL_VERIFYPEER => false // Për test, në produksion duhet true
        ]);
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            throw new Exception("Vala cURL error: $error");
        }
        
        if ($http_code === 200) {
            $result = json_decode($response, true);
            
            // Vala response format kontrolli
            if (isset($result['status']) && $result['status'] === 'success') {
                return [
                    'success' => true,
                    'message_id' => $result['sms_id'] ?? $result['message_id'] ?? null,
                    'credits_used' => $result['credits_used'] ?? null
                ];
            } else {
                throw new Exception("Vala SMS error: " . ($result['error'] ?? 'Unknown error'));
            }
        }
        
        throw new Exception("Vala SMS error: HTTP $http_code - $response");
    }
    
    // Infobip SMS
    private function sendWithInfobip($config, $phone_number, $message) {
        $data = [
            'messages' => [[
                'from' => $config['sender'],
                'destinations' => [['to' => $phone_number]],
                'text' => $message
            ]]
        ];
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $config['base_url'] . '/sms/2/text/advanced',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: App ' . $config['api_key'],
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => $config['timeout']
        ]);
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($http_code === 200) {
            $result = json_decode($response, true);
            return [
                'success' => true,
                'message_id' => $result['messages'][0]['messageId'] ?? null
            ];
        }
        
        throw new Exception("Infobip SMS error: HTTP $http_code");
    }
    
    // Twilio SMS
    private function sendWithTwilio($config, $phone_number, $message) {
        $data = [
            'From' => $config['from_number'],
            'To' => $phone_number,
            'Body' => $message
        ];
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $config['endpoint'] . $config['account_sid'] . '/Messages.json',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_USERPWD => $config['account_sid'] . ':' . $config['auth_token'],
            CURLOPT_TIMEOUT => $config['timeout']
        ]);
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($http_code === 201) {
            $result = json_decode($response, true);
            return [
                'success' => true,
                'message_id' => $result['sid'] ?? null
            ];
        }
        
        throw new Exception("Twilio SMS error: HTTP $http_code");
    }
    
    // Validimi i numrit të telefonit për Kosovë
    private function isValidKosovaPhone($phone_number) {
        $allowed_codes = $this->config['verification_settings']['allowed_country_codes'];
        
        foreach ($allowed_codes as $code) {
            if (strpos($phone_number, $code) === 0) {
                // Formati: +383XXXXXXXX (12 shifra total)
                if ($code === '+383' && strlen($phone_number) === 12) {
                    return preg_match('/^\+383[4-9]\d{7}$/', $phone_number);
                }
                return true;
            }
        }
        
        return false;
    }
    
    // Kontrolli i limiteve ditore
    private function checkDailyLimits($phone_number) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM phone_verification_codes 
            WHERE phone_number = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$phone_number]);
        $daily_count = $stmt->fetchColumn();
        
        return $daily_count < $this->config['verification_settings']['max_daily_sends'];
    }
    
    // Përditësimi i tentativave
    private function updateVerificationAttempts($id, $attempts) {
        $stmt = $this->pdo->prepare("
            UPDATE phone_verification_codes 
            SET attempts = ? 
            WHERE id = ?
        ");
        $stmt->execute([$attempts, $id]);
    }
    
    // Shënimi si të verifikuar
    private function markAsVerified($id) {
        $stmt = $this->pdo->prepare("
            UPDATE phone_verification_codes 
            SET is_used = 1 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
    }
    
    // Dërgimi i SMS-it të konfirmimit
    private function sendConfirmationSMS($phone_number, $transaction_id) {
        $message = str_replace('{transaction_id}', $transaction_id, 
                  $this->config['templates']['payment_confirmed']);
        
        // Nëse jemi në demo mode, simuloj suksesin
        if ($this->config['demo_mode']) {
            error_log("DEMO CONFIRMATION SMS: Phone: $phone_number, Transaction: $transaction_id");
            return true;
        }
        
        // Dërgo përmes provider-it më të shpejtë
        try {
            $this->sendWithProvider('ipko', $phone_number, $message);
        } catch (Exception $e) {
            // Nëse dështon, vazhdo pa SMS konfirmimi
            error_log("Confirmation SMS failed: " . $e->getMessage());
        }
    }
    
    // Logimi i veprimeve
    private function logPhoneVerification($phone_number, $action, $transaction_id = null, $code = null, $error = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO phone_verification_logs 
                (phone_number, action, transaction_id, verification_code, error_message, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $phone_number,
                $action,
                $transaction_id,
                $code,
                $error,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            // Log error silently
            error_log("Phone verification log error: " . $e->getMessage());
        }
    }
    
    // Kontrolli i statusit të verifikimit
    public function isPhoneVerified($phone_number, $transaction_id = null) {
        $stmt = $this->pdo->prepare("
            SELECT is_used FROM phone_verification_codes 
            WHERE phone_number = ? AND transaction_id = ? AND is_used = 1
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$phone_number, $transaction_id]);
        
        return $stmt->fetchColumn() ? true : false;
    }
    
    // Statistikat e verifikimit
    public function getVerificationStats($time_period = '24h') {
        $where_clause = match($time_period) {
            '1h' => "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            '3h' => "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 3 HOUR)",
            '24h' => "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            '7d' => "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            default => "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        };
        
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_sent,
                SUM(CASE WHEN is_used = 1 THEN 1 ELSE 0 END) as total_verified,
                AVG(CASE WHEN is_used = 1 THEN TIMESTAMPDIFF(SECOND, created_at, created_at) ELSE NULL END) as avg_verification_time_seconds
            FROM phone_verification_codes 
            $where_clause
        ");
        
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>