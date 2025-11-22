<?php
// Ky file përmban të dhënat e lidhjes me bazën e të dhënave
// dhe krijon një objekt PDO për përdorim në aplikacion

$host = 'localhost';
$dbname = 'noteria';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // Ruaj gabimet në log file
    error_log('Database connection error: ' . $e->getMessage());
    die('Gabim në lidhjen me bazën e të dhënave. Ju lutemi provoni më vonë.');
}
?>