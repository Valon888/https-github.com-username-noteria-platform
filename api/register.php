<?php
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
$password = $data['password'] ?? '';

if (!$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'Email and password required']);
    exit;
}

// Simulate user creation (in real app, check if user exists)
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$newUser = ['email' => $email];
// Don't return password in response
echo json_encode(['success' => true, 'message' => 'User registered', 'user' => $newUser]);
