<?php
/**
 * Clean up subscription plans - Keep only 2 plans
 * Keep: Abonimin Mujor (30€) and Abonimin Vjetor (360€)
 * Delete: Free, Basic, Professional, Enterprise
 */

require_once __DIR__ . '/config.php';

echo "🧹 Pastrimi i paketave të tepërta...\n\n";

try {
    // Delete paketet e tjera (keep only 30€ monthly and 360€ yearly)
    $sql = "DELETE FROM subscription_plans 
            WHERE code NOT IN ('monthly-30', 'yearly-360')
            AND is_active = 1";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute();
    
    $deleted = $stmt->rowCount();
    echo "❌ U fshirën $deleted pakete të tjera\n\n";

    // Verify remaining plans
    $sql = "SELECT id, name, code, price_monthly, price_yearly, features FROM subscription_plans WHERE is_active = 1";
    $stmt = $pdo->query($sql);
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ Pakete të mbetura:\n";
    echo "═══════════════════════════════════════════════════════════\n";
    
    foreach ($plans as $plan) {
        echo "\n📦 {$plan['name']}\n";
        echo "   Code: {$plan['code']}\n";
        echo "   Çmim Mujor: {$plan['price_monthly']}€\n";
        echo "   Çmim Vjetor: {$plan['price_yearly']}€\n";
        
        $features = json_decode($plan['features'], true);
        if ($features) {
            echo "   Features:\n";
            foreach ($features as $feature) {
                echo "     • $feature\n";
            }
        }
    }
    
    echo "\n═══════════════════════════════════════════════════════════\n";
    echo "\n✨ Sistemi është i gatshëm me vetëm 2 pakete abonimesh!\n";

} catch (Exception $e) {
    echo "❌ GABIM: " . $e->getMessage() . "\n";
    exit(1);
}
