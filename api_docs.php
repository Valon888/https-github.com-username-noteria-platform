<?php
// api_docs.php - Dokumentimi i API për zhvilluesit
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';
session_start();

// Kontrollo nëse përdoruesi është i autentifikuar
$needLogin = true;

// Kontrollo nëse është kërkuar qasja publike në dokumentim
if (isset($_GET['public']) && $_GET['public'] === 'true') {
    $needLogin = false;
}

// Nëse përdoruesi nuk është i autentifikuar dhe dokumentimi nuk është publik, ridrejto tek login
if ($needLogin && !isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Kontrollo nëse është kërkuar vetëm një pjesë e caktuar e dokumentimit (për AJAX)
$section = isset($_GET['section']) ? $_GET['section'] : '';

// Struktura e API-t
$apiStructure = [
    'authentication' => [
        'title' => 'Autentifikimi',
        'description' => 'Të gjitha kërkesat API duhet të autentifikohen duke përdorur një token API. Token-at mund të gjenerohen duke përdorur <a href="token_generator.php">Token Generator</a>.',
        'examples' => [
            [
                'title' => 'Autentifikimi me token',
                'code' => 'GET /mcp_api_new.php?endpoint=payments HTTP/1.1
Host: yourdomain.com
Authorization: Bearer YOUR_TOKEN_HERE
Content-Type: application/json'
            ],
            [
                'title' => 'Kërkesë cURL me token',
                'code' => 'curl -X GET "http://yourdomain.com/mcp_api_new.php?endpoint=payments" \\
-H "Authorization: Bearer YOUR_TOKEN_HERE" \\
-H "Content-Type: application/json"'
            ]
        ]
    ],
    'errors' => [
        'title' => 'Kodet e gabimeve',
        'description' => 'API përdor kodet standarde HTTP për të treguar suksesin ose dështimin e një kërkese API.',
        'table' => [
            'headers' => ['Kodi i statusit', 'Emri', 'Përshkrimi'],
            'rows' => [
                ['200', 'OK', 'Kërkesa u përpunua me sukses.'],
                ['400', 'Bad Request', 'Kërkesa nuk ishte e vlefshme. Kontrollo parametrat.'],
                ['401', 'Unauthorized', 'Mungon token-i i autentifikimit ose është i pavlefshëm.'],
                ['404', 'Not Found', 'Resursi i kërkuar nuk u gjet.'],
                ['405', 'Method Not Allowed', 'Metoda HTTP e përdorur nuk lejohet për këtë endpoint.'],
                ['500', 'Internal Server Error', 'Gabim i brendshëm i serverit.']
            ]
        ]
    ],
    'endpoints' => [
        'title' => 'Pikat fundore (Endpoints)',
        'description' => 'API ofron disa pika fundore për të aksesuar dhe menaxhuar të dhënat e aplikacionit.',
        'endpoints' => [
            [
                'name' => 'payments',
                'description' => 'Menaxhimi i pagesave dhe transaksioneve',
                'methods' => [
                    'GET' => [
                        'description' => 'Merr listën e pagesave',
                        'parameters' => [
                            ['name' => 'id', 'type' => 'integer', 'optional' => true, 'description' => 'ID e pagesës specifike për filtrim'],
                            ['name' => 'status', 'type' => 'string', 'optional' => true, 'description' => 'Filtro pagesat sipas statusit (completed, pending, failed)'],
                            ['name' => 'date_from', 'type' => 'date', 'optional' => true, 'description' => 'Filtro pagesat nga një datë e caktuar (YYYY-MM-DD)'],
                            ['name' => 'date_to', 'type' => 'date', 'optional' => true, 'description' => 'Filtro pagesat deri në një datë të caktuar (YYYY-MM-DD)']
                        ],
                        'example_request' => 'GET /mcp_api_new.php?endpoint=payments&status=completed HTTP/1.1
Host: yourdomain.com
Authorization: Bearer YOUR_TOKEN_HERE
Content-Type: application/json',
                        'example_response' => '{
    "status": 200,
    "data": [
        {
            "id": 1,
            "amount": 50.00,
            "currency": "EUR",
            "status": "completed",
            "date": "2023-07-15 14:30:22",
            "user_id": 42
        },
        {
            "id": 2,
            "amount": 75.00,
            "currency": "EUR",
            "status": "completed",
            "date": "2023-07-16 09:15:47",
            "user_id": 28
        }
    ]
}'
                    ],
                    'POST' => [
                        'description' => 'Krijo një pagesë të re',
                        'parameters' => [
                            ['name' => 'amount', 'type' => 'float', 'optional' => false, 'description' => 'Shuma e pagesës'],
                            ['name' => 'currency', 'type' => 'string', 'optional' => false, 'description' => 'Valuta e pagesës (EUR, USD, etj.)'],
                            ['name' => 'user_id', 'type' => 'integer', 'optional' => false, 'description' => 'ID e përdoruesit që po kryen pagesën'],
                            ['name' => 'description', 'type' => 'string', 'optional' => true, 'description' => 'Përshkrimi i pagesës']
                        ],
                        'example_request' => 'POST /mcp_api_new.php?endpoint=payments HTTP/1.1
Host: yourdomain.com
Authorization: Bearer YOUR_TOKEN_HERE
Content-Type: application/json

{
    "amount": 100.00,
    "currency": "EUR",
    "user_id": 42,
    "description": "Pagesa për shërbimin X"
}',
                        'example_response' => '{
    "status": 200,
    "message": "Payment created successfully",
    "data": {
        "payment_id": 3,
        "status": "pending"
    }
}'
                    ],
                    'PUT' => [
                        'description' => 'Përditëso statusin e një pagese ekzistuese',
                        'parameters' => [
                            ['name' => 'id', 'type' => 'integer', 'optional' => false, 'description' => 'ID e pagesës për të përditësuar'],
                            ['name' => 'status', 'type' => 'string', 'optional' => false, 'description' => 'Statusi i ri i pagesës (completed, pending, failed)'],
                            ['name' => 'notes', 'type' => 'string', 'optional' => true, 'description' => 'Shënime shtesë për përditësimin']
                        ],
                        'example_request' => 'PUT /mcp_api_new.php?endpoint=payments HTTP/1.1
Host: yourdomain.com
Authorization: Bearer YOUR_TOKEN_HERE
Content-Type: application/json

{
    "id": 3,
    "status": "completed",
    "notes": "Pagesa u procesua me sukses"
}',
                        'example_response' => '{
    "status": 200,
    "message": "Payment updated successfully",
    "data": {
        "payment_id": 3,
        "status": "completed"
    }
}'
                    ]
                ]
            ],
            [
                'name' => 'verify_payment',
                'description' => 'Verifikimi i pagesave dhe statusit të tyre',
                'methods' => [
                    'GET' => [
                        'description' => 'Kontrollo statusin e një pagese specifike',
                        'parameters' => [
                            ['name' => 'payment_id', 'type' => 'integer', 'optional' => false, 'description' => 'ID e pagesës për të kontrolluar']
                        ],
                        'example_request' => 'GET /mcp_api_new.php?endpoint=verify_payment&payment_id=3 HTTP/1.1
Host: yourdomain.com
Authorization: Bearer YOUR_TOKEN_HERE',
                        'example_response' => '{
    "status": 200,
    "data": {
        "payment_id": 3,
        "verified": true,
        "status": "completed",
        "amount": 100.00,
        "date_processed": "2023-07-16 15:42:18"
    }
}'
                    ],
                    'POST' => [
                        'description' => 'Verifiko një pagesë të re',
                        'parameters' => [
                            ['name' => 'transaction_id', 'type' => 'string', 'optional' => false, 'description' => 'ID e transaksionit për verifikim'],
                            ['name' => 'amount', 'type' => 'float', 'optional' => false, 'description' => 'Shuma e pagesës për verifikim'],
                            ['name' => 'provider', 'type' => 'string', 'optional' => true, 'description' => 'Ofruesi i pagesës (paysera, stripe, etj.)']
                        ],
                        'example_request' => 'POST /mcp_api_new.php?endpoint=verify_payment HTTP/1.1
Host: yourdomain.com
Authorization: Bearer YOUR_TOKEN_HERE
Content-Type: application/json

{
    "transaction_id": "TRX12345",
    "amount": 100.00,
    "provider": "paysera"
}',
                        'example_response' => '{
    "status": 200,
    "data": {
        "transaction_id": "TRX12345",
        "verified": true,
        "internal_payment_id": 42,
        "status": "completed"
    }
}'
                    ]
                ]
            ],
            [
                'name' => 'users',
                'description' => 'Menaxhimi i përdoruesve',
                'methods' => [
                    'GET' => [
                        'description' => 'Merr listën e përdoruesve ose një përdorues specifik',
                        'parameters' => [
                            ['name' => 'id', 'type' => 'integer', 'optional' => true, 'description' => 'ID e përdoruesit specifik']
                        ],
                        'example_request' => 'GET /mcp_api_new.php?endpoint=users&id=42 HTTP/1.1
Host: yourdomain.com
Authorization: Bearer YOUR_TOKEN_HERE',
                        'example_response' => '{
    "status": 200,
    "data": {
        "id": 42,
        "name": "John Doe",
        "email": "john.doe@example.com",
        "created_at": "2023-01-15 09:30:00"
    }
}'
                    ]
                ]
            ]
        ]
    ],
    'client_code' => [
        'title' => 'Kodi i klientit',
        'description' => 'Shembuj kodi për të thirrur API nga gjuhë të ndryshme programimi.',
        'code_examples' => [
            [
                'language' => 'PHP',
                'title' => 'Kërkesë me PHP',
                'code' => '<?php
// Shembull thirrje API me PHP
$token = "YOUR_API_TOKEN_HERE";
$apiUrl = "https://yourdomain.com/mcp_api_new.php?endpoint=payments";

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $token,
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
if (curl_errno($ch)) {
    echo "Error: " . curl_error($ch);
} else {
    $data = json_decode($response, true);
    print_r($data);
}
curl_close($ch);'
            ],
            [
                'language' => 'JavaScript',
                'title' => 'Kërkesë me JavaScript (fetch)',
                'code' => 'const apiUrl = "https://yourdomain.com/mcp_api_new.php?endpoint=payments";
const token = "YOUR_API_TOKEN_HERE";

fetch(apiUrl, {
    method: "GET",
    headers: {
        "Authorization": `Bearer ${token}`,
        "Content-Type": "application/json"
    }
})
.then(response => response.json())
.then(data => {
    console.log(data);
})
.catch(error => {
    console.error("Error:", error);
});'
            ],
            [
                'language' => 'Python',
                'title' => 'Kërkesë me Python (requests)',
                'code' => 'import requests

api_url = "https://yourdomain.com/mcp_api_new.php?endpoint=payments"
token = "YOUR_API_TOKEN_HERE"

headers = {
    "Authorization": f"Bearer {token}",
    "Content-Type": "application/json"
}

response = requests.get(api_url, headers=headers)
data = response.json()
print(data)'
            ]
        ]
    ],
    'testing' => [
        'title' => 'Testimi i API-t',
        'description' => 'Mjete për të testuar API-n dhe gjeneruar token-a për zhvillim dhe testim.',
        'tools' => [
            [
                'name' => 'API Client Test',
                'url' => 'api_client_test.php',
                'description' => 'Një ndërfaqe e thjeshtë për të testuar pikat fundore të API-t me metoda të ndryshme HTTP.'
            ],
            [
                'name' => 'Token Generator',
                'url' => 'token_generator.php',
                'description' => 'Krijoni dhe menaxhoni token-at API për zhvillim dhe testim.'
            ],
            [
                'name' => 'API Debug',
                'url' => 'api_debug.php',
                'description' => 'Një mjet i avancuar për diagnostikim dhe testim të automatizuar të API-t.'
            ]
        ]
    ]
];

