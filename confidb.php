<?php 
// filepath: c:\xampp\htdocs\noteria\book.php
// Konfigurimi i raportimit të gabimeve
ini_set('display_errors', 0); // Mos shfaq gabime në faqe
ini_set('log_errors', 1);     // Log gabimet në server
ini_set('error_log', __DIR__ . '/error.log'); // Ruaj gabimet në error.log në këtë folder

session_start();
require_once 'config.php';

$host = 'localhost';
$dbname = 'noteria';
$username = 'root';
$password = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ndodhi një gabim. Ju lutemi provoni më vonë.");
}

// Në vend të shfaqjes së gabimit të detajuar:
try {
    // Kodi që mund të shkaktojë gabim
} catch (Exception $e) {
    error_log($e->getMessage()); // Log gabimin në server
    echo "<div style='color:#d32f2f; background:#ffeaea; border-radius:8px; padding:10px; max-width:420px; margin:40px auto; font-family:Montserrat;'>Ndodhi një gabim. Ju lutemi provoni përsëri ose kontaktoni administratorin.</div>";
}