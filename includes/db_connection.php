<?php
/**
 * Lidhja me bazën e të dhënave
 * 
 * Ky file përmban konfigurimin dhe lidhjen me databazën MySQL për sistemin Noteria
 */

// Parametrat e lidhjes me bazën e të dhënave
$db_host = 'localhost';  // Zakonisht 'localhost' për zhvillim lokal me XAMPP
$db_name = 'noteria';    // Emri i bazës së të dhënave
$db_username = 'root';   // Përdoruesi i paracaktuar i XAMPP
$db_password = '';       // Fjalëkalimi i paracaktuar i XAMPP (bosh)

try {
    // Krijimi i lidhjes PDO
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_username, $db_password);
    
    // Vendosja e opsioneve të PDO për trajtimin e erroreve
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Vendosja e opsionit për të kthyer rreshtat si asoc array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Opsional: Vendosja e opsionit për të parandaluar execute() që të përgatisë deklarata me të njëjtin emër
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
} catch (PDOException $e) {
    // Në rast gabimi, shfaq një mesazh të përgjithshëm dhe regjistro gabimin aktual
    die("Gabim në lidhjen me bazën e të dhënave: " . $e->getMessage());
}