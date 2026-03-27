<?php
require_once 'config.php';

try {
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'abonimet'");
    if ($stmt->rowCount() == 0) {
        die("Tabela 'abonimet' nuk ekziston. Ju lutem vizitoni abonimet.php fillimisht.");
    }

    echo "Duke përditësuar planet e abonimit...<br>";

    // Check for existing subscriptions to decide strategy
    $stmt = $pdo->query("SELECT COUNT(*) FROM noteri_abonimet");
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        echo "U gjetën abonime ekzistuese. Po përditësojmë planet ekzistuese në vend që t'i fshijmë.<br>";
        
        $pdo->beginTransaction();
        
        // 1. Update or Insert Monthly Plan (150 EUR)
        // We look for a monthly plan (kohezgjatja = 1)
        $stmt = $pdo->prepare("SELECT id FROM abonimet WHERE kohezgjatja = 1 LIMIT 1");
        $stmt->execute();
        $monthlyId = $stmt->fetchColumn();
        
        $monthlyFeatures = json_encode([
            "Qasje e plotë në platformë", 
            "Dokumente të pakufizuara", 
            "Mbështetje teknike 24/7", 
            "Arkivë digjitale e sigurt",
            "Gjenerim automatik i raporteve"
        ], JSON_UNESCAPED_UNICODE);

        if ($monthlyId) {
            $sql = "UPDATE abonimet SET emri = ?, cmimi = ?, pershkrimi = ?, karakteristikat = ?, status = 'aktiv' WHERE id = ?";
            $pdo->prepare($sql)->execute([
                'Abonim Mujor', 
                150.00, 
                'Abonim mujor standard për noterë', 
                $monthlyFeatures,
                $monthlyId
            ]);
            echo "Plani mujor u përditësua (ID: $monthlyId).<br>";
        } else {
            $sql = "INSERT INTO abonimet (emri, cmimi, kohezgjatja, pershkrimi, karakteristikat, status) VALUES (?, ?, ?, ?, ?, 'aktiv')";
            $pdo->prepare($sql)->execute([
                'Abonim Mujor', 
                150.00, 
                1, 
                'Abonim mujor standard për noterë', 
                $monthlyFeatures
            ]);
            echo "Plani mujor u krijua.<br>";
        }

        // 2. Update or Insert Yearly Plan (1600 EUR)
        // We look for a yearly plan (kohezgjatja = 12)
        $stmt = $pdo->prepare("SELECT id FROM abonimet WHERE kohezgjatja = 12 LIMIT 1");
        $stmt->execute();
        $yearlyId = $stmt->fetchColumn();
        
        $yearlyFeatures = json_encode([
            "Të gjitha benefitet e abonimit mujor", 
            "Zbritje vjetore (Kurseni 200€)", 
            "Trajnime falas për stafin", 
            "Prioritet në mbështetje",
            "Backup ditor i të dhënave"
        ], JSON_UNESCAPED_UNICODE);

        if ($yearlyId) {
            $sql = "UPDATE abonimet SET emri = ?, cmimi = ?, pershkrimi = ?, karakteristikat = ?, status = 'aktiv' WHERE id = ?";
            $pdo->prepare($sql)->execute([
                'Abonim Vjetor', 
                1600.00, 
                'Abonim vjetor me zbritje', 
                $yearlyFeatures,
                $yearlyId
            ]);
            echo "Plani vjetor u përditësua (ID: $yearlyId).<br>";
        } else {
            $sql = "INSERT INTO abonimet (emri, cmimi, kohezgjatja, pershkrimi, karakteristikat, status) VALUES (?, ?, ?, ?, ?, 'aktiv')";
            $pdo->prepare($sql)->execute([
                'Abonim Vjetor', 
                1600.00, 
                12, 
                'Abonim vjetor me zbritje', 
                $yearlyFeatures
            ]);
            echo "Plani vjetor u krijua.<br>";
        }
        
        // 3. Deactivate other plans
        // We deactivate any plan that is NOT 150 EUR or 1600 EUR
        $pdo->exec("UPDATE abonimet SET status = 'joaktiv' WHERE cmimi NOT IN (150.00, 1600.00)");
        echo "Planet e tjera u çaktivizuan.<br>";
        
        $pdo->commit();
        
    } else {
        // No dependencies, clean slate
        echo "Nuk u gjetën abonime ekzistuese. Po rikrijojmë tabelën e planeve.<br>";
        $pdo->exec("TRUNCATE TABLE abonimet");
        
        $sql = "INSERT INTO abonimet (emri, cmimi, kohezgjatja, pershkrimi, karakteristikat, status) VALUES 
        (?, ?, ?, ?, ?, 'aktiv'),
        (?, ?, ?, ?, ?, 'aktiv')";
        
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
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'Abonim Mujor', 
            150.00, 
            1, 
            'Abonim mujor standard për noterë', 
            $monthlyFeatures,
            
            'Abonim Vjetor', 
            1600.00, 
            12, 
            'Abonim vjetor me zbritje', 
            $yearlyFeatures
        ]);
        
        echo "Planet u krijuan me sukses.<br>";
    }
    
    echo "<br><strong>Përfundoi!</strong><br>";
    echo "Planet aktuale:<br>";
    $stmt = $pdo->query("SELECT * FROM abonimet WHERE status = 'aktiv'");
    while ($row = $stmt->fetch()) {
        echo "- " . htmlspecialchars($row['emri']) . ": " . $row['cmimi'] . "€ (" . $row['kohezgjatja'] . " muaj)<br>";
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Gabim: " . $e->getMessage();
}
?>