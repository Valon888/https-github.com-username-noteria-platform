<?php
/**
 * Ads Widget - Shfaq reklamat në të gjithë faqet
 * Përfshirë: tracking, responsive design, multiple ad types
 */

function get_active_ads($pdo, $limit = 3) {
    try {
        // Kontrollo nëse tabela ekziston
        $tables = $pdo->query("SHOW TABLES LIKE 'ads'")->fetchAll();
        if (empty($tables)) {
            error_log("Ads table does not exist!");
            return [];
        }
        
        $stmt = $pdo->prepare("
            SELECT * FROM ads 
            WHERE is_active = 1 
            AND (end_date IS NULL OR end_date > NOW())
            ORDER BY RAND()
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching ads: " . $e->getMessage());
        return [];
    }
}

function track_ad_impression($pdo, $ad_id) {
    try {
        // Update impressions count
        $stmt = $pdo->prepare("UPDATE ads SET impressions = impressions + 1 WHERE id = ?");
        $stmt->execute([$ad_id]);
        
        // Log interaction
        $stmt = $pdo->prepare("
            INSERT INTO ad_interactions (ad_id, interaction_type, user_ip, user_agent, referer) 
            VALUES (?, 'impression', ?, ?, ?)
        ");
        $stmt->execute([
            $ad_id,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_REFERER'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Error tracking impression: " . $e->getMessage());
    }
}

function track_ad_click($pdo, $ad_id) {
    try {
        // Update clicks count
        $stmt = $pdo->prepare("UPDATE ads SET clicks = clicks + 1 WHERE id = ?");
        $stmt->execute([$ad_id]);
        
        // Log interaction
        $stmt = $pdo->prepare("
            INSERT INTO ad_interactions (ad_id, interaction_type, user_ip, user_agent, referer) 
            VALUES (?, 'click', ?, ?, ?)
        ");
        $stmt->execute([
            $ad_id,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_REFERER'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Error tracking click: " . $e->getMessage());
    }
}

function render_ads_banner($pdo) {
    $ads = get_active_ads($pdo, 1);
    if (empty($ads)) return '';
    
    $ad = $ads[0];
    track_ad_impression($pdo, $ad['id']);
    
    // Determine background media (Video or Image)
    $background_media = '';
    if (!empty($ad['video_url'])) {
        // Debug: Log the video URL
        error_log("Video URL from DB: " . $ad['video_url']);
        
        $video_src = $ad['video_url'];
        
        // Clean up the path - remove any leading slashes for relative paths
        $video_src = trim($video_src);
        
        // If path doesn't start with http, treat it as relative
        if (strpos($video_src, 'http') !== 0) {
            // Make sure it's a relative path without leading slash
            $video_src = ltrim($video_src, '/');
            // Add the domain if needed
            if (!empty($_SERVER['HTTP_HOST'])) {
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                $video_src = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/' . $video_src;
            }
        }
        
        $video_src = htmlspecialchars($video_src);
        
        $background_media = '
        <video autoplay muted loop playsinline style="
            position: absolute;
            top: 50%;
            left: 50%;
            width: 120%;
            height: 120%;
            transform: translateX(-50%) translateY(-50%);
            object-fit: cover;
            z-index: 0;
            filter: brightness(1.05) contrast(1.1);
        " onerror="console.error(\'Video failed to load: ' . $video_src . '\')">
            <source src="' . $video_src . '" type="video/mp4">
            Your browser does not support the video tag.
        </video>';
    } elseif (!empty($ad['image_url'])) {
        $background_media = '
        <div style="
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url(\'' . htmlspecialchars($ad['image_url']) . '\');
            background-size: cover;
            background-position: center;
            z-index: 0;
            transition: transform 0.5s ease;
            filter: brightness(1.05) contrast(1.1);
        " class="ad-bg-image"></div>';
    } else {
        // Fallback gradient if no media
        $background_media = '<div style="position:absolute;top:0;left:0;width:100%;height:100%;background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);z-index:0;"></div>';
    }
    
    $html = '
    <div class="promo-banner" style="
        position: relative;
        width: 100%;
        height: 320px;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 20px 45px rgba(0,0,0,0.18), inset 0 1px 0 rgba(255,255,255,0.1);
        margin: 25px 0;
        font-family: \'Montserrat\', sans-serif;
        transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        backdrop-filter: blur(10px);
    " onmouseover="this.style.transform=\'translateY(-6px) scale(1.01)\'; this.style.boxShadow=\'0 30px 60px rgba(0,0,0,0.25), inset 0 1px 0 rgba(255,255,255,0.1)\';" 
      onmouseout="this.style.transform=\'translateY(0) scale(1)\'; this.style.boxShadow=\'0 20px 45px rgba(0,0,0,0.18), inset 0 1px 0 rgba(255,255,255,0.1)\';">
        
        ' . $background_media . '
        
        <!-- Advanced Gradient Overlay with Animation -->
        <div style="
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.5) 40%, rgba(0,0,0,0.15) 100%);
            z-index: 1;
            transition: background 0.5s ease;
        " class="ad-overlay"></div>
        
        <!-- Particle Effect Elements -->
        <div style="
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            filter: blur(40px);
            z-index: 1;
            animation: float 6s ease-in-out infinite;
        "></div>
        
        <!-- Content -->
        <div style="
            position: relative;
            z-index: 2;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 40px;
            max-width: 580px;
            color: white;
        ">
            <span style="
                background: linear-gradient(135deg, #ff4757 0%, #ff6348 100%);
                color: white;
                padding: 6px 14px;
                border-radius: 50px;
                font-size: 0.75rem;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 1.2px;
                align-self: flex-start;
                margin-bottom: 14px;
                box-shadow: 0 6px 15px rgba(255, 71, 87, 0.35);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255,255,255,0.2);
            ">⚡ Ekskluzive</span>
            
            <h2 style="
                font-size: 2.6rem;
                font-weight: 900;
                margin: 0 0 12px 0;
                line-height: 1.1;
                text-shadow: 0 3px 15px rgba(0,0,0,0.4);
                letter-spacing: -0.5px;
            ">' . htmlspecialchars($ad['ad_title']) . '</h2>
            
            <div style="
                width: 50px;
                height: 3px;
                background: linear-gradient(to right, #ff4757, transparent);
                margin-bottom: 14px;
                border-radius: 2px;
            "></div>
            
            <p style="
                font-size: 1rem;
                line-height: 1.6;
                margin: 0 0 24px 0;
                opacity: 0.93;
                text-shadow: 0 1px 6px rgba(0,0,0,0.3);
                letter-spacing: 0.2px;
            ">' . htmlspecialchars(substr($ad['ad_description'], 0, 130)) . (strlen($ad['ad_description']) > 130 ? '...' : '') . '</p>
            
            <a href="' . htmlspecialchars($ad['ad_link']) . '" onclick="trackAdClick(' . $ad['id'] . ')" target="_blank" style="
                background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
                color: #111;
                padding: 12px 32px;
                border-radius: 50px;
                text-decoration: none;
                font-weight: 800;
                font-size: 0.95rem;
                display: inline-flex;
                align-items: center;
                gap: 10px;
                transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
                align-self: flex-start;
                box-shadow: 0 10px 25px rgba(255,255,255,0.25), 0 0 25px rgba(255,71,87,0.15);
                border: 1px solid rgba(255,255,255,0.3);
                position: relative;
                overflow: hidden;
            " onmouseover="this.style.transform=\'scale(1.06) translateY(-2px)\'; this.style.boxShadow=\'0 15px 35px rgba(255,255,255,0.35), 0 0 35px rgba(255,71,87,0.25)\';" 
              onmouseout="this.style.transform=\'scale(1) translateY(0)\'; this.style.boxShadow=\'0 10px 25px rgba(255,255,255,0.25), 0 0 25px rgba(255,71,87,0.15)\';">
                Zbulo Më Shumë <span style="font-size: 1.2em; transition: all 0.3s ease;">→</span>
            </a>
        </div>
    </div>
    
    <style>
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(20px) rotate(5deg); }
        }
        
        .promo-banner:hover .ad-overlay {
            background: linear-gradient(135deg, rgba(0,0,0,0.75) 0%, rgba(0,0,0,0.4) 40%, rgba(0,0,0,0.05) 100%) !important;
        }
        
        @media (max-width: 768px) {
            .promo-banner { height: 280px !important; }
            .promo-banner h2 { font-size: 1.8rem !important; }
            .promo-banner p { font-size: 0.9rem !important; }
        }
    </style>';
    
    return $html;
}

function render_ads_cards($pdo, $limit = 3) {
    $ads = get_active_ads($pdo, $limit);
    if (empty($ads)) return '';
    
    foreach ($ads as $ad) {
        track_ad_impression($pdo, $ad['id']);
    }
    
    $html = '<div class="ads-cards-container" style="
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin: 30px 0;
        width: 100%;
    ">';
    
    foreach ($ads as $ad) {
        $media_html = '';
        
        // Check if ad has video - only local videos with autoplay
        if (!empty($ad['video_url'])) {
            $media_html = '<video style="width:100%;height:100%;object-fit:cover;" autoplay muted loop><source src="' . htmlspecialchars($ad['video_url']) . '" type="video/mp4"></video>';
        }
        
        if (empty($media_html)) {
            $media_html = (!empty($ad['image_url']) ? '<img src="' . htmlspecialchars($ad['image_url']) . '" style="width:100%;height:100%;object-fit:cover;" alt="Ad">' : '<div style="font-size:60px;opacity:0.3;">📢</div>');
        }
        
        $html .= '
        <div class="ad-card" style="
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border: 2px solid transparent;
        " onmouseover="this.style.boxShadow=\'0 20px 40px rgba(102, 126, 234, 0.25)\';this.style.transform=\'translateY(-8px)\';this.style.borderColor=\'#667eea\';" onmouseout="this.style.boxShadow=\'0 4px 15px rgba(0,0,0,0.1)\';this.style.transform=\'translateY(0)\';this.style.borderColor=\'transparent\';">
            <div style="width:100%;height:200px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative;">
                ' . $media_html . '
                <div style="position:absolute;top:10px;right:10px;background:linear-gradient(135deg,#667eea,#764ba2);color:white;padding:6px 12px;border-radius:20px;font-size:0.8rem;font-weight:700;">REKLAMA</div>
            </div>
            <div style="padding:20px;">
                <h4 style="margin:0 0 10px 0;font-size:1.15rem;color:#1f2937;font-weight:700;line-height:1.4;">' . htmlspecialchars($ad['ad_title']) . '</h4>
                <p style="margin:0 0 15px 0;font-size:0.95rem;color:#6b7280;line-height:1.6;min-height:60px;">' . htmlspecialchars(substr($ad['ad_description'], 0, 100)) . '...</p>
                <a href="' . htmlspecialchars($ad['ad_link']) . '" onclick="trackAdClick(' . $ad['id'] . ')" target="_blank" style="
                    display: inline-block;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 10px 20px;
                    border-radius: 8px;
                    text-decoration: none;
                    font-weight: 700;
                    font-size: 0.95rem;
                    transition: all 0.3s;
                    border: none;
                    cursor: pointer;
                " onmouseover="this.style.transform=\'scale(1.08)\';this.style.boxShadow=\'0 8px 16px rgba(102, 126, 234, 0.3)\';" onmouseout="this.style.transform=\'scale(1)\';this.style.boxShadow=\'none\';">
                    ➜ Shiko më shumë
                </a>
            </div>
        </div>';
    }
    
    $html .= '</div>';
    return $html;
}

function render_ads_sidebar($pdo, $limit = 5) {
    $ads = get_active_ads($pdo, $limit);
    if (empty($ads)) return '';
    
    foreach ($ads as $ad) {
        track_ad_impression($pdo, $ad['id']);
    }
    
    $html = '<div class="ads-sidebar" style="
        background: #f9fafb;
        padding: 15px;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
    ">
        <h3 style="margin:0 0 15px 0;font-size:1rem;color:#1f2937;display:flex;align-items:center;gap:8px;">
            📢 Ofertat e Partnerëve
        </h3>';
    
    foreach ($ads as $ad) {
        $html .= '
        <div style="
            background: white;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 12px;
            border-left: 4px solid #667eea;
            cursor: pointer;
            transition: all 0.2s;
        " onmouseover="this.style.boxShadow=\'0 2px 8px rgba(0,0,0,0.1)\';" onmouseout="this.style.boxShadow=\'none\';">
            <a href="' . htmlspecialchars($ad['ad_link']) . '" onclick="trackAdClick(' . $ad['id'] . ')" style="
                text-decoration: none;
                color: inherit;
                display: block;
            ">
                <h4 style="margin:0 0 4px 0;font-size:0.95rem;color:#1f2937;font-weight:600;">' . htmlspecialchars($ad['ad_title']) . '</h4>
                <p style="margin:0;font-size:0.85rem;color:#6b7280;">' . htmlspecialchars(substr($ad['ad_description'], 0, 60)) . '</p>
            </a>
        </div>';
    }
    
    $html .= '</div>';
    return $html;
}

function render_ads_popup($pdo) {
    $ads = get_active_ads($pdo, 1);
    if (empty($ads)) return '';
    
    $ad = $ads[0];
    track_ad_impression($pdo, $ad['id']);
    
    $html = '
    <div id="ad-popup-' . $ad['id'] . '" class="ad-popup" style="
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        z-index: 9999;
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        display: none;
    ">
        <button onclick="closeAdPopup(' . $ad['id'] . ')" style="
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            z-index: 10000;
        ">✕</button>
        
        <div style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);padding:30px 20px;text-align:center;color:white;">
            ' . (!empty($ad['image_url']) ? '<img src="' . htmlspecialchars($ad['image_url']) . '" style="max-width:100%;border-radius:8px;margin-bottom:15px;" alt="Ad">' : '') . '
            <h2 style="margin:0 0 10px 0;font-size:1.8rem;">' . htmlspecialchars($ad['ad_title']) . '</h2>
        </div>
        
        <div style="padding:20px;">
            <p style="color:#6b7280;line-height:1.6;margin-bottom:20px;">' . htmlspecialchars($ad['ad_description']) . '</p>
            <a href="' . htmlspecialchars($ad['ad_link']) . '" onclick="trackAdClick(' . $ad['id'] . ')" style="
                display: block;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 12px;
                border-radius: 8px;
                text-decoration: none;
                text-align: center;
                font-weight: 600;
                margin-bottom: 10px;
            ">
                Vizito Tani →
            </a>
            <button onclick="closeAdPopup(' . $ad['id'] . ')" style="
                width: 100%;
                padding: 10px;
                background: #f3f4f6;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-weight: 600;
                color: #6b7280;
            ">Mbyll</button>
        </div>
    </div>
    
    <div id="ad-popup-overlay-' . $ad['id'] . '" class="ad-popup-overlay" style="
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 9998;
        display: none;
    " onclick="closeAdPopup(' . $ad['id'] . ')"></div>
    
    <script>
    function showAdPopup(adId) {
        document.getElementById("ad-popup-" + adId).style.display = "block";
        document.getElementById("ad-popup-overlay-" + adId).style.display = "block";
        document.body.style.overflow = "hidden";
    }
    function closeAdPopup(adId) {
        document.getElementById("ad-popup-" + adId).style.display = "none";
        document.getElementById("ad-popup-overlay-" + adId).style.display = "none";
        document.body.style.overflow = "auto";
    }
    // Show popup after 3 seconds
    setTimeout(function() { showAdPopup(' . $ad['id'] . '); }, 3000);
    </script>';
    
    return $html;
}

function trackAdClick($ad_id) {
    // AJAX call to track click
}

// Add global JavaScript for tracking
if (!function_exists('add_ad_tracking_script')) {
    function add_ad_tracking_script() {
        echo '<script>
        function trackAdClick(adId) {
            fetch("track_ad_click.php?ad_id=" + adId, { credentials: "same-origin" });
        }
        </script>';
    }
}
?>
