<?php
// Test CSRF token generation and session handling

session_start();

echo "[SESSION TEST]\n\n";
echo "Session ID: " . session_id() . "\n";

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    echo "Generated new token\n";
}

$token = substr($_SESSION['csrf_token'], 0, 20) . "...\n";
echo "Token in session: " . $token;

// Check POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "\nPOST received\n";
    
    if (isset($_POST['csrf_token'])) {
        $post_token = $_POST['csrf_token'];
        $session_token = $_SESSION['csrf_token'];
        
        if ($post_token === $session_token) {
            echo "MATCH: Tokens are identical\n";
        } else {
            echo "MISMATCH:\n";
            echo "  POST token: " . substr($post_token, 0, 20) . "...\n";
            echo "  SESSION token: " . substr($session_token, 0, 20) . "...\n";
        }
    } else {
        echo "NO TOKEN IN POST\n";
    }
} else {
    echo "\n[FORM]\n";
    echo '<form method="POST">' . "\n";
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">' . "\n";
    echo '<button type="submit">Test</button>' . "\n";
    echo '</form>' . "\n";
}
?>
