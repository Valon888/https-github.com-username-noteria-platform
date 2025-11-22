<?php
/**
 * Skript për të eksportuar kodet unike të një përdoruesi në CSV/JSON
 * Përdorimi: php export_user_codes.php user_id [format]
 * Format: csv ose json (default: csv)
 */

require_once 'confidb.php';

if (php_sapi_name() !== 'cli') {
    die("Ky skript duhet të ekzekutohet përmes CLI\n");
}

if ($argc < 2) {
    echo "Përdorimi: php export_user_codes.php <user_id> [format]\n";
    echo "Format: csv ose json (default: csv)\n";
    exit(1);
}

$user_id = intval($argv[1]);
$format = isset($argv[2]) ? strtolower($argv[2]) : 'csv';

if (!in_array($format, ['csv', 'json'])) {
    echo "Format i pavlefshëm. Përdor 'csv' ose 'json'.\n";
    exit(1);
}

// Kontrollo nëse përdoruesi ekziston
$stmt = $pdo->prepare("SELECT emri, mbiemri, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    echo "Përdoruesi me ID $user_id nuk ekziston.\n";
    exit(1);
}

// Merr të gjithë kodet e një përdoruesi
$stmt = $pdo->prepare("
    SELECT code, used, generated_at 
    FROM user_unique_codes 
    WHERE user_id = ? 
    ORDER BY generated_at
");
$stmt->execute([$user_id]);
$codes = $stmt->fetchAll();

if (empty($codes)) {
    echo "Ky përdorues nuk ka asnjë kod.\n";
    exit(0);
}

$filename = "codes_{$user_id}_{$user['emri']}_{$format}" . ($format === 'json' ? '.json' : '.csv');

if ($format === 'csv') {
    // Eksporto në CSV
    $fp = fopen($filename, 'w');
    
    // Header
    fputcsv($fp, ['Kodi', 'I Përdorur', 'Data e Gjenerimit']);
    
    // Të dhëna
    foreach ($codes as $code) {
        fputcsv($fp, [
            $code['code'],
            $code['used'] ? 'Po' : 'Jo',
            $code['generated_at']
        ]);
    }
    
    fclose($fp);
    echo "✅ CSV eksportua në: {$filename}\n";
    echo "Kodet totale: " . count($codes) . "\n";
    
} else {
    // Eksporto në JSON
    $data = [
        'user' => [
            'id' => $user_id,
            'emri' => $user['emri'],
            'mbiemri' => $user['mbiemri'],
            'email' => $user['email']
        ],
        'total_codes' => count($codes),
        'available_codes' => count(array_filter($codes, fn($c) => $c['used'] == 0)),
        'used_codes' => count(array_filter($codes, fn($c) => $c['used'] == 1)),
        'codes' => $codes
    ];
    
    file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "✅ JSON eksportua në: {$filename}\n";
    echo "Kodet totale: " . count($codes) . "\n";
}

?>
