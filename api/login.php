<?php
header('Content-Type: application/json');

// Simulate DB user storage (in real app, use a database)
session_start();
if (!isset($_SESSION['users'])) {
    $_SESSION['users'] = [
        [
            'email' => 'user@example.com',
            // password: password123
            'password' => password_hash('password123', PASSWORD_DEFAULT)
        ]
    ];
}
$users = &$_SESSION['users'];

$data = json_decode(file_get_contents('php://input'), true);
$email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
$password = $data['password'] ?? '';

if (!$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'Email and password required']);
    exit;
}

$found = false;
foreach ($users as $user) {
    if ($user['email'] === $email) {
        $found = true;
        if (password_verify($password, $user['password'])) {
            // Generate a simple JWT-like token (for demo only)
            $token = base64_encode(json_encode([
                'email' => $email,
                'iat' => time(),
                'exp' => time() + 3600
            ]));
            echo json_encode(['success' => true, 'message' => 'Login successful', 'token' => $token]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            exit;
        }
    }
}
// If not found, auto-register
$users[] = [
    'email' => $email,
    'password' => password_hash($password, PASSWORD_DEFAULT)
];
$token = base64_encode(json_encode([
    'email' => $email,
    'iat' => time(),
    'exp' => time() + 3600
]));
echo json_encode(['success' => true, 'message' => 'User auto-registered and logged in', 'token' => $token]);
