<?php
// Konfigurimi i raportimit të gabimeve
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Inicializimi i sesionit
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kontrolli i autentifikimit - nëse nuk është i loguar, shfaq faqen hyrëse publike
$is_logged_in = !empty($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['emri'] ?? $_SESSION['username'] ?? 'Përdorues';
$user = null;

// Nëse përdoruesi është i loguar, merr të dhënat e tij
if ($is_logged_in) {
    require_once 'db_connection.php';
    
    // Merr të dhënat e përdoruesit
    $stmt = $conn->prepare("SELECT id, emri, mbiemri, email, telefoni, photo_path FROM users WHERE id = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    // Merr statistikat e përdoruesit
    $stats = [
        'reservations' => 0,
        'payments' => 0,
        'subscription_status' => 'Aktiv'
    ];

    // Numri i rezervimeve
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM reservations WHERE user_id = ? AND status != 'cancelled'");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stats['reservations'] = $res['count'] ?? 0;
    $stmt->close();

    // Numri i pagesash
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM payments WHERE user_id = ? AND status = 'completed'");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stats['payments'] = $res['count'] ?? 0;
    $stmt->close();

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Noteria - Platform Noteriale</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        /* Header Navigation */
        header {
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            padding: 16px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: #667eea;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logo i {
            font-size: 2rem;
        }

        nav {
            display: flex;
            gap: 24px;
            align-items: center;
        }

        nav a {
            text-decoration: none;
            color: #333;
            font-weight: 600;
            transition: all 0.3s;
            padding: 8px 12px;
            border-radius: 6px;
        }

        nav a:hover {
            background: #e2eafc;
            color: #667eea;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
        }

        .dropdown {
            position: relative;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: white;
            min-width: 200px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            border-radius: 8px;
            padding: 8px 0;
            top: 100%;
            margin-top: 8px;
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        .dropdown-content a {
            display: block;
            padding: 12px 16px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s;
        }

        .dropdown-content a:hover {
            background: #e2eafc;
            color: #667eea;
            padding-left: 24px;
        }

        /* Main Container */
        .main-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* Welcome Section */
        .welcome-section {
            background: white;
            border-radius: 16px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
        }

        .welcome-content h1 {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 16px;
        }

        .welcome-content p {
            font-size: 1.1rem;
            color: #666;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .user-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
        }

        .user-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 16px;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            overflow: hidden;
            border: 3px solid white;
        }

        .user-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .user-email {
            font-size: 0.95rem;
            opacity: 0.9;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .action-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: #333;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }

        .action-icon {
            font-size: 2.5rem;
            color: #667eea;
            margin-bottom: 16px;
        }

        .action-card h3 {
            font-size: 1.2rem;
            margin-bottom: 8px;
            color: #333;
        }

        .action-card p {
            font-size: 0.9rem;
            color: #666;
        }

        /* Statistics Section */
        .stats-section {
            background: white;
            border-radius: 16px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }

        .stats-section h2 {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 1rem;
            opacity: 0.9;
        }

        /* Services Section */
        .services-section {
            background: white;
            border-radius: 16px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }

        .services-section h2 {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .service-card {
            background: linear-gradient(135deg, #ffffff 0%, #f5f7fa 100%);
            border: 2px solid #e2eafc;
            border-radius: 16px;
            padding: 32px 24px;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #333;
            position: relative;
            overflow: hidden;
        }

        .service-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: -1;
        }

        .service-card:hover {
            border-color: #667eea;
            box-shadow: 0 12px 32px rgba(102, 126, 234, 0.25);
            transform: translateY(-8px);
        }

        .service-card:hover .service-icon {
            color: white;
            transform: scale(1.2) rotate(5deg);
        }

        .service-card:hover h3,
        .service-card:hover p {
            color: white;
        }

        .service-icon {
            font-size: 2.8rem;
            color: #667eea;
            margin-bottom: 16px;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .service-card h3 {
            font-size: 1.3rem;
            margin-bottom: 8px;
            font-weight: 700;
            transition: color 0.3s ease;
        }

        .service-card p {
            font-size: 0.95rem;
            color: #666;
            line-height: 1.5;
            transition: color 0.3s ease;
        }

        /* Pseudo-element hover effect */
        .service-card:hover::before {
            opacity: 1;
        }

        .service-card p {
            font-size: 0.95rem;
            color: #666;
            line-height: 1.5;
        }

        /* Footer */
        footer {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px 20px;
            text-align: center;
            color: #666;
            margin-top: 60px;
            box-shadow: 0 -2px 20px rgba(0, 0, 0, 0.1);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .welcome-section {
                grid-template-columns: 1fr;
            }

            .welcome-content h1 {
                font-size: 2rem;
            }

            nav {
                flex-direction: column;
                gap: 12px;
            }

            .quick-actions, .services-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
            <a href="index.php" class="logo">
                <i class="fas fa-certificate"></i>
                <span>NOTERIA</span>
            </a>
            <nav>
                <?php if ($is_logged_in): ?>
                    <a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
                    <a href="reservation.php"><i class="fas fa-calendar"></i> Rezervimet</a>
                    <a href="e_signature.php"><i class="fas fa-pen-fancy"></i> E-Nënshkrime</a>
                    <a href="abonime_dashboard.php"><i class="fas fa-star"></i> Abonimet</a>
                    <a href="video_call.php"><i class="fas fa-video"></i> Video Thirrje</a>
                    <div class="dropdown user-menu">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user['emri'] ?? 'U', 0, 1)); ?>
                        </div>
                        <div class="dropdown-content">
                            <a href="profile.php"><i class="fas fa-user"></i> Profili Im</a>
                            <a href="settings.php"><i class="fas fa-cog"></i> Cilësimet</a>
                            <a href="logout.php" style="color: #d32f2f;"><i class="fas fa-sign-out-alt"></i> Dilni</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php"><i class="fas fa-sign-in-alt"></i> Kyçuni</a>
                    <a href="register.php" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 10px 20px;"><i class="fas fa-user-plus"></i> Regjistrohu</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="welcome-content">
                <h1>Mirë se vini në Noteria!</h1>
                <p>Platforma profesionale për të gjithë shërbimeve noteriale. Menaxhoni rezervimet, pagesat, video konsultencat dhe abonimet tuaja në një vend të sigurt dhe të përshtatshëm.</p>
                <p><strong>Përvojë e thjeshtë, shërbim i shpejtë, siguri maksimale.</strong></p>
                <?php if (!$is_logged_in): ?>
                    <div style="margin-top: 24px;">
                        <a href="register.php" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 32px; border-radius: 8px; text-decoration: none; font-weight: 600;">Regjistrohu Tani</a>
                        <a href="login.php" style="display: inline-block; margin-left: 12px; background: white; color: #667eea; border: 2px solid #667eea; padding: 10px 30px; border-radius: 8px; text-decoration: none; font-weight: 600;">Kyçuni</a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="user-info-card">
                <?php if ($is_logged_in): ?>
                    <div class="user-photo">
                        <?php if ($user['photo_path'] && file_exists($user['photo_path'])): ?>
                            <img src="<?php echo htmlspecialchars($user['photo_path']); ?>" alt="Profil">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div class="user-name"><?php echo htmlspecialchars($user['emri'] . ' ' . $user['mbiemri']); ?></div>
                    <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                <?php else: ?>
                    <svg viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg" style="max-width: 100%; height: auto;">
                        <!-- Background gradient -->
                        <defs>
                            <linearGradient id="bgGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#667eea;stop-opacity:0.1" />
                                <stop offset="100%" style="stop-color:#764ba2;stop-opacity:0.1" />
                            </linearGradient>
                        </defs>
                        <rect width="400" height="400" fill="url(#bgGradient)" rx="20"/>
                        
                        <!-- Happy Family -->
                        <g transform="translate(50, 80)">
                            <text x="0" y="-10" font-size="24" font-weight="bold" fill="#667eea">Familja</text>
                            <!-- Father -->
                            <circle cx="15" cy="20" r="8" fill="#d4a373"/>
                            <rect x="10" y="30" width="10" height="20" fill="#4a90e2"/>
                            <rect x="8" y="50" width="4" height="15" fill="#d4a373"/>
                            <rect x="18" y="50" width="4" height="15" fill="#d4a373"/>
                            <text x="15" y="70" font-size="20" text-anchor="middle">😊</text>
                            
                            <!-- Mother -->
                            <circle cx="40" cy="20" r="8" fill="#d4a373"/>
                            <polygon points="35,30 45,30 43,50 37,50" fill="#e94b3c"/>
                            <rect x="33" y="50" width="4" height="15" fill="#d4a373"/>
                            <rect x="43" y="50" width="4" height="15" fill="#d4a373"/>
                            <text x="40" y="70" font-size="20" text-anchor="middle">😊</text>
                            
                            <!-- Child -->
                            <circle cx="27.5" cy="30" r="6" fill="#d4a373"/>
                            <rect x="23.5" y="38" width="8" height="12" fill="#6c63ff"/>
                            <rect x="22" y="50" width="3" height="10" fill="#d4a373"/>
                            <rect x="30.5" y="50" width="3" height="10" fill="#d4a373"/>
                            <text x="27.5" y="68" font-size="18" text-anchor="middle">😊</text>
                        </g>

                        <!-- Notary -->
                        <g transform="translate(170, 80)">
                            <text x="0" y="-10" font-size="24" font-weight="bold" fill="#667eea">Noter</text>
                            <!-- Head -->
                            <circle cx="20" cy="20" r="9" fill="#d4a373"/>
                            <!-- Suit -->
                            <polygon points="11,32 29,32 26,55 14,55" fill="#1a1a2e"/>
                            <!-- Tie -->
                            <polygon points="18,32 22,32 21,40 19,40" fill="#e74c3c"/>
                            <!-- Briefcase -->
                            <rect x="15" y="50" width="10" height="8" fill="#8b4513"/>
                            <rect x="17" y="48" width="6" height="2" fill="#8b4513"/>
                            <!-- Document -->
                            <rect x="28" y="45" width="8" height="12" fill="#ecf0f1"/>
                            <line x1="29" y1="48" x2="35" y2="48" stroke="#333" stroke-width="0.5"/>
                            <text x="20" y="70" font-size="20" text-anchor="middle">✔️</text>
                        </g>

                        <!-- Businesswoman -->
                        <g transform="translate(290, 80)">
                            <text x="0" y="-10" font-size="24" font-weight="bold" fill="#667eea">Biznese</text>
                            <!-- Head -->
                            <circle cx="18" cy="20" r="8" fill="#d4a373"/>
                            <!-- Hair -->
                            <path d="M 10 20 Q 10 10 18 10 Q 26 10 26 20" fill="#8B4513"/>
                            <!-- Suit -->
                            <polygon points="10,30 26,30 23,55 13,55" fill="#2c3e50"/>
                            <!-- Laptop -->
                            <rect x="25" y="40" width="10" height="7" fill="#34495e"/>
                            <rect x="25.5" y="40.5" width="9" height="5" fill="#3498db"/>
                            <!-- Briefcase -->
                            <rect x="5" y="50" width="8" height="6" fill="#c0392b"/>
                            <text x="18" y="68" font-size="20" text-anchor="middle">💼</text>
                        </g>

                        <!-- Video Conference -->
                        <g transform="translate(50, 200)">
                            <text x="100" y="-10" font-size="24" font-weight="bold" fill="#667eea" text-anchor="middle">Video Konferencë në Zyre</text>
                            <!-- Monitor/Screen -->
                            <rect x="30" y="20" width="140" height="80" fill="none" stroke="#667eea" stroke-width="3" rx="5"/>
                            <!-- Screen content - people video -->
                            <rect x="35" y="25" width="60" height="70" fill="#e8eaf6"/>
                            <circle cx="50" cy="45" r="12" fill="#d4a373"/>
                            <polygon points="38,60 62,60 60,68 40,68" fill="#4a90e2"/>
                            
                            <rect x="100" y="25" width="60" height="70" fill="#e8eaf6"/>
                            <circle cx="115" cy="45" r="12" fill="#d4a373"/>
                            <polygon points="103,60 127,60 125,68 105,68" fill="#e94b3c"/>
                            
                            <!-- Laptop stand -->
                            <line x1="60" y1="105" x2="50" y2="115" stroke="#999" stroke-width="2"/>
                            <line x1="110" y1="105" x2="120" y2="115" stroke="#999" stroke-width="2"/>
                            <rect x="40" y="115" width="140" height="3" fill="#999"/>
                            
                            <!-- Check mark - secure -->
                            <circle cx="160" cy="110" r="20" fill="#27ae60" opacity="0.9"/>
                            <text x="160" y="120" font-size="28" font-weight="bold" fill="white" text-anchor="middle">✓</text>
                        </g>

                        <!-- Speed indicator -->
                        <g transform="translate(280, 230)">
                            <text x="0" y="0" font-size="20" font-weight="bold" fill="#667eea">Shpejt & Sigurt</text>
                            <circle cx="25" cy="25" r="15" fill="none" stroke="#667eea" stroke-width="2"/>
                            <path d="M 25 10 L 30 25 L 20 25 Z" fill="#667eea"/>
                            <line x1="25" y1="40" x2="25" y2="50" stroke="#667eea" stroke-width="2"/>
                        </g>
                    </svg>
                <?php endif; ?>
            </div>
        </div>

        <!-- Trust & Benefits Section (for public users) -->
        <?php if (!$is_logged_in): ?>
        <div style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); border-radius: 16px; padding: 40px; margin-bottom: 30px;">
            <h2 style="text-align: center; color: #333; margin-bottom: 30px; font-size: 1.8rem;">Përse të Zgjedhni Noterian?</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px;">
                <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center;">
                    <div style="font-size: 2.5rem; margin-bottom: 12px;">🔒</div>
                    <h3 style="color: #667eea; margin-bottom: 8px;">Siguresi Maksimale</h3>
                    <p style="color: #666; font-size: 0.95rem;">Të dhënat tuaja janë të mbrojtura me enkriptim të avancuar dhe pëlqim të GDPR.</p>
                </div>
                <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center;">
                    <div style="font-size: 2.5rem; margin-bottom: 12px;">⚡</div>
                    <h3 style="color: #667eea; margin-bottom: 8px;">Shpejt & Eficient</h3>
                    <p style="color: #666; font-size: 0.95rem;">Përfundoni procedurat noteriale në minuta, jo në orë apo ditë.</p>
                </div>
                <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center;">
                    <div style="font-size: 2.5rem; margin-bottom: 12px;">👨‍💼</div>
                    <h3 style="color: #667eea; margin-bottom: 8px;">Notera Profesionalë</h3>
                    <p style="color: #666; font-size: 0.95rem;">Punë me notera të certifikuar dhe të përvojshëm në platformën tonë.</p>
                </div>
                <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center;">
                    <div style="font-size: 2.5rem; margin-bottom: 12px;">💻</div>
                    <h3 style="color: #667eea; margin-bottom: 8px;">Platforma Moderne</h3>
                    <p style="color: #666; font-size: 0.95rem;">Qasje 24/7 nga kudo - telefon, tablet ose kompjuter.</p>
                </div>
                <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center;">
                    <div style="font-size: 2.5rem; margin-bottom: 12px;">🎥</div>
                    <h3 style="color: #667eea; margin-bottom: 8px;">Video Konsultime</h3>
                    <p style="color: #666; font-size: 0.95rem;">Kontaktoni notera direkt përmes video për pyetje dhe këshillim.</p>
                </div>
                <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center;">
                    <div style="font-size: 2.5rem; margin-bottom: 12px;">💰</div>
                    <h3 style="color: #667eea; margin-bottom: 8px;">Çmime Transparente</h3>
                    <p style="color: #666; font-size: 0.95rem;">Asnjë kosto e fshehur - shihni çmimin përpara se të përfundoni.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <?php if ($is_logged_in): ?>
        <div class="quick-actions">
            <a href="reservation.php" class="action-card">
                <div class="action-icon"><i class="fas fa-plus-circle"></i></div>
                <h3>Krijoni Rezervim</h3>
                <p>Rezervoni një konsultim notelial tani</p>
            </a>
            <a href="abonime_dashboard.php" class="action-card">
                <div class="action-icon"><i class="fas fa-crown"></i></div>
                <h3>Zgjidh Abonimin</h3>
                <p>Marrni qasje në shërbime premium</p>
            </a>
            <a href="video_call.php" class="action-card">
                <div class="action-icon"><i class="fas fa-phone-volume"></i></div>
                <h3>Video Konsultim</h3>
                <p>Flaini me noter nëpërmjet video</p>
            </a>
            <a href="payment_confirmation.php" class="action-card">
                <div class="action-icon"><i class="fas fa-credit-card"></i></div>
                <h3>Pagesat</h3>
                <p>Trajtoni pagesat tuaja dhe faturat</p>
            </a>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <?php if ($is_logged_in): ?>
        <div class="stats-section">
            <h2>Statistikat e Juaja</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['reservations']; ?></div>
                    <div class="stat-label">Rezervime Aktive</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['payments']; ?></div>
                    <div class="stat-label">Pagesa të Përfunduara</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['subscription_status']; ?></div>
                    <div class="stat-label">Statusi Abonimit</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Services -->
        <div class="services-section">
            <h2>Shërbime Noteriale</h2>
            <div class="services-grid">
                <a href="reservation.php" class="service-card">
                    <div class="service-icon"><i class="fas fa-file-contract"></i></div>
                    <h3>Autentifikime Dokumentesh</h3>
                    <p>Autentifikimet e dokumenteve personale, kontratash dhe marrëveshjesh.</p>
                </a>
                <a href="reservation.php" class="service-card">
                    <div class="service-icon"><i class="fas fa-handshake"></i></div>
                    <h3>Vërtetimet Nënshkrimesh</h3>
                    <p>Vërtetime të nënshkrimeve dhe faksimile të dokumenteve publike.</p>
                </a>
                <a href="video_call.php" class="service-card">
                    <div class="service-icon"><i class="fas fa-video"></i></div>
                    <h3>Konsultime Video</h3>
                    <p>Konsultime direkte me noter nëpërmjet videokamer të sigurt.</p>
                </a>
                <a href="reservation.php" class="service-card">
                    <div class="service-icon"><i class="fas fa-scroll"></i></div>
                    <h3>Testamentet</h3>
                    <p>Përgatitja dhe autentifikimi i testamenteve dhe marrëveshjeve të tjera.</p>
                </a>
                <a href="reservation.php" class="service-card">
                    <div class="service-icon"><i class="fas fa-shield-alt"></i></div>
                    <h3>Marrëveshje Ligjore</h3>
                    <p>Përgatitja dhe ligjërim i marrëveshjeve dhe kontratash të ndryshme.</p>
                </a>
                <a href="abonime_dashboard.php" class="service-card">
                    <div class="service-icon"><i class="fas fa-gem"></i></div>
                    <h3>Abonime Premium</h3>
                    <p>Zgjidh një abonimin përshtatës për nevojat tuaja noteriale.</p>
                </a>
            </div>
        </div>

        <!-- Testimonials Section (for public users) -->
        <?php if (!$is_logged_in): ?>
        <div style="margin-top: 60px; margin-bottom: 40px;">
            <h2 style="text-align: center; color: #333; margin-bottom: 40px; font-size: 1.8rem;">Përvojat e Përdoruesve Tanë</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
                <!-- Testimonial 1 -->
                <div style="background: white; border-radius: 16px; padding: 28px; box-shadow: 0 4px 16px rgba(0,0,0,0.1); border-left: 5px solid #667eea;">
                    <div style="display: flex; align-items: center; margin-bottom: 16px;">
                        <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=50&h=50&fit=crop" alt="Familja" style="width: 50px; height: 50px; border-radius: 50%; margin-right: 12px; object-fit: cover;">
                        <div>
                            <h4 style="margin: 0; color: #333; font-size: 1.1rem;">Familja Buçaj</h4>
                            <p style="margin: 0; color: #999; font-size: 0.9rem;">Prishtinë</p>
                        </div>
                    </div>
                    <p style="color: #666; line-height: 1.6; margin: 0;">
                        "Përvojë e mahnitshme! Procesi i autentifikimit të dokumenteve të pronës zgjati vetëm 20 minuta përmes Noteries. Nuk na duhej të dilnim nga shtëpia!"
                    </p>
                    <div style="color: #ffc107; margin-top: 12px; font-size: 1.2rem;">★★★★★</div>
                </div>

                <!-- Testimonial 2 -->
                <div style="background: white; border-radius: 16px; padding: 28px; box-shadow: 0 4px 16px rgba(0,0,0,0.1); border-left: 5px solid #764ba2;">
                    <div style="display: flex; align-items: center; margin-bottom: 16px;">
                        <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=50&h=50&fit=crop" alt="Drita" style="width: 50px; height: 50px; border-radius: 50%; margin-right: 12px; object-fit: cover;">
                        <div>
                            <h4 style="margin: 0; color: #333; font-size: 1.1rem;">Drita Halili</h4>
                            <p style="margin: 0; color: #999; font-size: 0.9rem;">Biznese, Prishtinë</p>
                        </div>
                    </div>
                    <p style="color: #666; line-height: 1.6; margin: 0;">
                        "Si biznesite, kemi shumë dokumenta për autenfikuar. Noteria na ka shpëtuar kohën - video konsultime direkt me notera çdo orë që na duhet."
                    </p>
                    <div style="color: #ffc107; margin-top: 12px; font-size: 1.2rem;">★★★★★</div>
                </div>

                <!-- Testimonial 3 -->
                <div style="background: white; border-radius: 16px; padding: 28px; box-shadow: 0 4px 16px rgba(0,0,0,0.1); border-left: 5px solid #e94b3c;">
                    <div style="display: flex; align-items: center; margin-bottom: 16px;">
                        <img src="https://images.unsplash.com/photo-1507009996859-a8fa475dacfe?w=50&h=50&fit=crop" alt="Xhemajl" style="width: 50px; height: 50px; border-radius: 50%; margin-right: 12px; object-fit: cover;">
                        <div>
                            <h4 style="margin: 0; color: #333; font-size: 1.1rem;">Xhemajl Mustafa</h4>
                            <p style="margin: 0; color: #999; font-size: 0.9rem;">Prizren</p>
                        </div>
                    </div>
                    <p style="color: #666; line-height: 1.6; margin: 0;">
                        "Siguria dhe transparenca e Noteries më dha besim të plotë. Nuk kishte kosto të fshehur dhe notera ishte mjaft këshillues."
                    </p>
                    <div style="color: #ffc107; margin-top: 12px; font-size: 1.2rem;">★★★★★</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- CTA Section for Public Users -->
        <?php if (!$is_logged_in): ?>
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 16px; padding: 50px 30px; text-align: center; margin-bottom: 40px;">
            <h2 style="color: white; margin-bottom: 16px; font-size: 2rem;">Jeni Gati të Filloni?</h2>
            <p style="color: rgba(255,255,255,0.9); margin-bottom: 30px; font-size: 1.1rem;">Regjistrohu tani dhe përfito akses në të gjithë shërbimeve noteriale të Noteries.</p>
            <a href="register.php" style="display: inline-block; background: white; color: #667eea; padding: 16px 48px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 1.1rem; transition: all 0.3s;">
                Regjistrohu Falas Tani
            </a>
        </div>
        <?php endif; ?>

                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <p>&copy; 2025 Noteria - Platform Noteriale. Të gjitha të drejtat e rezervuara. | 
        <a href="Privacy_policy.php" style="color: #667eea; text-decoration: none;">Privatësia</a> | 
        <a href="terms.php" style="color: #667eea; text-decoration: none;">Kushtet e Përdorimit</a></p>
    </footer>
</body>
</html>