<?php
// check_token_table.php - Kontrollon nëse tabela api_tokens ekziston dhe ka token-a
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';

echo "<h1>Kontroll i Tabelave API dhe Token-ave</h1>";

try {
    // Kontrollo tabelën api_tokens
    $stmt = $pdo->query("SHOW TABLES LIKE 'api_tokens'");
    $apiTokensExists = $stmt->rowCount() > 0;
    
    if ($apiTokensExists) {
        echo "<p style='color: green;'>✅ Tabela api_tokens ekziston.</p>";
        
        // Kontrollo nëse ka token-a
        $stmt = $pdo->query("SELECT COUNT(*) as token_count FROM api_tokens");
        $tokenCount = $stmt->fetchColumn();
        
        if ($tokenCount > 0) {
            echo "<p style='color: green;'>✅ Ka $tokenCount token në tabelën api_tokens.</p>";
            
            // Shfaq tokenet e disponueshme
            $stmt = $pdo->query("SELECT token, description, created_at, expired_at FROM api_tokens");
            $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h2>Token-at e disponueshëm:</h2>";
            echo "<ul>";
            foreach ($tokens as $token) {
                $isExpired = !empty($token['expired_at']) && strtotime($token['expired_at']) < time();
                $style = $isExpired ? "color: red;" : "color: green;";
                
                echo "<li style='$style'>";
                echo "<strong>Token:</strong> " . htmlspecialchars($token['token']) . "<br>";
                echo "<strong>Përshkrimi:</strong> " . htmlspecialchars($token['description']) . "<br>";
                echo "<strong>Krijuar më:</strong> " . htmlspecialchars($token['created_at']) . "<br>";
                echo "<strong>Skadon më:</strong> " . ($token['expired_at'] ? htmlspecialchars($token['expired_at']) : 'Pa skadim');
                
                if ($isExpired) {
                    echo " <span style='color: red;'>(Skaduar)</span>";
                }
                
                echo "</li><br>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color: red;'>❌ Nuk ka token-a në tabelën api_tokens. Përdorni create_api_tables.php për të krijuar një token fillestar.</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Tabela api_tokens nuk ekziston. Përdorni create_api_tables.php për të krijuar tabelën dhe token fillestar.</p>";
    }
    
    // Kontrollo tabelën payment_logs
    $stmt = $pdo->query("SHOW TABLES LIKE 'payment_logs'");
    $paymentLogsExists = $stmt->rowCount() > 0;
    
    if ($paymentLogsExists) {
        echo "<p style='color: green;'>✅ Tabela payment_logs ekziston.</p>";
        
        // Kontrollo nëse ka pagesa
        $stmt = $pdo->query("SELECT COUNT(*) as payment_count FROM payment_logs");
        $paymentCount = $stmt->fetchColumn();
        
        if ($paymentCount > 0) {
            echo "<p style='color: green;'>✅ Ka $paymentCount rekorde pagesash në tabelën payment_logs.</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Nuk ka rekorde pagesash në tabelën payment_logs. Përdorni sample_payment_data.php për të gjeneruar të dhëna shembull për testim.</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Tabela payment_logs nuk ekziston. Përdorni create_api_tables.php për të krijuar tabelën.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Gabim gjatë kontrollit të tabelave: " . $e->getMessage() . "</p>";
}

echo "<p><a href='api_debug.php'>Shkoni tek API Debug për të testuar API-n</a></p>";
echo "<p><a href='api_client_test.php'>Shkoni tek API Client Test për të testuar thirrjet API</a></p>";
echo "<p><a href='create_api_tables.php'>Krijoni tabelat dhe token-at</a></p>";

?>