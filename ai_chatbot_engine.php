<?php
// ai_chatbot_engine.php

const CHATBOT_FALLBACK = 'Asistenti AI është përkohësisht i padisponueshëm. Ndërkohë mund të rezervoni termin direkt ose të na shkruani te support@e-noteria.com.';

/**
 * Generate AI or predefined response (Claude API integration)
 */
function generateAIResponse($pdo, $tenant_id, $user_message) {
    $user_message = sanitize_input($user_message);
    $intent = detect_intent($user_message);

    // 1. Try to match predefined Q&A for this tenant (skipped if the table is missing)
    try {
        $stmt = $pdo->prepare("SELECT answer, intent_category FROM chatbot_qa WHERE tenant_id = ? AND MATCH(question) AGAINST (?) LIMIT 1");
        $stmt->execute([$tenant_id, $user_message]);
        $row = $stmt->fetch();
        if ($row) {
            return [
                'response' => $row['answer'],
                'intent' => $row['intent_category'],
                'suggestions' => getSmartSuggestions($row['intent_category'])
            ];
        }
    } catch (Throwable $e) {
        error_log('chatbot_qa lookup failed: ' . $e->getMessage());
    }

    // 2. Claude API
    $ai_response = callClaudeChat($user_message);
    if ($ai_response === null) {
        return [
            'response' => CHATBOT_FALLBACK,
            'intent' => $intent,
            'suggestions' => getSmartSuggestions('appointment process')
        ];
    }

    return [
        'response' => htmlspecialchars($ai_response, ENT_QUOTES, 'UTF-8'),
        'intent' => $intent,
        'suggestions' => getSmartSuggestions($intent)
    ];
}

/**
 * Read a setting from the environment or the project's .env file.
 */
function chatbotEnv($name) {
    $v = getenv($name);
    if ($v !== false && $v !== '') return $v;
    $file = __DIR__ . '/.env';
    if (is_readable($file)) {
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (preg_match('/^\s*' . preg_quote($name, '/') . '\s*=\s*(.*)$/', $line, $m)) {
                $val = trim($m[1], " \t\"'");
                return $val !== '' ? $val : null;
            }
        }
    }
    return null;
}

/**
 * Returns the assistant's reply, or null when the API is unavailable.
 */
function callClaudeChat($user_message) {
    $api_key = chatbotEnv('ANTHROPIC_API_KEY') ?: chatbotEnv('CLAUDE_API_KEY');
    if (!$api_key) {
        error_log('chatbot: ANTHROPIC_API_KEY is not configured');
        return null;
    }

    $payload = [
        'model' => chatbotEnv('CLAUDE_MODEL') ?: 'claude-haiku-5-5',
        'max_tokens' => 400,
        'system' => 'Ti je asistent ligjor-noterial i Noteria. Përgjigju qartë dhe shkurt në gjuhën e përdoruesit (shqip si parazgjedhje). Nëse pyetja nuk lidhet me shërbimet noteriale/ligjore, refuzo me mirësjellje.',
        'messages' => [['role' => 'user', 'content' => $user_message]],
    ];

    $headers = [
        'Content-Type: application/json',
        'x-api-key: ' . $api_key,
        'anthropic-version: 2023-06-01',
    ];
    // Keys that are not scoped to a workspace must name one
    if ($workspace = chatbotEnv('ANTHROPIC_WORKSPACE_ID')) {
        $headers[] = 'anthropic-workspace-id: ' . $workspace;
    }

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $result = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($result === false || $code !== 200) {
        error_log("chatbot: Claude API failed (HTTP $code) $err " . substr((string)$result, 0, 300));
        return null;
    }
    $data = json_decode($result, true);
    $text = $data['content'][0]['text'] ?? null;
    return ($text !== null && $text !== '') ? $text : null;
}

/**
 * Intent detection (simple keyword-based, can be replaced with AI)
 */
function detect_intent($msg) {
    $msg = strtolower($msg);
    if (strpos($msg, 'price') !== false || strpos($msg, 'cost') !== false || strpos($msg, 'çmim') !== false) return 'pricing info';
    if (strpos($msg, 'document') !== false || strpos($msg, 'require') !== false || strpos($msg, 'dokument') !== false) return 'required documents';
    if (strpos($msg, 'appointment') !== false || strpos($msg, 'book') !== false || strpos($msg, 'termin') !== false) return 'appointment process';
    if (strpos($msg, 'notary') !== false || strpos($msg, 'service') !== false || strpos($msg, 'noter') !== false) return 'notary services';
    return null;
}

/**
 * Smart suggestions based on intent
 */
function getSmartSuggestions($intent) {
    $suggestions = [];
    if ($intent === 'notary services') {
        $suggestions[] = "See list of notary services";
    }
    if ($intent === 'required documents') {
        $suggestions[] = "View required documents";
    }
    if ($intent === 'appointment process') {
        $suggestions[] = "<button onclick=\"window.location.href='reservation.php'\">Book Appointment</button>";
    }
    if ($intent === 'pricing info') {
        $suggestions[] = "See pricing information";
    }
    return $suggestions;
}

/**
 * Sanitize input
 */
function sanitize_input($text) {
    return htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
}
