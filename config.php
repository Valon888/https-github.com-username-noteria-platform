<?php
// filepath: c:\xampp\htdocs\noteria\book.php
// Konfigurimi i raportimit të gabimeve
ini_set('display_errors', 1); // Shfaq gabime në faqe
ini_set('log_errors', 1);     // Log gabimet në server
ini_set('error_log', __DIR__ . '/error.log'); // Ruaj gabimet në error.log në këtë folder

require_once 'config.php';

$host = 'localhost';
$db   = 'Noteria';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
// Mos vendosni asnjë HTML, asnjë karakter jashtë tagut PHP!

try {
    // Kodi që mund të shkaktojë gabim
} catch (Exception $e) {
    error_log($e->getMessage()); // Log gabimin në server
    echo "<div style='color:#d32f2f; background:#ffeaea; border-radius:8px; padding:10px; max-width:420px; margin:40px auto; font-family:Montserrat;'>Ndodhi një gabim. Ju lutemi provoni përsëri ose kontaktoni administratorin.</div>";
}
