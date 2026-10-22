<?php
// Start at the very top - before ANY other code
ob_start();

// Session configuration BEFORE session_start
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', 1);
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load configuration
require_once 'config.php';

// Clear any output from includes
ob_end_clean();

// Generate CSRF token if needed
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Multilingual labels
$lang = $_GET['lang'] ?? $_COOKIE['lang'] ?? 'sq';
if (!in_array($lang, ['sq','sr','en'])) $lang = 'sq';

// Set cookie only if headers not sent
if (!headers_sent()) {
    setcookie('lang', $lang, time()+60*60*24*30, '/');
}

$labels = [
    'sq' => [
        'title' => 'Lajme',
        'no_news' => 'Nuk ka lajme të disponueshme.',
        'back' => 'Kthehu në faqen kryesore',
    ],
    'sr' => [
        'title' => 'Vesti',
        'no_news' => 'Nema dostupnih vesti.',
        'back' => 'Vratite se na početnu stranu',
    ],
    'en' => [
        'title' => 'News',
        'no_news' => 'No news available.',
        'back' => 'Back to home page',
    ]
];
$L = $labels[$lang];

// Fetch news from database
try {
    require_once 'confidb.php';
    $stmt = $pdo->prepare("SELECT * FROM news WHERE published = 1 ORDER BY date_created DESC");
    $stmt->execute();
    $news = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $news = [];
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($L['title']); ?> | e-Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #003366;
            --primary-light: #004080;
            --primary-dark: #002244;
            --accent: #cfa856;
            --accent-light: #e0c078;
            --text-dark: #1a1a1a;
            --text-light: #666;
            --bg-gray: #f5f7fa;
            --border-light: #e1e8ed;
            --success: #10b981;
            --shadow-sm: 0 2px 8px rgba(0, 51, 102, 0.08);
            --shadow-md: 0 8px 24px rgba(0, 51, 102, 0.12);
            --shadow-lg: 0 16px 48px rgba(0, 51, 102, 0.16);
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
        }
        
        .nav-top {
            background: white;
            border-bottom: 1px solid var(--border-light);
            padding: 12px 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow-sm);
        }
        
        .nav-top .nav-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }
        
        .logo-link img {
            height: 45px;
            width: 45px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
            transition: all 0.3s ease;
        }
        
        .logo-link:hover img {
            filter: drop-shadow(0 4px 8px rgba(207, 168, 86, 0.4));
            transform: scale(1.05);
        }
        
        .logo-link i {
            color: var(--accent);
        }
        
        .lang-selector {
            display: flex;
            gap: 8px;
        }
        
        .lang-btn {
            padding: 6px 12px;
            border: 1px solid var(--border-light);
            background: white;
            color: var(--text-light);
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .lang-btn:hover,
        .lang-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .main-container {
            max-width: 900px;
            margin: 40px auto 0;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 60px 40px;
            border-radius: 20px;
            margin-bottom: 50px;
            box-shadow: var(--shadow-lg);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
            transform: translate(80px, -80px);
        }
        
        .page-header h1 {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 12px;
            position: relative;
            z-index: 1;
        }
        
        .page-header p {
            font-size: 1.1rem;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }
        
        .news-grid {
            display: grid;
            gap: 24px;
            margin-bottom: 40px;
        }
        
        .news-card {
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: var(--shadow-md);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(0, 51, 102, 0.05);
            animation: slideUp 0.6s ease forwards;
            opacity: 0;
        }
        
        @keyframes slideUp {
            0% {
                opacity: 0;
                transform: translateY(20px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .news-card:nth-child(1) { animation-delay: 0.1s; }
        .news-card:nth-child(2) { animation-delay: 0.2s; }
        .news-card:nth-child(3) { animation-delay: 0.3s; }
        .news-card:nth-child(4) { animation-delay: 0.4s; }
        .news-card:nth-child(5) { animation-delay: 0.5s; }
        .news-card:nth-child(n+6) { animation-delay: 0.6s; }
        
        .news-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
            border-color: var(--accent);
        }
        
        .news-header {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .news-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
            font-size: 24px;
        }
        
        .news-meta {
            flex: 1;
        }
        
        .news-card h2 {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .news-date {
            display: inline-block;
            background: var(--bg-gray);
            color: var(--text-light);
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .news-content {
            color: var(--text-light);
            line-height: 1.8;
            font-size: 0.95rem;
            margin: 20px 0 0 0;
        }

        /* News Portal Layout */
        .news-portal-wrapper {
            animation: fadeIn 0.6s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .featured-news {
            margin-bottom: 50px;
            animation: slideUp 0.8s ease;
        }

        .featured-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 20px;
            padding: 50px;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }

        .featured-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
            border-radius: 50%;
            transform: translate(100px, -100px);
        }

        .featured-content {
            position: relative;
            z-index: 2;
        }

        .featured-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }

        .featured-badge {
            display: inline-block;
            background: rgba(255,255,255,0.25);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
            backdrop-filter: blur(10px);
        }

        .featured-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 20px 0;
            line-height: 1.2;
        }

        .featured-excerpt {
            font-size: 1.1rem;
            opacity: 0.95;
            margin: 20px 0;
            line-height: 1.6;
        }

        .featured-meta {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid rgba(255,255,255,0.2);
        }

        .featured-date {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
            opacity: 0.9;
        }

        .read-more {
            color: white;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .read-more:hover {
            gap: 10px;
        }

        /* Main Grid Layout */
        .news-main-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 40px;
            margin-bottom: 40px;
        }

        .news-main-column {
            animation: slideUp 0.8s ease 0.2s both;
        }

        .news-sidebar {
            animation: slideUp 0.8s ease 0.4s both;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid var(--primary);
        }

        .section-header h2 {
            font-size: 1.5rem;
            color: var(--primary);
            margin: 0;
            font-weight: 700;
        }

        .section-header i {
            color: var(--accent);
            font-size: 1.3rem;
        }

        /* News Article List */
        .news-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .news-article-item {
            display: flex;
            gap: 20px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border-light);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .news-article-item:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--accent);
            transform: translateX(8px);
        }

        .article-number {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .article-body {
            flex: 1;
            display: flex;
            gap: 15px;
        }

        .article-icon-badge {
            flex-shrink: 0;
            width: 50px;
            height: 50px;
            background: var(--bg-gray);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.5rem;
        }

        .article-content {
            flex: 1;
        }

        .article-title {
            font-size: 1.1rem;
            color: var(--text-dark);
            margin: 0 0 8px 0;
            font-weight: 600;
            line-height: 1.4;
            transition: color 0.3s ease;
        }

        .news-article-item:hover .article-title {
            color: var(--primary);
        }

        .article-excerpt {
            color: var(--text-light);
            font-size: 0.9rem;
            margin: 0;
            line-height: 1.5;
        }

        .article-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--border-light);
        }

        .article-date {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-light);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .article-read {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .article-read:hover {
            color: var(--accent);
            gap: 8px;
        }

        /* Article Full Modal - Global Positioning Fix */
        .article-full {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.85); /* Slightly darker for focus */
            display: none; /* Controlled by JS active class */
            align-items: center;
            justify-content: center;
            z-index: 9999; /* Higher than everything else */
            backdrop-filter: blur(8px); /* Modern blur effect */
            padding: 20px;
            box-sizing: border-box;
        }

        .article-full.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        .article-modal {
            background: white;
            padding: 40px;
            max-width: 800px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: modalSlideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .close-article {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            font-size: 28px;
            color: #999;
            cursor: pointer;
            transition: color 0.3s ease;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close-article:hover {
            color: var(--text-dark);
            background: var(--bg-gray);
            border-radius: 4px;
        }

        .article-modal h2 {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 20px;
            font-weight: 700;
            line-height: 1.3;
        }

        .article-modal-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-light);
            color: var(--text-light);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .article-modal-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .article-modal-meta i {
            color: var(--primary);
        }

        .article-modal-content {
            color: var(--text-dark);
            line-height: 1.8;
            font-size: 1rem;
            font-weight: 400;
        }

        .article-modal-content p {
            margin: 0 0 16px 0;
        }

        .article-modal-content p:last-child {
            margin-bottom: 0;
        }

        /* Sidebar */
        .sidebar-widget {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
        }

        .widget-title {
            font-size: 1.1rem;
            color: var(--primary);
            margin: 0 0 20px 0;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .widget-title i {
            color: var(--accent);
            font-size: 1.2rem;
        }

        .trending-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .trending-item {
            display: flex;
            gap: 12px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-light);
            transition: all 0.3s ease;
            align-items: flex-start;
        }

        .trending-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .trending-item:hover {
            padding-left: 8px;
        }

        .trending-number {
            flex-shrink: 0;
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .trending-item h4 {
            margin: 0;
            font-size: 0.9rem;
            color: var(--text-dark);
            font-weight: 600;
            line-height: 1.3;
            flex: 1;
        }

        .trending-date {
            color: var(--text-light);
            font-size: 0.8rem;
            font-weight: 500;
        }

        .tips-box {
            background: linear-gradient(135deg, rgba(207,168,86,0.1) 0%, rgba(0,51,102,0.05) 100%);
            border-radius: 10px;
            padding: 16px;
            border-left: 4px solid var(--accent);
        }

        .tips-box ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .tips-box li {
            color: var(--text-dark);
            font-size: 0.9rem;
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .tips-box li:last-child {
            margin-bottom: 0;
        }

        .quick-links {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .quick-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            color: var(--primary);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .quick-link:hover {
            background: var(--bg-gray);
            gap: 15px;
            color: var(--accent);
        }

        .quick-link i {
            font-size: 1.1rem;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .news-main-grid {
                grid-template-columns: 1fr;
            }

            .featured-title {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 768px) {
            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .featured-card {
                padding: 30px;
            }

            .featured-title {
                font-size: 1.5rem;
            }

            .featured-icon {
                width: 60px;
                height: 60px;
                font-size: 32px;
            }

            .article-body {
                flex-direction: column;
            }

            .article-modal {
                margin: 0 20px;
                max-width: 100%;
            }
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
        }
        
        .empty-state i {
            font-size: 64px;
            color: var(--accent);
            margin-bottom: 20px;
            opacity: 0.8;
        }
        
        .empty-state p {
            font-size: 1.1rem;
            color: var(--text-light);
        }
        
        .footer-action {
            text-align: center;
            padding: 30px 0;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            padding: 12px 24px;
            border-radius: 10px;
            border: 2px solid var(--primary);
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            background: var(--primary);
            color: white;
            transform: translateX(-4px);
        }
        
        @media (max-width: 768px) {
            .page-header {
                padding: 40px 24px;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .news-card {
                padding: 24px;
            }
            
            .news-header {
                flex-direction: column;
            }
            
            .nav-top .nav-content {
                flex-direction: column;
                gap: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="nav-top">
        <div class="nav-content">
            <a href="index.php" class="logo-link">
                <img src="images/pngwing.com (1).png" alt="e-Noteria Logo">
                e-Noteria
            </a>
            <div class="lang-selector">
                <form method="get" style="display: flex; gap: 8px;">
                    <button type="submit" name="lang" value="sq" class="lang-btn <?php echo $lang === 'sq' ? 'active' : ''; ?>">
                        <i class="fas fa-flag"></i> Shqip
                    </button>
                    <button type="submit" name="lang" value="sr" class="lang-btn <?php echo $lang === 'sr' ? 'active' : ''; ?>">
                        <i class="fas fa-flag"></i> Српски
                    </button>
                    <button type="submit" name="lang" value="en" class="lang-btn <?php echo $lang === 'en' ? 'active' : ''; ?>">
                        <i class="fas fa-flag"></i> English
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="main-container">
        <div class="page-header">
            <h1>
                <i class="fas fa-newspaper" style="margin-right: 12px;"></i>
                <?php echo htmlspecialchars($L['title']); ?>
            </h1>
            <p><?php echo $lang === 'sq' ? 'Të gjitha përditësimet e fundit rreth platformës sonë' : ($lang === 'sr' ? 'Све последње вести и ажурирања' : 'Latest updates and news'); ?></p>
        </div>

        <?php if (empty($news)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p><?php echo htmlspecialchars($L['no_news']); ?></p>
            </div>
        <?php else: ?>
            <div class="news-portal-wrapper">
                <!-- Featured News Section -->
                <?php if (!empty($news)): ?>
                    <div class="featured-news">
                        <div class="featured-card" data-index="0">
                            <?php 
                                $featured = $news[0];
                                $icons = ['file-contract', 'shield-alt', 'calendar-check', 'credit-card', 'box-open', 'lock', 'chart-line', 'plug', 'headset'];
                            ?>
                            <div class="featured-content">
                                <div class="featured-icon">
                                    <i class="fas fa-<?php echo $icons[0]; ?>"></i>
                                </div>
                                <span class="featured-badge"><?php echo $lang === 'sq' ? 'LAJMI KRYESOR' : ($lang === 'sr' ? 'ГЛАВНА ВЕСТ' : 'FEATURED'); ?></span>
                                <h1 class="featured-title"><?php echo htmlspecialchars($featured['title_' . $lang]); ?></h1>
                                <p class="featured-excerpt"><?php echo substr(htmlspecialchars($featured['content_' . $lang]), 0, 200) . '...'; ?></p>
                                <div class="featured-meta">
                                    <span class="featured-date">
                                        <i class="fas fa-calendar-alt"></i>
                                        <?php echo date('d.m.Y', strtotime($featured['date_created'])); ?>
                                    </span>
                                    <a href="javascript:void(0);" class="read-more" onclick="openArticleModal('featured-article')"><?php echo $lang === 'sq' ? 'Lexo më shumë' : ($lang === 'sr' ? 'Прочитај више' : 'Read more'); ?> →</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="news-main-grid">
                    <!-- Main News Column -->
                    <div class="news-main-column">
                        <div class="section-header">
                            <h2><i class="fas fa-star"></i> <?php echo $lang === 'sq' ? 'Lajmet më të reja' : ($lang === 'sr' ? 'Најновије вести' : 'Latest News'); ?></h2>
                        </div>
                        
                        <div class="news-list">
                            <?php 
                            $count = 0;
                            foreach ($news as $index => $item): 
                                if ($index === 0) continue;
                                $count++;
                                $icons = ['file-contract', 'shield-alt', 'calendar-check', 'credit-card', 'box-open', 'lock', 'chart-line', 'plug', 'headset'];
                                $icon = $icons[$index % count($icons)];
                                $modal_id = 'article-' . $count; // Use $count for unique IDs
                            ?>
                                <article class="news-article-item" onclick="openArticleModal('<?php echo $modal_id; ?>')">
                                    <div class="article-number"><?php echo $count; ?></div>
                                    <div class="article-body">
                                        <div class="article-icon-badge">
                                            <i class="fas fa-<?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="article-content">
                                            <h3 class="article-title"><?php echo htmlspecialchars($item['title_' . $lang]); ?></h3>
                                            <p class="article-excerpt"><?php echo substr(htmlspecialchars($item['content_' . $lang]), 0, 150) . '...'; ?></p>
                                            <div class="article-footer">
                                                <span class="article-date">
                                                    <i class="fas fa-clock"></i>
                                                    <?php echo date('d.m.Y H:i', strtotime($item['date_created'])); ?>
                                                </span>
                                                <a href="javascript:void(0);" class="article-read">
                                                    <?php echo $lang === 'sq' ? 'Më shumë' : ($lang === 'sr' ? 'Више' : 'More'); ?> →
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <aside class="news-sidebar">
                        <div class="sidebar-widget">
                            <h3 class="widget-title"><i class="fas fa-fire"></i> <?php echo $lang === 'sq' ? 'Në trend' : ($lang === 'sr' ? 'Трендинг' : 'Trending'); ?></h3>
                            <div class="trending-list">
                                <?php 
                                $trending = array_slice($news, 0, 3);
                                foreach ($trending as $index => $item): 
                                    $icons = ['file-contract', 'shield-alt', 'calendar-check'];
                                ?>
                                    <div class="trending-item">
                                        <span class="trending-number"><?php echo $index + 1; ?></span>
                                        <h4><?php echo htmlspecialchars(substr($item['title_' . $lang], 0, 40)); ?></h4>
                                        <span class="trending-date"><?php echo date('d.m', strtotime($item['date_created'])); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="sidebar-widget">
                            <h3 class="widget-title"><i class="fas fa-lightbulb"></i> <?php echo $lang === 'sq' ? 'Këshilla' : ($lang === 'sr' ? 'Савети' : 'Tips'); ?></h3>
                            <div class="tips-box">
                                <ul>
                                    <li><?php echo $lang === 'sq' ? '💡 Mos harro të përditësosh profilin tënd' : ($lang === 'sr' ? '💡 Не заборави да ажурираш свој профил' : '💡 Keep your profile updated'); ?></li>
                                    <li><?php echo $lang === 'sq' ? '🔒 Përdor një fjalëkalim të sigurt' : ($lang === 'sr' ? '🔒 Користи јаку лозинку' : '🔒 Use strong passwords'); ?></li>
                                    <li><?php echo $lang === 'sq' ? '📱 Aktivo verifikimin dy-faktor' : ($lang === 'sr' ? '📱 Омогући двофакторску аутентификацију' : '📱 Enable 2FA'); ?></li>
                                </ul>
                            </div>
                        </div>

                        <div class="sidebar-widget">
                            <h3 class="widget-title"><i class="fas fa-link"></i> <?php echo $lang === 'sq' ? 'Lidhje të shpejta' : ($lang === 'sr' ? 'Брзе везе' : 'Quick Links'); ?></h3>
                            <div class="quick-links">
                                <a href="index.php" class="quick-link"><i class="fas fa-home"></i> <?php echo $lang === 'sq' ? 'Ballina' : ($lang === 'sr' ? 'Почетна' : 'Home'); ?></a>
                                <a href="services.php" class="quick-link"><i class="fas fa-cogs"></i> <?php echo $lang === 'sq' ? 'Shërbimet' : ($lang === 'sr' ? 'Услуге' : 'Services'); ?></a>
                                <a href="contact.php" class="quick-link"><i class="fas fa-envelope"></i> <?php echo $lang === 'sq' ? 'Kontakt' : ($lang === 'sr' ? 'Контакт' : 'Contact'); ?></a>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        <?php endif; ?>

        <!-- Modals outside animated containers for fixed positioning -->
        <?php if (!empty($news)): ?>
            <!-- Featured Article Modal -->
            <?php $featured = $news[0]; ?>
            <div id="featured-article" class="article-full">
                <div class="article-modal" onclick="event.stopPropagation();">
                    <button class="close-article" onclick="closeArticleModal('featured-article')">&times;</button>
                    <h2><?php echo htmlspecialchars($featured['title_' . $lang]); ?></h2>
                    <div class="article-modal-meta">
                        <span><i class="fas fa-calendar"></i> <?php echo date('d.m.Y', strtotime($featured['date_created'])); ?></span>
                        <span><i class="fas fa-user"></i> e-Noteria Team</span>
                    </div>
                    <div class="article-modal-content">
                        <?php echo nl2br(htmlspecialchars($featured['content_' . $lang])); ?>
                    </div>
                </div>
            </div>

            <!-- Other Article Modals -->
            <?php 
            $count = 0;
            foreach ($news as $index => $item): 
                if ($index === 0) continue;
                $count++;
                $modal_id = 'article-' . $count;
            ?>
                <div id="<?php echo $modal_id; ?>" class="article-full">
                    <div class="article-modal" onclick="event.stopPropagation();">
                        <button class="close-article" onclick="closeArticleModal('<?php echo $modal_id; ?>')">&times;</button>
                        <h2><?php echo htmlspecialchars($item['title_' . $lang]); ?></h2>
                        <div class="article-modal-meta">
                            <span><i class="fas fa-calendar"></i> <?php echo date('d.m.Y', strtotime($item['date_created'])); ?></span>
                            <span><i class="fas fa-user"></i> e-Noteria Team</span>
                        </div>
                        <div class="article-modal-content">
                            <?php echo nl2br(htmlspecialchars($item['content_' . $lang])); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="footer-action">
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                <?php echo htmlspecialchars($L['back']); ?>
            </a>
        </div>
    </div>

    <script>
        // Open article modal
        function openArticleModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                // Remove active class from any other modals first (just in case)
                document.querySelectorAll('.article-full').forEach(m => m.classList.remove('active'));
                
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
                
                // Track modal state
                window.currentOpenModalId = modalId;
            }
        }
        
        // Close article modal
        function closeArticleModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        }
        
        // Close modal when clicking on the dark overlay (article-full)
        window.addEventListener('mousedown', function(e) {
            if (e.target.classList.contains('article-full')) {
                closeArticleModal(e.target.id);
            }
        });

        // Keyboard shortcut to close modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const activeModal = document.querySelector('.article-full.active');
                if (activeModal) {
                    closeArticleModal(activeModal.id);
                }
            }
        });
    </script>

    <!-- Chat widget commented out due to undefined reference -->
    <!-- <?php include 'chat-widget.php'; ?> -->
    <script>
        // Guard against undefined chatWidget reference and its methods
        if (typeof chatWidget === 'undefined') {
            window.chatWidget = {
                contains: function(element) { return false; }
            };
        }
    </script>
</body>
</html>
