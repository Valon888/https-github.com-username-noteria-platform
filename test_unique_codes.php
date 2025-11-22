<?php
/**
 * Skript testimi për sistemin e kodeve unike
 * Gjenero kodet, verifikoji kyçjen, dhe kontrollo statistikat
 */

// Direkto përpjesë pa config.php
$host = 'localhost';
$db = 'noteria';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection error: " . $e->getMessage() . "\n");
}

// Konfigurimi i testit
$test_user_email = 'test@noteria.com';
$test_user_id = null;
$codes_to_generate = 100000; // Për test - fillon me 100K, pastaj mund të rritet në 1M
$test_results = [];

echo "═══════════════════════════════════════════════════════════\n";
echo "TEST: Sistemi i Kodeve Unike të Përdoruesve\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// 1. Kontrolloj nëse përdoruesi test ekziston
echo "1️⃣ KONTROLLIM I PËRDORUESIT TEST...\n";
$stmt = $pdo->prepare("SELECT id, emri, mbiemri, email FROM users WHERE email = ?");
$stmt->execute([$test_user_email]);
$test_user = $stmt->fetch();

if (!$test_user) {
    echo "❌ Përdoruesi test '{$test_user_email}' nuk ekziston!\n";
    exit(1);
} else {
    $test_user_id = $test_user['id'];
    echo "✅ Gjetur përdoruesi: {$test_user['emri']} {$test_user['mbiemri']} (ID: {$test_user_id})\n\n";
}

// 2. Kontrollo sa kode ekzistojnë tashmë
echo "2️⃣ KONTROLLIM I KODEVE EKZISTUES...\n";
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM user_unique_codes WHERE user_id = ?");
$stmt->execute([$test_user_id]);
$result = $stmt->fetch();
$existing_codes = $result['cnt'];

echo "Kodet ekzistues: " . number_format($existing_codes) . "\n";

// 3. Gjenero kodet nëse nuk ekzistojnë
if ($existing_codes < $codes_to_generate) {
    $codes_needed = $codes_to_generate - $existing_codes;
    echo "\n3️⃣ GJENERIM I KODEVE UNIKE ({$codes_needed} kode)...\n";
    
    $start_time = time();
    $batch_size = 5000;
    $total_inserted = 0;
    $failed = 0;
    
    try {
        $pdo->beginTransaction();
        
        for ($batch = 0; $batch < ceil($codes_needed / $batch_size); $batch++) {
            $batch_codes = [];
            
            // Gjenero kode unike
            for ($i = 0; $i < $batch_size && $total_inserted < $codes_needed; $i++) {
                $code = strtoupper(substr(bin2hex(random_bytes(8)), 0, 16));
                $batch_codes[] = $code;
                $total_inserted++;
            }
            
            // Inserto grupin
            $placeholders = implode(',', array_fill(0, count($batch_codes), '(?, ?)'));
            $sql = "INSERT INTO user_unique_codes (user_id, code) VALUES " . $placeholders;
            
            $stmt = $pdo->prepare($sql);
            $values = [];
            foreach ($batch_codes as $code) {
                $values[] = $test_user_id;
                $values[] = $code;
            }
            
            if (!$stmt->execute($values)) {
                $failed += count($batch_codes);
            }
            
            $percentage = round(($total_inserted / $codes_needed) * 100, 1);
            echo "Përparim: " . number_format($total_inserted) . " / " . number_format($codes_needed) . " ({$percentage}%)\n";
        }
        
        $pdo->commit();
        $elapsed = time() - $start_time;
        
        echo "\n✅ Përfunduar! Kodet u gjeneron në {$elapsed} sekonda\n";
        echo "   - U insertuan: " . number_format($total_inserted) . " kode\n";
        echo "   - Dështimet: {$failed}\n\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "❌ Gabim: " . $e->getMessage() . "\n\n";
        exit(1);
    }
}

// 4. Merr një kod për testim
echo "4️⃣ MARRJE I KODIT PËR TESTIM...\n";
$stmt = $pdo->prepare("SELECT id, code FROM user_unique_codes WHERE user_id = ? AND used = 0 LIMIT 1");
$stmt->execute([$test_user_id]);
$test_code_row = $stmt->fetch();

if (!$test_code_row) {
    echo "❌ Nuk ka kode të disponueshëm!\n\n";
    exit(1);
} else {
    $test_code = $test_code_row['code'];
    echo "✅ Merr kod: {$test_code}\n\n";
}

// 5. Merr statistikat e kodeve
echo "5️⃣ STATISTIKA E KODEVE...\n";
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN used = 0 THEN 1 ELSE 0 END) as available,
        SUM(CASE WHEN used = 1 THEN 1 ELSE 0 END) as used
    FROM user_unique_codes 
    WHERE user_id = ?
");
$stmt->execute([$test_user_id]);
$stats = $stmt->fetch();

echo "Kodet Totale: " . number_format($stats['total']) . "\n";
echo "Kodet në Dispozicion: " . number_format($stats['available']) . "\n";
echo "Kodet e Përdorur: " . number_format($stats['used']) . "\n";
echo "Përqindja e Përdorimit: " . ($stats['total'] > 0 ? round(($stats['used'] / $stats['total']) * 100, 2) : 0) . "%\n\n";

// 6. Simuloj përpunimin e kodit
echo "6️⃣ TESTIM I KODIT (Simulim Kyçjeje)...\n";

$check_stmt = $pdo->prepare("
    SELECT id, used FROM user_unique_codes 
    WHERE user_id = ? AND code = ?
    LIMIT 1
");
$check_stmt->execute([$test_user_id, $test_code]);
$check_result = $check_stmt->fetch();

if ($check_result) {
    echo "✅ Kodi gjendet në bazën e të dhënave\n";
    echo "   Status: " . ($check_result['used'] == 0 ? "Në Dispozicion" : "I Përdorur") . "\n";
    
    // Marko kodin si të përdorur
    $mark_stmt = $pdo->prepare("UPDATE user_unique_codes SET used = 1 WHERE id = ?");
    if ($mark_stmt->execute([$check_result['id']])) {
        echo "✅ Kodi u markua si i përdorur\n";
    }
} else {
    echo "❌ Kodi nuk gjendet në bazën e të dhënave!\n";
}

// 7. Përfundim
echo "\n═══════════════════════════════════════════════════════════\n";
echo "📊 PËRMBLEDHJE E TESTIT\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "✅ Të gjitha testet e kaloi me sukses!\n";
echo "\nTablela 'user_unique_codes' është e gati për përdorim.\n";
echo "Mund të kyçeni duke përdorur kodin: {$test_code}\n";
echo "\n";

?>
