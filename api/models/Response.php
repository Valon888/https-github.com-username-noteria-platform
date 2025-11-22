<?php
/**
 * Klasa për trajtimin e përgjigjeve të API-t
 */
class Response {
    // Kodi HTTP
    private $_httpStatusCode;
    
    // Rezultati i përgjithshëm
    private $_success;
    
    // Mesazhet për klientin
    private $_messages = array();
    
    // Të dhënat e përgjigjes
    private $_data = array();
    
    // Kodet e përgjigjeve të rasteve të trajtimit
    private $_responseData = array();
    
    /**
     * Vendos kodin e statusit HTTP
     * @param int $statusCode Kodi i statusit HTTP
     */
    public function setHttpStatusCode($httpStatusCode) {
        $this->_httpStatusCode = $httpStatusCode;
    }
    
    /**
     * Vendos suksesin e kërkesës
     * @param bool $success Nëse kërkesa ishte e suksesshme
     */
    public function setSuccess($success) {
        $this->_success = $success;
    }
    
    /**
     * Shton një mesazh në përgjigje
     * @param string $message Mesazhi për t'u shtuar
     */
    public function addMessage($message) {
        $this->_messages[] = $message;
    }
    
    /**
     * Vendos të dhënat e përgjigjes
     * @param array $data Të dhënat për t'u kthyer
     */
    public function setData($data) {
        $this->_data = $data;
    }
    
    /**
     * Vendos të dhënat e kodit të përgjigjeve
     * @param mixed $responseData Të dhënat e kodit të përgjigjeve
     */
    public function setResponseData($responseData) {
        $this->_responseData = $responseData;
    }
    
    /**
     * Dërgon përgjigjen e formatuar JSON
     */
    public function send() {
        header('Content-Type: application/json;charset=utf-8');
        
        // Vendos kodin e statusit HTTP
        if ($this->_httpStatusCode) {
            http_response_code($this->_httpStatusCode);
        }
        
        // Krijo përgjigjen
        $this->_responseData['statusCode'] = $this->_httpStatusCode;
        $this->_responseData['success'] = $this->_success;
        
        // Shto mesazhet nëse ka
        if (!empty($this->_messages)) {
            $this->_responseData['messages'] = $this->_messages;
        }
        
        // Shto të dhënat nëse ka
        if (!empty($this->_data)) {
            $this->_responseData['data'] = $this->_data;
        }
        
        // Konverto në JSON dhe dërgo
        echo json_encode($this->_responseData, JSON_UNESCAPED_UNICODE);
        exit;
    }
}