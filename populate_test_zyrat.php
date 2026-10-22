<?php
// Inserto të dhëna test në tabelën zyrat nëse është e zbrazët
require 'config.php';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Kontrollim nëse ka të dhëna në tabelën zyrat
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM zyrat");
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        // Inserto të dhëna test
        $test_zyrat = [
            ['emri' => 'Zyra Noteriale Prishtina', 'qyteti' => 'Prishtina', 'shteti' => 'Kosovë', 'telefoni' => '+383-38-123-456', 'email' => 'prishtina@noteria.com', 'adresa' => 'Rr. Peja nr. 10'],
            ['emri' => 'Zyra Noteriale Prizren', 'qyteti' => 'Prizren', 'shteti' => 'Kosovë', 'telefoni' => '+383-29-234-567', 'email' => 'prizren@noteria.com', 'adresa' => 'Rr. Sheshi i Flamurit nr. 5'],
            ['emri' => 'Zyra Noteriale Peja', 'qyteti' => 'Peja', 'shteti' => 'Kosovë', 'telefoni' => '+383-39-345-678', 'email' => 'peja@noteria.com', 'adresa' => 'Rr. e Dardanisë nr. 8'],
            ['emri' => 'Zyra Noteriale Gjakovë', 'qyteti' => 'Gjakovë', 'shteti' => 'Kosovë', 'telefoni' => '+383-290-456-789', 'email' => 'gjakove@noteria.com', 'adresa' => 'Rr. Ramiz Sadiku nr. 3'],
            ['emri' => 'Zyra Noteriale Mitrovicë', 'qyteti' => 'Mitrovicë', 'shteti' => 'Kosovë', 'telefoni' => '+383-28-567-890', 'email' => 'mitrovice@noteria.com', 'adresa' => 'Rr. Avni Rustemi nr. 12'],
        ];
        
        $stmt = $pdo->prepare("INSERT INTO zyrat (emri, qyteti, shteti, telefoni, email, adresa) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($test_zyrat as $zyra) {
            $stmt->execute([
                $zyra['emri'],
                $zyra['qyteti'],
                $zyra['shteti'],
                $zyra['telefoni'],
                $zyra['email'],
                $zyra['adresa']
            ]);
        }
        
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 16px; color: #155724; margin: 20px;'>";
        echo "<strong>✅ Të dhënat e testit u shtuan me sukses!</strong><br>";
        echo "U shtuan " . count($test_zyrat) . " zyra noteriale.";
        echo "</div>";
    } else {
        echo "<div style='background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 8px; padding: 16px; color: #0c5460; margin: 20px;'>";
        echo "<strong>ℹ️ Informacion:</strong> Tabela zyrat ka tashmë " . $result['count'] . " zyrë(ra).";
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; padding: 16px; color: #721c24; margin: 20px;'>";
    echo "<strong>❌ Gabim:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>
