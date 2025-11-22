<?php
/**
 * Kontrolleri për menaxhimin e njoftimeve të sistemit
 */
class NjoftimController {
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
     * Merr të gjitha njoftimet
     */
    public function getAll() {
        try {
            // ID e përdoruesit të autentikuar
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            $userOffice = $this->getUserOffice($userId);
            
            // Filtrat
            $vetemPaLexuara = isset($_GET['pa_lexuara']) && $_GET['pa_lexuara'] === 'true';
            $idPunonjesi = isset($_GET['id_punonjesi']) ? (int)$_GET['id_punonjesi'] : null;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            
            // Ndërto query-n bazuar në rolin e përdoruesit
            if ($userRole === 'admin') {
                // Admin mund të shohë të gjitha njoftimet në sistem
                if ($idPunonjesi) {
                    // Nëse është specifikuar një punonjës
                    $query = "SELECT n.id, n.id_punonjesi, n.titulli, n.permbajtja, 
                               n.id_detyre, n.id_leje, n.id_hyrje_dalje, 
                               n.lexuar, n.data_krijimit, n.data_leximit,
                               p.emri, p.mbiemri, p.email
                            FROM njoftimet n
                            JOIN perdoruesit p ON n.id_punonjesi = p.id
                            WHERE n.id_punonjesi = :id_punonjesi";
                    
                    if ($vetemPaLexuara) {
                        $query .= " AND n.lexuar = 0";
                    }
                    
                    $query .= " ORDER BY n.data_krijimit DESC LIMIT :limit";
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->bindParam(':id_punonjesi', $idPunonjesi);
                    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                    
                } else {
                    // Nëse nuk është specifikuar një punonjës
                    $query = "SELECT n.id, n.id_punonjesi, n.titulli, n.permbajtja, 
                               n.id_detyre, n.id_leje, n.id_hyrje_dalje, 
                               n.lexuar, n.data_krijimit, n.data_leximit,
                               p.emri, p.mbiemri, p.email
                            FROM njoftimet n
                            JOIN perdoruesit p ON n.id_punonjesi = p.id";
                    
                    if ($vetemPaLexuara) {
                        $query .= " WHERE n.lexuar = 0";
                    }
                    
                    $query .= " ORDER BY n.data_krijimit DESC LIMIT :limit";
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                }
            } elseif ($userRole === 'menaxher') {
                // Menaxheri shikon njoftimet e veta dhe të punonjësve të zyrës së tij
                if ($idPunonjesi) {
                    // Kontrollojmë nëse punonjësi është në zyrën e menaxherit
                    $punonjesQuery = "SELECT id_zyra FROM perdoruesit WHERE id = :id";
                    $punonjesStmt = $this->db->prepare($punonjesQuery);
                    $punonjesStmt->bindParam(':id', $idPunonjesi);
                    $punonjesStmt->execute();
                    
                    if ($punonjesStmt->rowCount() === 0 || $punonjesStmt->fetch(PDO::FETCH_ASSOC)['id_zyra'] != $userOffice) {
                        $this->response->setHttpStatusCode(403);
                        $this->response->setSuccess(false);
                        $this->response->addMessage("Nuk keni të drejta për të parë njoftimet e këtij punonjësi");
                        $this->response->send();
                        exit;
                    }
                    
                    // Nëse është punonjës i zyrës së tij
                    $query = "SELECT n.id, n.id_punonjesi, n.titulli, n.permbajtja, 
                               n.id_detyre, n.id_leje, n.id_hyrje_dalje, 
                               n.lexuar, n.data_krijimit, n.data_leximit
                            FROM njoftimet n
                            WHERE n.id_punonjesi = :id_punonjesi";
                    
                    if ($vetemPaLexuara) {
                        $query .= " AND n.lexuar = 0";
                    }
                    
                    $query .= " ORDER BY n.data_krijimit DESC LIMIT :limit";
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->bindParam(':id_punonjesi', $idPunonjesi);
                    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                    
                } else {
                    // Nëse kërkon njoftimet e veta
                    $query = "SELECT n.id, n.titulli, n.permbajtja, 
                               n.id_detyre, n.id_leje, n.id_hyrje_dalje, 
                               n.lexuar, n.data_krijimit, n.data_leximit
                            FROM njoftimet n
                            WHERE n.id_punonjesi = :id_punonjesi";
                    
                    if ($vetemPaLexuara) {
                        $query .= " AND n.lexuar = 0";
                    }
                    
                    $query .= " ORDER BY n.data_krijimit DESC LIMIT :limit";
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->bindParam(':id_punonjesi', $userId);
                    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                }
            } else {
                // Punonjësi i thjeshtë shikon vetëm njoftimet e tij
                $query = "SELECT n.id, n.titulli, n.permbajtja, 
                           n.id_detyre, n.id_leje, n.id_hyrje_dalje, 
                           n.lexuar, n.data_krijimit, n.data_leximit
                        FROM njoftimet n
                        WHERE n.id_punonjesi = :id_punonjesi";
                
                if ($vetemPaLexuara) {
                    $query .= " AND n.lexuar = 0";
                }
                
                $query .= " ORDER BY n.data_krijimit DESC LIMIT :limit";
                
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':id_punonjesi', $userId);
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            $njoftimet = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Numëro të gjitha njoftimet dhe njoftimet e palexuara
            if ($userRole === 'admin' && !$idPunonjesi) {
                // Admin pa filtrim punonjësi - numëron të gjitha njoftimet në sistem
                $countQuery = "SELECT 
                                (SELECT COUNT(*) FROM njoftimet) AS total,
                                (SELECT COUNT(*) FROM njoftimet WHERE lexuar = 0) AS pa_lexuara";
            } elseif ($userRole === 'admin' && $idPunonjesi) {
                // Admin me filtrim punonjësi
                $countQuery = "SELECT 
                                (SELECT COUNT(*) FROM njoftimet WHERE id_punonjesi = :id_punonjesi) AS total,
                                (SELECT COUNT(*) FROM njoftimet WHERE id_punonjesi = :id_punonjesi AND lexuar = 0) AS pa_lexuara";
            } elseif ($userRole === 'menaxher' && $idPunonjesi && $idPunonjesi != $userId) {
                // Menaxher që shikon njoftimet e një punonjësi
                $countQuery = "SELECT 
                                (SELECT COUNT(*) FROM njoftimet WHERE id_punonjesi = :id_punonjesi) AS total,
                                (SELECT COUNT(*) FROM njoftimet WHERE id_punonjesi = :id_punonjesi AND lexuar = 0) AS pa_lexuara";
            } else {
                // Punonjës ose menaxher që shikon njoftimet e veta
                $countQuery = "SELECT 
                                (SELECT COUNT(*) FROM njoftimet WHERE id_punonjesi = :id) AS total,
                                (SELECT COUNT(*) FROM njoftimet WHERE id_punonjesi = :id AND lexuar = 0) AS pa_lexuara";
            }
            
            $countStmt = $this->db->prepare($countQuery);
            
            if ($userRole === 'admin' && $idPunonjesi) {
                $countStmt->bindParam(':id_punonjesi', $idPunonjesi);
            } elseif ($userRole === 'menaxher' && $idPunonjesi && $idPunonjesi != $userId) {
                $countStmt->bindParam(':id_punonjesi', $idPunonjesi);
            } else {
                $countStmt->bindParam(':id', $userId);
            }
            
            $countStmt->execute();
            $numrat = $countStmt->fetch(PDO::FETCH_ASSOC);
            
            // Për secilin njoftim, shto informacion shtesë nëse ka referencë
            foreach ($njoftimet as &$njoftim) {
                if ($njoftim['id_detyre']) {
                    $njoftim['detyre'] = $this->getDetyreInfo($njoftim['id_detyre']);
                }
                
                if ($njoftim['id_leje']) {
                    $njoftim['leje'] = $this->getLejeInfo($njoftim['id_leje']);
                }
                
                if ($njoftim['id_hyrje_dalje']) {
                    $njoftim['hyrje_dalje'] = $this->getHyrjeDaljeInfo($njoftim['id_hyrje_dalje']);
                }
            }
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->setData([
                'numri_total' => (int)$numrat['total'],
                'numri_pa_lexuara' => (int)$numrat['pa_lexuara'],
                'njoftimet' => $njoftimet
            ]);
            $this->response->send();
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në listimin e njoftimeve: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Merr një njoftim sipas ID
     * @param int $id ID e njoftimit
     */
    public function getOne($id) {
        try {
            // ID e përdoruesit të autentikuar
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            
            // Ndërto query-n për të marrë njoftimin
            $query = "SELECT n.id, n.id_punonjesi, n.titulli, n.permbajtja, 
                       n.id_detyre, n.id_leje, n.id_hyrje_dalje, 
                       n.lexuar, n.data_krijimit, n.data_leximit,
                       p.emri, p.mbiemri, p.email, p.id_zyra
                    FROM njoftimet n
                    JOIN perdoruesit p ON n.id_punonjesi = p.id
                    WHERE n.id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Kontrollo nëse njoftimi ekziston
            if ($stmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Njoftimi nuk u gjet");
                $this->response->send();
                exit;
            }
            
            $njoftim = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Kontrollo nëse përdoruesi ka të drejtë për të parë këtë njoftim
            if ($userRole === 'punonjes' && $njoftim['id_punonjesi'] != $userId) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të parë këtë njoftim");
                $this->response->send();
                exit;
            }
            
            if ($userRole === 'menaxher' && $njoftim['id_punonjesi'] != $userId) {
                // Kontrollo nëse është punonjës i zyrës së menaxherit
                $userOffice = $this->getUserOffice($userId);
                if ($njoftim['id_zyra'] != $userOffice) {
                    $this->response->setHttpStatusCode(403);
                    $this->response->setSuccess(false);
                    $this->response->addMessage("Nuk keni të drejta për të parë këtë njoftim");
                    $this->response->send();
                    exit;
                }
            }
            
            // Shëno njoftimin si të lexuar nëse është për përdoruesin aktual dhe nuk është lexuar ende
            if ($njoftim['id_punonjesi'] == $userId && $njoftim['lexuar'] == 0) {
                $updateQuery = "UPDATE njoftimet SET lexuar = 1, data_leximit = NOW() WHERE id = :id";
                $updateStmt = $this->db->prepare($updateQuery);
                $updateStmt->bindParam(':id', $id);
                $updateStmt->execute();
                
                $njoftim['lexuar'] = 1;
                $njoftim['data_leximit'] = date('Y-m-d H:i:s');
            }
            
            // Shto informacion shtesë nëse ka referencë
            if ($njoftim['id_detyre']) {
                $njoftim['detyre'] = $this->getDetyreInfo($njoftim['id_detyre']);
            }
            
            if ($njoftim['id_leje']) {
                $njoftim['leje'] = $this->getLejeInfo($njoftim['id_leje']);
            }
            
            if ($njoftim['id_hyrje_dalje']) {
                $njoftim['hyrje_dalje'] = $this->getHyrjeDaljeInfo($njoftim['id_hyrje_dalje']);
            }
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->setData($njoftim);
            $this->response->send();
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në marrjen e njoftimit: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Krijon një njoftim të ri
     * @param array $data Të dhënat e njoftimit
     */
    public function create($data) {
        try {
            // Kontrollo të dhënat
            if (!isset($data['id_punonjesi']) || !isset($data['titulli']) || 
                !isset($data['permbajtja'])) {
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
            
            // Vetëm admin dhe menaxherët mund të krijojnë njoftime për të tjerët
            if ($userRole === 'punonjes') {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të krijuar njoftime");
                $this->response->send();
                exit;
            }
            
            // Menaxheri mund të krijojë njoftime vetëm për punonjësit e zyrës së tij
            if ($userRole === 'menaxher') {
                $punonjesOffice = $this->getUserOffice($data['id_punonjesi']);
                if ($punonjesOffice != $userOffice && $data['id_punonjesi'] != $userId) {
                    $this->response->setHttpStatusCode(403);
                    $this->response->setSuccess(false);
                    $this->response->addMessage("Nuk keni të drejta për të krijuar njoftim për këtë punonjës");
                    $this->response->send();
                    exit;
                }
            }
            
            // Ndërto query-n
            $query = "INSERT INTO njoftimet (id_punonjesi, titulli, permbajtja, id_detyre, id_leje, id_hyrje_dalje, lexuar, data_krijimit) 
                      VALUES (:id_punonjesi, :titulli, :permbajtja, :id_detyre, :id_leje, :id_hyrje_dalje, 0, NOW())";
            $stmt = $this->db->prepare($query);
            
            // Bind parametrat
            $stmt->bindParam(':id_punonjesi', $data['id_punonjesi']);
            $stmt->bindParam(':titulli', $data['titulli']);
            $stmt->bindParam(':permbajtja', $data['permbajtja']);
            
            // Parametrat opsionale
            $idDetyre = $data['id_detyre'] ?? null;
            $idLeje = $data['id_leje'] ?? null;
            $idHyrjeDalje = $data['id_hyrje_dalje'] ?? null;
            
            $stmt->bindParam(':id_detyre', $idDetyre);
            $stmt->bindParam(':id_leje', $idLeje);
            $stmt->bindParam(':id_hyrje_dalje', $idHyrjeDalje);
            
            $stmt->execute();
            
            // Merr ID e njoftimit të shtuar
            $newId = $this->db->lastInsertId();
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(201);
            $this->response->setSuccess(true);
            $this->response->addMessage("Njoftimi u krijua me sukses");
            $this->response->setData(['id' => $newId]);
            $this->response->send();
            
            // Regjistro aktivitetin
            $this->logger->logActivity(
                $userId, 
                'create_notification', 
                "Krijoi një njoftim për punonjësin me ID {$data['id_punonjesi']}"
            );
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në krijimin e njoftimit: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Shënon një njoftim si të lexuar
     * @param int $id ID e njoftimit
     * @param array $data Të dhënat për përditësim (nuk nevojiten për këtë funksion)
     */
    public function update($id, $data = null) {
        try {
            // Kontrollo nëse njoftimi ekziston
            $checkQuery = "SELECT n.id, n.id_punonjesi, n.lexuar, p.id_zyra 
                          FROM njoftimet n
                          JOIN perdoruesit p ON n.id_punonjesi = p.id
                          WHERE n.id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Njoftimi nuk u gjet");
                $this->response->send();
                exit;
            }
            
            $njoftim = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // Kontrollo nëse njoftimi është tashmë i lexuar
            if ($njoftim['lexuar'] == 1) {
                $this->response->setHttpStatusCode(409);
                $this->response->setSuccess(false);
                $this->response->addMessage("Njoftimi është tashmë i lexuar");
                $this->response->send();
                exit;
            }
            
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            
            // Vetëm përdoruesi për të cilin është njoftimi ose admin mund ta shënojë si të lexuar
            if ($userRole !== 'admin' && $njoftim['id_punonjesi'] != $userId) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të shënuar këtë njoftim si të lexuar");
                $this->response->send();
                exit;
            }
            
            // Ndërto query-n
            $query = "UPDATE njoftimet SET lexuar = 1, data_leximit = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->addMessage("Njoftimi u shënua si i lexuar");
            $this->response->send();
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në shënimin e njoftimit si të lexuar: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Fshin një njoftim
     * @param int $id ID e njoftimit
     */
    public function delete($id) {
        try {
            // Kontrollo nëse njoftimi ekziston
            $checkQuery = "SELECT n.id, n.id_punonjesi, p.id_zyra 
                          FROM njoftimet n
                          JOIN perdoruesit p ON n.id_punonjesi = p.id
                          WHERE n.id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Njoftimi nuk u gjet");
                $this->response->send();
                exit;
            }
            
            $njoftim = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            
            // Vetëm përdoruesi për të cilin është njoftimi ose admin mund ta fshijë
            if ($userRole !== 'admin' && $njoftim['id_punonjesi'] != $userId) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të fshirë këtë njoftim");
                $this->response->send();
                exit;
            }
            
            // Ndërto query-n
            $query = "DELETE FROM njoftimet WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->addMessage("Njoftimi u fshi me sukses");
            $this->response->send();
            
            // Regjistro aktivitetin
            $this->logger->logActivity(
                $userId, 
                'delete_notification', 
                "Fshiu njoftimin me ID " . $id
            );
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në fshirjen e njoftimit: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Shënon të gjitha njoftimet e një përdoruesi si të lexuara
     * @param int $id ID e përdoruesit
     * @param string $subResource Resursi dytësor (lexo)
     */
    public function updateSubResource($id, $subResource, $data = null) {
        try {
            // Vetëm nënresursi 'lexo' është i lejuar
            if ($subResource !== 'lexo') {
                throw new Exception("Nënresursi i pavlefshëm: " . $subResource);
            }
            
            // Kontrollo nëse përdoruesi ka të drejtë për këtë veprim
            $userId = $_SERVER['AUTHENTICATED_USER_ID'];
            $userRole = $this->getUserRole($userId);
            
            // Vetëm përdoruesi vetë ose admin mund t'i shënojë të gjitha njoftimet si të lexuara
            if ($userRole !== 'admin' && $id != $userId) {
                $this->response->setHttpStatusCode(403);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk keni të drejta për të shënuar njoftimet e këtij përdoruesi si të lexuara");
                $this->response->send();
                exit;
            }
            
            // Ndërto query-n
            $query = "UPDATE njoftimet SET lexuar = 1, data_leximit = NOW() WHERE id_punonjesi = :id_punonjesi AND lexuar = 0";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id_punonjesi', $id);
            $stmt->execute();
            
            // Numri i njoftimeve të përditësuara
            $numriNjoftimeve = $stmt->rowCount();
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->addMessage("U shënuan $numriNjoftimeve njoftime si të lexuara");
            $this->response->setData(['numri_njoftimeve' => $numriNjoftimeve]);
            $this->response->send();
            
            // Regjistro aktivitetin
            $this->logger->logActivity(
                $userId, 
                'mark_all_read', 
                "Shënoi të gjitha njoftimet si të lexuara për përdoruesin me ID " . $id
            );
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në shënimin e të gjitha njoftimeve si të lexuara: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistem");
            $this->response->send();
            exit;
        } catch (Exception $ex) {
            $this->response->setHttpStatusCode(405);
            $this->response->setSuccess(false);
            $this->response->addMessage($ex->getMessage());
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Merr informacion për një detyrë
     * @param int $id ID e detyrës
     * @return array|null Informacioni për detyrën ose null nëse nuk ekziston
     */
    private function getDetyreInfo($id) {
        try {
            $query = "SELECT d.id, d.titulli, d.statusi, d.prioriteti, d.afati
                     FROM detyrat d
                     WHERE d.id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                return null;
            }
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $ex) {
            return null;
        }
    }
    
    /**
     * Merr informacion për një leje
     * @param int $id ID e lejes
     * @return array|null Informacioni për lejen ose null nëse nuk ekziston
     */
    private function getLejeInfo($id) {
        try {
            $query = "SELECT l.id, l.tipi, l.statusi, l.data_fillimit, l.data_mbarimit
                     FROM lejet l
                     WHERE l.id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                return null;
            }
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $ex) {
            return null;
        }
    }
    
    /**
     * Merr informacion për një hyrje-dalje
     * @param int $id ID e hyrje-daljes
     * @return array|null Informacioni për hyrje-daljen ose null nëse nuk ekziston
     */
    private function getHyrjeDaljeInfo($id) {
        try {
            $query = "SELECT h.id, h.lloji, h.koha, h.koment
                     FROM hyrje_daljet h
                     WHERE h.id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                return null;
            }
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $ex) {
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