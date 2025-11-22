<?php
// example_tink_integration.php
// Example of a professional Tink API integration for payment initiation and verification

declare(strict_types=1);

// Composer autoload (make sure you have a Tink PHP SDK or use Guzzle for HTTP requests)
require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class TinkPaymentService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $tinkApiBase;
    private Client $httpClient;
    private ?string $accessToken = null;

    public function __construct(string $clientId, string $clientSecret, string $redirectUri, string $tinkApiBase = 'https://api.tink.com')
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;
        $this->tinkApiBase = $tinkApiBase;
        $this->httpClient = new Client(['base_uri' => $tinkApiBase]);
    }

    /**
     * Authenticate with Tink and get an access token
     * @throws GuzzleException
     */
    public function authenticate(): void
    {
        $response = $this->httpClient->post('/api/v1/oauth/token', [
            'form_params' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials',
                'scope' => 'payment:read, payment:write',
            ],
        ]);
        $data = json_decode($response->getBody()->getContents(), true);
        $this->accessToken = $data['access_token'] ?? null;
        if (!$this->accessToken) {
            throw new \RuntimeException('Failed to obtain Tink access token.');
        }
    }

    /**
     * Initiate a payment
     * @param float $amountEur
     * @param string $currency
     * @param string $recipientIban
     * @param string $recipientName
     * @param string $reference
     * @return array
     * @throws GuzzleException
     */
    public function initiatePayment(float $amountEur, string $currency, string $recipientIban, string $recipientName, string $reference): array
    {
        if (!$this->accessToken) {
            $this->authenticate();
        }
        $amountMinor = (int) round($amountEur * 100);
        $response = $this->httpClient->post('/api/v1/payments', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'amount' => [
                    'currencyCode' => $currency,
                    'value' => $amountMinor,
                ],
                'recipientIban' => $recipientIban,
                'recipientName' => $recipientName,
                'reference' => $reference,
            ],
        ]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Verify payment status
     * @param string $paymentId
     * @return array
     * @throws GuzzleException
     */
    public function verifyPayment(string $paymentId): array
    {
        if (!$this->accessToken) {
            $this->authenticate();
        }
        $response = $this->httpClient->get("/api/v1/payments/{$paymentId}", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
            ],
        ]);
        return json_decode($response->getBody()->getContents(), true);
    }
}


// Example: Split payment flow for a notary service marketplace with automated notary data retrieval
$tinkClientId = 'YOUR_TINK_CLIENT_ID';
$tinkClientSecret = 'YOUR_TINK_CLIENT_SECRET';
$tinkRedirectUri = 'https://yourapp.com/callback';

require_once __DIR__ . '/db_connect.php'; // Use $pdo for DB access

$service = new TinkPaymentService($tinkClientId, $tinkClientSecret, $tinkRedirectUri);

// 1. User pays the platform (total amount)
$totalAmount = 150.00; // EUR
$notaryAmount = 140.00; // EUR (amount to notary)
$platformCommission = $totalAmount - $notaryAmount; // EUR

$platformIban = 'PLATFORM_IBAN_HERE'; // Your platform's IBAN
$platformName = 'Your Platform Name';
$reference = 'Shërbim noterial #12345';


// --- AUTOMATION: Fetch notary data from DB based on payment/service/order/user input ---
function getNotaryIdForPayment(PDO $pdo): int {
    // Shembull: merr noterin nga një kërkesë POST, GET, ose nga një porosi
    // Këtu mund të përdorësh $_POST['notary_id'], $_GET['notary_id'], ose të kërkosh nga një tabelë pagesash/porosish
    if (isset($_POST['notary_id'])) {
        return (int)$_POST['notary_id'];
    }
    if (isset($_GET['notary_id'])) {
        return (int)$_GET['notary_id'];
    }
    // Shembull fallback: merr noterin e parë aktiv
    $stmt = $pdo->query('SELECT id FROM zyrat ORDER BY id ASC LIMIT 1');
    $row = $stmt->fetch();
    if ($row) return (int)$row['id'];
    die("Asnjë noter nuk u gjet në databazë!\n");
}

$notaryId = getNotaryIdForPayment($pdo);
$stmt = $pdo->prepare('SELECT emri_noterit, iban FROM zyrat WHERE id = ? LIMIT 1');
$stmt->execute([$notaryId]);
$notary = $stmt->fetch();
if (!$notary) {
    die("Noteri nuk u gjet në databazë!\n");
}
$notaryIban = $notary['iban'];
$notaryName = $notary['emri_noterit'];

try {
    // Step 1: User pays the platform
    $userToPlatform = $service->initiatePayment(
        $totalAmount,
        'EUR',
        $platformIban,
        $platformName,
        $reference
    );
    echo "[1] User payment to platform initiated. Payment ID: " . ($userToPlatform['id'] ?? 'N/A') . "\n";

    // Step 2: Verify user payment status
    if (isset($userToPlatform['id'])) {
        $status = $service->verifyPayment($userToPlatform['id']);
        echo "[1] User payment status: " . ($status['status'] ?? 'unknown') . "\n";

        if (($status['status'] ?? null) === 'EXECUTED') {
            // Step 3: Platform pays the notary (after commission is kept)
            $platformToNotary = $service->initiatePayment(
                $notaryAmount,
                'EUR',
                $notaryIban,
                $notaryName,
                'Pagesë për shërbim noterial #12345'
            );
            echo "[2] Platform payment to notary initiated. Payment ID: " . ($platformToNotary['id'] ?? 'N/A') . "\n";

            // Step 4: Verify notary payment status
            if (isset($platformToNotary['id'])) {
                $notaryStatus = $service->verifyPayment($platformToNotary['id']);
                echo "[2] Notary payment status: " . ($notaryStatus['status'] ?? 'unknown') . "\n";
            }
        } else {
            echo "[!] User payment not completed, not proceeding with notary payout.\n";
        }
    }
} catch (Exception $e) {
    echo "Tink API error: " . $e->getMessage() . "\n";
}
