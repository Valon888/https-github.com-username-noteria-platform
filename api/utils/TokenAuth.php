<?php
/**
 * Klasa për autentikim bazuar në token
 */
class TokenAuth {
    private $db;
    private $tokenExpirySeconds = 86400; // 24 orë në sekonda
    
    /**
     * Konstruktori
     * @param PDO $db Lidhja me databazën
     */
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Gjeneron një token për përdoruesin
     * @param int $userId ID e përdoruesit
     * @return string Token-i i krijuar
     */
    public function generateToken($userId) {
        // Gjeneroni një token të rastësishëm
        $token = bin2hex(random_bytes(32));
        
        // Llogaritni kohën e skadimit
        $expires = date('Y-m-d H:i:s', time() + $this->tokenExpirySeconds);
        
        // Fshini token-at e vjetër për këtë përdorues
        $query = "DELETE FROM seancat_token WHERE id_perdorues = :userId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId);
        $stmt->execute();
        
        // Ruani token-in e ri në databazë
        $query = "INSERT INTO seancat_token (id_perdorues, token, data_skadimit) 
                  VALUES (:userId, :token, :expires)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':expires', $expires);
        
        if (!$stmt->execute()) {
            throw new Exception("Dështoi gjenerimi i token-it të autentikimit");
        }
        
        return $token;
    }
    
    /**
     * Vërteton një token
     * @param string $token Token-i për t'u verifikuar
     * @return int ID e përdoruesit nëse token-i është i vlefshëm
     * @throws Exception Nëse token-i është i pavlefshëm ose ka skaduar
     */
    public function validateToken($token) {
        $query = "SELECT id_perdorues, data_skadimit FROM seancat_token 
                  WHERE token = :token";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Token-i i autentikimit është i pavlefshëm");
        }
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $expiryDate = $row['data_skadimit'];
        
        // Kontrollo nëse token-i ka skaduar
        if (strtotime($expiryDate) < time()) {
            throw new Exception("Sesioni juaj ka skaduar, ju lutemi identifikohuni përsëri");
        }
        
        return $row['id_perdorues'];
    }
    
    /**
     * Invalidon një token (logout)
     * @param string $token Token-i për t'u invaliduar
     * @return bool True nëse operacioni ishte i suksesshëm
     */
    public function invalidateToken($token) {
        $query = "DELETE FROM seancat_token WHERE token = :token";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':token', $token);
        return $stmt->execute();
    }
}