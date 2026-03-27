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
    <title>Rreth Nesh | e-Noteria | Republika e Kosovës</title>
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
            transition: all 0.3s ease;
        }
        .brand img {
            height: 45px;
            width: 45px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
            transition: all 0.3s ease;
        }
        .brand:hover img {
            filter: drop-shadow(0 4px 8px rgba(207, 168, 86, 0.4));
            transform: scale(1.05);
        }
        .brand span { color: #000; }

        /* About Section */
        .about-hero {
            background: linear-gradient(135deg, var(--rks-blue), #002244);
            color: white;
            padding: 80px 0;
            text-align: center;
        }
        .about-hero h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .about-hero p {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 800px;
            margin: 0 auto;
        }

        .about-section {
            padding: 80px 0;
        }
        .about-card {
            background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%);
            padding: 40px;
            border-radius: 20px;
            box-shadow: var(--shadow-md);
            margin-bottom: 30px;
            border: 2px solid rgba(0,51,102,0.06);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        .about-card::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle, rgba(207,168,86,0.1) 0%, transparent 70%);
            border-radius: 50%;
            transform: translate(50%, -50%);
            transition: var(--transition);
        }
        .about-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-lg);
            border-color: var(--rks-blue);
        }
        .about-card:hover::after {
            transform: translate(30%, -30%) scale(1.5);
        }
        .icon-box {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, var(--rks-blue) 0%, var(--rks-gold) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.25rem;
            margin: 0 auto 24px;
            box-shadow: 0 12px 28px rgba(0,51,102,0.25), inset 0 -4px 8px rgba(0,0,0,0.1);
            transition: var(--transition);
            position: relative;
        }
        .icon-box::before {
            content: '';
            position: absolute;
            top: 15%;
            left: 15%;
            width: 35%;
            height: 35%;
            background: rgba(255,255,255,0.25);
            border-radius: 50%;
            filter: blur(10px);
        }
        .about-card:hover .icon-box {
            transform: rotateY(360deg) scale(1.1);
            box-shadow: 0 16px 36px rgba(0,51,102,0.35), inset 0 -4px 8px rgba(0,0,0,0.1);
        }
        .about-card h3 {
            color: var(--rks-blue);
            font-weight: 600;
            margin-bottom: 15px;
        }

        .developer-section {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 80px 0;
        }
        .developer-card {
            background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%);
            padding: 60px;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            text-align: center;
            max-width: 600px;
            margin: 0 auto;
            border: 2px solid rgba(0,51,102,0.06);
            transition: var(--transition);
        }
        .developer-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 60px rgba(0,51,102,0.2);
        }
        .developer-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--rks-blue), var(--rks-gold));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            margin: 0 auto 30px;
            box-shadow: 0 10px 25px rgba(0,51,102,0.3);
        }
        .developer-card h3 {
            color: var(--rks-blue);
            font-weight: 700;
            margin-bottom: 10px;
        }
        .developer-role {
            color: var(--rks-gold);
            font-weight: 600;
            margin-bottom: 20px;
        }

        .stats-section {
            padding: 60px 0;
            background: white;
        }
        .stat-card {
            text-align: center;
            padding: 40px 20px;
            transition: var(--transition);
            border-radius: 16px;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(0,51,102,0.02);
        }
        .stat-number {
            font-size: 3.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--rks-blue), var(--rks-gold));
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 12px;
            letter-spacing: -1px;
        }
        .stat-label {
            color: #666;
            font-weight: 500;
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
                <img src="images/pngwing.com (1).png" alt="e-Noteria Logo">
                e<span class="text-primary">-Noteria</span>
            </a>

            <div class="d-flex align-items-center gap-4 d-none d-md-flex">
                <a href="services.php" class="text-dark text-decoration-none fw-medium">Shërbimet</a>
                <a href="#" class="text-dark text-decoration-none fw-medium">Lajme</a>
                <a href="#" class="text-dark text-decoration-none fw-medium">Ndihma</a>
                <?php if ($is_logged_in): ?>
                    <a href="dashboard.php" class="btn btn-primary px-4 rounded-pill fw-bold">Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-primary px-4 rounded-pill fw-bold">Kyçuni</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="about-hero">
        <div class="container">
            <h1>Rreth Nesh</h1>
            <p>Njihuni me platformën e-Noteria dhe ekipin që qëndron pas saj</p>
        </div>
    </section>

    <!-- About Platform -->
    <section class="about-section">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="about-card">
                        <div class="icon-box">
                            <i class="fas fa-cloud"></i>
                        </div>
                        <h3>Platformë SaaS</h3>
                        <p>e-Noteria është platformë cloud-based SaaS për zyrat noteriale në Kosovë. Çdo zyrë noteriale paguan 30€ në muaj për akses të plotë në sistem.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="about-card">
                        <div class="icon-box">
                            <i class="fas fa-euro-sign"></i>
                        </div>
                        <h3>Modeli i Abonimit</h3>
                        <p>Abonim mujor 30€ për zyrë noteriale. Përfshin të gjitha shërbimet, mbështetjen teknike dhe përditësimet e vazhdueshme të platformës.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="about-card">
                        <div class="icon-box">
                            <i class="fas fa-users-cog"></i>
                        </div>
                        <h3>Për Zyrat Noteriale</h3>
                        <p>Platformë e dizajnuar posaçërisht për nevojat e zyrave noteriale. Automatizon proceset, rrit efikasitetin dhe siguron pajtueshmëri me ligjin.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="about-card">
                        <div class="icon-box">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3>Siguria dhe Konfidencialiteti</h3>
                        <p>Të dhënat e klientëve dhe dokumentet noteriale ruhen me enkriptim të nivelit më të lartë dhe në përputhje me GDPR dhe ligjet e Kosovës.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="about-card">
                        <div class="icon-box">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3>Rritja e Produktivitetit</h3>
                        <p>Automatizimi i proceseve noteriale rrit produktivitetin me 300%, zvogëlon gabimet dhe përmirëson shërbimin ndaj klientëve.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="about-card">
                        <div class="icon-box">
                            <i class="fas fa-globe"></i>
                        </div>
                        <h3>Qasje Kudo dhe Kurdo</h3>
                        <p>Zyrat noteriale mund të aksesojnë platformën nga çdo pajisje dhe lokacion, duke mundësuar punë fleksibile dhe shërbim 24/7.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Developer Section -->
    <section class="developer-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold" style="color: var(--rks-blue);">Zhvilluesi i Platformës</h2>
                <p class="text-muted fs-5">Njihuni me personin që ka bërë të mundur këtë revolucion digjital</p>
            </div>

            <div class="developer-card">
                <div class="developer-avatar">
                    <i class="fas fa-user-tie"></i>
                </div>
                <h3>Valon Sadiku</h3>
                <p class="developer-role">Full-Stack Developer & SaaS Entrepreneur</p>
                <p class="mb-4">Valon Sadiku është themeluesi dhe zhvilluesi kryesor i platformës SaaS e-Noteria. Me vizionin për të revolucionarizuar industrinë noteriale në Kosovë, ai ka krijuar një platformë që shërben të gjitha zyrat noteriale me një model abonimi mujor prej 30€. Ekspertiza e tij teknike dhe njohuritë e biznesit kanë bërë të mundur këtë inovacion digjital.</p>

                <div class="row text-center g-4">
                    <div class="col-md-4">
                        <div class="stat-number">30€</div>
                        <div class="stat-label">Abonim Mujor</div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-number">300+</div>
                        <div class="stat-label">Zyra Noteriale</div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-number">22.5M€</div>
                        <div class="stat-label">Të Ardhura Vjetore</div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="fw-bold text-start mb-2"><i class="fas fa-code me-2"></i>Technologies</h6>
                        <p class="text-muted small mb-0">PHP, JavaScript, HTML5, CSS3, MySQL, Bootstrap, React, Node.js</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold text-start mb-2"><i class="fas fa-graduation-cap me-2"></i>Business Model</h6>
                        <p class="text-muted small mb-0">SaaS Platform • 30€/month • B2B Model • Scalable Revenue</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row text-center g-4">
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-number">1M+</div>
                        <div class="stat-label">Përdorues në Ditë</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-number">50,000+</div>
                        <div class="stat-label">Dokumente të Procesuar</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-number">99.9%</div>
                        <div class="stat-label">Uptime i Platformës</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">Mbështetje Disponibile</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Capacity Section -->
    <section class="developer-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold" style="color: var(--rks-blue);">Kapaciteti dhe Performanca</h2>
                <p class="text-muted fs-5">Platforma jonë është dizajnuar për të trajtuar trafik të lartë dhe përdorues masiv</p>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="developer-card">
                        <div class="text-center mb-4">
                            <div class="icon-circle mx-auto mb-3" style="background: linear-gradient(135deg, var(--rks-blue), var(--rks-gold));">
                                <i class="fas fa-server"></i>
                            </div>
                            <h4 class="fw-bold" style="color: var(--rks-blue);">Infrastrukturë e Shkallëzueshme</h4>
                        </div>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i><strong>1 Milion+</strong> përdorues aktivë në ditë</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i><strong>10,000+</strong> lidhje simultane</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i><strong>Load Balancing</strong> automatik</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i><strong>CDN Global</strong> për shpërndarje të përmbajtjes</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i><strong>Redis Cluster</strong> për cache dhe sesione</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i><strong>Database Sharding</strong> për performancë maksimale</li>
                        </ul>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="developer-card">
                        <div class="text-center mb-4">
                            <div class="icon-circle mx-auto mb-3" style="background: linear-gradient(135deg, var(--rks-gold), var(--rks-blue));">
                                <i class="fas fa-rocket"></i>
                            </div>
                            <h4 class="fw-bold" style="color: var(--rks-blue);">Performanca dhe Optimizime</h4>
                        </div>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-rocket text-warning me-2"></i><strong>70-80%</strong> ngarkim më i shpejtë i faqeve</li>
                            <li class="mb-2"><i class="fas fa-rocket text-warning me-2"></i><strong>60%</strong> ulje në përdorimin e CPU-së</li>
                            <li class="mb-2"><i class="fas fa-rocket text-warning me-2"></i><strong>Gzip Compression</strong> për bandwidth</li>
                            <li class="mb-2"><i class="fas fa-rocket text-warning me-2"></i><strong>OPcache</strong> për bytecode caching</li>
                            <li class="mb-2"><i class="fas fa-rocket text-warning me-2"></i><strong>File-based Caching</strong> për përmbajtje dinamike</li>
                            <li class="mb-2"><i class="fas fa-rocket text-warning me-2"></i><strong>Monitoring 24/7</strong> dhe alertime</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12">
                    <div class="developer-card">
                        <div class="text-center mb-4">
                            <h4 class="fw-bold" style="color: var(--rks-blue);">Siguria dhe Disponueshmëria</h4>
                        </div>
                        <div class="row text-center g-4">
                            <div class="col-md-3 col-6">
                                <div class="p-3">
                                    <i class="fas fa-shield-alt fs-2 text-primary mb-2"></i>
                                    <h6 class="fw-bold">SSL/TLS Encryption</h6>
                                    <small class="text-muted">Të gjitha komunikimet e enkriptuara</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="p-3">
                                    <i class="fas fa-lock fs-2 text-success mb-2"></i>
                                    <h6 class="fw-bold">Web Application Firewall</h6>
                                    <small class="text-muted">Mbrojtje nga sulmet kibernetike</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="p-3">
                                    <i class="fas fa-database fs-2 text-info mb-2"></i>
                                    <h6 class="fw-bold">Backup Automatik</h6>
                                    <small class="text-muted">Backup ditor dhe replikimi</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="p-3">
                                    <i class="fas fa-sync-alt fs-2 text-warning mb-2"></i>
                                    <h6 class="fw-bold">Disaster Recovery</h6>
                                    <small class="text-muted">Plan për rimëkëmbje të shpejtë</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <span class="footer-logo">e-Noteria</span>
                    <p>Platforma për Shërbime Noteriale Online SaaS.</p>
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
                <small>&copy; <?php echo date('Y'); ?> Platforma e-Noteria</small>
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

