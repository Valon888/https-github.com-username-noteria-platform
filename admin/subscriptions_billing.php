<?php
/**
 * SUBSCRIPTION PLANS & BILLING DASHBOARD
 * Menaxhimi i pakoveve abonimesh dhe faturimit automatik
 */

session_start();

// Check admin access
if (!isset($_SESSION['admin_id'])) {
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/SubscriptionPlan.php';
require_once __DIR__ . '/../classes/DiscountManager.php';
require_once __DIR__ . '/../classes/BillingAutomation.php';

$plans = new SubscriptionPlan($pdo);
$discounts = new DiscountManager($pdo);
$billing = new BillingAutomation($pdo);

// Merr të dhënat për dashboard
$activePlans = $plans->getActivePlans();
$activeDiscounts = $discounts->getActiveDiscounts();

// Merr statistika
$statsQuery = "SELECT 
                COUNT(DISTINCT s.id) as total_subscriptions,
                COUNT(DISTINCT CASE WHEN s.status = 'active' THEN s.id END) as active_subscriptions,
                SUM(CASE WHEN i.status = 'issued' THEN i.total_amount ELSE 0 END) as pending_amount,
                COUNT(DISTINCT CASE WHEN i.status = 'overdue' THEN i.id END) as overdue_invoices
               FROM subscription s
               LEFT JOIN invoices i ON s.id = i.subscription_id";
               
$stmt = $pdo->query($statsQuery);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menaxhimi i Abonimeve - Noteria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #b8962e;
            --secondary: #1a1410;
            --accent: #d4af37;
            --light: #faf7f2;
            --danger: #e74c3c;
            --success: #27ae60;
            --warning: #f39c12;
        }

        body {
            background: linear-gradient(135deg, var(--secondary) 0%, #2d241f 100%);
            color: var(--secondary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%);
            border-bottom: 3px solid var(--accent);
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .navbar-brand {
            font-weight: bold;
            color: var(--accent) !important;
            font-size: 1.5rem;
        }

        .container-fluid {
            padding: 2rem;
        }

        .card {
            border: 1px solid var(--primary);
            background: var(--light);
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
            border-color: var(--accent);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            color: var(--secondary);
            border: none;
            font-weight: bold;
            padding: 1rem 1.5rem;
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            color: var(--light);
        }

        .btn-primary:hover {
            background-color: var(--accent);
            border-color: var(--accent);
            color: var(--secondary);
        }

        .badge-plan {
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-weight: bold;
        }

        .badge-starter {
            background-color: #3498db;
            color: white;
        }

        .badge-pro {
            background-color: var(--primary);
            color: white;
        }

        .badge-premium {
            background-color: var(--accent);
            color: var(--secondary);
        }

        .stat-box {
            background: var(--light);
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
            text-align: center;
            margin-bottom: 1rem;
        }

        .stat-box h3 {
            color: var(--primary);
            font-size: 2rem;
            font-weight: bold;
        }

        .stat-box p {
            color: #666;
            margin: 0;
            font-size: 0.9rem;
        }

        .table {
            background: var(--light);
            margin-top: 1rem;
        }

        .table thead {
            background: var(--primary);
            color: var(--light);
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid #ddd;
        }

        .modal-content {
            background: var(--light);
            border: 1px solid var(--primary);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            color: var(--light);
            border-bottom: 2px solid var(--accent);
        }

        .form-control {
            border: 1px solid var(--primary);
            padding: 0.75rem;
            border-radius: 4px;
        }

        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 0.2rem rgba(184, 150, 46, 0.25);
        }

        .nav-tabs {
            border-bottom: 2px solid var(--primary);
        }

        .nav-tabs .nav-link {
            color: var(--secondary);
            border: none;
            border-bottom: 3px solid transparent;
        }

        .nav-tabs .nav-link.active {
            background-color: transparent;
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-content {
            padding: 1.5rem 0;
        }

        .alert-info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/dashboard.php">
                <i class="fas fa-heartbeat"></i> Noteria Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">Abonimet</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/users.php">Përdoruesit</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/logout.php">Dalje</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-md-12">
                <h1 class="mb-4"><i class="fas fa-cube"></i> Menaxhimi i Abonimeve</h1>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-box">
                    <h3><?php echo $stats['total_subscriptions'] ?? 0; ?></h3>
                    <p>Abonimet Totale</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <h3><?php echo $stats['active_subscriptions'] ?? 0; ?></h3>
                    <p>Abonimet Aktive</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <h3><?php echo number_format($stats['pending_amount'] ?? 0, 2); ?> €</h3>
                    <p>Pagesat në Pritje</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <h3><?php echo $stats['overdue_invoices'] ?? 0; ?></h3>
                    <p>Fatura të Vonuara</p>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#plans">Pakete Abonimesh</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#discounts">Zbritje</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#billing">Faturim & Pagesa</a>
            </li>
        </ul>

        <!-- TAB 1: SUBSCRIPTION PLANS -->
        <div class="tab-content">
            <div id="plans" class="tab-pane fade show active">
                <div class="row mt-4">
                    <div class="col-md-12">
                        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#createPlanModal">
                            <i class="fas fa-plus"></i> Krijo Paketë të Re
                        </button>
                        <button class="btn btn-secondary mb-3" onclick="createDefaultPlans()">
                            <i class="fas fa-wand-magic-sparkles"></i> Krijo Pakete Standarde
                        </button>
                    </div>
                </div>

                <div class="row">
                    <?php foreach ($activePlans as $plan): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <?php
                                $badgeClass = 'badge-' . $plan['slug'] ?? 'badge-starter';
                                ?>
                                <span class="badge badge-plan <?php echo $badgeClass; ?>">
                                    <?php echo htmlspecialchars($plan['name']); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($plan['name']); ?></h5>
                                <p class="card-text text-muted"><?php echo htmlspecialchars($plan['description']); ?></p>
                                
                                <div class="price-section mb-3">
                                    <h4 class="text-primary">
                                        <?php echo number_format($plan['monthly_price'], 2); ?> €
                                        <small class="text-muted">/muaj</small>
                                    </h4>
                                    <?php if ($plan['yearly_price']): ?>
                                    <p class="text-muted">
                                        <small><?php echo number_format($plan['yearly_price'], 2); ?> € vjetore</small>
                                    </p>
                                    <?php endif; ?>
                                </div>

                                <div class="features-section mb-3">
                                    <?php
                                    if ($plan['features']) {
                                        $features = json_decode($plan['features'], true);
                                        foreach ($features as $feature) {
                                            echo '<p class="mb-1"><i class="fas fa-check text-success"></i> ' . htmlspecialchars($feature) . '</p>';
                                        }
                                    }
                                    ?>
                                </div>

                                <button class="btn btn-sm btn-primary" onclick="editPlan(<?php echo $plan['id']; ?>)">
                                    <i class="fas fa-edit"></i> Ndrysho
                                </button>
            <button class="btn btn-sm btn-danger" onclick="deletePlan(<?php echo $plan['id']; ?>)">
                                    <i class="fas fa-trash"></i> Fshi
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- TAB 2: DISCOUNTS -->
            <div id="discounts" class="tab-pane fade">
                <div class="row mt-4">
                    <div class="col-md-12">
                        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#createDiscountModal">
                            <i class="fas fa-tag"></i> Krijo Zbritje të Re
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Zbritje Aktive</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Kod</th>
                                        <th>Emri</th>
                                        <th>Lloji</th>
                                        <th>Vlera</th>
                                        <th>Përdorime</th>
                                        <th>Vlefshmëri</th>
                                        <th>Aksioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activeDiscounts as $discount): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($discount['code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($discount['name']); ?></td>
                                        <td><?php echo $discount['discount_type'] === 'percentage' ? '%' : '€'; ?></td>
                                        <td><?php echo number_format($discount['discount_value'], 2); ?></td>
                                        <td>
                                            <?php 
                                            echo $discount['used_count'];
                                            if ($discount['max_uses'] > 0) {
                                                echo ' / ' . $discount['max_uses'];
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('d.m.Y', strtotime($discount['valid_from'])); ?> -
                                                <?php echo date('d.m.Y', strtotime($discount['valid_until'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="editDiscount(<?php echo $discount['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteDiscount(<?php echo $discount['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: BILLING -->
            <div id="billing" class="tab-pane fade">
                <div class="row mt-4">
                    <div class="col-md-12">
                        <button class="btn btn-warning" onclick="runBillingTasks()">
                            <i class="fas fa-cog"></i> Ekzekuto Detyra Ditore
                        </button>
                        <button class="btn btn-info" onclick="checkOverdue()">
                            <i class="fas fa-exclamation-triangle"></i> Kontrollo Pagesa Vonuese
                        </button>
                        <button class="btn btn-success" onclick="sendReminders()">
                            <i class="fas fa-bell"></i> Dërgo Kujtesa
                        </button>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Faturim & Pagesa</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Sistemi i automatizimit të faturimit po punon. Kontrollo detyra cdo ditë.
                        </div>

                        <h6 class="mt-4">Detyra Automatike:</h6>
                        <ul>
                            <li>Gjenero fatura të reja sipas ciklit të abonimit</li>
                            <li>Dërgo kujtesa 3 ditë përpara afatit të pagesës</li>
                            <li>Dërgo kujtesa 1 ditë përpara afatit të pagesës</li>
                            <li>Kontrollo pagesat vonuese dhe apliko penalitete</li>
                            <li>Dërgo kujtesa për pagesat vonuese (1, 7, 30 ditë)</li>
                            <li>Përditëso statusin e abonimeve të skaduar</li>
                        </ul>

                        <h6 class="mt-4">Penalitete për Vonesa:</h6>
                        <p>0.5% për ditë von (maksimum 10% të shumës totale)</p>

                        <h6 class="mt-4">Konfigurimi i Cron Job (Linux/Server):</h6>
                        <pre class="bg-light p-3 rounded"><code>0 2 * * * php /path/to/noteria/cron/billing_cron.php</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODALS -->

    <!-- Create/Edit Plan Modal -->
    <div class="modal fade" id="createPlanModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Krijo Paketë të Re</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="planForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Emri Paketës *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Slug (URL) *</label>
                            <input type="text" class="form-control" name="slug" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Përshkrim</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Çmimi Mujor (€) *</label>
                                <input type="number" class="form-control" name="monthly_price" step="0.01" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Çmimi Vjetor (€)</label>
                                <input type="number" class="form-control" name="yearly_price" step="0.01">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Taksa për Instalim (€)</label>
                                <input type="number" class="form-control" name="setup_fee" step="0.01" value="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Niveli i Suportit</label>
                                <select class="form-control" name="support_level">
                                    <option value="email">Email</option>
                                    <option value="priority">Priority</option>
                                    <option value="dedicated">Dedicated</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Dokumentet Maksimale</label>
                                <input type="number" class="form-control" name="max_documents" value="-1">
                                <small class="text-muted">-1 = Të pakufizuara</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ditë Prove Falas</label>
                                <input type="number" class="form-control" name="trial_days" value="14">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anulo</button>
                        <button type="submit" class="btn btn-primary">Ruaj Paketën</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create/Edit Discount Modal -->
    <div class="modal fade" id="createDiscountModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Krijo Zbritje të Re</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="discountForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kodi i Zbritjes *</label>
                                <input type="text" class="form-control" name="code" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emri i Zbritjes *</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Përshkrim</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Lloji i Zbritjes *</label>
                                <select class="form-control" name="discount_type" required>
                                    <option value="percentage">Përqindje (%)</option>
                                    <option value="fixed">Shumë fikse (€)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Vlera *</label>
                                <input type="number" class="form-control" name="discount_value" step="0.01" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Zbatohet në *</label>
                                <select class="form-control" name="applies_to" required>
                                    <option value="all_plans">Të gjitha planet</option>
                                    <option value="specific_plans">Planet specifike</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Përdorime Maksimale</label>
                                <input type="number" class="form-control" name="max_uses" value="-1">
                                <small class="text-muted">-1 = Të pakufizuara</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Vlefshme Nga *</label>
                                <input type="datetime-local" class="form-control" name="valid_from" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Vlefshme Deri *</label>
                                <input type="datetime-local" class="form-control" name="valid_until" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Muajat Minimale të Abonimit</label>
                            <input type="number" class="form-control" name="min_subscription_months" value="1">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anulo</button>
                        <button type="submit" class="btn btn-primary">Ruaj Zbritje</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Create default plans
        function createDefaultPlans() {
            if (!confirm('Këtu do të krijohen 3 pakete standarde (Starter, Pro, Premium). Jeni i sigurt?')) return;
            
            fetch('/api/SubscriptionPlansController.php?action=create_defaults', {
                method: 'POST'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Pakete standarde u krijuan!');
                    location.reload();
                } else {
                    alert('Gabim: ' + data.error);
                }
            });
        }

        // Edit plan
        function editPlan(id) {
            alert('Funksionaliteti i ndryshjimit do të implementohet përpara se të kontakt support');
        }

        // Delete plan
        function deletePlan(id) {
            if (!confirm('Jeni i sigurt se dëshironi të fshini këtë paketë?')) return;
            
            fetch('/api/SubscriptionPlansController.php?action=delete', {
                method: 'POST',
                body: new FormData(Object.assign(document.createElement('form'), {
                    elements: { id: { value: id } }
                }))
            })
            .then(r => r.json())
            .then(data => {
                alert(data.message);
                location.reload();
            });
        }

        // Edit discount
        function editDiscount(id) {
            alert('Funksionaliteti i ndryshjimit do të implementohet përpara se të kontakt support');
        }

        // Delete discount
        function deleteDiscount(id) {
            if (!confirm('Jeni i sigurt?')) return;
            
            fetch('/api/DiscountsController.php?action=delete', {
                method: 'POST',
                body: new FormData(Object.assign(document.createElement('form'), {
                    elements: { id: { value: id } }
                }))
            })
            .then(r => r.json())
            .then(data => {
                alert(data.message);
                location.reload();
            });
        }

        // Run billing tasks
        function runBillingTasks() {
            if (!confirm('Ekzekuto të gjitha detyra ditore të faturimit?')) return;
            
            fetch('/api/BillingController.php?action=run_daily_tasks', {
                method: 'POST'
            })
            .then(r => r.json())
            .then(data => {
                alert(data.message);
            });
        }

        // Check overdue
        function checkOverdue() {
            fetch('/api/BillingController.php?action=check_overdue', {
                method: 'POST'
            })
            .then(r => r.json())
            .then(data => {
                alert('U gjetën ' + data.overdue_count + ' fatura të vonuara');
            });
        }

        // Send reminders
        function sendReminders() {
            fetch('/api/BillingController.php?action=send_reminders', {
                method: 'POST'
            })
            .then(r => r.json())
            .then(data => {
                alert('U dërguan ' + data.reminders_sent + ' kujtesa');
            });
        }

        // Plan form submission
        document.getElementById('planForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            fetch('/api/SubscriptionPlansController.php?action=create', {
                method: 'POST',
                body: new FormData(this)
            })
            .then(r => r.json())
            .then(data => {
                alert(data.message);
                location.reload();
            });
        });

        // Discount form submission
        document.getElementById('discountForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            fetch('/api/DiscountsController.php?action=create', {
                method: 'POST',
                body: new FormData(this)
            })
            .then(r => r.json())
            .then(data => {
                alert(data.message);
                location.reload();
            });
        });
    </script>
</body>
</html>
