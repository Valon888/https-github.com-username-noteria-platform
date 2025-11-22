<?php
/**
 * Frigate NVR API Client for PHP
 * 
 * This class provides methods to interact with a Frigate NVR server
 * for accessing camera streams, events, recordings and other security features.
 */
class FrigateAPI {
    private $baseUrl;
    private $timeout;
    
    /**
     * Constructor
     * 
     * @param string $baseUrl The base URL of your Frigate NVR server (e.g. 'http://192.168.1.100:5000')
     * @param int $timeout Request timeout in seconds
     */
    public function __construct($baseUrl, $timeout = 10) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
    }
    
    /**
     * Get general Frigate config and stats
     * 
     * @return array Frigate configuration and statistics
     */
    public function getConfig() {
        return $this->makeRequest('GET', '/api/config');
    }
    
    /**
     * Get list of all cameras
     * 
     * @return array List of cameras and their details
     */
    public function getCameras() {
        return $this->makeRequest('GET', '/api/config/cameras');
    }
    
    /**
     * Get specific camera details
     * 
     * @param string $cameraName Name of the camera
     * @return array Camera details
     */
    public function getCamera($cameraName) {
        return $this->makeRequest('GET', "/api/config/cameras/{$cameraName}");
    }
    
    /**
     * Get recent events (detected objects)
     * 
     * @param array $params Optional query parameters
     *        - limit: Maximum number of events to return
     *        - before: Events before this timestamp
     *        - after: Events after this timestamp
     *        - cameras: Comma-separated list of cameras
     *        - labels: Comma-separated list of object labels
     *        - zones: Comma-separated list of zones
     * @return array List of events
     */
    public function getEvents($params = []) {
        return $this->makeRequest('GET', '/api/events', $params);
    }
    
    /**
     * Get RTSP URL for a camera
     * 
     * @param string $cameraName Name of the camera
     * @return string RTSP URL for direct streaming
     */
    public function getRtspUrl($cameraName) {
        // Frigate restreams cameras via RTSP on port 8554 by default
        // Format is rtsp://frigate-host:8554/[camera_name]
        $host = parse_url($this->baseUrl, PHP_URL_HOST);
        return "rtsp://{$host}:8554/{$cameraName}";
    }
    
    /**
     * Get URL for MJPEG stream of a camera
     * 
     * @param string $cameraName Name of the camera
     * @return string URL for MJPEG stream
     */
    public function getMjpegUrl($cameraName) {
        return "{$this->baseUrl}/api/{$cameraName}/mjpeg";
    }
    
    /**
     * Get a snapshot from a camera (JPEG image)
     * 
     * @param string $cameraName Name of the camera
     * @return string Binary JPEG image data
     */
    public function getSnapshot($cameraName) {
        return $this->makeRequest('GET', "/api/{$cameraName}/snapshot", [], false);
    }
    
    /**
     * Save a snapshot from a camera to a file
     * 
     * @param string $cameraName Name of the camera
     * @param string $filePath Path to save the image
     * @return bool Success or failure
     */
    public function saveSnapshot($cameraName, $filePath) {
        $imageData = $this->getSnapshot($cameraName);
        if ($imageData) {
            return file_put_contents($filePath, $imageData) !== false;
        }
        return false;
    }
    
    /**
     * Make HTTP request to Frigate API
     * 
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @param bool $jsonDecode Whether to decode JSON response
     * @return mixed Response data
     */
    private function makeRequest($method, $endpoint, $params = [], $jsonDecode = true) {
        $url = $this->baseUrl . $endpoint;
        
        // Add query parameters if needed
        if (!empty($params) && $method === 'GET') {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        
        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if (!empty($params)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            }
        }
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Frigate API request failed: {$error}");
        }
        
        if ($httpCode >= 400) {
            throw new Exception("Frigate API error: HTTP code {$httpCode}, Response: {$response}");
        }
        
        return $jsonDecode ? json_decode($response, true) : $response;
    }
}
?>