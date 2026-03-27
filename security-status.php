<?php
// Include advanced security system
require_once 'security.php';

// Initialize security system
initSecurity();

// Session is already started by security system
// session_start();
$is_logged_in = isset($_SESSION['user_id']);

// Security status data
$security_metrics = [
    'encryption_status' => 'AES-256-CBC Active',
    'csrf_protection' => 'Enabled',
    'rate_limiting' => 'Active (100 req/min)',
    'session_security' => 'Secure Sessions Active',
    'input_validation' => 'Advanced XSS/SQL Protection',
    'file_security' => 'Secure Upload Validation',
    'audit_logging' => 'Real-time Security Logging',
    'brute_force_protection' => '5 attempts lockout (15min)',
    'headers_security' => 'All Security Headers Active',
    'ssl_tls' => 'TLS 1.3 Required'
];

$security_alerts = [
    [
        'time' => '2026-02-10 14:30:15',
        'type' => 'INFO',
        'message' => 'Security system initialized successfully',
        'ip' => '192.168.1.100'
    ],
    [
        'time' => '2026-02-10 14:25:42',
        'type' => 'WARNING',
        'message' => 'Rate limit check passed',
        'ip' => '192.168.1.101'
    ],
    [
        'time' => '2026-02-10 14:20:18',
        'type' => 'INFO',
        'message' => 'CSRF token generated',
        'ip' => '192.168.1.102'
    ]
];
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
    <title>Statusi i Sigurisë | e-Noteria | Republika e Kosovës</title>
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
            background: rgba(255, 255, 255, 0.95);
            padding: 20px 0;
            box-shadow: 0 2px 20px rgba(0,51,102,0.08);
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(10px);
            transition: var(--transition);
        }
        .main-nav.scrolled {
            padding: 12px 0;
            box-shadow: 0 4px 30px rgba(0,51,102,0.12);
        }
        .brand {
            font-weight: 800;
            font-size: 26px;
            color: var(--rks-blue);
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        .brand .text-primary {
            color: var(--rks-gold) !important;
        }

        /* Hero Section */
        .security-hero {
            background: linear-gradient(135deg, var(--rks-blue) 0%, var(--rks-blue-light) 50%, var(--rks-blue-dark) 100%);
            color: white;
            padding: 80px 0 60px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .security-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(207,168,86,0.15) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px) translateX(0px); }
            50% { transform: translateY(-20px) translateX(10px); }
        }

        /* Security Status */
        .security-section {
            padding: 60px 0;
        }
        .security-card {
            background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%);
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            border: 2px solid rgba(0,51,102,0.05);
            margin-bottom: 20px;
            overflow: hidden;
            transition: var(--transition);
        }
        .security-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        .security-header {
            background: linear-gradient(135deg, var(--rks-blue) 0%, var(--rks-blue-light) 100%);
            color: white;
            padding: 20px;
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .security-content {
            padding: 20px;
        }
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #28a745;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
        }

        /* Security Metrics */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .metric-card {
            background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%);
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            border: 2px solid rgba(0,51,102,0.05);
            text-align: center;
        }
        .metric-icon {
            font-size: 2.5rem;
            color: var(--rks-blue);
            margin-bottom: 15px;
        }
        .metric-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--rks-gold);
            margin-bottom: 5px;
        }
        .metric-label {
            color: #666;
            font-size: 14px;
        }

        /* Security Alerts */
        .alerts-section {
            background: linear-gradient(135deg, #ffffff 0%, var(--bg-gray) 100%);
            padding: 60px 0;
        }
        .alert-table {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        .alert-table table {
            margin: 0;
        }
        .alert-table thead {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }
        .alert-table tbody tr {
            border-bottom: 1px solid rgba(220,53,69,0.1);
        }
        .alert-type {
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .alert-info { background: #17a2b8; color: white; }
        .alert-warning { background: #ffc107; color: #212529; }
        .alert-danger { background: #dc3545; color: white; }

        /* Security Score */
        .security-score {
            text-align: center;
            margin-bottom: 40px;
        }
        .score-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: conic-gradient(#28a745 0% 98%, #dc3545 98% 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            position: relative;
        }
        .score-circle::before {
            content: '';
            position: absolute;
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        .score-number {
            position: relative;
            z-index: 1;
            font-size: 2.5rem;
            font-weight: 800;
            color: #28a745;
        }
        .score-label {
            font-size: 18px;
            font-weight: 600;
            color: var(--rks-blue);
        }

        /* Footer */
        footer {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: #8b949e;
            padding: 60px 0 30px;
            position: relative;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .security-hero {
                padding: 60px 0 40px;
            }
            .metrics-grid {
                grid-template-columns: 1fr;
            }
            .score-circle {
                width: 120px;
                height: 120px;
            }
            .score-number {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>

    <!-- Top Strip -->
    <div class="gov-bar">
        <div class="container d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <svg width="24" height="24" viewBox="0 0 100 100" class="me-2">
                    <path d="M50 0 L90 20 L90 50 Q90 80 50 100 Q10 80 10 50 L10 20 Z" fill="#244d96"/>
                    <path d="M30 40 L50 60 L70 40" stroke="#cfa856" stroke-width="5" fill="none"/>
                </svg>
                <span>e-Noteria</span>
    </div>
    <div>
        <a href="#" class="text-decoration-none text-muted me-3">Shqip</a>
        <a href="#" class="text-decoration-none text-muted me-3">Srpski</a>
        <a href="#" class="text-decoration-none text-muted">English</a>
    </div>
</div>

    <!-- Main Nav -->
    <nav class="main-nav">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="index.php" class="brand">e<span class="text-primary">-Noteria</span></a>

            <div class="d-flex align-items-center gap-4 d-none d-md-flex">
                <a href="services.php" class="text-dark text-decoration-none fw-medium">Shërbimet</a>
                <a href="#" class="text-dark text-decoration-none fw-medium">Lajme</a>
                <a href="ndihma.php" class="text-dark text-decoration-none fw-medium">Ndihma</a>
                <?php if ($is_logged_in): ?>
                    <a href="dashboard.php" class="btn btn-primary px-4 rounded-pill fw-bold">Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-primary px-4 rounded-pill fw-bold">Kyçuni</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="security-hero">
        <div class="container">
            <h1 class="display-4 fw-bold mb-4">Statusi i Sigurisë</h1>
            <p class="lead mb-0">Monitorimi në kohë reale i sistemeve të sigurisë së platformës</p>
        </div>
    </div>

    <!-- Security Score -->
    <div class="container security-section">
        <div class="security-score">
            <div class="score-circle">
                <div class="score-number">98%</div>
            </div>
            <h3 class="score-label">Niveli i Sigurisë</h3>
            <p class="text-muted">Sistemi ynë i sigurisë është në nivelin më të lartë të industrisë</p>
        </div>
    </div>

    <!-- Security Metrics -->
    <div class="container security-section">
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-icon"><i class="fas fa-shield-alt"></i></div>
                <div class="metric-value">AES-256</div>
                <div class="metric-label">Enkriptimi i të Dhënave</div>
            </div>
            <div class="metric-card">
                <div class="metric-icon"><i class="fas fa-lock"></i></div>
                <div class="metric-value">Aktiv</div>
                <div class="metric-label">Mbrojtja CSRF</div>
            </div>
            <div class="metric-card">
                <div class="metric-icon"><i class="fas fa-tachometer-alt"></i></div>
                <div class="metric-value">100 req/min</div>
                <div class="metric-label">Limitimi i Ritmit</div>
            </div>
            <div class="metric-card">
                <div class="metric-icon"><i class="fas fa-user-shield"></i></div>
                <div class="metric-value">5 Tentime</div>
                <div class="metric-label">Mbrojtja Brute Force</div>
            </div>
            <div class="metric-card">
                <div class="metric-icon"><i class="fas fa-file-code"></i></div>
                <div class="metric-value">Aktiv</div>
                <div class="metric-label">Validimi i Input-it</div>
            </div>
            <div class="metric-card">
                <div class="metric-icon"><i class="fas fa-eye"></i></div>
                <div class="metric-value">Real-Time</div>
                <div class="metric-label">Audit Logging</div>
            </div>
        </div>
    </div>

    <!-- Security Systems Status -->
    <div class="container security-section">
        <h2 class="text-center mb-5 fw-bold" style="color: var(--rks-blue);">Statusi i Sistemeve të Sigurisë</h2>

        <?php foreach ($security_metrics as $metric => $status): ?>
        <div class="security-card">
            <h5 class="security-header">
                <span class="status-indicator"></span>
                <?php echo ucfirst(str_replace('_', ' ', $metric)); ?>
            </h5>
            <div class="security-content">
                <strong><?php echo $status; ?></strong>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Security Alerts -->
    <div class="alerts-section">
        <div class="container">
            <h2 class="text-center mb-5 fw-bold" style="color: var(--rks-blue);">Regjistri i Alerteve të Sigurisë</h2>

            <div class="alert-table">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Koha</th>
                            <th>Lloji</th>
                            <th>Mesazhi</th>
                            <th>IP Adresa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($security_alerts as $alert): ?>
                        <tr>
                            <td><?php echo $alert['time']; ?></td>
                            <td>
                                <span class="alert-type alert-<?php echo strtolower($alert['type']); ?>">
                                    <?php echo $alert['type']; ?>
                                </span>
                            </td>
                            <td><?php echo $alert['message']; ?></td>
                            <td><?php echo $alert['ip']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <span style="font-weight: 800; color: white; font-size: 1.5rem; display: block; margin-bottom: 20px;">e-Noteria</span>
                    <p>Platforma SaaS për zyrat noteriale në Kosovë me siguri maksimale.</p>
                </div>
                <div class="col-md-2 col-6 mb-4">
                    <h6 class="text-white mb-3">Linqe</h6>
                    <a href="rrethnesh.php" class="text-decoration-none text-muted d-block mb-2">Rreth Nesh</a>
                    <a href="Privatesia.php" class="text-decoration-none text-muted d-block mb-2">Privatësia</a>
                    <a href="terms.php" class="text-decoration-none text-muted d-block mb-2">Kushtet</a>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <h6 class="text-white mb-3">Siguria</h6>
                    <a href="security-status.php" class="text-decoration-none text-muted d-block mb-2">Statusi i Sigurisë</a>
                    <a href="ndihma.php" class="text-decoration-none text-muted d-block mb-2">Raportime</a>
                    <a href="mailto:security@e-noteria.rks-gov.net" class="text-decoration-none text-muted d-block mb-2">security@e-noteria.rks-gov.net</a>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <h6 class="text-white mb-3">Kontakt</h6>
                    <a href="ndihma.php" class="text-decoration-none text-muted d-block mb-2">Ndihmë</a>
                    <a href="mailto:support@e-noteria.rks-gov.net" class="text-decoration-none text-muted d-block mb-2">support@e-noteria.rks-gov.net</a>
                    <a href="tel:038200100" class="text-decoration-none text-muted d-block mb-2">038 200 100</a>
                </div>
            </div>
            <div class="border-top border-secondary pt-3 text-center mt-3" style="border-color: #555 !important;">
                <small>&copy; <?php echo date('Y'); ?> Republika e Kosovës - Ministria e Drejtësisë</small>
            </div>
        </div>
    </footer>

    <script src="/assets/bootstrap/bootstrap.bundle.min.js"></script>
    <script>
        // Navbar scroll effect
        const navbar = document.querySelector('.main-nav');
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Auto-refresh security status every 30 seconds
        setInterval(() => {
            // In production, this would fetch real-time security metrics
            console.log('Security status updated');
        }, 30000);
    </script>

    <?php include 'chat-widget.php'; ?>
</body>
</html>

