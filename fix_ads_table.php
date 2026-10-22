<?php
require_once 'confidb.php';

try {
    // Shto kolonën is_active në ads
    $sql1 = "ALTER TABLE `ads` ADD COLUMN `is_active` TINYINT(1) DEFAULT 1";
    try {
        $pdo->exec($sql1);
        echo "Kolona 'is_active' u shtua me sukses.<br>";
    } catch (PDOException $e) {
        echo "Kolona 'is_active' mund të ekzistojë tashmë ose pati një gabim: " . $e->getMessage() . "<br>";
    }

    // Ndrysho views në impressions në ads
    $sql2 = "ALTER TABLE `ads` CHANGE COLUMN `views` `impressions` INT(11) DEFAULT 0";
    try {
        $pdo->exec($sql2);
        echo "Kolona 'views' u ndryshua në 'impressions' me sukses.<br>";
    } catch (PDOException $e) {
        echo "Ndryshimi i kolonës 'views' dështoi (ndoshta nuk ekziston ose është ndryshuar tashmë): " . $e->getMessage() . "<br>";
    }

    // Shto kolonën 'description' në advertisements nëse mungon
    $table = 'advertisements';
    $column = 'description';
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($stmt->rowCount() === 0) {
        $sql3 = "ALTER TABLE `$table` ADD `$column` TEXT NULL AFTER `title`";
        try {
            $pdo->exec($sql3);
            echo "Kolona 'description' u shtua me sukses në advertisements.<br>";
        } catch (PDOException $e) {
            echo "Gabim gjatë shtimit të kolonës 'description': " . $e->getMessage() . "<br>";
        }
    } else {
        echo "Kolona 'description' ekziston tashmë në advertisements.<br>";
    }

    echo "Struktura e tabelave u përditësua.";

} catch (PDOException $e) {
    echo "Gabim i përgjithshëm: " . $e->getMessage();
}
?>