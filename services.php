<?php
// Include performance monitoring
require_once 'performance.php';

// Include advanced security system
require_once 'security.php';

// Initialize security system
initSecurity();

// Session is already started by security system
// session_start();
$is_logged_in = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <title>Shërbimet | e-Noteria | Republika e Kosovës</title>
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <style>
        :root {
            --rks-blue: #003366;
            --rks-blue-light: #004080;
            --rks-blue-dark: #002244;
            --rks-gold: #cfa856;
            --rks-gold-light: #e0c078;
            --bg-gray: #f2f4f8;
            --shadow-sm: 0 2px 8px rgba(0, 51, 102, 0.08);
            --shadow-md: 0 8px 24px rgba(0, 51, 102, 0.12);
            --shadow-lg: 0 16px 48px rgba(0, 51, 102, 0.16);
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--bg-gray);
            color: #333;
            overflow-x: hidden;
        }
        
        /* Top Strip */
        .gov-bar {
            background: #fff;
            border-bottom: 1px solid #e1e4e8;
            padding: 8px 0;
            font-size: 13px;
            color: #586069;
        }
        
        /* Navbar */
        .main-nav {
            background: #fff;
            padding: 16px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .brand {
            font-weight: 700;
            font-size: 22px;
            color: var(--rks-blue);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .brand .logo-img {
            height: 45px;
            width: 45px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
            transition: all 0.3s ease;
        }
        .brand:hover .logo-img {
            filter: drop-shadow(0 4px 8px rgba(207, 168, 86, 0.4));
            transform: scale(1.05);
        }
        .brand span { color: #000; }

        /* Services */
        .services-grid {
            padding: 80px 0;
            position: relative;
        }
        .services-grid::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(0,51,102,0.05) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .service-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 30px 24px;
            border-radius: 12px;
            text-align: center;
            height: 100%;
            border: 1px solid #eaeaea;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
            border-color: var(--rks-blue);
            z-index: 2;
        }
        .icon-circle {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #f0f4f8 0%, #e9ecef 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: var(--rks-blue);
            font-size: 1.75rem;
            border: 2px solid rgba(0,51,102,0.1);
        }
        .service-card h5 { 
            font-weight: 700; 
            margin-bottom: 14px; 
            font-size: 1.25rem; 
            color: var(--rks-blue);
            transition: var(--transition);
        }
        .service-card:hover h5 {
            color: var(--rks-blue-light);
            transform: translateY(-2px);
        }
        .service-card p { 
            font-size: 0.95rem; 
            color: #666; 
            margin: 0; 
            line-height: 1.6;
            transition: var(--transition);
        }
        .service-card:hover p {
            color: #444;
        }

        /* Footer */
        footer {
            background: #24292e;
            color: #d1d5da;
            padding: 40px 0 20px;
            font-size: 0.9rem;
        }
        footer a { color: #8b949e; text-decoration: none; display: block; margin-bottom: 8px; }
        footer a:hover { color: white; }
        .footer-logo { font-weight: 700; color: white; font-size: 1.2rem; margin-bottom: 16px; display: block; }
    </style>
</head>
<body>

    <!-- Top Strip -->
    <div class="gov-bar">
        <div class="container d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <!-- Inline SVG for Gov Logo -->
                <svg width="24" height="24" viewBox="0 0 100 100" class="me-2">
                    <path d="M50 0 L90 20 L90 50 Q90 80 50 100 Q10 80 10 50 L10 20 Z" fill="#244d96"/>
                    <path d="M30 40 L50 60 L70 40" stroke="#cfa856" stroke-width="5" fill="none"/>
                </svg>
                <span>Republika e Kosovës | Qeveria</span>
            </div>
            <div>
                <a href="#" class="text-decoration-none text-muted me-3">Shqip</a>
                <a href="#" class="text-decoration-none text-muted me-3">Srpski</a>
                <a href="#" class="text-decoration-none text-muted">English</a>
            </div>
        </div>
    </div>

    <!-- Main Nav -->
    <nav class="main-nav">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="index.php" class="brand">
                <!-- Logo Image -->
                <img src="images/pngwing.com (1).png" alt="e-Noteria Logo" class="logo-img">
                e<span class="text-primary">-Noteria</span>
            </a>
            
            <div class="d-flex align-items-center gap-4 d-none d-md-flex">
                <a href="services.php" class="text-dark text-decoration-none fw-medium">Shërbimet</a>
                <a href="news.php" class="text-dark text-decoration-none fw-medium">Lajme</a>
                <a href="ndihma.php" class="text-dark text-decoration-none fw-medium">Ndihma</a>
                <?php if ($is_logged_in): ?>
                    <a href="dashboard.php" class="btn btn-primary px-4 rounded-pill fw-bold">Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-primary px-4 rounded-pill fw-bold">Kyçuni</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Services Section -->
    <div class="container services-grid">
        <h1 class="text-center mb-5 fw-bold" style="color: var(--rks-blue);">Shërbimet Tona</h1>
        <p class="text-center text-muted mb-5 fs-5">Zbuloni shërbimet tona digjitale për të gjitha nevojat tuaja noteriale. Ne ofrojmë zgjidhje të shpejta, të sigurta dhe efikase për çdo procedurë.</p>
        
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="icon-circle"><i class="fas fa-cloud"></i></div>
                    <h5>Platformë SaaS</h5>
                    <p>Platformë cloud-based për zyrat noteriale me abonim mujor 150€. Akses i plotë në të gjitha shërbimet dhe veçoritë.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <a href="reservation.php" class="service-card">
                    <div class="icon-circle"><i class="far fa-calendar-check"></i></div>
                    <h5>Rezervo Termin</h5>
                    <p>Cakto takim online me një noter të licencuar. Sistemi ynë i avancuar ju lejon të zgjidhni datën dhe orën që ju përshtatet më së miri.</p>
                </a>
            </div>
            <div class="col-lg-4 col-md-6">
                <a href="#" class="service-card">
                    <div class="icon-circle"><i class="fas fa-file-signature"></i></div>
                    <h5>Shitblerje Pronësie</h5>
                    <p>Kontrata dhe dokumente për shitblerje pronësie. Procesi i plotë digjital për transferimin e pronësisë në mënyrë të sigurt dhe ligjor.</p>
                </a>
            </div>
            <div class="col-lg-4 col-md-6">
                <a href="#" class="service-card">
                    <div class="icon-circle"><i class="fas fa-stamp"></i></div>
                    <h5>Vërtetime dhe Legalizime</h5>
                    <p>Vërtetimi dhe legalizimi i dokumenteve. Shërbime për certifikimin e dokumenteve personale dhe zyrtare sipas ligjit.</p>
                </a>
            </div>
            <div class="col-lg-4 col-md-6">
                <a href="video_call.php" class="service-card">
                    <div class="icon-circle"><i class="fas fa-video"></i></div>
                    <h5>E-Konsulta</h5>
                    <p>Video konferencë me noter për këshilla dhe konsultime. Mundësi për komunikim të drejtpërdrejtë pa dalë nga shtëpia.</p>
                </a>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="icon-circle"><i class="fas fa-euro-sign"></i></div>
                    <h5>Abonim Mujor</h5>
                    <p>150€ në muaj për çdo zyrë noteriale. Përfshin të gjitha shërbimet, mbështetjen teknike dhe përditësimet.</p>
                </div>
            </div>
        </div>

        <!-- Additional Services -->
        <div class="row mt-5">
            <div class="col-12">
                <h3 class="text-center mb-4 fw-bold" style="color: var(--rks-blue);">Shërbime të Plota Noteriale</h3>
            </div>
        </div>

        <div class="row g-4">
            <!-- Vërtetimi i nënshkrimeve dhe kopjeve -->
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="icon-circle"><i class="fas fa-signature"></i></div>
                    <h5>Vërtetimi i Nënshkrimeve</h5>
                    <p>Vërtetimi i nënshkrimeve në kontrata, kërkesa dhe dokumente të ndryshme për vlefshmërinë ligjore.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="icon-circle"><i class="fas fa-copy"></i></div>
                    <h5>Vërtetimi i Kopjeve</h5>
                    <p>Vërtetimi i kopjeve të dokumenteve si diplomave, certifikatave, vendimeve gjyqësore dhe dokumenteve të tjera zyrtare.</p>
                </div>
            </div>

            <!-- Kontrata dhe marrëveshje -->
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="icon-circle"><i class="fas fa-home"></i></div>
                    <h5>Kontrata Shitblerjeje</h5>
                    <p>Kontrata për shitblerje të pasurisë së paluajtshme si shtëpi, toka dhe apartamente me garanci ligjore të plotë.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="icon-circle"><i class="fas fa-gift"></i></div>
                    <h5>Kontrata Dhurimi</h5>
                    <p>Hartimi dhe vërtetimi i kontratave të dhurimit të pasurisë me të gjitha kërkesat ligjore.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="icon-circle"><i class="fas fa-key"></i></div>
                    <h5>Kontrata Qiraje</h5>
                    <p>Kontrata qiraje për banesa dhe objekte tregtare me kushte të qarta dhe mbrojtje ligjore.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="icon-circle"><i class="fas fa-money-bill-wave"></i></div>
                    <h5>Kontrata Huadhënieje</h5>
                    <p>Kontrata huadhënieje dhe hipotekimi me garanci dhe kushte të rregulluara ligjor.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="icon-circle"><i class="fas fa-handshake"></i></div>
                    <h5>Kontrata Tregtare</h5>
                    <p>Kontrata të ndryshme tregtare dhe civile për biznes dhe individë me ekspertizë profesionale.</p>
                </div>
            </div>

            <!-- Deklarata dhe plotfuqizorime -->
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="icon-circle"><i class="fas fa-file-alt"></i></div>
                    <h5>Deklarata me Shkrim</h5>
                    <p>Hartimi dhe vërtetimi i deklaratave me shkrim për çdo lloj nevoje ligjore dhe administrative.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="icon-circle"><i class="fas fa-user-check"></i></div>
                    <h5>Plotfuqizorime të Përgjithshme</h5>
                    <p>Plotfuqizorime të përgjithshme dhe të veçanta për përfaqësim në institucione dhe procedura.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="icon-circle"><i class="fas fa-id-card"></i></div>
                    <h5>Plotfuqizorime për Institucione</h5>
                    <p>Plotfuqizorime specifike për përfaqësim në gjykata, banka dhe institucione të tjera zyrtare.</p>
                </div>
            </div>

            <!-- Testamente -->
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="icon-circle"><i class="fas fa-scroll"></i></div>
                    <h5>Hartimi i Testamenteve</h5>
                    <p>Hartimi dhe regjistrimi i testamenteve me këshilla profesionale dhe ruajtje të sigurt.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="icon-circle"><i class="fas fa-archive"></i></div>
                    <h5>Ruajtja e Testamenteve</h5>
                    <p>Ruajtja e sigurt dhe konfidenciale e testamenteve në arkivin noterial me akses të kontrolluar.</p>
                </div>
            </div>

            <!-- Procese-verbale -->
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="icon-circle"><i class="fas fa-exclamation-triangle"></i></div>
                    <h5>Protestimi i Çeqeve</h5>
                    <p>Protestimi i çeqeve dhe kambjove sipas procedurave ligjore dhe afateve të përcaktuara.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="icon-circle"><i class="fas fa-search"></i></div>
                    <h5>Konstatimi i Fakteve</h5>
                    <p>Konstatimi dhe dokumentimi i fakteve të caktuara me proces-verbal noterial zyrtar.</p>
                </div>
            </div>

            <!-- Shërbime të tjera -->
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="icon-circle"><i class="fas fa-balance-scale"></i></div>
                    <h5>Këshillim Juridik</h5>
                    <p>Këshillim juridik profesional lidhur me aktet noteriale dhe procedurat ligjore.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="icon-circle"><i class="fas fa-database"></i></div>
                    <h5>Ruajtja e Regjistrave</h5>
                    <p>Ruajtja dhe administrimi i regjistrave të akteve noteriale me siguri maksimale.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="icon-circle"><i class="fas fa-file-export"></i></div>
                    <h5>Kopje të Vërtetuara</h5>
                    <p>Lëshimi i kopjeve të vërtetuara nga arkivi noterial për çdo dokument zyrtar.</p>
                </div>
            </div>
        </div>
        
        <div class="row mt-5 g-4">
            <div class="col-md-6">
                <div class="bg-white p-4 rounded border d-flex align-items-center">
                    <i class="fas fa-shield-alt text-primary fs-2 me-3"></i>
                    <div>
                        <h6 class="fw-bold mb-1">Siguria e të Dhënave</h6>
                        <p class="small text-muted mb-0">Të dhënat tuaja mbrohen sipas standardeve shtetërore dhe ligjit për mbrojtjen e të dhënave personale.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="bg-white p-4 rounded border d-flex align-items-center">
                    <i class="fas fa-headset text-success fs-2 me-3"></i>
                    <div>
                        <h6 class="fw-bold mb-1">Mbështetje 24/7</h6>
                        <p class="small text-muted mb-0">Qendra e thirrjeve dhe sistemi online janë në dispozicionin tuaj 24 orë në ditë. Kontaktoni në +383 38 200 100.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <span class="footer-logo">e-Noteria</span>
                    <p>Platformë SaaS për noterinë në Kosovë.</p>
                </div>
                <div class="col-md-2 col-6 mb-4">
                    <h6 class="text-white mb-3">Linqe</h6>
                    <a href="rrethnesh.php">Rreth Nesh</a>
                    <a href="Privatesia.php">Privatësia</a>
                    <a href="terms.php">Kushtet</a>
                </div>
                <div class="col-md-2 col-6 mb-4">
                    <h6 class="text-white mb-3">Shërbime</h6>
                    <a href="reservation.php">Rezervime</a>
                    <a href="verifikimi.php">Verifikimi</a>
                    <a href="status.php">Statusi</a>
                </div>
                <div class="col-md-4 mb-4">
                    <h6 class="text-white mb-3">Kontakti</h6>
                    <p><i class="fas fa-phone me-2"></i> 038 200 100</p>
                    <p><i class="fas fa-envelope me-2"></i> info@rks-gov.net</p>
                </div>
            </div>
            <div class="border-top border-secondary pt-3 text-center mt-3">
                <small>&copy; <?php echo date('Y'); ?> Republika e Kosovës - Ministria e Drejtësisë</small>
                <div style="margin-top: 10px;">
                    <a href="terms.php" style="color: #8b949e; text-decoration: none; margin: 0 15px; display: inline-block; transition: color 0.3s;">Kushtet e Përdorimit</a>
                    <a href="Privacy_policy.php" style="color: #8b949e; text-decoration: none; margin: 0 15px; display: inline-block; transition: color 0.3s;">Politika e Privatësisë</a>
                    <a href="ndihma.php" style="color: #8b949e; text-decoration: none; margin: 0 15px; display: inline-block; transition: color 0.3s;">Ndihma</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="/assets/bootstrap/bootstrap.bundle.min.js"></script>

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

