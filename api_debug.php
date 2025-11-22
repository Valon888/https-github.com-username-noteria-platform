<?php
// api_debug.php - Mjeti për diagnostifikim automatik të API
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';
session_start();

// Kontrolloni nëse përdoruesi është admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Funksionet ndihmëse për thirrjen e API
function callApi($endpoint, $method = 'GET', $data = null, $token = null) {
    $url = 'http://localhost/noteria/mcp_api_new.php?endpoint=' . $endpoint;
    
    // Nëse është kërkesë GET me parametra
    if ($method === 'GET' && is_array($data) && !empty($data)) {
        $queryParams = http_build_query($data);
        $url .= '&' . $queryParams;
    }
    
    $options = [
        'http' => [
            'method' => $method,
            'header' => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            'ignore_errors' => true
        ]
    ];
    
    // Shto token nëse është dhënë
    if ($token) {
        $options['http']['header'][] = 'Authorization: Bearer ' . $token;
    }
    
    // Shto të dhëna nëse është POST/PUT dhe jo GET
    if ($data && in_array($method, ['POST', 'PUT']) && $method !== 'GET') {
        $options['http']['content'] = json_encode($data);
    }
    
    $context = stream_context_create($options);
    
    // Kap çdo gabim që mund të ndodhë gjatë kërkesës
    try {
        $startTime = microtime(true);
        $response = file_get_contents($url, false, $context);
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2); // në milisekonda
        
        // Merr status code dhe headers
        $statusCode = $http_response_header ? intval(substr($http_response_header[0], 9, 3)) : 0;
        
        // Merr të gjitha headers
        $responseHeaders = [];
        foreach ($http_response_header as $header) {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $headerName = trim($parts[0]);
                $headerValue = trim($parts[1]);
                $responseHeaders[$headerName] = $headerValue;
            }
        }
        
        return [
            'status' => $statusCode,
            'body' => json_decode($response, true),
            'raw' => $response,
            'headers' => $responseHeaders,
            'execution_time' => $executionTime,
            'url' => $url,
            'request_method' => $method
        ];
    } catch (Exception $e) {
        return [
            'status' => 500,
            'body' => ['error' => 'Request failed: ' . $e->getMessage()],
            'raw' => 'Exception: ' . $e->getMessage(),
            'headers' => [],
            'execution_time' => 0,
            'url' => $url,
            'request_method' => $method
        ];
    }
}

