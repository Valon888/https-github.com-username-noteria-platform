<?php
/**
 * Setup Wizard për Pakete Abonimesh
 * Krijon pakete standarde për një zyre
 */

session_start();

// Check admin access
if (!isset($_SESSION['admin_id'])) {
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/SubscriptionPlan.php';

$plans = new SubscriptionPlan($pdo);

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_basic_plans') {
        // Krijo dy pakete bazike: 30€ mujor, 360€ vjetor
        
        // Paketa e parë - Mujore
        $monthly = $plans->createPlan([
            'name' => 'Abonimit Mujor',
            'slug' => 'monthly-30',
            'description' => 'Abonimin fleksibël mujor - 30€ për muaj',
            'currency' => 'EUR',
            'monthly_price' => 30.00,
            'yearly_price' => null,
            'setup_fee' => 0,
            'billing_cycle' => 'monthly',
            'features' => [
                'Dokumentet të pakufizuara',
                'Nënshkrime elektronike të pakufizuara',
                'Email support',
                'Dashboard & Analytics',
                'Sigurimi i të dhënave (GDPR compliant)',
                'Backup i përditshëm'
            ],
            'max_documents' => -1,
            'max_signatures' => -1,
            'max_consultations' => 5,
            'support_level' => 'email',
            'trial_days' => 14,
            'is_active' => true
        ]);

        // Paketa e dytë - Vjetore
        $yearly = $plans->createPlan([
            'name' => 'Abonimit Vjetor',
            'slug' => 'yearly-360',
            'description' => 'Zbritje vjetore - 360€ për vit (30€/muaj)',
            'currency' => 'EUR',
            'monthly_price' => 30.00,
            'yearly_price' => 360.00,
            'setup_fee' => 0,
            'billing_cycle' => 'yearly',
            'features' => [
                'Dokumentet të pakufizuara',
                'Nënshkrime elektronike të pakufizuara',
                'Priority email support',
                'Dashboard & Analytics të avancuar',
                'Sigurimi i të dhënave (GDPR compliant)',
                'Backup i përditshëm',
                'API access',
                'Zbritje vjetore (4€ më pak/muaj)'
            ],
            'max_documents' => -1,
            'max_signatures' => -1,
            'max_consultations' => -1,
            'support_level' => 'priority',
            'trial_days' => 30,
            'is_active' => true
        ]);

        if ($monthly && $yearly) {
            $success = true;
            $message = '✅ Dy pakete u krijuan me sukses!
            
• Abonimit Mujor: 30€/muaj
• Abonimit Vjetor: 360€/vit (kushtim 4€/muaj)';
        } else {
            $message = '❌ Gabim gjatë krijimit të paketave. Kontrolloni logun.';
        }
    }
}

