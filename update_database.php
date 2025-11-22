<?php
// filepath: d:\xampp\htdocs\noteria\update_database.php
// Ky script shton kolonat e reja në tabelat ekzistuese të databazës
require_once 'config.php';

try {
    // Funksion ndihmës për të kontrolluar nëse kolona ekziston
    function columnExists($pdo, $table, $column) {
        $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
        return $stmt->rowCount() > 0;
    }
    
    // Funksion ndihmës për të kontrolluar nëse tabela ekziston
    function tableExists($pdo, $table) {
        try {
            $result = $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    // Shtimi i kolonës 'operator' në tabelën 'zyrat' (nëse nuk ekziston)
    if (!columnExists($pdo, 'zyrat', 'operator')) {
        $pdo->exec("ALTER TABLE zyrat ADD COLUMN operator VARCHAR(50) AFTER telefoni");
        echo "✅ Kolona 'operator' u shtua me sukses në tabelën 'zyrat'<br>";
    } else {
        echo "ℹ️ Kolona 'operator' ekziston tashmë në tabelën 'zyrat'<br>";
    }
    
    // Shtimi i kolonës 'adresa' në tabelën 'zyrat' (nëse nuk ekziston)
    if (!columnExists($pdo, 'zyrat', 'adresa')) {
        $pdo->exec("ALTER TABLE zyrat ADD COLUMN adresa VARCHAR(255) AFTER qyteti");
        echo "✅ Kolona 'adresa' u shtua me sukses në tabelën 'zyrat'<br>";
    } else {
        echo "ℹ️ Kolona 'adresa' ekziston tashmë në tabelën 'zyrat'<br>";
    }
    
    // Kolonat fiskale për të shtuar në tabelën 'zyrat'
    $zyratColumns = [
        'numri_fiskal' => "VARCHAR(20) AFTER llogaria",
        'numri_biznesit' => "VARCHAR(20) AFTER numri_fiskal",
        'numri_licences' => "VARCHAR(20) AFTER numri_biznesit",
        'data_licences' => "DATE AFTER numri_licences",
        'data_regjistrimit' => "DATETIME DEFAULT CURRENT_TIMESTAMP"
    ];
    
    // Shto kolonat fiskale në tabelën 'zyrat'
    foreach ($zyratColumns as $column => $definition) {
        if (!columnExists($pdo, 'zyrat', $column)) {
            $pdo->exec("ALTER TABLE zyrat ADD COLUMN {$column} {$definition}");
            echo "✅ Kolona '{$column}' u shtua me sukses në tabelën 'zyrat'<br>";
        } else {
            echo "ℹ️ Kolona '{$column}' ekziston tashmë në tabelën 'zyrat'<br>";
        }
    }
    
    // Kolonat për të shtuar në tabelën 'payment_logs'
    $paymentLogsColumns = [
        'operator' => "VARCHAR(50) AFTER phone_number",
        'numri_fiskal' => "VARCHAR(20) AFTER file_path",
        'numri_biznesit' => "VARCHAR(20) AFTER numri_fiskal"
    ];
    
    // Shto kolonat në tabelën 'payment_logs'
    foreach ($paymentLogsColumns as $column => $definition) {
        if (!columnExists($pdo, 'payment_logs', $column)) {
            $pdo->exec("ALTER TABLE payment_logs ADD COLUMN {$column} {$definition}");
            echo "✅ Kolona '{$column}' u shtua me sukses në tabelën 'payment_logs'<br>";
        } else {
            echo "ℹ️ Kolona '{$column}' ekziston tashmë në tabelën 'payment_logs'<br>";
        }
    }
    
    // Kontrollo dhe shto kolonën 'operator' në tabelën 'noteri' (nëse nuk ekziston)
    if (tableExists($pdo, 'noteri')) {
        if (!columnExists($pdo, 'noteri', 'operator')) {
            $pdo->exec("ALTER TABLE noteri ADD COLUMN operator VARCHAR(100) DEFAULT NULL");
            echo "✅ Kolona 'operator' u shtua me sukses në tabelën 'noteri'<br>";
        } else {
            echo "ℹ️ Kolona 'operator' ekziston tashmë në tabelën 'noteri'<br>";
        }
    }
    
    echo "<br>👍 Databaza u përditësua me sukses!";
} catch (PDOException $e) {
    echo "❌ Gabim gjatë përditësimit të databazës: " . $e->getMessage();
}
?>

<style>
    body {
        font-family: 'Montserrat', sans-serif;
        max-width: 800px;
        margin: 40px auto;
        padding: 20px;
        background: #f5f8ff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        line-height: 1.6;
    }
    
    h2 {
        color: #2563eb;
        margin-bottom: 20px;
    }
    
    a {
        display: inline-block;
        margin: 10px 10px 0 0;
        padding: 10px 15px;
        background: #2563eb;
        color: white;
        text-decoration: none;
        border-radius: 4px;
        transition: all 0.3s ease;
    }
    
    a:hover {
        background: #1d4ed8;
    }
    
    .btn-secondary {
        background: #4b5563;
    }
    
    .btn-secondary:hover {
        background: #374151;
    }
    
    .info-box {
        margin-top: 20px;
        padding: 15px;
        background: #f0f9ff;
        border-left: 4px solid #2563eb;
        border-radius: 4px;
    }
</style>

<h2>Përditësimi i strukturës së databazës</h2>
<p>Databaza u përditësua për të mbështetur fushat e reja për zyrën noteriale.</p>

<div class="info-box">
    <p><strong>Keni probleme me databazën?</strong> Nëse keni probleme me kolonat që mungojnë ose kolonat dyfishe,
    përdorni <a href="fix_database_columns.php" style="display:inline; padding:0; background:transparent; color:#2563eb; text-decoration:underline;">mjëtin e korrigjimit të databazës</a> 
    për të rregulluar strukturën e plotë të databazës.</p>
</div>

<div>
    <a href="admin_noters.php">Menaxhimi i Noterëve</a>
    <a href="zyrat_register.php">Faqja e Regjistrimit</a>
    <a href="fix_database_columns.php" class="btn-secondary">Korrigjo Databazën</a>
</div>