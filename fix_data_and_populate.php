<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'confidb.php';

echo "<h2>Fixing Data...</h2>";

// 1. Ensure Notaret Data Exists
$stmt = $pdo->query("SELECT COUNT(*) FROM notaret");
if ($stmt->fetchColumn() == 0) {
    echo "Tabela 'notaret' është bosh. Duke importuar të dhënat...<br>";
    // Include the logic from import_notaries.php directly here or curl it
    // For speed, I'll just insert a few sample records if the file isn't run
    $notaries_data = [
        ['Adelina Ajeti-Qerimi', '045-489-444', 'notereadelinaajeti@hotmail.com', 'Rr. "Dëshmorët e Kombit", nr.65, Ferizaj', 'Ferizaj'],
        ['Adnan Imeri', '044 322-933', 'adnanimeri@live.com', 'Rr. "Kongresi i Manastirit" p.n. (Objekti i Postës), Viti', 'Viti'],
        ['Afrore Zatriqi', '049-324-329', 'afrore.ukaj.zatriqi@gmail.com', 'Rr. "Mbretëresha Teutë", p.n, Pejë.', 'Pejë'],
        ['Alban Janova', '044-444-221', 'albanjanova@gmail.com', 'Rr. "Fehmi dhe Xhevë Lladrovci" Nr. 15', 'Gllogoc'],
        ['Alban Musliu', '044-285-545', 'alban.musliu@gmail.com', 'Rr. "28 Nëntori", obj. Veranda F2, nr. 6, Skenderaj', 'Skënderaj']
    ];
    
    $stmtInsert = $pdo->prepare("INSERT INTO notaret (emri_mbiemri, kontakti, email, adresa, qyteti) VALUES (?, ?, ?, ?, ?)");
    foreach ($notaries_data as $row) {
        $stmtInsert->execute($row);
    }
    echo "U shtuan " . count($notaries_data) . " noterë shembull.<br>";
} else {
    echo "Tabela 'notaret' ka të dhëna.<br>";
}

// 2. Populate Zyrat from Notaret
$stmt = $pdo->query("SELECT COUNT(*) FROM zyrat");
if ($stmt->fetchColumn() == 0) {
    echo "Tabela 'zyrat' është bosh. Duke migruar nga 'notaret'...<br>";
    
    $notaries = $pdo->query("SELECT * FROM notaret")->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;
    
    foreach ($notaries as $notary) {
        $emri = "Zyra Noteriale " . $notary['emri_mbiemri'];
        $qyteti = $notary['qyteti'] ?? 'Prishtinë';
        $adresa = $notary['adresa'] ?? $qyteti;
        $email = $notary['email'] ?: 'noter_' . $notary['id'] . '@noteria.local';
        $telefoni = $notary['kontakti'] ?: '044-' . str_pad($notary['id'], 6, '0', STR_PAD_LEFT);
        
        // Ensure unique fields
        $numri_fiskal = '600' . str_pad($notary['id'], 6, '0', STR_PAD_LEFT);
        $numri_biznesit = '810' . str_pad($notary['id'], 6, '0', STR_PAD_LEFT);
        $numri_licences = 'LIC-' . str_pad($notary['id'], 4, '0', STR_PAD_LEFT);
        $iban = 'XK05' . '20' . str_pad($notary['id'], 16, '0', STR_PAD_LEFT);
        $fjalekalimi = password_hash('password123', PASSWORD_DEFAULT);
        $username = 'noter_' . $notary['id'];
        
        $sql = "INSERT INTO zyrat (
            emri, qyteti, adresa, email, telefoni, 
            numri_fiskal, numri_biznesit, numri_licences, 
            iban, emri_noterit, fjalekalimi, username, statusi
        ) VALUES (
            ?, ?, ?, ?, ?, 
            ?, ?, ?, 
            ?, ?, ?, ?, 'aprovuar'
        )";
        
        try {
            $stmtInsert = $pdo->prepare($sql);
            $stmtInsert->execute([
                $emri, $qyteti, $adresa, $email, $telefoni,
                $numri_fiskal, $numri_biznesit, $numri_licences,
                $iban, $notary['emri_mbiemri'], $fjalekalimi, $username
            ]);
            $count++;
        } catch (PDOException $e) {
            // Ignore duplicates
        }
    }
    echo "U shtuan $count zyra noteriale.<br>";
} else {
    echo "Tabela 'zyrat' ka të dhëna.<br>";
}

echo "<h3>Statusi Final:</h3>";
echo "Notaret: " . $pdo->query("SELECT COUNT(*) FROM notaret")->fetchColumn() . "<br>";
echo "Zyrat: " . $pdo->query("SELECT COUNT(*) FROM zyrat")->fetchColumn() . "<br>";
?>