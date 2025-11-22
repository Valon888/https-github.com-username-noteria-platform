<?php
/**
 * RaportController - Menaxhimi i raporteve për sistemin noterial
 * Ofron CRUD, gjenerim raportesh, filtrime sipas datës, përdoruesit, zyrës, etj.
 */
class RaportController {
    private $db;
    private $response;
    private $logger;

    public function __construct($db, $response, $logger) {
        $this->db = $db;
        $this->response = $response;
        $this->logger = $logger;
    }

    // GET /raport
    public function getAll() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM raportet ORDER BY data_krijimit DESC");
            $stmt->execute();
            $raportet = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->setData($raportet);
            $this->response->send();
        } catch (Exception $ex) {
            $this->handleError($ex, "Gabim gjatë marrjes së raporteve");
        }
    }

    // GET /raport/{id}
    public function getOne($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM raportet WHERE id = ?");
            $stmt->execute([$id]);
            $raport = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($raport) {
                $this->response->setHttpStatusCode(200);
                $this->response->setSuccess(true);
                $this->response->setData($raport);
            } else {
                $this->response->setHttpStatusCode(404);
                $this->response->setSuccess(false);
                $this->response->addMessage("Raporti nuk u gjet");
            }
            $this->response->send();
        } catch (Exception $ex) {
            $this->handleError($ex, "Gabim gjatë marrjes së raportit");
        }
    }

    // POST /raport
    public function create($data) {
        try {
            $stmt = $this->db->prepare("INSERT INTO raportet (titulli, pershkrimi, data_krijimit, user_id, zyra_id) VALUES (?, ?, NOW(), ?, ?)");
            $stmt->execute([
                $data['titulli'] ?? '',
                $data['pershkrimi'] ?? '',
                $_SERVER['AUTHENTICATED_USER_ID'] ?? null,
                $data['zyra_id'] ?? null
            ]);
            $id = $this->db->lastInsertId();
            $this->logger->log("U krijua raport i ri me ID: $id", $_SERVER['AUTHENTICATED_USER_ID'] ?? null);
            $this->response->setHttpStatusCode(201);
            $this->response->setSuccess(true);
            $this->response->addMessage("Raporti u krijua me sukses");
            $this->response->setData(['id' => $id]);
            $this->response->send();
        } catch (Exception $ex) {
            $this->handleError($ex, "Gabim gjatë krijimit të raportit");
        }
    }

    // PUT /raport/{id}
    public function update($id, $data) {
        try {
            $stmt = $this->db->prepare("UPDATE raportet SET titulli = ?, pershkrimi = ?, zyra_id = ? WHERE id = ?");
            $stmt->execute([
                $data['titulli'] ?? '',
                $data['pershkrimi'] ?? '',
                $data['zyra_id'] ?? null,
                $id
            ]);
            $this->logger->log("U përditësua raporti me ID: $id", $_SERVER['AUTHENTICATED_USER_ID'] ?? null);
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->addMessage("Raporti u përditësua me sukses");
            $this->response->send();
        } catch (Exception $ex) {
            $this->handleError($ex, "Gabim gjatë përditësimit të raportit");
        }
    }

    // DELETE /raport/{id}
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM raportet WHERE id = ?");
            $stmt->execute([$id]);
            $this->logger->log("U fshi raporti me ID: $id", $_SERVER['AUTHENTICATED_USER_ID'] ?? null);
            $this->response->setHttpStatusCode(200);
            $this->response->setSuccess(true);
            $this->response->addMessage("Raporti u fshi me sukses");
            $this->response->send();
        } catch (Exception $ex) {
            $this->handleError($ex, "Gabim gjatë fshirjes së raportit");
        }
    }

    // GET /raport/{id}/detaje ose filtrime të avancuara
    public function getSubResource($id, $subResource) {
        try {
            if ($subResource === 'detaje') {
                $stmt = $this->db->prepare("SELECT * FROM raportet WHERE id = ?");
                $stmt->execute([$id]);
                $raport = $stmt->fetch(PDO::FETCH_ASSOC);
                $this->response->setHttpStatusCode(200);
                $this->response->setSuccess(true);
                $this->response->setData($raport);
                $this->response->send();
            } else {
                throw new Exception("Sub-resource nuk mbështetet");
            }
        } catch (Exception $ex) {
            $this->handleError($ex, "Gabim gjatë marrjes së sub-resource");
        }
    }

    // POST /raport/{id}/subResource
    public function createSubResource($id, $subResource, $data) {
        try {
            throw new Exception("Krijimi i sub-resource nuk mbështetet për raportet");
        } catch (Exception $ex) {
            $this->handleError($ex, $ex->getMessage());
        }
    }

    // PUT /raport/{id}/subResource
    public function updateSubResource($id, $subResource, $data) {
        try {
            throw new Exception("Përditësimi i sub-resource nuk mbështetet për raportet");
        } catch (Exception $ex) {
            $this->handleError($ex, $ex->getMessage());
        }
    }

    // DELETE /raport/{id}/subResource
    public function deleteSubResource($id, $subResource) {
        try {
            throw new Exception("Fshirja e sub-resource nuk mbështetet për raportet");
        } catch (Exception $ex) {
            $this->handleError($ex, $ex->getMessage());
        }
    }

    // Funksion ndihmës për trajtimin e gabimeve
    private function handleError($ex, $msg) {
        $this->response->setHttpStatusCode(500);
        $this->response->setSuccess(false);
        $this->response->addMessage($msg . ': ' . $ex->getMessage());
        $this->response->send();
    }
}
