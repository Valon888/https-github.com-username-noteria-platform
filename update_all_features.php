<?php
/**
 * Update subscription plans with ALL features
 * Shto të gjitha opsionet/shërbimeve në të dyja paketet
 */

require_once __DIR__ . '/config.php';

echo "🚀 Përditësohen pakete me të GJITHË features...\n\n";

try {
    // Të GJITHË features të disponueshme
    $allFeatures = [
        'Dokumentet të pakufizuara',
        'Nënshkrime elektronike të pakufizuara',
        'Video consultations të pakufizuara',
        'Verifikimi i dokumenteve',
        'Dokusign integration',
        'Dashboard & Analytics të avancuar',
        'API access',
        'Backup i përditshëm',
        'Sigurimi i të dhënave (GDPR compliant)',
        'Multi-user access',
        'Advanced reporting',
        '24/7 Priority support',
        'Bulk operations',
        'Custom workflows',
        'White label options',
        'SSO integration',
        'Advanced audit logs',
        'Two-factor authentication',
        'Mobile app access',
        'Unlimited storage',
        'Template library',
        'Webhook integration',
        'Custom branding',
        'Advanced search'
    ];

    // Update Paketa 1: Mujore (30€)
    $sql = "UPDATE subscription_plans 
            SET features = ?,
                description = 'Abonimin fleksibël mujor - 30€ për muaj - Të GJITHË features të platformës'
            WHERE code = 'monthly-30'";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([json_encode($allFeatures)]);
    
    echo "✅ Abonimit Mujor përditësuar me " . count($allFeatures) . " features\n";

    // Update Paketa 2: Vjetore (360€)
    $sql = "UPDATE subscription_plans 
            SET features = ?,
                description = 'Zbritje vjetore - 360€ për vit (30€/muaj) - Të GJITHË features të platformës + Priority support'
            WHERE code = 'yearly-360'";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([json_encode($allFeatures)]);
    
    echo "✅ Abonimit Vjetor përditësuar me " . count($allFeatures) . " features\n";

    // Verify updates
    $sql = "SELECT name, code, price_monthly, price_yearly, features FROM subscription_plans WHERE is_active = 1 ORDER BY price_monthly";
    $stmt = $pdo->query($sql);
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n════════════════════════════════════════════════════════════\n";
    echo "📦 PAKETE PËRFUNDIMTARE:\n";
    echo "════════════════════════════════════════════════════════════\n";
    
    foreach ($plans as $plan) {
        echo "\n✨ {$plan['name']}\n";
        echo "   💰 Çmim: {$plan['price_monthly']}€/muaj | {$plan['price_yearly']}€/vit\n";
        
        $features = json_decode($plan['features'], true);
        echo "   📋 Features (" . count($features) . " opsione):\n";
        
        foreach ($features as $i => $feature) {
            $num = $i + 1;
            echo "      $num. ✓ $feature\n";
        }
    }
    
    echo "\n════════════════════════════════════════════════════════════\n";
    echo "\n✨ SUKSES! Të dyja pakete kanë TË GJITHË servat e platformës!\n";
    echo "\n💡 Shënime:\n";
    echo "   • Mujore: 30€ - Fleksibël, mund të ndryshoni ose anuloni çdo muaj\n";
    echo "   • Vjetore: 360€/vit (4€ më pak çdo muaj) - Më ekonomike\n";
    echo "   • Të dyja kanë akses PLOTË në të gjitha features\n";

} catch (Exception $e) {
    echo "❌ GABIM: " . $e->getMessage() . "\n";
    exit(1);
}
