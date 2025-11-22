<?php
/**
 * Kontrolleri për menaxhimin e lejeve të punonjësve
 */
class LejeController {
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
     * Merr të gjitha lejet
     */
    public function getAll() {
        try {
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            $userOffice = $this->getUserOffice($userId);
            
            // Parametrat e filtrimit
            $statusi = isset($_GET['statusi']) ? $_GET['statusi'] : null;
            $tipi = isset($_GET['tipi']) ? $_GET['tipi'] : null;
            $idZyra = isset($_GET['id_zyra']) ? (int)$_GET['id_zyra'] : null;
            $idPunonjesi = isset($_GET['id_punonjesi']) ? (int)$_GET['id_punonjesi'] : null;
            
            // Ndërto query-n bazuar në rolin e përdoruesit
            if ($userRole === 'admin') {
                $query = "SELECT l.id, l.id_punonjesi, l.tipi, l.arsyeja, l.statusi, 
                             l.data_fillimit, l.data_mbarimit, l.data_kerkeses, 
                             l.data_aprovimit, l.id_aprovuesi, l.data_perditesimit,
                             p.emri, p.mbiemri, p.email, z.emri AS emri_zyres,
                             a.emri AS emri_aprovuesit, a.mbiemri AS mbiemri_aprovuesit
                          FROM lejet l
                          JOIN perdoruesit p ON l.id_punonjesi = p.id
                          JOIN zyrat z ON p.id_zyra = z.id
                          LEFT JOIN perdoruesit a ON l.id_aprovuesi = a.id
                          WHERE 1=1";
                
                // Filtrimet
                if ($statusi) {
                    $query .= " AND l.statusi = :statusi";
                }
                
                if ($tipi) {
                    $query .= " AND l.tipi = :tipi";
                }
                
                if ($idZyra) {
                    $query .= " AND p.id_zyra = :idZyra";
                }
                
                if ($idPunonjesi) {
                    $query .= " AND l.id_punonjesi = :idPunonjesi";
                }
                
                $query .= " ORDER BY l.data_kerkeses DESC";
                
            } elseif ($userRole === 'menaxher') {
                // Menaxheri shikon vetëm lejet e punonjësve të zyrës së tij
                $query = "SELECT l.id, l.id_punonjesi, l.tipi, l.arsyeja, l.statusi, 
                             l.data_fillimit, l.data_mbarimit, l.data_kerkeses, 
                             l.data_aprovimit, l.id_aprovuesi, l.data_perditesimit,
                             p.emri, p.mbiemri, p.email
                          FROM lejet l
                          JOIN perdoruesit p ON l.id_punonjesi = p.id
                          WHERE p.id_zyra = :idZyra";
                
                // Filtrimet
                if ($statusi) {
                    $query .= " AND l.statusi = :statusi";
                }
                
                if ($tipi) {
                    $query .= " AND l.tipi = :tipi";
                }
                
                if ($idPunonjesi) {
                    $query .= " AND l.id_punonjesi = :idPunonjesi";
                }
                
                $query .= " ORDER BY l.data_kerkeses DESC";
                
            } else {
                // Punonjësi i thjeshtë shikon vetëm lejet e tij
                $query = "SELECT l.id, l.tipi, l.arsyeja, l.statusi, 
                             l.data_fillimit, l.data_mbarimit, l.data_kerkeses, 
                             l.data_aprovimit, l.id_aprovuesi, l.data_perditesimit,
                             a.emri AS emri_aprovuesit, a.mbiemri AS mbiemri_aprovuesit
                          FROM lejet l
                          LEFT JOIN perdoruesit a ON l.id_aprovuesi = a.id
                          WHERE l.id_punonjesi = :idPunonjesi";
                
                // Filtrimet
                if ($statusi) {
                    $query .= " AND l.statusi = :statusi";
                }
                
                if ($tipi) {
                    $query .= " AND l.tipi = :tipi";
                }
                
                $query .= " ORDER BY l.data_kerkeses DESC";
                $idPunonjesi = $userId; // Punonjësi shikon vetëm lejet e tij
            }
            
            $stmt = $this->db->prepare($query);
            
            // Bind parametrat
            if ($userRole === 'admin') {
                if ($statusi) {
                    $stmt->bindParam(':statusi', $statusi);
                }
                
                if ($tipi) {
                    $stmt->bindParam(':tipi', $tipi);
                }
                
                if ($idZyra) {
                    $stmt->bindParam(':idZyra', $idZyra);
                }
                
                if ($idPunonjesi) {
                    $stmt->bindParam(':idPunonjesi', $idPunonjesi);
                }
            } elseif ($userRole === 'menaxher') {
                $stmt->bindParam(':idZyra', $userOffice);
                
                if ($statusi) {
                    $stmt->bindParam(':statusi', $statusi);
                }
                
                if ($tipi) {
                    $stmt->bindParam(':tipi', $tipi);
                }
                
                if ($idPunonjesi) {
                    $stmt->bindParam(':idPunonjesi', $idPunonjesi);
                }
            } else {
                $stmt->bindParam(':idPunonjesi', $idPunonjesi);
                
                if ($statusi) {
                    $stmt->bindParam(':statusi', $statusi);
                }
                
                if ($tipi) {
                    $stmt->bindParam(':tipi', $tipi);
                }
            }
            
            $stmt->execute();
            $lejet = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->setData([
                'numri_total' => count($lejet),
                'lejet' => $lejet
            ]);
            $this->response->send();
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në listimin e lejeve: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Merr një leje sipas ID
     * @param int $id ID e lejes
     */
    public function getOne($id) {
        try {
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            $userOffice = $this->getUserOffice($userId);
            
            // Ndërto query-n
            $query = "SELECT l.id, l.id_punonjesi, l.tipi, l.arsyeja, l.statusi, 
                         l.data_fillimit, l.data_mbarimit, l.data_kerkeses, 
                         l.data_aprovimit, l.id_aprovuesi, l.data_perditesimit,
                         p.emri, p.mbiemri, p.email, p.id_zyra,
                         a.emri AS emri_aprovuesit, a.mbiemri AS mbiemri_aprovuesit
                      FROM lejet l
                      JOIN perdoruesit p ON l.id_punonjesi = p.id
                      LEFT JOIN perdoruesit a ON l.id_aprovuesi = a.id
                      WHERE l.id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Kontrollo nëse u gjet leja
            if ($stmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Leja nuk u gjet");
                $this->response->send();
                exit;
            }
            
            $leje = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Kontrollo të drejtat e përdoruesit
            if ($userRole === 'punonjes' && $leje['id_punonjesi'] != $userId) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të parë këtë leje");
                $this->response->send();
                exit;
            }
            
            if ($userRole === 'menaxher' && $leje['id_zyra'] != $userOffice) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të parë këtë leje");
                $this->response->send();
                exit;
            }
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->setData($leje);
            $this->response->send();
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në marrjen e lejes: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Kërkon një leje të re
     * @param array $data Të dhënat e lejes
     */
    public function create($data) {
        try {
            // Kontrollo të dhënat
            if (!isset($data['tipi']) || !isset($data['data_fillimit']) || 
                !isset($data['data_mbarimit'])) {
                $this->response->setHttpStatusCode(400);
                $this->response->setSuccess(false);
                $this->response->addMessage("Mungojnë të dhënat e kërkuara");
                $this->response->send();
                exit;
            }
            
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            
            // Nëse është admin ose menaxher, mund të kërkojë leje për një punonjës tjetër
            $userRole = $this->getUserRole($userId);
            $idPunonjesi = isset($data['id_punonjesi']) ? $data['id_punonjesi'] : $userId;
            
            // Nëse është punonjës i thjeshtë, mund të kërkojë leje vetëm për veten
            if ($userRole === 'punonjes' && $idPunonjesi != $userId) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të kërkuar leje për një punonjës tjetër");
                $this->response->send();
                exit;
            }
            
            // Nëse është menaxher, mund të kërkojë leje vetëm për punonjësit e zyrës së tij
            if ($userRole === 'menaxher' && $idPunonjesi != $userId) {
                $userOffice = $this->getUserOffice($userId);
                $punonjesOffice = $this->getUserOffice($idPunonjesi);
                
                if ($userOffice != $punonjesOffice) {
                    $this->response->setHttpStatusCode(403);
                    $this->response->setSuccess(false);
                    $this->response->addMessage("Nuk keni të drejta për të kërkuar leje për një punonjës jashtë zyrës tuaj");
                    $this->response->send();
                    exit;
                }
            }
            
            // Ndërto query-n
            $query = "INSERT INTO lejet (id_punonjesi, tipi, arsyeja, statusi, data_fillimit, data_mbarimit, data_kerkeses) 
                      VALUES (:id_punonjesi, :tipi, :arsyeja, :statusi, :data_fillimit, :data_mbarimit, NOW())";
            $stmt = $this->db->prepare($query);
            
            // Bind parametrat
            $stmt->bindParam(':id_punonjesi', $idPunonjesi);
            $stmt->bindParam(':tipi', $data['tipi']);
            $stmt->bindParam(':data_fillimit', $data['data_fillimit']);
            $stmt->bindParam(':data_mbarimit', $data['data_mbarimit']);
            
            // Parametrat me vlera default ose opsionale
            $arsyeja = $data['arsyeja'] ?? null;
            $statusi = 'Në pritje'; // Lejet e reja janë gjithmonë në pritje
            
            $stmt->bindParam(':arsyeja', $arsyeja);
            $stmt->bindParam(':statusi', $statusi);
            
            $stmt->execute();
            
            // Merr ID e lejes së shtuar
            $newId = $this->db->lastInsertId();
            
            // Dërgo njoftim për menaxherin nëse nuk është ai që ka krijuar lejen
            if ($userRole !== 'menaxher' && $userRole !== 'admin') {
                $this->njoftoMenaxherinPerLejeTeRe($idPunonjesi, $data['tipi'], $newId);
            }
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(201);
            $this->response->setSuccess(true);
            $this->response->addMessage("Kërkesa për leje u dërgua me sukses");
            $this->response->setData(['id' => $newId]);
            $this->response->send();
            
            // Regjistro aktivitetin
            $this->logger->logActivity(
                $userId, 
                'create_leave', 
                "Kërkoi leje të tipit '{$data['tipi']}' për punonjësin me ID {$idPunonjesi}"
            );
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në krijimin e lejes: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Përditëson një leje
     * @param int $id ID e lejes
     * @param array $data Të dhënat për përditësim
     */
    public function update($id, $data) {
        try {
            // Kontrollo nëse leja ekziston
            $checkQuery = "SELECT l.id, l.id_punonjesi, l.statusi, p.id_zyra 
                          FROM lejet l
                          JOIN perdoruesit p ON l.id_punonjesi = p.id
                          WHERE l.id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Leja nuk u gjet");
                $this->response->send();
                exit;
            }
            
            $leje = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            $userOffice = $this->getUserOffice($userId);
            
            // Vetëm punonjësi mund të përditësojë lejen e tij dhe vetëm nëse është në statusin 'Në pritje'
            if ($leje['id_punonjesi'] == $userId) {
                if ($leje['statusi'] !== 'Në pritje') {
                    $this->response->setHttpStatusCode(400);
                    $this->response->setSuccess(false);
                    $this->response->addMessage("Leja nuk mund të përditësohet pasi nuk është në statusin 'Në pritje'");
                    $this->response->send();
                    exit;
                }
            } 
            // Menaxheri mund të përditësojë vetëm lejet e punonjësve të zyrës së tij
            else if ($userRole === 'menaxher') {
                if ($leje['id_zyra'] != $userOffice) {
                    $this->response->setHttpStatusCode(403);
                    $this->response->setSuccess(false);
                    $this->response->addMessage("Nuk keni të drejta për të përditësuar këtë leje");
                    $this->response->send();
                    exit;
                }
            }
            // Kontrollo nëse është admin
            else if ($userRole !== 'admin') {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të përditësuar këtë leje");
                $this->response->send();
                exit;
            }
            
            // Ndërto query-n e përditësimit
            $updateFields = [];
            $parameters = [];
            
            // Të dhënat që mund të përditësohen nga punonjësi
            if ($leje['id_punonjesi'] == $userId && $userRole === 'punonjes') {
                $allowedFields = ['tipi', 'arsyeja', 'data_fillimit', 'data_mbarimit'];
                
                // Shto fushat e lejuara që janë dërguar
                foreach ($allowedFields as $field) {
                    if (isset($data[$field])) {
                        $updateFields[] = "$field = :$field";
                        $parameters[":$field"] = $data[$field];
                    }
                }
            } 
            // Të dhënat që mund të përditësohen nga menaxheri ose admin
            else if ($userRole === 'menaxher' || $userRole === 'admin') {
                // Statusi mund të përditësohet nga menaxheri/admin
                if (isset($data['statusi'])) {
                    $updateFields[] = "statusi = :statusi";
                    $parameters[":statusi"] = $data['statusi'];
                    
                    // Nëse statusi është ndryshuar në 'Aprovuar' ose 'Refuzuar', vendosim datën e aprovimit dhe id_aprovuesi
                    if ($data['statusi'] === 'Aprovuar' || $data['statusi'] === 'Refuzuar') {
                        $updateFields[] = "data_aprovimit = NOW()";
                        $updateFields[] = "id_aprovuesi = :id_aprovuesi";
                        $parameters[":id_aprovuesi"] = $userId;
                    }
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
            $query = "UPDATE lejet SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $this->db->prepare($query);
            
            // Bind ID
            $stmt->bindParam(':id', $id);
            
            // Bind parametrat e tjerë
            foreach ($parameters as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            
            $stmt->execute();
            
            // Nëse statusi është ndryshuar, dërgo njoftim për punonjësin
            if (isset($data['statusi']) && ($data['statusi'] === 'Aprovuar' || $data['statusi'] === 'Refuzuar')) {
                $this->njoftoPunonjesinPerStatusLejen($leje['id_punonjesi'], $id, $data['statusi']);
            }
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->addMessage("Leja u përditësua me sukses");
            $this->response->send();
            
            // Regjistro aktivitetin
            $this->logger->logActivity(
                $userId, 
                'update_leave', 
                "Përditësoi lejen me ID " . $id
            );
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në përditësimin e lejes: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Fshin një leje
     * @param int $id ID e lejes
     */
    public function delete($id) {
        try {
            // Kontrollo nëse leja ekziston
            $checkQuery = "SELECT l.id, l.id_punonjesi, l.statusi, p.id_zyra 
                          FROM lejet l
                          JOIN perdoruesit p ON l.id_punonjesi = p.id
                          WHERE l.id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Leja nuk u gjet");
                $this->response->send();
                exit;
            }
            
            $leje = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            
            // Vetëm punonjësi mund të fshijë lejen e tij dhe vetëm nëse është në statusin 'Në pritje'
            if ($leje['id_punonjesi'] == $userId) {
                if ($leje['statusi'] !== 'Në pritje') {
                    $this->response->setHttpStatusCode(400);
                    $this->response->setSuccess(false);
                    $this->response->addMessage("Leja nuk mund të fshihet pasi nuk është në statusin 'Në pritje'");
                    $this->response->send();
                    exit;
                }
            } 
            // Vetëm admin mund të fshijë lejet e të tjerëve
            else if ($userRole !== 'admin') {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të fshirë këtë leje");
                $this->response->send();
                exit;
            }
            
            // Ndërto query-n
            $query = "DELETE FROM lejet WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->addMessage("Leja u anulua me sukses");
            $this->response->send();
            
            // Regjistro aktivitetin
            $this->logger->logActivity(
                $userId, 
                'delete_leave', 
                "Fshiu lejen me ID " . $id
            );
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në fshirjen e lejes: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Dërgon njoftim për menaxherin për një kërkesë të re leje
     * @param int $punonjesiId ID e punonjësit që kërkon leje
     * @param string $tipiLejes Tipi i lejes
     * @param int $lejeId ID e lejes së krijuar
     */
    private function njoftoMenaxherinPerLejeTeRe($punonjesiId, $tipiLejes, $lejeId) {
        try {
            // Gjeji menaxherin e zyrës së punonjësit
            $query = "SELECT p.id, p.id_zyra, CONCAT(p.emri, ' ', p.mbiemri) AS emri_punonjesit,
                       (SELECT m.id FROM perdoruesit m 
                        WHERE m.id_zyra = p.id_zyra AND m.roli = 'menaxher' LIMIT 1) AS id_menaxheri
                      FROM perdoruesit p 
                      WHERE p.id = :id_punonjesi";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id_punonjesi', $punonjesiId);
            $stmt->execute();
            
            $punonjesi = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Nëse nuk ka menaxher, nuk mund të dërgohet njoftim
            if (!$punonjesi['id_menaxheri']) {
                return;
            }
            
            // Krijo njoftimin
            $titulli = "Kërkesë e re për leje";
            $permbajtja = "Punonjësi {$punonjesi['emri_punonjesit']} ka bërë një kërkesë të re për leje të tipit '{$tipiLejes}'.";
            
            $njoftimQuery = "INSERT INTO njoftimet (id_punonjesi, titulli, permbajtja, id_leje, lexuar, data_krijimit) 
                            VALUES (:id_punonjesi, :titulli, :permbajtja, :id_leje, 0, NOW())";
            $njoftimStmt = $this->db->prepare($njoftimQuery);
            
            $njoftimStmt->bindParam(':id_punonjesi', $punonjesi['id_menaxheri']);
            $njoftimStmt->bindParam(':titulli', $titulli);
            $njoftimStmt->bindParam(':permbajtja', $permbajtja);
            $njoftimStmt->bindParam(':id_leje', $lejeId);
            
            $njoftimStmt->execute();
            
        } catch (Exception $ex) {
            $this->logger->logError("Gabim në dërgimin e njoftimit për menaxherin: " . $ex->getMessage(), __FILE__, __LINE__);
        }
    }
    
    /**
     * Dërgon njoftim për punonjësin për ndryshimin e statusit të lejes
     * @param int $punonjesiId ID e punonjësit
     * @param int $lejeId ID e lejes
     * @param string $statusi Statusi i ri i lejes
     */
    private function njoftoPunonjesinPerStatusLejen($punonjesiId, $lejeId, $statusi) {
        try {
            // Merr informacionin për aprovuesin
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $query = "SELECT CONCAT(emri, ' ', mbiemri) AS emri_aprovuesit, roli
                     FROM perdoruesit 
                     WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $userId);
            $stmt->execute();
            
            $aprovuesi = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Merr informacionin për lejen
            $lejeQuery = "SELECT tipi FROM lejet WHERE id = :id";
            $lejeStmt = $this->db->prepare($lejeQuery);
            $lejeStmt->bindParam(':id', $lejeId);
            $lejeStmt->execute();
            
            $leje = $lejeStmt->fetch(PDO::FETCH_ASSOC);
            
            // Krijo njoftimin
            $titulli = "Leja juaj është " . ($statusi === 'Aprovuar' ? "aprovuar" : "refuzuar");
            $permbajtja = "Kërkesa juaj për leje të tipit '{$leje['tipi']}' është {$statusi} nga {$aprovuesi['emri_aprovuesit']} ({$aprovuesi['roli']}).";
            
            $njoftimQuery = "INSERT INTO njoftimet (id_punonjesi, titulli, permbajtja, id_leje, lexuar, data_krijimit) 
                            VALUES (:id_punonjesi, :titulli, :permbajtja, :id_leje, 0, NOW())";
            $njoftimStmt = $this->db->prepare($njoftimQuery);
            
            $njoftimStmt->bindParam(':id_punonjesi', $punonjesiId);
            $njoftimStmt->bindParam(':titulli', $titulli);
            $njoftimStmt->bindParam(':permbajtja', $permbajtja);
            $njoftimStmt->bindParam(':id_leje', $lejeId);
            
            $njoftimStmt->execute();
            
        } catch (Exception $ex) {
            $this->logger->logError("Gabim në dërgimin e njoftimit për punonjësin: " . $ex->getMessage(), __FILE__, __LINE__);
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