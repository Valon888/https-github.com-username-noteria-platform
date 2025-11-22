<?php
// check_db.php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';

try {
    // Kontrollo lidhjen me databazën
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p>Lidhja me databazën u realizua me sukses!</p>";
    
    // Merr kolonat e tabelës zyrat
    $stmt = $pdo->query("DESCRIBE zyrat");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Struktura e tabelës 'zyrat':</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    // Kontrollo nëse ekziston kolona emri_noterit
    $hasEmriNoterit = false;
    
    foreach ($columns as $column) {
        echo "<tr>";
        foreach ($column as $key => $value) {
            echo "<td>" . htmlspecialchars($value) . "</td>";
        }
        echo "</tr>";
        
        if ($column['Field'] === 'emri_noterit') {
            $hasEmriNoterit = true;
        }
    }
    echo "</table>";
    
    if (!$hasEmriNoterit) {
        echo "<p style='color:red'>Kolona 'emri_noterit' mungon në tabelën 'zyrat'.</p>";
        echo "<p>Po tentoj ta shtoj kolonën...</p>";
        
        $pdo->exec("ALTER TABLE zyrat ADD COLUMN emri_noterit VARCHAR(255) DEFAULT NULL");
        echo "<p style='color:green'>Kolona 'emri_noterit' u shtua me sukses!</p>";
    } else {
        echo "<p style='color:green'>Kolona 'emri_noterit' ekziston në tabelën 'zyrat'.</p>";
    }
    
    // Kontrollo nëse mungojnë kolonat e tjera të nevojshme
    $neededColumns = [
        'vitet_pervoje' => 'INT DEFAULT 0',
        'numri_punetoreve' => 'INT DEFAULT 1',
        'gjuhet' => 'VARCHAR(255) DEFAULT NULL',
        'staff_data' => 'JSON NULL'
    ];
    
    $columnsToAdd = [];
    foreach ($neededColumns as $columnName => $columnType) {
        $exists = false;
        foreach ($columns as $column) {
            if ($column['Field'] === $columnName) {
                $exists = true;
                break;
            }
        }
        
        if (!$exists) {
            $columnsToAdd[$columnName] = $columnType;
        }
    }
    
    if (!empty($columnsToAdd)) {
        echo "<p>Po shtoj kolonat e mëposhtme që mungojnë:</p>";
        echo "<ul>";
        foreach ($columnsToAdd as $columnName => $columnType) {
            try {
                $pdo->exec("ALTER TABLE zyrat ADD COLUMN $columnName $columnType");
                echo "<li style='color:green'>U shtua kolona '$columnName' me sukses.</li>";
            } catch (PDOException $e) {
                echo "<li style='color:red'>Gabim gjatë shtimit të kolonës '$columnName': " . $e->getMessage() . "</li>";
            }
        }
        echo "</ul>";
    } else {
        echo "<p style='color:green'>Të gjitha kolonat e nevojshme ekzistojnë në tabelën 'zyrat'.</p>";
    }
    
    echo "<p>Kontrollimi përfundoi me sukses!</p>";
    echo "<p><a href='zyrat_register.php'>Kthehu te faqja e regjistrimit</a></p>";
    
} catch (PDOException $e) {
    echo "<h2 style='color:red'>Gabim në lidhjen me databazën:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>