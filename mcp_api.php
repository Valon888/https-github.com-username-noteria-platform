<?php
// mcp_api.php - MCP API për verifikim të pagesave
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Kërkohet autentifikim për API
require_once 'config.php';
session_start();

// Initialize PDO connection if not already set
if (!isset($pdo) || !$pdo) {
    try {
        $pdo = new PDO(
            "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
            $db_user,
            $db_pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
}

// Kontrollo autentifikimin
// Funksioni për të lexuar të gjithë headers në PHP CLI dhe CGI/FastCGI
function get_all_headers() {
    if (function_exists('getallheaders')) {
        return getallheaders();
    }
    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            $headers[$name] = $value;
        } elseif ($name == 'CONTENT_TYPE') {
            $headers['Content-Type'] = $value;
        } elseif ($name == 'CONTENT_LENGTH') {
            $headers['Content-Length'] = $value;
        }
    }
    return $headers;
}

$headers = get_all_headers();
$authToken = $headers['Authorization'] ?? '';

// Funksioni për validimin e token-it
function validateToken($token) {
    global $pdo, $db_host, $db_name, $db_user, $db_pass;
    if (empty($token)) return false;

    // Ensure $pdo is initialized
    if (!isset($pdo) || !$pdo) {
        try {
            $pdo = new PDO(
                "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
                $db_user,
                $db_pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed in validateToken: " . $e->getMessage());
            return false;
        }
    }

    // Formati: "Bearer TOKEN"
    $parts = explode(' ', $token);
    if (count($parts) !== 2 || $parts[0] !== 'Bearer') return false;

    $actualToken = $parts[1];
    // Validimi nga databaza (shembull)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM api_tokens WHERE token = ? AND expired_at > NOW()");
    $stmt->execute([$actualToken]);
    return $stmt->fetchColumn() > 0;
}

// Funksioni për përgjigje JSON
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Ndërto tabelën e api_tokens nëse nuk ekziston
function createApiTokensTable() {
    global $pdo, $db_host, $db_name, $db_user, $db_pass;
    // Ensure $pdo is initialized
    if (!isset($pdo) || !$pdo) {
        try {
            $pdo = new PDO(
                "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
                $db_user,
                $db_pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed in createApiTokensTable: " . $e->getMessage());
            return false;
        }
    }
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS api_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                token VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expired_at TIMESTAMP DEFAULT NULL,
                description VARCHAR(255),
                UNIQUE(token)
            )
        ");
        return true;
    } catch (PDOException $e) {
        error_log("Error creating api_tokens table: " . $e->getMessage());
        return false;
    }
}

// Ndërto tabelën e payment_logs nëse nuk ekziston
function createPaymentLogsTable() {
    global $pdo, $db_host, $db_name, $db_user, $db_pass;
    // Ensure $pdo is initialized
    if (!isset($pdo) || !$pdo) {
        try {
            $pdo = new PDO(
                "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
                $db_user,
                $db_pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed in createPaymentLogsTable: " . $e->getMessage());
            return false;
        }
    }
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS payment_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                office_email VARCHAR(255) NOT NULL,
                office_name VARCHAR(255) NOT NULL,
                phone_number VARCHAR(20) NOT NULL,
                operator VARCHAR(50),
                payment_method VARCHAR(50) NOT NULL,
                payment_amount DECIMAL(10,2) NOT NULL,
                payment_details TEXT,
                transaction_id VARCHAR(100) NOT NULL,
                verification_status VARCHAR(20) DEFAULT 'pending',
                file_path VARCHAR(255),
                numri_fiskal VARCHAR(20),
                numri_biznesit VARCHAR(20),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                verified_at TIMESTAMP NULL DEFAULT NULL,
                verified_by VARCHAR(100),
                UNIQUE(transaction_id)
            )
        ");
        return true;
    } catch (PDOException $e) {
        error_log("Error creating payment_logs table: " . $e->getMessage());
        return false;
    }
}

// Rrugët e API
$requestMethod = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? 'default';

