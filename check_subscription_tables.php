<?php
// check_subscription_tables.php - Një skedar për të kontrolluar strukturën e tabelave për sistemin e abonimeve

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';

// Funksioni për të kontrolluar nëse tabela ekziston
function tableExists($pdo, $tableName) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE '{$tableName}'");
        return $result->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Funksioni për të kontrolluar nëse një kolonë ekziston në tabelë
function columnExists($pdo, $tableName, $columnName) {
    try {
        $result = $pdo->query("SHOW COLUMNS FROM {$tableName} LIKE '{$columnName}'");
        return $result->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Tabelat që duhet të ekzistojnë për sistemin e abonimeve
$requiredTables = [
    'system_settings',
    'subscription_payments',
    'activity_logs',
    'noteri'
];

// Kolonat që duhet të ekzistojnë në tabelën noteri
$requiredNoteriColumns = [
    'custom_price',
    'subscription_status',
    'account_number',
    'bank_name'
];

// Kontrolli për çdo tabelë
echo "<h2>Kontrolli i tabelave të sistemit të abonimeve</h2>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Tabela</th><th>Ekziston</th><th>Statusi</th></tr>";

foreach ($requiredTables as $table) {
    $exists = tableExists($pdo, $table);
    echo "<tr>";
    echo "<td>{$table}</td>";
    echo "<td>" . ($exists ? "Po" : "Jo") . "</td>";
    echo "<td>" . ($exists ? "<span style='color:green'>OK</span>" : "<span style='color:red'>Mungon</span>") . "</td>";
    echo "</tr>";
}

echo "</table>";

// Kontrolli për kolonat e tabelës noteri
if (tableExists($pdo, 'noteri')) {
    echo "<h2>Kontrolli i kolonave të tabelës 'noteri'</h2>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Kolona</th><th>Ekziston</th><th>Statusi</th></tr>";
    
    foreach ($requiredNoteriColumns as $column) {
        $exists = columnExists($pdo, 'noteri', $column);
        echo "<tr>";
        echo "<td>{$column}</td>";
        echo "<td>" . ($exists ? "Po" : "Jo") . "</td>";
        echo "<td>" . ($exists ? "<span style='color:green'>OK</span>" : "<span style='color:red'>Mungon</span>") . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

// Kontrolli i konfigurimeve në system_settings
if (tableExists($pdo, 'system_settings')) {
    echo "<h2>Kontrolli i konfigurimeve në 'system_settings'</h2>";
    
    try {
        $result = $pdo->query("SELECT * FROM system_settings LIMIT 1");
        $settings = $result->fetch(PDO::FETCH_ASSOC);
        
        if ($settings) {
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
            echo "<tr><th>Parametri</th><th>Vlera</th></tr>";
            
            foreach ($settings as $key => $value) {
                echo "<tr>";
                echo "<td>{$key}</td>";
                echo "<td>{$value}</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        } else {
            echo "<p style='color:orange'>Nuk ka konfigurime të ruajtura në tabelën system_settings.</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>Gabim në leximin e konfigurimeve: " . $e->getMessage() . "</p>";
    }
}

// Shiko noterët me informacion pagese
if (tableExists($pdo, 'noteri')) {
    echo "<h2>Noterët me informacion pagese</h2>";
    
    try {
        $result = $pdo->query("
            SELECT 
                n.id, n.emri, n.mbiemri, n.email, n.statusi,
                n.account_number, n.bank_name, n.subscription_status,
                COALESCE(n.custom_price, 25.00) as price
            FROM 
                noteri n
            WHERE 
                n.statusi = 'active'
            LIMIT 10
        ");
        
        $noters = $result->fetchAll(PDO::FETCH_ASSOC);
        
        if ($noters) {
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
            echo "<tr><th>ID</th><th>Emri</th><th>Mbiemri</th><th>Email</th><th>Statusi</th><th>Llogaria</th><th>Banka</th><th>Status Abonimi</th><th>Çmimi</th></tr>";
            
            foreach ($noters as $noter) {
                echo "<tr>";
                echo "<td>{$noter['id']}</td>";
                echo "<td>{$noter['emri']}</td>";
                echo "<td>{$noter['mbiemri']}</td>";
                echo "<td>{$noter['email']}</td>";
                echo "<td>{$noter['statusi']}</td>";
                echo "<td>" . ($noter['account_number'] ? $noter['account_number'] : '<span style="color:red">Mungon</span>') . "</td>";
                echo "<td>" . ($noter['bank_name'] ? $noter['bank_name'] : '<span style="color:red">Mungon</span>') . "</td>";
                echo "<td>{$noter['subscription_status']}</td>";
                echo "<td>{$noter['price']} EUR</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        } else {
            echo "<p style='color:orange'>Nuk ka noterë aktivë në sistem.</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>Gabim në leximin e noterëve: " . $e->getMessage() . "</p>";
    }
}