// Merr paketet e tanishme
$activePlans = $plans->getActivePlans();
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Abonimesh - Noteria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <style>
        :root {
            --primary: #b8962e;
            --secondary: #1a1410;
            --accent: #d4af37;
            --light: #faf7f2;
        }

        body {
            background: linear-gradient(135deg, var(--secondary) 0%, #2d241f 100%);
            color: var(--secondary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .container-main {
            padding: 3rem 1rem;
        }

        .card {
            border: 2px solid var(--primary);
            background: var(--light);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            margin-bottom: 2rem;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            color: var(--light);
            border: none;
            padding: 1.5rem;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .card-body {
            padding: 2rem;
        }

        .pricing-card {
            border: 2px solid var(--primary);
            background: white;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .pricing-card:hover {
            border-color: var(--accent);
            box-shadow: 0 8px 25px rgba(184, 150, 46, 0.3);
            transform: translateY(-5px);
        }

        .pricing-card h3 {
            color: var(--primary);
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }

        .price {
            font-size: 3rem;
            color: var(--accent);
            font-weight: bold;
            margin: 1rem 0;
        }

        .price-period {
            color: #888;
            font-size: 1rem;
        }

        .features-list {
            text-align: left;
            margin: 1.5rem 0;
        }

        .features-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }

        .features-list li:last-child {
            border-bottom: none;
        }

        .features-list i {
            color: #27ae60;
            margin-right: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            color: var(--light);
            padding: 0.75rem 2rem;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .btn-primary:hover {
            background-color: var(--accent);
            border-color: var(--accent);
            color: var(--secondary);
        }

        .alert-success {
            background-color: #d4edda;
            border: 2px solid #27ae60;
            color: #155724;
            padding: 1.5rem;
            border-radius: 8px;
            white-space: pre-wrap;
            font-weight: bold;
        }

        .alert-danger {
            background-color: #f8d7da;
            border: 2px solid #e74c3c;
            color: #721c24;
            padding: 1.5rem;
            border-radius: 8px;
        }

        .header-section {
            text-align: center;
            margin-bottom: 3rem;
        }

        .header-section h1 {
            color: var(--accent);
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }

        .header-section p {
            color: #666;
            font-size: 1.1rem;
        }

        .existing-plans {
            background: var(--light);
            padding: 2rem;
            border-radius: 8px;
            margin-top: 2rem;
        }

        .plan-item {
            background: white;
            padding: 1rem;
            border-left: 4px solid var(--primary);
            margin-bottom: 1rem;
            border-radius: 4px;
        }

        .plan-item h5 {
            color: var(--primary);
            margin-bottom: 0.3rem;
        }

        .plan-item .price-info {
            font-size: 1.3rem;
            color: var(--accent);
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container-main">
        <div class="header-section">
            <h1><i class="fas fa-layer-group"></i> Konfigurimi i Paketave Abonimesh</h1>
            <p>Krijo dy pakete standarde për të gjithë zyrën</p>
        </div>

        <div class="row">
            <div class="col-lg-8 mx-auto">
                <!-- AlertMessages -->
                <?php if ($message): ?>
                    <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Pricing Cards -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="pricing-card">
                            <h3>📅 Mujore</h3>
                            <div class="price">30€<span class="price-period">/muaj</span></div>
                            <ul class="features-list">
                                <li><i class="fas fa-check"></i> Dokumentet të pakufizuara</li>
                                <li><i class="fas fa-check"></i> Nënshkrime të pakufizuara</li>
                                <li><i class="fas fa-check"></i> Deri 5 konsultata</li>
                                <li><i class="fas fa-check"></i> Email support</li>
                                <li><i class="fas fa-check"></i> Backup i përditshëm</li>
                            </ul>
                            <p class="text-muted"><small>Fleksibël - Zgjatje ose anulim në çdo kohë</small></p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="pricing-card">
                            <div style="background: linear-gradient(135deg, var(--primary), var(--accent)); color: white; padding: 0.5rem; border-radius: 20px; margin: -2rem -2rem 1rem -2rem; text-align: center; font-weight: bold;">
                                ⭐ KUSHTIM 4€/MUAJ
                            </div>
                            <h3>📆 Vjetore</h3>
                            <div class="price">360€<span class="price-period">/vit</span></div>
                            <ul class="features-list">
                                <li><i class="fas fa-check"></i> Dokumentet të pakufizuara</li>
                                <li><i class="fas fa-check"></i> Nënshkrime të pakufizuara</li>
                                <li><i class="fas fa-check"></i> Konsultata të pakufizuara</li>
                                <li><i class="fas fa-check"></i> Priority support</li>
                                <li><i class="fas fa-check"></i> Backup i përditshëm</li>
                                <li><i class="fas fa-check"></i> API access</li>
                            </ul>
                            <p class="text-muted"><small>Zbritje vjetore më e mirë</small></p>
                        </div>
                    </div>
                </div>

                <!-- Setup Card -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-magic"></i> Krijimi i Paketave
                    </div>
                    <div class="card-body">
                        <p class="mb-3">Kliko butonin përpak për të krijuar këto dy pakete standarde automatikisht.</p>
                        
                        <form method="POST" class="mb-4">
                            <input type="hidden" name="action" value="create_basic_plans">
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-rocket"></i> Krijo Pakete Menjëherë
                            </button>
                        </form>

                        <hr>

                        <h5 class="mt-4"><i class="fas fa-info-circle"></i> Çfarë do Të Krijohet?</h5>
                        <ul>
                            <li>Paketa e abonimit mujor: <strong>30€/muaj</strong> me prove 14 ditësh</li>
                            <li>Paketa e abonimit vjetor: <strong>360€/vit</strong> me prove 30 ditësh (kushtim 4€ më pak me muaj)</li>
                            <li>Të dyja pakete me karakteristikat e plotë të platformës</li>
                            <li>Email dhe Priority support të aktivizuar</li>
                        </ul>
                    </div>
                </div>

                <!-- Existing Plans -->
                <?php if (!empty($activePlans)): ?>
                <div class="existing-plans">
                    <h4 class="mb-3"><i class="fas fa-th"></i> Pakete të Tanishme</h4>
                    <?php foreach ($activePlans as $plan): ?>
                        <div class="plan-item">
                            <h5><?php echo htmlspecialchars($plan['name']); ?></h5>
                            <div class="price-info">
                                <?php echo number_format($plan['monthly_price'], 2); ?>€/muaj
                                <?php if ($plan['yearly_price']): ?>
                                    | <?php echo number_format($plan['yearly_price'], 2); ?>€/vit
                                <?php endif; ?>
                            </div>
                            <small class="text-muted"><?php echo htmlspecialchars($plan['description']); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Help Section -->
                <div class="card mt-4">
                    <div class="card-header">
                        <i class="fas fa-question-circle"></i> FAQ
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6>🤔 Çfarë ndodh pas krijimit të paketave?</h6>
                            <p>Pakete do të bëhen të disponueshme në dashboard-in e abonimeve dhe përdoruesit mund të zgjedhin ndonjërën kur të bëhen abonues.</p>
                        </div>
                        <div class="mb-3">
                            <h6>💰 A mund t'i ndryshoj çmimet më vonë?</h6>
                            <p>Po! Mund të nuk i ndrysho çmimet në dashboard-in e abonimeve për pakete ekzistuese.</p>
                        </div>
                        <div class="mb-3">
                            <h6>🔄 A mund të krijoim më shumë pakete?</h6>
                            <p>Po! Mund të krijohen pakete të shtuara vetëm përmes dashboard-it të abonimeve.</p>
                        </div>
                        <div class="mb-3">
                            <h6>⚙️ Si funksionon faturimi automatik?</h6>
                            <p>Sistemi gjeneron fatura çdo muaj ose vit sipas planit të zgjedhur. Kujetsa të automatizuara dërgohen përpara afatit të pagesës.</p>
                        </div>
                    </div>
                </div>

                <!-- Back Links -->
                <div class="mt-4 text-center">
                    <a href="/admin/subscriptions_billing.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kthehu në Dashboard
                    </a>
                    <a href="/admin/dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-home"></i> Kthehu në Admin
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
