<?php
require 'confidb.php';

echo "=== Adding €100 Entry-Level Pricing Tier ===\n\n";

// Check if entry-level plan exists
$stmt = $pdo->prepare("SELECT id FROM pricing_plans WHERE price = 100");
$stmt->execute();

if (!$stmt->fetch()) {
    echo "Inserting Entry-Level Plan (€100/month)...\n";
    
    $stmt = $pdo->prepare("
        INSERT INTO pricing_plans (name, description, price, billing_period, features, active, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $result = $stmt->execute([
        'Entry-Level',
        'Për bizneset e vogla në fillimet e tyre',
        100,
        'monthly',
        'Up to 2 ads, Basic analytics, 5,000 impressions/month, Email support',
        1
    ]);
    
    if ($result) {
        echo "✓ Entry-Level plan created: €100/month\n";
    } else {
        echo "✗ Error creating plan\n";
    }
} else {
    echo "✓ Entry-Level plan already exists\n";
}

echo "\nUpdating Starter plan to match new tier structure...\n";

// Update existing Starter plan if needed
$stmt = $pdo->prepare("
    UPDATE pricing_plans 
    SET description = ?, features = ?
    WHERE name = 'Starter' AND price = 99
");

$stmt->execute([
    'Për bizneset në rritje',
    'Up to 5 ads, Advanced analytics, 10,000 impressions/month, Chat support'
]);

echo "✓ Starter plan updated\n";

echo "\n=== All Pricing Plans ===\n";
$stmt = $pdo->query("SELECT * FROM pricing_plans WHERE active = 1 ORDER BY price ASC");
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($plans as $plan) {
    echo "• " . $plan['name'] . " - €" . $plan['price'] . "/month\n";
    echo "  " . $plan['description'] . "\n";
}

echo "\n=== Setup Complete ===\n";
?>
