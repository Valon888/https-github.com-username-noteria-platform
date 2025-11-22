<?php
// Tink API Integration Example (Professional Structure)
// Kjo klasë mundëson autentikimin dhe marrjen e të dhënave nga Tink API

require 'vendor/autoload.php'; // Sigurohuni që Guzzle është instaluar
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class TinkApi {
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $client;
    private $baseUrl = 'https://api.tink.com';

    public function __construct($clientId, $clientSecret, $redirectUri) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;
        $this->client = new Client(['base_uri' => $this->baseUrl]);
    }

    // Step 1: Get Authorization URL
    public function getAuthorizationUrl($scope = 'accounts:read') {
        $params = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => $scope
        ]);
        return $this->baseUrl . '/oauth/authorize?' . $params;
    }

    // Step 2: Exchange code for access token
    public function getAccessToken($code) {
        try {
            $response = $this->client->post('/api/v1/oauth/token', [
                'form_params' => [
                    'code' => $code,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $this->redirectUri
                ]
            ]);
            $data = json_decode($response->getBody(), true);
            return $data['access_token'] ?? null;
        } catch (RequestException $e) {
            error_log('Tink Token Error: ' . $e->getMessage());
            return null;
        }
    }

    // Step 3: Get accounts (example API call)
    public function getAccounts($accessToken) {
        try {
            $response = $this->client->get('/api/v1/accounts', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken
                ]
            ]);
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            error_log('Tink Accounts Error: ' . $e->getMessage());
            return null;
        }
    }
}

// ======= Shembull përdorimi =======
$clientId = getenv('TINK_CLIENT_ID');
$clientSecret = getenv('TINK_CLIENT_SECRET');
$redirectUri = 'https://yourdomain.com/callback'; // Ndryshoni sipas nevojës

$tink = new TinkApi($clientId, $clientSecret, $redirectUri);

// 1. Printoni URL-në e autorizimit për përdoruesin
echo "<a href='" . $tink->getAuthorizationUrl() . "'>Lidhu me bankën përmes Tink</a>";
// 2. Pasi përdoruesi kthehet me ?code=...
if (isset($_GET['code'])) {
    $accessToken = $tink->getAccessToken($_GET['code']);
    if ($accessToken) {
        $accounts = $tink->getAccounts($accessToken);
        echo '<pre>' . print_r($accounts, true) . '</pre>';
    } else {
        echo 'Autentikimi dështoi.';
    }
}

// Phone verification testing code
require_once 'config.php';
require_once 'PhoneVerificationAdvanced.php';

echo "=== TESTING REAL CODE VERIFICATION ===\n";

try {
    $phoneVerifier = new PhoneVerificationAdvanced($pdo);
    
    // Get the latest code from database
    $stmt = $pdo->query("
        SELECT verification_code, transaction_id 
        FROM phone_verification_codes 
        WHERE phone_number = '+38344123456' AND is_used = 0 AND expires_at > NOW()
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($record) {
        $code = $record['verification_code'];
        $transaction_id = $record['transaction_id'];
        
        echo "Testing verification with:\n";
        echo "  Phone: +38344123456\n";
        echo "  Code: $code\n";
        echo "  Transaction: $transaction_id\n\n";
        
        $result = $phoneVerifier->verifyCode('+38344123456', $code, $transaction_id);
        
        if ($result['success']) {
            echo "🎉 VERIFICATION SUCCESS!\n";
            echo "   Message: {$result['message']}\n";
            echo "   Verification time: {$result['verification_time_seconds']} seconds\n";
        } else {
            echo "❌ VERIFICATION FAILED: {$result['error']}\n";
        }
        
    } else {
        echo "❌ No valid codes found. Generate a new one first.\n";
    }
    
} catch(Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>