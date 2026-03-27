<?php
require_once 'config.php';

try {
    echo "<h1>Duke përditësuar planet e abonimit...</h1>";

    // 1. Sigurohu që tabela ekziston
    $pdo->exec("CREATE TABLE IF NOT EXISTS `abonimet` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `emri` VARCHAR(255) NOT NULL,
        `cmimi` DECIMAL(10,2) NOT NULL,
        `kohezgjatja` INT(11) NOT NULL COMMENT 'Në muaj',
        `pershkrimi` TEXT,
        `karakteristikat` TEXT,
        `status` ENUM('aktiv', 'joaktiv') NOT NULL DEFAULT 'aktiv',
        `krijuar_me` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `perditesuar_me` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 2. Përgatit të dhënat e reja
    $monthlyFeatures = json_encode([
        "Qasje e plotë në platformë", 
        "Dokumente të pakufizuara", 
        "Mbështetje teknike 24/7", 
        "Arkivë digjitale e sigurt",
        "Gjenerim automatik i raporteve"
    ], JSON_UNESCAPED_UNICODE);
    
    $yearlyFeatures = json_encode([
        "Të gjitha benefitet e abonimit mujor", 
        "Zbritje vjetore (Kurseni 200€)", 
        "Trajnime falas për stafin", 
        "Prioritet në mbështetje",
        "Backup ditor i të dhënave"
    ], JSON_UNESCAPED_UNICODE);

    $pdo->beginTransaction();

    // 3. Menaxho Planin Mujor (150 EUR)
    // Provo të gjesh një plan ekzistues mujor për ta përditësuar (që të mos prishen lidhjet me abonimet e vjetra)
    $stmt = $pdo->prepare("SELECT id FROM abonimet WHERE kohezgjatja = 1 LIMIT 1");
    $stmt->execute();
    $monthlyId = $stmt->fetchColumn();

    if ($monthlyId) {
        $stmt = $pdo->prepare("UPDATE abonimet SET emri = ?, cmimi = ?, pershkrimi = ?, karakteristikat = ?, status = 'aktiv' WHERE id = ?");
        $stmt->execute(['Abonim Mujor', 150.00, 'Abonim mujor standard për noterë', $monthlyFeatures, $monthlyId]);
        echo "<p style='color:green'>✓ Plani mujor ekzistues u përditësua në 150.00 EUR.</p>";
    } else {
        $stmt = $pdo->prepare("INSERT INTO abonimet (emri, cmimi, kohezgjatja, pershkrimi, karakteristikat, status) VALUES (?, ?, ?, ?, ?, 'aktiv')");
        $stmt->execute(['Abonim Mujor', 150.00, 1, 'Abonim mujor standard për noterë', $monthlyFeatures]);
        echo "<p style='color:green'>✓ Plani mujor u krijua i ri (150.00 EUR).</p>";
    }

    // 4. Menaxho Planin Vjetor (1600 EUR)
    $stmt = $pdo->prepare("SELECT id FROM abonimet WHERE kohezgjatja = 12 LIMIT 1");
    $stmt->execute();
    $yearlyId = $stmt->fetchColumn();

    if ($yearlyId) {
        $stmt = $pdo->prepare("UPDATE abonimet SET emri = ?, cmimi = ?, pershkrimi = ?, karakteristikat = ?, status = 'aktiv' WHERE id = ?");
        $stmt->execute(['Abonim Vjetor', 1600.00, 'Abonim vjetor me zbritje', $yearlyFeatures, $yearlyId]);
        echo "<p style='color:green'>✓ Plani vjetor ekzistues u përditësua në 1600.00 EUR.</p>";
    } else {
        $stmt = $pdo->prepare("INSERT INTO abonimet (emri, cmimi, kohezgjatja, pershkrimi, karakteristikat, status) VALUES (?, ?, ?, ?, ?, 'aktiv')");
        $stmt->execute(['Abonim Vjetor', 1600.00, 12, 'Abonim vjetor me zbritje', $yearlyFeatures]);
        echo "<p style='color:green'>✓ Plani vjetor u krijua i ri (1600.00 EUR).</p>";
    }

    // 5. Çaktivizo planet e tjera që nuk janë këto dyja
    // Identifiko ID-të e planeve që sapo përditësuam/krijuam
    $stmt = $pdo->prepare("SELECT id FROM abonimet WHERE kohezgjatja IN (1, 12) AND status = 'aktiv'");
    $stmt->execute();
    $activeIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($activeIds)) {
        $placeholders = implode(',', array_fill(0, count($activeIds), '?'));
        $stmt = $pdo->prepare("UPDATE abonimet SET status = 'joaktiv' WHERE id NOT IN ($placeholders)");
        $stmt->execute($activeIds);
        echo "<p style='color:orange'>✓ Planet e tjera të vjetra u çaktivizuan.</p>";
    }

    $pdo->commit();
    echo "<h2>Përditësimi përfundoi me sukses!</h2>";
    echo "<a href='abonimet.php'>Kthehu tek Abonimet</a>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h2 style='color:red'>Gabim: " . $e->getMessage() . "</h2>";
}
?>