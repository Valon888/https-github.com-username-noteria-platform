<?php
header('Content-Type: application/json');

// Simulate authentication (JWT-like)
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
if (strpos($authHeader, 'Bearer ') !== 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$token = substr($authHeader, 7);
$payload = json_decode(base64_decode($token), true);
if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token expired or invalid']);
    exit;
}
// Simulate user list
$users = [
    ['id' => 1, 'email' => 'user@example.com'],
    ['id' => 2, 'email' => 'admin@example.com']
];
echo json_encode(['success' => true, 'users' => $users]);
