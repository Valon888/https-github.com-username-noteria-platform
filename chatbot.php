
<?php
// chatbot.php - Logjika bazë e chatbot-it shumëgjuhësh
// require_once 'db.php';

$responses = [
    'hello' => [
        'sq' => 'Përshëndetje! Si mund t’ju ndihmoj?',
        'sr' => 'Zdravo! Kako mogu da pomognem?',
        'en' => 'Hello! How can I help you?',
    ],
    'bye' => [
        'sq' => 'Mirupafshim!',
        'sr' => 'Doviđenja!',
        'en' => 'Goodbye!',
    ],
    // Shto më shumë pyetje/përgjigje sipas nevojës
];

// --- Shto API handler për POST ---
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $msg = $input['message'] ?? '';
        $lang = $input['lang'] ?? 'sq';
        $response = getBotResponse($msg, $lang);
        echo json_encode(['response' => $response]);
        exit();
    } else {
        echo json_encode(['error' => 'Use POST with {"message": "...", "lang": "sq|en|sr"}']);
        exit();
    }
}

function getBotResponse($message, $lang = 'sq') {
    global $responses;
    $msg = strtolower(trim($message));
    if (isset($responses[$msg])) {
        return $responses[$msg][$lang] ?? $responses[$msg]['sq'];
    }
    // Përgjigje default
    if ($lang === 'en') return "Sorry, I don't understand.";
    if ($lang === 'sr') return "Žao mi je, ne razumem.";
    return "Më vjen keq, nuk e kuptoj.";
}
