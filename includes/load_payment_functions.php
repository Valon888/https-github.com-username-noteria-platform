<?php
// Include payment_functions.php in the index file so it can be accessible throughout the application
require_once 'includes/payment_functions.php';

// Redirect to the appropriate page based on session
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
} else {
    header('Location: login.php');
    exit;
}