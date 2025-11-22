<?php
/**
 * Kontrolluesi për hyrje-daljet e punonjësve
 */
class HyrjeDaljeController {
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
     * Merr të gjitha hyrje-daljet
     */
    public function getAll() {
        try {
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            $userOffice = $this->getUserOffice($userId);
            
            // Parametrat e filtrimit
            $dataFillimi = isset($_GET['data_fillimi']) ? $_GET['data_fillimi'] : date('Y-m-d');
            $dataMbarimi = isset($_GET['data_mbarimi']) ? $_GET['data_mbarimi'] : date('Y-m-d');
            $idZyra = isset($_GET['id_zyra']) ? (int)$_GET['id_zyra'] : null;
            $idPunonjesi = isset($_GET['id_punonjesi']) ? (int)$_GET['id_punonjesi'] : null;
            
            // Ndërto query-n bazuar në rolin e përdoruesit
            if ($userRole === 'admin') {
                $query = "SELECT h.id, h.id_punonjesi, h.data, h.ora_hyrjes, h.ora_daljes, h.koha_totale, h.komente, 
                             p.emri, p.mbiemri, p.email, z.emri AS emri_zyres
                      FROM hyrje_daljet h
                      JOIN perdoruesit p ON h.id_punonjesi = p.id
                      JOIN zyrat z ON p.id_zyra = z.id
                      WHERE h.data BETWEEN :dataFillimi AND :dataMbarimi";
                
                // Filtrim sipas zyrës nëse është specifikuar
                if ($idZyra) {
                    $query .= " AND p.id_zyra = :idZyra";
                }
                
                // Filtrim sipas punonjësit nëse është specifikuar
                if ($idPunonjesi) {
                    $query .= " AND h.id_punonjesi = :idPunonjesi";
                }
                
                $query .= " ORDER BY h.data DESC, h.ora_hyrjes DESC";
                
            } elseif ($userRole === 'menaxher') {
                // Menaxheri shikon vetëm punonjësit e zyrës së tij
                $query = "SELECT h.id, h.id_punonjesi, h.data, h.ora_hyrjes, h.ora_daljes, h.koha_totale, h.komente, 
                             p.emri, p.mbiemri, p.email
                      FROM hyrje_daljet h
                      JOIN perdoruesit p ON h.id_punonjesi = p.id
                      WHERE p.id_zyra = :idZyra
                        AND h.data BETWEEN :dataFillimi AND :dataMbarimi";
                
                // Filtrim sipas punonjësit nëse është specifikuar
                if ($idPunonjesi) {
                    $query .= " AND h.id_punonjesi = :idPunonjesi";
                }
                
                $query .= " ORDER BY h.data DESC, h.ora_hyrjes DESC";
                
            } else {
                // Punonjësi i thjeshtë shikon vetëm hyrje-daljet e veta
                $query = "SELECT h.id, h.data, h.ora_hyrjes, h.ora_daljes, h.koha_totale, h.komente
                      FROM hyrje_daljet h
                      WHERE h.id_punonjesi = :idPunonjesi
                        AND h.data BETWEEN :dataFillimi AND :dataMbarimi
                      ORDER BY h.data DESC, h.ora_hyrjes DESC";
                $idPunonjesi = $userId; // Punonjësi shikon vetëm të dhënat e veta
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':dataFillimi', $dataFillimi);
            $stmt->bindParam(':dataMbarimi', $dataMbarimi);
            
            if ($userRole === 'admin') {
                if ($idZyra) {
                    $stmt->bindParam(':idZyra', $idZyra);
                }
                
                if ($idPunonjesi) {
                    $stmt->bindParam(':idPunonjesi', $idPunonjesi);
                }
            } elseif ($userRole === 'menaxher') {
                $stmt->bindParam(':idZyra', $userOffice);
                
                if ($idPunonjesi) {
                    $stmt->bindParam(':idPunonjesi', $idPunonjesi);
                }
            } else {
                $stmt->bindParam(':idPunonjesi', $idPunonjesi);
            }
            
            $stmt->execute();
            $hyrjeDaljet = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->setData([
                'numri_total' => count($hyrjeDaljet),
                'data_fillimi' => $dataFillimi,
                'data_mbarimi' => $dataMbarimi,
                'hyrje_daljet' => $hyrjeDaljet
            ]);
            $this->response->send();
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në listimin e hyrje-daljeve: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Merr një hyrje-dalje sipas ID
     * @param int $id ID e hyrje-daljes
     */
    public function getOne($id) {
        try {
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            $userOffice = $this->getUserOffice($userId);
            
            // Ndërto query-n
            $query = "SELECT h.id, h.id_punonjesi, h.data, h.ora_hyrjes, h.ora_daljes, h.koha_totale, h.komente, 
                         p.emri, p.mbiemri, p.email, p.id_zyra
                  FROM hyrje_daljet h
                  JOIN perdoruesit p ON h.id_punonjesi = p.id
                  WHERE h.id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Kontrollo nëse u gjet regjistrimi
            if ($stmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Regjistrimi i hyrje-daljes nuk u gjet");
                $this->response->send();
                exit;
            }
            
            $hyrjeDalja = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Kontrollo të drejtat e përdoruesit
            if ($userRole === 'punonjes' && $hyrjeDalja['id_punonjesi'] != $userId) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të parë këtë regjistrim");
                $this->response->send();
                exit;
            }
            
            if ($userRole === 'menaxher' && $hyrjeDalja['id_zyra'] != $userOffice) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të parë këtë regjistrim");
                $this->response->send();
                exit;
            }
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->setData($hyrjeDalja);
            $this->response->send();
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në marrjen e hyrje-daljes: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Regjistro hyrjen e një punonjësi
     * @param array $data Të dhënat e hyrjes
     */
    public function create($data) {
        try {
            // Kontrollo të dhënat
            if (!isset($data['id_punonjesi']) || !isset($data['data']) || !isset($data['ora_hyrjes'])) {
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
            
            // Kontrollo të drejtat e përdoruesit
            // Punonjësi mund të regjistrojë vetëm hyrjen e tij
            if ($userRole === 'punonjes' && $data['id_punonjesi'] != $userId) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të regjistruar hyrjen e një punonjësi tjetër");
                $this->response->send();
                exit;
            }
            
            // Kontrollo nëse punonjësi është në zyrën e menaxherit
            if ($userRole === 'menaxher') {
                $punonjesQuery = "SELECT id_zyra FROM perdoruesit WHERE id = :id";
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
                if ($punonjesi['id_zyra'] != $userOffice) {
                    $this->response->setHttpStatusCode(403);
                    $this->response->setSuccess(false);
                    $this->response->addMessage("Nuk keni të drejta për të regjistruar hyrjen e këtij punonjësi");
                    $this->response->send();
                    exit;
                }
            }
            
            // Kontrollo nëse ekziston një hyrje e papërfunduar për sot
            $checkQuery = "SELECT id FROM hyrje_daljet 
                          WHERE id_punonjesi = :id_punonjesi 
                            AND data = :data 
                            AND ora_daljes IS NULL";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id_punonjesi', $data['id_punonjesi']);
            $checkStmt->bindParam(':data', $data['data']);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $this->response->setHttpStatusCode(409);
                $this->response->setSuccess(false);
                $this->response->addMessage("Ekziston një hyrje e papërfunduar për këtë punonjës sot");
                $this->response->send();
                exit;
            }
            
            // Ndërto query-n
            $query = "INSERT INTO hyrje_daljet (id_punonjesi, data, ora_hyrjes, komente) 
                      VALUES (:id_punonjesi, :data, :ora_hyrjes, :komente)";
            $stmt = $this->db->prepare($query);
            
            // Bind parametrat
            $stmt->bindParam(':id_punonjesi', $data['id_punonjesi']);
            $stmt->bindParam(':data', $data['data']);
            $stmt->bindParam(':ora_hyrjes', $data['ora_hyrjes']);
            
            // Komente opsionale
            $komente = $data['komente'] ?? null;
            $stmt->bindParam(':komente', $komente);
            
            $stmt->execute();
            
            // Merr ID e regjistrimit të shtuar
            $newId = $this->db->lastInsertId();
            
            // Kontrollo nëse punonjësi ka hyrë me vonesë
            $this->kontrolloVonesen($data['id_punonjesi'], $data['data'], $data['ora_hyrjes']);
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(201);
            $this->response->setSuccess(true);
            $this->response->addMessage("Hyrja u regjistrua me sukses");
            $this->response->setData(['id' => $newId]);
            $this->response->send();
            
            // Regjistro aktivitetin
            $this->logger->logActivity(
                $userId, 
                'create_entry', 
                "Regjistroi hyrjen e punonjësit me ID " . $data['id_punonjesi'] . " në orën " . $data['ora_hyrjes']
            );
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në regjistrimin e hyrjes: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Regjistro daljen e një punonjësi
     * @param int $id ID e regjistrimit të hyrjes
     * @param array $data Të dhënat e daljes
     */
    public function update($id, $data) {
        try {
            // Kontrollo të dhënat
            if (!isset($data['ora_daljes'])) {
                $this->response->setHttpStatusCode(400);
                $this->response->setSuccess(false);
                $this->response->addMessage("Mungon ora e daljes");
                $this->response->send();
                exit;
            }
            
            // Kontrollo nëse regjistrimi ekziston
            $checkQuery = "SELECT h.id, h.id_punonjesi, h.data, h.ora_hyrjes, p.id_zyra 
                          FROM hyrje_daljet h
                          JOIN perdoruesit p ON h.id_punonjesi = p.id
                          WHERE h.id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Regjistrimi i hyrjes nuk u gjet");
                $this->response->send();
                exit;
            }
            
            $hyrjeDalja = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            $userOffice = $this->getUserOffice($userId);
            
            // Punonjësi mund të regjistrojë vetëm daljen e tij
            if ($userRole === 'punonjes' && $hyrjeDalja['id_punonjesi'] != $userId) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të regjistruar daljen e një punonjësi tjetër");
                $this->response->send();
                exit;
            }
            
            // Menaxheri mund të regjistrojë daljen vetëm për punonjësit e zyrës së tij
            if ($userRole === 'menaxher' && $hyrjeDalja['id_zyra'] != $userOffice) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të regjistruar daljen e këtij punonjësi");
                $this->response->send();
                exit;
            }
            
            // Llogarit kohën totale në orë
            $oraHyrjes = new DateTime($hyrjeDalja['ora_hyrjes']);
            $oraDaljes = new DateTime($data['ora_daljes']);
            
            $diff = $oraDaljes->diff($oraHyrjes);
            $kohaTotale = $diff->format('%H:%I:%S');
            
            // Ndërto query-n
            $query = "UPDATE hyrje_daljet 
                      SET ora_daljes = :ora_daljes, 
                          koha_totale = :koha_totale, 
                          komente = :komente 
                      WHERE id = :id";
            $stmt = $this->db->prepare($query);
            
            // Bind parametrat
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':ora_daljes', $data['ora_daljes']);
            $stmt->bindParam(':koha_totale', $kohaTotale);
            
            // Komente opsionale
            $komente = $data['komente'] ?? null;
            $stmt->bindParam(':komente', $komente);
            
            $stmt->execute();
            
            // Kontrollo nëse punonjësi ka dalë herët
            $this->kontrolloDaljeHershem(
                $hyrjeDalja['id_punonjesi'], 
                $hyrjeDalja['data'], 
                $data['ora_daljes']
            );
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->addMessage("Dalja u regjistrua me sukses");
            $this->response->setData([
                'koha_totale' => $kohaTotale
            ]);
            $this->response->send();
            
            // Regjistro aktivitetin
            $this->logger->logActivity(
                $userId, 
                'update_exit', 
                "Regjistroi daljen e punonjësit me ID " . $hyrjeDalja['id_punonjesi'] . " në orën " . $data['ora_daljes']
            );
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në regjistrimin e daljes: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Fshin një regjistrim hyrje-dalje
     * @param int $id ID e regjistrimit
     */
    public function delete($id) {
        try {
            // Kontrollo nëse regjistrimi ekziston
            $checkQuery = "SELECT h.id, h.id_punonjesi, p.id_zyra 
                          FROM hyrje_daljet h
                          JOIN perdoruesit p ON h.id_punonjesi = p.id
                          WHERE h.id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Regjistrimi nuk u gjet");
                $this->response->send();
                exit;
            }
            
            $hyrjeDalja = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            $userOffice = $this->getUserOffice($userId);
            
            // Vetëm admin dhe menaxher mund të fshijnë regjistrime
            if ($userRole === 'punonjes') {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të fshirë regjistrime");
                $this->response->send();
                exit;
            }
            
            // Menaxheri mund të fshijë vetëm regjistrimet e punonjësve të zyrës së tij
            if ($userRole === 'menaxher' && $hyrjeDalja['id_zyra'] != $userOffice) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të fshirë këtë regjistrim");
                $this->response->send();
                exit;
            }
            
            // Ndërto query-n
            $query = "DELETE FROM hyrje_daljet WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->addMessage("Regjistrimi u fshi me sukses");
            $this->response->send();
            
            // Regjistro aktivitetin
            $this->logger->logActivity(
                $userId, 
                'delete_entry_exit', 
                "Fshiu regjistrimin e hyrje-daljes me ID " . $id . " të punonjësit me ID " . $hyrjeDalja['id_punonjesi']
            );
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në fshirjen e regjistrimit: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Kontrollon nëse punonjësi ka hyrë me vonesë dhe krijon njoftim
     * @param int $punonjesiId ID e punonjësit
     * @param string $data Data e hyrjes
     * @param string $oraHyrjes Ora e hyrjes
     */
    private function kontrolloVonesen($punonjesiId, $data, $oraHyrjes) {
        try {
            // Gjej ditën e javës
            $ditaJaves = date('N', strtotime($data));
            $ditatJaves = [
                1 => 'E Hënë',
                2 => 'E Martë',
                3 => 'E Mërkurë',
                4 => 'E Enjte',
                5 => 'E Premte',
                6 => 'E Shtunë',
                7 => 'E Diel'
            ];
            $ditaJaves = $ditatJaves[$ditaJaves];
            
            // Merr orarin e punës për këtë ditë
            $orariQuery = "SELECT ora_fillimit FROM oraret_punonjesve 
                          WHERE id_punonjesi = :id_punonjesi AND dita_javes = :dita_javes";
            $orariStmt = $this->db->prepare($orariQuery);
            $orariStmt->bindParam(':id_punonjesi', $punonjesiId);
            $orariStmt->bindParam(':dita_javes', $ditaJaves);
            $orariStmt->execute();
            
            // Nëse nuk ka orar për këtë ditë, dalja
            if ($orariStmt->rowCount() === 0) {
                return;
            }
            
            $orari = $orariStmt->fetch(PDO::FETCH_ASSOC);
            $oraFillimit = new DateTime($orari['ora_fillimit']);
            $oraAktuale = new DateTime($oraHyrjes);
            
            // Kontrollojmë nëse ka hyrë me vonesë (më shumë se 10 minuta)
            $diff = $oraFillimit->diff($oraAktuale);
            $minutaVonese = $diff->h * 60 + $diff->i;
            
            if ($oraAktuale > $oraFillimit && $minutaVonese > 10) {
                // Krijo njoftim për menaxherin dhe punonjësin
                $menaxherId = $this->getMenaxherIdByPunonjesId($punonjesiId);
                
                if ($menaxherId) {
                    // Merr emrin e punonjësit
                    $punonjesQuery = "SELECT CONCAT(emri, ' ', mbiemri) AS emri_plote FROM perdoruesit WHERE id = :id";
                    $punonjesStmt = $this->db->prepare($punonjesQuery);
                    $punonjesStmt->bindParam(':id', $punonjesiId);
                    $punonjesStmt->execute();
                    $punonjesi = $punonjesStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Njoftim për menaxherin
                    $njoftimQuery = "INSERT INTO njoftimet (id_punonjesi, titulli, permbajtja, lexuar, data_krijimit) 
                                    VALUES (:id_punonjesi, :titulli, :permbajtja, 0, NOW())";
                    $njoftimStmt = $this->db->prepare($njoftimQuery);
                    $njoftimStmt->bindParam(':id_punonjesi', $menaxherId);
                    
                    $titulli = "Vonesë në hyrje";
                    $permbajtja = "Punonjësi {$punonjesi['emri_plote']} ka hyrë me {$minutaVonese} minuta vonesë në datën {$data}.";
                    
                    $njoftimStmt->bindParam(':titulli', $titulli);
                    $njoftimStmt->bindParam(':permbajtja', $permbajtja);
                    $njoftimStmt->execute();
                }
                
                // Njoftim për punonjësin
                $njoftimQuery = "INSERT INTO njoftimet (id_punonjesi, titulli, permbajtja, lexuar, data_krijimit) 
                                VALUES (:id_punonjesi, :titulli, :permbajtja, 0, NOW())";
                $njoftimStmt = $this->db->prepare($njoftimQuery);
                $njoftimStmt->bindParam(':id_punonjesi', $punonjesiId);
                
                $titulli = "Vonesë në hyrje";
                $permbajtja = "Keni hyrë me {$minutaVonese} minuta vonesë në datën {$data}.";
                
                $njoftimStmt->bindParam(':titulli', $titulli);
                $njoftimStmt->bindParam(':permbajtja', $permbajtja);
                $njoftimStmt->execute();
            }
            
        } catch (Exception $ex) {
            // Thjesht regjistro gabimin, mos ndërprit ekzekutimin
            $this->logger->logError("Gabim në kontrollin e vonesës: " . $ex->getMessage(), __FILE__, __LINE__);
        }
    }
    
    /**
     * Kontrollon nëse punonjësi ka dalë para orarit dhe krijon njoftim
     * @param int $punonjesiId ID e punonjësit
     * @param string $data Data e daljes
     * @param string $oraDaljes Ora e daljes
     */
    private function kontrolloDaljeHershem($punonjesiId, $data, $oraDaljes) {
        try {
            // Gjej ditën e javës
            $ditaJaves = date('N', strtotime($data));
            $ditatJaves = [
                1 => 'E Hënë',
                2 => 'E Martë',
                3 => 'E Mërkurë',
                4 => 'E Enjte',
                5 => 'E Premte',
                6 => 'E Shtunë',
                7 => 'E Diel'
            ];
            $ditaJaves = $ditatJaves[$ditaJaves];
            
            // Merr orarin e punës për këtë ditë
            $orariQuery = "SELECT ora_mbarimit FROM oraret_punonjesve 
                          WHERE id_punonjesi = :id_punonjesi AND dita_javes = :dita_javes";
            $orariStmt = $this->db->prepare($orariQuery);
            $orariStmt->bindParam(':id_punonjesi', $punonjesiId);
            $orariStmt->bindParam(':dita_javes', $ditaJaves);
            $orariStmt->execute();
            
            // Nëse nuk ka orar për këtë ditë, dalja
            if ($orariStmt->rowCount() === 0) {
                return;
            }
            
            $orari = $orariStmt->fetch(PDO::FETCH_ASSOC);
            $oraMbarimit = new DateTime($orari['ora_mbarimit']);
            $oraAktuale = new DateTime($oraDaljes);
            
            // Kontrollojmë nëse ka dalë para orarit (më shumë se 10 minuta)
            if ($oraAktuale < $oraMbarimit) {
                $diff = $oraMbarimit->diff($oraAktuale);
                $minutaDiference = $diff->h * 60 + $diff->i;
                
                if ($minutaDiference > 10) {
                    // Krijo njoftim për menaxherin
                    $menaxherId = $this->getMenaxherIdByPunonjesId($punonjesiId);
                    
                    if ($menaxherId) {
                        // Merr emrin e punonjësit
                        $punonjesQuery = "SELECT CONCAT(emri, ' ', mbiemri) AS emri_plote FROM perdoruesit WHERE id = :id";
                        $punonjesStmt = $this->db->prepare($punonjesQuery);
                        $punonjesStmt->bindParam(':id', $punonjesiId);
                        $punonjesStmt->execute();
                        $punonjesi = $punonjesStmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Njoftim për menaxherin
                        $njoftimQuery = "INSERT INTO njoftimet (id_punonjesi, titulli, permbajtja, lexuar, data_krijimit) 
                                        VALUES (:id_punonjesi, :titulli, :permbajtja, 0, NOW())";
                        $njoftimStmt = $this->db->prepare($njoftimQuery);
                        $njoftimStmt->bindParam(':id_punonjesi', $menaxherId);
                        
                        $titulli = "Dalje para orarit";
                        $permbajtja = "Punonjësi {$punonjesi['emri_plote']} ka dalë {$minutaDiference} minuta para orarit në datën {$data}.";
                        
                        $njoftimStmt->bindParam(':titulli', $titulli);
                        $njoftimStmt->bindParam(':permbajtja', $permbajtja);
                        $njoftimStmt->execute();
                    }
                }
            }
            
        } catch (Exception $ex) {
            // Thjesht regjistro gabimin, mos ndërprit ekzekutimin
            $this->logger->logError("Gabim në kontrollin e daljes së hershme: " . $ex->getMessage(), __FILE__, __LINE__);
        }
    }
    
    /**
     * Merr ID e menaxherit të zyrës ku punon punonjësi
     * @param int $punonjesiId ID e punonjësit
     * @return int|null ID e menaxherit ose null nëse nuk ka
     */
    private function getMenaxherIdByPunonjesId($punonjesiId) {
        try {
            // Merr ID e zyrës ku punon punonjësi
            $zyraQuery = "SELECT id_zyra FROM perdoruesit WHERE id = :id";
            $zyraStmt = $this->db->prepare($zyraQuery);
            $zyraStmt->bindParam(':id', $punonjesiId);
            $zyraStmt->execute();
            
            if ($zyraStmt->rowCount() === 0) {
                return null;
            }
            
            $zyra = $zyraStmt->fetch(PDO::FETCH_ASSOC);
            
            // Merr ID e menaxherit të zyrës
            $menaxherQuery = "SELECT id FROM perdoruesit 
                             WHERE id_zyra = :id_zyra AND roli = 'menaxher'";
            $menaxherStmt = $this->db->prepare($menaxherQuery);
            $menaxherStmt->bindParam(':id_zyra', $zyra['id_zyra']);
            $menaxherStmt->execute();
            
            if ($menaxherStmt->rowCount() === 0) {
                return null;
            }
            
            return $menaxherStmt->fetch(PDO::FETCH_ASSOC)['id'];
            
        } catch (Exception $ex) {
            $this->logger->logError("Gabim në marrjen e ID të menaxherit: " . $ex->getMessage(), __FILE__, __LINE__);
            return null;
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