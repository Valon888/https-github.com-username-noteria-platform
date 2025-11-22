<?php
/**
 * Script për të gjeneruar 1 milion+ kode unike për çdo përdorues në sistem
 * Kur thirret përmes CLI: php generate_user_codes.php
 * Kur thirret përmes AJAX: http://localhost/noteria/generate_user_codes.php?user_id=1&count=1000000
 */

require_once 'confidb.php';

// Kontrollo nëse është CLI ose web request
$is_cli = php_sapi_name() === 'cli';
$user_id = null;
$code_count = 1000000; // Default: 1 milion kode

if ($is_cli) {
    // Nëse është CLI, lexo argumentet
    if ($argc < 2) {
        echo "Përdorimi: php generate_user_codes.php <user_id> [count]\n";
        echo "Shembull: php generate_user_codes.php 1 1000000\n";
        exit(1);
    }
    $user_id = intval($argv[1]);
    $code_count = isset($argv[2]) ? intval($argv[2]) : 1000000;
} else {
    // Nëse është web request, lexo parametrat
    require_once 'config.php';
    session_start();
    
    // Kontrollo nëse përdoruesi është admin
    if (!isset($_SESSION['user_id']) || $_SESSION['roli'] !== 'admin') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Nuk keni leje të aksesoni këtë faqe']);
        exit(1);
    }
    
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
    $code_count = isset($_GET['count']) ? intval($_GET['count']) : 1000000;
    
    if (!$user_id) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'user_id nuk u dërgo']);
        exit(1);
    }
}

// Kontrollo nëse përdoruesi ekziston
$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
if (!$stmt->fetch()) {
    $msg = "Përdoruesi me ID $user_id nuk ekziston";
    if (!$is_cli) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $msg]);
    } else {
        echo "Gabim: $msg\n";
    }
    exit(1);
}

// Kontrollo sa kode ekzistojnë tashmë
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM user_unique_codes WHERE user_id = ?");
$stmt->execute([$user_id]);
$result = $stmt->fetch();
$existing_codes = $result['cnt'];

if ($existing_codes >= $code_count) {
    $msg = "Përdoruesi tashmë ka $existing_codes kode (kërkuat $code_count)";
    if (!$is_cli) {
        header('Content-Type: application/json');
        echo json_encode(['message' => $msg, 'existing_codes' => $existing_codes]);
    } else {
        echo "$msg\n";
    }
    exit(0);
}

$codes_to_generate = $code_count - $existing_codes;

// Gjenero kodet unike
echo "Duke gjeneruar $codes_to_generate kode unike për përdoruesin $user_id...\n";

$batch_size = 5000; // Inserto në grupe të 5000
$total_inserted = 0;
$failed = 0;

try {
    $pdo->beginTransaction();
    
    for ($batch = 0; $batch < ceil($codes_to_generate / $batch_size); $batch++) {
        $batch_codes = [];
        
        // Gjenero kode unike për këtë grup
        for ($i = 0; $i < $batch_size && $total_inserted < $codes_to_generate; $i++) {
            // Gjenero kod: kombinim i UUID-short + timestamp + random
            $code = strtoupper(substr(bin2hex(random_bytes(8)), 0, 16));
            $batch_codes[] = $code;
            $total_inserted++;
        }
        
        // Inserto grupin e kodeve
        $placeholders = implode(',', array_fill(0, count($batch_codes), '(?, ?)'));
        $sql = "INSERT INTO user_unique_codes (user_id, code) VALUES $placeholders";
        
        $stmt = $pdo->prepare($sql);
        $values = [];
        foreach ($batch_codes as $code) {
            $values[] = $user_id;
            $values[] = $code;
        }
        
        if (!$stmt->execute($values)) {
            $failed += count($batch_codes);
        }
        
        $progress = min($total_inserted, $codes_to_generate);
        $percentage = round(($progress / $codes_to_generate) * 100, 2);
        echo "Përparim: $progress / $codes_to_generate ($percentage%)\n";
    }
    
    $pdo->commit();
    
    $msg = "Përfunduar! U gjeneron $total_inserted kode unike (Dështimet: $failed)";
    echo "$msg\n";
    
    if (!$is_cli) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $msg,
            'generated' => $total_inserted,
            'failed' => $failed,
            'total_for_user' => $existing_codes + $total_inserted
        ]);
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    $error_msg = "Gabim gjatë gjenerimit të kodeve: " . $e->getMessage();
    
    if (!$is_cli) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $error_msg]);
    } else {
        echo "Gabim: $error_msg\n";
    }
    exit(1);
}
?>
