<?php
// api_install.php - Krijo tabelat e nevojshme për API dhe monitorimin e tij
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';
session_start();

// Kontrollo nëse përdoruesi është i autentifikuar si admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$messages = [];
$errors = [];

// Funksion për të ekzekutuar një SQL query dhe shtuar mesazhin përkatës
function executeQuery($pdo, $sql, $successMsg, $errorMsg = null) {
    global $messages, $errors;
    
    try {
        $stmt = $pdo->exec($sql);
        $messages[] = $successMsg;
        return true;
    } catch (PDOException $e) {
        $errors[] = $errorMsg ?? "Gabim në SQL: " . $e->getMessage();
        return false;
    }
}

// Nëse është konfirmuar instalimi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    
    // Krijo tabelën api_tokens
    $sqlTokens = "CREATE TABLE IF NOT EXISTS api_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        token VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expired_at TIMESTAMP NULL DEFAULT NULL,
        description TEXT,
        UNIQUE KEY (token)
    )";
    
    executeQuery(
        $pdo, 
        $sqlTokens, 
        "Tabela 'api_tokens' u krijua me sukses.", 
        "Gabim në krijimin e tabelës 'api_tokens': "
    );
    
    // Krijo tabelën api_logs
    $sqlLogs = "CREATE TABLE IF NOT EXISTS api_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        endpoint VARCHAR(255) NOT NULL,
        method VARCHAR(10) NOT NULL,
        status_code INT NOT NULL,
        request_data TEXT,
        response_data TEXT,
        client_ip VARCHAR(45) NOT NULL,
        user_agent TEXT,
        token_id INT NULL,
        response_time FLOAT NOT NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (endpoint),
        INDEX (method),
        INDEX (status_code),
        INDEX (timestamp),
        INDEX (token_id),
        FOREIGN KEY (token_id) REFERENCES api_tokens(id) ON DELETE SET NULL
    )";
    
    executeQuery(
        $pdo, 
        $sqlLogs, 
        "Tabela 'api_logs' u krijua me sukses.", 
        "Gabim në krijimin e tabelës 'api_logs': "
    );
    
    // Krijo një token fillestar për testim
    if (!empty($messages) && count($errors) === 0) {
        try {
            // Kontrollo nëse ekziston një token testimi
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM api_tokens WHERE description LIKE '%test%'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                $token = bin2hex(random_bytes(32));
                $stmt = $pdo->prepare("INSERT INTO api_tokens (token, description) VALUES (?, 'Token testimi (i gjeneruar automatikisht)')");
                $stmt->execute([$token]);
                $messages[] = "Token-i fillestar për testim u krijua me sukses: " . $token;
            }
        } catch (PDOException $e) {
            $errors[] = "Gabim në krijimin e token-it fillestar: " . $e->getMessage();
        }
    }
}

