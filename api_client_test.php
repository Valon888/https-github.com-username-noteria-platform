<?php
// api_client_test.php - Klient për testimin e MCP API
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';
session_start();

// Kontrolloni nëse përdoruesi është i autentifikuar
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
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
        $response = file_get_contents($url, false, $context);
        
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
        
        // Kontrollo për headerin Allow (për gabimin 405)
        $allowedMethods = [];
        if ($statusCode === 405 && isset($responseHeaders['Allow'])) {
            $allowedMethods = explode(',', $responseHeaders['Allow']);
            $allowedMethods = array_map('trim', $allowedMethods);
        }
        
        $bodyDecoded = json_decode($response, true);
        
        return [
            'status' => $statusCode,
            'body' => $bodyDecoded,
            'raw' => $response,
            'allowed_methods' => $allowedMethods,
            'headers' => $responseHeaders,
            'url' => $url,
            'request_method' => $method
        ];
    } catch (Exception $e) {
        return [
            'status' => 500,
            'body' => ['error' => 'Request failed: ' . $e->getMessage()],
            'raw' => 'Exception: ' . $e->getMessage(),
            'allowed_methods' => [],
            'headers' => [],
            'url' => $url,
            'request_method' => $method
        ];
    }
}

// Merrni token-in nga databaza (për qëllime demonstrimi)
$token = null;
try {
    $stmt = $pdo->query("SELECT token FROM api_tokens ORDER BY created_at DESC LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $token = $result['token'] ?? null;
} catch (PDOException $e) {
    $errorMessage = "Gabim në marrjen e token-it: " . $e->getMessage();
}

// Testimi i API
$result = null;
$method = $_POST['method'] ?? 'GET';
$endpoint = $_POST['endpoint'] ?? 'default';
$testToken = $_POST['token'] ?? $token;
$jsonData = $_POST['json_data'] ?? '{}';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_api'])) {
    try {
        $data = json_decode($jsonData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON i pavlefshëm: " . json_last_error_msg());
        }
        
        $result = callApi($endpoint, $method, $data, $testToken);
    } catch (Exception $e) {
        $errorMessage = "Gabim në thirrjen e API: " . $e->getMessage();
    }
}

