<?php
require 'confidb.php';

echo "=== Consolidating Pricing Plans ===\n\n";

// Get all plans ordered by price
$stmt = $pdo->query("SELECT * FROM pricing_plans WHERE active = 1 ORDER BY price ASC");
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Current plans:\n";
foreach ($plans as $p) {
    echo "  ID " . $p['id'] . ": " . $p['name'] . " - €" . $p['price'] . "\n";
}

// Delete the €99 Starter plan since we have €100 Entry-Level now
echo "\nRemoving duplicate €99 Starter plan...\n";
$stmt = $pdo->prepare("DELETE FROM pricing_plans WHERE price = 99 AND name = 'Starter'");
if ($stmt->execute()) {
    echo "✓ Deleted €99 Starter plan\n";
}

// Verify final structure
echo "\n=== Final Pricing Structure ===\n";
$stmt = $pdo->query("SELECT * FROM pricing_plans WHERE active = 1 ORDER BY price ASC");
$final_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($final_plans as $plan) {
    echo "\n• " . $plan['name'] . " - €" . number_format($plan['price'], 2) . "/muaj\n";
    echo "  📝 " . $plan['description'] . "\n";
    echo "  ✨ " . $plan['features'] . "\n";
}

echo "\n✅ Pricing consolidation complete!\n";
?>
