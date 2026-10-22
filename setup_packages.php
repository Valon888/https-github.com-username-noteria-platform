<?php
/**
 * CLI Script për Krijimin Automatik të Paketave
 * Ekzekuto: php setup_packages.php
 */

// Kontrolloni nëse po ekzekutohet përmes CLI
if (php_sapi_name() !== 'cli') {
    // Nëse thirret përmes HTTP
    header('Content-Type: application/json');
    if (!isset($_GET['key']) || $_GET['key'] !== 'setup_'.date('YmdH')) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/SubscriptionPlan.php';

echo "🚀 Inicjalizimi i Paketave Abonimesh...\n\n";

$plans = new SubscriptionPlan($pdo);

try {
    // Paketa 1: Abonimit Mujor
    echo "📅 Krijohet paketa mujore (30€/muaj)...\n";
    $monthly = $plans->createPlan([
        'name' => 'Abonimit Mujor',
        'code' => 'monthly-30',
        'description' => 'Abonimin fleksibël mujor - 30€ për muaj',
        'price_monthly' => 30.00,
        'price_yearly' => 0.00,
        'features' => [
            'Dokumentet të pakufizuara',
            'Nënshkrime elektronike të pakufizuara',
            'Email support',
            'Dashboard & Analytics',
            'Sigurimi i të dhënave (GDPR compliant)',
            'Backup i përditshëm'
        ]
    ]);

    if ($monthly) {
        echo "   ✅ Paketa mujore u krijua me sukses!\n";
    } else {
        throw new Exception('Gabim gjatë krijimit të paketës mujore');
    }

    // Paketa 2: Abonimit Vjetor
    echo "\n📆 Krijohet paketa vjetore (360€/vit)...\n";
    $yearly = $plans->createPlan([
        'name' => 'Abonimit Vjetor',
        'code' => 'yearly-360',
        'description' => 'Zbritje vjetore - 360€ për vit (30€/muaj)',
        'price_monthly' => 30.00,
        'price_yearly' => 360.00,
        'features' => [
            'Dokumentet të pakufizuara',
            'Nënshkrime elektronike të pakufizuara',
            'Priority email support',
            'Dashboard & Analytics të avancuar',
            'Sigurimi i të dhënave (GDPR compliant)',
            'Backup i përditshëm',
            'API access',
            'Zbritje vjetore (4€ më pak/muaj)'
        ]
    ]);

    if ($yearly) {
        echo "   ✅ Paketa vjetore u krijua me sukses!\n";
    } else {
        throw new Exception('Gabim gjatë krijimit të paketës vjetore');
    }

    echo "\n✨ SUKSES! Pakete u krijuan:\n";
    echo "   💰 Mujore:   30€/muaj\n";
    echo "   💰 Vjetore:  360€/vit (4€ zbritje/muaj)\n";
    echo "\n📊 Hir në /admin/subscriptions_billing.php për të shikuar pakete.\n";

    // Nëse thirret përmes HTTP
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Pakete u krijuan me sukses',
            'packages' => [
                'monthly' => '30€/muaj',
                'yearly' => '360€/vit'
            ]
        ]);
    }

} catch (Exception $e) {
    echo "\n❌ GABIM: " . $e->getMessage() . "\n";
    
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit(1);
}
