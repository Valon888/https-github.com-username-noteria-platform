<?php
/**
 * Klasa për lidhjen me bazën e të dhënave
 */
class Database {
    // Parametrat e lidhjes me databazën
    private $host = "localhost";
    private $db_name = "noteria";
    private $username = "root";
    private $password = "";
    public $conn;

    /**
     * Metoda për të krijuar lidhjen me bazën e të dhënave
     * @return PDO Objekti i lidhjes me databazën
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8",
                $this->username,
                $this->password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch(PDOException $e) {
            // Regjistroni gabimin në log dhe riktheni një përgjigje të përshtatshme
            error_log("Gabim në lidhjen me databazë: " . $e->getMessage());
            throw new Exception("Lidhja me databazë dështoi. Ju lutemi kontaktoni administratorin.");
        }

        return $this->conn;
    }
}