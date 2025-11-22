<?php
// add_column_emri_noterit.php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';

try {
    // Shto kolonën emri_noterit në tabelën zyrat
    $sql = "ALTER TABLE zyrat ADD COLUMN emri_noterit VARCHAR(255) DEFAULT NULL";
    $pdo->exec($sql);
    
    // Shto kolonat e tjera që mund të mungojnë
    $sql_check_columns = [
        "vitet_pervoje" => "ALTER TABLE zyrat ADD COLUMN vitet_pervoje INT DEFAULT 0",
        "numri_punetoreve" => "ALTER TABLE zyrat ADD COLUMN numri_punetoreve INT DEFAULT 1",
        "gjuhet" => "ALTER TABLE zyrat ADD COLUMN gjuhet VARCHAR(255) DEFAULT NULL",
        "staff_data" => "ALTER TABLE zyrat ADD COLUMN staff_data JSON DEFAULT NULL",
        "data_licences" => "ALTER TABLE zyrat ADD COLUMN data_licences DATE DEFAULT NULL"
    ];
    
    // Merr kolonat ekzistuese
    $columns = [];
    $stmt = $pdo->query("SHOW COLUMNS FROM zyrat");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }
    
    // Shto vetëm kolonat që mungojnë
    foreach ($sql_check_columns as $column => $sql) {
        if (!in_array($column, $columns)) {
            $pdo->exec($sql);
            echo "U shtua kolona: $column<br>";
        }
    }
    
    echo "<h3 style='color:green'>Kolona emri_noterit dhe kolonat e tjera të nevojshme u shtuan me sukses!</h3>";
    echo "<p>Tani mund të ktheheni në faqen <a href='zyrat_register.php'>Regjistro Zyrën</a> për të regjistruar një zyrë të re.</p>";
} catch (PDOException $e) {
    echo "<h3 style='color:red'>Gabim gjatë shtimit të kolonës:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>