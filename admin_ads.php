<?php
/**
 * Admin Panel për Menaxhimin e Reklamave
 * Vetëm adminët mund të aksesojnë këtë faqe
 */

// Fillimi i sesionit
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kontrollimi i autentifikimit
if (!isset($_SESSION['user_id']) || $_SESSION['roli'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'confidb.php';

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    die("Gabim në lidhjen me databazën: " . htmlspecialchars($e->getMessage()));
}

// ==========================================
// PROCESIMI I FORMËS - SHTIM REKLAME
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_advertiser'])) {
    $company_name = trim($_POST['company_name']);
    $contact_email = trim($_POST['contact_email']);
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $website_url = trim($_POST['website_url'] ?? '');
    
    if ($company_name && $contact_email && filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO advertisers (company_name, contact_email, contact_phone, website_url) 
                                   VALUES (?, ?, ?, ?)");
            $stmt->execute([$company_name, $contact_email, $contact_phone, $website_url]);
            $success = "Biznesi u shtua me sukses!";
        } catch (PDOException $e) {
            $error = "Ky biznes ekziston tashmë!";
        }
    } else {
        $error = "Ju lutemi plotësoni të gjitha fushat e detyruara.";
    }
}

// ==========================================
// SHFAQJA E REKLAMAVE
// ==========================================
$action = $_GET['action'] ?? 'list';

if ($action === 'add_ad') {
    // Merr bizneset aktive
    $stmt = $pdo->query("SELECT id, company_name FROM advertisers WHERE subscription_status = 'active' ORDER BY company_name");
    $advertisers = $stmt->fetchAll();
}

if ($action === 'edit_ad' && isset($_GET['id'])) {
    $ad_id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM advertisements WHERE id = ?");
    $stmt->execute([$ad_id]);
    $ad = $stmt->fetch();
    if (!$ad) {
        die("Reklama nuk u gjet!");
    }
}

