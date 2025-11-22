<?php
/**
 * Kontrolluesi për detyrat e punonjësve
 */
class DetyreController {
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
     * Merr të gjitha detyrat
     */
    public function getAll() {
        try {
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            $userOffice = $this->getUserOffice($userId);
            
            // Parametrat e filtrimit
            $statusi = isset($_GET['statusi']) ? $_GET['statusi'] : null;
            $prioriteti = isset($_GET['prioriteti']) ? $_GET['prioriteti'] : null;
            $idZyra = isset($_GET['id_zyra']) ? (int)$_GET['id_zyra'] : null;
            $idPunonjesi = isset($_GET['id_punonjesi']) ? (int)$_GET['id_punonjesi'] : null;
            
            // Ndërto query-n bazuar në rolin e përdoruesit
            if ($userRole === 'admin') {
                $query = "SELECT d.id, d.id_punonjesi, d.titulli, d.pershkrimi, d.statusi, d.prioriteti, 
                             d.afati, d.data_krijimit, d.data_perditesimit, d.id_krijuesi,
                             p.emri, p.mbiemri, p.email, z.emri AS emri_zyres
                      FROM detyrat_punonjesve d
                      JOIN perdoruesit p ON d.id_punonjesi = p.id
                      JOIN zyrat z ON p.id_zyra = z.id
                      WHERE 1=1";
                
                // Filtrimet
                if ($statusi) {
                    $query .= " AND d.statusi = :statusi";
                }
                
                if ($prioriteti) {
                    $query .= " AND d.prioriteti = :prioriteti";
                }
                
                if ($idZyra) {
                    $query .= " AND p.id_zyra = :idZyra";
                }
                
                if ($idPunonjesi) {
                    $query .= " AND d.id_punonjesi = :idPunonjesi";
                }
                
                $query .= " ORDER BY d.afati ASC, d.prioriteti DESC";
                
            } elseif ($userRole === 'menaxher') {
                // Menaxheri shikon vetëm detyrat e punonjësve të zyrës së tij
                $query = "SELECT d.id, d.id_punonjesi, d.titulli, d.pershkrimi, d.statusi, d.prioriteti, 
                             d.afati, d.data_krijimit, d.data_perditesimit, d.id_krijuesi,
                             p.emri, p.mbiemri, p.email
                      FROM detyrat_punonjesve d
                      JOIN perdoruesit p ON d.id_punonjesi = p.id
                      WHERE p.id_zyra = :idZyra";
                
                // Filtrimet
                if ($statusi) {
                    $query .= " AND d.statusi = :statusi";
                }
                
                if ($prioriteti) {
                    $query .= " AND d.prioriteti = :prioriteti";
                }
                
                if ($idPunonjesi) {
                    $query .= " AND d.id_punonjesi = :idPunonjesi";
                }
                
                $query .= " ORDER BY d.afati ASC, d.prioriteti DESC";
                
            } else {
                // Punonjësi i thjeshtë shikon vetëm detyrat e tij
                $query = "SELECT d.id, d.titulli, d.pershkrimi, d.statusi, d.prioriteti, 
                             d.afati, d.data_krijimit, d.data_perditesimit, d.id_krijuesi,
                             k.emri AS emri_krijuesit, k.mbiemri AS mbiemri_krijuesit
                      FROM detyrat_punonjesve d
                      LEFT JOIN perdoruesit k ON d.id_krijuesi = k.id
                      WHERE d.id_punonjesi = :idPunonjesi";
                
                // Filtrimet
                if ($statusi) {
                    $query .= " AND d.statusi = :statusi";
                }
                
                if ($prioriteti) {
                    $query .= " AND d.prioriteti = :prioriteti";
                }
                
                $query .= " ORDER BY d.afati ASC, d.prioriteti DESC";
                $idPunonjesi = $userId; // Punonjësi shikon vetëm detyrat e tij
            }
            
            $stmt = $this->db->prepare($query);
            
            // Bind parametrat
            if ($userRole === 'admin') {
                if ($statusi) {
                    $stmt->bindParam(':statusi', $statusi);
                }
                
                if ($prioriteti) {
                    $stmt->bindParam(':prioriteti', $prioriteti);
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
                
                if ($prioriteti) {
                    $stmt->bindParam(':prioriteti', $prioriteti);
                }
                
                if ($idPunonjesi) {
                    $stmt->bindParam(':idPunonjesi', $idPunonjesi);
                }
            } else {
                $stmt->bindParam(':idPunonjesi', $idPunonjesi);
                
                if ($statusi) {
                    $stmt->bindParam(':statusi', $statusi);
                }
                
                if ($prioriteti) {
                    $stmt->bindParam(':prioriteti', $prioriteti);
                }
            }
            
            $stmt->execute();
            $detyrat = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->setData([
                'numri_total' => count($detyrat),
                'detyrat' => $detyrat
            ]);
            $this->response->send();
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në listimin e detyrave: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Merr një detyrë sipas ID
     * @param int $id ID e detyrës
     */
    public function getOne($id) {
        try {
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            $userOffice = $this->getUserOffice($userId);
            
            // Ndërto query-n
            $query = "SELECT d.id, d.id_punonjesi, d.titulli, d.pershkrimi, d.statusi, d.prioriteti, 
                         d.afati, d.data_krijimit, d.data_perditesimit, d.id_krijuesi,
                         p.emri, p.mbiemri, p.email, p.id_zyra,
                         k.emri AS emri_krijuesit, k.mbiemri AS mbiemri_krijuesit
                  FROM detyrat_punonjesve d
                  JOIN perdoruesit p ON d.id_punonjesi = p.id
                  LEFT JOIN perdoruesit k ON d.id_krijuesi = k.id
                  WHERE d.id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Kontrollo nëse u gjet detyra
            if ($stmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Detyra nuk u gjet");
                $this->response->send();
                exit;
            }
            
            $detyra = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Kontrollo të drejtat e përdoruesit
            if ($userRole === 'punonjes' && $detyra['id_punonjesi'] != $userId) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të parë këtë detyrë");
                $this->response->send();
                exit;
            }
            
            if ($userRole === 'menaxher' && $detyra['id_zyra'] != $userOffice) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të parë këtë detyrë");
                $this->response->send();
                exit;
            }
            
            // Merr komentet e detyrës
            $komentQuery = "SELECT id, id_perdoruesi, koment, data_krijimit,
                             (SELECT CONCAT(emri, ' ', mbiemri) FROM perdoruesit WHERE id = k.id_perdoruesi) AS emri_perdoruesit
                          FROM komentet_detyrave k
                          WHERE id_detyre = :id
                          ORDER BY data_krijimit ASC";
            $komentStmt = $this->db->prepare($komentQuery);
            $komentStmt->bindParam(':id', $id);
            $komentStmt->execute();
            $komentet = $komentStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Shto komentet te detyra
            $detyra['komentet'] = $komentet;
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->setData($detyra);
            $this->response->send();
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në marrjen e detyrës: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Shton një detyrë të re
     * @param array $data Të dhënat e detyrës
     */
    public function create($data) {
        try {
            // Kontrollo të dhënat
            if (!isset($data['id_punonjesi']) || !isset($data['titulli']) || 
                !isset($data['prioriteti']) || !isset($data['afati'])) {
                $this->response->setHttpStatusCode(400);
                $this->response->setSuccess(false);
                $this->response->addMessage("Mungojnë të dhënat e kërkuara");
                $this->response->send();
                exit;
            }
            
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            $userOffice = $this->getUserOffice($userId);
            
            // Kontrollo nëse punonjësi ekziston dhe nëse përdoruesi ka të drejtë për t'i caktuar detyrë
            $punonjesQuery = "SELECT id, id_zyra FROM perdoruesit WHERE id = :id";
            $punonjesStmt = $this->db->prepare($punonjesQuery);
            $punonjesStmt->bindParam(':id', $data['id_punonjesi']);
            $punonjesStmt->execute();
            
            if ($punonjesStmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Punonjësi nuk u gjet");
                $this->response->send();
                exit;
            }
            
            $punonjesi = $punonjesStmt->fetch(PDO::FETCH_ASSOC);
            
            // Menaxheri mund të caktojë detyrë vetëm për punonjësit e zyrës së tij
            if ($userRole === 'menaxher' && $punonjesi['id_zyra'] != $userOffice) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për t'i caktuar detyrë këtij punonjësi");
                $this->response->send();
                exit;
            }
            
            // Punonjësi i thjeshtë mund të caktojë detyrë vetëm vetes
            if ($userRole === 'punonjes' && $data['id_punonjesi'] != $userId) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për t'i caktuar detyrë një punonjësi tjetër");
                $this->response->send();
                exit;
            }
            
            // Ndërto query-n
            $query = "INSERT INTO detyrat_punonjesve (id_punonjesi, titulli, pershkrimi, statusi, prioriteti, afati, id_krijuesi) 
                      VALUES (:id_punonjesi, :titulli, :pershkrimi, :statusi, :prioriteti, :afati, :id_krijuesi)";
            $stmt = $this->db->prepare($query);
            
            // Bind parametrat
            $stmt->bindParam(':id_punonjesi', $data['id_punonjesi']);
            $stmt->bindParam(':titulli', $data['titulli']);
            $stmt->bindParam(':prioriteti', $data['prioriteti']);
            $stmt->bindParam(':afati', $data['afati']);
            $stmt->bindParam(':id_krijuesi', $userId);
            
            // Parametrat me vlera default ose opsionale
            $pershkrimi = $data['pershkrimi'] ?? null;
            $statusi = $data['statusi'] ?? 'Në pritje';
            
            $stmt->bindParam(':pershkrimi', $pershkrimi);
            $stmt->bindParam(':statusi', $statusi);
            
            $stmt->execute();
            
            // Merr ID e detyrës së shtuar
            $newId = $this->db->lastInsertId();
            
            // Krijo një njoftim për punonjësin nëse nuk është ai që ka krijuar detyrën
            if ($data['id_punonjesi'] != $userId) {
                $this->krijoNjoftimDetyre(
                    $data['id_punonjesi'], 
                    "Detyrë e re", 
                    "Ju është caktuar një detyrë e re: " . $data['titulli'],
                    $newId
                );
            }
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(201);
            $this->response->setSuccess(true);
            $this->response->addMessage("Detyra u shtua me sukses");
            $this->response->setData(['id' => $newId]);
            $this->response->send();
            
            // Regjistro aktivitetin
            $this->logger->logActivity(
                $userId, 
                'create_task', 
                "Krijoi detyrën '{$data['titulli']}' për punonjësin me ID {$data['id_punonjesi']}"
            );
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në krijimin e detyrës: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Përditëson një detyrë
     * @param int $id ID e detyrës
     * @param array $data Të dhënat për përditësim
     */
    public function update($id, $data) {
        try {
            // Kontrollo nëse detyra ekziston
            $checkQuery = "SELECT d.id, d.id_punonjesi, d.statusi, p.id_zyra 
                          FROM detyrat_punonjesve d
                          JOIN perdoruesit p ON d.id_punonjesi = p.id
                          WHERE d.id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Detyra nuk u gjet");
                $this->response->send();
                exit;
            }
            
            $detyra = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            $userOffice = $this->getUserOffice($userId);
            
            // Punonjësi mund të përditësojë vetëm detyrat e tij
            if ($userRole === 'punonjes' && $detyra['id_punonjesi'] != $userId) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të përditësuar këtë detyrë");
                $this->response->send();
                exit;
            }
            
            // Menaxheri mund të përditësojë vetëm detyrat e punonjësve të zyrës së tij
            if ($userRole === 'menaxher' && $detyra['id_zyra'] != $userOffice) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të përditësuar këtë detyrë");
                $this->response->send();
                exit;
            }
            
            // Ndërto query-n e përditësimit
            $updateFields = [];
            $parameters = [];
            
            // Të dhënat që mund të përditësohen
            $allowedFields = ['titulli', 'pershkrimi', 'statusi', 'prioriteti', 'afati'];
            
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
            $query = "UPDATE detyrat_punonjesve SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $this->db->prepare($query);
            
            // Bind ID
            $stmt->bindParam(':id', $id);
            
            // Bind parametrat e tjerë
            foreach ($parameters as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            
            $stmt->execute();
            
            // Kontrollo nëse statusi është ndryshuar në Përfunduar
            if (isset($data['statusi']) && $data['statusi'] === 'Përfunduar' && $detyra['statusi'] !== 'Përfunduar') {
                // Krijo njoftim për krijuesin e detyrës
                $this->njoftoKrijuesinPerPerfundim($id, $detyra['id_punonjesi']);
            }
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->addMessage("Detyra u përditësua me sukses");
            $this->response->send();
            
            // Regjistro aktivitetin
            $this->logger->logActivity(
                $userId, 
                'update_task', 
                "Përditësoi detyrën me ID " . $id
            );
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në përditësimin e detyrës: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Fshin një detyrë
     * @param int $id ID e detyrës
     */
    public function delete($id) {
        try {
            // Kontrollo nëse detyra ekziston
            $checkQuery = "SELECT d.id, d.id_punonjesi, d.id_krijuesi, p.id_zyra 
                          FROM detyrat_punonjesve d
                          JOIN perdoruesit p ON d.id_punonjesi = p.id
                          WHERE d.id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Detyra nuk u gjet");
                $this->response->send();
                exit;
            }
            
            $detyra = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            $userOffice = $this->getUserOffice($userId);
            
            // Vetëm krijuesi ose admin mund të fshijë detyrën
            if ($userRole !== 'admin' && $detyra['id_krijuesi'] != $userId) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të fshirë këtë detyrë");
                $this->response->send();
                exit;
            }
            
            // Menaxheri mund të fshijë vetëm detyrat e punonjësve të zyrës së tij
            if ($userRole === 'menaxher' && $detyra['id_zyra'] != $userOffice) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të fshirë këtë detyrë");
                $this->response->send();
                exit;
            }
            
            // Ndërto query-n
            $query = "DELETE FROM detyrat_punonjesve WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->addMessage("Detyra u fshi me sukses");
            $this->response->send();
            
            // Regjistro aktivitetin
            $this->logger->logActivity(
                $userId, 
                'delete_task', 
                "Fshiu detyrën me ID " . $id
            );
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në fshirjen e detyrës: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Shto koment në një detyrë
     * @param int $id ID e detyrës
     * @param array $data Të dhënat e komentit
     */
    public function createSubResource($id, $subResource, $data) {
        if ($subResource !== 'koment') {
            $this->response->setHttpStatusCode(404);
            $this->response->setSuccess(false);
            $this->response->addMessage("Burimi i kërkuar nuk ekziston");
            $this->response->send();
            exit;
        }
        
        try {
            // Kontrollo të dhënat
            if (!isset($data['koment'])) {
                $this->response->setHttpStatusCode(400);
                $this->response->setSuccess(false);
                $this->response->addMessage("Mungon komenti");
                $this->response->send();
                exit;
            }
            
            // Kontrollo nëse detyra ekziston
            $checkQuery = "SELECT d.id, d.id_punonjesi, p.id_zyra 
                          FROM detyrat_punonjesve d
                          JOIN perdoruesit p ON d.id_punonjesi = p.id
                          WHERE d.id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Detyra nuk u gjet");
                $this->response->send();
                exit;
            }
            
            $detyra = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            $userOffice = $this->getUserOffice($userId);
            
            // Punonjësi mund të komentojë vetëm detyrat e tij
            if ($userRole === 'punonjes' && $detyra['id_punonjesi'] != $userId) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të komentuar këtë detyrë");
                $this->response->send();
                exit;
            }
            
            // Menaxheri mund të komentojë vetëm detyrat e punonjësve të zyrës së tij
            if ($userRole === 'menaxher' && $detyra['id_zyra'] != $userOffice) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të komentuar këtë detyrë");
                $this->response->send();
                exit;
            }
            
            // Ndërto query-n
            $query = "INSERT INTO komentet_detyrave (id_detyre, id_perdoruesi, koment) 
                      VALUES (:id_detyre, :id_perdoruesi, :koment)";
            $stmt = $this->db->prepare($query);
            
            // Bind parametrat
            $stmt->bindParam(':id_detyre', $id);
            $stmt->bindParam(':id_perdoruesi', $userId);
            $stmt->bindParam(':koment', $data['koment']);
            
            $stmt->execute();
            
            // Merr ID e komentit të shtuar
            $newId = $this->db->lastInsertId();
            
            // Krijo njoftim për punonjësin nëse komenti është nga dikush tjetër
            if ($detyra['id_punonjesi'] != $userId) {
                $this->krijoNjoftimPunonjesitPerKoment($id, $detyra['id_punonjesi']);
            }
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(201);
            $this->response->setSuccess(true);
            $this->response->addMessage("Komenti u shtua me sukses");
            $this->response->setData(['id' => $newId]);
            $this->response->send();
            
            // Regjistro aktivitetin
            $this->logger->logActivity(
                $userId, 
                'add_task_comment', 
                "Shtoi koment në detyrën me ID " . $id
            );
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në shtimin e komentit: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Krijo njoftim për detyrë të re
     * @param int $punonjesiId ID e punonjësit
     * @param string $titulli Titulli i njoftimit
     * @param string $permbajtja Përmbajtja e njoftimit
     * @param int $detyraId ID e detyrës
     */
    private function krijoNjoftimDetyre($punonjesiId, $titulli, $permbajtja, $detyraId) {
        try {
            $query = "INSERT INTO njoftimet (id_punonjesi, titulli, permbajtja, id_detyre, lexuar, data_krijimit) 
                      VALUES (:id_punonjesi, :titulli, :permbajtja, :id_detyre, 0, NOW())";
            $stmt = $this->db->prepare($query);
            
            $stmt->bindParam(':id_punonjesi', $punonjesiId);
            $stmt->bindParam(':titulli', $titulli);
            $stmt->bindParam(':permbajtja', $permbajtja);
            $stmt->bindParam(':id_detyre', $detyraId);
            
            $stmt->execute();
        } catch (Exception $ex) {
            $this->logger->logError("Gabim në krijimin e njoftimit: " . $ex->getMessage(), __FILE__, __LINE__);
        }
    }
    
    /**
     * Njofto krijuesin e detyrës për përfundimin e saj
     * @param int $detyraId ID e detyrës
     * @param int $punonjesiId ID e punonjësit që ka përfunduar detyrën
     */
    private function njoftoKrijuesinPerPerfundim($detyraId, $punonjesiId) {
        try {
            // Merr informacionin e detyrës dhe krijuesit
            $query = "SELECT d.id_krijuesi, d.titulli, 
                            CONCAT(p.emri, ' ', p.mbiemri) AS emri_punonjesit 
                     FROM detyrat_punonjesve d 
                     JOIN perdoruesit p ON d.id_punonjesi = p.id 
                     WHERE d.id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $detyraId);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                return;
            }
            
            $detyra = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Nëse krijuesi dhe punonjësi janë i njëjti person, nuk kemi pse dërgojmë njoftim
            if ($detyra['id_krijuesi'] == $punonjesiId) {
                return;
            }
            
            // Krijo njoftimin
            $njoftimQuery = "INSERT INTO njoftimet (id_punonjesi, titulli, permbajtja, id_detyre, lexuar, data_krijimit) 
                            VALUES (:id_punonjesi, :titulli, :permbajtja, :id_detyre, 0, NOW())";
            $njoftimStmt = $this->db->prepare($njoftimQuery);
            
            $titulli = "Detyrë e përfunduar";
            $permbajtja = "Punonjësi {$detyra['emri_punonjesit']} ka përfunduar detyrën '{$detyra['titulli']}'.";
            
            $njoftimStmt->bindParam(':id_punonjesi', $detyra['id_krijuesi']);
            $njoftimStmt->bindParam(':titulli', $titulli);
            $njoftimStmt->bindParam(':permbajtja', $permbajtja);
            $njoftimStmt->bindParam(':id_detyre', $detyraId);
            
            $njoftimStmt->execute();
        } catch (Exception $ex) {
            $this->logger->logError("Gabim në njoftimin e krijuesit për përfundim detyre: " . $ex->getMessage(), __FILE__, __LINE__);
        }
    }
    
    /**
     * Krijo njoftim për punonjësin për koment të ri në detyrë
     * @param int $detyraId ID e detyrës
     * @param int $punonjesiId ID e punonjësit
     */
    private function krijoNjoftimPunonjesitPerKoment($detyraId, $punonjesiId) {
        try {
            // Merr informacionin e detyrës dhe komentuesit
            $query = "SELECT d.titulli, k.id_perdoruesi, 
                            CONCAT(p.emri, ' ', p.mbiemri) AS emri_komentuesit 
                     FROM detyrat_punonjesve d 
                     JOIN komentet_detyrave k ON d.id = k.id_detyre
                     JOIN perdoruesit p ON k.id_perdoruesi = p.id 
                     WHERE d.id = :id
                     ORDER BY k.id DESC LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $detyraId);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                return;
            }
            
            $koment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Krijo njoftimin
            $njoftimQuery = "INSERT INTO njoftimet (id_punonjesi, titulli, permbajtja, id_detyre, lexuar, data_krijimit) 
                            VALUES (:id_punonjesi, :titulli, :permbajtja, :id_detyre, 0, NOW())";
            $njoftimStmt = $this->db->prepare($njoftimQuery);
            
            $titulli = "Koment i ri në detyrë";
            $permbajtja = "{$koment['emri_komentuesit']} ka shtuar një koment të ri në detyrën '{$koment['titulli']}'.";
            
            $njoftimStmt->bindParam(':id_punonjesi', $punonjesiId);
            $njoftimStmt->bindParam(':titulli', $titulli);
            $njoftimStmt->bindParam(':permbajtja', $permbajtja);
            $njoftimStmt->bindParam(':id_detyre', $detyraId);
            
            $njoftimStmt->execute();
        } catch (Exception $ex) {
            $this->logger->logError("Gabim në njoftimin për koment: " . $ex->getMessage(), __FILE__, __LINE__);
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