// Sigurohu që tabelat ekzistojnë
createApiTokensTable();
createPaymentLogsTable();

// Route për admin për të gjeneruar token
if ($endpoint === 'generate_token' && $requestMethod === 'POST' && isset($_SESSION['admin_id'])) {
    $token = bin2hex(random_bytes(32));
    $description = $_POST['description'] ?? 'API Token';
    $expiry = $_POST['expiry'] ?? date('Y-m-d H:i:s', strtotime('+1 year'));
    
    try {
        $stmt = $pdo->prepare("INSERT INTO api_tokens (token, expired_at, description) VALUES (?, ?, ?)");
        $stmt->execute([$token, $expiry, $description]);
        jsonResponse(['success' => true, 'token' => $token, 'expires' => $expiry]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Gabim gjatë gjenerimit të tokenit: ' . $e->getMessage()], 500);
    }
}

// Kërko autentifikimin për endpoints të tjera
if (!validateToken($authToken) && $endpoint !== 'default') {
    jsonResponse(['error' => 'Unauthorized access'], 401);
}

// API endpoints
switch ($endpoint) {
    case 'payments':
        if ($requestMethod === 'GET') {
            // Kthe listën e pagesave për verifikim
            $stmt = $pdo->query("SELECT * FROM payment_logs ORDER BY created_at DESC LIMIT 100");
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            jsonResponse(['payments' => $payments]);
        } else {
            jsonResponse(['error' => 'Method not allowed'], 405);
        }
        break;
        
    case 'verify_payment':
        if ($requestMethod === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $transaction_id = $data['transaction_id'] ?? '';
            $status = $data['status'] ?? '';
            $verifier = $data['verifier'] ?? 'api_user';
            
            if (empty($transaction_id) || empty($status)) {
                jsonResponse(['error' => 'Missing required fields'], 400);
            }
            
            if (!in_array($status, ['verified', 'rejected', 'pending'])) {
                jsonResponse(['error' => 'Invalid status'], 400);
            }
            
            try {
                $stmt = $pdo->prepare("
                    UPDATE payment_logs 
                    SET verification_status = ?, verified_at = NOW(), verified_by = ? 
                    WHERE transaction_id = ?
                ");
                $stmt->execute([$status, $verifier, $transaction_id]);
                
                if ($stmt->rowCount() === 0) {
                    jsonResponse(['error' => 'Transaction not found'], 404);
                }
                
                // Nëse është verifikuar, aktivizo edhe përdoruesin
                if ($status === 'verified') {
                    $stmt = $pdo->prepare("
                        UPDATE users u 
                        JOIN payment_logs p ON u.email = p.office_email 
                        SET u.status = 'active' 
                        WHERE p.transaction_id = ?
                    ");
                    $stmt->execute([$transaction_id]);
                }
                
                jsonResponse(['success' => true, 'message' => 'Payment status updated']);
            } catch (PDOException $e) {
                jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
            }
        } else {
            jsonResponse(['error' => 'Method not allowed'], 405);
        }
        break;
        
    case 'payment_details':
        if ($requestMethod === 'GET') {
            $transaction_id = $_GET['transaction_id'] ?? '';
            
            if (empty($transaction_id)) {
                jsonResponse(['error' => 'Missing transaction_id'], 400);
            }
            
            $stmt = $pdo->prepare("SELECT * FROM payment_logs WHERE transaction_id = ?");
            $stmt->execute([$transaction_id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                jsonResponse(['error' => 'Transaction not found'], 404);
            }
            
            jsonResponse(['payment' => $payment]);
        } else {
            jsonResponse(['error' => 'Method not allowed'], 405);
        }
        break;
        
    case 'default':
        jsonResponse([
            'api' => 'Noteria Payment Verification API',
            'version' => '1.0',
            'endpoints' => [
                '/mcp_api.php?endpoint=payments',
                '/mcp_api.php?endpoint=verify_payment',
                '/mcp_api.php?endpoint=payment_details'
            ]
        ]);
        break;
        
    default:
        jsonResponse(['error' => 'Endpoint not found'], 404);
}
?>