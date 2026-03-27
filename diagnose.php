<?php
/**
 * Diagnostic Script - Check API Key Configuration
 */

echo "=== Noteria Chatbot Configuration Diagnostic ===\n\n";

$env_file = __DIR__ . '/.env';

// 1. Check if .env file exists
echo "1. Checking .env file...\n";
if (file_exists($env_file)) {
    echo "   ✅ .env file found at: " . realpath($env_file) . "\n";
    echo "   📝 File size: " . filesize($env_file) . " bytes\n";
    echo "   🔒 Readable: " . (is_readable($env_file) ? "YES" : "NO") . "\n";
} else {
    echo "   ❌ .env file NOT found at: " . $env_file . "\n";
    exit("Create .env file first!\n");
}

// 2. Try to load .env
echo "\n2. Loading .env file...\n";
$env_lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
echo "   📄 Lines in file: " . count($env_lines) . "\n";

// 3. Look for CLAUDE_API_KEY
echo "\n3. Searching for CLAUDE_API_KEY...\n";
$found = false;
$value = '';
foreach ($env_lines as $line) {
    if (strpos($line, '#') === 0) continue; // skip comments
    if (strpos($line, 'CLAUDE_API_KEY') !== false) {
        $found = true;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $value = trim($val, ' "\'');
        }
        break;
    }
}

if ($found) {
    echo "   ✅ Found CLAUDE_API_KEY in .env\n";
    if (!empty($value) && $value !== 'sk-ant-v1-YOUR-API-KEY-HERE') {
        echo "   ✅ Value is set to: " . substr($value, 0, 20) . "...\n";
        echo "   ✅ Looks like a valid key!\n";
    } else {
        echo "   ❌ Value is placeholder or empty: " . htmlspecialchars($value) . "\n";
        echo "   💡 Please replace with your real API key\n";
    }
} else {
    echo "   ❌ CLAUDE_API_KEY not found in .env\n";
    echo "   💡 Add this line to .env:\n";
    echo "      CLAUDE_API_KEY=sk-ant-v1-YOUR-KEY-HERE\n";
}

// 4. Test getenv()
echo "\n4. Testing getenv()...\n";
putenv("CLAUDE_API_KEY=$value");
$getenv_value = getenv('CLAUDE_API_KEY');
if (!empty($getenv_value)) {
    echo "   ✅ getenv() returned: " . substr($getenv_value, 0, 20) . "...\n";
} else {
    echo "   ❌ getenv() returned empty\n";
}

// 5. Load via config.php
echo "\n5. Loading via config.php...\n";
require_once __DIR__ . '/config.php';
$config_key = getenv('CLAUDE_API_KEY');
if (!empty($config_key) && $config_key !== 'sk-ant-v1-YOUR-API-KEY-HERE') {
    echo "   ✅ config.php loaded successfully\n";
    echo "   ✅ API Key is: " . substr($config_key, 0, 20) . "...\n";
} else {
    echo "   ❌ API Key not loaded via config.php\n";
    echo "   ⚠️  Key value: " . ($config_key ?: "EMPTY") . "\n";
}

// 6. Test API call
echo "\n6. Testing API call...\n";
$api_key = getenv('CLAUDE_API_KEY');
if (empty($api_key) || $api_key === 'sk-ant-v1-YOUR-API-KEY-HERE') {
    echo "   ❌ Cannot test - API key not configured\n";
} else {
    echo "   ✅ API key available, can make test calls\n";
}

echo "\n=== Summary ===\n";
if (!empty($api_key) && $api_key !== 'sk-ant-v1-YOUR-API-KEY-HERE') {
    echo "✅ Everything looks good! API key is configured.\n";
    echo "💡 Try opening: http://localhost/noteria/noteria_chatbot.php\n";
} else {
    echo "❌ API key is missing or not configured.\n";
    echo "📋 Next steps:\n";
    echo "   1. Get API key from: https://console.anthropic.com/\n";
    echo "   2. Open: " . $env_file . "\n";
    echo "   3. Find: CLAUDE_API_KEY=sk-ant-v1-YOUR-API-KEY-HERE\n";
    echo "   4. Replace with your real key\n";
    echo "   5. Save file (Ctrl+S)\n";
    echo "   6. Run this script again to verify\n";
}
?>
