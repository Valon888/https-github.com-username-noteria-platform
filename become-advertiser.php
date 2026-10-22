<?php
/**
 * Faqja e Nëndryshimit për Reklamues - Become Advertiser
 * Lejon përdoruesit të regjistrohen si reklamues në platformë
 */

session_start();

require_once 'confidb.php';

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    die("Gabim në lidhjen me databazën!");
}

$error = '';
$success = '';

// Përpunoni formën nëse dërgohet
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name'] ?? '');
    $company_email = trim($_POST['company_email'] ?? '');
    $company_phone = trim($_POST['company_phone'] ?? '');
    $company_website = trim($_POST['company_website'] ?? '');
    $company_category = trim($_POST['company_category'] ?? '');
    $company_description = trim($_POST['company_description'] ?? '');
    $business_registration = trim($_POST['business_registration'] ?? '');
    $agreed_terms = isset($_POST['agreed_terms']) ? 1 : 0;
    
    // Validim
    if (empty($company_name) || empty($company_email) || empty($company_category)) {
        $error = 'Ju lutemi plotësoni të gjithë fushat e kërkuara!';
    } elseif (!filter_var($company_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email-i nuk është në formatin e saktë!';
    } elseif ($agreed_terms === 0) {
        $error = 'Ju duhet të pranoni kushtet dhe politikat!';
    } else {
        try {
            // Kontrolloni nëse email-i ekziston
            $check = $pdo->prepare("SELECT id FROM advertisers WHERE email = ?");
            $check->execute([$company_email]);
            
            if ($check->rowCount() > 0) {
                $error = 'Ky email është tashmë i regjistruar!';
            } else {
                // Futni reklamuesin e ri
                try {
                    $stmt = $pdo->prepare("INSERT INTO advertisers (company_name, email, phone, website, category, description, business_registration, subscription_status, created_at) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    
                    $stmt->execute([
                        $company_name,
                        $company_email,
                        $company_phone,
                        $company_website,
                        $company_category,
                        $company_description,
                        $business_registration,
                        'pending'
                    ]);
                    
                    $success = 'Faleminderit për interesin! Ekipi ynë do të kontaktojë shpejt për të konfirmuar kërkesën tuaj.';
                } catch (PDOException $innerException) {
                    // Nëse gabim për 'category', përpiquni pa atë
                    if (strpos($innerException->getMessage(), 'category') !== false || 
                        strpos($innerException->getMessage(), '1054') !== false) {
                        
                        $stmt = $pdo->prepare("INSERT INTO advertisers (company_name, email, phone, website, description, business_registration, subscription_status, created_at) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                        
                        $stmt->execute([
                            $company_name,
                            $company_email,
                            $company_phone,
                            $company_website,
                            $company_description,
                            $business_registration,
                            'pending'
                        ]);
                        
                        $success = 'Faleminderit për interesin! Ekipi ynë do të kontaktojë shpejt për të konfirmuar kërkesën tuaj.';
                    } else {
                        throw $innerException;
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Advertiser Registration Error: " . $e->getMessage());
            $error = 'Ndodhi një gabim gjatë regjistrimit. Verifikoni të dhënat dhe provoni përsëri. (' . $e->getCode() . ')';
        }
    }
}

$user_id = $_SESSION['user_id'] ?? null;
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#1e40af">
    <title>Bëhu Reklamues | Noteria Marketplace</title>
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
        
        /* ==================== HERO SECTION ==================== */
        .hero-section {
            background: linear-gradient(135deg, #1e40af 0%, #2d6cdf 50%, #0ea5e9 100%);
            color: white;
            padding: 80px 20px;
            position: relative;
            overflow: hidden;
            margin-bottom: 60px;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 500px;
            height: 500px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            transform: translate(100px, -100px);
        }
        
        .hero-section::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
            transform: translate(-50px, 100px);
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
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
        
        .hero-section h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 20px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .hero-section p {
            font-size: 1.3rem;
            opacity: 0.95;
            margin-bottom: 30px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* ==================== BENEFITS SECTION ==================== */
        .benefits-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 60px;
        }
        
        .benefit-card {
            background: white;
            padding: 32px;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.1);
            transition: all 0.3s cubic-bezier(0.23, 1, 0.320, 1);
            border: 2px solid transparent;
            text-align: center;
        }
        
        .benefit-card:hover {
            transform: translateY(-8px);
            border-color: #2d6cdf;
            box-shadow: 0 12px 32px rgba(30, 64, 175, 0.2);
        }
        
        .benefit-icon {
            font-size: 3rem;
            color: #2d6cdf;
            margin-bottom: 16px;
        }
        
        .benefit-card h3 {
            color: #1e40af;
            font-size: 1.3rem;
            margin-bottom: 12px;
        }
        
        .benefit-card p {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.6;
        }
        
        /* ==================== MAIN FORM SECTION ==================== */
        .form-section {
            background: white;
            padding: 60px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(30, 64, 175, 0.12);
            margin-bottom: 60px;
        }
        
        .form-section h2 {
            color: #1e40af;
            font-size: 2.2rem;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .form-section .subtitle {
            color: #666;
            text-align: center;
            font-size: 1rem;
            margin-bottom: 40px;
        }
        
        /* ==================== ALERTS ==================== */
        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            align-items: gap;
            gap: 12px;
            font-weight: 500;
        }
        
        .alert-error {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }
        
        .alert-success {
            background: #dcfce7;
            border-left: 4px solid #22c55e;
            color: #166534;
        }
        
        .alert i {
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        /* ==================== FORM GRID ==================== */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full {
            grid-column: 1 / -1;
        }
        
        label {
            color: #1e40af;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
        }
        
        label .required {
            color: #ef4444;
        }
        
        label i {
            font-size: 0.9rem;
            color: #2d6cdf;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="url"],
        select,
        textarea {
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            transition: all 0.3s;
            background: white;
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="tel"]:focus,
        input[type="url"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #2d6cdf;
            box-shadow: 0 0 0 3px rgba(45, 108, 223, 0.1);
            background: #f8fafb;
        }
        
        textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        /* ==================== CHECKBOX ==================== */
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px;
            background: #f8fafb;
            border-radius: 10px;
            border: 2px solid #e5e7eb;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-top: 2px;
            cursor: pointer;
            accent-color: #2d6cdf;
        }
        
        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            font-weight: 500;
            color: #555;
        }
        
        .checkbox-group label a {
            color: #2d6cdf;
            text-decoration: none;
            font-weight: 600;
        }
        
        .checkbox-group label a:hover {
            text-decoration: underline;
        }
        
        /* ==================== SUBMIT BUTTON ==================== */
        .form-actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-top: 40px;
        }
        
        .btn {
            padding: 16px 40px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #1e40af 0%, #2d6cdf 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 64, 175, 0.4);
        }
        
        .btn-secondary {
            background: white;
            color: #1e40af;
            border: 2px solid #1e40af;
        }
        
        .btn-secondary:hover {
            background: #f0f4ff;
            transform: translateY(-2px);
        }
        
        /* ==================== FEATURES GRID ==================== */
        .features-section {
            background: linear-gradient(135deg, #f8fafb 0%, #f0f4ff 100%);
            padding: 60px 20px;
            margin-bottom: 60px;
            border-radius: 20px;
        }
        
        .features-section h2 {
            color: #1e40af;
            font-size: 2.2rem;
            text-align: center;
            margin-bottom: 50px;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 32px;
        }
        
        .feature-item {
            display: flex;
            gap: 20px;
        }
        
        .feature-number {
            background: linear-gradient(135deg, #1e40af 0%, #2d6cdf 100%);
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 700;
            flex-shrink: 0;
        }
        
        .feature-content h3 {
            color: #1e40af;
            margin-bottom: 8px;
            font-size: 1.1rem;
        }
        
        .feature-content p {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.6;
        }
        
        /* ==================== CTA PRICING ==================== */
        .pricing-section {
            background: white;
            padding: 60px 20px;
            margin-bottom: 60px;
            border-radius: 20px;
            text-align: center;
        }
        
        .pricing-section h2 {
            color: #1e40af;
            font-size: 2.2rem;
            margin-bottom: 40px;
        }
        
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 32px;
            max-width: 1100px;
            margin: 0 auto;
        }
        
        .pricing-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            padding: 40px 24px;
            transition: all 0.3s;
            position: relative;
        }
        
        .pricing-card:hover {
            border-color: #2d6cdf;
            box-shadow: 0 12px 32px rgba(30, 64, 175, 0.2);
            transform: translateY(-8px);
        }
        
        .pricing-card.featured {
            border-color: #2d6cdf;
            background: linear-gradient(135deg, #1e40af 0%, #2d6cdf 100%);
            color: white;
            transform: scale(1.05);
        }
        
        .featured-badge {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: #ff6b35;
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
        }
        
        .pricing-card h3 {
            color: inherit;
            font-size: 1.4rem;
            margin-bottom: 12px;
        }
        
        .pricing-card .price {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 8px;
        }
        
        .pricing-card .price span {
            font-size: 1rem;
        }
        
        .pricing-card .period {
            color: inherit;
            opacity: 0.8;
            margin-bottom: 24px;
            font-size: 0.95rem;
        }
        
        .pricing-card ul {
            list-style: none;
            margin-bottom: 30px;
            text-align: left;
        }
        
        .pricing-card li {
            padding: 10px 0;
            border-bottom: 1px solid;
            border-color: inherit;
            opacity: 0.8;
            font-size: 0.9rem;
        }
        
        .pricing-card li:last-child {
            border-bottom: none;
        }
        
        .pricing-card li i {
            margin-right: 8px;
            color: #22c55e;
        }
        
        .pricing-card.featured li i {
            color: #fbbf24;
        }
        
        .pricing-card .btn {
            width: 100%;
            justify-content: center;
        }
        
        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 1024px) {
            .form-section {
                padding: 40px 30px;
            }
            
            .hero-section h1 {
                font-size: 2.5rem;
            }
        }
        
        @media (max-width: 768px) {
            .hero-section {
                padding: 40px 20px;
            }
            
            .hero-section h1 {
                font-size: 2rem;
            }
            
            .hero-section p {
                font-size: 1rem;
            }
            
            .form-section {
                padding: 30px 20px;
            }
            
            .form-section h2 {
                font-size: 1.6rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .features-section h2,
            .pricing-section h2 {
                font-size: 1.6rem;
            }
            
            .pricing-card.featured {
                transform: scale(1);
            }
        }
        
        /* ==================== ANIMATIONS ==================== */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .benefit-card {
            animation: slideInUp 0.6s ease backwards;
        }
        
        .benefit-card:nth-child(1) { animation-delay: 0.1s; }
        .benefit-card:nth-child(2) { animation-delay: 0.2s; }
        .benefit-card:nth-child(3) { animation-delay: 0.3s; }
        .benefit-card:nth-child(4) { animation-delay: 0.4s; }
    </style>
</head>
<body>
    <!-- HERO SECTION -->
    <div class="hero-section">
        <div class="container">
            <div class="hero-content">
                <a href="dashboard.php" class="back-btn"><i class="fas fa-chevron-left"></i> Kthehu në Dashboard</a>
                <h1><i class="fas fa-rocket"></i> Bëhu Reklamues</h1>
                <p>Zgjerojeni arritjen e biznesit tuaj dhe jini në kontakt me mijëra klientë të mundshëm nëpërmjet Marketplace tonë.</p>
            </div>
        </div>
    </div>
    
    <div class="container">
        <!-- BENEFITS SECTION -->
        <div class="benefits-section">
            <div class="benefit-card">
                <div class="benefit-icon"><i class="fas fa-users"></i></div>
                <h3>Audiencë e Gjerë</h3>
                <p>Arritni mijëra përdorues aktiv të interesuarë në produktet e reja dhe ofertat speciale.</p>
            </div>
            
            <div class="benefit-card">
                <div class="benefit-icon"><i class="fas fa-chart-line"></i></div>
                <h3>Rritja e Shitjeve</h3>
                <p>Shtoni trafikun në faqen tuaj dhe rritni konversimet me kampanjet e synuara.</p>
            </div>
            
            <div class="benefit-card">
                <div class="benefit-icon"><i class="fas fa-bar-chart"></i></div>
                <h3>Analitika të Detajuara</h3>
                <p>Mbikëqyrni performancën e reklamave me statistika të plota dhe soportuese.</p>
            </div>
            
            <div class="benefit-card">
                <div class="benefit-icon"><i class="fas fa-headset"></i></div>
                <h3>Suport 24/7</h3>
                <p>Ekipi ynë është gjithmonë i gatshëm të ndihmojë me çdo pyetje apo në çdo kohë.</p>
            </div>
        </div>
        
        <!-- HOW IT WORKS SECTION -->
        <div class="features-section">
            <h2><i class="fas fa-cog"></i> Si Funksionon?</h2>
            <div class="features-grid">
                <div class="feature-item">
                    <div class="feature-number">1</div>
                    <div class="feature-content">
                        <h3>Plotësoni Formularin</h3>
                        <p>Jepni detajet e biznesit tuaj dhe kategoritë e interesit tuaj.</p>
                    </div>
                </div>
                
                <div class="feature-item">
                    <div class="feature-number">2</div>
                    <div class="feature-content">
                        <h3>Verifikimi</h3>
                        <p>Ekipi ynë do të verifikojë kredencialet e biznesit tuaj në 24-48 orë.</p>
                    </div>
                </div>
                
                <div class="feature-item">
                    <div class="feature-number">3</div>
                    <div class="feature-content">
                        <h3>Zgjedhni Paketën</h3>
                        <p>Zgjedhni planin e përshtatshëm për buxhetin dhe qëllimet tuaja.</p>
                    </div>
                </div>
                
                <div class="feature-item">
                    <div class="feature-number">4</div>
                    <div class="feature-content">
                        <h3>Filloni të Reklamoni</h3>
                        <p>Krijoni kampanjet tuaja dhe filloni të arritni klientë të ri menjëherë.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- REGISTRATION FORM -->
        <div class="form-section">
            <h2><i class="fas fa-edit"></i> Regjistrohu Si Reklamues</h2>
            <p class="subtitle">Plotësoni formularin për të arritur të kemimet tuaj të parë</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="form-structure">
                <div class="form-grid">
                    <!-- Company Name -->
                    <div class="form-group">
                        <label for="company_name">
                            <i class="fas fa-building"></i>
                            Emri i Kompanisë
                            <span class="required">*</span>
                        </label>
                        <input type="text" id="company_name" name="company_name" required 
                               placeholder="P.sh. Teknologia Shqiptare">
                    </div>
                    
                    <!-- Category -->
                    <div class="form-group">
                        <label for="company_category">
                            <i class="fas fa-tag"></i>
                            Kategoria
                            <span class="required">*</span>
                        </label>
                        <select id="company_category" name="company_category" required>
                            <option value="">-- Zgjedhni Kategorinë --</option>
                            <option value="produktet">Produktet</option>
                            <option value="sherbimet">Shërbime</option>
                            <option value="edukimi">Edukimi</option>
                            <option value="ushqim">Ushqim & Pije</option>
                            <option value="shendeti">Shëndetësi</option>
                            <option value="teknologjia">Teknologjia</option>
                            <option value="fashion">Fashion & Stil</option>
                            <option value="e-commerce">E-Commerce</option>
                            <option value="tjeter">Tjeter</option>
                        </select>
                    </div>
                    
                    <!-- Email -->
                    <div class="form-group">
                        <label for="company_email">
                            <i class="fas fa-envelope"></i>
                            Email
                            <span class="required">*</span>
                        </label>
                        <input type="email" id="company_email" name="company_email" required 
                               placeholder="info@kompania.com">
                    </div>
                    
                    <!-- Phone -->
                    <div class="form-group">
                        <label for="company_phone">
                            <i class="fas fa-phone"></i>
                            Telefoni
                        </label>
                        <input type="tel" id="company_phone" name="company_phone" 
                               placeholder="+355 69 XXX XXXX">
                    </div>
                    
                    <!-- Website -->
                    <div class="form-group">
                        <label for="company_website">
                            <i class="fas fa-globe"></i>
                            Faqja Zyrtare
                        </label>
                        <input type="url" id="company_website" name="company_website" 
                               placeholder="https://www.kompania.com">
                    </div>
                    
                    <!-- Business Registration -->
                    <div class="form-group">
                        <label for="business_registration">
                            <i class="fas fa-certificate"></i>
                            Numri i Regjistrimit të Biznesit
                        </label>
                        <input type="text" id="business_registration" name="business_registration" 
                               placeholder="P.sh. L123456789">
                    </div>
                    
                    <!-- Description -->
                    <div class="form-group full">
                        <label for="company_description">
                            <i class="fas fa-align-left"></i>
                            Përshkrimi i Biznesit
                        </label>
                        <textarea id="company_description" name="company_description" 
                                  placeholder="Tregoni se çfarë bën biznesit juaj dhe çfarë e bën unik..."></textarea>
                    </div>
                    
                    <!-- Terms Agreement -->
                    <div class="form-group full">
                        <div class="checkbox-group">
                            <input type="checkbox" id="agreed_terms" name="agreed_terms" required>
                            <label for="agreed_terms">
                                Unë pajtohem me <a href="#" target="_blank">Kushtet e Shërbimit</a> 
                                dhe <a href="#" target="_blank">Politikën e Privatësisë</a> të Noteria Marketplace
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Dorëzoni Kërkesën
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Anulo
                    </a>
                </div>
            </form>
        </div>
        
        <!-- PRICING SECTION -->
        <div class="pricing-section">
            <h2><i class="fas fa-tag"></i> Planet e Paketim</h2>
            
            <div class="pricing-grid">
                <!-- Starter Plan -->
                <div class="pricing-card">
                    <h3>Starter</h3>
                    <div class="price">$9<span>/muaj</span></div>
                    <div class="period">Ideal për fillesat</div>
                    <ul>
                        <li><i class="fas fa-check"></i> Deri në 5 reklamat</li>
                        <li><i class="fas fa-check"></i> Analitika bazike</li>
                        <li><i class="fas fa-check"></i> Suport email</li>
                        <li><i class="fas fa-times" style="opacity: 0.4;"></i> Takim personalizuar</li>
                    </ul>
                    <button class="btn btn-secondary">Zgjedhni Planin</button>
                </div>
                
                <!-- Professional Plan (Featured) -->
                <div class="pricing-card featured">
                    <div class="featured-badge"><i class="fas fa-star"></i> MË POPULARE</div>
                    <h3>Professional</h3>
                    <div class="price">$29<span>/muaj</span></div>
                    <div class="period">Për biznesat në rritje</div>
                    <ul>
                        <li><i class="fas fa-check"></i> Deri në 25 reklamat</li>
                        <li><i class="fas fa-check"></i> Analitika të matura</li>
                        <li><i class="fas fa-check"></i> Suport prioritar</li>
                        <li><i class="fas fa-check"></i> Takim personalizuar menjëherë</li>
                    </ul>
                    <button class="btn btn-primary" style="background: white; color: #2d6cdf;">Zgjedhni Planin</button>
                </div>
                
                <!-- Enterprise Plan -->
                <div class="pricing-card">
                    <h3>Enterprise</h3>
                    <div class="price">$99<span>/muaj</span></div>
                    <div class="period">Për ndërmarrjet e mëdha</div>
                    <ul>
                        <li><i class="fas fa-check"></i> Reklamat e pakufizuara</li>
                        <li><i class="fas fa-check"></i> Analitika të plota</li>
                        <li><i class="fas fa-check"></i> Dedicated account manager</li>
                        <li><i class="fas fa-check"></i> Integrime custom</li>
                    </ul>
                    <button class="btn btn-secondary">Kontaktoni Ne</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Form submission handling
        document.querySelector('form').addEventListener('submit', function(e) {
            const agreed = document.getElementById('agreed_terms').checked;
            if (!agreed) {
                e.preventDefault();
                alert('Ju lutemi pranoni kushtet dhe politikat!');
            }
        });
    </script>
</body>
</html>
