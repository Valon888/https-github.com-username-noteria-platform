<?php 
session_start();
// Set auth_test flag to bypass authentication
$_SESSION['auth_test'] = true;
// Include statistikat.php and capture output
ob_start();
include 'statistikat.php';
ob_end_clean();
echo 'Page loaded without fatal errors.';
?>