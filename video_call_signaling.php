<?php
// video_call_signaling.php - Implementimi i një simple Signaling Server për WebRTC
// Kjo është një zgjidhje e thjeshtë në PHP, por për një implementim më të mirë rekomandohet përdorimi i Node.js me Socket.io

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Për të ruajtur gjendjen përdorim file-cache
$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// Pastrim i cache file-ve të vjetra (më shumë se 1 orë)
$files = glob($cacheDir . '/*');
foreach ($files as $file) {
    if (is_file($file) && (time() - filemtime($file) > 3600)) {
        unlink($file);
    }
}

// Funksion për të ruajtur mesazhe në dhomë
function saveRoomMessage($roomId, $messageType, $data) {
    global $cacheDir;
    
    $roomFile = $cacheDir . '/room_' . preg_replace('/[^a-zA-Z0-9_]/', '', $roomId) . '.json';
    
    $messages = [];
    if (file_exists($roomFile)) {
        $content = file_get_contents($roomFile);
        if (!empty($content)) {
            $messages = json_decode($content, true) ?: [];
        }
    }
    
    // Shto mesazhin e ri
    $messages[] = [
        'type' => $messageType,
        'data' => $data,
        'timestamp' => time()
    ];
    
    // Ruaj vetëm 50 mesazhet e fundit
    if (count($messages) > 50) {
        $messages = array_slice($messages, -50);
    }
    
    // Ruaj në file
    file_put_contents($roomFile, json_encode($messages));
    
    return true;
}

// Funksion për të marrë mesazhe të reja
function getNewMessages($roomId, $lastTimestamp) {
    global $cacheDir;
    
    $roomFile = $cacheDir . '/room_' . preg_replace('/[^a-zA-Z0-9_]/', '', $roomId) . '.json';
    
    if (!file_exists($roomFile)) {
        return [];
    }
    
    $content = file_get_contents($roomFile);
    if (empty($content)) {
        return [];
    }
    
    $messages = json_decode($content, true) ?: [];
    
    // Kthe vetëm mesazhet e reja
    return array_filter($messages, function($msg) use ($lastTimestamp) {
        return $msg['timestamp'] > $lastTimestamp;
    });
}

// Kontrollojmë të dhënat e dërguara
$requestMethod = $_SERVER['REQUEST_METHOD'];

if ($requestMethod === 'OPTIONS') {
    // Përgjigju për preflighted requests
    header('HTTP/1.1 200 OK');
    exit;
}

// Marrim të dhënat e dërguara
$requestData = json_decode(file_get_contents('php://input'), true);

if (!$requestData || !isset($requestData['action'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Kërkesë e pavlefshme'
    ]);
    exit;
}

// Veprimet e ndryshme që mund të kryhen nga klienti
$action = $requestData['action'];
$roomId = $requestData['roomId'] ?? '';
$userId = $requestData['userId'] ?? '';

if (empty($roomId) || empty($userId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Mungon ID e dhomës ose përdoruesit'
    ]);
    exit;
}

// Përgjigju sipas veprimit
switch ($action) {
    case 'join':
        // Përdoruesi i bashkohet dhomës
        saveRoomMessage($roomId, 'join', [
            'userId' => $userId,
            'username' => $requestData['username'] ?? 'Përdorues'
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'U bashkuat në dhomën ' . $roomId
        ]);
        break;
        
    case 'offer':
        // Ruaj ofertën SDP
        if (!isset($requestData['offer'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Mungon oferta SDP'
            ]);
            exit;
        }
        
        saveRoomMessage($roomId, 'offer', [
            'userId' => $userId,
            'offer' => $requestData['offer']
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Oferta u ruajt me sukses'
        ]);
        break;
        
    case 'answer':
        // Ruaj përgjigjen SDP
        if (!isset($requestData['answer'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Mungon përgjigjja SDP'
            ]);
            exit;
        }
        
        saveRoomMessage($roomId, 'answer', [
            'userId' => $userId,
            'answer' => $requestData['answer']
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Përgjigjja u ruajt me sukses'
        ]);
        break;
        
    case 'candidate':
        // Ruaj ICE kandidatët
        if (!isset($requestData['candidate'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Mungon kandidati ICE'
            ]);
            exit;
        }
        
        saveRoomMessage($roomId, 'candidate', [
            'userId' => $userId,
            'candidate' => $requestData['candidate']
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Kandidati u ruajt me sukses'
        ]);
        break;
        
    case 'poll':
        // Kërko për mesazhe të reja
        $lastTimestamp = isset($requestData['lastTimestamp']) ? (int) $requestData['lastTimestamp'] : 0;
        $messages = getNewMessages($roomId, $lastTimestamp);
        
        // Filtroj mesazhet që nuk janë për këtë përdorues (nuk dërgojmë ofertën/përgjigjen e vetë përdoruesit)
        $filteredMessages = array_filter($messages, function($msg) use ($userId) {
            // Kthej vetëm mesazhet që nuk janë nga ky përdorues ose që janë për të gjithë (join, leave)
            return ($msg['data']['userId'] !== $userId) || in_array($msg['type'], ['join', 'leave']);
        });
        
        echo json_encode([
            'success' => true,
            'messages' => array_values($filteredMessages),
            'timestamp' => time()
        ]);
        break;
        
    case 'leave':
        // Përdoruesi largohet nga dhoma
        saveRoomMessage($roomId, 'leave', [
            'userId' => $userId
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'U larguat nga dhoma ' . $roomId
        ]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Veprim i pavlefshëm'
        ]);
        break;
}
?>