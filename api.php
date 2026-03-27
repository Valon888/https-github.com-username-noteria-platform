// Debug: Log raw POST input për të parë çfarë merr PHP
file_put_contents(__DIR__ . '/debug_input.txt', file_get_contents('php://input'));
<?php
// api.php - Endpoint REST për chatbot
require_once 'chatbot.php';       // getBotResponse() - përgjigjet e paracaktuara
require_once __DIR__ . '/../mcp-noteria/db.php';
require_once __DIR__ . '/../mcp-noteria/tools.php';

header('Content-Type: application/json; charset=UTF-8');

// Merr mesazhin dhe gjuhën nga POST
$data    = json_decode(file_get_contents('php://input'), true);
$message = trim($data['message'] ?? '');
$lang    = $data['lang'] ?? 'sq';

if ($message === '') {
    echo json_encode(['error' => 'Use POST with {"message": "...", "lang": "sq|en|sr"}']);
    exit();
}

// Provo fillimisht përgjigjet e paracaktuara
$response = getBotResponse($message, $lang);

$fallback_phrases = [
    "Më vjen keq, nuk e kuptoj.",
    "Žao mi je, ne razumem.",
    "Sorry, I don't understand.",
];

if (in_array($response, $fallback_phrases, true)) {
    // Fallback: Claude me MCP tools
    $response = askClaude($message, $lang);
}

echo json_encode(['response' => $response], JSON_UNESCAPED_UNICODE);

// ── Claude me MCP Tools ───────────────────────────────────────────────────────

function askClaude(string $message, string $lang): string
{
    $api_key = getenv('CLAUDE_API_KEY') ?: '';
    if (empty($api_key)) {
        return "Shërbimi i asistentit nuk është i disponueshëm momentalisht. Ju lutem na kontaktoni në +383 44 000 000.";
    }

    // System prompt sipas gjuhës
    $prompts = [
        'sq' => 'Jeni asistenti i platformës Noteria — zyrë noteriale në Kosovë. Komunikoni vetëm në shqip, me ton profesional por miqësor.

RREGULLA PËR TOOLS:
✓ Kur klienti pyet për noterë → thirr merr_notaret
✓ Para se të propozosh orar → thirr merr_oraret_lira
✓ Kur klienti konfirmon noter + datë + orë + shërbim → thirr krijo_rezervim
✓ Kur klienti jep token → thirr kontrollo_rezervim ose anulo_rezervim

Shërbime: Prokurë, Testament, Kontratë shitjeje, Vërtetime, Legalizim.
Mos jep këshilla ligjore të detajuara.',

        'en' => 'You are the assistant of Noteria platform — a notarial office in Kosovo. Communicate only in English, professionally.

USE TOOLS:
✓ Client asks about notaries → call merr_notaret
✓ Before suggesting time slots → call merr_oraret_lira
✓ Client confirms booking details → call krijo_rezervim

Services: Power of Attorney, Will, Sale Contract, Certifications, Legalization.',

        'sr' => 'Vi ste asistent platforme Noteria — notarska kancelarija na Kosovu. Komunicirajte samo na srpskom, profesionalno.

KORISTITE ALATE:
✓ Klijent pita o notarima → pozovite merr_notaret
✓ Pre predlaganja termina → pozovite merr_oraret_lira
✓ Klijent potvrdi detalje → pozovite krijo_rezervim',
    ];

    $system = $prompts[$lang] ?? $prompts['sq'];

    // Histori nga session (ruhet nga `chatbot.php` origjinal nëse ekziston)
    $history = $_SESSION['claude_history'] ?? [];

    // Shto mesazhin e ri
    $history[] = ['role' => 'user', 'content' => $message];

    // Tool definitions
    $tools = buildAnthropicTools();

    // Agentic loop
    $loop_messages  = $history;
    $assistant_text = '';

    for ($i = 0; $i < 5; $i++) {

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'model'      => 'claude-3-5-sonnet-20241022',
                'max_tokens' => 1024,
                'system'     => $system,
                'tools'      => $tools,
                'messages'   => $loop_messages,
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $api_key,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $code !== 200) {
            error_log('Claude API error ' . $code . ': ' . $raw);
            break;
        }

        $data        = json_decode($raw, true);
        $stop_reason = $data['stop_reason'] ?? 'end_turn';
        $content     = $data['content']     ?? [];

        if ($stop_reason === 'end_turn') {
            foreach ($content as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $assistant_text .= $block['text'];
                }
            }
            break;
        }

        if ($stop_reason === 'tool_use') {
            $loop_messages[] = ['role' => 'assistant', 'content' => $content];

            $tool_results = [];
            foreach ($content as $block) {
                if (($block['type'] ?? '') !== 'tool_use') continue;
                $result = callTool($block['name'], $block['input'] ?? []);
                $tool_results[] = [
                    'type'        => 'tool_result',
                    'tool_use_id' => $block['id'],
                    'content'     => $result['content'][0]['text'] ?? 'Pa rezultat.',
                ];
            }

            $loop_messages[] = ['role' => 'user', 'content' => $tool_results];
            continue;
        }

        break;
    }

    if (empty($assistant_text)) {
        $assistant_text = match($lang) {
            'en' => 'I am unable to respond right now. Please call +383 44 000 000.',
            'sr' => 'Trenutno ne mogu da odgovorim. Pozovite +383 44 000 000.',
            default => 'Nuk mund të përgjigjem tani. Kontaktoni +383 44 000 000.',
        };
    }

    echo json_encode(['response' => $assistant_text], JSON_UNESCAPED_UNICODE);
    $loop_messages[] = ['role' => 'assistant', 'content' => $assistant_text];
    $_SESSION['claude_history'] = array_slice($loop_messages, -20); // max 20 mesazhe

    return $assistant_text;
}

function buildAnthropicTools(): array
{
    $mcp  = getToolsList();
    $defs = [];
    foreach ($mcp['tools'] as $t) {
        $defs[] = [
            'name'         => $t['name'],
            'description'  => $t['description'],
            'input_schema' => $t['inputSchema'],
        ];
    }
    return $defs;
}