<?php
// api.php - Endpoint REST për chatbot
require_once 'chatbot.php';
require_once 'openai.php';
header('Content-Type: application/json');

// Merr mesazhin dhe gjuhën nga POST
$data = json_decode(file_get_contents('php://input'), true);
$message = $data['message'] ?? '';
$lang = $data['lang'] ?? 'sq';


$response = getBotResponse($message, $lang);
if ($response === "Më vjen keq, nuk e kuptoj." || $response === "Žao mi je, ne razumem." || $response === "Sorry, I don't understand.") {
	// Nëse nuk ka përgjigje të paracaktuar, përdor OpenAI
	$response = askOpenAI($message, $lang);
}
echo json_encode(['response' => $response]);
