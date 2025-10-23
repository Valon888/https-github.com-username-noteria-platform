<?php
// filepath: c:\xampp\htdocs\noteria\db_connection.php
$conn = new mysqli("localhost", "root", "", "noteria");
if ($conn->connect_error) {
    die("Lidhja me databazën dështoi: " . $conn->connect_error);
}
?>