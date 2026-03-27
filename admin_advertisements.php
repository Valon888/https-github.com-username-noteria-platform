<?php
// Admin Panel për Menaxhimin e Reklamave - Noteria Platform
// Dizajn Profesional | Video Posting Interface
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

require_once 'config.php';

// Kontrollo nëse përdoruesi është admin
if (!isset($_SESSION['user_id']) || $_SESSION['roli'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Create uploads directory if it doesn't exist
$upload_dir = __DIR__ . '/uploads/ads_videos/';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0755, true);
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        
        // Add/Update Advertisement
        if ($_POST['action'] === 'add_ad') {
            $business_name = trim($_POST['business_name'] ?? '');
            $business_email = trim($_POST['business_email'] ?? '');
            $business_contact = trim($_POST['business_contact'] ?? '');
            $ad_title = trim($_POST['ad_title'] ?? '');
            $ad_description = trim($_POST['ad_description'] ?? '');
            $ad_link = trim($_POST['ad_link'] ?? '');
            $ad_type = $_POST['ad_type'] ?? 'card';
            $video_url = '';
            
            // Handle video upload
            if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['video_file']['tmp_name'];
                $file_name = $_FILES['video_file']['name'];
                $file_size = $_FILES['video_file']['size'];
                
                // Validate file
                $allowed_types = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $file_tmp);
                finfo_close($finfo);
                
                // Max size: 500MB
                if ($file_size > 500 * 1024 * 1024) {
                    $error = "❌ Videja është shumë e madhe! Maksimumi është 500MB.";
                } elseif (!in_array($mime_type, $allowed_types)) {
                    $error = "❌ Formati nuk është i lejuar! Përdor MP4, MOV ose AVI.";
                } else {
                    // Generate unique filename
                    $unique_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_name);
                    $upload_path = $upload_dir . $unique_name;
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        $video_url = 'uploads/ads_videos/' . $unique_name;
                    } else {
                        $error = "❌ Gabim në ngarkimin e videos!";
                    }
                }
            }
            
            if ($business_name && $ad_title && $ad_link && !$error) {
                try {
                    // Check if business already exists
                    $stmt = $pdo->prepare("SELECT id FROM ads WHERE business_name = ? LIMIT 1");
                    $stmt->execute([$business_name]);
                    $existing = $stmt->fetch();
                    
                    if ($existing) {
                        // Update existing
                        if ($video_url) {
                            // Delete old video if exists
                            if (!empty($existing['video_url']) && file_exists($existing['video_url'])) {
                                @unlink($existing['video_url']);
                            }
                        } else {
                            // Keep old video if no new one uploaded
                            $stmt2 = $pdo->prepare("SELECT video_url FROM ads WHERE id = ?");
                            $stmt2->execute([$existing['id']]);
                            $old_data = $stmt2->fetch();
                            $video_url = $old_data['video_url'] ?? '';
                        }
                        
                        $stmt = $pdo->prepare("
                            UPDATE ads SET 
                            ad_title = ?, 
                            ad_description = ?, 
                            ad_link = ?, 
                            ad_type = ?, 
                            video_url = ?,
                            is_active = 1,
                            updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$ad_title, $ad_description, $ad_link, $ad_type, $video_url, $existing['id']]);
                        $message = "✅ Reklama u përditësua me sukses!";
                    } else {
                        // Insert new
                        $stmt = $pdo->prepare("
                            INSERT INTO ads (business_name, business_email, business_contact, ad_title, ad_description, ad_link, ad_type, video_url, is_active)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                        ");
                        $stmt->execute([$business_name, $business_email, $business_contact, $ad_title, $ad_description, $ad_link, $ad_type, $video_url]);
                        $message = "✅ Reklama e shtuar me sukses!";
                    }
                } catch (Exception $e) {
                    $error = "❌ Gabim: " . $e->getMessage();
                }
            } elseif (!$error) {
                $error = "❌ Plotëso të gjithë fushat e detyrueshme!";
            }
        }
        
        // Toggle active status
        if ($_POST['action'] === 'toggle_ad') {
            $ad_id = intval($_POST['ad_id'] ?? 0);
            if ($ad_id) {
                try {
                    $stmt = $pdo->prepare("UPDATE ads SET is_active = NOT is_active WHERE id = ?");
                    $stmt->execute([$ad_id]);
                    $message = "✅ Statusi i reklamës u ndryshua!";
                } catch (Exception $e) {
                    $error = "❌ Gabim: " . $e->getMessage();
                }
            }
        }
        
        // Delete advertisement
        if ($_POST['action'] === 'delete_ad') {
            $ad_id = intval($_POST['ad_id'] ?? 0);
            if ($ad_id) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM ads WHERE id = ?");
                    $stmt->execute([$ad_id]);
                    $message = "✅ Reklama u fshi me sukses!";
                } catch (Exception $e) {
                    $error = "❌ Gabim: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch all advertisements
$ads = [];
try {
    $ads = $pdo->query("SELECT * FROM ads ORDER BY created_at DESC")->fetchAll();
} catch (Exception $e) {
    $error = "Gabim në marrjen e reklamave: " . $e->getMessage();
}

// Calculate statistics
$total_ads = count($ads);
$active_ads = count(array_filter($ads, fn($a) => $a['is_active']));
$total_impressions = array_sum(array_column($ads, 'impressions'));
$total_clicks = array_sum(array_column($ads, 'clicks'));
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menaxhimi i Reklamave | Noteria Admin</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,500,600,700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .header h1 {
            color: #667eea;
            font-size: 2rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header p {
            color: #666;
            font-size: 0.95rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }

        .stat-card h3 {
            font-size: 2.5rem;
            margin-bottom: 5px;
        }

        .stat-card p {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .section {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .section h2 {
            color: #667eea;
            font-size: 1.5rem;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 0.95rem;
        }

        input[type="text"],
        input[type="email"],
        input[type="url"],
        select,
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.95rem;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .video-upload-zone {
            border: 2px dashed #667eea;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 15px;
        }

        .video-upload-zone:hover {
            border-color: #764ba2;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        }

        .video-upload-zone.dragover {
            border-color: #764ba2;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
        }

        .video-upload-zone p {
            color: #667eea;
            font-weight: 600;
            margin: 0;
        }

        .video-upload-zone small {
            color: #999;
            display: block;
            margin-top: 8px;
        }

        .video-preview {
            margin-top: 15px;
            padding: 15px;
            background: #f0f0f0;
            border-radius: 8px;
            display: none;
        }

        .video-preview.show {
            display: block;
        }

        .video-preview video {
            max-width: 100%;
            max-height: 300px;
            border-radius: 6px;
        }

        .upload-progress {
            display: none;
            margin-top: 15px;
        }

        .upload-progress.show {
            display: block;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            width: 0%;
            transition: width 0.3s;
        }

        .progress-text {
            font-size: 0.85rem;
            color: #999;
            margin-top: 5px;
        }

        .ads-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .ad-card {
            background: #f9f9f9;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s;
        }

        .ad-card:hover {
            border-color: #667eea;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.15);
            transform: translateY(-5px);
        }

        .ad-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .ad-header h3 {
            margin: 0 0 5px 0;
            font-size: 1.1rem;
        }

        .ad-header p {
            margin: 0;
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .ad-body {
            padding: 15px;
        }

        .ad-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }

        .info-item {
            background: white;
            padding: 10px;
            border-radius: 6px;
        }

        .info-label {
            color: #999;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .info-value {
            color: #333;
            font-weight: 600;
            margin-top: 3px;
        }

        .ad-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .ad-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
        }

        .btn-small {
            flex: 1;
            padding: 8px 12px;
            font-size: 0.85rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-toggle {
            background: #ffc107;
            color: #333;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-edit {
            background: #17a2b8;
            color: white;
        }

        .btn-small:hover {
            opacity: 0.9;
            transform: scale(1.02);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .ads-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>📢 Menaxhimi i Reklamave</h1>
            <p>Shto, ndrysho dhe menaxho reklamat e bizneseve në platformën Noteria</p>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo $total_ads; ?></h3>
                    <p>Total Reklamash</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $active_ads; ?></h3>
                    <p>Reklamat Aktive</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $total_impressions; ?></h3>
                    <p>Gjithsej Shikime</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $total_clicks; ?></h3>
                    <p>Gjithsej Klika</p>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="message success">✅ <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Add/Edit Advertisement Form -->
        <div class="section">
            <h2>📝 Shto/Ndrysho Reklama me Video</h2>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_ad">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Emri i Biznesit *</label>
                        <input type="text" name="business_name" required placeholder="p.sh. Viva Fresh Store">
                    </div>
                    <div class="form-group">
                        <label>Email i Biznesit *</label>
                        <input type="email" name="business_email" required placeholder="info@business.com">
                    </div>
                    <div class="form-group">
                        <label>Telefoni i Biznesit</label>
                        <input type="text" name="business_contact" placeholder="+383 45 123 456">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Titulli i Reklamës *</label>
                        <input type="text" name="ad_title" required placeholder="🛒 Viva Fresh - Produktet më Të Freskëta Online">
                    </div>
                    <div class="form-group">
                        <label>Lloji i Reklamës *</label>
                        <select name="ad_type" required>
                            <option value="banner">🎬 Banner (Horizontal)</option>
                            <option value="card">📇 Card (Grid)</option>
                            <option value="popup">🔔 Popup Modal</option>
                            <option value="sidebar">📌 Sidebar</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Përshkrimi i Reklamës</label>
                    <textarea name="ad_description" placeholder="Përshkruaj biznesin dhe ofertat tuaja..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>URL i Faqes Web *</label>
                        <input type="url" name="ad_link" required placeholder="https://online.vivafresh.shop/">
                    </div>
                </div>

                <!-- Video Upload Zone -->
                <div class="form-group">
                    <label>🎥 Ngarkoje Videon tuaj (MP4, MOV, AVI - Max 500MB)</label>
                    <div class="video-upload-zone" id="uploadZone" onclick="document.getElementById('videoFile').click()">
                        <p>📤 Zvarrit videon këtu ose kliko për të zgjedhur</p>
                        <small>Formate të lejuara: MP4, MOV, AVI | Madhësia maksimale: 500MB</small>
                        <input type="file" id="videoFile" name="video_file" accept="video/mp4,video/quicktime,video/x-msvideo,.mp4,.mov,.avi" style="display: none;">
                    </div>
                    
                    <!-- Video Preview -->
                    <div class="video-preview" id="videoPreview">
                        <small style="color: #999;">Pamje paraprake:</small>
                        <video id="previewVideo" controls style="margin-top: 10px;"></video>
                        <small style="color: #999; display: block; margin-top: 8px;" id="fileName"></small>
                    </div>
                    
                    <!-- Upload Progress -->
                    <div class="upload-progress" id="uploadProgress">
                        <div class="progress-bar">
                            <div class="progress-fill" id="progressFill"></div>
                        </div>
                        <div class="progress-text" id="progressText">0%</div>
                    </div>
                </div>

                <button type="submit" style="width: 100%; padding: 15px;">➕ Shto Reklama me Video</button>
            </form>
        </div>

        <!-- Advertisements List -->
        <div class="section">
            <h2>📊 Reklamat Aktuale</h2>
            
            <?php if (!empty($ads)): ?>
                <div class="ads-grid">
                    <?php foreach ($ads as $ad): ?>
                        <div class="ad-card">
                            <div class="ad-header">
                                <h3><?php echo htmlspecialchars($ad['ad_title']); ?></h3>
                                <p><?php echo htmlspecialchars($ad['business_name']); ?></p>
                            </div>
                            
                            <div class="ad-body">
                                <div style="margin-bottom: 15px;">
                                    <span class="ad-status <?php echo $ad['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $ad['is_active'] ? '✅ AKTIVE' : '❌ JOAKTIVE'; ?>
                                    </span>
                                </div>

                                <div class="ad-info">
                                    <div class="info-item">
                                        <div class="info-label">Lloji</div>
                                        <div class="info-value"><?php echo ucfirst($ad['ad_type']); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Shikime</div>
                                        <div class="info-value"><?php echo number_format($ad['impressions'] ?? 0); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Klika</div>
                                        <div class="info-value"><?php echo number_format($ad['clicks'] ?? 0); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">CTR</div>
                                        <div class="info-value">
                                            <?php 
                                            $ctr = ($ad['impressions'] > 0) ? round(($ad['clicks'] / $ad['impressions']) * 100, 2) : 0;
                                            echo $ctr . '%';
                                            ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($ad['video_url'])): ?>
                                    <div style="background: #f0f0f0; padding: 10px; border-radius: 6px; margin-top: 10px; font-size: 0.85rem; color: #666;">
                                        🎥 Ka Video: <?php echo htmlspecialchars(substr($ad['video_url'], 0, 50)) . '...'; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="ad-actions">
                                    <form method="POST" style="flex: 1;">
                                        <input type="hidden" name="action" value="toggle_ad">
                                        <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                                        <button type="submit" class="btn-small btn-toggle">
                                            <?php echo $ad['is_active'] ? '⏸️ Deaktivizo' : '▶️ Aktivizo'; ?>
                                        </button>
                                    </form>
                                    <form method="POST" style="flex: 1;">
                                        <input type="hidden" name="action" value="delete_ad">
                                        <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                                        <button type="submit" class="btn-small btn-delete" onclick="return confirm('Je i sigurt?');">
                                            🗑️ Fshi
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>Nuk ka reklamat ende</h3>
                    <p>Shto reklamën tuaj të parë duke plotësuar formularin më lart</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div style="text-align: center; padding: 20px; color: white;">
            <p><a href="dashboard.php" style="color: white; text-decoration: none; font-weight: 600;">← Kthehu në Dashboard</a></p>
        </div>
    </div>

    <script>
    // Video upload handling
    const uploadZone = document.getElementById('uploadZone');
    const videoFile = document.getElementById('videoFile');
    const videoPreview = document.getElementById('videoPreview');
    const previewVideo = document.getElementById('previewVideo');
    const fileName = document.getElementById('fileName');
    const uploadProgress = document.getElementById('uploadProgress');
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');

    // Click to upload
    uploadZone.addEventListener('click', () => videoFile.click());

    // Drag and drop
    uploadZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadZone.classList.add('dragover');
    });

    uploadZone.addEventListener('dragleave', () => {
        uploadZone.classList.remove('dragover');
    });

    uploadZone.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadZone.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            videoFile.files = files;
            handleVideoSelect();
        }
    });

    // File input change
    videoFile.addEventListener('change', handleVideoSelect);

    function handleVideoSelect() {
        const file = videoFile.files[0];
        if (!file) return;

        // Validate file size
        const maxSize = 500 * 1024 * 1024; // 500MB
        if (file.size > maxSize) {
            alert('❌ Videja është shumë e madhe! Maksimumi është 500MB.');
            videoFile.value = '';
            videoPreview.classList.remove('show');
            return;
        }

        // Validate file type
        const allowedTypes = ['video/mp4', 'video/quicktime', 'video/x-msvideo'];
        if (!allowedTypes.includes(file.type)) {
            alert('❌ Formati nuk është i lejuar! Përdor MP4, MOV ose AVI.');
            videoFile.value = '';
            videoPreview.classList.remove('show');
            return;
        }

        // Show preview
        const fileReader = new FileReader();
        fileReader.onload = (e) => {
            previewVideo.src = e.target.result;
            previewVideo.autoplay = true;
            previewVideo.muted = true;
            previewVideo.loop = true;
            fileName.textContent = '📄 ' + file.name + ' (' + formatFileSize(file.size) + ')';
            videoPreview.classList.add('show');
        };
        fileReader.readAsDataURL(file);
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    // Show progress bar while submitting
    const form = document.querySelector('form');
    form.addEventListener('submit', (e) => {
        if (videoFile.files.length > 0) {
            uploadProgress.classList.add('show');
            // Simulate upload progress
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * 30;
                if (progress > 90) progress = 90;
                progressFill.style.width = progress + '%';
                progressText.textContent = Math.round(progress) + '%';
            }, 300);
            setTimeout(() => clearInterval(interval), 4500);
        }
    });
    </script>
</body>
</html>
