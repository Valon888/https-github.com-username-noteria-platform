<?php
// create_sepa_setup_intent.php
// Kthen një Stripe SetupIntent client_secret për SEPA Direct Debit
require_once __DIR__ . '/vendor/autoload.php';

use Stripe\Stripe;
use Stripe\Checkout\Session;

$stripeSecretKey = getenv('STRIPE_SECRET_KEY') ?: 'sk_test_xxx';
Stripe::setApiKey($stripeSecretKey);
try {
    $session = Session::create([
        'payment_method_types' => ['sepa_debit'],
        'mode' => 'payment',
        'line_items' => [[
            'price_data' => [
                'currency' => 'eur',
                'product_data' => [
                    'name' => 'Shërbimi Noterial',
                ],
                'unit_amount' => 1000, // 10.00 EUR
            ],
            'quantity' => 1,
        ]],
        'customer_email' => 'klient@email.com', // opsionale
        'success_url' => 'https://example.com/success',
        'cancel_url' => 'https://example.com/cancel',
    ]);
    echo json_encode(['url' => $session->url]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