// Shtimi i reklamës
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ad'])) {
    $advertiser_id = intval($_POST['advertiser_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $image_url = trim($_POST['image_url'] ?? '');
    $cta_url = trim($_POST['cta_url']);
    $ad_type = $_POST['ad_type'] ?? 'card';
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'] ?? null;
    $placement = $_POST['placement'] ?? 'dashboard_sidebar';
    $target_role = $_POST['target_role'] ?? 'all';
    
    if ($advertiser_id && $title && $cta_url && $start_date) {
        try {
            // Shto reklamën
            $stmt = $pdo->prepare("INSERT INTO advertisements 
                                   (advertiser_id, title, description, image_url, cta_url, ad_type, start_date, end_date, status)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$advertiser_id, $title, $description, $image_url, $cta_url, $ad_type, $start_date, $end_date]);
            
            $new_ad_id = $pdo->lastInsertId();
            
            // Shto placement-in
            $stmt = $pdo->prepare("INSERT INTO ad_placements (ad_id, placement_location, target_role, order_priority, enabled)
                                   VALUES (?, ?, ?, 0, 1)");
            $stmt->execute([$new_ad_id, $placement, $target_role]);
            
            $success = "Reklama u shtua me sukses!";
        } catch (PDOException $e) {
            $error = "Gabim: " . $e->getMessage();
        }
    } else {
        $error = "Ju lutemi plotësoni të gjitha fushat e detyruara.";
    }
}

// Përditësim reklamë
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_ad'])) {
    $ad_id = intval($_POST['ad_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $cta_url = trim($_POST['cta_url']);
    $status = $_POST['status'] ?? 'active';
    $end_date = $_POST['end_date'] ?? null;
    
    if ($ad_id && $title && $cta_url) {
        try {
            $stmt = $pdo->prepare("UPDATE advertisements 
                                   SET title=?, description=?, cta_url=?, status=?, end_date=?
                                   WHERE id=?");
            $stmt->execute([$title, $description, $cta_url, $status, $end_date, $ad_id]);
            $success = "Reklama u përditësua me sukses!";
        } catch (PDOException $e) {
            $error = "Gabim: " . $e->getMessage();
        }
    }
}

// Fshirja e reklamës
if ($_GET['action'] === 'delete' && isset($_GET['id'])) {
    $ad_id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM advertisements WHERE id = ?");
        $stmt->execute([$ad_id]);
        $success = "Reklama u fshi me sukses!";
    } catch (PDOException $e) {
        $error = "Gabim: " . $e->getMessage();
    }
    // Ridrejto në listë
    header("Location: admin_ads.php");
    exit();
}

// Merr të gjitha reklamat me detajet e reklamuesit
if ($action === 'list' || $action === 'add_ad' || $action === 'edit_ad') {
    $stmt = $pdo->query("SELECT a.*, adv.company_name, ap.placement_location, ap.target_role
                        FROM advertisements a
                        LEFT JOIN advertisers adv ON a.advertiser_id = adv.id
                        LEFT JOIN ad_placements ap ON a.id = ap.ad_id
                        ORDER BY a.created_at DESC");
    $ads = $stmt->fetchAll();
}

// Merr të gjitha bizneset e reklamuesve
$stmt = $pdo->query("SELECT id, company_name, contact_email, subscription_status FROM advertisers ORDER BY company_name");
$all_advertisers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Menaxhimi i Reklamave | Noteria</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            background: linear-gradient(135deg, #e2eafc 0%, #f8fafc 100%);
            color: #333;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(45, 108, 223, 0.12);
            padding: 30px;
        }
        
        h1 {
            color: #2d6cdf;
            margin-bottom: 30px;
            border-bottom: 3px solid #2d6cdf;
            padding-bottom: 15px;
        }
        
        h2 {
            color: #184fa3;
            margin-top: 30px;
            margin-bottom: 20px;
            font-size: 1.3rem;
        }
        
        .success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 5px solid #4caf50;
        }
        
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 5px solid #f44336;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #2d6cdf;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            margin: 5px;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #184fa3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(45, 108, 223, 0.3);
        }
        
        .btn-danger {
            background: #d32f2f;
        }
        
        .btn-danger:hover {
            background: #b71c1c;
        }
        
        .btn-success {
            background: #388e3c;
        }
        
        .btn-success:hover {
            background: #2e7d32;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #184fa3;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="url"],
        input[type="date"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2eafc;
            border-radius: 6px;
            font-family: inherit;
            font-size: 1rem;
        }
        
        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #2d6cdf;
            box-shadow: 0 0 0 3px rgba(45, 108, 223, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row > div {
            flex: 1;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th {
            background: #2d6cdf;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #e2eafc;
        }
        
        tr:hover {
            background: #f8fafc;
        }
        
        .status-active {
            color: #388e3c;
            font-weight: 600;
        }
        
        .status-paused {
            color: #f57c00;
            font-weight: 600;
        }
        
        .status-draft {
            color: #999;
            font-weight: 600;
        }
        
        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e2eafc;
        }
        
        .nav-tab {
            padding: 12px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: #888;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .nav-tab.active {
            color: #2d6cdf;
            border-bottom-color: #2d6cdf;
        }
        
        .nav-tab:hover {
            color: #2d6cdf;
        }
        
        .ad-preview {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            border: 2px solid #e2eafc;
        }
        
        .ad-preview img {
            max-width: 100%;
            height: auto;
            border-radius: 6px;
            margin-bottom: 10px;
        }
        
        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎯 Menaxhimi i Reklamave</h1>
        
        <?php if (isset($success)): ?>
            <div class="success">✅ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Navigation Tabs -->
        <div class="nav-tabs">
            <button class="nav-tab <?php echo $action === 'list' ? 'active' : ''; ?>" onclick="location.href='admin_ads.php?action=list'">📋 Lista e Reklamave</button>
            <button class="nav-tab <?php echo $action === 'add_ad' ? 'active' : ''; ?>" onclick="location.href='admin_ads.php?action=add_ad'">➕ Shtim Reklame</button>
            <button class="nav-tab <?php echo $action === 'advertisers' ? 'active' : ''; ?>" onclick="location.href='admin_ads.php?action=advertisers'">🏢 Bizneset e Reklamuesve</button>
        </div>
        
        <!-- Lista e Reklamave -->
        <?php if ($action === 'list'): ?>
            <h2>Të Gjitha Reklamat</h2>
            <?php if (empty($ads)): ?>
                <div class="error">Nuk ka reklama. <a href="?action=add_ad" class="btn">Shto një reklam</a></div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Titulli</th>
                            <th>Biznesi</th>
                            <th>Tipi</th>
                            <th>Statusi</th>
                            <th>Data Fillimi</th>
                            <th>Data Përfundimi</th>
                            <th>Placement</th>
                            <th>Aksionet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ads as $ad): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($ad['title']); ?></strong></td>
                                <td><?php echo htmlspecialchars($ad['company_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($ad['ad_type']); ?></td>
                                <td><span class="status-<?php echo $ad['status']; ?>"><?php echo ucfirst($ad['status']); ?></span></td>
                                <td><?php echo htmlspecialchars($ad['start_date']); ?></td>
                                <td><?php echo htmlspecialchars($ad['end_date'] ?? 'Përjetë'); ?></td>
                                <td><?php echo htmlspecialchars($ad['placement_location'] ?? 'N/A'); ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="?action=edit_ad&id=<?php echo $ad['id']; ?>" class="btn" style="background: #f57c00;">Redakto</a>
                                        <a href="?action=delete&id=<?php echo $ad['id']; ?>" class="btn btn-danger" onclick="return confirm('Jeni i sigurt?');">Fshi</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Shtim Reklame -->
        <?php if ($action === 'add_ad'): ?>
            <h2>➕ Shtim Reklame të Re</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Biznesi Reklamues *</label>
                        <select name="advertiser_id" required>
                            <option value="">Zgjidhni biznesin...</option>
                            <?php foreach ($advertisers as $adv): ?>
                                <option value="<?php echo $adv['id']; ?>"><?php echo htmlspecialchars($adv['company_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Titulli i Reklamës *</label>
                    <input type="text" name="title" required placeholder="p.sh. Shërbimet Bankare të Sigurta">
                </div>
                
                <div class="form-group">
                    <label>Përshkrimi</label>
                    <textarea name="description" placeholder="Përshkrimi i detajuar i reklamës..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>URL-i i Imazhit</label>
                    <input type="url" name="image_url" placeholder="https://example.com/image.jpg">
                </div>
                
                <div class="form-group">
                    <label>URL-i i Lidhjes (CTA) *</label>
                    <input type="url" name="cta_url" required placeholder="https://example.com">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Tipi i Reklamës</label>
                        <select name="ad_type">
                            <option value="card">Kartë</option>
                            <option value="banner">Banner</option>
                            <option value="modal">Modal</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Placement</label>
                        <select name="placement">
                            <option value="dashboard_sidebar">Dashboard Sidebar</option>
                            <option value="dashboard_main">Dashboard Kryesor</option>
                            <option value="reservation_page">Faqja e Rezervimit</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Shfaqet për të Cilat Rolet</label>
                        <select name="target_role">
                            <option value="all">Të Gjithë</option>
                            <option value="user">Përdorues</option>
                            <option value="noter">Noter</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Data Fillimi *</label>
                        <input type="date" name="start_date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Data Përfundimi</label>
                        <input type="date" name="end_date">
                    </div>
                </div>
                
                <button type="submit" name="add_ad" class="btn btn-success" style="width: 100%; padding: 15px; font-size: 1.1rem;">✅ Shtimi i Reklamës</button>
            </form>
        <?php endif; ?>
        
        <!-- Redaktim Reklame -->
        <?php if ($action === 'edit_ad' && isset($ad)): ?>
            <h2>✏️ Redaktim Reklame</h2>
            <form method="POST">
                <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                
                <div class="form-group">
                    <label>Titulli *</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($ad['title']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Përshkrimi</label>
                    <textarea name="description"><?php echo htmlspecialchars($ad['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>URL-i i Lidhjes (CTA) *</label>
                    <input type="url" name="cta_url" value="<?php echo htmlspecialchars($ad['cta_url']); ?>" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Statusi</label>
                        <select name="status">
                            <option value="active" <?php echo $ad['status'] === 'active' ? 'selected' : ''; ?>>Aktive</option>
                            <option value="paused" <?php echo $ad['status'] === 'paused' ? 'selected' : ''; ?>>E Ndërprerë</option>
                            <option value="draft" <?php echo $ad['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Data Përfundimi</label>
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($ad['end_date'] ?? ''); ?>">
                    </div>
                </div>
                
                <button type="submit" name="edit_ad" class="btn btn-success" style="width: 100%; padding: 15px; font-size: 1.1rem;">✅ Ruaje Ndryshimet</button>
            </form>
        <?php endif; ?>
        
        <!-- Menaxhimi i Bizneseve -->
        <?php if ($action === 'advertisers'): ?>
            <h2>🏢 Bizneset e Reklamuesve</h2>
            
            <h3 style="margin-top: 20px;">Shtim Biznesi të Ri</h3>
            <form method="POST" style="background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
                <div class="form-row">
                    <div class="form-group">
                        <label>Emri i Biznesit *</label>
                        <input type="text" name="company_name" required placeholder="p.sh. Banka e Kosovës">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Email i Kontaktit *</label>
                        <input type="email" name="contact_email" required placeholder="contact@example.com">
                    </div>
                    
                    <div class="form-group">
                        <label>Telefoni</label>
                        <input type="text" name="contact_phone" placeholder="+383 (44) 123 456">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Faqja e Uebit</label>
                    <input type="url" name="website_url" placeholder="https://example.com">
                </div>
                
                <button type="submit" name="add_advertiser" class="btn btn-success">➕ Shto Biznesin</button>
            </form>
            
            <table>
                <thead>
                    <tr>
                        <th>Emri i Biznesit</th>
                        <th>Email</th>
                        <th>Telefoni</th>
                        <th>Statusi</th>
                        <th>Data Fillesës</th>
                        <th>Data Përfundimit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_advertisers as $advertiser): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($advertiser['company_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($advertiser['contact_email']); ?></td>
                            <td><?php echo htmlspecialchars($advertiser['contact_phone'] ?? 'N/A'); ?></td>
                            <td><span class="status-<?php echo $advertiser['subscription_status']; ?>"><?php echo ucfirst($advertiser['subscription_status']); ?></span></td>
                            <td><?php echo htmlspecialchars($advertiser['subscription_start'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($advertiser['subscription_end'] ?? 'Përjetë'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div style="margin-top: 40px; text-align: center;">
            <a href="dashboard.php" class="btn" style="background: #888;">← Kthehu në Dashboard</a>
        </div>
    </div>
</body>
</html>
