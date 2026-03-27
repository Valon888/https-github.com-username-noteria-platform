<?php
// Konfigurimi i raportimit të gabimeve
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Fillimi i sigurt i sesionit
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

require_once 'confidb.php';

// Merr listën e zyrave noteriale
try {
    $stmt = $pdo->query("SELECT id, emri, qyteti, shteti, adresa, telefoni, email, orari_punimit FROM zyrat ORDER BY qyteti, emri");
    $zyrat = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $zyrat = [];
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zyrat Noteriale - Noteria</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 40px 20px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .header h1 {
            color: #2d3748;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #718096;
            font-size: 1.1rem;
        }
        
        .offices-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .office-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            flex-direction: column;
        }
        
        .office-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        
        .office-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }
        
        .office-header h2 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        
        .office-location {
            font-size: 0.95rem;
            opacity: 0.9;
        }
        
        .office-body {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .office-info {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .office-info-icon {
            width: 24px;
            height: 24px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            margin-top: 2px;
            flex-shrink: 0;
        }
        
        .office-info-content {
            flex: 1;
        }
        
        .office-info-label {
            font-size: 0.75rem;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .office-info-value {
            color: #2d3748;
            font-size: 0.95rem;
            margin-top: 2px;
            word-break: break-word;
        }
        
        .office-info-value a {
            color: #667eea;
            text-decoration: none;
        }
        
        .office-info-value a:hover {
            text-decoration: underline;
        }
        
        .office-footer {
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
            margin-top: 10px;
        }
        
        .btn-reserve {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: transform 0.2s;
            width: 100%;
        }
        
        .btn-reserve:hover {
            transform: scale(1.02);
        }
        
        .no-offices {
            background: white;
            padding: 40px;
            border-radius: 12px;
            text-align: center;
            color: #718096;
            font-size: 1.1rem;
        }
        
        .footer {
            text-align: center;
            color: white;
            padding: 20px;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.8rem;
            }
            
            .offices-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .office-card {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏢 Zyrat Noteriale</h1>
            <p>Shërbim profesional noterialit në të gjithë vendin</p>
        </div>
        
        <?php if (!empty($zyrat)): ?>
            <div class="offices-grid">
                <?php foreach ($zyrat as $zyra): ?>
                    <div class="office-card">
                        <div class="office-header">
                            <h2><?php echo htmlspecialchars($zyra['emri']); ?></h2>
                            <div class="office-location">
                                📍 <?php echo htmlspecialchars($zyra['qyteti'] ?? 'Nuk dihet'); ?>, <?php echo htmlspecialchars($zyra['shteti'] ?? 'Nuk dihet'); ?>
                            </div>
                        </div>
                        
                        <div class="office-body">
                            <?php if (!empty($zyra['adresa'])): ?>
                                <div class="office-info">
                                    <div class="office-info-icon">📌</div>
                                    <div class="office-info-content">
                                        <div class="office-info-label">Adresa</div>
                                        <div class="office-info-value"><?php echo htmlspecialchars($zyra['adresa']); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($zyra['telefoni'])): ?>
                                <div class="office-info">
                                    <div class="office-info-icon">📞</div>
                                    <div class="office-info-content">
                                        <div class="office-info-label">Telefon</div>
                                        <div class="office-info-value"><a href="tel:<?php echo htmlspecialchars($zyra['telefoni']); ?>"><?php echo htmlspecialchars($zyra['telefoni']); ?></a></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($zyra['email'])): ?>
                                <div class="office-info">
                                    <div class="office-info-icon">✉️</div>
                                    <div class="office-info-content">
                                        <div class="office-info-label">Email</div>
                                        <div class="office-info-value"><a href="mailto:<?php echo htmlspecialchars($zyra['email']); ?>"><?php echo htmlspecialchars($zyra['email']); ?></a></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($zyra['orari_punimit'])): ?>
                                <div class="office-info">
                                    <div class="office-info-icon">🕐</div>
                                    <div class="office-info-content">
                                        <div class="office-info-label">Orari i Punimit</div>
                                        <div class="office-info-value"><?php echo nl2br(htmlspecialchars($zyra['orari_punimit'])); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="office-footer">
                                <a href="reservation.php?zyra_id=<?php echo $zyra['id']; ?>" class="btn-reserve">
                                    Rezervo Termin 📅
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-offices">
                <p>Nuk ka zyra noteriale të disponueshme në këtë moment.</p>
            </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>&copy; 2024 Noteria Platform. Të gjitha të drejtat e rezervuara.</p>
        </div>
    </div>
</body>
</html>
