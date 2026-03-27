<?php
/**
 * DOCUMENT GENERATOR API
 * Handles AI-powered legal document generation
 */

session_start();

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'generate':
        generateDocument();
        break;
    case 'save':
        saveDocument();
        break;
    case 'export_pdf':
        exportPDF();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function generateDocument() {
    global $pdo;

    $data = [
        'document_type' => sanitize($_POST['document_type'] ?? ''),
        'client_full_name' => sanitize($_POST['client_full_name'] ?? ''),
        'personal_id_number' => sanitize($_POST['personal_id_number'] ?? ''),
        'address' => sanitize($_POST['address'] ?? ''),
        'second_party_name' => sanitize($_POST['second_party_name'] ?? ''),
        'second_party_id' => sanitize($_POST['second_party_id'] ?? ''),
        'property_description' => sanitize($_POST['property_description'] ?? ''),
        'date' => sanitize($_POST['date'] ?? ''),
        'city' => sanitize($_POST['city'] ?? '')
    ];

    // Validate required fields
    if (empty($data['document_type']) || empty($data['client_full_name']) || empty($data['personal_id_number'])) {
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }

    // Build prompt for Claude
    $prompt = buildDocumentPrompt($data);

    // Call Claude API
    $apiKey = getenv('CLAUDE_API_KEY') ?: $_ENV['CLAUDE_API_KEY'] ?? '';
    if (empty($apiKey)) {
        echo json_encode(['error' => 'Claude API key not configured']);
        return;
    }

    $generatedText = callClaudeAPI($prompt, $apiKey);

    if (!$generatedText) {
        echo json_encode(['error' => 'Failed to generate document']);
        return;
    }

    // Save to database
    try {
        $stmt = $pdo->prepare('
            INSERT INTO document_requests 
            (document_type, client_full_name, personal_id_number, address, 
             second_party_name, second_party_id, property_description, date, city, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ');

        $stmt->execute([
            $data['document_type'],
            $data['client_full_name'],
            $data['personal_id_number'],
            $data['address'],
            $data['second_party_name'],
            $data['second_party_id'],
            $data['property_description'],
            $data['date'],
            $data['city']
        ]);

        $requestId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'request_id' => $requestId,
            'document_content' => $generatedText,
            'data' => $data
        ]);

    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function buildDocumentPrompt($data) {
    $documentTypeLabels = [
        'authorization' => 'Authorization Letter',
        'sales_contract' => 'Sales Contract',
        'declaration' => 'Declaration',
        'rental_contract' => 'Rental Contract',
        'power_of_attorney' => 'Power of Attorney',
        'will' => 'Will/Testament'
    ];

    $type = $documentTypeLabels[$data['document_type']] ?? $data['document_type'];

    $prompt = "Generate a professional Albanian legal notary document based on the following data:

Document Type: {$type}
Client Name: {$data['client_full_name']}
Client ID: {$data['personal_id_number']}
Client Address: {$data['address']}
Client City: {$data['city']}

Second Party Name: {$data['second_party_name']}
Second Party ID: {$data['second_party_id']}

Property Description: {$data['property_description']}
Date: {$data['date']}

Requirements:
1. Use formal notarial language appropriate for Kosovo legal standards
2. Include proper legal headers and formatting
3. Include signature blocks for both parties
4. Use professional Albanian legal terminology
5. Include date, place, and notary reference fields
6. Format as a complete, ready-to-print document
7. Include proper indentation and spacing

Generate only the document content without any explanations or metadata.";

    return $prompt;
}

function callClaudeAPI($prompt, $apiKey) {
    $url = 'https://api.anthropic.com/v1/messages';

    $payload = json_encode([
        'model' => 'claude-3-5-sonnet-20241022',
        'max_tokens' => 4096,
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01'
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Claude API Error: $response");
        return false;
    }

    $decoded = json_decode($response, true);
    return $decoded['content'][0]['text'] ?? false;
}

function saveDocument() {
    global $pdo;

    $requestId = intval($_POST['request_id'] ?? 0);
    $content = $_POST['content'] ?? '';

    if ($requestId <= 0 || empty($content)) {
        echo json_encode(['error' => 'Invalid data']);
        return;
    }

    try {
        $stmt = $pdo->prepare('
            INSERT INTO generated_documents 
            (request_id, content, status, created_at)
            VALUES (?, ?, ?, NOW())
        ');

        $stmt->execute([$requestId, $content, 'pending_review']);

        $documentId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'document_id' => $documentId,
            'message' => 'Document saved successfully'
        ]);

    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function exportPDF() {
    $content = $_POST['content'] ?? '';

    if (empty($content)) {
        echo json_encode(['error' => 'No content to export']);
        return;
    }

    // Use TCPDF or similar library
    require_once __DIR__ . '/../vendor/autoload.php';

    try {
        $pdf = new \TCPDF();
        $pdf->AddPage();
        $pdf->SetFont('dejavusans', '', 11);
        $pdf->MultiCell(0, 10, $content, 0, 'L');

        $pdfContent = $pdf->Output('document.pdf', 'S');

        echo json_encode([
            'success' => true,
            'pdf_base64' => base64_encode($pdfContent)
        ]);

    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}