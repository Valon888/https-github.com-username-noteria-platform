<?php
// create_sepa_subscription.php
// Krijon një Stripe customer dhe subscription me SEPA Direct Debit


require_once 'vendor/autoload.php';

$stripeSecretKey = getenv('STRIPE_SECRET_KEY') ?: 'sk_test_xxx'; // vendosni çelësin tuaj
\Stripe\Stripe::setApiKey($stripeSecretKey);

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? null;
$name = $data['name'] ?? null;
$iban = $data['iban'] ?? null;

if (!$email || !$name || !$iban) {
    echo json_encode(['error' => 'Të dhënat janë të paplota.']);
    exit;
}

try {
    // 1. Krijo customer
    $customer = \Stripe\Customer::create([
        'email' => $email,
        'name' => $name
    ]);

    // 2. Krijo payment method SEPA
    $paymentMethod = \Stripe\PaymentMethod::create([
        'type' => 'sepa_debit',
        'sepa_debit' => ['iban' => $iban],
        'billing_details' => [
            'name' => $name,
            'email' => $email
        ]
    ]);

    // 3. Attach payment method te customer
    $paymentMethod->attach(['customer' => $customer->id]);

    // 4. Vendos default payment method
    \Stripe\Customer::update($customer->id, [
        'invoice_settings' => [
            'default_payment_method' => $paymentMethod->id
        ]
    ]);

    // 5. Krijo subscription (150 EUR/muaj, plan ID duhet të jetë krijuar në Stripe dashboard)
    $subscription = \Stripe\Subscription::create([
        'customer' => $customer->id,
        'items' => [[
            'price' => 'price_1Nxxxxxxx', // Zëvendëso me ID-në e planit tënd Stripe
        ]],
        'default_payment_method' => $paymentMethod->id,
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
