<?php
require_once __DIR__ . '/gjenero_kopje_vertetuar.php';

$documentTitle = 'Vendim Zyrtar';
$documentContent = '<ul style="margin:0 0 10px 20px; padding:0; color:#003366; font-size:15px;">
    <li>Ky vendim është marrë në përputhje me ligjin nr. 06/L-010.</li>
    <li>Dokumenti është ruajtur në arkivin noterial me siguri të lartë.</li>
    <li>Çdo kopje e lëshuar është e vlefshme vetëm me vulë dhe nënshkrim elektronik.</li>
</ul>';
$issuedBy = 'Noteri: Arben Krasniqi';
$archiveNumber = 'ARK-2026-00123';
$issuedTo = 'Gentiana Berisha';

$pdfPath = gjeneroKopjeVertetuarPDF($documentTitle, $documentContent, $issuedBy, $archiveNumber, $issuedTo);

?><!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kopje e Vërtetuar Noteriale | Shembull</title>
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <style>
        body { background: #f2f4f8; font-family: 'Segoe UI', Arial, sans-serif; }
        .card-custom {
            max-width: 520px;
            margin: 60px auto 0 auto;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,51,102,0.13);
            border: 1.5px solid #e0e6ed;
            background: #fff;
        }
        .header-logo {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px;
        }
        .header-logo img { height: 48px; }
        .header-logo span { font-size: 18px; color: #003366; font-weight: 700; letter-spacing: 1px; }
        .success-icon {
            color: #198754;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .btn-download {
            background: linear-gradient(90deg, #003366 60%, #cfa856 100%);
            color: #fff;
            font-weight: 600;
            border: none;
            border-radius: 30px;
            padding: 12px 36px;
            font-size: 1.1rem;
            box-shadow: 0 2px 8px rgba(0,51,102,0.08);
            transition: background 0.3s;
        }
        .btn-download:hover {
            background: linear-gradient(90deg, #002244 60%, #cfa856 100%);
            color: #fff;
        }
        .info-list li { color: #003366; font-size: 15px; }
    </style>
</head>
<body>
    <div class="card card-custom p-4">
        <div class="header-logo">
            <img src="https://upload.wikimedia.org/wikipedia/commons/1/1a/Coat_of_arms_of_Kosovo.svg" alt="Logo">
            <span>e-Noteria</span>
        </div>
        <div class="text-center">
            <i class="fas fa-file-pdf success-icon"></i>
            <h3 class="fw-bold mb-2" style="color:#003366;">Kopja e Vërtetuar Noteriale</h3>
            <div class="mb-3 text-muted">Gjenerimi i dokumentit u krye me sukses.</div>
        </div>
        <ul class="info-list mb-3">
            <li><b>Dokument:</b> <?= htmlspecialchars($documentTitle) ?></li>
            <li><b>Numri i Arkivit:</b> <?= htmlspecialchars($archiveNumber) ?></li>
            <li><b>Lëshuar për:</b> <?= htmlspecialchars($issuedTo) ?></li>
            <li><b>Lëshuar nga:</b> <?= htmlspecialchars($issuedBy) ?></li>
        </ul>
        <?php if ($pdfPath): ?>
            <div class="d-grid gap-2 mb-2">
                <a href="pdf_output/<?= basename($pdfPath) ?>" target="_blank" class="btn btn-download"><i class="fas fa-download me-2"></i>Shkarko PDF</a>
            </div>
            <div class="text-center small text-success"><i class="fas fa-check-circle me-1"></i>Dokumenti është gati për përdorim zyrtar.</div>
        <?php else: ?>
            <div class="alert alert-danger mt-3">Gabim gjatë gjenerimit të PDF. Ju lutem provoni përsëri.</div>
        <?php endif; ?>
        <div class="text-center mt-4">
            <a href="services.php" class="text-decoration-none text-primary">&larr; Kthehu te shërbimet</a>
        </div>
    </div>
</body>
</html>

