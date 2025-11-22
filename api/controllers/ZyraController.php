<?php
/**
 * Kontrolleri për menaxhimin e zyrave noteriale
 */
class ZyraController {
    private $db;
    private $response;
    private $logger;
    
    /**
     * Konstruktori
     * @param PDO $db Lidhja me databazën
     * @param Response $response Objekti i përgjigjes
     * @param Logger $logger Objekti i regjistrimit të aktivitetit
     */
    public function __construct($db, $response, $logger) {
        $this->db = $db;
        $this->response = $response;
        $this->logger = $logger;
    }
    
    /**
     * Merr të gjitha zyrat
     */
    public function getAll() {
        try {
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            
            // Ndërto query-n bazuar në rolin e përdoruesit
            $query = "SELECT z.id, z.emri, z.adresa, z.qyteti, z.telefoni, z.email, 
                         z.data_krijimit, z.data_perditesimit, z.aktive,
                         (SELECT COUNT(*) FROM perdoruesit p WHERE p.id_zyra = z.id) AS numri_punonjesve
                      FROM zyrat z";
            
            if ($userRole !== 'admin') {
                $userOffice = $this->getUserOffice($userId);
                $query .= " WHERE z.id = :id_zyra";
            }
            
            $query .= " ORDER BY z.emri ASC";
            
            $stmt = $this->db->prepare($query);
            
            if ($userRole !== 'admin') {
                $stmt->bindParam(':id_zyra', $userOffice);
            }
            
            $stmt->execute();
            $zyrat = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->setData([
                'numri_total' => count($zyrat),
                'zyrat' => $zyrat
            ]);
            $this->response->send();
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në listimin e zyrave: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Merr një zyrë sipas ID
     * @param int $id ID e zyrës
     */
    public function getOne($id) {
        try {
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            $userOffice = $this->getUserOffice($userId);
            
            // Nëse nuk është admin dhe po kërkon një zyrë tjetër nga ajo e tij
            if ($userRole !== 'admin' && $userOffice != $id) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të parë këtë zyrë");
                $this->response->send();
                exit;
            }
            
            // Ndërto query-n
            $query = "SELECT z.id, z.emri, z.adresa, z.qyteti, z.telefoni, z.email, 
                         z.data_krijimit, z.data_perditesimit, z.aktive
                      FROM zyrat z
                      WHERE z.id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Kontrollo nëse u gjet zyra
            if ($stmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Zyra nuk u gjet");
                $this->response->send();
                exit;
            }
            
            $zyra = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Merr punonjësit e zyrës
            $punonjesQuery = "SELECT p.id, p.emri, p.mbiemri, p.email, p.telefoni, p.roli, p.aktiv
                            FROM perdoruesit p
                            WHERE p.id_zyra = :id_zyra
                            ORDER BY p.roli ASC, p.emri ASC";
            
            $punonjesStmt = $this->db->prepare($punonjesQuery);
            $punonjesStmt->bindParam(':id_zyra', $id);
            $punonjesStmt->execute();
            $punonjesit = $punonjesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Shto punonjësit te zyra
            $zyra['punonjesit'] = $punonjesit;
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->setData($zyra);
            $this->response->send();
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në marrjen e zyrës: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Shton një zyrë të re
     * @param array $data Të dhënat e zyrës
     */
    public function create($data) {
        try {
            // Vetëm admin mund të krijojë zyrë
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            
            if ($userRole !== 'admin') {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të krijuar zyrë të re");
                $this->response->send();
                exit;
            }
            
            // Kontrollo të dhënat
            if (!isset($data['emri']) || !isset($data['adresa']) || 
                !isset($data['qyteti'])) {
                $this->response->setHttpStatusCode(400);
                $this->response->setSuccess(false);
                $this->response->addMessage("Mungojnë të dhënat e kërkuara");
                $this->response->send();
                exit;
            }
            
            // Ndërto query-n
            $query = "INSERT INTO zyrat (emri, adresa, qyteti, telefoni, email, aktive) 
                      VALUES (:emri, :adresa, :qyteti, :telefoni, :email, :aktive)";
            $stmt = $this->db->prepare($query);
            
            // Bind parametrat
            $stmt->bindParam(':emri', $data['emri']);
            $stmt->bindParam(':adresa', $data['adresa']);
            $stmt->bindParam(':qyteti', $data['qyteti']);
            
            // Parametrat opsionale
            $telefoni = $data['telefoni'] ?? null;
            $email = $data['email'] ?? null;
            $aktive = $data['aktive'] ?? 1;
            
            $stmt->bindParam(':telefoni', $telefoni);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':aktive', $aktive);
            
            $stmt->execute();
            
            // Merr ID e zyrës së shtuar
            $newId = $this->db->lastInsertId();
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(201);
            $this->response->setSuccess(true);
            $this->response->addMessage("Zyra u shtua me sukses");
            $this->response->setData(['id' => $newId]);
            $this->response->send();
            
            // Regjistro aktivitetin
            $this->logger->logActivity(
                $userId, 
                'create_office', 
                "Krijoi zyrën '{$data['emri']}' me ID {$newId}"
            );
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në krijimin e zyrës: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Përditëson një zyrë
     * @param int $id ID e zyrës
     * @param array $data Të dhënat për përditësim
     */
    public function update($id, $data) {
        try {
            // Vetëm admin mund të përditësojë zyrë
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            
            if ($userRole !== 'admin') {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të përditësuar zyrë");
                $this->response->send();
                exit;
            }
            
            // Kontrollo nëse zyra ekziston
            $checkQuery = "SELECT id FROM zyrat WHERE id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Zyra nuk u gjet");
                $this->response->send();
                exit;
            }
            
            // Ndërto query-n e përditësimit
            $updateFields = [];
            $parameters = [];
            
            // Të dhënat që mund të përditësohen
            $allowedFields = ['emri', 'adresa', 'qyteti', 'telefoni', 'email', 'aktive'];
            
            // Shto fushat e lejuara që janë dërguar
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = :$field";
                    $parameters[":$field"] = $data[$field];
                }
            }
            
            // Nëse nuk ka asnjë fushë për t'u përditësuar
            if (empty($updateFields)) {
                $this->response->setHttpStatusCode(400);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk ka të dhëna për t'u përditësuar");
                $this->response->send();
                exit;
            }
            
            // Shto kohën e përditësimit
            $updateFields[] = "data_perditesimit = NOW()";
            
            // Ndërto query-n e përditësimit
            $query = "UPDATE zyrat SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $this->db->prepare($query);
            
            // Bind ID
            $stmt->bindParam(':id', $id);
            
            // Bind parametrat e tjerë
            foreach ($parameters as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            
            $stmt->execute();
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->addMessage("Zyra u përditësua me sukses");
            $this->response->send();
            
            // Regjistro aktivitetin
            $this->logger->logActivity(
                $userId, 
                'update_office', 
                "Përditësoi zyrën me ID " . $id
            );
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në përditësimin e zyrës: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Fshin një zyrë
     * @param int $id ID e zyrës
     */
    public function delete($id) {
        try {
            // Vetëm admin mund të fshijë zyrë
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            
            if ($userRole !== 'admin') {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të fshirë zyrë");
                $this->response->send();
                exit;
            }
            
            // Kontrollo nëse zyra ekziston
            $checkQuery = "SELECT id FROM zyrat WHERE id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Zyra nuk u gjet");
                $this->response->send();
                exit;
            }
            
            // Kontrollo nëse ka punonjës në zyrë
            $checkPunonjesQuery = "SELECT COUNT(*) as numri FROM perdoruesit WHERE id_zyra = :id_zyra";
            $checkPunonjesStmt = $this->db->prepare($checkPunonjesQuery);
            $checkPunonjesStmt->bindParam(':id_zyra', $id);
            $checkPunonjesStmt->execute();
            
            $numriPunonjesve = $checkPunonjesStmt->fetch(PDO::FETCH_ASSOC)['numri'];
            
            if ($numriPunonjesve > 0) {
                $this->response->setHttpStatusCode(400);
                $this->response->setSuccess(false);
                $this->response->addMessage("Zyra nuk mund të fshihet sepse ka punonjës të lidhur me të");
                $this->response->send();
                exit;
            }
            
            // Ndërto query-n
            $query = "DELETE FROM zyrat WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->addMessage("Zyra u fshi me sukses");
            $this->response->send();
            
            // Regjistro aktivitetin
            $this->logger->logActivity(
                $userId, 
                'delete_office', 
                "Fshiu zyrën me ID " . $id
            );
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në fshirjen e zyrës: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Merr rolin e përdoruesit
     * @param int $userId ID e përdoruesit
     * @return string Roli i përdoruesit
     */
    private function getUserRole($userId) {
        $query = "SELECT roli FROM perdoruesit WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $userId);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            return null;
        }
        
        return $stmt->fetch(PDO::FETCH_ASSOC)['roli'];
    }
    
    /**
     * Merr zyrën e përdoruesit
     * @param int $userId ID e përdoruesit
     * @return int ID e zyrës
     */
    private function getUserOffice($userId) {
        $query = "SELECT id_zyra FROM perdoruesit WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $userId);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            return null;
        }
        
        return $stmt->fetch(PDO::FETCH_ASSOC)['id_zyra'];
    }
}