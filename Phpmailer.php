<?php
// Shto këto rreshta në vend të funksionit mail()
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;


$mail->isSMTP();
$mail->Host = 'smtp.gmail.com';
$mail->SMTPAuth = true;
$mail->Username = 'emaili.yt@gmail.com';
$mail->Password = 'fjalekalimi';
$mail->SMTPSecure = 'tls';
$mail->Port = 587;

$mail->setFrom('emaili.yt@gmail.com', 'Noteria');
$mail->addAddress($email);
$mail->Subject = 'Rivendosja e Fjalëkalimit';
$mail->Body = "Fjalëkalimi juaj i ri është: $new_password";

if ($mail->send()) {
    $success = "Fjalëkalimi i ri është dërguar në emailin tuaj.";
} else {
    $error = "Dërgimi i emailit dështoi: " . $mail->ErrorInfo;
}
