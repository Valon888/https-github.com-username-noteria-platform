<?php
/**
 * Plan Selection and Payment Processing
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'confidb.php';
require_once 'pricing_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';
$advertiser_id = null;

// Get advertiser ID
$stmt = $pdo->prepare("SELECT id FROM advertisers WHERE email = ?");
$stmt->execute([$_SESSION['email'] ?? '']);
$advertiser = $stmt->fetch();

if ($advertiser) {
    $advertiser_id = $advertiser['id'];
}

// Handle plan selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan_id'])) {
    $plan_id = intval($_POST['plan_id']);
    
    if (!$advertiser_id) {
        $error = "Duhet të jeni i regjistruar si biznes më parë.";
    } else {
        $plan = getPricingPlanById($pdo, $plan_id);
        if (!$plan) {
            $error = "Paketa nuk u gjet.";
        } else {
            // Create subscription
            $subscription_id = createSubscription($pdo, $advertiser_id, $plan_id);
            
            if ($subscription_id) {
                $message = "Paketa u zgjodh me sukses! Ju do të ridrejtoheni në pagesë...";
                
                // Log activity
                log_activity($pdo, $_SESSION['user_id'], 'advertising_subscription_selected', 
                            'Zgjodhi paketën: ' . $plan['name'] . ' (€' . $plan['price'] . '/muaj)');
            } else {
                $error = "Gabim gjatë përpunimit të abonimit.";
            }
        }
    }
}

// Get current subscription if exists
$subscription = null;
if ($advertiser_id) {
    $subscription = getSubscriptionStatus($pdo, $advertiser_id);
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zgjedh Paketën - Noteria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
            background: #f5f7fa;
            padding: 40px 20px;
        }
        
        .container { max-width: 1200px; margin: 0 auto; }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .header h1 {
            font-size: 32px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 16px;
            color: #666;
        }
        
        .message {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .pricing-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            position: relative;
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
        }
        
        .pricing-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.15);
        }
        
        .pricing-card.recommended {
            border: 3px solid #667eea;
            transform: scale(1.05);
        }
        
        .pricing-card.recommended:hover {
            transform: translateY(-8px) scale(1.05);
        }
        
        .recommended-badge {
            position: absolute;
            top: -15px;
            right: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .pricing-card h3 {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
            margin-top: 0;
        }
        
        .pricing-card .description {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .price {
            display: flex;
            align-items: baseline;
            margin: 20px 0;
            gap: 5px;
        }
        
        .price .currency {
            font-size: 24px;
            color: #667eea;
            font-weight: bold;
        }
        
        .price .amount {
            font-size: 48px;
            font-weight: bold;
            color: #333;
        }
        
        .price .period {
            color: #999;
            font-size: 14px;
        }
        
        .features {
            list-style: none;
            padding: 0;
            margin: 20px 0;
            flex-grow: 1;
        }
        
        .features li {
            padding: 10px 0;
            color: #555;
            border-bottom: 1px solid #eee;
            font-size: 14px;
            display: flex;
            align-items: center;
        }
        
        .features li:before {
            content: "✓";
            color: #667eea;
            font-weight: bold;
            margin-right: 10px;
            font-size: 16px;
        }
        
        .select-plan-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }
        
        .select-plan-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .select-plan-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .current-plan {
            background: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
            padding: 10px;
            border-radius: 6px;
            font-size: 13px;
            margin-top: 10px;
            text-align: center;
            font-weight: 500;
        }
        
        .comparison-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin: 40px 0;
        }
        
        .comparison-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .comparison-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
        }
        
        .comparison-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .comparison-table tr:nth-child(even) {
            background: #fafbfc;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
        }
        
        .back-btn:hover {
            color: #764ba2;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="business_advertising.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Kthehu
        </a>
        
        <div class="header">
            <h1><i class="fas fa-tag"></i> Zgjedh Paketën Tuaj</h1>
            <p>Selektoni paketën më të përshtatshme për biznese</p>
        </div>
        
        <?php if ($message): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- PRICING CARDS -->
        <div class="pricing-grid">
            <?php
            $plans = getPricingPlans($pdo);
            foreach ($plans as $plan):
                $is_recommended = ($plan['price'] == 300);
                $is_current = ($subscription && $subscription['plan_id'] == $plan['id']);
            ?>
            <div class="pricing-card <?php echo $is_recommended ? 'recommended' : ''; ?>">
                <?php if ($is_recommended): ?>
                    <div class="recommended-badge">REKOMANDUAR</div>
                <?php endif; ?>
                
                <h3><?php echo htmlspecialchars($plan['name']); ?></h3>
                <p class="description"><?php echo htmlspecialchars($plan['description']); ?></p>
                
                <div class="price">
                    <span class="currency">€</span>
                    <span class="amount"><?php echo number_format($plan['price'], 0); ?></span>
                    <span class="period">/muaj</span>
                </div>
                
                <?php if ($plan['features']): ?>
                    <ul class="features">
                        <?php 
                        $features = array_map('trim', explode(',', $plan['features']));
                        foreach ($features as $feature): 
                        ?>
                            <li><?php echo htmlspecialchars($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <form method="POST" style="display: flex; flex-direction: column;">
                    <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                    <button type="submit" class="select-plan-btn" <?php echo $is_current ? 'disabled' : ''; ?>>
                        <?php echo $is_current ? 'Paketa Aktuale' : 'Zgjidh këtë Plan'; ?>
                    </button>
                </form>
                
                <?php if ($is_current): ?>
                    <div class="current-plan">
                        <i class="fas fa-check-circle"></i> Paketa aktuale tuaj
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- COMPARISON TABLE -->
        <div class="comparison-table">
            <h2 style="padding: 20px; margin: 0; background: #f8f9fa; border-bottom: 2px solid #ddd;">
                <i class="fas fa-table"></i> Krahasim Paketash
            </h2>
            <table>
                <thead>
                    <tr>
                        <th>Karakteristika</th>
                        <th>Starter</th>
                        <th>Professional</th>
                        <th>Enterprise</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Çmim Mujor</strong></td>
                        <td>€99</td>
                        <td>€300</td>
                        <td>€999</td>
                    </tr>
                    <tr>
                        <td>Reklama Aktive</td>
                        <td>Deri 5</td>
                        <td>Deri 20</td>
                        <td>Nuk ka limit</td>
                    </tr>
                    <tr>
                        <td>Përgatitjet e Reklamave</td>
                        <td>10/muaj</td>
                        <td>Nuk ka limit</td>
                        <td>Nuk ka limit</td>
                    </tr>
                    <tr>
                        <td>Analytics në kohë reale</td>
                        <td>✓ Bazik</td>
                        <td>✓ Avancuar</td>
                        <td>✓ Avancuar</td>
                    </tr>
                    <tr>
                        <td>Testim A/B</td>
                        <td>-</td>
                        <td>✓</td>
                        <td>✓</td>
                    </tr>
                    <tr>
                        <td>Mbështetje Prioritare</td>
                        <td>-</td>
                        <td>✓</td>
                        <td>✓</td>
                    </tr>
                    <tr>
                        <td>Menaxher Dedikatë i Llogarisë</td>
                        <td>-</td>
                        <td>-</td>
                        <td>✓</td>
                    </tr>
                    <tr>
                        <td>API Qasje</td>
                        <td>-</td>
                        <td>-</td>
                        <td>✓</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- HELP SECTION -->
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; text-align: center; margin-top: 40px;">
            <h2 style="color: white; margin-bottom: 10px;">Pyetje ose Ndihmë?</h2>
            <p style="margin-bottom: 15px; opacity: 0.9;">Kontaktoni ekipin tonë të reklamimit për më shumë informacion</p>
            <a href="mailto:advertising@noteria.al" style="display: inline-block; background: white; color: #667eea; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600;">
                <i class="fas fa-envelope"></i> Shkruani Email
            </a>
        </div>
    </div>
    
    <?php echo getPricingJS(); ?>
</body>
</html>
