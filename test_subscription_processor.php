<?php
// test_subscription_processor.php - Një skript për të testuar procesimin e abonimeve

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Simuloj parametrin test=true dhe token
$_GET['test'] = 'true';
$_GET['token'] = 'YXV0b21hdGljX3N1YnNjcmlwdGlvbl90b2tlbg==';

// Përfshij skriptin e procesimit të abonimeve
require_once 'subscription_processor.php';