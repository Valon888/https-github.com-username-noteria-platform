<?php
/**
 * Kontrolluesi për punonjësit
 */
class PunonjesController {
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
     * Merr të gjithë punonjësit
     */
    public function getAll() {
        try {
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            $userOffice = $this->getUserOffice($userId);
            
            // Ndërto query-n bazuar në rolin e përdoruesit
            if ($userRole === 'admin') {
                $query = "SELECT p.id, p.emri, p.mbiemri, p.email, p.roli, p.id_zyra, p.aktiv, 
                             z.emri AS emri_zyres, z.adresa AS adresa_zyres
                      FROM perdoruesit p
                      LEFT JOIN zyrat z ON p.id_zyra = z.id
                      ORDER BY p.id_zyra, p.emri";
                $stmt = $this->db->prepare($query);
            } else {
                // Përdoruesit që nuk janë admin shohin vetëm punonjësit e zyrës së tyre
                $query = "SELECT p.id, p.emri, p.mbiemri, p.email, p.roli, p.id_zyra, p.aktiv, 
                             z.emri AS emri_zyres, z.adresa AS adresa_zyres
                      FROM perdoruesit p
                      LEFT JOIN zyrat z ON p.id_zyra = z.id
                      WHERE p.id_zyra = :officeId
                      ORDER BY p.emri";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':officeId', $userOffice);
            }
            
            $stmt->execute();
            $punonjesit = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->setData([
                'numri_total' => count($punonjesit),
                'punonjesit' => $punonjesit
            ]);
            $this->response->send();
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në listimin e punonjësve: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistemin e punonjësve");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Merr një punonjës sipas ID
     * @param int $id ID e punonjësit
     */
    public function getOne($id) {
        try {
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            $userOffice = $this->getUserOffice($userId);
            
            // Ndërto query-n
            $query = "SELECT p.id, p.emri, p.mbiemri, p.email, p.roli, p.id_zyra, p.aktiv, 
                         p.telefon, p.adresa, p.data_lindjes, p.gjinia,
                         z.emri AS emri_zyres, z.adresa AS adresa_zyres
                  FROM perdoruesit p
                  LEFT JOIN zyrat z ON p.id_zyra = z.id
                  WHERE p.id = :id";
            
            // Nëse nuk është admin, kontrollo edhe zyrën
            if ($userRole !== 'admin') {
                $query .= " AND p.id_zyra = :officeId";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($userRole !== 'admin') {
                $stmt->bindParam(':officeId', $userOffice);
            }
            
            $stmt->execute();
            
            // Kontrollo nëse u gjet punonjësi
            if ($stmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Punonjësi nuk u gjet ose ju nuk keni akses");
                $this->response->send();
                exit;
            }
            
            $punonjesi = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Merr informacion shtesë për punonjësin
            
            // 1. Orari i punës
            $orariQuery = "SELECT id, dita_javes, ora_fillimit, ora_mbarimit 
                          FROM oraret_punonjesve 
                          WHERE id_punonjesi = :id 
                          ORDER BY 
                            CASE dita_javes 
                                WHEN 'E Hënë' THEN 1 
                                WHEN 'E Martë' THEN 2 
                                WHEN 'E Mërkurë' THEN 3 
                                WHEN 'E Enjte' THEN 4 
                                WHEN 'E Premte' THEN 5 
                                WHEN 'E Shtunë' THEN 6 
                                WHEN 'E Diel' THEN 7 
                            END";
            $stmtOrari = $this->db->prepare($orariQuery);
            $stmtOrari->bindParam(':id', $id);
            $stmtOrari->execute();
            $orari = $stmtOrari->fetchAll(PDO::FETCH_ASSOC);
            
            // 2. Detyrat aktive
            $detyratQuery = "SELECT id, titulli, pershkrimi, statusi, prioriteti, afati, data_krijimit 
                            FROM detyrat_punonjesve 
                            WHERE id_punonjesi = :id AND statusi != 'Përfunduar' 
                            ORDER BY prioriteti DESC, afati ASC";
            $stmtDetyrat = $this->db->prepare($detyratQuery);
            $stmtDetyrat->bindParam(':id', $id);
            $stmtDetyrat->execute();
            $detyrat = $stmtDetyrat->fetchAll(PDO::FETCH_ASSOC);
            
            // Shto informacionin shtesë te punonjësi
            $punonjesi['orari'] = $orari;
            $punonjesi['detyrat_aktive'] = $detyrat;
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->setData($punonjesi);
            $this->response->send();
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në marrjen e punonjësit: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistemin e punonjësve");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Shton një punonjës të ri
     * @param array $data Të dhënat e punonjësit
     */
    public function create($data) {
        try {
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            
            // Vetëm admin dhe menaxher mund të shtojnë punonjës
            if ($userRole !== 'admin' && $userRole !== 'menaxher') {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të shtuar punonjës");
                $this->response->send();
                exit;
            }
            
            // Kontrollo të dhënat
            if (!isset($data['emri']) || !isset($data['mbiemri']) || !isset($data['email']) || 
                !isset($data['fjalekalimi']) || !isset($data['roli']) || !isset($data['id_zyra'])) {
                $this->response->setHttpStatusCode(400);
                $this->response->setSuccess(false);
                $this->response->addMessage("Mungojnë të dhënat e kërkuara");
                $this->response->send();
                exit;
            }
            
            // Kontrollo nëse email ekziston
            $checkQuery = "SELECT id FROM perdoruesit WHERE email = :email";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':email', $data['email']);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $this->response->setHttpStatusCode(409);
                $this->response->setSuccess(false);
                $this->response->addMessage("Ekziston një përdorues me këtë email");
                $this->response->send();
                exit;
            }
            
            // Kontrollo nëse përdoruesi ka të drejtë të shtojë punonjës në këtë zyrë
            if ($userRole !== 'admin') {
                $userOffice = $this->getUserOffice($userId);
                if ($data['id_zyra'] != $userOffice) {
                    $this->response->setHttpStatusCode(403);
                    $this->response->setSuccess(false);
                    $this->response->addMessage("Nuk keni të drejta për të shtuar punonjës në këtë zyrë");
                    $this->response->send();
                    exit;
                }
                
                // Menaxheri nuk mund të shtojë admin
                if ($data['roli'] === 'admin') {
                    $this->response->setHttpStatusCode(403);
                    $this->response->setSuccess(false);
                    $this->response->addMessage("Nuk keni të drejta për të shtuar administrator");
                    $this->response->send();
                    exit;
                }
            }
            
            // Përgatit fjalekalimin
            $hashedPassword = password_hash($data['fjalekalimi'], PASSWORD_DEFAULT);
            
            // Ndërto query-n
            $query = "INSERT INTO perdoruesit (emri, mbiemri, email, fjalekalimi, roli, id_zyra, telefon, adresa, data_lindjes, gjinia, aktiv) 
                      VALUES (:emri, :mbiemri, :email, :fjalekalimi, :roli, :id_zyra, :telefon, :adresa, :data_lindjes, :gjinia, :aktiv)";
            $stmt = $this->db->prepare($query);
            
            // Bind parametrat
            $stmt->bindParam(':emri', $data['emri']);
            $stmt->bindParam(':mbiemri', $data['mbiemri']);
            $stmt->bindParam(':email', $data['email']);
            $stmt->bindParam(':fjalekalimi', $hashedPassword);
            $stmt->bindParam(':roli', $data['roli']);
            $stmt->bindParam(':id_zyra', $data['id_zyra']);
            
            // Parametrat opsionale
            $telefon = $data['telefon'] ?? null;
            $adresa = $data['adresa'] ?? null;
            $dataLindjes = $data['data_lindjes'] ?? null;
            $gjinia = $data['gjinia'] ?? null;
            $aktiv = $data['aktiv'] ?? 1;
            
            $stmt->bindParam(':telefon', $telefon);
            $stmt->bindParam(':adresa', $adresa);
            $stmt->bindParam(':data_lindjes', $dataLindjes);
            $stmt->bindParam(':gjinia', $gjinia);
            $stmt->bindParam(':aktiv', $aktiv);
            
            $stmt->execute();
            
            // Merr ID e punonjësit të shtuar
            $newId = $this->db->lastInsertId();
            
            // Nëse ka orare të punës, shto ato
            if (isset($data['oraret']) && is_array($data['oraret'])) {
                $this->shtoOraretPunonjesi($newId, $data['oraret']);
            }
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(201);
            $this->response->setSuccess(true);
            $this->response->addMessage("Punonjësi u shtua me sukses");
            $this->response->setData(['id' => $newId]);
            $this->response->send();
            
            // Regjistro aktivitetin
            $this->logger->logActivity($userId, 'create_employee', 'Shtoi punonjësin ' . $data['emri'] . ' ' . $data['mbiemri']);
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në krijimin e punonjësit: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistemin e punonjësve");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Përditëson një punonjës
     * @param int $id ID e punonjësit
     * @param array $data Të dhënat për përditësim
     */
    public function update($id, $data) {
        try {
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            $userOffice = $this->getUserOffice($userId);
            
            // Kontrollo nëse punonjësi ekziston dhe nëse përdoruesi ka të drejtë për ta edituar
            $checkQuery = "SELECT id, roli, id_zyra FROM perdoruesit WHERE id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Punonjësi nuk u gjet");
                $this->response->send();
                exit;
            }
            
            $employee = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // Vetëm admin mund të përditësojë admin
            if ($employee['roli'] === 'admin' && $userRole !== 'admin') {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të përditësuar administratorin");
                $this->response->send();
                exit;
            }
            
            // Menaxheri mund të përditësojë vetëm punonjësit e zyrës së tij
            if ($userRole === 'menaxher' && $employee['id_zyra'] != $userOffice) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të përditësuar këtë punonjës");
                $this->response->send();
                exit;
            }
            
            // Ndërto query-n e përditësimit
            $updateFields = [];
            $parameters = [];
            
            // Të dhënat që mund të përditësohen
            $allowedFields = ['emri', 'mbiemri', 'telefon', 'adresa', 'data_lindjes', 'gjinia', 'aktiv'];
            
            // Shto fushat e lejuara që janë dërguar
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = :$field";
                    $parameters[":$field"] = $data[$field];
                }
            }
            
            // Kontrollo dhe shto fusha të veçanta
            if (isset($data['email'])) {
                // Kontrollo nëse email i ri është i ndryshëm
                if ($data['email'] != $employee['email']) {
                    // Kontrollo nëse email ekziston
                    $emailQuery = "SELECT id FROM perdoruesit WHERE email = :email AND id != :id";
                    $emailStmt = $this->db->prepare($emailQuery);
                    $emailStmt->bindParam(':email', $data['email']);
                    $emailStmt->bindParam(':id', $id);
                    $emailStmt->execute();
                    
                    if ($emailStmt->rowCount() > 0) {
                        $this->response->setHttpStatusCode(409);
                        $this->response->setSuccess(false);
                        $this->response->addMessage("Ekziston një përdorues tjetër me këtë email");
                        $this->response->send();
                        exit;
                    }
                    
                    $updateFields[] = "email = :email";
                    $parameters[':email'] = $data['email'];
                }
            }
            
            // Përditëso fjalëkalimin nëse është dhënë
            if (isset($data['fjalekalimi']) && !empty($data['fjalekalimi'])) {
                $hashedPassword = password_hash($data['fjalekalimi'], PASSWORD_DEFAULT);
                $updateFields[] = "fjalekalimi = :fjalekalimi";
                $parameters[':fjalekalimi'] = $hashedPassword;
            }
            
            // Vetëm admin mund të ndryshojë rolin ose zyrën
            if ($userRole === 'admin') {
                if (isset($data['roli'])) {
                    $updateFields[] = "roli = :roli";
                    $parameters[':roli'] = $data['roli'];
                }
                
                if (isset($data['id_zyra'])) {
                    $updateFields[] = "id_zyra = :id_zyra";
                    $parameters[':id_zyra'] = $data['id_zyra'];
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
            
            // Ndërto query-n e përditësimit
            $query = "UPDATE perdoruesit SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $this->db->prepare($query);
            
            // Bind ID
            $stmt->bindParam(':id', $id);
            
            // Bind parametrat e tjerë
            foreach ($parameters as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            
            $stmt->execute();
            
            // Nëse ka orare të punës, përditëso ato
            if (isset($data['oraret']) && is_array($data['oraret'])) {
                // Fshi oraret ekzistuese
                $deleteOraret = "DELETE FROM oraret_punonjesve WHERE id_punonjesi = :id";
                $stmtDelete = $this->db->prepare($deleteOraret);
                $stmtDelete->bindParam(':id', $id);
                $stmtDelete->execute();
                
                // Shto oraret e reja
                $this->shtoOraretPunonjesi($id, $data['oraret']);
            }
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->addMessage("Punonjësi u përditësua me sukses");
            $this->response->send();
            
            // Regjistro aktivitetin
            $this->logger->logActivity($userId, 'update_employee', 'Përditësoi punonjësin me ID ' . $id);
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në përditësimin e punonjësit: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistemin e punonjësve");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Fshin një punonjës
     * @param int $id ID e punonjësit
     */
    public function delete($id) {
        try {
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            $userOffice = $this->getUserOffice($userId);
            
            // Vetëm admin dhe menaxher mund të fshijnë punonjës
            if ($userRole !== 'admin' && $userRole !== 'menaxher') {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të fshirë punonjës");
                $this->response->send();
                exit;
            }
            
            // Kontrollo nëse punonjësi ekziston dhe nëse përdoruesi ka të drejtë për ta fshirë
            $checkQuery = "SELECT id, roli, id_zyra FROM perdoruesit WHERE id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Punonjësi nuk u gjet");
                $this->response->send();
                exit;
            }
            
            $employee = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // Përdoruesi nuk mund të fshijë vetveten
            if ($id == $userId) {
                $this->response->setHttpStatusCode(400);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk mund të fshini veten");
                $this->response->send();
                exit;
            }
            
            // Vetëm admin mund të fshijë admin
            if ($employee['roli'] === 'admin' && $userRole !== 'admin') {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të fshirë administratorin");
                $this->response->send();
                exit;
            }
            
            // Menaxheri mund të fshijë vetëm punonjësit e zyrës së tij
            if ($userRole === 'menaxher' && $employee['id_zyra'] != $userOffice) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të fshirë këtë punonjës");
                $this->response->send();
                exit;
            }
            
            // Ndërto query-n e fshirjes
            $query = "DELETE FROM perdoruesit WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->addMessage("Punonjësi u fshi me sukses");
            $this->response->send();
            
            // Regjistro aktivitetin
            $this->logger->logActivity($userId, 'delete_employee', 'Fshiu punonjësin me ID ' . $id);
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në fshirjen e punonjësit: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistemin e punonjësve");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Merr një burim të lidhur me punonjësin
     * @param int $id ID e punonjësit
     * @param string $subResource Burimi i lidhur
     */
    public function getSubResource($id, $subResource) {
        try {
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            $userOffice = $this->getUserOffice($userId);
            
            // Kontrollo nëse punonjësi ekziston dhe nëse përdoruesi ka të drejtë për ta parë
            $checkQuery = "SELECT id, id_zyra FROM perdoruesit WHERE id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Punonjësi nuk u gjet");
                $this->response->send();
                exit;
            }
            
            $employee = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // Punonjësi mund të shikojë vetëm të dhënat e veta
            // Menaxheri mund të shikojë vetëm të dhënat e punonjësve të zyrës së tij
            if ($id != $userId && $userRole === 'punonjes') {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të parë këto të dhëna");
                $this->response->send();
                exit;
            }
            
            if ($userRole === 'menaxher' && $employee['id_zyra'] != $userOffice) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të parë këto të dhëna");
                $this->response->send();
                exit;
            }
            
            // Trajto burimet e ndryshme
            switch ($subResource) {
                case 'orari':
                    // Merr orarin e punës së punonjësit
                    $query = "SELECT id, dita_javes, ora_fillimit, ora_mbarimit 
                              FROM oraret_punonjesve 
                              WHERE id_punonjesi = :id 
                              ORDER BY 
                                CASE dita_javes 
                                    WHEN 'E Hënë' THEN 1 
                                    WHEN 'E Martë' THEN 2 
                                    WHEN 'E Mërkurë' THEN 3 
                                    WHEN 'E Enjte' THEN 4 
                                    WHEN 'E Premte' THEN 5 
                                    WHEN 'E Shtunë' THEN 6 
                                    WHEN 'E Diel' THEN 7 
                                END";
                    $stmt = $this->db->prepare($query);
                    $stmt->bindParam(':id', $id);
                    $stmt->execute();
                    $orari = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Dërgo përgjigjen
                    $this->response->setHttpStatusCode(200);
                    $this->response->setSuccess(true);
                    $this->response->setData($orari);
                    break;
                    
                case 'detyrat':
                    // Merr detyrat e punonjësit
                    // Parametri status për filtrim opsional
                    $status = isset($_GET['status']) ? $_GET['status'] : null;
                    
                    $query = "SELECT id, titulli, pershkrimi, statusi, prioriteti, afati, data_krijimit, data_perditesimit 
                              FROM detyrat_punonjesve 
                              WHERE id_punonjesi = :id";
                    
                    if ($status) {
                        $query .= " AND statusi = :status";
                    }
                    
                    $query .= " ORDER BY prioriteti DESC, afati ASC";
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->bindParam(':id', $id);
                    
                    if ($status) {
                        $stmt->bindParam(':status', $status);
                    }
                    
                    $stmt->execute();
                    $detyrat = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Dërgo përgjigjen
                    $this->response->setHttpStatusCode(200);
                    $this->response->setSuccess(true);
                    $this->response->setData($detyrat);
                    break;
                    
                case 'hyrje-dalje':
                    // Merr regjistrimet e hyrje-daljeve
                    // Filtrim sipas datës
                    $dataFillimi = isset($_GET['data_fillimi']) ? $_GET['data_fillimi'] : date('Y-m-d');
                    $dataMbarimi = isset($_GET['data_mbarimi']) ? $_GET['data_mbarimi'] : date('Y-m-d');
                    
                    $query = "SELECT id, data, ora_hyrjes, ora_daljes, koha_totale, komente 
                              FROM hyrje_daljet 
                              WHERE id_punonjesi = :id 
                                AND data BETWEEN :dataFillimi AND :dataMbarimi 
                              ORDER BY data DESC, ora_hyrjes DESC";
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->bindParam(':id', $id);
                    $stmt->bindParam(':dataFillimi', $dataFillimi);
                    $stmt->bindParam(':dataMbarimi', $dataMbarimi);
                    $stmt->execute();
                    $hyrjeDaljet = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Dërgo përgjigjen
                    $this->response->setHttpStatusCode(200);
                    $this->response->setSuccess(true);
                    $this->response->setData([
                        'data_fillimi' => $dataFillimi,
                        'data_mbarimi' => $dataMbarimi,
                        'hyrje_daljet' => $hyrjeDaljet
                    ]);
                    break;
                    
                case 'lejet':
                    // Merr lejet e punonjësit
                    // Filtrim sipas statusit
                    $statusi = isset($_GET['statusi']) ? $_GET['statusi'] : null;
                    
                    $query = "SELECT id, lloji, arsyeja, data_fillimi, data_mbarimi, dite_totale, statusi, 
                                data_kerkeses, data_pergjigjes, aprovuar_nga, komente 
                              FROM lejet 
                              WHERE id_punonjesi = :id";
                    
                    if ($statusi) {
                        $query .= " AND statusi = :statusi";
                    }
                    
                    $query .= " ORDER BY data_kerkeses DESC";
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->bindParam(':id', $id);
                    
                    if ($statusi) {
                        $stmt->bindParam(':statusi', $statusi);
                    }
                    
                    $stmt->execute();
                    $lejet = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Dërgo përgjigjen
                    $this->response->setHttpStatusCode(200);
                    $this->response->setSuccess(true);
                    $this->response->setData($lejet);
                    break;
                    
                case 'njoftimet':
                    // Merr njoftimet për punonjësin
                    // Filtrim sipas leximit
                    $lexuar = isset($_GET['lexuar']) ? (bool)$_GET['lexuar'] : null;
                    
                    $query = "SELECT id, titulli, permbajtja, lexuar, data_krijimit 
                              FROM njoftimet 
                              WHERE id_punonjesi = :id";
                    
                    if ($lexuar !== null) {
                        $lexuar = $lexuar ? 1 : 0;
                        $query .= " AND lexuar = :lexuar";
                    }
                    
                    $query .= " ORDER BY data_krijimit DESC";
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->bindParam(':id', $id);
                    
                    if ($lexuar !== null) {
                        $stmt->bindParam(':lexuar', $lexuar);
                    }
                    
                    $stmt->execute();
                    $njoftimet = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Dërgo përgjigjen
                    $this->response->setHttpStatusCode(200);
                    $this->response->setSuccess(true);
                    $this->response->setData($njoftimet);
                    break;
                    
                case 'raporti':
                    // Gjenero raport për punonjësin
                    // Parametrat për filtrim
                    $muaji = isset($_GET['muaji']) ? (int)$_GET['muaji'] : (int)date('m');
                    $viti = isset($_GET['viti']) ? (int)$_GET['viti'] : (int)date('Y');
                    
                    // Muaji i formatuar për filter
                    $dataFillimi = sprintf("%04d-%02d-01", $viti, $muaji);
                    $dataMbarimi = date('Y-m-t', strtotime($dataFillimi));
                    
                    // 1. Merr informacionin bazë të punonjësit
                    $punonjesQuery = "SELECT p.emri, p.mbiemri, p.email, p.roli, z.emri AS emri_zyres 
                                     FROM perdoruesit p
                                     LEFT JOIN zyrat z ON p.id_zyra = z.id
                                     WHERE p.id = :id";
                    $punonjesStmt = $this->db->prepare($punonjesQuery);
                    $punonjesStmt->bindParam(':id', $id);
                    $punonjesStmt->execute();
                    $punonjesi = $punonjesStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // 2. Merr orarin standard të punës
                    $orariQuery = "SELECT dita_javes, ora_fillimit, ora_mbarimit 
                                  FROM oraret_punonjesve 
                                  WHERE id_punonjesi = :id";
                    $orariStmt = $this->db->prepare($orariQuery);
                    $orariStmt->bindParam(':id', $id);
                    $orariStmt->execute();
                    $orari = $orariStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // 3. Merr hyrje-daljet e muajit
                    $hyrjeDaljeQuery = "SELECT data, ora_hyrjes, ora_daljes, koha_totale 
                                       FROM hyrje_daljet 
                                       WHERE id_punonjesi = :id 
                                         AND data BETWEEN :dataFillimi AND :dataMbarimi 
                                       ORDER BY data ASC";
                    $hyrjeDaljeStmt = $this->db->prepare($hyrjeDaljeQuery);
                    $hyrjeDaljeStmt->bindParam(':id', $id);
                    $hyrjeDaljeStmt->bindParam(':dataFillimi', $dataFillimi);
                    $hyrjeDaljeStmt->bindParam(':dataMbarimi', $dataMbarimi);
                    $hyrjeDaljeStmt->execute();
                    $hyrjeDaljet = $hyrjeDaljeStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // 4. Merr lejet e muajit
                    $lejeQuery = "SELECT lloji, data_fillimi, data_mbarimi, dite_totale, statusi
                                 FROM lejet 
                                 WHERE id_punonjesi = :id 
                                   AND ((data_fillimi BETWEEN :dataFillimi AND :dataMbarimi) 
                                     OR (data_mbarimi BETWEEN :dataFillimi AND :dataMbarimi)
                                     OR (data_fillimi <= :dataFillimi AND data_mbarimi >= :dataMbarimi))
                                   AND statusi = 'Aprovuar'";
                    $lejeStmt = $this->db->prepare($lejeQuery);
                    $lejeStmt->bindParam(':id', $id);
                    $lejeStmt->bindParam(':dataFillimi', $dataFillimi);
                    $lejeStmt->bindParam(':dataMbarimi', $dataMbarimi);
                    $lejeStmt->execute();
                    $lejet = $lejeStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // 5. Merr detyrat e përfunduara të muajit
                    $detyraQuery = "SELECT titulli, statusi, prioriteti, data_perditesimit
                                   FROM detyrat_punonjesve 
                                   WHERE id_punonjesi = :id 
                                     AND statusi = 'Përfunduar'
                                     AND data_perditesimit BETWEEN :dataFillimi AND :dataMbarimi";
                    $detyraStmt = $this->db->prepare($detyraQuery);
                    $detyraStmt->bindParam(':id', $id);
                    $detyraStmt->bindParam(':dataFillimi', $dataFillimi);
                    $detyraStmt->bindParam(':dataMbarimi', $dataMbarimi);
                    $detyraStmt->execute();
                    $detyrat = $detyraStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Llogarit statistikat
                    $ditePune = 0;
                    $diteMungese = 0;
                    $oreTotale = 0;
                    $vonesat = 0;
                    $daljeHershme = 0;
                    
                    // Përgatit raportin
                    $raport = [
                        'punonjesi' => $punonjesi,
                        'periudha' => [
                            'muaji' => $muaji,
                            'viti' => $viti,
                            'data_fillimi' => $dataFillimi,
                            'data_mbarimi' => $dataMbarimi
                        ],
                        'orari_standard' => $orari,
                        'prezenca' => [
                            'dite_pune' => $ditePune,
                            'dite_mungese' => $diteMungese,
                            'ore_totale' => $oreTotale,
                            'vonesa' => $vonesat,
                            'dalje_hershme' => $daljeHershme
                        ],
                        'hyrje_daljet' => $hyrjeDaljet,
                        'lejet' => $lejet,
                        'detyrat_perfunduara' => $detyrat
                    ];
                    
                    // Dërgo përgjigjen
                    $this->response->setHttpStatusCode(200);
                    $this->response->setSuccess(true);
                    $this->response->setData($raport);
                    break;
                    
                default:
                    $this->response->setHttpStatusCode(404);
                    $this->response->setSuccess(false);
                    $this->response->addMessage("Burimi i kërkuar nuk ekziston");
                    break;
            }
            
            $this->response->send();
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në marrjen e burimeve të punonjësit: " . $ex->getMessage(), __FILE__, __LINE__);
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
    
    /**
     * Shton oraret e punës për një punonjës
     * @param int $punonjesiId ID e punonjësit
     * @param array $oraret Oraret për t'u shtuar
     */
    private function shtoOraretPunonjesi($punonjesiId, $oraret) {
        foreach ($oraret as $orar) {
            if (isset($orar['dita_javes']) && isset($orar['ora_fillimit']) && isset($orar['ora_mbarimit'])) {
                $query = "INSERT INTO oraret_punonjesve (id_punonjesi, dita_javes, ora_fillimit, ora_mbarimit) 
                          VALUES (:id_punonjesi, :dita_javes, :ora_fillimit, :ora_mbarimit)";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':id_punonjesi', $punonjesiId);
                $stmt->bindParam(':dita_javes', $orar['dita_javes']);
                $stmt->bindParam(':ora_fillimit', $orar['ora_fillimit']);
                $stmt->bindParam(':ora_mbarimit', $orar['ora_mbarimit']);
                $stmt->execute();
            }
        }
    }
}