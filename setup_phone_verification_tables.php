<?php
// Setup për tabelat e verifikimit të telefonave
// filepath: d:\xampp\htdocs\noteria\setup_phone_verification_tables.php

require_once 'config.php';

try {
    echo "<h2>🚀 Konfigurimi i Sistemit të Verifikimit të Telefonave</h2>\n";
    
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p>✅ Lidhja me databazën u vendos</p>\n";
    
    // Lexo dhe ekzekuto SQL script
    $sql_content = file_get_contents('create_phone_verification_tables.sql');
    
    // Ndaj SQL statements
    $statements = array_filter(array_map('trim', explode(';', $sql_content)));
    
    $success_count = 0;
    $error_count = 0;
    
    echo "<h3>📋 Ekzekutimi i SQL statements:</h3>\n";
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        try {
            // Handle DELIMITER statements for events/triggers
            if (strpos($statement, 'DELIMITER') !== false) {
                continue;
            }
            
            $pdo->exec($statement);
            
            // Identifiko llojin e statement
            $statement_type = '';
            if (strpos(strtoupper($statement), 'CREATE TABLE') !== false) {
                preg_match('/CREATE TABLE[^`]*`?(\w+)`?/i', $statement, $matches);
                $statement_type = "Tabela: " . ($matches[1] ?? 'Unknown');
            } elseif (strpos(strtoupper($statement), 'CREATE INDEX') !== false) {
                $statement_type = "Index";
            } elseif (strpos(strtoupper($statement), 'INSERT INTO') !== false) {
                $statement_type = "Data insertion";
            } elseif (strpos(strtoupper($statement), 'CREATE EVENT') !== false) {
                $statement_type = "Event scheduler";
            } else {
                $statement_type = "Other SQL";
            }
            
            echo "<p style='color: green;'>✅ $statement_type</p>\n";
            $success_count++;
            
        } catch (Exception $e) {
            echo "<p style='color: orange;'>⚠️ Warning: " . $e->getMessage() . "</p>\n";
            $error_count++;
        }
    }
    
    echo "<hr>\n";
    echo "<h3>📊 Përmbledhje:</h3>\n";
    echo "<p><strong>✅ Të suksesshme:</strong> $success_count</p>\n";
    echo "<p><strong>⚠️ Warnings:</strong> $error_count</p>\n";
    
    // Kontrollo nëse tabelat u krijuan
    echo "<h3>🔍 Verifikimi i tabelave të krijuara:</h3>\n";
    
    $tables_to_check = [
        'phone_verification_codes',
        'phone_verification_logs',
        'sms_provider_config',
        'sms_statistics',
        'phone_blacklist'
    ];
    
    foreach ($tables_to_check as $table) {
        try {
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "<p style='color: green;'>✅ Tabela <strong>$table</strong> - " . count($columns) . " kolona</p>\n";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Tabela <strong>$table</strong> - Nuk u krijua</p>\n";
        }
    }
    
    // Test i konfigurimit të provider-ëve
    echo "<h3>📱 Kontrolli i provider-ëve SMS:</h3>\n";
    
    try {
        $stmt = $pdo->query("SELECT provider_name, is_active, priority_order FROM sms_provider_config ORDER BY priority_order");
        $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($providers) {
            echo "<ul>\n";
            foreach ($providers as $provider) {
                $status = $provider['is_active'] ? '🟢 Aktiv' : '🔴 Joaktiv';
                echo "<li><strong>{$provider['provider_name']}</strong> - $status (Prioriteti: {$provider['priority_order']})</li>\n";
            }
            echo "</ul>\n";
        } else {
            echo "<p style='color: orange;'>⚠️ Nuk u gjetën provider-ë SMS</p>\n";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Gabim në leximin e provider-ëve: " . $e->getMessage() . "</p>\n";
    }
    
    echo "<hr>\n";
    echo "<h3>🎉 Sistemi i verifikimit të telefonave është gati!</h3>\n";
    echo "<p><strong>Veçoritë e reja:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>📱 Verifikim përmes SMS brenda 3 minutave</li>\n";
    echo "<li>🔄 Support për provider-ë të shumtë (IPKO, Infobip, Twilio)</li>\n";
    echo "<li>🛡️ Sistemi i avancuar i sigurisë dhe limiteve</li>\n";
    echo "<li>📊 Statistika dhe monitoring në kohë reale</li>\n";
    echo "<li>🚫 Blacklist për numra problematik</li>\n";
    echo "<li>⚡ Pastrimi automatik i të dhënave të vjetra</li>\n";
    echo "</ul>\n";
    
    echo "<p><strong>📋 Hapat e ardhshëm:</strong></p>\n";
    echo "<ol>\n";
    echo "<li>Konfiguro API keys për provider-ët SMS</li>\n";
    echo "<li>Integroje me formën e regjistrimit</li>\n";
    echo "<li>Testo verifikimin 3-minutësh</li>\n";
    echo "</ol>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Gabim: " . $e->getMessage() . "</p>\n";
}
?>