<?php
// Ky script merr ID e rezervimit dhe gjeneron PDF të faturës për të
require_once __DIR__ . '/vendor/autoload.php'; // Kërkon mPDF
require_once __DIR__ . '/db_connect.php'; // Lidhja me databazën

if (!isset($_GET['reservation_id'])) {
    die('Rezervimi nuk u gjet.');
}
$reservation_id = intval($_GET['reservation_id']);

// Merr të dhënat nga databaza
$stmt = $pdo->prepare('SELECT r.*, u.name as user_name, u.surname as user_surname, u.address as user_address, n.name as notary_name, n.address as notary_address, n.city as notary_city FROM reservations r JOIN users u ON r.user_id = u.id JOIN notary_offices n ON r.notary_office_id = n.id WHERE r.id = ?');
$stmt->execute([$reservation_id]);
$res = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$res) die('Rezervimi nuk ekziston.');

// Gjenero HTML për faturën
$html = '<h2 style="color:#244d96;">Fatura e Shërbimit Noterial</h2>';
$html .= '<hr>';
$html .= '<b>Nr. Faturës:</b> ' . htmlspecialchars('INV-' . $reservation_id) . '<br>';
$html .= '<b>Data:</b> ' . date('d.m.Y H:i') . '<br>';
$html .= '<b>Përdoruesi:</b> ' . htmlspecialchars($res['user_name'] . ' ' . $res['user_surname']) . '<br>';
$html .= '<b>Vendbanimi:</b> ' . htmlspecialchars($res['user_address']) . '<br>';
$html .= '<b>Zyra Noteriale:</b> ' . htmlspecialchars($res['notary_name']) . '<br>';
$html .= '<b>Adresa e Zyrës:</b> ' . htmlspecialchars($res['notary_address']) . ', ' . htmlspecialchars($res['notary_city']) . '<br>';
$html .= '<b>Shërbimi:</b> ' . htmlspecialchars($res['service']) . '<br>';
$html .= '<b>Data e Rezervimit:</b> ' . htmlspecialchars($res['reservation_date']) . '<br>';
$html .= '<b>Metoda e Pagesës:</b> ' . htmlspecialchars($res['payment_method'] ?: 'Nuk është paguar online') . '<br>';
$html .= '<b>Shuma:</b> ' . number_format($res['amount'],2) . ' €<br>';
$html .= '<hr>';
$html .= '<i>Fatura është gjeneruar automatikisht nga sistemi.</i>';

// Gjenero PDF
$mpdf = new \Mpdf\Mpdf();
$mpdf->WriteHTML($html);
$mpdf->Output('fatura_'.$reservation_id.'.pdf', 'I'); // Shfaq PDF në browser
