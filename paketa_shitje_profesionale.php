<?php
require_once __DIR__ . '/vendor/autoload.php';
use Mpdf\Mpdf;

function gjeneroPaketeProfesionaleShitjePDF($emriKlientit = "", $cmimi = "50,000€", $pa_hostim = true) {
    $mpdf = new Mpdf();
    $data = date('d.m.Y');
    $hostim = $pa_hostim ? 'Çmimi nuk përfshin hostimin, mirëmbajtjen dhe suportin teknik.' : 'Çmimi përfshin hostimin dhe suportin bazë.';
    $html = '<div style="font-family:Segoe UI,Arial,sans-serif; max-width:700px; margin:0 auto; padding:32px; background:#f8fafc; border-radius:18px; border:2px solid #003366;">';
    $html .= '<div style="display:flex;align-items:center;gap:18px;margin-bottom:24px;">';
    $html .= '<img src="logo.png.png" alt="Logo" style="height:60px;">';
    $html .= '<div><span style="font-size:18px;color:#003366;font-weight:700;letter-spacing:1px;">e-Noteria</span><br>';
    $html .= '<span style="font-size:15px;color:#555;">Paketa Profesionale e Shitjes</span></div></div>';
    $html .= '<h2 style="color:#003366;text-align:center;margin-bottom:18px;">Oferta për Platformën Noteriale Digjitale</h2>';
    if ($emriKlientit) {
        $html .= '<div style="margin-bottom:18px;"><b>Për:</b> '.htmlspecialchars($emriKlientit).'</div>';
    }
    $html .= '<table style="width:100%;margin-bottom:18px;font-size:15px;">';
    $html .= '<tr><td style="font-weight:600;color:#003366;width:180px;">Data e Ofertës:</td><td>' . $data . '</td></tr>';
    $html .= '<tr><td style="font-weight:600;color:#003366;">Çmimi:</td><td style="font-size:1.3rem;color:#198754;font-weight:700;">' . $cmimi . '</td></tr>';
    $html .= '<tr><td style="font-weight:600;color:#003366;">Hostimi:</td><td>' . $hostim . '</td></tr>';
    $html .= '</table>';
    $html .= '<hr style="border:0;border-top:2px solid #cfa856;margin:24px 0;">';
    $html .= '<h3 style="color:#003366;margin-bottom:12px;">Çfarë përfshin paketa?</h3>';
    $html .= '<ul style="margin-left:18px;font-size:15px;">';
    $html .= '<li>Licencë të plotë për përdorim të platformës (kodi burimor, dokumentacion, të gjitha funksionalitetet)</li>';
    $html .= '<li>Panel administrativ për menaxhimin e zyrave, përdoruesve, dokumenteve dhe pagesave</li>';
    $html .= '<li>Menaxhim i rezervimeve, dokumenteve elektronike, pagesave online dhe nënshkrimeve digjitale</li>';
    $html .= '<li>Auditim, logim, siguri dhe përputhshmëri me ligjet e Kosovës dhe ndërkombëtare</li>';
    $html .= '<li>Integrime me bankat, email, SMS, Docusign, Paysera, etj.</li>';
    $html .= '<li>Mbështetje për shumë gjuhë dhe personalizim të dizajnit</li>';
    $html .= '<li>Dokumentacion i plotë teknik dhe ligjor</li>';
    $html .= '<li>Trajnim fillestar për përdorimin e platformës</li>';
    $html .= '</ul>';
    $html .= '<h3 style="color:#003366;margin-top:24px;margin-bottom:12px;">Kushtet e Pagesës</h3>';
    $html .= '<ul style="margin-left:18px;font-size:15px;">';
    $html .= '<li>Pagesa 100% paraprakisht para dorëzimit të kodit burimor dhe dokumentacionit</li>';
    $html .= '<li>Çmimi është pa TVSH</li>';
    $html .= '<li>Hostimi, mirëmbajtja dhe suporti teknik tarifohen veçmas sipas marrëveshjes</li>';
    $html .= '</ul>';
    $html .= '<h3 style="color:#003366;margin-top:24px;margin-bottom:12px;">Kontakti</h3>';
    $html .= '<p>Për çdo pyetje ose ofertë të personalizuar, kontaktoni <b>Valon Sadiku</b> në <a href="mailto:valon.sadiku@noteria.com">valon.sadiku@noteria.com</a> ose në WhatsApp: +383 45 213 675</p>';
    $html .= '<div style="margin-top:40px;font-size:12px;color:#888;text-align:center;">Kjo ofertë është e vlefshme për 30 ditë nga data e lëshimit.</div>';
    $html .= '</div>';
    $fileName = 'paketa_shitje_profesionale_' . uniqid() . '.pdf';
    $filePath = __DIR__ . '/pdf_output/' . $fileName;
    if (!is_dir(__DIR__ . '/pdf_output')) {
        mkdir(__DIR__ . '/pdf_output', 0777, true);
    }
    $mpdf->WriteHTML($html);
    $mpdf->Output($filePath, \Mpdf\Output\Destination::FILE);
    return $filePath;
}


// Shembull përdorimi UI:
$pdf = gjeneroPaketeProfesionaleShitjePDF("", "50,000€", true);
?><!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paketa Profesionale e Shitjes | e-Noteria</title>
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
</head>
<body style="background:#f2f4f8;">
    <div class="container" style="max-width:600px;margin:60px auto;">
        <div class="card shadow p-4 mt-5">
            <h2 class="mb-3 text-primary">Paketa Profesionale e Shitjes</h2>
            <?php if ($pdf): ?>
                <div class="alert alert-success">PDF u krijua me sukses!</div>
                <a href="pdf_output/<?=basename($pdf)?>" class="btn btn-success" target="_blank">Shkarko Ofertën (PDF)</a>
            <?php else: ?>
                <div class="alert alert-danger">Gabim gjatë krijimit të PDF.</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

