<?php
/**
 * ANALYTICS DIAGNOSTIC
 * Checks database setup and data availability
 */

require_once __DIR__ . '/config.php';

echo "<pre style='background: #f0f0f0; padding: 20px; border-radius: 5px; font-family: monospace;'>";
echo "=== ANALYTICS DIAGNOSTIC ===\n\n";

// Check tables
$tables_to_check = [
    'countries',
    'country_pricing',
    'country_regulations',
    'reservations',
    'payments'
];

echo "1. DATABASE TABLES:\n";
foreach ($tables_to_check as $table) {
    try {
        $result = $pdo->query("DESCRIBE $table");
        $count = $result->rowCount();
        echo "   ✅ $table (exists)\n";
    } catch (Exception $e) {
        echo "   ❌ $table (missing: " . $e->getMessage() . ")\n";
    }
}

// Check Kosovo data
echo "\n2. KOSOVO CONFIGURATION:\n";
try {
    $result = $pdo->query("SELECT * FROM countries WHERE code='XK'");
    $country = $result->fetch();
    if ($country) {
        echo "   ✅ Kosovo (XK) configured\n";
        echo "      Name: " . $country['name_sq'] . "\n";
        echo "      Currency: " . $country['currency'] . "\n";
    } else {
        echo "   ❌ Kosovo not found\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Check pricing
echo "\n3. PRICING SERVICES:\n";
try {
    $result = $pdo->query("SELECT COUNT(*) as count FROM country_pricing WHERE country_code='XK'");
    $row = $result->fetch();
    echo "   ✅ Services configured: " . $row['count'] . "\n";
    
    if ($row['count'] > 0) {
        $services = $pdo->query("SELECT service_name_sq, base_price FROM country_pricing WHERE country_code='XK' LIMIT 5");
        foreach ($services as $service) {
            echo "      - " . $service['service_name_sq'] . ": €" . $service['base_price'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Check reservations data
echo "\n4. RESERVATION DATA:\n";
try {
    $result = $pdo->query("SELECT COUNT(*) as count FROM reservations");
    $row = $result->fetch();
    echo "   ✅ Total reservations: " . $row['count'] . "\n";
    
    $result = $pdo->query("SELECT COUNT(*) as count FROM reservations WHERE country_code='XK'");
    $row = $result->fetch();
    echo "   ✅ Kosovo reservations: " . $row['count'] . "\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Check payments data
echo "\n5. PAYMENT DATA:\n";
try {
    $result = $pdo->query("SELECT COUNT(*) as count FROM payments");
    $row = $result->fetch();
    echo "   ✅ Total payments: " . $row['count'] . "\n";
    
    $result = $pdo->query("SELECT SUM(amount) as total FROM payments");
    $row = $result->fetch();
    echo "   ✅ Total revenue: €" . ($row['total'] ?? 0) . "\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== READY FOR ANALYTICS ===\n";
echo "</pre>";

// Test API
echo "<h2>API Test</h2>";
echo "<button onclick=\"testAPI()\">Test Analytics API</button>";
echo "<div id='api-result'></div>";

?>
<script>
function testAPI() {
    fetch('/api/analytics_api.php?test=1&action=summary&days=30')
        .then(r => r.json())
        .then(data => {
            document.getElementById('api-result').innerHTML = 
                '<pre style="background: #e8f4f8; padding: 10px; border-radius: 5px; margin-top: 10px;">' + 
                JSON.stringify(data, null, 2) + 
                '</pre>';
        })
        .catch(e => {
            document.getElementById('api-result').innerHTML = 
                '<div style="color: red; padding: 10px;">Error: ' + e.message + '</div>';
        });
}
</script>
