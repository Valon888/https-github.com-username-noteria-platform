<?php
/**
 * Skript për gjenerimin e një token-i testimi
 * Kjo do të përdoret për të simuluar një përdorues të autentikuar
 * për testimin e API-t
 */

// Lidhja me skedarin e nevojshëm
require_once 'config/database.php';
require_once 'utils/TokenAuth.php';

// Inicializimi i databazës
$database = new Database();
$db = $database->getConnection();

// Inicializimi i autentikimit të tokenave
$auth = new TokenAuth($db);

// Krijimi i një token-i testimi për një përdorues me ID 1
// Admin për testim
$userId = 1;
$token = $auth->generateToken($userId);

echo "Token-i i gjeneruar për përdoruesin me ID $userId:\n";
echo $token . "\n\n";
echo "Për të testuar API-n, përdorni këtë token në header-in Authorization:\n";
echo "Authorization: Bearer $token\n\n";
echo "Për testimin në browser, mund të përdorni një plugin si ModHeader për të shtuar header-in Authorization.\n";