// Kontrollo nëse kërkesa është për një seksion të veçantë (për AJAX)
if (!empty($section) && isset($apiStructure[$section])) {
    header('Content-Type: application/json');
    echo json_encode($apiStructure[$section]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Dokumentimi | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Highlight.js për ngjyrosjen e kodit -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/atom-one-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js"></script>
    <script>hljs.highlightAll();</script>
    <style>
        :root {
            --primary-color: #1a56db;
            --primary-hover: #1e40af;
            --secondary-color: #6b7280;
            --light-bg: #f8fafc;
            --border-color: #e2e8f0;
            --text-color: #374151;
            --heading-color: #1e293b;
            --code-bg: #1e293b;
            --code-text: #e2e8f0;
            --success-color: #16a34a;
            --error-color: #dc2626;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            margin: 0;
            padding: 0;
            background-color: var(--light-bg);
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 280px;
            background-color: white;
            border-right: 1px solid var(--border-color);
            padding: 20px 0;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .sidebar-header h2 {
            color: var(--primary-color);
            display: flex;
            align-items: center;
            font-size: 1.5rem;
        }
        
        .sidebar-header h2 i {
            margin-right: 10px;
        }
        
        .sidebar-nav {
            list-style: none;
            padding: 20px 0;
        }
        
        .sidebar-nav li {
            padding: 0;
            margin: 0;
        }
        
        .sidebar-nav a {
            padding: 12px 20px;
            display: block;
            color: var(--text-color);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            transition: all 0.2s;
        }
        
        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background-color: rgba(26, 86, 219, 0.1);
            color: var(--primary-color);
            border-left: 4px solid var(--primary-color);
        }
        
        .sidebar-nav a i {
            margin-right: 10px;
            width: 24px;
            text-align: center;
        }
        
        .main-content {
            flex: 1;
            padding: 40px;
            margin-left: 280px;
        }
        
        .panel {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        h1, h2, h3, h4 {
            color: var(--heading-color);
            margin-bottom: 20px;
        }
        
        h1 {
            font-size: 2rem;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        
        h2 {
            font-size: 1.6rem;
            margin-top: 40px;
        }
        
        h3 {
            font-size: 1.3rem;
            margin-top: 30px;
            color: var(--primary-color);
        }
        
        h4 {
            font-size: 1.1rem;
            margin-top: 20px;
        }
        
        p {
            margin-bottom: 20px;
        }
        
        a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        a:hover {
            text-decoration: underline;
        }
        
        .code-block {
            background-color: var(--code-bg);
            color: var(--code-text);
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            margin: 15px 0 25px;
            position: relative;
        }
        
        .code-block pre {
            margin: 0;
            padding: 0;
            font-family: 'Courier New', Courier, monospace;
            white-space: pre-wrap;
        }
        
        .code-block .language-label {
            position: absolute;
            top: 0;
            right: 0;
            background-color: rgba(0, 0, 0, 0.4);
            padding: 4px 8px;
            border-radius: 0 6px 0 6px;
            font-size: 0.8rem;
            color: #ddd;
        }
        
        .copy-btn {
            position: absolute;
            bottom: 8px;
            right: 8px;
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 5px 10px;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            opacity: 0.7;
            transition: opacity 0.2s;
        }
        
        .copy-btn:hover {
            opacity: 1;
        }
        
        .copy-btn i {
            margin-right: 5px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        th, td {
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            text-align: left;
        }
        
        th {
            background-color: #f1f5f9;
            font-weight: 600;
        }
        
        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        
        .method-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            color: white;
            font-size: 0.8rem;
            font-weight: bold;
            margin-right: 10px;
            min-width: 60px;
            text-align: center;
        }
        
        .get { background-color: #22c55e; }
        .post { background-color: #3b82f6; }
        .put { background-color: #f59e0b; }
        .delete { background-color: #ef4444; }
        .patch { background-color: #8b5cf6; }
        
        .endpoint {
            margin-bottom: 50px;
            padding-bottom: 30px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .endpoint:last-child {
            border-bottom: none;
        }
        
        .endpoint-header {
            background-color: #f1f5f9;
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 25px;
            border-left: 5px solid var(--primary-color);
        }
        
        .endpoint-title {
            font-size: 1.3rem;
            margin: 0;
            color: var(--primary-color);
        }
        
        .endpoint-description {
            margin: 10px 0 0;
            color: var(--text-color);
        }
        
        .method {
            margin-bottom: 40px;
        }
        
        .parameters-title {
            font-weight: 600;
            margin: 20px 0 10px;
        }
        
        .parameter-required {
            color: #ef4444;
            font-weight: bold;
        }
        
        .parameter-optional {
            color: #6b7280;
            font-style: italic;
        }
        
        .parameter-type {
            color: #8b5cf6;
            font-family: monospace;
            background-color: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
        }
        
        .tool-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .tool-icon {
            font-size: 2rem;
            color: var(--primary-color);
            margin-right: 20px;
        }
        
        .tool-info {
            flex: 1;
        }
        
        .tool-title {
            font-size: 1.2rem;
            margin: 0 0 10px;
        }
        
        .tool-description {
            margin: 0;
            color: var(--secondary-color);
        }
        
        .tool-link {
            background-color: var(--primary-color);
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        
        .tool-link:hover {
            background-color: var(--primary-hover);
            text-decoration: none;
        }
        
        @media (max-width: 1024px) {
            .sidebar {
                width: 240px;
            }
            
            .main-content {
                margin-left: 240px;
                padding: 30px;
            }
        }
        
        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                position: static;
                padding: 10px 0;
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .sidebar-header h2 {
                font-size: 1.3rem;
            }
            
            .sidebar-nav a {
                padding: 10px 15px;
            }
        }
        
        .back-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 100;
            opacity: 0;
            transition: opacity 0.3s;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }
        
        .back-to-top.visible {
            opacity: 1;
        }
        
        .back-to-top:hover {
            background-color: var(--primary-hover);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-code"></i> API Docs</h2>
        </div>
        
        <ul class="sidebar-nav">
            <li><a href="#overview"><i class="fas fa-home"></i> Përmbledhje</a></li>
            <li><a href="#authentication"><i class="fas fa-key"></i> Autentifikimi</a></li>
            <li><a href="#errors"><i class="fas fa-exclamation-triangle"></i> Kodet e gabimeve</a></li>
            <li><a href="#endpoints"><i class="fas fa-link"></i> Endpoints</a></li>
            <li><a href="#client_code"><i class="fas fa-laptop-code"></i> Kodi i klientit</a></li>
            <li><a href="#testing"><i class="fas fa-flask"></i> Testimi i API-t</a></li>
        </ul>
        
        <div style="padding: 20px;">
            <a href="api_client_test.php" class="tool-link" style="width: 100%; text-align: center;">
                <i class="fas fa-flask"></i> Test API
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="panel">
            <h1 id="overview">Dokumentimi i API | Noteria</h1>
            
            <p>Mirë se vini në dokumentimin e API-t të Noteria. Ky dokumentim ofron informacionet e nevojshme për të përdorur API-n tonë për të integruar funksionalitetet e Noteria në aplikimet tuaja.</p>
            
            <h2>Përmbledhje</h2>
            <p>API i Noteria është ndërtuar duke përdorur parimet RESTful dhe ofron një sërë pikash fundore (endpoints) për të aksesuar dhe menaxhuar të dhënat e aplikacionit. Të gjitha kërkesat dhe përgjigjet janë në formatin JSON.</p>
            
            <p>URL bazë e API-t është:</p>
            <div class="code-block">
                <pre>https://yourdomain.com/mcp_api_new.php</pre>
            </div>
            
            <h3>Si të përdorni API-n</h3>
            <ol>
                <li>Sigurohuni që keni një token API të vlefshëm (shih <a href="#authentication">Autentifikimi</a>).</li>
                <li>Zgjidhni endpoint-in e duhur për nevojat tuaja.</li>
                <li>Dërgoni kërkesën duke përdorur metodën e duhur HTTP (GET, POST, PUT, DELETE).</li>
                <li>Përpunoni përgjigjen JSON.</li>
            </ol>
            
            <h2 id="authentication">Autentifikimi</h2>
            <p><?php echo $apiStructure['authentication']['description']; ?></p>
            
            <?php foreach ($apiStructure['authentication']['examples'] as $example): ?>
                <h4><?php echo $example['title']; ?></h4>
                <div class="code-block">
                    <pre><?php echo htmlspecialchars($example['code']); ?></pre>
                    <button class="copy-btn" onclick="copyCode(this)">
                        <i class="fas fa-copy"></i> Kopjo
                    </button>
                </div>
            <?php endforeach; ?>
            
            <h2 id="errors">Kodet e gabimeve</h2>
            <p><?php echo $apiStructure['errors']['description']; ?></p>
            
            <table>
                <thead>
                    <tr>
                        <?php foreach ($apiStructure['errors']['table']['headers'] as $header): ?>
                            <th><?php echo $header; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($apiStructure['errors']['table']['rows'] as $row): ?>
                        <tr>
                            <td><strong><?php echo $row[0]; ?></strong></td>
                            <td><?php echo $row[1]; ?></td>
                            <td><?php echo $row[2]; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2 id="endpoints">Pikat fundore (Endpoints)</h2>
            <p><?php echo $apiStructure['endpoints']['description']; ?></p>
            
            <?php foreach ($apiStructure['endpoints']['endpoints'] as $endpoint): ?>
                <div class="endpoint">
                    <div class="endpoint-header">
                        <h3 id="endpoint-<?php echo $endpoint['name']; ?>" class="endpoint-title">
                            <?php echo $endpoint['name']; ?>
                        </h3>
                        <p class="endpoint-description"><?php echo $endpoint['description']; ?></p>
                    </div>
                    
                    <?php foreach ($endpoint['methods'] as $method => $details): ?>
                        <div class="method">
                            <h4>
                                <span class="method-badge <?php echo strtolower($method); ?>"><?php echo $method; ?></span>
                                <?php echo $details['description']; ?>
                            </h4>
                            
                            <?php if (!empty($details['parameters'])): ?>
                                <div class="parameters-title">Parametrat:</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Emri</th>
                                            <th>Tipi</th>
                                            <th>I detyrueshëm</th>
                                            <th>Përshkrimi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($details['parameters'] as $param): ?>
                                            <tr>
                                                <td><?php echo $param['name']; ?></td>
                                                <td><span class="parameter-type"><?php echo $param['type']; ?></span></td>
                                                <td>
                                                    <?php if ($param['optional']): ?>
                                                        <span class="parameter-optional">Jo</span>
                                                    <?php else: ?>
                                                        <span class="parameter-required">Po</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $param['description']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                            
                            <h4>Shembull kërkese:</h4>
                            <div class="code-block">
                                <pre><?php echo htmlspecialchars($details['example_request']); ?></pre>
                                <span class="language-label">HTTP</span>
                                <button class="copy-btn" onclick="copyCode(this)">
                                    <i class="fas fa-copy"></i> Kopjo
                                </button>
                            </div>
                            
                            <h4>Shembull përgjigje:</h4>
                            <div class="code-block">
                                <pre><?php echo htmlspecialchars($details['example_response']); ?></pre>
                                <span class="language-label">JSON</span>
                                <button class="copy-btn" onclick="copyCode(this)">
                                    <i class="fas fa-copy"></i> Kopjo
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            
            <h2 id="client_code">Kodi i klientit</h2>
            <p><?php echo $apiStructure['client_code']['description']; ?></p>
            
            <?php foreach ($apiStructure['client_code']['code_examples'] as $example): ?>
                <h3><?php echo $example['title']; ?></h3>
                <div class="code-block">
                    <pre><code class="language-<?php echo strtolower($example['language']); ?>"><?php echo htmlspecialchars($example['code']); ?></code></pre>
                    <span class="language-label"><?php echo $example['language']; ?></span>
                    <button class="copy-btn" onclick="copyCode(this)">
                        <i class="fas fa-copy"></i> Kopjo
                    </button>
                </div>
            <?php endforeach; ?>
            
            <h2 id="testing">Testimi i API-t</h2>
            <p><?php echo $apiStructure['testing']['description']; ?></p>
            
            <?php foreach ($apiStructure['testing']['tools'] as $tool): ?>
                <div class="tool-card">
                    <div class="tool-icon">
                        <i class="fas <?php echo $tool['name'] === 'API Client Test' ? 'fa-flask' : ($tool['name'] === 'Token Generator' ? 'fa-key' : 'fa-bug'); ?>"></i>
                    </div>
                    <div class="tool-info">
                        <h4 class="tool-title"><?php echo $tool['name']; ?></h4>
                        <p class="tool-description"><?php echo $tool['description']; ?></p>
                        <a href="<?php echo $tool['url']; ?>" class="tool-link">Hap mjeti</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Back to top button -->
    <div class="back-to-top" onclick="scrollToTop()">
        <i class="fas fa-arrow-up"></i>
    </div>
    
    <script>
        // Highlight active section in sidebar
        const sections = document.querySelectorAll('h2[id], h3[id]');
        const navItems = document.querySelectorAll('.sidebar-nav a');
        
        // Back to top button
        window.addEventListener('scroll', function() {
            const backToTopButton = document.querySelector('.back-to-top');
            if (window.scrollY > 300) {
                backToTopButton.classList.add('visible');
            } else {
                backToTopButton.classList.remove('visible');
            }
        });
        
        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }
        
        // Update active menu item on scroll
        window.addEventListener('scroll', function() {
            let current = '';
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop - 100;
                if (scrollY >= sectionTop) {
                    current = '#' + section.getAttribute('id');
                }
            });
            
            navItems.forEach(item => {
                item.classList.remove('active');
                if (item.getAttribute('href') === current) {
                    item.classList.add('active');
                }
            });
        });
        
        // Smooth scroll for anchor links
        document.querySelectorAll('.sidebar-nav a').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                window.scrollTo({
                    top: targetElement.offsetTop - 80,
                    behavior: 'smooth'
                });
                
                // Update URL hash without jumping
                history.pushState(null, null, targetId);
            });
        });
        
        // Copy code to clipboard
        function copyCode(button) {
            const codeBlock = button.parentElement;
            const code = codeBlock.querySelector('pre').innerText;
            
            navigator.clipboard.writeText(code).then(function() {
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i> Kopjuar!';
                button.style.backgroundColor = '#16a34a';
                
                setTimeout(function() {
                    button.innerHTML = originalText;
                    button.style.backgroundColor = '';
                }, 2000);
            }, function() {
                button.innerHTML = '<i class="fas fa-times"></i> Gabim!';
                button.style.backgroundColor = '#dc2626';
                
                setTimeout(function() {
                    button.innerHTML = '<i class="fas fa-copy"></i> Kopjo';
                    button.style.backgroundColor = '';
                }, 2000);
            });
        }
        
        // Initialize page with active section
        document.addEventListener('DOMContentLoaded', function() {
            // Check if there's a hash in the URL
            if (window.location.hash) {
                const targetElement = document.querySelector(window.location.hash);
                if (targetElement) {
                    setTimeout(function() {
                        window.scrollTo({
                            top: targetElement.offsetTop - 80,
                            behavior: 'smooth'
                        });
                    }, 100);
                }
            }
            
            // Highlight code blocks
            document.querySelectorAll('pre code').forEach((block) => {
                hljs.highlightBlock(block);
            });
        });
    </script>
</body>
</html>