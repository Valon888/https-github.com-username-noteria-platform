<?php
/**
 * Skript për të testuar endpoint-in e detyrave me autentifikim të simuluar
 */

// Përfshirja e konfigurimeve dhe librarive
require_once 'config/database.php';
require_once 'models/Response.php';
require_once 'utils/TokenAuth.php';
require_once 'utils/Logger.php';
require_once 'controllers/DetyreController.php';

// Inicializimi i databazës
$database = new Database();
$db = $database->getConnection();
$response = new Response();
$logger = new Logger($db);

// Simulojmë një përdorues të autentikuar (Admin ID=1)
$_SERVER['AUTHENTICATED_USER_ID'] = 1;

// Krijojmë një instancë të kontrollerit dhe ekzekutojmë metodën getAll
$controller = new DetyreController($db, $response, $logger);

// Thirrja e metodës për të listuar detyrat
$controller->getAll();