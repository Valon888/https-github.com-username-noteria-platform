<?php
// DocuSign Configuration
// Ju lutem plotësoni të dhënat tuaja nga paneli i DocuSign Developer

// Integration Key (Client ID)
define('DS_CLIENT_ID', 'YOUR_INTEGRATION_KEY');

// Impersonated User GUID (User ID)
define('DS_IMPERSONATED_USER_ID', 'YOUR_USER_ID');

// Private Key File (Path to your private key file)
define('DS_PRIVATE_KEY_FILE', __DIR__ . '/private.key');

// DocuSign Account ID
define('DS_TARGET_ACCOUNT_ID', FALSE); // FALSE = use default account

// Base Path (demo or production)
define('DS_BASE_PATH', 'https://demo.docusign.net/restapi');

// Authentication Server
define('DS_AUTH_SERVER', 'account-d.docusign.com');

// JWT Scope
define('DS_ESIGN_SCOPE', 'signature impersonation');

// Redirect URI (if using Authorization Code Grant)
define('DS_REDIRECT_URI', 'http://localhost/noteria/callback.php');

// Check if private key exists, if not create a dummy one to prevent errors
if (!file_exists(DS_PRIVATE_KEY_FILE)) {
    file_put_contents(DS_PRIVATE_KEY_FILE, "-----BEGIN RSA PRIVATE KEY-----\n...\n-----END RSA PRIVATE KEY-----");
}

/**
 * Creates and sends a DocuSign envelope
 * 
 * @param string $docName Name of the document
 * @param string $docPath Path to the file
 * @param string $signerEmail Email of the signer
 * @param string $signerName Name of the signer
 * @return array ['success' => bool, 'message' => string, 'envelopeId' => string|null]
 */
function createDocuSignEnvelope($docName, $docPath, $signerEmail, $signerName) {
    // Kjo është një funksion simulues. 
    // Për të aktivizuar DocuSign vërtetë, duhet të instaloni librarinë docusign/esign-client
    // dhe të implementoni logjikën e plotë të API.
    
    // Simulojmë një vonesë të vogël
    sleep(1);
    
    // Kthejmë sukses
    return [
        'success' => true,
        'message' => 'Dokumenti u dërgua me sukses në DocuSign (Simulim).',
        'envelopeId' => 'simulated-envelope-' . time()
    ];
}
?>