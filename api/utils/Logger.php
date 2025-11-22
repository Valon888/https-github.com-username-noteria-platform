<?php
/**
 * Klasa për regjistrim të ngjarjeve në sistem
 */
class Logger {
    private $db;
    
    /**
     * Konstruktori
     * @param PDO $db Lidhja me databazën
     */
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Regjistron një ngjarje në log
     * @param int $userId ID e përdoruesit (ose null nëse nuk ka përdorues)
     * @param string $action Veprimi i kryer
     * @param string $details Detaje shtesë për veprimin
     * @param string $ipAddress Adresa IP e përdoruesit
     * @param string $userAgent User agent i përdoruesit
     * @return bool Nëse regjistrimi ishte i suksesshëm
     */
    public function logActivity($userId, $action, $details = '', $ipAddress = '', $userAgent = '') {
        // Nëse IP dhe userAgent nuk janë dhënë, përdorni vlerat aktuale
        if (empty($ipAddress)) {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        
        if (empty($userAgent)) {
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        }
        
        try {
            $query = "INSERT INTO regjistri_veprimeve (id_perdorues, veprimi, detaje, adresa_ip, user_agent, data_krijimit) 
                      VALUES (:userId, :action, :details, :ipAddress, :userAgent, NOW())";
            $stmt = $this->db->prepare($query);
            
            // Bind parametrat
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':details', $details);
            $stmt->bindParam(':ipAddress', $ipAddress);
            $stmt->bindParam(':userAgent', $userAgent);
            
            return $stmt->execute();
        } catch (Exception $ex) {
            // Shkruaj gabimin në error_log të PHP
            error_log("Gabim gjatë regjistrimit të aktivitetit: " . $ex->getMessage());
            return false;
        }
    }
    
    /**
     * Regjistron një gabim në sistem
     * @param string $errorMessage Mesazhi i gabimit
     * @param string $errorFile Skedari ku ndodhi gabimi
     * @param int $errorLine Rreshti ku ndodhi gabimi
     * @param array $stackTrace Stack trace i gabimit
     * @return bool Nëse regjistrimi ishte i suksesshëm
     */
    public function logError($errorMessage, $errorFile = '', $errorLine = 0, $stackTrace = '') {
        try {
            // Nëse stack trace është një array, e konvertojmë në string
            if (is_array($stackTrace)) {
                $stackTrace = json_encode($stackTrace);
            }
            
            $userId = isset($_SERVER['AUTHENTICATED_USER_ID']) ? $_SERVER['AUTHENTICATED_USER_ID'] : null;
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            
            $query = "INSERT INTO regjistri_gabimeve (id_perdorues, mesazhi, skedari, rreshti, stack_trace, adresa_ip, data_krijimit) 
                      VALUES (:userId, :message, :file, :line, :stackTrace, :ipAddress, NOW())";
            $stmt = $this->db->prepare($query);
            
            // Bind parametrat
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':message', $errorMessage);
            $stmt->bindParam(':file', $errorFile);
            $stmt->bindParam(':line', $errorLine, PDO::PARAM_INT);
            $stmt->bindParam(':stackTrace', $stackTrace);
            $stmt->bindParam(':ipAddress', $ipAddress);
            
            return $stmt->execute();
        } catch (Exception $ex) {
            // Shkruaj gabimin në error_log të PHP
            error_log("Gabim gjatë regjistrimit të errorit: " . $ex->getMessage());
            return false;
        }
    }
}