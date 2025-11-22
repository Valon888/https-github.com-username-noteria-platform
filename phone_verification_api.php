<?php
// API për verifikimin e telefonave - Resend dhe kontrolle
// filepath: d:\xampp\htdocs\noteria\phone_verification_api.php

header('Content-Type: application/json');
session_start();

require_once 'config.php';
require_once 'PhoneVerificationAdvanced.php';

try {
    // Kontrolli i metodës
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Vetëm POST requests janë të lejuara');
    }
    
    // Leximi i të dhënave JSON
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $phoneVerifier = new PhoneVerificationAdvanced($pdo);
    
    switch ($action) {
        case 'resend':
            if (!isset($_SESSION['phone_verification_pending'])) {
                throw new Exception('Sesioni i verifikimit nuk është aktiv');
            }
            
            $phone_data = $_SESSION['phone_verification_pending'];
            
            // Kontrollo nëse ka kaluar 1 minuta nga dërgimi i fundit
            $stmt = $pdo->prepare("
                SELECT created_at FROM phone_verification_codes 
                WHERE phone_number = ? AND transaction_id = ? 
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$phone_data['phone'], $phone_data['transaction_id']]);
            $last_sent = $stmt->fetchColumn();
            
            if ($last_sent && (time() - strtotime($last_sent)) < 60) {
                throw new Exception('Duhet të prisni 1 minutë para se të kërkoni SMS tjetër');
            }
            
            $result = $phoneVerifier->generateVerificationCode(
                $phone_data['phone'], 
                $phone_data['transaction_id']
            );
            
            if ($result['success']) {
                // Përditëso kohën e skadimit në session
                $_SESSION['phone_verification_pending']['expires_at'] = time() + (3 * 60);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'SMS u dërgua përsëri',
                    'expires_in_minutes' => 3
                ]);
            } else {
                throw new Exception($result['error']);
            }
            break;
            
        case 'check_status':
            if (!isset($_SESSION['phone_verification_pending'])) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Sesioni i verifikimit nuk është aktiv'
                ]);
                exit;
            }
            
            $phone_data = $_SESSION['phone_verification_pending'];
            $is_verified = $phoneVerifier->isPhoneVerified(
                $phone_data['phone'], 
                $phone_data['transaction_id']
            );
            
            echo json_encode([
                'success' => true,
                'is_verified' => $is_verified,
                'time_left' => max(0, $phone_data['expires_at'] - time())
            ]);
            break;
            
        case 'get_stats':
            // Statistika për admin
            $stats_1h = $phoneVerifier->getVerificationStats('1h');
            $stats_24h = $phoneVerifier->getVerificationStats('24h');
            
            echo json_encode([
                'success' => true,
                'stats' => [
                    'last_hour' => $stats_1h,
                    'last_24_hours' => $stats_24h
                ]
            ]);
            break;
            
        default:
            throw new Exception('Veprim i panjohur: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>