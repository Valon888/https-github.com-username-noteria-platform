<?php
// api_index.php - Faqja kryesore për API
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');


// RATE LIMIT: 100 requests per minute per IP, user_id ose api_key (DB version)
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
require_once 'confidb.php';
$identifier = $ip;
// Kontrollo nëse ka user_id të kyçur
if (isset($_SESSION['user_id'])) {
	$identifier = 'user_' . $_SESSION['user_id'];
}
// Kontrollo nëse ka api_key në header ose query
if (isset($_GET['api_key'])) {
	$identifier = 'key_' . $_GET['api_key'];
} elseif (isset($_SERVER['HTTP_X_API_KEY'])) {
	$identifier = 'key_' . $_SERVER['HTTP_X_API_KEY'];
}
try {
	$stmt = $pdo->prepare("SELECT COUNT(*) FROM api_rate_limits WHERE identifier = ? AND request_time > (NOW() - INTERVAL 1 MINUTE)");
	$stmt->execute([$identifier]);
	$request_count = (int)$stmt->fetchColumn();
	if ($request_count >= 100) {
		http_response_code(429);
		header('Content-Type: application/json');
		echo json_encode(['error' => 'Rate limit exceeded. Try again in 1 minute.']);
		error_log("API_RATE_LIMIT: $identifier");
		exit();
	}
	// Regjistro këtë kërkesë
	$stmt = $pdo->prepare("INSERT INTO api_rate_limits (identifier) VALUES (?)");
	$stmt->execute([$identifier]);
} catch (PDOException $e) {
	error_log('DB error (api_rate_limits): ' . $e->getMessage());
}

// Ridrejto tek dokumentimi i API
header("Location: api_docs.php?public=true");
exit();
?>