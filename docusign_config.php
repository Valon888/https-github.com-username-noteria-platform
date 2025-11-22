<?php
/**
 * DocuSign E-Signature Configuration
 * Integrim për nënshkrimet elektronike të dokumenteve
 */

// DocuSign API Configuration
define('DOCUSIGN_ACCOUNT_ID', $_ENV['DOCUSIGN_ACCOUNT_ID'] ?? 'YOUR_ACCOUNT_ID');
define('DOCUSIGN_CLIENT_ID', $_ENV['DOCUSIGN_CLIENT_ID'] ?? 'YOUR_CLIENT_ID');
define('DOCUSIGN_CLIENT_SECRET', $_ENV['DOCUSIGN_CLIENT_SECRET'] ?? 'YOUR_CLIENT_SECRET');
define('DOCUSIGN_API_BASE_URL', 'https://demo.docusign.net/restapi');
define('DOCUSIGN_AUTH_SERVER', 'https://account-d.docusign.com/oauth/token');
define('DOCUSIGN_REDIRECT_URI', 'http://localhost/noteria/docusign_callback.php');

/**
 * Get DocuSign Access Token
 */
function getDocuSignAccessToken() {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, DOCUSIGN_AUTH_SERVER);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'client_credentials',
        'client_id' => DOCUSIGN_CLIENT_ID,
        'client_secret' => DOCUSIGN_CLIENT_SECRET,
        'scope' => 'signature'
    ]));
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($http_code != 200) {
        error_log("DocuSign Token Error: " . $response);
        return null;
    }
    
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

/**
 * Create Envelope (Document for Signature)
 */
function createDocuSignEnvelope($documentName, $documentPath, $signerEmail, $signerName, $callId = null) {
    $accessToken = getDocuSignAccessToken();
    
    if (!$accessToken) {
        error_log("Failed to get DocuSign access token");
        return false;
    }
    
    if (!file_exists($documentPath)) {
        error_log("Document not found: " . $documentPath);
        return false;
    }
    
    $documentContent = base64_encode(file_get_contents($documentPath));
    
    $envelopeDefinition = [
        "emailSubject" => "Nënshkrim i Dokumentit - Noteria",
        "emailBlurb" => "Ju lutem nënshkruani dokumentin në platformën Noteria",
        "documents" => [
            [
                "documentId" => "1",
                "name" => $documentName,
                "documentBase64" => $documentContent,
                "fileExtension" => pathinfo($documentPath, PATHINFO_EXTENSION)
            ]
        ],
        "recipients" => [
            "signers" => [
                [
                    "email" => $signerEmail,
                    "name" => $signerName,
                    "recipientId" => "1",
                    "routingOrder" => "1",
                    "tabs" => [
                        "signHereTabs" => [
                            [
                                "documentId" => "1",
                                "pageNumber" => "1",
                                "xPosition" => "100",
                                "yPosition" => "100"
                            ]
                        ]
                    ]
                ]
            ]
        ],
        "status" => "sent"
    ];
    
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, DOCUSIGN_API_BASE_URL . "/v2.1/accounts/" . DOCUSIGN_ACCOUNT_ID . "/envelopes");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $accessToken,
        "Content-Type: application/json"
    ]);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($envelopeDefinition));
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($http_code != 201) {
        error_log("DocuSign Envelope Creation Error: " . $response);
        return false;
    }
    
    $envelopeData = json_decode($response, true);
    $envelopeId = $envelopeData['envelopeId'] ?? null;
    
    if (!$envelopeId) {
        return false;
    }
    
    // Store envelope in database
    if ($callId) {
        try {
            require_once 'db_connection.php';
            $stmt = $conn->prepare("INSERT INTO docusign_envelopes (call_id, envelope_id, document_name, signer_email, signer_name, status, created_at) VALUES (?, ?, ?, ?, ?, 'sent', NOW())");
            $stmt->bind_param("sssss", $callId, $envelopeId, $documentName, $signerEmail, $signerName);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            error_log("Database insert failed: " . $e->getMessage());
        }
    }
    
    return [
        'success' => true,
        'envelopeId' => $envelopeId,
        'message' => 'Dokumenti u dërgua për nënshkrim'
    ];
}

/**
 * Get Envelope Status
 */
function getEnvelopeStatus($envelopeId) {
    $accessToken = getDocuSignAccessToken();
    
    if (!$accessToken) {
        return false;
    }
    
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, DOCUSIGN_API_BASE_URL . "/v2.1/accounts/" . DOCUSIGN_ACCOUNT_ID . "/envelopes/" . $envelopeId);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $accessToken
    ]);
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($http_code != 200) {
        return false;
    }
    
    return json_decode($response, true);
}

/**
 * Get Signing URL (for embedded signing)
 */
function getSigningUrl($envelopeId, $recipientId, $signerEmail, $signerName, $returnUrl) {
    $accessToken = getDocuSignAccessToken();
    
    if (!$accessToken) {
        return false;
    }
    
    $postData = [
        "returnUrl" => $returnUrl,
        "authenticationMethod" => "none",
        "clientUserId" => $recipientId,
        "email" => $signerEmail,
        "userName" => $signerName
    ];
    
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, DOCUSIGN_API_BASE_URL . "/v2.1/accounts/" . DOCUSIGN_ACCOUNT_ID . "/envelopes/" . $envelopeId . "/views/recipient");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $accessToken,
        "Content-Type: application/json"
    ]);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($postData));
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($http_code != 201) {
        error_log("Signing URL Error: " . $response);
        return false;
    }
    
    $data = json_decode($response, true);
    return $data['url'] ?? false;
}

?>
