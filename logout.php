<?php
// filepath: c:\xampp\htdocs\noteria\book.php
// Konfigurimi i raportimit të gabimeve
ini_set('display_errors', 0); // Mos shfaq gabime në faqe
ini_set('log_errors', 1);     // Log gabimet në server
ini_set('error_log', __DIR__ . '/error.log'); // Ruaj gabimet në error.log në këtë folder

session_start();
require_once 'config.php';

// ...existing code...

try {
    // Kodi që mund të shkaktojë gabim
} catch (Exception $e) {
    error_log($e->getMessage()); // Log gabimin në server
    echo "<div style='color:#d32f2f; background:#ffeaea; border-radius:8px; padding:10px; max-width:420px; margin:40px auto; font-family:Montserrat;'>Ndodhi një gabim. Ju lutemi provoni përsëri ose kontaktoni administratorin.</div>";
}
?><?php
// This code logs out the user by destroying the session and redirecting to the login page.
session_start();
session_unset();
session_destroy();
header("Location: login.php");
exit();
?>
