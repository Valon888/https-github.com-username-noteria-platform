<?php
// filepath: c:\xampp\htdocs\noteria\db_connection.php

// Krijojmë lidhjen me databazën
$conn = new mysqli("localhost", "root", "", "noteria");
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Lidhja me databazën dështoi: " . $conn->connect_error);
}

// Funksion alternativ për lidhje me databazën 
// Kjo është si backup në rast se funksioni nga paysera_pay.php nuk është i disponueshëm
if (!function_exists('connectToDatabase')) {
    function connectToDatabase() {
        $conn = new mysqli("localhost", "root", "", "noteria");
        if ($conn->connect_error) {
            error_log("Database connection failed inside connectToDatabase function: " . $conn->connect_error);
            throw new Exception('Lidhja me databazën dështoi: ' . $conn->connect_error);
        }
        return $conn;
    }
}
?>