// Merr disa pagesa nga databaza për testim
$payments = [];
try {
    $stmt = $pdo->query("SELECT transaction_id, office_name, payment_amount, verification_status FROM payment_logs ORDER BY created_at DESC LIMIT 5");
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $paymentError = "Gabim në marrjen e pagesave: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCP API Test Client | Noteria</title>
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
        form {
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }
        input[type="text"], 
        select, 
        textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 16px;
            font-family: inherit;
        }
        textarea {
            min-height: 150px;
            resize: vertical;
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
        .code-block.json {
            white-space: pre;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 30px;
        }
        th, td {
            padding: 12px 15px;
            border: 1px solid #e5e7eb;
            text-align: left;
        }
        th {
            background-color: #f1f5f9;
            font-weight: 600;
            color: #374151;
        }
        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        tr:hover {
            background-color: #f1f5f9;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .badge-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .badge-verified {
            background-color: #dcfce7;
            color: #166534;
        }
        .badge-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .endpoint-list {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        .endpoint-list li {
            background-color: #f1f5f9;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 10px;
            border-left: 5px solid #2563eb;
            cursor: pointer;
            transition: all 0.2s;
        }
        .endpoint-list li:hover {
            background-color: #e2e8f0;
            transform: translateX(5px);
        }
        .json-sample {
            font-size: 0.9rem;
            margin-top: 5px;
            color: #6b7280;
        }
        .tab-buttons {
            display: flex;
            margin-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
        }
        .tab-button {
            padding: 12px 20px;
            background: none;
            border: none;
            color: #4b5563;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-right: 10px;
        }
        .tab-button.active {
            color: #2563eb;
            border-bottom: 3px solid #2563eb;
        }
        .tab-content {
            display: none;
            padding: 15px 0;
        }
        .tab-content.active {
            display: block;
        }
        .btn-fix {
            background-color: #059669;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s;
            margin-top: 15px;
        }
        .btn-fix:hover {
            background-color: #047857;
        }
        .btn-test-token {
            background-color: #3b82f6;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            margin-left: 10px;
            transition: all 0.2s;
        }
        .btn-test-token:hover {
            background-color: #2563eb;
        }
        .btn-copy-token {
            background-color: #6b7280;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            margin-left: 10px;
            transition: all 0.2s;
        }
        .btn-copy-token:hover {
            background-color: #4b5563;
        }
        .btn-generate-token {
            background-color: #f59e0b;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            margin-left: 10px;
            transition: all 0.2s;
        }
        .btn-generate-token:hover {
            background-color: #d97706;
        }
        .btn-cancel {
            background-color: #ef4444;
            color: white;
            margin-left: 10px;
        }
        .btn-cancel:hover {
            background-color: #dc2626;
        }
        #generate-token-form {
            margin-top: 20px;
            margin-bottom: 20px;
            border: 1px solid #e5e7eb;
            padding: 15px;
            background-color: #f9fafb;
        }
        #generate-token-form h3 {
            margin-top: 0;
            color: #1f2937;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-code"></i> MCP API Test Client</h1>
        
        <div class="panel">
            <div class="tab-buttons">
                <button class="tab-button active" onclick="openTab('test-api')">Test API</button>
                <button class="tab-button" onclick="openTab('documentation')">Dokumentacioni</button>
                <button class="tab-button" onclick="openTab('payment-data')">Të dhënat e Pagesave</button>
            </div>
            
            <div id="test-api" class="tab-content active">
                <div class="message info">
                    <strong>API Token:</strong> <?php echo $token ? substr($token, 0, 10) . '...' : 'Nuk u gjet asnjë token'; ?>
                    <?php if ($token): ?>
                        <button type="button" class="btn-test-token" onclick="testApiToken('<?php echo htmlspecialchars($token); ?>')">
                            <i class="fas fa-check-circle"></i> Testo token-in
                        </button>
                        <button type="button" class="btn-copy-token" onclick="copyTokenToClipboard('<?php echo htmlspecialchars($token); ?>')">
                            <i class="fas fa-copy"></i> Kopjo
                        </button>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['admin_id'])): ?>
                        <button type="button" class="btn-generate-token" onclick="showGenerateTokenForm()">
                            <i class="fas fa-key"></i> Gjenero token të ri
                        </button>
                    <?php endif; ?>
                </div>
                <div id="token-test-result"></div>
                
                <?php if (isset($_SESSION['admin_id'])): ?>
                <div id="generate-token-form" style="display: none;" class="panel">
                    <h3>Gjenero Token të Ri API</h3>
                    <div class="form-group">
                        <label for="token_description">Përshkrimi:</label>
                        <input type="text" id="token_description" placeholder="Token për përdorim në API">
                    </div>
                    <div class="form-group">
                        <label for="token_expiry">Data e skadimit:</label>
                        <input type="date" id="token_expiry" value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>">
                    </div>
                    <button type="button" onclick="generateNewToken()">
                        <i class="fas fa-plus-circle"></i> Gjenero
                    </button>
                    <button type="button" class="btn-cancel" onclick="hideGenerateTokenForm()">
                        <i class="fas fa-times"></i> Anulo
                    </button>
                </div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="flex">
                        <div class="col">
                            <div class="form-group">
                                <label for="endpoint">Endpoint:</label>
                                <select id="endpoint" name="endpoint" onchange="autoConfigureForEndpoint(this.value)">
                                    <option value="default" <?php echo $endpoint === 'default' ? 'selected' : ''; ?>>Default (Info)</option>
                                    <option value="payments" <?php echo $endpoint === 'payments' ? 'selected' : ''; ?>>Payments (Listë)</option>
                                    <option value="payment_details" <?php echo $endpoint === 'payment_details' ? 'selected' : ''; ?>>Payment Details (Detaje)</option>
                                    <option value="verify_payment" <?php echo $endpoint === 'verify_payment' ? 'selected' : ''; ?>>Verify Payment (Verifiko)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="method">Metoda:</label>
                                <select id="method" name="method">
                                    <option value="GET" <?php echo $method === 'GET' ? 'selected' : ''; ?>>GET</option>
                                    <option value="POST" <?php echo $method === 'POST' ? 'selected' : ''; ?>>POST</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="token">Token API (opsional):</label>
                                <input type="text" id="token" name="token" value="<?php echo htmlspecialchars($testToken ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="col">
                            <div class="form-group">
                                <label for="json_data">Të dhënat JSON (për POST/PUT):</label>
                                <textarea id="json_data" name="json_data" placeholder="{}"><?php echo htmlspecialchars($jsonData ?? '{}'); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="test_api"><i class="fas fa-paper-plane"></i> Dërgo Kërkesë</button>
                </form>
                
                <?php if (isset($errorMessage)): ?>
                    <div class="message error"><?php echo $errorMessage; ?></div>
                <?php endif; ?>
                
                <?php if ($result): ?>
                    <h2>Përgjigjja (Status: <?php echo $result['status']; ?>)</h2>
                    <div class="code-block json"><?php echo htmlspecialchars($result['raw']); ?></div>
                    
                    <?php if ($result['body']): ?>
                        <h2>Të dhënat e strukturuara:</h2>
                        <div class="code-block">
                            <?php print_r($result['body']); ?>
                        </div>
                        
                        <?php if ($result['status'] === 405): ?>
                            <div class="message error">
                                <strong>Gabim 405: Method Not Allowed</strong><br>
                                <?php if (isset($result['body']['message'])): ?>
                                    <p><?php echo htmlspecialchars($result['body']['message']); ?></p>
                                <?php else: ?>
                                    <p>Metoda <code><?php echo $method; ?></code> nuk lejohet për endpoint-in <code><?php echo $endpoint; ?></code>.</p>
                                <?php endif; ?>
                                
                                <?php if (!empty($result['body']['allowed_methods'])): ?>
                                    <p><strong>Metodat e lejuara për këtë endpoint:</strong> 
                                        <?php foreach($result['body']['allowed_methods'] as $i => $allowedMethod): ?>
                                            <code><?php echo htmlspecialchars($allowedMethod); ?></code><?php echo ($i < count($result['body']['allowed_methods']) - 1) ? ', ' : ''; ?>
                                        <?php endforeach; ?>
                                    </p>
                                <?php elseif (!empty($result['allowed_methods'])): ?>
                                    <p><strong>Metodat e lejuara për këtë endpoint:</strong> 
                                        <?php foreach($result['allowed_methods'] as $i => $allowedMethod): ?>
                                            <code><?php echo htmlspecialchars($allowedMethod); ?></code><?php echo ($i < count($result['allowed_methods']) - 1) ? ', ' : ''; ?>
                                        <?php endforeach; ?>
                                    </p>
                                <?php endif; ?>
                                
                                <br>
                                <strong>Metodat e lejuara sipas endpoint-it:</strong>
                                <ul>
                                    <li><code>default</code>: GET</li>
                                    <li><code>payments</code>: GET</li>
                                    <li><code>payment_details</code>: GET</li>
                                    <li><code>verify_payment</code>: POST</li>
                                    <li><code>generate_token</code>: POST (vetëm për admin)</li>
                                </ul>
                                <p>Ju lutemi përdorni metodën e duhur për këtë endpoint. Kliko në dokumentim për më shumë informacion.</p>
                                
                                <button type="button" class="btn-fix" onclick="fixMethodForEndpoint('<?php echo $endpoint; ?>')">
                                    <i class="fas fa-wrench"></i> Ndrysho metodën automatikisht
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <div id="documentation" class="tab-content">
                <h2>Dokumentacioni API</h2>
                <p>API e verifikimit të pagesave përdor këta endpoints:</p>
                
                <ul class="endpoint-list">
                    <li onclick="setEndpoint('default', 'GET')">
                        <strong>GET /mcp_api_new.php</strong> - Informacion për API
                        <div class="json-sample">Nuk kërkon autentifikim</div>
                        <div class="json-sample">Kthen informacion për API dhe endpoints e disponueshme</div>
                    </li>
                    <li onclick="setEndpoint('payments', 'GET')">
                        <strong>GET /mcp_api_new.php?endpoint=payments</strong> - Liston të gjitha pagesat
                        <div class="json-sample">Kërkon autentifikim me token</div>
                        <div class="json-sample">Kthen një listë të pagesave në formatin JSON</div>
                    </li>
                    <li onclick="setEndpoint('payment_details', 'GET')">
                        <strong>GET /mcp_api_new.php?endpoint=payment_details</strong> - Merr detajet e një pagese
                        <div class="json-sample">Kërkon autentifikim me token</div>
                        <div class="json-sample">Parametri URL: transaction_id</div>
                        <div class="json-sample">Kthen detajet e plota të pagesës në formatin JSON</div>
                    </li>
                    <li onclick="setEndpoint('verify_payment', 'POST', '{\n  &quot;transaction_id&quot;: &quot;TXN_123456&quot;,\n  &quot;status&quot;: &quot;verified&quot;,\n  &quot;verifier&quot;: &quot;admin&quot;\n}')">
                        <strong>POST /mcp_api_new.php?endpoint=verify_payment</strong> - Verifikon një pagesë
                        <div class="json-sample">Kërkon autentifikim me token</div>
                        <div class="json-sample">Trupi: {"transaction_id": "TXN_ID", "status": "verified|rejected|pending", "verifier": "user"}</div>
                        <div class="json-sample">Kthen rezultatin e veprimit të verifikimit</div>
                    </li>
                </ul>
                
                <h2>Autentifikimi</h2>
                <p>API përdor autentifikimin me token. Vendosni headerin e mëposhtëm në kërkesat tuaja:</p>
                <div class="code-block">
