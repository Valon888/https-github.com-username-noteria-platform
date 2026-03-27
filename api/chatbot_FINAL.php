<?php
/**
 * Noteria Chatbot API Handler
 * Secure backend for Claude API calls
 * + MCP Tools: notaret, oraret, rezervimet
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load configuration (which loads .env)
require_once __DIR__ . '/../config.php';

// MCP Tools (tools.php ndodhet pranë chatbot.php ose ndrysho path-in)
require_once __DIR__ . '/../mcp-noteria/db.php';
require_once __DIR__ . '/../mcp-noteria/tools.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// CORS headers
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get API key from environment (loaded via config.php)
$api_key = getenv('CLAUDE_API_KEY') ?: '';

// Configuration
define('CLAUDE_API_KEY', $api_key);
define('CLAUDE_MODEL', 'claude-3-5-sonnet-20241022');
define('CLAUDE_ENDPOINT', 'https://api.anthropic.com/v1/messages');

// System prompt
$SYSTEM_PROMPT = 'Ju jeni asistenti AI premium i platformës Noteria, një zyrë noteriale e licencuar në Kosovë. Emri juaj është "Asistenti i Noterisë".

DETYRAT TUAJA KRYESORE:
1. Të ndihmoni klientët me informacion të plotë mbi shërbimet noteriale
2. Të merrni informacionin e nevojshëm për rezervim takimesh
3. Të ofroni këshilla të përgjithshme (JO ligjore specifike)
4. Të jeni profesional, miqësor dhe të ndihmuar

SHËRBIME NOTERIALE:
- Kontrata Shitjeje (Veturave, Pasurive)
- Prokura (Përfaqësim Ligjor)
- Testamente & Dokumentet e Testamentit
- Vërtetime (Nënshkrimi, Kopjet)
- Legalizim Dokumentesh
- Deklarata Nën Betim
- Mbikëqyrje Kontratash

INFORMACIONE ZYRE:
- Orari: E Hënë–E Premte, 08:00–17:00
- Adresa: Rr. "Nëna Terezë", Prishtinë, Kosovë
- Telefon: +383 44 000 000
- Email: info@noteria.com

RREGULLA PËR TOOLS:
✓ Kur klienti pyet për noterë ose zyra → thirr merr_notaret
✓ Para se të propozosh orar → GJITHMONË thirr merr_oraret_lira
✓ Kur klienti konfirmon noter + datë + orë + shërbim → thirr krijo_rezervim
✓ Kur klienti jep token konfirmimi → thirr kontrollo_rezervim ose anulo_rezervim
✓ Pas rezervimit → lexoji klientit token-in dhe thuaji ta ruajë

RREGULLA TË PËRGJITHSHME:
✓ Gjithmonë flisni shqip me ton formal por miqësor
✓ Jini konciz dhe të qartë
✓ Për çështje komplekse, sugjeroni takim personal
✓ Mos ofroni këshilla ligjore të detajuara
✓ Sigurohuni se të dhënat e klientit janë konfidenciale

TONE: Profesional, besnik, ndihmues, modern';

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['messages']) || !is_array($input['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request format']);
    exit();
}

// Initialize conversation history in session
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

// Build messages array for Claude
$messages = [];
foreach ($input['messages'] as $msg) {
    if (isset($msg['role']) && isset($msg['content'])) {
        $messages[] = [
            'role'    => $msg['role'] === 'ai' ? 'assistant' : 'user',
            'content' => $msg['content'],
        ];
    }
}

if (empty($messages)) {
    http_response_code(400);
    echo json_encode(['error' => 'No messages provided']);
    exit();
}

// Store in session
$_SESSION['chat_history'] = $messages;

// Build tool definitions from MCP server
$mcp_tools = buildAnthropicTools();

// ── Agentic loop ─────────────────────────────────────────────────────────────
try {
    $loop_messages  = $messages;
    $max_loops      = 5;
    $assistant_text = '';

    for ($i = 0; $i < $max_loops; $i++) {

        $response_data = callClaude($loop_messages, $mcp_tools, $SYSTEM_PROMPT);

        $stop_reason = $response_data['stop_reason'] ?? 'end_turn';
        $content     = $response_data['content']     ?? [];

        // Claude përfundoi — merr tekstin final
        if ($stop_reason === 'end_turn') {
            foreach ($content as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $assistant_text .= $block['text'];
                }
            }
            break;
        }

        // Claude dëshiron të thirr tool(s)
        if ($stop_reason === 'tool_use') {

            // Shto përgjigjen e assistant-it në histori
            $loop_messages[] = ['role' => 'assistant', 'content' => $content];

            // Ekzekuto çdo tool_use block
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

            // Shto rezultatet si mesazh user
            $loop_messages[] = ['role' => 'user', 'content' => $tool_results];
            continue;
        }

        // Stop i papritur
        break;
    }

    if (empty($assistant_text)) {
        $assistant_text = 'Nuk mund të gjeneroj një përgjigje tani. Ju lutem provoni përsëri.';
    }

    error_log('Noteria Chatbot: User message processed successfully');

    http_response_code(200);
    echo json_encode([
        'success'   => true,
        'message'   => $assistant_text,
        'timestamp' => date('Y-m-d H:i:s'),
    ]);

} catch (Exception $e) {
    error_log('Chatbot API Error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
        'contact' => '+383 44 000 000',
        'debug'   => getenv('APP_ENV') === 'development',
    ]);
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function callClaude(array $messages, array $tools, string $system): array
{
    if (empty(CLAUDE_API_KEY) || CLAUDE_API_KEY === 'sk-ant-v1-YOUR-API-KEY-HERE') {
        throw new Exception('API key not configured. Add CLAUDE_API_KEY to .env. Get key: https://console.anthropic.com/');
    }

    $ch = curl_init(CLAUDE_ENDPOINT);
    if ($ch === false) throw new Exception('Failed to initialize cURL');

    $body = json_encode([
        'model'      => CLAUDE_MODEL,
        'max_tokens' => 1200,
        'system'     => $system,
        'tools'      => $tools,
        'messages'   => $messages,
    ]);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: '          . CLAUDE_API_KEY,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $response   = curl_exec($ch);
    $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) throw new Exception('cURL error: ' . $curl_error);

    $data = json_decode($response, true);

    if ($http_code !== 200) {
        error_log('Claude API Error (' . $http_code . '): ' . $response);
        $msg = $data['error']['message'] ?? 'API returned status: ' . $http_code;
        throw new Exception('API Error: ' . $msg);
    }

    if (!isset($data['content'])) {
        throw new Exception('Invalid response structure from Claude API');
    }

    return $data;
}

function buildAnthropicTools(): array
{
    // Merr listën nga MCP server, konverto në formatin e Anthropic API
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