<?php
// Test script për email_config.php
// Teston nëse ka gabime në array offset

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🧪 Test Email Config - Array Offset Check</h2>";

try {
    // Test 1: Include email_config.php
    echo "<h3>Test 1: Loading email_config.php</h3>";
    require_once 'email_config.php';
    echo "✅ email_config.php u ngarkua me sukses<br>";
    
    // Test 2: Kontrollo nëse $email_config ekziston
    echo "<h3>Test 2: Checking \$email_config variable</h3>";
    if (isset($email_config)) {
        echo "✅ \$email_config ekziston<br>";
        echo "📋 Përmbajtja: " . print_r($email_config, true) . "<br>";
    } else {
        echo "❌ \$email_config nuk ekziston<br>";
    }
    
    // Test 3: Test sendEmailWithSMTP function
    echo "<h3>Test 3: Testing sendEmailWithSMTP function</h3>";
    if (function_exists('sendEmailWithSMTP')) {
        echo "✅ sendEmailWithSMTP function ekziston<br>";
        $result = sendEmailWithSMTP('test@example.com', 'Test Subject', 'Test Message');
        echo "📧 Rezultati i test email: " . ($result ? "SUCCESS" : "FAILED") . "<br>";
    } else {
        echo "❌ sendEmailWithSMTP function nuk ekziston<br>";
    }
    
    // Test 4: Test testEmailConfiguration function
    echo "<h3>Test 4: Testing testEmailConfiguration function</h3>";
    if (function_exists('testEmailConfiguration')) {
        echo "✅ testEmailConfiguration function ekziston<br>";
        echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
        testEmailConfiguration();
        echo "</div>";
    } else {
        echo "❌ testEmailConfiguration function nuk ekziston<br>";
    }
    
    // Test 5: Test template functions
    echo "<h3>Test 5: Testing template functions</h3>";
    if (function_exists('getRegistrationSuccessEmail')) {
        echo "✅ getRegistrationSuccessEmail function ekziston<br>";
        $template = getRegistrationSuccessEmail('Test Office', 'TEST123', 'test@example.com');
        echo "📄 Template i gjeneruar: " . (strlen($template) > 0 ? "SUCCESS (" . strlen($template) . " characters)" : "FAILED") . "<br>";
    } else {
        echo "❌ getRegistrationSuccessEmail function nuk ekziston<br>";
    }
    
    // Test 6: Test email sending functions
    echo "<h3>Test 6: Testing sendRegistrationEmail function</h3>";
    if (function_exists('sendRegistrationEmail')) {
        echo "✅ sendRegistrationEmail function ekziston<br>";
        $result = sendRegistrationEmail('test@example.com', 'Test Office', 'TEST123');
        echo "📧 Rezultati i sendRegistrationEmail: " . ($result ? "SUCCESS" : "FAILED") . "<br>";
    } else {
        echo "❌ sendRegistrationEmail function nuk ekziston<br>";
    }

} catch (Exception $e) {
    echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
    echo "❌ <strong>Gabim:</strong> " . $e->getMessage() . "<br>";
    echo "📍 <strong>Fajlli:</strong> " . $e->getFile() . "<br>";
    echo "📍 <strong>Linja:</strong> " . $e->getLine() . "<br>";
    echo "</div>";
} catch (Error $e) {
    echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
    echo "❌ <strong>PHP Error:</strong> " . $e->getMessage() . "<br>";
    echo "📍 <strong>Fajlli:</strong> " . $e->getFile() . "<br>";
    echo "📍 <strong>Linja:</strong> " . $e->getLine() . "<br>";
    echo "</div>";
}

echo "<hr>";
echo "<h3>💡 Shënim:</h3>";
echo "<p>Nëse nuk ka gabime më sipër, atëherë array offset problemi është zgjidhur.</p>";
echo "<p>Nëse ende ka gabime, kontrolloni PHP error log në: <code>d:\\xampp\\php\\logs\\php_error_log</code></p>";
?>