Authorization: Bearer TOKENI_YT
                </div>
                
                <h2>Kodet e përgjigjes</h2>
                <ul>
                    <li><strong>200</strong> - Kërkesa u krye me sukses</li>
                    <li><strong>400</strong> - Kërkesë e pavlefshme (mungojnë të dhënat ose janë të pasakta)</li>
                    <li><strong>401</strong> - Autentifikim i pavlefshëm (token i gabuar ose mungon)</li>
                    <li><strong>404</strong> - Burim i pagjetur (endpoint ose transaksion i panjohur)</li>
                    <li><strong>405</strong> - Metoda nuk lejohet (HTTP metoda e gabuar për këtë endpoint)</li>
                    <li><strong>500</strong> - Gabim serveri (probleme në përpunimin e kërkesës)</li>
                </ul>
                
                <h2>Trajtimi i gabimeve</h2>
                <p>Të gjitha përgjigjet e gabimit kthehen në formatin JSON me një fushë <code>error</code> që përshkruan problemin. 
                Për gabimet 405 (Method Not Allowed), API kthen edhe një fushë <code>allowed_methods</code> që tregon metodat e lejuara 
                për atë endpoint dhe një <code>message</code> me sugjerim për përmirësimin e kërkesës.</p>
                
                <div class="code-block">
{
  "error": "Method not allowed",
  "allowed_methods": ["GET"],
  "message": "This endpoint requires GET method instead of POST."
}
                </div>
                
                <p>Për të gjeneruar një token të ri, vizitoni <a href="token_generator.php">Token Generator</a> ose përdorni funksionin <code>generate_token</code> nga API (vetëm për administratorët).</p>
            </div>
            
            <div id="payment-data" class="tab-content">
                <h2>Të dhënat e pagesave në sistem</h2>
                
                <?php if (isset($paymentError)): ?>
                    <div class="message error"><?php echo $paymentError; ?></div>
                <?php else: ?>
                    <?php if (empty($payments)): ?>
                        <div class="message info">Nuk ka të dhëna pagese në sistem.</div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID e Transaksionit</th>
                                    <th>Emri i Zyrës</th>
                                    <th>Shuma</th>
                                    <th>Statusi</th>
                                    <th>Veprime</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payment['transaction_id']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['office_name']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['payment_amount']); ?> €</td>
                                        <td>
                                            <?php 
                                                $status = htmlspecialchars($payment['verification_status']);
                                                $statusClass = 'badge-pending';
                                                if ($status === 'verified') $statusClass = 'badge-verified';
                                                if ($status === 'rejected') $statusClass = 'badge-rejected';
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>"><?php echo $status; ?></span>
                                        </td>
                                        <td>
                                            <button type="button" onclick="viewPaymentDetails('<?php echo htmlspecialchars($payment['transaction_id']); ?>')">
                                                <i class="fas fa-eye"></i> Shiko
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function openTab(tabId) {
            const tabContents = document.getElementsByClassName('tab-content');
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }
            
            const tabButtons = document.getElementsByClassName('tab-button');
            for (let i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove('active');
            }
            
            document.getElementById(tabId).classList.add('active');
            event.currentTarget.classList.add('active');
        }
        
        function setEndpoint(endpoint, method, jsonData = '{}') {
            document.getElementById('endpoint').value = endpoint;
            document.getElementById('method').value = method;
            document.getElementById('json_data').value = jsonData;
            
            // Shko te tabi i testimit
            openTab('test-api');
            
            // Zhvendos në krye të ekranit për vëmendje të përdoruesit
            window.scrollTo(0, 0);
        }
        
        function viewPaymentDetails(transactionId) {
            setEndpoint('payment_details', 'GET');
            document.getElementById('json_data').value = JSON.stringify({
                transaction_id: transactionId
            }, null, 2);
        }
        
        // Funksion për të ndryshuar metodën në përputhje me endpoint-in
        function fixMethodForEndpoint(endpoint) {
            let correctMethod = 'GET'; // Default metoda
            
            // Cakto metodën e duhur sipas endpoint-it
            switch (endpoint) {
                case 'default':
                case 'payments':
                case 'payment_details':
                    correctMethod = 'GET';
                    break;
                case 'verify_payment':
                case 'generate_token':
                    correctMethod = 'POST';
                    break;
            }
            
            // Përditëso metodën
            document.getElementById('method').value = correctMethod;
            
            // Shto të dhëna shembull për endpoint specifike
            if (endpoint === 'payment_details' && correctMethod === 'GET') {
                document.getElementById('json_data').value = JSON.stringify({
                    transaction_id: "TXN_20250922_225911_972321bc"
                }, null, 2);
            }
            else if (endpoint === 'verify_payment' && correctMethod === 'POST') {
                document.getElementById('json_data').value = JSON.stringify({
                    transaction_id: "TXN_20250922_225911_972321bc",
                    status: "verified",
                    verifier: "admin"
                }, null, 2);
            }
            
            alert('Metoda është ndryshuar në ' + correctMethod + ' për endpoint-in ' + endpoint + '. Tani mund të dërgoni kërkesën.');
        }
        
        // Funksion për të konfiguruar automatikisht metodën dhe të dhënat sipas endpoint-it
        function autoConfigureForEndpoint(endpoint) {
            let methodSelect = document.getElementById('method');
            let jsonData = document.getElementById('json_data');
            
            // Përcakto metodën dhe të dhënat sipas endpoint-it
            switch (endpoint) {
                case 'default':
                    methodSelect.value = 'GET';
                    jsonData.value = '{}';
                    showTooltip('Ky endpoint kthen informacion të përgjithshëm për API-në.');
                    break;
                    
                case 'payments':
                    methodSelect.value = 'GET';
                    jsonData.value = '{}';
                    showTooltip('Ky endpoint kthen listën e të gjitha pagesave. Kërkon autentifikim me token.');
                    break;
                    
                case 'payment_details':
                    methodSelect.value = 'GET';
                    jsonData.value = JSON.stringify({
                        transaction_id: "TXN_20250922_225911_972321bc"
                    }, null, 2);
                    showTooltip('Për kërkesë GET, parametrat do të shtohen në URL. Kërkon autentifikim me token.');
                    break;
                    
                case 'verify_payment':
                    methodSelect.value = 'POST';
                    jsonData.value = JSON.stringify({
                        transaction_id: "TXN_20250922_225911_972321bc",
                        status: "verified",
                        verifier: "admin"
                    }, null, 2);
                    showTooltip('Verifikon statusin e pagesës. Statuset e vlefshme janë "verified", "rejected", "pending".');
                    break;
                    
                case 'generate_token':
                    methodSelect.value = 'POST';
                    jsonData.value = JSON.stringify({
                        description: "Token i ri për testim",
                        expiry: new Date(Date.now() + 365*24*60*60*1000).toISOString().slice(0, 19).replace('T', ' ')
                    }, null, 2);
                    showTooltip('Gjeneron një token të ri API. Kërkon sesion administratori për t\'u ekzekutuar.');
                    break;
            }
        }
        
        // Shfaq një tooltip informues për përdoruesin
        function showTooltip(message) {
            let tooltip = document.createElement('div');
            tooltip.className = 'message info';
            tooltip.innerHTML = `<i class="fas fa-info-circle"></i> ${message}`;
            
            // Shto tooltip para butonit të dërgimit
            let form = document.querySelector('form button[type="submit"]').parentNode;
            
            // Hiq tooltip-in e mëparshëm nëse ekziston
            let existingTooltip = form.querySelector('.message.info:not(:first-child)');
            if (existingTooltip) {
                form.removeChild(existingTooltip);
            }
            
            form.insertBefore(tooltip, form.lastChild);
            
            // Fshij tooltip pas 5 sekondash
            setTimeout(() => {
                if (tooltip.parentNode) {
                    tooltip.parentNode.removeChild(tooltip);
                }
            }, 5000);
        }
        
        // Funksion për të testuar vlefshmërinë e token-it
        function testApiToken(token) {
            // Tregues që jemi duke pritur
            document.getElementById('token-test-result').innerHTML = `
                <div class="message info">
                    <i class="fas fa-spinner fa-spin"></i> Po kontrollohet token-i...
                </div>
            `;
            
            // Krijojmë një objekt FormData për thirrjen e API
            const formData = new FormData();
            formData.append('test_api', '1');
            formData.append('endpoint', 'payments');
            formData.append('method', 'GET');
            formData.append('token', token);
            formData.append('json_data', '{}');
            
            // Dërgojmë një kërkesë POST për të testuar token-in
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Kontrollojmë përgjigjen për të parë nëse token është i vlefshëm
                let isValid = html.includes('"status":200') && !html.includes('"error":"Unauthorized access"');
                
                if (isValid) {
                    document.getElementById('token-test-result').innerHTML = `
                        <div class="message success">
                            <i class="fas fa-check-circle"></i> Token-i është i vlefshëm dhe funksional!
                        </div>
                    `;
                } else {
                    document.getElementById('token-test-result').innerHTML = `
                        <div class="message error">
                            <i class="fas fa-times-circle"></i> Token-i nuk është i vlefshëm ose ka skaduar.
                        </div>
                    `;
                }
                
                // Fshij mesazhin pas 5 sekondash
                setTimeout(() => {
                    document.getElementById('token-test-result').innerHTML = '';
                }, 5000);
            })
            .catch(error => {
                document.getElementById('token-test-result').innerHTML = `
                    <div class="message error">
                        <i class="fas fa-exclamation-triangle"></i> Gabim gjatë testimit të token-it: ${error.message}
                    </div>
                `;
            });
        }
        
        // Funksioni për të kopjuar token-in në clipboard
        function copyTokenToClipboard(token) {
            navigator.clipboard.writeText(token).then(function() {
                showTooltip('Token-i u kopjua në clipboard!');
            }, function() {
                showTooltip('Nuk u arrit të kopjohet token-i. Ju lutemi provoni manualisht.');
            });
        }
        
        // Shfaq formularin për gjenerimin e token-it
        function showGenerateTokenForm() {
            document.getElementById('generate-token-form').style.display = 'block';
        }
        
        // Fsheh formularin për gjenerimin e token-it
        function hideGenerateTokenForm() {
            document.getElementById('generate-token-form').style.display = 'none';
        }
        
        // Funksion për të gjeneruar një token të ri API
        function generateNewToken() {
            const description = document.getElementById('token_description').value;
            const expiry = document.getElementById('token_expiry').value;
            
            // Tregues që jemi duke pritur
            document.getElementById('token-test-result').innerHTML = `
                <div class="message info">
                    <i class="fas fa-spinner fa-spin"></i> Po gjenerohet token-i i ri...
                </div>
            `;
            
            // Dërgojmë një kërkesë API për të gjeneruar token-in
            const formData = new FormData();
            formData.append('test_api', '1');
            formData.append('endpoint', 'generate_token');
            formData.append('method', 'POST');
            formData.append('json_data', JSON.stringify({
                description: description,
                expiry: expiry ? new Date(expiry).toISOString().slice(0, 19).replace('T', ' ') : null
            }));
            
            // Dërgojmë kërkesën
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                try {
                    // Kontrollojmë përgjigjen për të parë nëse token-i u gjenerua me sukses
                    if (html.includes('"success":true') && html.includes('"token":')) {
                        // Gjejmë token-in në përgjigje
                        const match = html.match(/"token":"([^"]+)"/);
                        if (match && match[1]) {
                            const newToken = match[1];
                            document.getElementById('token-test-result').innerHTML = `
                                <div class="message success">
                                    <i class="fas fa-check-circle"></i> Token-i i ri u gjenerua me sukses!<br>
                                    <strong>Token-i i ri:</strong> ${newToken}<br><br>
                                    <button type="button" class="btn-copy-token" onclick="copyTokenToClipboard('${newToken}')">
                                        <i class="fas fa-copy"></i> Kopjo token-in
                                    </button>
                                </div>
                            `;
                            
                            // Fshehim formularin e gjenerimit
                            hideGenerateTokenForm();
                            
                            // Përditësojmë tokenin në formë për testim
                            document.getElementById('token').value = newToken;
                            
                            // Rifreskojmë faqen pas 3 sekondash për të treguar token-in e ri
                            setTimeout(() => {
                                window.location.reload();
                            }, 3000);
                        } else {
                            throw new Error('Token-i nuk u gjend në përgjigje');
                        }
                    } else if (html.includes('error')) {
                        const match = html.match(/"error":"([^"]+)"/);
                        const errorMessage = match ? match[1] : 'Gabim i panjohur';
                        throw new Error(errorMessage);
                    } else {
                        throw new Error('Përgjigje e papritur nga serveri');
                    }
                } catch (e) {
                    document.getElementById('token-test-result').innerHTML = `
                        <div class="message error">
                            <i class="fas fa-times-circle"></i> Gabim gjatë gjenerimit të token-it: ${e.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                document.getElementById('token-test-result').innerHTML = `
                    <div class="message error">
                        <i class="fas fa-exclamation-triangle"></i> Gabim gjatë gjenerimit të token-it: ${error.message}
                    </div>
                `;
            });
        }
    </script>
</body>
</html>