<?php
// checkout_session.php
require 'vendor/autoload.php';

\Stripe\Stripe::setApiKey(getenv('STRIPE_SECRET_KEY') ?: 'sk_test_xxx');

$session = \Stripe\Checkout\Session::create([
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
