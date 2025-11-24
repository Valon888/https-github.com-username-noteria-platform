<?php
// filepath: business_advertising.php
// Faqe për bizneset të managojnë reklamat

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'confidb.php';

// Check if user is logged in and is an advertiser
$advertiser = null;
$advertiser_id = null;

if (isset($_SESSION['user_id'])) {
    // Check if user is an advertiser
    $stmt = $pdo->prepare("SELECT * FROM advertisers WHERE email = ?");
    $stmt->execute([$_SESSION['email'] ?? '']);
    $advertiser = $stmt->fetch();
    if ($advertiser) {
        $advertiser_id = $advertiser['id'];
    }
}

// If not advertiser and not admin, redirect
if (!$advertiser_id && ($_SESSION['roli'] ?? 'user') !== 'admin') {
    // Allow registration as advertiser
}

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['register_advertiser'])) {
        $company_name = trim($_POST['company_name'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $logo_url = trim($_POST['logo_url'] ?? '');
        
        if ($company_name) {
            try {
                // Check if already registered
                $stmt = $pdo->prepare("SELECT id FROM advertisers WHERE email = ?");
                $stmt->execute([$_SESSION['email'] ?? 'guest@noteria.al']);
                
                if (!$stmt->fetch()) {
                    $stmt = $pdo->prepare("INSERT INTO advertisers 
                                          (company_name, email, website, logo_url, subscription_status, created_at)
                                          VALUES (?, ?, ?, ?, 'pending', NOW())");
                    $stmt->execute([
                        $company_name,
                        $_SESSION['email'] ?? 'guest@noteria.al',
                        $website,
                        $logo_url
                    ]);
                    
                    $advertiser_id = $pdo->lastInsertId();
                    $message = "Biznesin u regjistrua me sukses! Në pritje të aprovimit nga administrata.";
                } else {
                    $error = "Email-i tashmë është i regjistruar si biznes.";
                }
            } catch (Exception $e) {
                $error = "Gabim: " . $e->getMessage();
            }
        }
    }
    
    if (isset($_POST['submit_ad']) && $advertiser_id) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $image_url = trim($_POST['image_url'] ?? '');
        $cta_text = trim($_POST['cta_text'] ?? 'Vizito');
        $cta_url = trim($_POST['cta_url'] ?? '');
        $ad_type = $_POST['ad_type'] ?? 'banner';
        
        if ($title && $cta_url) {
            try {
                $stmt = $pdo->prepare("INSERT INTO advertisements 
                                      (advertiser_id, title, description, image_url, cta_text, cta_url, ad_type, status, created_at)
                                      VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', NOW())");
                $stmt->execute([
                    $advertiser_id,
                    $title,
                    $description,
                    $image_url,
                    $cta_text,
                    $cta_url,
                    $ad_type
                ]);
                
                $message = "Reklama u shtua! Në pritje të aprovimit.";
            } catch (Exception $e) {
                $error = "Gabim: " . $e->getMessage();
            }
        }
    }
}

// Get advertiser's ads if logged in as advertiser
$ads = [];
if ($advertiser_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM advertisements WHERE advertiser_id = ? ORDER BY created_at DESC");
        $stmt->execute([$advertiser_id]);
        $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching ads: " . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reklamoni në Noteria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f7fa; }
        
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .header h1 { font-size: 32px; margin-bottom: 10px; }
        .header p { font-size: 16px; opacity: 0.9; }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
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
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: inherit;
            font-size: 14px;
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .message {
            padding: 15px;
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
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .feature {
            text-align: center;
            padding: 20px;
        }
        
        .feature i {
            font-size: 40px;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
        }
        
        .table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #e7e7e7;
            color: #666;
        }
        
        .badge.active { background: #d4edda; color: #155724; }
        .badge.draft { background: #fff3cd; color: #856404; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-megaphone"></i> Reklamoni në Noteria</h1>
            <p>Zgjeri biznesin tënd dhe arrit miliona përdoruesit e platformës</p>
        </div>
        
        <?php if ($message): ?>
            <div class="message success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (!$advertiser_id): ?>
        <!-- REGISTRATION FORM -->
        <div class="card">
            <h2><i class="fas fa-briefcase"></i> Regjistro Biznesin Tënd</h2>
            <p style="margin-bottom: 20px; color: #666;">Krijoni një llogari reklamimi për të filluar</p>
            
            <form method="POST">
                <input type="hidden" name="register_advertiser" value="1">
                
                <div class="form-group">
                    <label>Emri i Kompanisë *</label>
                    <input type="text" name="company_name" required placeholder="p.sh. TechStudio Albania">
                </div>
                
                <div class="form-group">
                    <label>Faqja e Internetit</label>
                    <input type="url" name="website" placeholder="https://www.shembull.com">
                </div>
                
                <div class="form-group">
                    <label>URL i Logos</label>
                    <input type="url" name="logo_url" placeholder="https://www.shembull.com/logo.png">
                </div>
                
                <button type="submit"><i class="fas fa-user-plus"></i> Regjistrohu si Biznes</button>
            </form>
        </div>
        
        <?php else: ?>
        <!-- FEATURES -->
        <div class="feature-grid">
            <div class="feature">
                <i class="fas fa-users"></i>
                <h3>Milionda Përdorues</h3>
                <p>Arrit audiencën më të madhe në platformën Noteria</p>
            </div>
            <div class="feature">
                <i class="fas fa-chart-line"></i>
                <h3>Analytics Real-Time</h3>
                <p>Shiko statistikat e reklamave në kohë reale</p>
            </div>
            <div class="feature">
                <i class="fas fa-zap"></i>
                <h3>Rezultate të Shpejta</h3>
                <p>Kampanjet tuaja ndizen njëherësh me shfaqje të menjëhershme</p>
            </div>
        </div>
        
        <!-- NEW AD FORM -->
        <div class="card">
            <h2><i class="fas fa-image"></i> Shto Reklam të Re</h2>
            
            <form method="POST">
                <input type="hidden" name="submit_ad" value="1">
                
                <div class="form-group">
                    <label>Titulli *</label>
                    <input type="text" name="title" required placeholder="Titulli i reklamës">
                </div>
                
                <div class="form-group">
                    <label>Përshkrim</label>
                    <textarea name="description" rows="4" placeholder="Përshkrimi i detajuar i reklamës..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>URL i Imazhit</label>
                    <input type="url" name="image_url" placeholder="https://...">
                </div>
                
                <div class="form-group">
                    <label>Teksti i Butonit</label>
                    <input type="text" name="cta_text" value="Vizito" placeholder="Vizito, Mëso më shumë, etj.">
                </div>
                
                <div class="form-group">
                    <label>URL Destinacioni *</label>
                    <input type="url" name="cta_url" required placeholder="https://...">
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
                
                <button type="submit"><i class="fas fa-plus-circle"></i> Shto Reklamën</button>
            </form>
        </div>
        
        <!-- ADS LIST -->
        <?php if (!empty($ads)): ?>
        <div class="card">
            <h2><i class="fas fa-list"></i> Reklamat Tuaja</h2>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Titulli</th>
                        <th>Lloji</th>
                        <th>Status</th>
                        <th>Impresione</th>
                        <th>Klika</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ads as $ad): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ad['title']); ?></td>
                        <td><?php echo ucfirst($ad['ad_type']); ?></td>
                        <td>
                            <span class="badge <?php echo $ad['status']; ?>">
                                <?php echo ucfirst($ad['status']); ?>
                            </span>
                        </td>
                        <td><?php echo $ad['total_impressions']; ?></td>
                        <td><?php echo $ad['total_clicks']; ?></td>
                        <td><?php echo date('d.m.Y', strtotime($ad['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
        
        <!-- INFO SECTION -->
        <div class="card" style="text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <h2 style="color: white;"><i class="fas fa-question-circle"></i> Pyetje?</h2>
            <p style="color: rgba(255,255,255,0.9); margin-bottom: 15px;">Kontaktoni ekipin e reklamimit në advertising@noteria.al</p>
            <a href="mailto:advertising@noteria.al" style="display: inline-block; background: white; color: #667eea; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600;">Shkruani Email</a>
        </div>
    </div>
</body>
</html>
