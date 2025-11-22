<?php
/**
 * Hyrje e drejtpërdrejtë për testimin e faqes së cilësimeve
 */
session_start();
session_regenerate_id(true);

// Pastro çdo sesion ekzistues
$_SESSION = array();

// Vendos sesionin për admin testimi
$_SESSION["admin_id"] = 1;
$_SESSION["emri"] = "Admin";
$_SESSION["mbiemri"] = "Test";
$_SESSION["email"] = "admin@noteria.al";
$_SESSION["roli"] = "admin";
$_SESSION["auth_test"] = true; // Flag special për të evituar redirect loop

// Ridrejto tek faqja e cilësimeve
header("Location: admin_settings_view.php");
exit();
?>