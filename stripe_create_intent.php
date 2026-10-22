<?php
// Krijon PaymentIntent në Stripe për pagesën e planit gjatë regjistrimit të zyrës.
// Shuma merret nga serveri (sesioni), kurrë nga klienti.
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/env_loader.php';

function stripe_fail($code, $message) {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    stripe_fail(405, 'Metodë e palejuar.');
}

$secret = getenv('STRIPE_SECRET_KEY');
if (!$secret) {
    stripe_fail(500, 'Pagesa me kartë nuk është e konfiguruar.');
}

$input = json_decode(file_get_contents('php://input'), true);
$abonimId = intval($input['abonim_id'] ?? 0);
$prices = $_SESSION['stripe_plan_prices'] ?? [];
if ($abonimId <= 0 || !isset($prices[$abonimId])) {
    stripe_fail(400, 'Plani i zgjedhur nuk është valid.');
}

$amount = (int) round($prices[$abonimId] * 100);
if ($amount < 1000) {
    stripe_fail(400, 'Shuma nuk është valide.');
}

try {
    \Stripe\Stripe::setApiKey($secret);
    $intent = \Stripe\PaymentIntent::create([
        'amount' => $amount,
        'currency' => 'eur',
        'payment_method_types' => ['card'],
        'description' => 'Noteria - Regjistrim zyre (plani #' . $abonimId . ')',
        'metadata' => ['abonim_id' => $abonimId, 'source' => 'zyrat_register'],
    ]);
    $_SESSION['stripe_pi'] = ['id' => $intent->id, 'amount' => $amount, 'abonim_id' => $abonimId];
    echo json_encode(['client_secret' => $intent->client_secret, 'id' => $intent->id]);
} catch (\Throwable $e) {
    error_log('Stripe intent error: ' . $e->getMessage());
    stripe_fail(502, 'Nuk u krijua pagesa. Provoni përsëri.');
}
