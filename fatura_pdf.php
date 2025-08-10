<?php
require_once __DIR__ . '/fpdf.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../confidb.php';

if (!class_exists('FPDF')) {
    die('FPDF library not found. Please check the path to fpdf.php.');
}

$fatura_id = isset($_GET['fatura_id']) && is_numeric($_GET['fatura_id']) ? (int) $_GET['fatura_id'] : 0;

$stmt = $pdo->prepare("SELECT f.*, r.service, u.emri AS klient_emri, u.mbiemri AS klient_mbiemri, z.emri AS zyra_emri, z.nr_regjistrimit, z.qyteti
    FROM fatura f
    JOIN reservations r ON f.reservation_id = r.id
    JOIN users u ON r.user_id = u.id
    JOIN zyrat z ON f.zyra_id = z.id
    WHERE f.id = ?");
$stmt->execute([$fatura_id]);
$f = $stmt->fetch();

if (!$f) {
    die('Fatura nuk u gjet.');
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);