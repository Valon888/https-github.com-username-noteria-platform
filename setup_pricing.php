<?php
require 'confidb.php';

echo "=== Adding Pricing System for Advertising ===\n\n";

// Add pricing column to advertisers if not exists
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM advertisers LIKE 'monthly_price'");
    if ($stmt->rowCount() === 0) {
        echo "Adding monthly_price column...\n";
        $pdo->exec("ALTER TABLE advertisers ADD COLUMN monthly_price DECIMAL(10,2) DEFAULT 300 AFTER budget");
        echo "✓ monthly_price column added\n";
    } else {
        echo "✓ monthly_price column already exists\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Add payment_date to ad_payments
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM ad_payments LIKE 'next_payment_date'");
    if ($stmt->rowCount() === 0) {
        echo "Adding next_payment_date column...\n";
        $pdo->exec("ALTER TABLE ad_payments ADD COLUMN next_payment_date DATETIME AFTER paid_at");
        echo "✓ next_payment_date column added\n";
    } else {
        echo "✓ next_payment_date column already exists\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Create pricing plans table
echo "\nCreating pricing_plans table...\n";
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS pricing_plans (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        billing_period VARCHAR(20) DEFAULT 'monthly',
        features TEXT,
        active TINYINT(1) DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ pricing_plans table created\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Insert default pricing plans
echo "\nInserting pricing plans...\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM pricing_plans");
    $count = $stmt->fetchColumn();
    
    if ($count === 0) {
        $plans = [
            [
                'name' => 'Starter',
                'description' => 'Për bizneset e vogla',
                'price' => 99,
                'features' => 'Up to 5 ads, Basic analytics, 10,000 impressions/month'
            ],
            [
                'name' => 'Professional',
                'description' => 'Për bizneset në rritje',
                'price' => 300,
                'features' => 'Up to 20 ads, Advanced analytics, Unlimited impressions, A/B testing, Priority support'
            ],
            [
                'name' => 'Enterprise',
                'description' => 'Për bizneset e mëdha',
                'price' => 999,
                'features' => 'Unlimited ads, Full analytics, Dedicated account manager, Custom targeting, API access'
            ]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO pricing_plans (name, description, price, features) VALUES (?, ?, ?, ?)");
        foreach ($plans as $plan) {
            $stmt->execute([
                $plan['name'],
                $plan['description'],
                $plan['price'],
                $plan['features']
            ]);
            echo "  ✓ " . $plan['name'] . " - €" . $plan['price'] . "/month\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Pricing System Setup Complete ===\n";
echo "\nDefault Plans:\n";
echo "  1. Starter  - €99/month\n";
echo "  2. Professional - €300/month (RECOMMENDED)\n";
echo "  3. Enterprise - €999/month\n";
