<?php
declare(strict_types=1);

/**
 * EuropeanPaymentVerifier.php
 *
 * Stripe-based fast and accurate payment verification for Europe
 *
 * @author  Your Name <your@email.com>
 * @license MIT
 * @version 1.1
 */

namespace Noteria\Payments;

use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;

/**
 * Class EuropeanPaymentVerifier
 *
 * Provides methods to create and verify Stripe payments in a professional, robust manner.
 */
class EuropeanPaymentVerifier
{
    /**
     * @var StripeClient
     */
    private StripeClient $stripe;

    /**
     * EuropeanPaymentVerifier constructor.
     *
     * @param StripeClient $stripeClient  Injected StripeClient instance for better testability.
     */
    public function __construct(StripeClient $stripeClient)
    {
        $this->stripe = $stripeClient;
    }

    /**
     * Create a PaymentIntent and return the client secret.
     *
     * @param float $amountEur  Amount in EUR (not cents)
     * @param string $currency  Currency code (default 'eur')
     * @param array $metadata   Optional metadata
     * @return string           Client secret for frontend
     * @throws ApiErrorException
     */
    public function createPaymentIntent(float $amountEur, string $currency = 'eur', array $metadata = []): string
    {
        $amountCents = (int) round($amountEur * 100);
        $intent = $this->stripe->paymentIntents->create([
            'amount' => $amountCents,
            'currency' => $currency,
            'payment_method_types' => ['card'],
            'metadata' => $metadata,
        ]);
        return $intent->client_secret;
    }

    /**
     * Verify payment status by PaymentIntent ID.
     *
     * @param string $paymentIntentId
     * @return array{
     *     success: bool,
     *     amount?: float,
     *     currency?: string,
     *     payer_email?: string|null,
     *     stripe_receipt_url?: string|null,
     *     status?: string,
     *     raw: object
     * }
     * @throws ApiErrorException
     */
    public function verifyPayment(string $paymentIntentId): array
    {
        $intent = $this->stripe->paymentIntents->retrieve($paymentIntentId, []);
        if ($intent->status === 'succeeded') {
            $charge = $intent->charges->data[0] ?? null;
            return [
                'success' => true,
                'amount' => $intent->amount / 100,
                'currency' => $intent->currency,
                'payer_email' => $charge->billing_details->email ?? null,
                'stripe_receipt_url' => $charge->receipt_url ?? null,
                'raw' => $intent,
            ];
        }
        return [
            'success' => false,
            'status' => $intent->status,
            'raw' => $intent,
        ];
    }
}
