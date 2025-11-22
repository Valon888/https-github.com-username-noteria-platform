<?php
/**
 * Funksioni për të regjistruar një pagesë në log
 * 
 * @param PDO $pdo Lidhja me bazën e të dhënave
 * @param int $zyra_id ID e zyrës që kryen pagesën
 * @param float $amount Shuma e pagesës
 * @param string $description Përshkrimi i pagesës
 * @param string $payment_method Metoda e pagesës (p.sh. 'credit_card', 'bank_transfer', 'paypal')
 * @return bool Kthen true nëse pagesa u regjistrua me sukses, false nëse jo
 */
function logPayment($pdo, $zyra_id, $amount, $description, $payment_method = 'unknown') {
    try {
        // Kontrollo nëse tabela payment_logs ekziston
        $stmt = $pdo->query("SHOW TABLES LIKE 'payment_logs'");
        if ($stmt->rowCount() == 0) {
            // Krijo tabelën nëse nuk ekziston
            $pdo->exec("CREATE TABLE payment_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                zyra_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                description VARCHAR(255) NOT NULL,
                payment_method VARCHAR(50) NOT NULL,
                log_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ip_address VARCHAR(50),
                user_agent TEXT,
                FOREIGN KEY (zyra_id) REFERENCES zyrat(id) ON DELETE CASCADE
            )");
        }
        
        // Regjistro pagesën në log
        $stmt = $pdo->prepare("INSERT INTO payment_logs 
            (zyra_id, amount, description, payment_method, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)");
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        return $stmt->execute([
            $zyra_id, 
            $amount, 
            $description, 
            $payment_method, 
            $ip, 
            $userAgent
        ]);
    } catch (PDOException $e) {
        error_log('Error logging payment: ' . $e->getMessage());
        return false;
    }
}