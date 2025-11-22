<?php
require_once 'vendor/autoload.php';
require_once 'EuropeanPaymentVerifier.php';

header('Content-Type: application/json');

$stripe_secret_key = getenv('STRIPE_SECRET_KEY') ?: 'sk_test_xxx';
$stripeVerifier = new EuropeanPaymentVerifier($stripe_secret_key);

// Merr të dhënat nga AJAX
$data = json_decode(file_get_contents('php://input'), true);
$amount = $data['amount'] ?? 0;
$email = $data['email'] ?? '';
$metadata = [
    'office_name' => $data['office_name'] ?? '',
    'city' => $data['city'] ?? '',
    'email' => $email,
    'transaction_id' => $data['transaction_id'] ?? ''
];

$client_secret = $stripeVerifier->createPaymentIntent($amount, 'eur', $metadata);

echo json_encode(['clientSecret' => $client_secret]);
exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Pagesë Profesionale me Stripe</title>
  <script src="https://js.stripe.com/v3/"></script>
  <style>
    body { font-family: 'Segoe UI', sans-serif; background: #f4f6fa; }
    .container { max-width: 400px; margin: 60px auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 24px #0001; padding: 32px; }
    .title { font-size: 1.5rem; font-weight: 600; margin-bottom: 24px; color: #2d3748; }
    .stripe-form { margin-top: 16px; }
    #card-element { background: #f7fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; }
    #submit { background: #635bff; color: #fff; border: none; border-radius: 8px; padding: 12px 0; width: 100%; font-size: 1rem; font-weight: 600; margin-top: 20px; cursor: pointer; }
    #submit:disabled { background: #a0aec0; }
    .result-message { margin-top: 20px; color: #38a169; font-weight: 500; }
    .error-message { margin-top: 20px; color: #e53e3e; font-weight: 500; }
  </style>
</head>
<body>
  <div class="container">
    <div class="title">Pagesë me Kartelë (Stripe)</div>
    <form id="payment-form" class="stripe-form">
      <div id="card-element"></div>
      <button id="submit">Paguaj</button>
      <div id="card-errors" class="error-message"></div>
      <div id="result-message" class="result-message"></div>
    </form>
  </div>
  <script>
    // 1. Merr clientSecret nga backend
    async function getClientSecret() {
      const response = await fetch('create_payment_intent_backend.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          amount: 149, // shembull 149 EUR
          email: 'klient@example.com',
          office_name: 'Noteria Prishtina',
          city: 'Prishtinë',
          transaction_id: 'TXN_20250923_123456_abcdef12'
        })
      });
      const data = await response.json();
      return data.clientSecret;
    }

    (async () => {
      const stripe = Stripe('pk_test_xxx'); // vendos public key tënd
      const elements = stripe.elements();
      const card = elements.create('card', { style: { base: { fontSize: '16px' } } });
      card.mount('#card-element');

      const form = document.getElementById('payment-form');
      const submitBtn = document.getElementById('submit');
      const errorDiv = document.getElementById('card-errors');
      const resultDiv = document.getElementById('result-message');

      const clientSecret = await getClientSecret();

      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        submitBtn.disabled = true;
        errorDiv.textContent = '';
        resultDiv.textContent = '';

        const { paymentIntent, error } = await stripe.confirmCardPayment(clientSecret, {
          payment_method: { card }
        });

        if (error) {
          errorDiv.textContent = error.message;
          submitBtn.disabled = false;
        } else if (paymentIntent && paymentIntent.status === 'succeeded') {
          resultDiv.textContent = 'Pagesa u krye me sukses! ✅';
        }
      });
    })();
  </script>
</body>
</html>