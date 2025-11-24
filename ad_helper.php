<?php
/**
 * Advertising System Helper Functions
 */

/**
 * Merr reklamat për një placement
 */
function getAdsForPlacement($pdo, $placement_location, $user_role = 'all', $limit = 3) {
    try {
        $query = "SELECT a.*, adv.company_name, adv.logo_url
                  FROM advertisements a
                  JOIN ad_placements ap ON a.id = ap.ad_id
                  JOIN advertisers adv ON a.advertiser_id = adv.id
                  WHERE ap.placement_location = ? 
                    AND ap.enabled = 1
                    AND (ap.target_role = ? OR ap.target_role = 'all')
                    AND a.status = 'active'
                    AND a.start_date <= NOW()
                    AND (a.end_date IS NULL OR a.end_date >= NOW())
                    AND adv.subscription_status = 'active'
                  ORDER BY ap.order_priority DESC, a.id DESC
                  LIMIT ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$placement_location, $user_role, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting ads for placement: " . $e->getMessage());
        return [];
    }
}

/**
 * Regjistro impression për një reklam
 */
function recordAdImpression($pdo, $ad_id, $placement_location, $user_id = null) {
    try {
        $ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP) ?: '0.0.0.0';
        $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);
        
        $stmt = $pdo->prepare("INSERT INTO ad_impressions 
                              (ad_id, user_id, placement_location, ip_address, user_agent)
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$ad_id, $user_id, $placement_location, $ip, $user_agent]);
        
        // Update total impressions
        $stmt = $pdo->prepare("UPDATE advertisements SET total_impressions = total_impressions + 1 WHERE id = ?");
        $stmt->execute([$ad_id]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error recording ad impression: " . $e->getMessage());
        return false;
    }
}

/**
 * Regjistro click për një reklam
 */
function recordAdClick($pdo, $ad_id, $impression_id = null) {
    try {
        if ($impression_id) {
            $stmt = $pdo->prepare("UPDATE ad_impressions SET click_through = 1, click_time = NOW() WHERE id = ?");
            $stmt->execute([$impression_id]);
        }
        
        // Update total clicks
        $stmt = $pdo->prepare("UPDATE advertisements SET total_clicks = total_clicks + 1 WHERE id = ?");
        $stmt->execute([$ad_id]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error recording ad click: " . $e->getMessage());
        return false;
    }
}

/**
 * Shfaq HTML për një reklam
 */
function displayAd($ad, $user_id = null) {
    if (!$ad) {
        return '';
    }
    
    $html = '<div class="ad-container ad-' . htmlspecialchars($ad['ad_type']) . '" data-ad-id="' . $ad['id'] . '">';
    
    if ($ad['image_url']) {
        $html .= '<a href="' . htmlspecialchars($ad['cta_url']) . '" target="_blank" class="ad-link" onclick="recordAdClick(' . $ad['id'] . ')">';
        $html .= '<img src="' . htmlspecialchars($ad['image_url']) . '" alt="' . htmlspecialchars($ad['title']) . '" class="ad-image">';
        $html .= '</a>';
    }
    
    if ($ad['title']) {
        $html .= '<h3 class="ad-title">' . htmlspecialchars($ad['title']) . '</h3>';
    }
    
    if ($ad['description']) {
        $html .= '<p class="ad-description">' . htmlspecialchars(substr($ad['description'], 0, 150)) . '...</p>';
    }
    
    if ($ad['cta_url']) {
        $html .= '<a href="' . htmlspecialchars($ad['cta_url']) . '" target="_blank" class="ad-button">' . htmlspecialchars($ad['cta_text'] ?? 'Vizito') . '</a>';
    }
    
    if ($ad['logo_url']) {
        $html .= '<div class="ad-brand"><img src="' . htmlspecialchars($ad['logo_url']) . '" alt="' . htmlspecialchars($ad['company_name']) . '"></div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Shfaq CSS për reklamat
 */
function getAdCSS() {
    return <<<'CSS'
<style>
.ad-container {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 12px;
    margin: 12px 0;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.ad-container:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.ad-link {
    text-decoration: none;
    color: inherit;
    display: block;
}

.ad-image {
    width: 100%;
    max-height: 200px;
    object-fit: contain;
    margin-bottom: 10px;
    border-radius: 6px;
}

.ad-title {
    font-size: 16px;
    font-weight: 600;
    margin: 8px 0;
    color: #333;
}

.ad-description {
    font-size: 13px;
    color: #666;
    margin: 6px 0;
    line-height: 1.4;
}

.ad-button {
    display: inline-block;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    font-size: 13px;
    margin-top: 8px;
    transition: all 0.3s ease;
}

.ad-button:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.ad-brand {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid #eee;
}

.ad-brand img {
    max-height: 30px;
    opacity: 0.7;
}

.ad-banner {
    flex-direction: row;
    display: flex;
    align-items: center;
    gap: 12px;
}

.ad-banner .ad-image {
    flex: 0 0 120px;
    margin: 0;
}

.ad-sidebar {
    max-width: 300px;
}

.ad-popup {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 320px;
    z-index: 1000;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
}

.ad-popup .close-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: #999;
}

/* Responsive */
@media (max-width: 768px) {
    .ad-sidebar {
        max-width: 100%;
    }
    
    .ad-popup {
        width: calc(100% - 20px);
        right: 10px;
        left: 10px;
    }
    
    .ad-banner {
        flex-direction: column;
    }
    
    .ad-banner .ad-image {
        flex: 1 1 auto;
    }
}
</style>
CSS;
}

/**
 * Shfaq JavaScript për reklamat
 */
function getAdJS() {
    return <<<'JS'
<script>
function recordAdClick(adId) {
    fetch('/noteria/api/ad_click.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ad_id: adId})
    }).catch(e => console.log('Ad click recorded'));
}

document.addEventListener('DOMContentLoaded', function() {
    // Record impressions
    document.querySelectorAll('[data-ad-id]').forEach(el => {
        const adId = el.getAttribute('data-ad-id');
        fetch('/noteria/api/ad_impression.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ad_id: adId})
        }).catch(e => console.log('Ad impression recorded'));
    });
});
</script>
JS;
}