// Merr token-in nga databaza
$token = null;
try {
    $stmt = $pdo->query("SELECT token FROM api_tokens ORDER BY created_at DESC LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $token = $result['token'] ?? null;
} catch (PDOException $e) {
    $errorMessage = "Gabim në marrjen e token-it: " . $e->getMessage();
}

// Lista e të gjitha endpoints dhe metodave për testim
$endpoints = [
    'default' => ['GET', 'POST'], // duhet të jetë vetëm GET
    'payments' => ['GET', 'POST'], // duhet të jetë vetëm GET
    'payment_details' => ['GET', 'POST'], // duhet të jetë vetëm GET
    'verify_payment' => ['GET', 'POST'], // duhet të jetë vetëm POST
    'non_existent_endpoint' => ['GET'] // endpoint që nuk ekziston
];

// Ekzekuto testimin automatik
$testResults = [];
$overallStatus = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_tests'])) {
    foreach ($endpoints as $endpoint => $methods) {
        foreach ($methods as $method) {
            // Përgatit të dhënat për testim
            $testData = [];
            if ($endpoint === 'payment_details') {
                $testData = ['transaction_id' => 'TXN_20250922_225911_972321bc'];
            } elseif ($endpoint === 'verify_payment' && $method === 'POST') {
                $testData = [
                    'transaction_id' => 'TXN_20250922_225911_972321bc',
                    'status' => 'verified',
                    'verifier' => 'api_test'
                ];
            }
            
            // Thirr API
            $result = callApi($endpoint, $method, $testData, $token);
            
            // Përcakto nëse testi kaloi
            $expectedStatus = 200;
            
            // Raste speciale për dështime të pritshme
            if ($endpoint === 'non_existent_endpoint') {
                $expectedStatus = 404;
            } elseif ($method === 'POST' && in_array($endpoint, ['default', 'payments', 'payment_details'])) {
                $expectedStatus = 405;
            } elseif ($method === 'GET' && $endpoint === 'verify_payment') {
                $expectedStatus = 405;
            }
            
            $passed = ($result['status'] === $expectedStatus);
            
            // Ruaj rezultatin e testit
            $testResults[] = [
                'endpoint' => $endpoint,
                'method' => $method,
                'status_code' => $result['status'],
                'expected_status' => $expectedStatus,
                'passed' => $passed,
                'response' => $result['body'] ?? null,
                'execution_time' => $result['execution_time'] ?? 0
            ];
            
            // Përditëso statusin e përgjithshëm
            if (!$passed) {
                $overallStatus = false;
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Diagnostifikimi | Noteria</title>
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
            max-width: 1200px;
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
        .info {
            background-color: #dbeafe;
            color: #1e40af;
            border-left: 5px solid #2563eb;
        }
        .warning {
            background-color: #fef3c7;
            color: #92400e;
            border-left: 5px solid #f59e0b;
        }
        button {
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
        }
        button i {
            margin-right: 8px;
        }
        button:hover {
            background-color: #1e40af;
            transform: translateY(-1px);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px 15px;
            border: 1px solid #e5e7eb;
            text-align: left;
        }
        th {
            background-color: #f1f5f9;
            font-weight: 600;
        }
        .endpoint {
            font-weight: 600;
            color: #1f2937;
        }
        .method {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 0.85rem;
        }
        .method-get {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .method-post {
            background-color: #dcfce7;
            color: #166534;
        }
        .status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 0.85rem;
        }
        .status-200 {
            background-color: #dcfce7;
            color: #166534;
        }
        .status-400 {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .status-401 {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .status-404 {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .status-405 {
            background-color: #fef3c7;
            color: #92400e;
        }
        .status-500 {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .test-result {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
        }
        .test-passed {
            background-color: #dcfce7;
            color: #166534;
        }
        .test-failed {
            background-color: #fee2e2;
            color: #991b1b;
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
            font-size: 0.85rem;
            max-height: 200px;
            overflow-y: auto;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: 5px;
        }
        .badge-time {
            background-color: #e2e8f0;
            color: #475569;
        }
        .detail-row {
            display: none;
        }
        .toggle-details {
            cursor: pointer;
            color: #2563eb;
            font-size: 0.9rem;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-tools"></i> API Diagnostifikimi</h1>
        
        <div class="panel">
            <div class="message info">
                <i class="fas fa-info-circle"></i> Ky mjet teston të gjitha endpoints e API për të identifikuar probleme të mundshme. Vetëm administratorët kanë qasje në këtë mjet.
            </div>
            
            <p><strong>Shënim:</strong> Ky skript teston të gjitha endpoints me të gjitha metodat HTTP për të identifikuar gabimet "Method not allowed" dhe problemet e tjera.</p>
            
            <form method="post">
                <button type="submit" name="run_tests">
                    <i class="fas fa-play-circle"></i> Fillo Testet e Diagnostifikimit
                </button>
                <a href="api_client_test.php" class="button" style="margin-left: 10px; background-color: #6b7280; color: white; text-decoration: none; padding: 12px 20px; border-radius: 6px; display: inline-flex; align-items: center;">
                    <i class="fas fa-arrow-left" style="margin-right: 8px;"></i> Kthehu tek Test Client
                </a>
            </form>
            
            <?php if (!empty($testResults)): ?>
                <h2>Rezultatet e Testeve</h2>
                
                <?php if ($overallStatus): ?>
                    <div class="message success">
                        <i class="fas fa-check-circle"></i> Të gjitha testet kaluan me sukses! API po funksionon siç duhet.
                    </div>
                <?php else: ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-triangle"></i> Disa teste dështuan. Shikoni tabelën për më shumë detaje.
                    </div>
                <?php endif; ?>
                
                <p>Nëse shihni gabime 405 (Method Not Allowed) për disa thirrje, kjo është e pritur për metodat që nuk mbështeten nga endpoints përkatëse.</p>
                
                <table>
                    <thead>
                        <tr>
                            <th>Endpoint</th>
                            <th>Metoda</th>
                            <th>Status</th>
                            <th>Pritej</th>
                            <th>Rezultati</th>
                            <th>Detaje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($testResults as $i => $result): ?>
                            <tr>
                                <td class="endpoint"><?php echo htmlspecialchars($result['endpoint']); ?></td>
                                <td>
                                    <span class="method method-<?php echo strtolower($result['method']); ?>">
                                        <?php echo htmlspecialchars($result['method']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status status-<?php echo htmlspecialchars($result['status_code']); ?>">
                                        <?php echo htmlspecialchars($result['status_code']); ?>
                                    </span>
                                    <?php if (isset($result['execution_time'])): ?>
                                        <span class="badge badge-time">
                                            <?php echo htmlspecialchars($result['execution_time']); ?> ms
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($result['expected_status']); ?></td>
                                <td>
                                    <span class="test-result <?php echo $result['passed'] ? 'test-passed' : 'test-failed'; ?>">
                                        <?php echo $result['passed'] ? 'Kaluar' : 'Dështuar'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a class="toggle-details" onclick="toggleDetails('<?php echo $i; ?>')">Shfaq detajet</a>
                                </td>
                            </tr>
                            <tr class="detail-row" id="details-<?php echo $i; ?>">
                                <td colspan="6">
                                    <div class="code-block">
                                        <?php print_r($result['response']); ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function toggleDetails(index) {
            const detailRow = document.getElementById('details-' + index);
            detailRow.style.display = detailRow.style.display === 'table-row' ? 'none' : 'table-row';
        }
    </script>
</body>
</html>