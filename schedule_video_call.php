<?php
// Evito probleme me headers
ob_start();
session_start();
include 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle immediate video call
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['immediate']) && $_POST['immediate'] === 'true') {
    if (!isset($_POST['notary_id']) || empty($_POST['notary_id'])) {
        echo json_encode(['success' => false, 'message' => 'Ju lutemi zgjidhni një noter.']);
        exit();
    }

    $notary_id = mysqli_real_escape_string($conn, $_POST['notary_id']);
    $room_id = mysqli_real_escape_string($conn, $_POST['room_id']);
    
    // Insert into video_calls table with current time
    $sql = "INSERT INTO video_calls (user_id, notary_id, call_datetime, room_id, subject, status) 
            VALUES ('$user_id', '$notary_id', NOW(), '$room_id', 'Video thirrje e menjëhershme', 'in-progress')";
    
    if ($conn->query($sql) === TRUE) {
        echo json_encode(['success' => true, 'room_id' => $room_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gabim: ' . $conn->error]);
    }
    exit();
}

// Handle scheduled video call
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate input
    if (!isset($_POST['notary_id']) || empty($_POST['notary_id'])) {
        $error = "Ju lutemi zgjidhni një noter.";
    } elseif (!isset($_POST['call_date']) || empty($_POST['call_date'])) {
        $error = "Ju lutemi zgjidhni një datë.";
    } elseif (!isset($_POST['call_time']) || empty($_POST['call_time'])) {
        $error = "Ju lutemi zgjidhni një orë.";
    } else {
        $notary_id = mysqli_real_escape_string($conn, $_POST['notary_id']);
        $call_date = mysqli_real_escape_string($conn, $_POST['call_date']);
        $call_time = mysqli_real_escape_string($conn, $_POST['call_time']);
        $call_datetime = date("Y-m-d H:i:s", strtotime("$call_date $call_time"));
        $call_subject = mysqli_real_escape_string($conn, $_POST['call_subject'] ?? 'Video thirrje e planifikuar');
        
        // Generate a unique room ID
        $room_id = uniqid('room_');
        
        // Insert into video_calls table
        $sql = "INSERT INTO video_calls (user_id, notary_id, call_datetime, room_id, subject, status) 
                VALUES ('$user_id', '$notary_id', '$call_datetime', '$room_id', '$call_subject', 'scheduled')";
        if ($conn->query($sql) === TRUE) {
            $video_call_id = $conn->insert_id;
            // Redirect to payment form for this video call
            header("Location: paysera_pay.php?video_call_id=" . urlencode($video_call_id));
            exit();
        } else {
            $error = "Gabim: " . $conn->error;
            // Redirect back to dashboard with error
            header("Location: dashboard.php?error=" . urlencode($error));
            exit();
        }
    }
}

// If no POST data, redirect to dashboard
header("Location: dashboard.php");
exit();
?>