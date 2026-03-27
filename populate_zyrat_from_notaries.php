<?php
require_once 'confidb.php';

try {
    // 1. Get all notaries
    $stmt = $pdo->query("SELECT * FROM notaret");
    $notaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Gjetur " . count($notaries) . " noterë për t'u migruar.<br>";
    
    $count = 0;
    foreach ($notaries as $notary) {
        // Prepare data
        $emri = "Zyra Noteriale " . $notary['emri_mbiemri'];
        $qyteti = $notary['qyteti'] ?? 'Prishtinë';
        $adresa = $notary['adresa'] ?? $qyteti;
        $email = $notary['email'];
        
        // Generate dummy data for required unique fields if missing
        if (empty($email)) {
            $email = 'noter_' . $notary['id'] . '@noteria.local';
        }
        
        $telefoni = $notary['kontakti'];
        if (empty($telefoni)) {
            $telefoni = '044-' . str_pad($notary['id'], 6, '0', STR_PAD_LEFT);
        }
        
        // Clean phone for unique constraint (remove spaces, dashes)
        // $telefoni_clean = preg_replace('/[^0-9]/', '', $telefoni);
        
        $numri_fiskal = '600' . str_pad($notary['id'], 6, '0', STR_PAD_LEFT);
        $numri_biznesit = '810' . str_pad($notary['id'], 6, '0', STR_PAD_LEFT);
        $numri_licences = 'LIC-' . str_pad($notary['id'], 4, '0', STR_PAD_LEFT);
        $iban = 'XK05' . '20' . str_pad($notary['id'], 16, '0', STR_PAD_LEFT);
        $fjalekalimi = password_hash('password123', PASSWORD_DEFAULT);
        $username = 'noter_' . $notary['id'];
        
        // Insert into zyrat
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
            // Ignore duplicate errors, maybe print them
            // echo "Skipped " . $notary['emri_mbiemri'] . ": " . $e->getMessage() . "<br>";
        }
    }
    
    echo "U migruan me sukses $count zyra noteriale në tabelën 'zyrat'.";
    
} catch (PDOException $e) {
    echo "Gabim: " . $e->getMessage();
}
?>