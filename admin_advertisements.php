<?php
// filepath: admin_advertisements.php
// Admin panel për menaxhimin e reklamave

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

require_once 'confidb.php';

// Kontrollo nëse përdoruesi është admin
if (!isset($_SESSION['user_id']) || $_SESSION['roli'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_advertiser') {
            $company_name = trim($_POST['company_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            
            if ($company_name && $email) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO advertisers 
                                          (company_name, email, phone, subscription_status, created_at)
                                          VALUES (?, ?, ?, 'pending', NOW())");
                    $stmt->execute([$company_name, $email, $phone]);
                    $message = "Biznes i shtuar me sukses!";
                } catch (Exception $e) {
                    $error = "Gabim: " . $e->getMessage();
                }
            }
        }
        
        if ($_POST['action'] === 'approve_advertiser') {
            $advertiser_id = intval($_POST['advertiser_id'] ?? 0);
            if ($advertiser_id) {
                try {
                    $stmt = $pdo->prepare("UPDATE advertisers 
                                          SET subscription_status = 'active', 
                                              subscription_start = NOW(),
                                              subscription_end = DATE_ADD(NOW(), INTERVAL 30 DAY)
                                          WHERE id = ?");
                    $stmt->execute([$advertiser_id]);
                    $message = "Biznes aktivizuar!";
                } catch (Exception $e) {
                    $error = "Gabim: " . $e->getMessage();
                }
            }
        }
        
        if ($_POST['action'] === 'add_ad') {
            $advertiser_id = intval($_POST['advertiser_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $cta_url = trim($_POST['cta_url'] ?? '');
            $ad_type = $_POST['ad_type'] ?? 'banner';
            
            if ($advertiser_id && $title) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO advertisements 
                                          (advertiser_id, title, description, cta_url, ad_type, status, created_at)
                                          VALUES (?, ?, ?, ?, ?, 'draft', NOW())");
                    $stmt->execute([$advertiser_id, $title, $description, $cta_url, $ad_type]);
                    $ad_id = $pdo->lastInsertId();
                    $message = "Reklama e shtuar! ID: " . $ad_id;
                } catch (Exception $e) {
                    $error = "Gabim: " . $e->getMessage();
                }
            }
        }
        
        if ($_POST['action'] === 'activate_ad') {
            $ad_id = intval($_POST['ad_id'] ?? 0);
            if ($ad_id) {
                try {
                    $stmt = $pdo->prepare("UPDATE advertisements 
                                          SET status = 'active', 
                                              start_date = NOW()
                                          WHERE id = ?");
                    $stmt->execute([$ad_id]);
                    $message = "Reklama aktivizuar!";
                } catch (Exception $e) {
                    $error = "Gabim: " . $e->getMessage();
                }
            }
        }
    }
}

// Get advertisers
$advertisers = [];
try {
    $stmt = $pdo->query("SELECT * FROM advertisers ORDER BY created_at DESC");
    $advertisers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching advertisers: " . $e->getMessage());
}

// Get advertisements
$ads = [];
try {
    $stmt = $pdo->query("SELECT a.*, adv.company_name FROM advertisements a 
                        JOIN advertisers adv ON a.advertiser_id = adv.id
                        ORDER BY a.created_at DESC");
    $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching ads: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menaxhim Reklamash | Noteria Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f7fa; }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .header h1 { font-size: 28px; margin-bottom: 5px; }
        .header p { opacity: 0.9; }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .tab-btn {
            background: none;
            border: none;
            padding: 12px 20px;
            font-size: 15px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            color: #666;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .tab-btn.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .message {
            padding: 15px 20px;
            border-radius: 8px;
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        input, textarea, select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge.active { background: #d4edda; color: #155724; }
        .badge.pending { background: #fff3cd; color: #856404; }
        .badge.draft { background: #e7e7e7; color: #666; }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 28px;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-card p {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-ads"></i> Menaxhim Reklamash</h1>
            <p>Menaxho reklamat dhe biznesat reklamues në platformën Noteria</p>
        </div>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('advertisers')">
                <i class="fas fa-building"></i> Biznesat
            </button>
            <button class="tab-btn" onclick="switchTab('ads')">
                <i class="fas fa-image"></i> Reklamat
            </button>
            <button class="tab-btn" onclick="switchTab('stats')">
                <i class="fas fa-chart-bar"></i> Statistika
            </button>
        </div>
        
        <!-- ADVERTISERS TAB -->
        <div id="advertisers" class="tab-content active">
            <h2>Shto Biznes të Ri</h2>
            <form method="POST" style="max-width: 500px; margin-bottom: 40px;">
                <input type="hidden" name="action" value="add_advertiser">
                
                <div class="form-group">
                    <label>Emri i Kompanisë *</label>
                    <input type="text" name="company_name" required>
                </div>
                
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label>Telefon</label>
                    <input type="tel" name="phone">
                </div>
                
                <button type="submit"><i class="fas fa-plus"></i> Shto Biznesin</button>
            </form>
            
            <h2>Biznesat në Sistem</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Emri</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Data</th>
                        <th>Aksione</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($advertisers as $adv): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($adv['company_name']); ?></td>
                        <td><?php echo htmlspecialchars($adv['email']); ?></td>
                        <td>
                            <span class="badge <?php echo $adv['subscription_status']; ?>">
                                <?php echo ucfirst($adv['subscription_status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('d.m.Y', strtotime($adv['created_at'])); ?></td>
                        <td>
                            <?php if ($adv['subscription_status'] === 'pending'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="approve_advertiser">
                                <input type="hidden" name="advertiser_id" value="<?php echo $adv['id']; ?>">
                                <button type="submit" style="padding: 6px 12px; font-size: 12px;">Aprovo</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- ADS TAB -->
        <div id="ads" class="tab-content">
            <h2>Shto Reklam të Re</h2>
            <form method="POST" style="max-width: 500px; margin-bottom: 40px;">
                <input type="hidden" name="action" value="add_ad">
                
                <div class="form-group">
                    <label>Biznes *</label>
                    <select name="advertiser_id" required>
                        <option value="">-- Zgjedh Biznesin --</option>
                        <?php foreach ($advertisers as $adv): ?>
                            <?php if ($adv['subscription_status'] === 'active'): ?>
                            <option value="<?php echo $adv['id']; ?>">
                                <?php echo htmlspecialchars($adv['company_name']); ?>
                            </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Titulli *</label>
                    <input type="text" name="title" required>
                </div>
                
                <div class="form-group">
                    <label>Përshkrim</label>
                    <textarea name="description" rows="4"></textarea>
                </div>
                
                <div class="form-group">
                    <label>URL Destinacioni</label>
                    <input type="url" name="cta_url" placeholder="https://...">
                </div>
                
                <div class="form-group">
                    <label>Lloji i Reklamës</label>
                    <select name="ad_type">
                        <option value="banner">Banner</option>
                        <option value="sidebar">Sidebar</option>
                        <option value="popup">Popup</option>
                        <option value="native">Native</option>
                        <option value="video">Video</option>
                    </select>
                </div>
                
                <button type="submit"><i class="fas fa-plus"></i> Shto Reklamën</button>
            </form>
            
            <h2>Reklamat në Sistem</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Titulli</th>
                        <th>Biznes</th>
                        <th>Lloji</th>
                        <th>Status</th>
                        <th>Impresione</th>
                        <th>Aksione</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ads as $ad): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ad['title']); ?></td>
                        <td><?php echo htmlspecialchars($ad['company_name']); ?></td>
                        <td><?php echo ucfirst($ad['ad_type']); ?></td>
                        <td>
                            <span class="badge <?php echo $ad['status']; ?>">
                                <?php echo ucfirst($ad['status']); ?>
                            </span>
                        </td>
                        <td><?php echo $ad['total_impressions']; ?></td>
                        <td>
                            <?php if ($ad['status'] === 'draft'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="activate_ad">
                                <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                                <button type="submit" style="padding: 6px 12px; font-size: 12px;">Aktivo</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- STATS TAB -->
        <div id="stats" class="tab-content">
            <h2>Statistika Reklamash</h2>
            <div class="stats">
                <?php
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) FROM advertisers WHERE subscription_status = 'active'");
                    $active_ads = $stmt->fetchColumn();
                    
                    $stmt = $pdo->query("SELECT COUNT(*) FROM advertisements WHERE status = 'active'");
                    $total_ads = $stmt->fetchColumn();
                    
                    $stmt = $pdo->query("SELECT SUM(total_impressions) FROM advertisements");
                    $impressions = $stmt->fetchColumn() ?: 0;
                    
                    $stmt = $pdo->query("SELECT SUM(total_clicks) FROM advertisements");
                    $clicks = $stmt->fetchColumn() ?: 0;
                ?>
                <div class="stat-card">
                    <h3><?php echo $active_ads; ?></h3>
                    <p>Biznesat Aktiv</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $total_ads; ?></h3>
                    <p>Reklamat Aktive</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($impressions); ?></h3>
                    <p>Impresione Totale</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($clicks); ?></h3>
                    <p>Klika Totale</p>
                </div>
                <?php
                } catch (Exception $e) {
                    echo "Gabim në hentjen e statistikave";
                }
                ?>
            </div>
        </div>
    </div>
    
    <script>
    function switchTab(tabName) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById(tabName).classList.add('active');
        event.target.classList.add('active');
    }
    </script>
</body>
</html>
