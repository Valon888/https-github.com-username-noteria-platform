<?php
session_start();
header("Content-Type: application/json");

// Përcakto se çfarë lloj kërkese është
$isGet = $_SERVER["REQUEST_METHOD"] === "GET";
$isPost = $_SERVER["REQUEST_METHOD"] === "POST";

// Lexo CSRF token nga header-at ose query params për GET
$csrf_token = null;
if ($isPost) {
    $csrf_token = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? null;
} elseif ($isGet) {
    $csrf_token = $_GET["csrf_token"] ?? null;
}

// Kontrollo CSRF token
if (!$csrf_token || $csrf_token !== $_SESSION["csrf_token"]) {
    echo json_encode([
        "success" => false, 
        "message" => "Invalid CSRF token",
        "method" => $_SERVER["REQUEST_METHOD"]
    ]);
    exit();
}

// Gjenero captcha të ri
$_SESSION["captcha"] = rand(10000, 99999);
$_SESSION["captcha_time"] = time();

echo json_encode([
    "success" => true, 
    "captcha" => $_SESSION["captcha"],
    "timestamp" => $_SESSION["captcha_time"]
]);
?>