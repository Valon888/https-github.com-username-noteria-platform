<?php
// Include performance monitoring
require_once 'performance.php';

// Include advanced security system
require_once 'security.php';

// Include database connection
require_once 'db_connection.php';

// Initialize security system
initSecurity();

// Session is already started by security system
// session_start();
$is_logged_in = isset($_SESSION['user_id']);

// --- Handle AJAX verification request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    header('Content-Type: application/json');
    
    $code = trim($_POST['verify_code'] ?? '');
    
    if (empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Ju lutemi shkruani kodin e verifikimit.']);
        exit;
    }
    
    try {
        $conn = connectToDatabase();
        
        // Kërkimi i kodit përmes UUID ose verification code
        // Format: md5(payment_id + uuid) merr 8 karakteret e parë
        $stmt = $conn->prepare("
            SELECT p.id, p.uuid, p.status, p.amount, p.created_at, 
                   u.emri, u.mbiemri, u.email, 
                   r.service, r.date as reservation_date
            FROM payments p
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN reservations r ON p.reservation_id = r.id
            WHERE p.uuid IS NOT NULL AND p.id IS NOT NULL
        ");
        $stmt->execute();
        $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $found = null;
        
        // Kontrollo kaltrë UUID direkt
        foreach ($allRows as $row) {
            if (strtolower($row['uuid']) === strtolower($code)) {
                $found = $row;
                break;
            }
        }
        
        // Nëse nuk u gjet, provo përmes Verification Code (8 karakteret e parë të md5)
        if (!$found) {
            foreach ($allRows as $row) {
                $generated_code = strtoupper(substr(md5($row['id'] . $row['uuid']), 0, 8));
                if ($generated_code === strtoupper($code)) {
                    $found = $row;
                    break;
                }
            }
        }
        
        if ($found) {
            $is_paid = ($found['status'] === 'paid');
            $title = $is_paid ? 'Dokumenti i Vlefshëm dhe i Paguar' : 'Dokumenti në Pritje';
            $message = $is_paid 
                ? 'Pagesa është kryer. Dokumenti është lëshuar nga autoritetet kompetente.' 
                : 'Dokumenti nuk është paguar ende. Plotësoni pagesën për të marrë dokumentin përfundimtar.';
            $icon = $is_paid ? 'fas fa-check-circle' : 'fas fa-hourglass-half';
            $status = $is_paid ? 'valid' : 'pending';
            
            $details = sprintf(
                'Fatura #%d | Klienti: %s %s | Shuma: €%.2f | Data: %s | Shërbimi: %s',
                $found['id'],
                htmlspecialchars($found['emri'] ?? ''),
                htmlspecialchars($found['mbiemri'] ?? ''),
                $found['amount'],
                date('d.m.Y', strtotime($found['created_at'])),
                htmlspecialchars($found['service'] ?? 'N/A')
            );
            
            echo json_encode([
                'success' => true,
                'status' => $status,
                'title' => $title,
                'message' => $message,
                'icon' => $icon,
                'details' => $details,
                'timestamp' => date('d.m.Y H:i:s')
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'status' => 'invalid',
                'title' => 'Dokumenti i Pavlefshëm',
                'message' => 'Kodi i verifikimit nuk është i vlefshëm ose dokumenti nuk ekziston në sistem.',
                'icon' => 'fas fa-times-circle'
            ]);
        }
    } catch (Exception $e) {
        error_log('Verification error: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'title' => 'Gabim Sistemi',
            'message' => 'Ndodhi një gabim gjatë verifikimit. Ju lutemi provoni përsëri.',
            'icon' => 'fas fa-exclamation-triangle'
        ]);
    }
    exit;
}
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
    <title>Verifikimi | e-Noteria | Republika e Kosovës</title>
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
        }
        .brand span { color: #000; margin-left: 2px; }

        .icon-circle {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #f0f4f8 0%, #e9ecef 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--rks-blue);
            font-size: 2rem;
            border: 3px solid rgba(0,51,102,0.1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* Verification Section */
        .verification-section {
            padding: 80px 0;
            background: linear-gradient(135deg, var(--bg-gray) 0%, #ffffff 100%);
        }
        .verification-card {
            background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%);
            padding: 60px;
            border-radius: 20px;
            box-shadow: var(--shadow-lg), 0 0 0 1px rgba(0,51,102,0.05);
            max-width: 700px;
            margin: 0 auto;
            border: 2px solid rgba(0,51,102,0.08);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }
        .verification-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--rks-blue), var(--rks-gold));
        }
        .verification-form .form-control {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 16px 22px;
            font-size: 1rem;
            transition: var(--transition);
            background: rgba(255, 255, 255, 0.9);
        }
        .verification-form .form-control:focus {
            border-color: var(--rks-blue);
            box-shadow: 0 0 0 4px rgba(0,51,102,0.08), inset 0 2px 4px rgba(0,0,0,0.05);
            background: white;
            transform: translateY(-2px);
        }
        .verification-form .btn {
            background: linear-gradient(135deg, var(--rks-blue), #002244);
            border: none;
            border-radius: 10px;
            padding: 15px 30px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,51,102,0.3);
        }
        .verification-form .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,51,102,0.4);
        }
        .verification-result {
            margin-top: 30px;
            padding: 28px;
            border-radius: 16px;
            display: none;
            animation: slideInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid;
            position: relative;
            overflow: hidden;
        }
        .verification-result::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 2s infinite;
        }
        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        .verification-valid {
            background: linear-gradient(135deg, #d1f4e0, #c3e6cb);
            border-color: #28a745;
            color: #155724;
            box-shadow: 0 8px 24px rgba(40, 167, 69, 0.2);
        }
        .verification-invalid {
            background: linear-gradient(135deg, #ffe0e6, #f5c6cb);
            border-color: #dc3545;
            color: #721c24;
            box-shadow: 0 8px 24px rgba(220, 53, 69, 0.2);
        }
        .verification-pending {
            background: linear-gradient(135deg, #fff8e1, #ffeaa7);
            border-color: #ffc107;
            color: #856404;
            box-shadow: 0 8px 24px rgba(255, 193, 7, 0.2);
        }
        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
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
        <div class="container d-flex align-items-center justify-content-between">
            <a href="index.php" class="brand">
                <img src="images/pngwing.com (1).png" alt="e-Noteria Logo" style="height:48px;width:48px;object-fit:contain;margin-right:12px;">
                e-Noteria
            </a>

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

    <!-- Verification Section -->
    <div class="container verification-section">
        <div class="text-center mb-5">
            <h1 class="fw-bold mb-3" style="color: var(--rks-blue); font-size: 2.5rem;">Verifikimi i Dokumenteve</h1>
            <p class="text-muted fs-5">Futni kodin e verifikimit për të kontrolluar vlefshmërinë dhe autenticitetin e dokumentit</p>
        </div>

        <div class="verification-card">
            <div class="text-center mb-4">
                <div class="icon-circle mx-auto mb-3">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 class="fw-bold" style="color: var(--rks-blue);">Kontrolli i Vlefshmërisë</h3>
            </div>

            <form class="verification-form" id="verificationForm">
                <div class="mb-4">
                    <label for="verificationCode" class="form-label fw-semibold fs-6">
                        <i class="fas fa-key me-2"></i>Kodi i Verifikimit
                    </label>
                    <input type="text" class="form-control form-control-lg" id="verificationCode"
                           placeholder="Shkruani UUID ose Kodin e Verifikimit (p.sh. 1F0D99F7)" required>
                    <div class="form-text">Përdorni UUID direkttisht (36 karaktere) ose Kodin e Verifikimit (8 karaktere) nga dokumenti.</div>
                </div>
                <div class="text-center">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-check-circle me-2"></i>Verifiko Dokumentin
                    </button>
                </div>
            </form>

            <div class="verification-result" id="verificationResult">
                <!-- Verification result will be displayed here -->
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-3 mb-4">
                    <span class="footer-logo">e-Noteria</span>
                    <p>Platforma zyrtare SaaS për shërbimet noteriale në Kosovë</p>
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
                <div class="col-md-2 col-6 mb-4">
                    <h6 class="text-white mb-3">Ndihma</h6>
                    <a href="ndihma.php">FAQ</a>
                    <a href="mailto:support@e-noteria.rks-gov.net">Kontakti</a>
                    <a href="#">Dokumentacioni</a>
                </div>
                <div class="col-md-3 mb-4">
                    <h6 class="text-white mb-3">Kontakti</h6>
                    <p><i class="fas fa-phone me-2"></i> 038 200 100</p>
                    <p><i class="fas fa-envelope me-2"></i> info@rks-gov.net</p>
                </div>
            </div>
            <div class="border-top border-secondary pt-3 text-center mt-3">
                <small>&copy; <?php echo date('Y'); ?> Qeveria e Republikës së Kosovës</small>
            </div>
        </div>
    </footer>

    <script src="/assets/bootstrap/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('verificationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const verificationCode = document.getElementById('verificationCode').value.trim();
            const resultDiv = document.getElementById('verificationResult');

            if (!verificationCode) {
                showVerificationResult('invalid', 'Gabim', 'Ju lutem shkruani kodin e verifikimit.', 'fas fa-info-circle');
                return;
            }

            // Show loading state
            resultDiv.innerHTML = `
                <div class="d-flex align-items-center justify-content-center">
                    <div class="spinner-border text-primary me-3" role="status"></div>
                    <span>Duke verifikuar...</span>
                </div>
            `;
            resultDiv.style.display = 'block';

            // Send AJAX request to verify code
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'verify_code=' + encodeURIComponent(verificationCode)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success || data.status) {
                    showVerificationResult(
                        data.status || 'invalid',
                        data.title || 'Rezultati i Verifikimit',
                        data.message || 'Nuk ka informacione të disponueshme',
                        data.icon || 'fas fa-info-circle',
                        data.details || null,
                        data.timestamp || null
                    );
                } else {
                    showVerificationResult(
                        data.status || 'invalid',
                        data.title || 'Dokumenti i Pavlefshëm',
                        data.message || 'Kodi i verifikimit nuk është i vlefshëm ose dokumenti nuk ekziston në sistem.',
                        data.icon || 'fas fa-times-circle'
                    );
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showVerificationResult('invalid', 'Gabim Lidhje', 'Nuk mund të lidhet me serverin. Provoni përsëri.', 'fas fa-exclamation-triangle');
            });
        });

        function showVerificationResult(status, title, message, icon = 'fas fa-info-circle', details = null, timestamp = null) {
            const resultDiv = document.getElementById('verificationResult');
            resultDiv.className = 'verification-result verification-' + status;
            
            let detailsHTML = '';
            if (details) {
                detailsHTML = `<hr><small class="text-muted d-block"><strong>Detalet:</strong> ${details}</small>`;
            }
            
            let timestampHTML = '';
            if (timestamp && status === 'valid') {
                timestampHTML = `<small class="text-muted mt-2 d-block">Verifikuar më: ${timestamp}</small>`;
            }
            
            resultDiv.innerHTML = `
                <div class="d-flex align-items-center mb-2">
                    <i class="${icon} fs-4 me-3"></i>
                    <h6 class="mb-0 fw-bold">${title}</h6>
                </div>
                <p class="mb-0">${message}</p>
                ${detailsHTML}
                ${timestampHTML}
            `;
            resultDiv.style.display = 'block';
            resultDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    </script>

    <?php include 'chat-widget.php'; ?>
</body>
</html>

