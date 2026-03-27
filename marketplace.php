<?php
/**
 * Faqja e Reklamave të Partnerëve - Marketplace
 * Shfaq të gjitha reklamat aktive për produkte/shërbime të partnerëve
 */

session_start();

require_once 'confidb.php';
require_once 'ad_helper.php';

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    die("Gabim në lidhjen me databazën!");
}

// Merr të gjitha reklamat aktive
$stmt = $pdo->query("SELECT a.*, adv.company_name, ap.placement_location
                    FROM advertisements a
                    JOIN advertisers adv ON a.advertiser_id = adv.id
                    LEFT JOIN ad_placements ap ON a.id = ap.ad_id
                    WHERE a.status = 'active'
                      AND a.start_date <= NOW()
                      AND (a.end_date IS NULL OR a.end_date >= NOW())
                      AND adv.subscription_status = 'active'
                    ORDER BY a.id DESC");
$all_ads = $stmt->fetchAll();

$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['roli'] ?? 'all';
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#1e40af">
    <title>Marketplace - Oferta të Partnerëve | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Poppins:400,500,600,700,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f8fafb;
            color: #222;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* ==================== HEADER ==================== */
        .hero-header {
            background: linear-gradient(135deg, #1e40af 0%, #2d6cdf 50%, #0ea5e9 100%);
            color: white;
            padding: 60px 20px;
            margin-bottom: 40px;
            border-radius: 0;
            position: relative;
            overflow: hidden;
        }
        
        .hero-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            transform: translate(100px, -100px);
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            border: 1px solid rgba(255,255,255,0.3);
            font-size: 0.95rem;
        }
        
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateX(-5px);
        }
        
        .hero-header h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 15px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .hero-header p {
            font-size: 1.3rem;
            opacity: 0.95;
            margin-bottom: 30px;
            max-width: 600px;
        }
        
        /* ==================== FILTERS ==================== */
        .filters-section {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-btn {
            padding: 10px 24px;
            background: rgba(255,255,255,0.15);
            border: 2px solid rgba(255,255,255,0.4);
            color: white;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-btn:hover {
            background: rgba(255,255,255,0.25);
            border-color: rgba(255,255,255,0.6);
            transform: translateY(-2px);
        }
        
        .filter-btn.active {
            background: white;
            color: #1e40af;
            border-color: white;
        }
        
        /* ==================== MAIN LAYOUT ==================== */
        .main-content {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 30px;
            margin-bottom: 50px;
        }
        
        /* ==================== FEATURED ADS ==================== */
        .featured-section {
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1e40af;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-title i {
            font-size: 1.5rem;
        }
        
        .featured-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }
        
        .featured-ad-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(30, 64, 175, 0.12);
            transition: all 0.4s cubic-bezier(0.23, 1, 0.320, 1);
            display: flex;
            flex-direction: column;
            position: relative;
            border: 2px solid transparent;
        }
        
        .featured-ad-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 16px 48px rgba(30, 64, 175, 0.2);
            border-color: #2d6cdf;
        }
        
        .featured-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            z-index: 10;
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
        }
        
        .featured-image {
            width: 100%;
            height: 220px;
            object-fit: cover;
            background: linear-gradient(135deg, #e0e7ff 0%, #f0f4ff 100%);
            transition: transform 0.4s ease;
        }
        
        .featured-ad-card:hover .featured-image {
            transform: scale(1.05);
        }
        
        .featured-content {
            padding: 28px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .company-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: #0ea5e9;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            font-weight: 700;
            margin-bottom: 12px;
            width: fit-content;
        }
        
        .company-badge i {
            font-size: 0.9rem;
        }
        
        .featured-title {
            color: #1e40af;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 12px;
            line-height: 1.3;
        }
        
        .featured-description {
            color: #555;
            font-size: 0.95rem;
            line-height: 1.7;
            margin-bottom: 20px;
            flex: 1;
        }
        
        .offer-tag {
            display: inline-block;
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            color: #92400e;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .featured-footer {
            display: flex;
            gap: 12px;
            align-items: center;
            padding-top: 16px;
            border-top: 1px solid #f0f0f0;
        }
        
        .featured-cta {
            flex: 1;
            background: linear-gradient(135deg, #1e40af 0%, #2d6cdf 100%);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
            display: inline-block;
            font-size: 0.95rem;
            font-family: 'Poppins', sans-serif;
        }
        
        .featured-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(30, 64, 175, 0.4);
        }
        
        .featured-stats {
            display: flex;
            gap: 12px;
            font-size: 0.8rem;
            color: #999;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        /* ==================== REGULAR ADS GRID ==================== */
        .ads-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .ad-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s cubic-bezier(0.23, 1, 0.320, 1);
            display: flex;
            flex-direction: column;
            border: 1px solid #f0f0f0;
        }
        
        .ad-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 24px rgba(30, 64, 175, 0.15);
            border-color: #e0e7ff;
        }
        
        .ad-image {
            width: 100%;
            height: 160px;
            object-fit: cover;
            background: linear-gradient(135deg, #e0e7ff 0%, #f0f4ff 100%);
            transition: transform 0.3s ease;
        }
        
        .ad-card:hover .ad-image {
            transform: scale(1.03);
        }
        
        .ad-content {
            padding: 18px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .ad-company {
            font-size: 0.75rem;
            color: #0ea5e9;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            font-weight: 700;
        }
        
        .ad-title {
            color: #1e40af;
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 8px;
            line-height: 1.3;
            min-height: 45px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .ad-description {
            color: #666;
            font-size: 0.85rem;
            line-height: 1.5;
            margin-bottom: 12px;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .ad-footer {
            display: flex;
            gap: 8px;
            align-items: center;
            padding-top: 10px;
            border-top: 1px solid #f5f5f5;
        }
        
        .ad-cta {
            flex: 1;
            background: linear-gradient(135deg, #1e40af 0%, #2d6cdf 100%);
            color: white;
            padding: 8px 14px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
            display: inline-block;
            font-size: 0.85rem;
            font-family: 'Poppins', sans-serif;
        }
        
        .ad-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.35);
        }
        
        .ad-stats {
            display: flex;
            gap: 8px;
            font-size: 0.75rem;
            color: #999;
            margin-top: 8px;
        }
        
        /* ==================== SIDEBAR ==================== */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        
        .sidebar-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #f0f0f0;
        }
        
        .sidebar-card h3 {
            color: #1e40af;
            font-size: 1.1rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sidebar-card h3 i {
            font-size: 1.3rem;
            color: #2d6cdf;
        }
        
        .category-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .category-item {
            padding: 10px 14px;
            background: #f8fafb;
            border-left: 3px solid transparent;
            border-radius: 6px;
            color: #555;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
        }
        
        .category-item:hover {
            background: #f0f4ff;
            border-left-color: #2d6cdf;
            color: #1e40af;
        }
        
        .promo-box {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            padding: 24px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 8px 20px rgba(255, 107, 53, 0.3);
        }
        
        .promo-box h3 {
            color: white;
            font-size: 1.2rem;
            margin-bottom: 12px;
        }
        
        .promo-box p {
            font-size: 0.9rem;
            margin-bottom: 16px;
            opacity: 0.95;
        }
        
        .promo-cta {
            display: block;
            background: white;
            color: #ff6b35;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s;
            text-align: center;
            font-size: 0.9rem;
        }
        
        .promo-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        /* ==================== NO ADS ==================== */
        .no-ads {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 12px;
            color: #999;
            border: 2px dashed #ddd;
            grid-column: 1 / -1;
        }
        
        .no-ads i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .no-ads h2 {
            color: #666;
            margin-bottom: 10px;
            font-size: 1.5rem;
        }
        
        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 1024px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .hero-header h1 {
                font-size: 2.5rem;
            }
            
            .featured-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .hero-header {
                padding: 40px 20px;
            }
            
            .hero-header h1 {
                font-size: 2rem;
            }
            
            .hero-header p {
                font-size: 1rem;
            }
            
            .filters-section {
                justify-content: flex-start;
            }
            
            .ads-grid {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
        }
        
        /* ==================== ANIMATIONS ==================== */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .featured-ad-card,
        .ad-card {
            animation: fadeInUp 0.6s ease backwards;
        }
        
        .featured-ad-card:nth-child(1) {
            animation-delay: 0.1s;
        }
        
        .featured-ad-card:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .featured-ad-card:nth-child(3) {
            animation-delay: 0.3s;
        }
    </style>
</head>
<body>
    <div class="hero-header">
        <div class="container">
            <div class="hero-content">
                <a href="dashboard.php" class="back-btn"><i class="fas fa-chevron-left"></i> Kthehu në Dashboard</a>
                <h1><i class="fas fa-store"></i> Marketplace</h1>
                <p>Zbuloni ofertat më të fundit dhe më atraktive nga bizneset e besura të komunitetit tonë.</p>
                
                <div class="filters-section">
                    <button class="filter-btn active" data-filter="all"><i class="fas fa-th"></i> Të Gjitha</button>
                    <button class="filter-btn" data-filter="card"><i class="fas fa-tag"></i> Oferta</button>
                    <button class="filter-btn" data-filter="banner"><i class="fas fa-flag"></i> Kampanja</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="main-content">
            <!-- ADS SECTION -->
            <div>
                <?php if (!empty($all_ads)): ?>
                    <!-- FEATURED ADS -->
                    <div class="featured-section">
                        <h2 class="section-title"><i class="fas fa-star"></i> Ofertat më të Shquara</h2>
                        <div class="featured-grid">
                            <?php 
                            $featured_count = 0;
                            foreach ($all_ads as $ad): 
                                if ($featured_count >= 3) break;
                                $featured_count++;
                            ?>
                                <div class="featured-ad-card" data-type="<?php echo htmlspecialchars($ad['ad_type']); ?>">
                                    <div style="position: relative;">
                                        <?php if (!empty($ad['image_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($ad['image_url']); ?>" alt="<?php echo htmlspecialchars($ad['title']); ?>" class="featured-image">
                                        <?php else: ?>
                                            <div class="featured-image" style="display:flex;align-items:center;justify-content:center;font-size:4rem;color:#e0e7ff;">
                                                <i class="fas fa-gift"></i>
                                            </div>
                                        <?php endif; ?>
                                        <span class="featured-badge"><i class="fas fa-crown"></i> Veçantar</span>
                                    </div>
                                    
                                    <div class="featured-content">
                                        <div class="company-badge"><i class="fas fa-building"></i> <?php echo htmlspecialchars($ad['company_name']); ?></div>
                                        <h3 class="featured-title"><?php echo htmlspecialchars($ad['title']); ?></h3>
                                        <p class="featured-description"><?php echo htmlspecialchars(substr($ad['description'], 0, 100)) . '...'; ?></p>
                                        
                                        <?php if (strpos(strtolower($ad['description']), 'zbritje') !== false): ?>
                                            <span class="offer-tag"><i class="fas fa-bolt"></i> Ofertë e Limituar</span>
                                        <?php endif; ?>
                                        
                                        <div class="featured-footer">
                                            <a href="<?php echo htmlspecialchars($ad['cta_url']); ?>" target="_blank" class="featured-cta" onclick="recordAdClick(<?php echo $ad['id']; ?>)">
                                                Shikoni Më Shumë
                                            </a>
                                        </div>
                                        
                                        <div class="featured-stats">
                                            <span class="stat-item"><i class="fas fa-eye"></i> <?php echo number_format($ad['total_impressions']); ?></span>
                                            <span class="stat-item"><i class="fas fa-mouse"></i> <?php echo number_format($ad['total_clicks']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- ALL ADS -->
                    <div class="featured-section">
                        <h2 class="section-title"><i class="fas fa-list"></i> Të Gjitha Reklamat</h2>
                        <div class="ads-grid" id="ads-container">
                            <?php 
                            $shown = 0;
                            foreach ($all_ads as $ad): 
                                if ($shown >= 3 && $shown < COUNT($all_ads)) {
                            ?>
                                <div class="ad-card" data-type="<?php echo htmlspecialchars($ad['ad_type']); ?>">
                                    <?php if (!empty($ad['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($ad['image_url']); ?>" alt="<?php echo htmlspecialchars($ad['title']); ?>" class="ad-image">
                                    <?php else: ?>
                                        <div class="ad-image" style="display:flex;align-items:center;justify-content:center;font-size:2.5rem;color:#e0e7ff;">
                                            <i class="fas fa-box"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="ad-content">
                                        <div class="ad-company"><?php echo htmlspecialchars($ad['company_name']); ?></div>
                                        <h3 class="ad-title"><?php echo htmlspecialchars($ad['title']); ?></h3>
                                        <p class="ad-description"><?php echo htmlspecialchars($ad['description']); ?></p>
                                        
                                        <div class="ad-footer">
                                            <a href="<?php echo htmlspecialchars($ad['cta_url']); ?>" target="_blank" class="ad-cta" onclick="recordAdClick(<?php echo $ad['id']; ?>)">
                                                Më Shumë
                                            </a>
                                        </div>
                                        
                                        <div class="ad-stats">
                                            <span class="stat-item"><i class="fas fa-eye"></i> <?php echo number_format($ad['total_impressions']); ?></span>
                                            <span class="stat-item"><i class="fas fa-mouse"></i> <?php echo number_format($ad['total_clicks']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php 
                                }
                                $shown++;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-ads">
                        <i class="fas fa-inbox"></i>
                        <h2>Nuk ka oferta të disponueshme</h2>
                        <p>Shpejt do të ketë oferta të reja dhe atraktive nga partnerët e ynë.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- SIDEBAR -->
            <div class="sidebar">
                <!-- Categories -->
                <div class="sidebar-card">
                    <h3><i class="fas fa-th-large"></i> Kategoritë</h3>
                    <div class="category-list">
                        <div class="category-item">
                            <span><i class="fas fa-shopping-bag"></i> Produktet</span>
                            <span style="font-size: 0.8rem; background: #2d6cdf; color: white; padding: 2px 8px; border-radius: 12px;"><?php echo count($all_ads); ?></span>
                        </div>
                        <div class="category-item">
                            <span><i class="fas fa-tools"></i> Shërbime</span>
                            <span style="font-size: 0.8rem; background: #0ea5e9; color: white; padding: 2px 8px; border-radius: 12px;">12</span>
                        </div>
                        <div class="category-item">
                            <span><i class="fas fa-graduation-cap"></i> Edukimi</span>
                            <span style="font-size: 0.8rem; background: #10b981; color: white; padding: 2px 8px; border-radius: 12px;">8</span>
                        </div>
                        <div class="category-item">
                            <span><i class="fas fa-utensils"></i> Ushqim & Pije</span>
                            <span style="font-size: 0.8rem; background: #f59e0b; color: white; padding: 2px 8px; border-radius: 12px;">15</span>
                        </div>
                        <div class="category-item">
                            <span><i class="fas fa-heartbeat"></i> Shëndetësi</span>
                            <span style="font-size: 0.8rem; background: #ef4444; color: white; padding: 2px 8px; border-radius: 12px;">6</span>
                        </div>
                    </div>
                </div>
                
                <!-- Promotional Banner -->
                <div class="promo-box">
                    <h3>Bëhu Reklamues</h3>
                    <p>Promovo biznesin tënd në Marketplace tonë</p>
                    <a href="become-advertiser.php" class="promo-cta">Shëndrrohu në Partner</a>
                </div>
                
                <!-- Statistics -->
                <div class="sidebar-card">
                    <h3><i class="fas fa-chart-bar"></i> Statistika</h3>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <div style="padding: 10px; background: #f0f4ff; border-radius: 6px;">
                            <div style="font-size: 0.8rem; color: #666; margin-bottom: 4px;">Oferta Aktive</div>
                            <div style="font-size: 1.5rem; font-weight: 700; color: #2d6cdf;"><?php echo count($all_ads); ?></div>
                        </div>
                        <div style="padding: 10px; background: #f0f4ff; border-radius: 6px;">
                            <div style="font-size: 0.8rem; color: #666; margin-bottom: 4px;">Vizita Ditore</div>
                            <div style="font-size: 1.5rem; font-weight: 700; color: #2d6cdf;">1.2K</div>
                        </div>
                        <div style="padding: 10px; background: #f0f4ff; border-radius: 6px;">
                            <div style="font-size: 0.8rem; color: #666; margin-bottom: 4px;">Partnerë</div>
                            <div style="font-size: 1.5rem; font-weight: 700; color: #2d6cdf;">43</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Filtrim i reklamave
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.dataset.filter;
                document.querySelectorAll('.ad-card, .featured-ad-card').forEach(card => {
                    if (filter === 'all' || card.dataset.type === filter) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
        
        // Regjistro click-in
        function recordAdClick(adId) {
            fetch('ad_click_handler.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'ad_id=' + adId
            }).catch(e => console.log('Click recorded'));
        }
    </script>
</body>
</html>
