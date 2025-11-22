<?php
// teb_payment_callback.php
// Përpunon përgjigjen automatike nga TEB Bank dhe ruan statusin në sesion
session_start();

// Kontrollo nëse është sukses apo dështim sipas parametrave që dërgon TEB
$isSuccess = false;
if (isset($_POST['Response']) && strtolower($_POST['Response']) === 'approved') {
    $isSuccess = true;
}

if ($isSuccess) {
    // Ruaj të dhënat e pagesës në sesion për regjistrim
    $_SESSION['teb_payment_success'] = [
        'emri' => $_POST['BillToName'] ?? '',
        'email' => $_POST['email'] ?? '',
        'telefoni' => $_POST['telefoni'] ?? '',
        'adresa' => $_POST['adresa'] ?? '',
        'qyteti' => $_POST['qyteti'] ?? '',
        'kodi_postar' => $_POST['kodi_postar'] ?? '',
        'shuma' => $_POST['amount'] ?? '',
        'transaction_id' => $_POST['OrderId'] ?? '',
        'teb_response' => $_POST
    ];
    header('Location: zyrat_register.php?teb=success');
    exit();
} else {
    header('Location: teb_payment_fail.php');
    exit();
}
