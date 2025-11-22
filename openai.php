<?php
// openai.php - Integrim me OpenAI GPT-4 API
function askOpenAI($message, $lang = 'sq') {
    $apiKey = 'YOUR_OPENAI_API_KEY'; // Vendos çelësin tënd
    $endpoint = 'https://api.openai.com/v1/chat/completions';
    $systemPrompt = [
        'sq' => 'Je një asistent virtual që flet shqip, serbisht dhe anglisht. Përgjigju natyrshëm dhe profesionalisht.',
        'sr' => 'Vi ste virtuelni asistent koji govori albanski, srpski i engleski. Odgovarajte prirodno i profesionalno.',
        'en' => 'You are a virtual assistant that speaks Albanian, Serbian, and English. Respond naturally and professionally.'
    ];
    $data = [
        'model' => 'gpt-4',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt[$lang] ?? $systemPrompt['sq']],
            ['role' => 'user', 'content' => $message]
        ],
        'max_tokens' => 256,
        'temperature' => 0.7
    ];
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $result = curl_exec($ch);
    curl_close($ch);
    $response = json_decode($result, true);
    return $response['choices'][0]['message']['content'] ?? 'Nuk ka përgjigje.';
}
