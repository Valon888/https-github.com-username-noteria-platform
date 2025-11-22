<?php
// TinkPaymentVerifier.php
// Simple Tink Open Banking payment verification for sandbox

class TinkPaymentVerifier {
    private $clientId;
    private $clientSecret;
    private $tokenEndpoint = 'https://api.tink.com/api/v1/oauth/token';
    private $paymentsEndpoint = 'https://api.tink.com/api/v1/payments';
    private $accessToken;

    public function __construct($clientId, $clientSecret) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    // Step 1: Get access token
    public function authenticate() {
        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'client_credentials',
            'scope' => 'payments:read payments:write'
        ];
        $response = $this->curlPost($this->tokenEndpoint, $data);
        if (isset($response['access_token'])) {
            $this->accessToken = $response['access_token'];
            return true;
        }
        return false;
    }

    // Step 2: Create a payment (sandbox)
    public function createPayment($amount, $currency, $recipientIban, $recipientName, $reference) {
        $data = [
            'amount' => [
                'currencyCode' => $currency,
                'value' => $amount // e.g. 1000 = 10.00 EUR
            ],
            'recipientIban' => $recipientIban,
            'recipientName' => $recipientName,
            'reference' => $reference
        ];
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ];
        $response = $this->curlPostJson($this->paymentsEndpoint, $data, $headers);
        return $response;
    }

    // Step 3: Get payment status
    public function getPaymentStatus($paymentId) {
        $url = $this->paymentsEndpoint . '/' . $paymentId;
        $headers = [
            'Authorization: Bearer ' . $this->accessToken
        ];
        $response = $this->curlGet($url, $headers);
        return $response;
    }

    // Helper: POST x-www-form-urlencoded
    private function curlPost($url, $data) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, true);
    }

    // Helper: POST JSON
    private function curlPostJson($url, $data, $headers) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, true);
    }

    // Helper: GET
    private function curlGet($url, $headers) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, true);
    }
}
