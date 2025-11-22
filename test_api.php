<?php
// test_api.php - Skript i thjeshtë për të testuar API-n
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Tokeni i gjeneruar nga create_api_tables.php
$token = 'f71de63c48c832da0a8e4ee46b9f406c7e8cc692470032b51dbcb4bcb349b1a1';

// Testojmë endpoint-in default
$apiUrl = 'http://localhost/noteria/mcp_api_new.php';

// Inicializimi i curl
$ch = curl_init($apiUrl);

// Headers
$headers = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
];

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Ekzekutimi i kërkesës
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

echo "<h2>Test i API-it</h2>";
echo "<h3>Endpoint: default</h3>";
echo "<h4>Status Code: $httpCode</h4>";

if (!empty($error)) {
    echo "<h4>Error: $error</h4>";
}

echo "<h4>Response:</h4>";
echo "<pre>";
$decodedResponse = json_decode($response, true);
if ($decodedResponse) {
    echo json_encode($decodedResponse, JSON_PRETTY_PRINT);
} else {
    echo htmlspecialchars($response);
}
echo "</pre>";

// Testojmë endpoint-in payments
$apiUrl = 'http://localhost/noteria/mcp_api_new.php?endpoint=payments';

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

echo "<h3>Endpoint: payments</h3>";
echo "<h4>Status Code: $httpCode</h4>";

if (!empty($error)) {
    echo "<h4>Error: $error</h4>";
}

echo "<h4>Response:</h4>";
echo "<pre>";
$decodedResponse = json_decode($response, true);
if ($decodedResponse) {
    echo json_encode($decodedResponse, JSON_PRETTY_PRINT);
} else {
    echo htmlspecialchars($response);
}
echo "</pre>";

// Link për të shkuar tek API Test Client
echo "<p><a href='api_client_test.php'>Shko tek API Test Client për teste më të detajuara</a></p>";
?>