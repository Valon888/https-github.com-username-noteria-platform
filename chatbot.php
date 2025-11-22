<?php
// chatbot.php - Logjika bazë e chatbot-it shumëgjuhësh
require_once 'db.php';

// Përgjigje të paracaktuara në 3 gjuhë
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
