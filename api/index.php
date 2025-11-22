<?php
/**
 * API për sistemin e menaxhimit të punonjësve të zyrave noteriale
 * 
 * Ky sistem ofron një API të avancuar për menaxhimin e punonjësve, 
 * oraret e tyre, hyrje-daljet, detyrat dhe sistemin e njoftimeve
 */

// Konfigurimi i headers për CORS dhe JSON
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Për kërkesat OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit;
}

// Përfshirja e konfigurimeve dhe librarive
require_once 'config/database.php';
require_once 'models/Response.php';
require_once 'utils/TokenAuth.php';
require_once 'utils/Logger.php';

// Inicializimi i databazës
$database = new Database();
$db = $database->getConnection();
$response = new Response();
$logger = new Logger($db);

// Inicializimi i autentikimit të tokenave
$auth = new TokenAuth($db);

// Kontrolli i autentikimit për kërkesat që nuk janë login/register
$publicEndpoints = [
    '/api/login',
    '/api/register',
    '/api/forgot-password',
    '/api/reset-password',
    '/api/ping'
];

$requestUri = $_SERVER['REQUEST_URI'];
$isPublicEndpoint = false;

foreach ($publicEndpoints as $endpoint) {
    if (strpos($requestUri, $endpoint) !== false) {
        $isPublicEndpoint = true;
        break;
    }
}

// Autentikimi i kërkesave private
if (!$isPublicEndpoint) {
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

    if (!$token) {
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        $response->addMessage("Mungon token-i i autentikimit");
        $response->send();
        exit;
    }

    try {
        $userId = $auth->validateToken($token);
        // Vendosim ID e përdoruesit për ta përdorur më vonë
        $_SERVER['AUTHENTICATED_USER_ID'] = $userId;
    } catch (Exception $ex) {
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        $response->addMessage($ex->getMessage());
        $response->send();
        exit;
    }
}

// Përcaktimi i rutës dhe parametrave
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = '/api';
// Ne po punojme direkt nga rrenja e direktorise, pa /api ne URL
// $endpoint = str_replace($basePath, '', $path);
$endpoint = $path;
$segments = explode('/', trim($endpoint, '/'));
$resource = $segments[0] ?? '';
$id = $segments[1] ?? null;
$subResource = $segments[2] ?? null;

// Merrni metodën e kërkesës HTTP
$method = $_SERVER['REQUEST_METHOD'];

// Lexoni të dhënat nga kërkesa
$data = json_decode(file_get_contents('php://input'), true);

// Definimi i listës së resurseve dhe kontrolluesve
$controllers = [
    'punonjes' => 'PunonjesController',
    'zyra'     => 'ZyraController',
    'hyrje-dalje' => 'HyrjeDaljeController',
    'detyre'   => 'DetyreController',
    'leje'     => 'LejeController',
    'orar'     => 'OrarController',
    'njoftim'  => 'NjoftimController',
    'raport'   => 'RaportController'
];

// Bëni routing bazuar në resursin dhe metodën
try {
    // Kontrollojmë nëse resursi ekziston në sistemin tonë
    if (array_key_exists($resource, $controllers)) {
        $controllerName = $controllers[$resource];
        $controllerFile = "controllers/{$controllerName}.php";
        
        // Verifikojmë nëse skedari i kontrollerit ekziston
        if (file_exists($controllerFile)) {
            require_once $controllerFile;
            $controller = new $controllerName($db, $response, $logger);
            handleResource($controller, $method, $id, $subResource, $data);
        } else {
            // Kontrolluesi nuk është i disponueshëm aktualisht
            $response->setHttpStatusCode(503);
            $response->setSuccess(false);
            $response->addMessage("Shërbimi '{$resource}' aktualisht nuk është i disponueshëm.");
            $response->send();
        }
    } else if ($resource === 'ping') {
        // Endpoint për testim të API
        $response->setHttpStatusCode(200);
        $response->setSuccess(true);
        $response->addMessage("API është duke funksionuar");
        $response->send();
    } else if ($resource === 'login') {
        require_once 'controllers/AuthController.php';
        $controller = new AuthController($db, $response, $logger, $auth);
        $controller->login($data);
    } else if ($resource === 'logout') {
        require_once 'controllers/AuthController.php';
        $controller = new AuthController($db, $response, $logger, $auth);
        $controller->logout();
    } else {
        // Endpoint nuk u gjet
        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->addMessage("Endpoint nuk ekziston");
        $response->send();
    }
} catch (PDOException $ex) {
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("Gabim në databazë: " . $ex->getMessage());
    $response->send();
} catch (Exception $ex) {
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("Gabim: " . $ex->getMessage());
    $response->send();
}

// Funksion për të trajtuar resurset e ndryshme
function handleResource($controller, $method, $id, $subResource, $data) {
    switch ($method) {
        case 'GET':
            if ($id !== null) {
                if ($subResource !== null) {
                    $controller->getSubResource($id, $subResource);
                } else {
                    $controller->getOne($id);
                }
            } else {
                $controller->getAll();
            }
            break;
            
        case 'POST':
            if ($id !== null && $subResource !== null) {
                $controller->createSubResource($id, $subResource, $data);
            } else {
                $controller->create($data);
            }
            break;
            
        case 'PUT':
            if ($id !== null) {
                if ($subResource !== null) {
                    $controller->updateSubResource($id, $subResource, $data);
                } else {
                    $controller->update($id, $data);
                }
            } else {
                throw new Exception("ID mungon për kërkesën PUT");
            }
            break;
            
        case 'DELETE':
            if ($id !== null) {
                if ($subResource !== null) {
                    $controller->deleteSubResource($id, $subResource);
                } else {
                    $controller->delete($id);
                }
            } else {
                throw new Exception("ID mungon për kërkesën DELETE");
            }
            break;
            
        default:
            throw new Exception("Metoda HTTP nuk mbështetet");
    }
}