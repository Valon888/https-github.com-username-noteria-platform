<?php
/**
 * E-Noteria SaaS Landing Page
 * Showcases the platform and allows tenant registration
 */

require_once 'confidb.php';
require_once 'tenant_manager.php';

// Initialize tenant system
init_tenant_system($pdo);

// Get available plans for display (some installations might not have the table yet)
$plans = [];
try {
    $stmt = $pdo->query("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price_monthly");
    $plans = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('subscription_plans table missing: ' . $e->getMessage());
}

// Get some statistics
$tenant_count = 141; // Zyra noteriale aktive
$user_count = 1000000; // Mbi 1 milion përdorues
$reservation_count = 500000; // Qindra mijëra rezervime në ditë
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Noteria SaaS - Platforma për Noterinë | Republika e Kosovës</title>
    <meta name="description" content="E-Noteria SaaS - Platforma më moderne për notarinë në Kosovë. Menaxhoni rezervime, dokumente dhe klientë në një platformë të sigurt dhe efikase.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <style>
        :root {
            --primary: #0033A0;
            --secondary: #FFD700;
            --accent: #10B981;
            --dark: #1f2937;
            --light: #f8fafc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--dark);
        }

        /* Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary) !important;
        }

        .navbar-brand i {
            color: var(--secondary);
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, var(--primary) 0%, #002266 100%);
            color: white;
            padding: 8rem 0 6rem;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.05"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.05"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.03"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            text-shadow: 0 4px 8px rgba(0,0,0,0.3);
        }

        .hero p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .hero-stats {
            display: flex;
            gap: 2rem;
            margin-top: 3rem;
            justify-content: center;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--secondary);
            display: block;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        /* Features Section */
        .features {
            padding: 6rem 0;
            background: var(--light);
        }

        .section-title {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .feature-card {
            background: white;
            border-radius: 15px;
            padding: 2.5rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
        }

        .feature-card h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        /* Pricing Section */
        .pricing {
            padding: 6rem 0;
            background: white;
        }

        .pricing-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 2px solid transparent;
            transition: all 0.3s ease;
            position: relative;
        }

        .pricing-card.popular {
            border-color: var(--secondary);
            transform: scale(1.05);
        }

        .pricing-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .pricing-header {
            margin-bottom: 2rem;
        }

        .pricing-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .pricing-price {
            font-size: 3rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .pricing-price .currency {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .pricing-price .period {
            font-size: 1rem;
            font-weight: 400;
            color: #6b7280;
        }

        .pricing-features {
            list-style: none;
            padding: 0;
            margin-bottom: 2rem;
        }

        .pricing-features li {
            padding: 0.5rem 0;
            color: #4b5563;
        }

        .pricing-features li i {
            color: var(--accent);
            margin-right: 0.5rem;
        }

        .btn-pricing {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            color: white;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-pricing:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 51, 160, 0.3);
        }

        /* CTA Section */
        .cta {
            background: linear-gradient(135deg, var(--primary) 0%, #002266 100%);
            color: white;
            padding: 6rem 0;
            text-align: center;
        }

        .cta h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .cta p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .btn-cta {
            background: var(--secondary);
            color: var(--primary);
            padding: 1.2rem 3rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.3);
        }

        .btn-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 215, 0, 0.4);
        }

        /* Footer */
        .footer {
            background: var(--dark);
            color: white;
            padding: 3rem 0 1rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-section h5 {
            color: var(--secondary);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .footer-links {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 0.5rem;
        }

        .footer-links a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--secondary);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 2rem;
            text-align: center;
            color: rgba(255,255,255,0.6);
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }

            .hero-stats {
                flex-direction: column;
                gap: 1rem;
            }

            .pricing-price {
                font-size: 2.5rem;
            }

            .cta h2 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-balance-scale me-2"></i>
                E-Noteria SaaS
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Veçoritë</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#pricing">Çmimet</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Hyni</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-pricing ms-3" href="register.php">Regjistrohu Falas</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center hero-content">
                    <h1 data-aos="fade-up">E-Noteria SaaS</h1>
                    <p data-aos="fade-up" data-aos-delay="200">
                        Platforma më moderne SaaS për notarinë në Kosovë. Sistemi elektronik për menaxhimin e shërbimeve noteriale.
                    </p>
                    <div data-aos="fade-up" data-aos-delay="400">
                        <a href="register.php" class="btn-cta me-3">
                            <i class="fas fa-rocket me-2"></i>Fillo Falas
                        </a>
                        <a href="#features" class="btn btn-outline-light">
                            <i class="fas fa-info-circle me-2"></i>Mëso Më Shumë
                        </a>
                    </div>

                    <div class="hero-stats" data-aos="fade-up" data-aos-delay="600">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo number_format($tenant_count); ?>+</span>
                            <span class="stat-label">Zyra Noteriale</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo number_format($user_count); ?>+</span>
                            <span class="stat-label">Përdorues Aktivë</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo number_format($reservation_count); ?>+</span>
                            <span class="stat-label">Rezervime në Ditë</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h2>Veçoritë e Platformës SaaS</h2>
                <p>Të gjitha mjetet që ju nevojiten për një notari moderne dhe efikase</p>
            </div>

            <div class="row g-4">
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3>Menaxhimi i Rezervimeve</h3>
                        <p>Sistemi i avancuar për prenotimin dhe menaxhimin e termineve noteriale me njoftime automatike dhe konfirmime.</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-file-contract"></i>
                        </div>
                        <h3>Dokumentet Elektronike</h3>
                        <p>Ngarkoni, ruani dhe menaxhoni dokumentet noteriale në format elektronik. Integrim me nënshkrimin elektronik.</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Menaxhimi i Përdoruesve</h3>
                        <p>Sistemi i plotë për menaxhimin e përdoruesve me role të ndryshme dhe leje të personalizuara për çdo organizatë.</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <h3>Paguar Online</h3>
                        <p>Sistemi i sigurt i pagesave online me integrimin e bankave kosovare dhe ndërkombëtare.</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="500">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h3>Raportet dhe Statistikat</h3>
                        <p>Paneli i detajuar i raporteve me statistika të plota për performancën dhe efikasitetin e shërbimeve.</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="600">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3>Siguria dhe Privatësia</h3>
                        <p>Enkriptimi i të dhënave, certifikimi SSL, dhe përputhshmëria me GDPR dhe ligjet kosovare për mbrojtjen e të dhënave.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="pricing">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h2>Plane Abonimi</h2>
                <p>Zgjidhni planin që përshtatet më së miri për zyrën tuaj noteriale</p>
            </div>

            <div class="row g-4 justify-content-center">
                <!-- Abonim Mujor -->
                <div class="col-lg-5 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="pricing-card">
                        <div class="pricing-header">
                            <div class="pricing-name">Abonim Mujor</div>
                            <div class="pricing-price">
                                <span class="currency">€</span>150.00
                                <span class="period">/muaj</span>
                            </div>
                            <p class="text-muted">Abonim mujor me qasje të plotë në platformën E-Noteria SaaS</p>
                        </div>

                        <ul class="pricing-features">
                            <li><i class="fas fa-check"></i>Qasje e plotë në platformë</li>
                            <li><i class="fas fa-check"></i>Dokumente të pakufizuara</li>
                            <li><i class="fas fa-check"></i>Mbështetje prioritare 24/7</li>
                            <li><i class="fas fa-check"></i>Të gjitha shërbimet e platformës</li>
                            <li><i class="fas fa-check"></i>Mjete të avancuara për noterë</li>
                        </ul>

                        <a href="zyrat_register.php?plan=1" class="btn-pricing">
                            Regjistro Zyrën Tënde
                        </a>
                    </div>
                </div>

                <!-- Abonim Vjetor -->
                <div class="col-lg-5 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="pricing-card popular">
                        <div class="badge bg-warning text-dark position-absolute" style="top: -10px; right: 20px;">Më Popullor</div>

                        <div class="pricing-header">
                            <div class="pricing-name">Abonim Vjetor</div>
                            <div class="pricing-price">
                                <span class="currency">€</span>1600.00
                                <span class="period">/vitë</span>
                            </div>
                            <p class="text-muted">Abonim vjetor me qasje të plotë në platformën E-Noteria SaaS - Kurseni 200€</p>
                        </div>

                        <ul class="pricing-features">
                            <li><i class="fas fa-check"></i>Qasje e plotë në platformë</li>
                            <li><i class="fas fa-check"></i>Dokumente të pakufizuara</li>
                            <li><i class="fas fa-check"></i>Mbështetje prioritare 24/7</li>
                            <li><i class="fas fa-check"></i>Të gjitha shërbimet e platformës</li>
                            <li><i class="fas fa-check"></i>Mjete të avancuara për noterë</li>
                            <li><i class="fas fa-check"></i>Kurseni 200€ me pagesë vjetore</li>
                            <li><i class="fas fa-check"></i>Trajnime personale</li>
                            <li><i class="fas fa-check"></i>Këshillime ligjore mujore</li>
                        </ul>

                        <a href="zyrat_register.php?plan=2" class="btn-pricing">
                            Regjistro Zyrën Tënde
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center" data-aos="fade-up">
                    <h2>Gati për të Filluar?</h2>
                    <p>Bashkohuni me qindra zyra noteriale që besojnë në platformën tonë SaaS. Regjistrohuni sot dhe filloni transformimin dixhital të shërbimeve tuaja noteriale.</p>
                    <a href="zyrat_register.php" class="btn-cta">
                        <i class="fas fa-rocket me-2"></i>Regjistro Zyrën Tënde Noteriale
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h5><i class="fas fa-balance-scale me-2"></i>E-Noteria SaaS</h5>
                    <p>Platforma zyrtare SaaS për notarinë në Republikën e Kosovës. Zhvilluar për të modernizuar dhe dixhitalizuar shërbimet noteriale.</p>
                    <div class="mt-3">
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>

                <div class="footer-section">
                    <h5>Produkti</h5>
                    <ul class="footer-links">
                        <li><a href="#features">Veçoritë</a></li>
                        <li><a href="#pricing">Çmimet</a></li>
                        <li><a href="login.php">Hyni</a></li>
                        <li><a href="zyrat_register.php">Regjistro Zyrën</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h5>Suporti</h5>
                    <ul class="footer-links">
                        <li><a href="#">Qendra e Ndihmës</a></li>
                        <li><a href="#">Dokumentacioni</a></li>
                        <li><a href="#">API Reference</a></li>
                        <li><a href="#">Kontakti</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h5>Ligjore</h5>
                    <ul class="footer-links">
                        <li><a href="privatesia.php">Politika e Privatësisë</a></li>
                        <li><a href="terms.php">Kushtet e Përdorimit</a></li>
                        <li><a href="#">Siguria</a></li>
                        <li><a href="#">Compliance</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Republika e Kosovës - Ministria e Drejtësisë. Të gjitha të drejtat e rezervuara.</p>
                <p>Zhvilluar nga <strong>Valon Sadiku</strong> | Powered by e-Noteria SaaS Platform</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            once: true
        });

        // Smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.background = 'rgba(255, 255, 255, 0.98)';
                navbar.style.boxShadow = '0 2px 20px rgba(0,0,0,0.1)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.95)';
                navbar.style.boxShadow = '0 2px 20px rgba(0,0,0,0.1)';
            }
        });
    </script>
</body>
</html>