<?php
/**
 * Platform Commission Helper Functions
 */

$commission_config = require_once __DIR__ . '/commission_config.php';

/**
 * Calculate commission for a given amount
 * @param float $amount Service price (with VAT)
 * @param string $payment_method Payment method key
 * @return array ['commission' => float, 'total' => float, 'rate' => float]
 */
function calculate_commission($amount, $payment_method = 'card') {
    global $commission_config;
    
    $rate = $commission_config[$payment_method]['commission'] ?? 3.5;
    $commission = $amount * ($rate / 100);
    $total = $amount + $commission;
    
    return [
        'commission' => round($commission, 2),
        'total' => round($total, 2),
        'rate' => $rate,
        'payment_method' => $payment_method
    ];
}

/**
 * Get all available payment methods with commissions
 * @return array
 */
function get_payment_methods() {
    global $commission_config;
    return $commission_config;
}

/**
 * Get commission rate for payment method
 * @param string $payment_method
 * @return float
 */
function get_commission_rate($payment_method) {
    global $commission_config;
    return $commission_config[$payment_method]['commission'] ?? 3.5;
}

/**
 * Get payment method name in selected language
 * @param string $payment_method
 * @param string $lang Language code (sq, en, sr)
 * @return string
 */
function get_payment_method_name($payment_method, $lang = 'sq') {
    global $commission_config;
    $key = $lang === 'sq' ? 'name_sq' : 'name_en';
    return $commission_config[$payment_method][$key] ?? $payment_method;
}
