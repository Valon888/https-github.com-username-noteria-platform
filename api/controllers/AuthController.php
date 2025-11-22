<?php
/**
 * Kontrolluesi për autentikim
 */
class AuthController {
    private $db;
    private $response;
    private $logger;
    private $auth;
    
    /**
     * Konstruktori
     * @param PDO $db Lidhja me databazën
     * @param Response $response Objekti i përgjigjes
     * @param Logger $logger Objekti i regjistrimit të aktivitetit
     * @param TokenAuth $auth Objekti i autentikimit
     */
    public function __construct($db, $response, $logger, $auth) {
        $this->db = $db;
        $this->response = $response;
        $this->logger = $logger;
        $this->auth = $auth;
    }
    
    /**
     * Autentikimi i përdoruesit
     * @param array $data Të dhënat e kërkesës
     */
    public function login($data) {
        // Kontrollo të dhënat
        if (!isset($data['email']) || !isset($data['fjalekalimi'])) {
            $this->response->setHttpStatusCode(400);
            $this->response->setSuccess(false);
            $this->response->addMessage("Mungojnë të dhënat e kërkuara");
            $this->response->send();
            exit;
        }
        
        try {
            // Përgatit query
            $query = "SELECT id, emri, mbiemri, email, fjalekalimi, roli, id_zyra, aktiv 
                      FROM perdoruesit 
                      WHERE email = :email AND aktiv = 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':email', $data['email']);
            $stmt->execute();
            
            // Kontrollo nëse ekziston përdoruesi
            if ($stmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(401);
                $this->response->setSuccess(false);
                $this->response->addMessage("Email ose fjalëkalim i gabuar");
                $this->response->send();
                $this->logger->logActivity(null, 'login_failed', 'Email i gabuar: ' . $data['email']);
                exit;
            }
            
            // Merr të dhënat e përdoruesit
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verifikoni fjalëkalimin
            if (!password_verify($data['fjalekalimi'], $row['fjalekalimi'])) {
                $this->response->setHttpStatusCode(401);
                $this->response->setSuccess(false);
                $this->response->addMessage("Email ose fjalëkalim i gabuar");
                $this->response->send();
                $this->logger->logActivity($row['id'], 'login_failed', 'Fjalëkalim i gabuar për: ' . $data['email']);
                exit;
            }
            
            // Gjeneroni token-in
            $token = $this->auth->generateToken($row['id']);
            
            // Përgatit të dhënat e përgjigjes
            $userInfo = [
                'id' => $row['id'],
                'emri' => $row['emri'],
                'mbiemri' => $row['mbiemri'],
                'email' => $row['email'],
                'roli' => $row['roli'],
                'id_zyra' => $row['id_zyra'],
                'token' => $token
            ];
            
            // Dërgo përgjigjen
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->addMessage("Identifikimi u realizua me sukses");
            $this->response->setData($userInfo);
            $this->response->send();
            
            // Regjistro aktivitetin
            $this->logger->logActivity($row['id'], 'login_success', 'Përdoruesi u identifikua me sukses');
            
        } catch (PDOException $ex) {
            $this->logger->logError("Gabim në bazën e të dhënave gjatë loginit: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistemin e autentikimit");
            $this->response->send();
            exit;
        }
    }
    
    /**
     * Dalja nga sistemi
     */
    public function logout() {
        try {
            // Merr token-in nga headeri
            $headers = getallheaders();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
            
            if (!$token) {
                $this->response->setHttpStatusCode(400);
                $this->response->setSuccess(false);
                $this->response->addMessage("Token-i mungon");
                $this->response->send();
                exit;
            }
            
            // Invalidoni token-in
            if ($this->auth->invalidateToken($token)) {
                $this->response->setHttpStatusCode(200);
                $this->response->setSuccess(true);
                $this->response->addMessage("Dalja nga sistemi u realizua me sukses");
                $this->response->send();
                
                // Regjistro aktivitetin
                $userId = $_SERVER['AUTHENTICATED_USER_ID'] ?? null;
                $this->logger->logActivity($userId, 'logout', 'Përdoruesi doli nga sistemi');
            } else {
                $this->response->setHttpStatusCode(500);
                $this->response->setSuccess(false);
                $this->response->addMessage("Gabim gjatë daljes nga sistemi");
                $this->response->send();
            }
            
        } catch (Exception $ex) {
            $this->logger->logError("Gabim gjatë logout: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistemin e daljes");
            $this->response->send();
        }
    }
    
    /**
     * Rivendosje e fjalëkalimit
     * @param array $data Të dhënat e kërkesës
     */
    public function forgotPassword($data) {
        if (!isset($data['email'])) {
            $this->response->setHttpStatusCode(400);
            $this->response->setSuccess(false);
            $this->response->addMessage("Mungon email-i");
            $this->response->send();
            exit;
        }
        
        try {
            // Kontrollo nëse ekziston përdoruesi
            $query = "SELECT id, emri, mbiemri FROM perdoruesit WHERE email = :email AND aktiv = 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':email', $data['email']);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Nuk u gjet përdorues me këtë email");
                $this->response->send();
                exit;
            }
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Krijo një token për rivendosjen e fjalëkalimit
            $resetToken = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 orë
            
            // Ruaj token-in në databazë
            $query = "INSERT INTO token_rivendosje_fjalekalimi (id_perdorues, token, data_skadimit) 
                      VALUES (:userId, :token, :expires)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':userId', $user['id']);
            $stmt->bindParam(':token', $resetToken);
            $stmt->bindParam(':expires', $expires);
            $stmt->execute();
            
            // Këtu do të dërgonim një email me linkun për rivendosjen e fjalëkalimit
            // Por për qëllime demo thjesht kthejmë token-in në përgjigje
            
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->addMessage("Linku për rivendosjen e fjalëkalimit është dërguar në email-in tuaj");
            $this->response->setData([
                'resetToken' => $resetToken,
                'expires' => $expires
            ]);
            $this->response->send();
            
            // Regjistro aktivitetin
            $this->logger->logActivity($user['id'], 'forgot_password', 'Kërkesë për rivendosje fjalëkalimi');
            
        } catch (Exception $ex) {
            $this->logger->logError("Gabim gjatë forgot password: " . $ex->getMessage(), __FILE__, __LINE__);
            $this->response->setHttpStatusCode(500);
            $this->response->setSuccess(false);
            $this->response->addMessage("Gabim në sistemin e rivendosjes së fjalëkalimit");
            $this->response->send();
        }
    }
}