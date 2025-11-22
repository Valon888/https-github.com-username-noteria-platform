<?php
// create_sepa_subscription_pm.php
// Krijon një Stripe customer dhe subscription me SEPA Direct Debit duke përdorur payment_method_id nga SetupIntent
require_once 'vendor/autoload.php';

$stripeSecretKey = getenv('STRIPE_SECRET_KEY') ?: 'sk_test_xxx'; // vendosni çelësin tuaj
\Stripe\Stripe::setApiKey($stripeSecretKey);

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? null;
$name = $data['name'] ?? null;
$payment_method_id = $data['payment_method_id'] ?? null;

if (!$email || !$name || !$payment_method_id) {
    echo json_encode(['error' => 'Të dhënat janë të paplota.']);
    exit;
}

try {
    // 1. Krijo customer
    $customer = \Stripe\Customer::create([
        'email' => $email,
        'name' => $name,
        'payment_method' => $payment_method_id,
        'invoice_settings' => [
            'default_payment_method' => $payment_method_id
        ]
    ]);

    // 2. Krijo subscription (150 EUR/muaj, plan ID duhet të jetë krijuar në Stripe dashboard)
    $subscription = \Stripe\Subscription::create([
        'customer' => $customer->id,
        'items' => [[
            'price' => 'price_1Nxxxxxxx', // Zëvendëso me ID-në e planit tënd Stripe
        ]],
        'default_payment_method' => $payment_method_id,
        'expand' => ['latest_invoice.payment_intent']
    ]);

    echo json_encode([
        'success' => true,
        'subscription_id' => $subscription->id,
        'customer_id' => $customer->id
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
