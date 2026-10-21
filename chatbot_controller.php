<?php
// chatbot_controller.php

require_once 'db_connection.php';
require_once 'ai_chatbot_engine.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'], $_SESSION['tenant_id'])) {
    http_response_code(401);
    exit(json_encode(['response' => 'Ju lutem kyçuni për të vazhduar.', 'suggestions' => []]));
}
$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    // Rate limit: max 1 message per 2 seconds
    if (isset($_SESSION['last_chat_time']) && time() - $_SESSION['last_chat_time'] < 2) {
        http_response_code(429);
        exit(json_encode(['response' => 'Ju lutem prisni pak para mesazhit tjetër.', 'suggestions' => []]));
    }
    $_SESSION['last_chat_time'] = time();

    $message = $_POST['message'];

    // Generate AI/bot response (never throws; falls back to a friendly message)
    $result = generateAIResponse($pdo, $tenant_id, $message);

    // Chat history is best-effort: a missing table must not break the chat
    try {
        if (!isset($_SESSION['chat_session_id'])) {
            $stmt = $pdo->prepare("INSERT INTO chat_sessions (user_id, tenant_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $tenant_id]);
            $_SESSION['chat_session_id'] = $pdo->lastInsertId();
        }
        $session_id = $_SESSION['chat_session_id'];

        $stmt = $pdo->prepare("INSERT INTO chat_messages (session_id, sender, message) VALUES (?, 'user', ?)");
        $stmt->execute([$session_id, sanitize_input($message)]);

        $stmt = $pdo->prepare("INSERT INTO chat_messages (session_id, sender, message, intent_category) VALUES (?, 'bot', ?, ?)");
        $stmt->execute([$session_id, $result['response'], $result['intent']]);
    } catch (Throwable $e) {
        error_log('chat history logging failed: ' . $e->getMessage());
    }

    echo json_encode([
        'response' => $result['response'],
        'suggestions' => $result['suggestions']
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['response' => 'Kërkesë e pavlefshme.', 'suggestions' => []]);