// Kontrollo nëse tabelat ekzistojnë
try {
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    $hasTokensTable = in_array('api_tokens', $tables);
    $hasLogsTable = in_array('api_logs', $tables);
    
    // Merr numrin e token-ave dhe logeve
    $tokenCount = 0;
    $logCount = 0;
    
    if ($hasTokensTable) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM api_tokens");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $tokenCount = $result['count'];
    }
    
    if ($hasLogsTable) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM api_logs");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $logCount = $result['count'];
    }
    
} catch (PDOException $e) {
    $errors[] = "Gabim në kontrollin e tabelave: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Instalimi | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            background-color: #f8fafc;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .panel {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
        }
        h1 {
            color: #1a56db;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
        }
        h1 i {
            margin-right: 12px;
        }
        h2 {
            color: #2563eb;
            font-size: 1.4rem;
            margin-top: 30px;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
        }
        .message {
            padding: 12px 15px;
            margin: 20px 0;
            border-radius: 6px;
            font-weight: 500;
        }
        .success {
            background-color: #dcfce7;
            color: #166534;
            border-left: 5px solid #16a34a;
        }
        .error {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 5px solid #dc2626;
        }
        .warning {
            background-color: #fef3c7;
            color: #92400e;
            border-left: 5px solid #f59e0b;
        }
        .info {
            background-color: #dbeafe;
            color: #1e40af;
            border-left: 5px solid #2563eb;
        }
        .flex {
            display: flex;
            gap: 20px;
        }
        .flex-col {
            flex-direction: column;
        }
        .col {
            flex: 1;
        }
        button, .button {
            background-color: #1a56db;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s;
            text-decoration: none;
        }
        button i, .button i {
            margin-right: 8px;
        }
        button:hover, .button:hover {
            background-color: #1e40af;
            transform: translateY(-1px);
        }
        .btn-danger {
            background-color: #ef4444;
        }
        .btn-danger:hover {
            background-color: #dc2626;
        }
        .btn-warning {
            background-color: #f59e0b;
        }
        .btn-warning:hover {
            background-color: #d97706;
        }
        .btn-success {
            background-color: #10b981;
        }
        .btn-success:hover {
            background-color: #059669;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            margin-left: 10px;
        }
        .status-success {
            background-color: #dcfce7;
            color: #166534;
        }
        .status-missing {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .code-block {
            background-color: #1e293b;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            margin: 15px 0;
            font-family: 'Courier New', Courier, monospace;
            white-space: pre-wrap;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background-color: #f8fafc;
            font-weight: 600;
        }
        tr:hover {
            background-color: #f1f5f9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-wrench"></i> Instalimi i API</h1>
        
        <div class="toolbar">
            <div>
                <a href="api_monitor.php" class="button">
                    <i class="fas fa-chart-line"></i> Monitori i API
                </a>
                <a href="token_generator.php" class="button">
                    <i class="fas fa-key"></i> Gjeneratori i Token
                </a>
                <a href="api_client_test.php" class="button">
                    <i class="fas fa-flask"></i> Test Client
                </a>
            </div>
        </div>
        
        <div class="panel">
            <h2>Statusi i instalimit</h2>
            
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="message error"><?php echo $error; ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($messages)): ?>
                <?php foreach ($messages as $message): ?>
                    <div class="message success"><?php echo $message; ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <div class="flex">
                <div class="col">
                    <h3>
                        Tabela api_tokens
                        <?php if (isset($hasTokensTable)): ?>
                            <?php if ($hasTokensTable): ?>
                                <span class="status-badge status-success">Instaluar</span>
                            <?php else: ?>
                                <span class="status-badge status-missing">Mungon</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </h3>
                    <p>Kjo tabelë përdoret për ruajtjen e token-ave të API. Aktualisht ka <?php echo $tokenCount ?? 0; ?> token(a) të regjistruar.</p>
                    
                    <?php if (isset($hasTokensTable) && $hasTokensTable): ?>
                        <div class="flex" style="margin-top: 15px;">
                            <a href="token_generator.php" class="button btn-success">
                                <i class="fas fa-key"></i> Menaxho token-at
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="col">
                    <h3>
                        Tabela api_logs
                        <?php if (isset($hasLogsTable)): ?>
                            <?php if ($hasLogsTable): ?>
                                <span class="status-badge status-success">Instaluar</span>
                            <?php else: ?>
                                <span class="status-badge status-missing">Mungon</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </h3>
                    <p>Kjo tabelë përdoret për monitorimin e kërkesave API. Aktualisht ka <?php echo $logCount ?? 0; ?> log(e) të regjistruara.</p>
                    
                    <?php if (isset($hasLogsTable) && $hasLogsTable): ?>
                        <div class="flex" style="margin-top: 15px;">
                            <a href="api_monitor.php" class="button btn-success">
                                <i class="fas fa-chart-line"></i> Shiko monitorimin
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ((!isset($hasTokensTable) || !$hasTokensTable) || (!isset($hasLogsTable) || !$hasLogsTable)): ?>
                <form method="post" style="margin-top: 30px;">
                    <div class="message warning">
                        <strong>Vëmendje:</strong> Ky operacion do të krijojë tabelat e nevojshme për funksionimin e API-t në bazën e të dhënave. 
                        Sigurohuni që keni bërë një backup të bazës së të dhënave përpara se të vazhdoni.
                    </div>
                    <button type="submit" name="install" class="btn-warning">
                        <i class="fas fa-database"></i> Instalo tabelat
                    </button>
                </form>
            <?php else: ?>
                <div class="message success" style="margin-top: 30px;">
                    <strong>Sukses:</strong> Të gjitha tabelat e nevojshme për API janë instaluar.
                </div>
            <?php endif; ?>
        </div>
        
        <div class="panel">
            <h2>Struktura e tabelave</h2>
            
            <h3>Tabela api_tokens</h3>
            <div class="code-block">CREATE TABLE api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expired_at TIMESTAMP NULL DEFAULT NULL,
    description TEXT,
    UNIQUE KEY (token)
)</div>
            
            <h3>Tabela api_logs</h3>
            <div class="code-block">CREATE TABLE api_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(10) NOT NULL,
    status_code INT NOT NULL,
    request_data TEXT,
    response_data TEXT,
    client_ip VARCHAR(45) NOT NULL,
    user_agent TEXT,
    token_id INT NULL,
    response_time FLOAT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (endpoint),
    INDEX (method),
    INDEX (status_code),
    INDEX (timestamp),
    INDEX (token_id),
    FOREIGN KEY (token_id) REFERENCES api_tokens(id) ON DELETE SET NULL
)</div>
            
            <h2>Udhëzime për zhvilluesit</h2>
            <div class="info message">
                <ol>
                    <li>Instaloni tabelat duke përdorur butonin "Instalo tabelat" më sipër.</li>
                    <li>Përdorni <a href="token_generator.php">Gjeneratorin e Token</a> për të krijuar token-a API.</li>
                    <li>Përdorni <a href="api_client_test.php">Test Client</a> për të testuar API-n.</li>
                    <li>Monitoroni trafikun e API-t me <a href="api_monitor.php">Monitorin e API</a>.</li>
                    <li>Dokumentoni API-n në <a href="api_docs.php">API Docs</a>.</li>
                </ol>
            </div>
            
            <h3>Kodet e database për tu përfshirë në mcp_api_new.php</h3>
            <p>Për të aktivizuar monitorimin dhe regjistrimin e kërkesave API, shtoni kodin e mëposhtëm në API tuaj:</p>
            
            <div class="code-block">// Funksioni për logimin e kërkesave API
function logApiRequest($endpoint, $method, $statusCode, $requestData, $responseData, $tokenId = null) {
    global $pdo;
    
    $startTime = microtime(true);
    $clientIp = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Llogarit kohën e përgjigjes
    $responseTime = microtime(true) - $startTime;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO api_logs 
            (endpoint, method, status_code, request_data, response_data, client_ip, user_agent, token_id, response_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
        $stmt->execute([
            $endpoint,
            $method,
            $statusCode,
            $requestData,
            $responseData,
            $clientIp,
            $userAgent,
            $tokenId,
            $responseTime
        ]);
    } catch (Exception $e) {
        // Injoro gabimet në loging, për të shmangur ndikimin në performancë të API
        error_log("API log error: " . $e->getMessage());
    }
}</div>

        </div>
    </div>
</body>
</html>