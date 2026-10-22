<?php
// Faqja e Compliance për e-Noteria
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compliance & Përputhshmëria | e-Noteria</title>
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <style>
        body { background: #f2f4f8; font-family: 'Segoe UI', Arial, sans-serif; }
        .compliance-card {
            max-width: 700px;
            margin: 60px auto 0 auto;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,51,102,0.13);
            border: 1.5px solid #e0e6ed;
            background: #fff;
            padding: 38px 36px 30px 36px;
        }
        .header-logo {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px;
        }
        .header-logo img { height: 48px; }
        .header-logo span { font-size: 18px; color: #003366; font-weight: 700; letter-spacing: 1px; }
        .compliance-title {
            color: #003366;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 18px;
            text-align: center;
        }
        .compliance-section h4 {
            color: #003366;
            font-size: 1.2rem;
            margin-top: 24px;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .compliance-section ul {
            margin-left: 18px;
        }
        .compliance-section li {
            margin-bottom: 7px;
        }
        .compliance-footer {
            margin-top: 32px;
            text-align: center;
            color: #888;
            font-size: 0.98rem;
        }
    </style>
</head>
<body>
    <div class="compliance-card">
        <div class="header-logo mb-2">
            <img src="https://upload.wikimedia.org/wikipedia/commons/1/1a/Coat_of_arms_of_Kosovo.svg" alt="Logo">
            <span>e-Noteria</span>
        </div>
        <div class="compliance-title mb-4">
            <i class="fas fa-shield-alt me-2"></i>Compliance & Përputhshmëria
        </div>
        <div class="compliance-section">
            <h4>Standardet dhe Ligjet</h4>
            <ul>
                <li>Përputhshmëri me Ligjin për Noterinë të Republikës së Kosovës</li>
                <li>Respektim i Ligjit për Mbrojtjen e të Dhënave Personale (GDPR & ligji vendor)</li>
                <li>Standardet ISO 27001 për sigurinë e informacionit</li>
                <li>Auditim i rregullt i proceseve dhe sistemeve</li>
            </ul>
            <h4>Privatësia dhe Siguria</h4>
            <ul>
                <li>Të dhënat ruhen në serverë të sigurt dhe të certifikuar</li>
                <li>Enkriptim i komunikimit (HTTPS/TLS 1.3)</li>
                <li>Kontroll i aksesit dhe autentifikim i shumëfishtë</li>
                <li>Monitorim 24/7 dhe reagim ndaj incidenteve</li>
            </ul>
            <h4>Transparenca dhe Auditimi</h4>
            <ul>
                <li>Regjistrim i plotë i të gjitha veprimeve noteriale</li>
                <li>Auditim i jashtëm dhe i brendshëm periodik</li>
                <li>Raportim i rregullt për autoritetet kompetente</li>
            </ul>
            <h4>Politikat e Përditësimit</h4>
            <ul>
                <li>Përditësime të rregullta të softuerit dhe sigurisë</li>
                <li>Trajnime të vazhdueshme për stafin noterial</li>
            </ul>
        </div>
        <div class="compliance-footer">
            Për çdo pyetje rreth përputhshmërisë, kontaktoni <a href="mailto:compliance@e-noteria.rks-gov.net">compliance@e-noteria.rks-gov.net</a>.<br>
            <a href="index.php" class="text-decoration-none text-primary">&larr; Kthehu te faqja kryesore</a>
        </div>
    </div>
</body>
</html>

