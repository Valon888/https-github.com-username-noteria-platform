<?php
require 'confidb.php';
require 'pricing_helper.php';

echo "=== PRICING SYSTEM TEST ===\n\n";

// Create test advertiser if not exists
echo "0. Setting up test advertiser...\n";
$stmt = $pdo->prepare("SELECT id FROM advertisers WHERE email = ?");
$stmt->execute(['testadvertiser@noteria.al']);
$advertiser = $stmt->fetch();

if (!$advertiser) {
    $stmt = $pdo->prepare("INSERT INTO advertisers (company_name, email, website, subscription_status) VALUES (?, ?, ?, ?)");
    $stmt->execute(['Test Company', 'testadvertiser@noteria.al', 'https://test.com', 'pending']);
    $advertiser_id = $pdo->lastInsertId();
    echo "  ✓ Test advertiser created: ID " . $advertiser_id . "\n";
} else {
    $advertiser_id = $advertiser['id'];
    echo "  ✓ Test advertiser exists: ID " . $advertiser_id . "\n";
}

// Test getting plans
echo "\n1. Getting pricing plans...\n";
$plans = getPricingPlans($pdo);
foreach ($plans as $plan) {
    echo "  - " . $plan['name'] . ": €" . $plan['price'] . "/month\n";
}

echo "\n2. Creating test subscription...\n";
// Test subscription creation
$plan_id = 2; // Professional

$sub_id = createSubscription($pdo, $advertiser_id, $plan_id);
if ($sub_id) {
    echo "  ✓ Subscription created: ID " . $sub_id . "\n";
}

echo "\n3. Checking subscription status...\n";
$status = getSubscriptionStatus($pdo, $advertiser_id);
if ($status) {
    echo "  ✓ Status: " . $status['status'] . "\n";
    echo "  ✓ Plan: " . $status['plan_name'] . "\n";
    echo "  ✓ Price: €" . $status['monthly_price'] . "/month\n";
}

echo "\n4. Checking if subscription is active...\n";
$is_active = isSubscriptionActive($pdo, $advertiser_id);
echo "  ✓ Is Active: " . ($is_active ? "Yes" : "No (not paid yet)") . "\n";

echo "\n5. Marking subscription as paid...\n";
if ($sub_id && markSubscriptionAsPaid($pdo, $sub_id)) {
    echo "  ✓ Subscription marked as paid\n";
    
    echo "\n6. Re-checking if subscription is active...\n";
    $is_active = isSubscriptionActive($pdo, $advertiser_id);
    echo "  ✓ Is Active: " . ($is_active ? "Yes" : "No") . "\n";
}

echo "\n7. Testing invoice generation...\n";
$invoice = generateInvoice($pdo, $advertiser_id);
if ($invoice) {
    echo "  ✓ Invoice #: " . $invoice['invoice_number'] . "\n";
    echo "  ✓ Amount: €" . $invoice['amount'] . "\n";
    echo "  ✓ Status: " . $invoice['status'] . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>
