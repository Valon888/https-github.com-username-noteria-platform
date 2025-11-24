<?php
require 'confidb.php';
require 'pricing_helper.php';

echo "=== UPDATED PRICING TIERS ===\n\n";
$plans = getPricingPlans($pdo);
foreach ($plans as $plan) {
    echo "✓ " . $plan['name'] . " - €" . number_format($plan['price'], 2) . "/muaj\n";
    echo "  " . $plan['description'] . "\n";
    echo "  Features: " . $plan['features'] . "\n\n";
}
