<?php
/**
 * Kontrolleri për menaxhimin e orareve të punonjësve
 */
class OrarController {
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
     * Merr orarin për të gjithë punonjësit 
     * ose filtron sipas parametrave të kërkesës
     */
    public function getAll() {
        try {
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            $userOffice = $this->getUserOffice($userId);
            
            // Filtrimet
            $java = isset($_GET['java']) ? $_GET['java'] : $this->getJavaAktuale();
            $idZyra = isset($_GET['id_zyra']) ? (int)$_GET['id_zyra'] : null;
            $idPunonjesi = isset($_GET['id_punonjesi']) ? (int)$_GET['id_punonjesi'] : null;
            
            // Vendos data_fillimit dhe data_mbarimit bazuar në javën
            list($dataFillimit, $dataMbarimit) = $this->getDitetEJaves($java);
            
            // Ndërto query-n bazuar në rolin e përdoruesit
            if ($userRole === 'admin') {
                $query = "SELECT o.id, o.id_punonjesi, o.dita, o.ora_fillimit, o.ora_mbarimit, 
                             o.pershkrimi, o.data_krijimit, o.data_perditesimit,
                             p.emri, p.mbiemri, z.emri AS emri_zyres
                          FROM oraret o
                          JOIN perdoruesit p ON o.id_punonjesi = p.id
                          JOIN zyrat z ON p.id_zyra = z.id
                          WHERE o.dita BETWEEN :data_fillimit AND :data_mbarimit";
                
                // Filtro sipas zyrës nëse është dhënë
                if ($idZyra) {
                    $query .= " AND p.id_zyra = :id_zyra";
                }
                
                // Filtro sipas punonjësit nëse është dhënë
                if ($idPunonjesi) {
                    $query .= " AND o.id_punonjesi = :id_punonjesi";
                }
                
                $query .= " ORDER BY o.dita ASC, o.ora_fillimit ASC";
                
            } elseif ($userRole === 'menaxher') {
                // Menaxheri shikon vetëm oraret e punonjësve të zyrës së tij
                $query = "SELECT o.id, o.id_punonjesi, o.dita, o.ora_fillimit, o.ora_mbarimit, 
                             o.pershkrimi, o.data_krijimit, o.data_perditesimit,
                             p.emri, p.mbiemri
                          FROM oraret o
                          JOIN perdoruesit p ON o.id_punonjesi = p.id
                          WHERE p.id_zyra = :id_zyra AND o.dita BETWEEN :data_fillimit AND :data_mbarimit";
                
                // Filtro sipas punonjësit nëse është dhënë
                if ($idPunonjesi) {
                    $query .= " AND o.id_punonjesi = :id_punonjesi";
                }
                
                $query .= " ORDER BY o.dita ASC, o.ora_fillimit ASC";
                
            } else {
                // Punonjësi i thjeshtë shikon vetëm orarin e tij
                $query = "SELECT o.id, o.dita, o.ora_fillimit, o.ora_mbarimit, 
                             o.pershkrimi, o.data_krijimit, o.data_perditesimit
                          FROM oraret o
                          WHERE o.id_punonjesi = :id_punonjesi AND o.dita BETWEEN :data_fillimit AND :data_mbarimit
                          ORDER BY o.dita ASC, o.ora_fillimit ASC";
                
                $idPunonjesi = $userId; // Punonjësi shikon vetëm orarin e tij
            }
            
            $stmt = $this->db->prepare($query);
            
            // Bind parametrat e përbashkët
            $stmt->bindParam(':data_fillimit', $dataFillimit);
            $stmt->bindParam(':data_mbarimit', $dataMbarimit);
            
            // Bind parametrat e tjerë sipas rolit
            if ($userRole === 'admin') {
                if ($idZyra) {
                    $stmt->bindParam(':id_zyra', $idZyra);
                }
                
                if ($idPunonjesi) {
                    $stmt->bindParam(':id_punonjesi', $idPunonjesi);
                }
            } elseif ($userRole === 'menaxher') {
                $stmt->bindParam(':id_zyra', $userOffice);
                
                if ($idPunonjesi) {
                    $stmt->bindParam(':id_punonjesi', $idPunonjesi);
                }
            } else {
                $stmt->bindParam(':id_punonjesi', $idPunonjesi);
            }
            
            $stmt->execute();
            $oraret = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Grupo oraret sipas punonjësit dhe ditës
            $oraretGrupuar = [];
            
            foreach ($oraret as $orar) {
                $idPunonjesi = $orar['id_punonjesi'] ?? $userId;
                $dita = $orar['dita'];
                
                if (!isset($oraretGrupuar[$idPunonjesi])) {
                    // Shto informacionin e punonjësit nëse ekziston
                    if (isset($orar['emri']) && isset($orar['mbiemri'])) {
                        $oraretGrupuar[$idPunonjesi] = [
                            'id_punonjesi' => $idPunonjesi,
                            'emri' => $orar['emri'],
                            'mbiemri' => $orar['mbiemri'],
                            'zyra' => $orar['emri_zyres'] ?? null,
                            'oraret' => []
                        ];
                    } else {
                        $oraretGrupuar[$idPunonjesi] = [
                            'id_punonjesi' => $idPunonjesi,
                            'oraret' => []
                        ];
                    }
                }
                
                // Shto orarin në listën e orareve të punonjësit
                $oraretGrupuar[$idPunonjesi]['oraret'][] = [
                    'id' => $orar['id'],
                    'dita' => $dita,
                    'ora_fillimit' => $orar['ora_fillimit'],
                    'ora_mbarimit' => $orar['ora_mbarimit'],
                    'pershkrimi' => $orar['pershkrimi']
                ];
            }
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->setData([
                'java' => $java,
                'data_fillimit' => $dataFillimit,
                'data_mbarimit' => $dataMbarimit,
                'punonjesit' => array_values($oraretGrupuar)
            ]);
            $this->response->send();
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në listimin e orareve: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Merr orarin për një punonjës të caktuar
     * @param int $id ID e punonjësit
     */
    public function getOne($id) {
        try {
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            $userOffice = $this->getUserOffice($userId);
            
            // Nëse nuk është admin dhe po kërkon orarin e një punonjësi tjetër
            if ($userRole === 'punonjes' && $id != $userId) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të parë orarin e këtij punonjësi");
                $this->response->send();
                exit;
            }
            
            // Nëse është menaxher, kontrollo nëse punonjësi është në zyrën e tij
            if ($userRole === 'menaxher' && $id != $userId) {
                $punonjesOffice = $this->getUserOffice($id);
                if ($punonjesOffice != $userOffice) {
                    $this->response->setHttpStatusCode(403);
                    $this->response->setSuccess(false);
                    $this->response->addMessage("Nuk keni të drejta për të parë orarin e këtij punonjësi");
                    $this->response->send();
                    exit;
                }
            }
            
            // Filtrimet
            $java = isset($_GET['java']) ? $_GET['java'] : $this->getJavaAktuale();
            
            // Vendos data_fillimit dhe data_mbarimit bazuar në javën
            list($dataFillimit, $dataMbarimit) = $this->getDitetEJaves($java);
            
            // Merr informacionin e punonjësit
            $punonjesQuery = "SELECT p.id, p.emri, p.mbiemri, p.email, p.telefoni, p.id_zyra, 
                               z.emri AS emri_zyres
                             FROM perdoruesit p
                             JOIN zyrat z ON p.id_zyra = z.id
                             WHERE p.id = :id";
            $punonjesStmt = $this->db->prepare($punonjesQuery);
            $punonjesStmt->bindParam(':id', $id);
            $punonjesStmt->execute();
            
            // Kontrollo nëse u gjet punonjësi
            if ($punonjesStmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Punonjësi nuk u gjet");
                $this->response->send();
                exit;
            }
            
            $punonjesi = $punonjesStmt->fetch(PDO::FETCH_ASSOC);
            
            // Merr oraret e punonjësit për javën e përzgjedhur
            $orarQuery = "SELECT id, dita, ora_fillimit, ora_mbarimit, pershkrimi, 
                            data_krijimit, data_perditesimit
                          FROM oraret
                          WHERE id_punonjesi = :id_punonjesi AND dita BETWEEN :data_fillimit AND :data_mbarimit
                          ORDER BY dita ASC, ora_fillimit ASC";
            $orarStmt = $this->db->prepare($orarQuery);
            $orarStmt->bindParam(':id_punonjesi', $id);
            $orarStmt->bindParam(':data_fillimit', $dataFillimit);
            $orarStmt->bindParam(':data_mbarimit', $dataMbarimit);
            $orarStmt->execute();
            
            $oraret = $orarStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Organizo oraret sipas ditës
            $oraretSipasDiteve = [];
            
            foreach ($oraret as $orar) {
                $dita = $orar['dita'];
                if (!isset($oraretSipasDiteve[$dita])) {
                    $oraretSipasDiteve[$dita] = [];
                }
                
                $oraretSipasDiteve[$dita][] = [
                    'id' => $orar['id'],
                    'ora_fillimit' => $orar['ora_fillimit'],
                    'ora_mbarimit' => $orar['ora_mbarimit'],
                    'pershkrimi' => $orar['pershkrimi']
                ];
            }
            
            // Përgadisim përgjigjen
            $rezultati = [
                'punonjesi' => [
                    'id' => $punonjesi['id'],
                    'emri' => $punonjesi['emri'],
                    'mbiemri' => $punonjesi['mbiemri'],
                    'email' => $punonjesi['email'],
                    'telefoni' => $punonjesi['telefoni'],
                    'zyra' => [
                        'id' => $punonjesi['id_zyra'],
                        'emri' => $punonjesi['emri_zyres']
                    ]
                ],
                'java' => $java,
                'data_fillimit' => $dataFillimit,
                'data_mbarimit' => $dataMbarimit,
                'oraret' => $oraretSipasDiteve
            ];
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->setData($rezultati);
            $this->response->send();
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në marrjen e orarit: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Krijon një orar të ri
     * @param array $data Të dhënat e orarit
     */
    public function create($data) {
        try {
            // Kontrollo të dhënat
            if (!isset($data['id_punonjesi']) || !isset($data['dita']) || 
                !isset($data['ora_fillimit']) || !isset($data['ora_mbarimit'])) {
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
            
            // Punonjësi i thjeshtë nuk mund të shtojë orare
            if ($userRole === 'punonjes') {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të shtuar orar");
                $this->response->send();
                exit;
            }
            
            // Nëse është menaxher, mund të shtojë orare vetëm për punonjësit e zyrës së tij
            if ($userRole === 'menaxher') {
                $punonjesOffice = $this->getUserOffice($data['id_punonjesi']);
                if ($punonjesOffice != $userOffice) {
                    $this->response->setHttpStatusCode(403);
                    $this->response->setSuccess(false);
                    $this->response->addMessage("Nuk keni të drejta për të shtuar orar për këtë punonjës");
                    $this->response->send();
                    exit;
                }
            }
            
            // Kontrollo nëse ka mbivendosje të orarit
            $kontrolloMbivendosjenQuery = "SELECT id FROM oraret 
                                        WHERE id_punonjesi = :id_punonjesi 
                                        AND dita = :dita 
                                        AND ((ora_fillimit <= :ora_fillimit AND ora_mbarimit > :ora_fillimit) 
                                            OR (ora_fillimit < :ora_mbarimit AND ora_mbarimit >= :ora_mbarimit)
                                            OR (ora_fillimit >= :ora_fillimit AND ora_mbarimit <= :ora_mbarimit))";
            $kontrolloStmt = $this->db->prepare($kontrolloMbivendosjenQuery);
            $kontrolloStmt->bindParam(':id_punonjesi', $data['id_punonjesi']);
            $kontrolloStmt->bindParam(':dita', $data['dita']);
            $kontrolloStmt->bindParam(':ora_fillimit', $data['ora_fillimit']);
            $kontrolloStmt->bindParam(':ora_mbarimit', $data['ora_mbarimit']);
            $kontrolloStmt->execute();
            
            if ($kontrolloStmt->rowCount() > 0) {
                $this->response->setHttpStatusCode(409);
                $this->response->setSuccess(false);
                $this->response->addMessage("Ka mbivendosje me një orar ekzistues për këtë punonjës dhe ditë");
                $this->response->send();
                exit;
            }
            
            // Ndërto query-n për shtimin e orarit
            $query = "INSERT INTO oraret (id_punonjesi, dita, ora_fillimit, ora_mbarimit, pershkrimi) 
                      VALUES (:id_punonjesi, :dita, :ora_fillimit, :ora_mbarimit, :pershkrimi)";
            $stmt = $this->db->prepare($query);
            
            // Bind parametrat
            $stmt->bindParam(':id_punonjesi', $data['id_punonjesi']);
            $stmt->bindParam(':dita', $data['dita']);
            $stmt->bindParam(':ora_fillimit', $data['ora_fillimit']);
            $stmt->bindParam(':ora_mbarimit', $data['ora_mbarimit']);
            
            // Parametri opsional
            $pershkrimi = $data['pershkrimi'] ?? null;
            $stmt->bindParam(':pershkrimi', $pershkrimi);
            
            $stmt->execute();
            
            // Merr ID e orarit të shtuar
            $newId = $this->db->lastInsertId();
            
            // Njofto punonjësin për orarin e ri
            $this->njoftoPunonjesinPerOrarTeRi($data['id_punonjesi'], $data['dita']);
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(201);
            $this->response->setSuccess(true);
            $this->response->addMessage("Orari u shtua me sukses");
            $this->response->setData(['id' => $newId]);
            $this->response->send();
            
            // Regjistro aktivitetin
            $this->logger->logActivity(
                $userId, 
                'create_schedule', 
                "Krijoi një orar të ri për punonjësin me ID {$data['id_punonjesi']} për datën {$data['dita']}"
            );
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në krijimin e orarit: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Përditëson një orar
     * @param int $id ID e orarit
     * @param array $data Të dhënat për përditësim
     */
    public function update($id, $data) {
        try {
            // Kontrollo nëse orari ekziston dhe merr informacionin për të
            $checkQuery = "SELECT o.id, o.id_punonjesi, p.id_zyra 
                          FROM oraret o
                          JOIN perdoruesit p ON o.id_punonjesi = p.id
                          WHERE o.id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Orari nuk u gjet");
                $this->response->send();
                exit;
            }
            
            $orar = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            $userOffice = $this->getUserOffice($userId);
            
            // Punonjësi i thjeshtë nuk mund të përditësojë orare
            if ($userRole === 'punonjes') {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të përditësuar orar");
                $this->response->send();
                exit;
            }
            
            // Nëse është menaxher, mund të përditësojë orare vetëm për punonjësit e zyrës së tij
            if ($userRole === 'menaxher' && $orar['id_zyra'] != $userOffice) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të përditësuar orarin e këtij punonjësi");
                $this->response->send();
                exit;
            }
            
            // Ndërto query-n e përditësimit
            $updateFields = [];
            $parameters = [];
            
            // Të dhënat që mund të përditësohen
            $allowedFields = ['dita', 'ora_fillimit', 'ora_mbarimit', 'pershkrimi'];
            
            // Shto fushat e lejuara që janë dërguar
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = :$field";
                    $parameters[":$field"] = $data[$field];
                }
            }
            
            // Nëse do të ndryshojë dita ose oraret, kontrollo për mbivendosje
            if (isset($data['dita']) || isset($data['ora_fillimit']) || isset($data['ora_mbarimit'])) {
                // Merr vlerat aktuale për fushat që nuk do të përditësohen
                $orarAktualQuery = "SELECT dita, ora_fillimit, ora_mbarimit FROM oraret WHERE id = :id";
                $orarAktualStmt = $this->db->prepare($orarAktualQuery);
                $orarAktualStmt->bindParam(':id', $id);
                $orarAktualStmt->execute();
                $orarAktual = $orarAktualStmt->fetch(PDO::FETCH_ASSOC);
                
                // Vendos vlerat e orarit që do të kontrollohet
                $ditaKontroll = $data['dita'] ?? $orarAktual['dita'];
                $oraFillimitKontroll = $data['ora_fillimit'] ?? $orarAktual['ora_fillimit'];
                $oraMbarimitKontroll = $data['ora_mbarimit'] ?? $orarAktual['ora_mbarimit'];
                
                // Kontrollo nëse ka mbivendosje
                $kontrolloMbivendosjenQuery = "SELECT id FROM oraret 
                                            WHERE id_punonjesi = :id_punonjesi 
                                            AND dita = :dita 
                                            AND id != :id_orar
                                            AND ((ora_fillimit <= :ora_fillimit AND ora_mbarimit > :ora_fillimit) 
                                                OR (ora_fillimit < :ora_mbarimit AND ora_mbarimit >= :ora_mbarimit)
                                                OR (ora_fillimit >= :ora_fillimit AND ora_mbarimit <= :ora_mbarimit))";
                $kontrolloStmt = $this->db->prepare($kontrolloMbivendosjenQuery);
                $kontrolloStmt->bindParam(':id_punonjesi', $orar['id_punonjesi']);
                $kontrolloStmt->bindParam(':dita', $ditaKontroll);
                $kontrolloStmt->bindParam(':id_orar', $id);
                $kontrolloStmt->bindParam(':ora_fillimit', $oraFillimitKontroll);
                $kontrolloStmt->bindParam(':ora_mbarimit', $oraMbarimitKontroll);
                $kontrolloStmt->execute();
                
                if ($kontrolloStmt->rowCount() > 0) {
                    $this->response->setHttpStatusCode(409);
                    $this->response->setSuccess(false);
                    $this->response->addMessage("Ka mbivendosje me një orar ekzistues për këtë punonjës dhe ditë");
                    $this->response->send();
                    exit;
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
            $query = "UPDATE oraret SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $this->db->prepare($query);
            
            // Bind ID
            $stmt->bindParam(':id', $id);
            
            // Bind parametrat e tjerë
            foreach ($parameters as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            
            $stmt->execute();
            
            // Njofto punonjësin për ndryshimin e orarit
            if (!empty($updateFields)) {
                $this->njoftoPunonjesinPerNdryshimOrari($orar['id_punonjesi'], $data['dita'] ?? null);
            }
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->addMessage("Orari u përditësua me sukses");
            $this->response->send();
            
            // Regjistro aktivitetin
            $this->logger->logActivity(
                $userId, 
                'update_schedule', 
                "Përditësoi orarin me ID " . $id
            );
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në përditësimin e orarit: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Fshin një orar
     * @param int $id ID e orarit
     */
    public function delete($id) {
        try {
            // Kontrollo nëse orari ekziston dhe merr informacionin për të
            $checkQuery = "SELECT o.id, o.id_punonjesi, o.dita, p.id_zyra 
                          FROM oraret o
                          JOIN perdoruesit p ON o.id_punonjesi = p.id
                          WHERE o.id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Orari nuk u gjet");
                $this->response->send();
                exit;
            }
            
            $orar = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            $userOffice = $this->getUserOffice($userId);
            
            // Punonjësi i thjeshtë nuk mund të fshijë orare
            if ($userRole === 'punonjes') {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të fshirë orar");
                $this->response->send();
                exit;
            }
            
            // Nëse është menaxher, mund të fshijë orare vetëm për punonjësit e zyrës së tij
            if ($userRole === 'menaxher' && $orar['id_zyra'] != $userOffice) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të fshirë orarin e këtij punonjësi");
                $this->response->send();
                exit;
            }
            
            // Ndërto query-n për fshirjen e orarit
            $query = "DELETE FROM oraret WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Njofto punonjësin për anulimin e orarit
            $this->njoftoPunonjesinPerAnulimOrari($orar['id_punonjesi'], $orar['dita']);
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->addMessage("Orari u fshi me sukses");
            $this->response->send();
            
            // Regjistro aktivitetin
            $this->logger->logActivity(
                $userId, 
                'delete_schedule', 
                "Fshiu orarin me ID " . $id
            );
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në fshirjen e orarit: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Merr numrin e javës aktuale
     * @return string Numri i javës në formatin 'YYYY-WW'
     */
    private function getJavaAktuale() {
        return date('o-W'); // Format: YYYY-WW (p.sh. 2023-32)
    }
    
    /**
     * Merr datat e fillimit dhe mbarimit për një javë të caktuar
     * @param string $java Java në formatin 'YYYY-WW'
     * @return array [$dataFillimit, $dataMbarimit]
     */
    private function getDitetEJaves($java) {
        list($viti, $nrJave) = explode('-', $java);
        
        // Përcakto ditën e parë të javës (e hënë)
        $dataFillimit = new DateTime();
        $dataFillimit->setISODate($viti, $nrJave, 1); // 1 = e hënë
        
        // Përcakto ditën e fundit të javës (e dielë)
        $dataMbarimit = clone $dataFillimit;
        $dataMbarimit->add(new DateInterval('P6D')); // Shto 6 ditë
        
        return [
            $dataFillimit->format('Y-m-d'),
            $dataMbarimit->format('Y-m-d')
        ];
    }
    
    /**
     * Njofton punonjësin për një orar të ri
     * @param int $punonjesiId ID e punonjësit
     * @param string $data Data e orarit
     */
    private function njoftoPunonjesinPerOrarTeRi($punonjesiId, $data) {
        try {
            // Merr informacionin për përdoruesin që po bën veprimin
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $query = "SELECT CONCAT(emri, ' ', mbiemri) AS emri_plote, roli
                     FROM perdoruesit 
                     WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $userId);
            $stmt->execute();
            
            $perdoruesi = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Krijo njoftimin
            $titulli = "Orar i ri i punës";
            $permbajtja = "Ju është shtuar një orar i ri pune për datën " . $data . 
                        " nga " . $perdoruesi['emri_plote'] . " (" . $perdoruesi['roli'] . ").";
            
            $njoftimQuery = "INSERT INTO njoftimet (id_punonjesi, titulli, permbajtja, lexuar, data_krijimit) 
                            VALUES (:id_punonjesi, :titulli, :permbajtja, 0, NOW())";
            $njoftimStmt = $this->db->prepare($njoftimQuery);
            
            $njoftimStmt->bindParam(':id_punonjesi', $punonjesiId);
            $njoftimStmt->bindParam(':titulli', $titulli);
            $njoftimStmt->bindParam(':permbajtja', $permbajtja);
            
            $njoftimStmt->execute();
            
        } catch (Exception $ex) {
            $this->logger->logError("Gabim në dërgimin e njoftimit për orar të ri: " . $ex->getMessage(), __FILE__, __LINE__);
        }
    }
    
    /**
     * Njofton punonjësin për ndryshimin e një orari
     * @param int $punonjesiId ID e punonjësit
     * @param string $data Data e orarit (opsionale)
     */
    private function njoftoPunonjesinPerNdryshimOrari($punonjesiId, $data = null) {
        try {
            // Merr informacionin për përdoruesin që po bën veprimin
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $query = "SELECT CONCAT(emri, ' ', mbiemri) AS emri_plote, roli
                     FROM perdoruesit 
                     WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $userId);
            $stmt->execute();
            
            $perdoruesi = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Krijo njoftimin
            $titulli = "Ndryshim në orarin e punës";
            
            if ($data) {
                $permbajtja = "Orari juaj i punës për datën " . $data . 
                            " është ndryshuar nga " . $perdoruesi['emri_plote'] . " (" . $perdoruesi['roli'] . ").";
            } else {
                $permbajtja = "Një nga oraret tuaja të punës është ndryshuar nga " . 
                            $perdoruesi['emri_plote'] . " (" . $perdoruesi['roli'] . ").";
            }
            
            $njoftimQuery = "INSERT INTO njoftimet (id_punonjesi, titulli, permbajtja, lexuar, data_krijimit) 
                            VALUES (:id_punonjesi, :titulli, :permbajtja, 0, NOW())";
            $njoftimStmt = $this->db->prepare($njoftimQuery);
            
            $njoftimStmt->bindParam(':id_punonjesi', $punonjesiId);
            $njoftimStmt->bindParam(':titulli', $titulli);
            $njoftimStmt->bindParam(':permbajtja', $permbajtja);
            
            $njoftimStmt->execute();
            
        } catch (Exception $ex) {
            $this->logger->logError("Gabim në dërgimin e njoftimit për ndryshim orari: " . $ex->getMessage(), __FILE__, __LINE__);
        }
    }
    
    /**
     * Njofton punonjësin për anulimin e një orari
     * @param int $punonjesiId ID e punonjësit
     * @param string $data Data e orarit
     */
    private function njoftoPunonjesinPerAnulimOrari($punonjesiId, $data) {
        try {
            // Merr informacionin për përdoruesin që po bën veprimin
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $query = "SELECT CONCAT(emri, ' ', mbiemri) AS emri_plote, roli
                     FROM perdoruesit 
                     WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $userId);
            $stmt->execute();
            
            $perdoruesi = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Krijo njoftimin
            $titulli = "Anulim i orarit të punës";
            $permbajtja = "Orari juaj i punës për datën " . $data . 
                        " është anuluar nga " . $perdoruesi['emri_plote'] . " (" . $perdoruesi['roli'] . ").";
            
            $njoftimQuery = "INSERT INTO njoftimet (id_punonjesi, titulli, permbajtja, lexuar, data_krijimit) 
                            VALUES (:id_punonjesi, :titulli, :permbajtja, 0, NOW())";
            $njoftimStmt = $this->db->prepare($njoftimQuery);
            
            $njoftimStmt->bindParam(':id_punonjesi', $punonjesiId);
            $njoftimStmt->bindParam(':titulli', $titulli);
            $njoftimStmt->bindParam(':permbajtja', $permbajtja);
            
            $njoftimStmt->execute();
            
        } catch (Exception $ex) {
            $this->logger->logError("Gabim në dërgimin e njoftimit për anulim orari: " . $ex->getMessage(), __FILE__, __LINE__);
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