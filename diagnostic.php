<?php
/**
 * Diagnostic Script - Kontrollon problemet me regjistrimin
 * Aksesohet direkt në browser: http://localhost/noteria/diagnostic.php
 */

echo "<pre style='background: #f4f4f4; padding: 20px; border-radius: 8px; font-family: monospace;'>";
echo "🔍 SISTEM DIAGNOSTIK\n";
echo str_repeat("=", 80) . "\n\n";

// 1. Database Connection
echo "1️⃣  LIDHJA ME DATABAZËN\n";
echo str_repeat("-", 80) . "\n";
try {
    require_once 'confidb.php';
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    echo "✅ Lidhja me databazën u arrit me sukses\n";
    echo "   DSN: " . $dsn . "\n";
    echo "   Përdorues: " . $dbUser . "\n\n";
} catch (PDOException $e) {
    echo "❌ Gabim në lidhjen me databazën\n";
    echo "   Mesazhi: " . $e->getMessage() . "\n";
    echo "   Kodi: " . $e->getCode() . "\n\n";
    exit;
}

// 2. Check advertisers table
echo "2️⃣  TABELA 'ADVERTISERS'\n";
echo str_repeat("-", 80) . "\n";
try {
    $tables = $pdo->query("SHOW TABLES LIKE 'advertisers'")->fetchAll();
    if (empty($tables)) {
        echo "❌ Tabela 'advertisers' nuk ekziston!\n";
        echo "   Zgjidhje: Aksesu http://localhost/noteria/setup_advertisers_table.php\n\n";
    } else {
        echo "✅ Tabela 'advertisers' ekziston\n";
        $columns = $pdo->query("DESC advertisers")->fetchAll(PDO::FETCH_ASSOC);
        echo "   Kolonat:\n";
        foreach ($columns as $col) {
            echo "   - " . $col['Field'] . " (" . $col['Type'] . ")\n";
        }
        
        // Test insert
        echo "\n   🧪 Test INSERT:\n";
        try {
            $testEmail = 'test_' . time() . '@example.com';
            $stmt = $pdo->prepare("INSERT INTO advertisers (company_name, email, phone, website, category, description, business_registration, subscription_status) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([
                'Test Company',
                $testEmail,
                '+355 69 123 4567',
                'https://test.com',
                'tjeter',
                'Test description',
                'L123456789',
                'pending'
            ]);
            
            if ($result) {
                echo "   ✅ INSERT test u arrit me sukses\n";
                echo "   📧 Email i testit: " . $testEmail . "\n";
                
                // Fshini rekordin e testit
                $deleteStmt = $pdo->prepare("DELETE FROM advertisers WHERE email = ?");
                $deleteStmt->execute([$testEmail]);
                echo "   🗑️  Rekord i testit u fshi\n";
            }
        } catch (PDOException $e) {
            echo "   ❌ INSERT test dështoi\n";
            echo "   Mesazhi: " . $e->getMessage() . "\n";
            echo "   Kodi: " . $e->getCode() . "\n";
        }
        echo "\n";
    }
} catch (PDOException $e) {
    echo "❌ Gabim kur kontrollohet tabela\n";
    echo "   Mesazhi: " . $e->getMessage() . "\n\n";
}

// 3. Check file permissions
echo "3️⃣  LEJET E FAJLLEVE\n";
echo str_repeat("-", 80) . "\n";
$files = [
    'become-advertiser.php',
    'confidb.php',
    'setup_advertisers_table.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $perms = fileperms($file);
        $readable = is_readable($file) ? '✅ READABLE' : '❌ NOT READABLE';
        $writable = is_writable($file) ? '✅ WRITABLE' : '❌ NOT WRITABLE';
        echo "$file: $readable, $writable\n";
    } else {
        echo "❌ $file - NUK EKZISTON\n";
    }
}
echo "\n";

// 4. PHP Configuration
echo "4️⃣  KONFIGURIMI I PHP\n";
echo str_repeat("-", 80) . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "PDO Extension: " . (extension_loaded('pdo') ? '✅ Loaded' : '❌ Not loaded') . "\n";
echo "PDO MySQL: " . (extension_loaded('pdo_mysql') ? '✅ Loaded' : '❌ Not loaded') . "\n";
echo "File Uploads: " . (ini_get('file_uploads') ? '✅ Enabled' : '❌ Disabled') . "\n";
echo "Max Upload Size: " . ini_get('upload_max_filesize') . "\n";
echo "POST Max Size: " . ini_get('post_max_size') . "\n";
echo "Error Reporting: " . (ini_get('error_reporting') ? 'Enabled' : 'Disabled') . "\n";
echo "Display Errors: " . (ini_get('display_errors') ? '✅ Enabled' : '❌ Disabled') . "\n\n";

// 5. Session
echo "5️⃣  SESIONI\n";
echo str_repeat("-", 80) . "\n";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "✅ Sesioni aktiv\n";
    echo "Session ID: " . session_id() . "\n";
} else {
    echo "❌ Sesioni nuk është aktiv\n";
}
echo "\n";

echo "🎯 PËRFUNDIM\n";
echo str_repeat("=", 80) . "\n";
echo "Nëse të gjitha testet janë ✅, forma duhet të funksionojë.\n";
echo "Nëse ka ndonjë ❌, ndjekie zgjidhjen e sugjeruar.\n";
echo "</pre>";
?>
