<?php
// Funksion për të gjeneruar një kopje të vërtetuar PDF nga arkivi noterial

require_once __DIR__ . '/vendor/autoload.php'; // Kërkon mPDF
use Mpdf\Mpdf;

/**
 * Gjeneron një kopje të vërtetuar PDF për një dokument zyrtar
 * @param string $documentTitle Titulli i dokumentit
 * @param string $documentContent Përmbajtja e dokumentit (HTML ose tekst)
 * @param string $issuedBy Emri i noterit ose institucionit
 * @param string $archiveNumber Numri i arkivit
 * @param string $issuedTo Emri i personit që kërkon kopjen
 * @return string|false Kthen path-in e PDF-së ose false nëse dështon
 */
function gjeneroKopjeVertetuarPDF($documentTitle, $documentContent, $issuedBy, $archiveNumber, $issuedTo) {
    try {
        $mpdf = new Mpdf();
        $date = date('d.m.Y H:i');
        $html = '<div style="border:2px solid #003366; border-radius:16px; padding:32px; background:#f8fafc; font-family:Segoe UI,Arial,sans-serif; max-width:700px; margin:0 auto;">';
        // Header me logo dhe titull
        $html .= '<div style="display:flex; align-items:center; gap:18px; margin-bottom:24px;">';
        $html .= '<img src="https://upload.wikimedia.org/wikipedia/commons/1/1a/Coat_of_arms_of_Kosovo.svg" alt="Logo" style="height:60px;">';
        $html .= '<div><span style="font-size:18px; color:#003366; font-weight:700; letter-spacing:1px;">REPUBLIKA E KOSOVËS</span><br>';
        $html .= '<span style="font-size:15px; color:#555;">Ministria e Drejtësisë - Zyra Noteriale</span></div>';
        $html .= '</div>';
        // Titulli
        $html .= '<h2 style="color:#003366; text-align:center; margin-bottom:18px; letter-spacing:1px;">KOPJE E VËRTETUAR NOTERIALE</h2>';
        // Info dokumenti
        $html .= '<table style="width:100%; margin-bottom:18px; font-size:15px;">';
        $html .= '<tr><td style="font-weight:600; color:#003366; width:180px;">Titulli i Dokumentit:</td><td>' . htmlspecialchars($documentTitle) . '</td></tr>';
        $html .= '<tr><td style="font-weight:600; color:#003366;">Numri i Arkivit:</td><td>' . htmlspecialchars($archiveNumber) . '</td></tr>';
        $html .= '<tr><td style="font-weight:600; color:#003366;">Lëshuar për:</td><td>' . htmlspecialchars($issuedTo) . '</td></tr>';
        $html .= '<tr><td style="font-weight:600; color:#003366;">Lëshuar nga:</td><td>' . htmlspecialchars($issuedBy) . '</td></tr>';
        $html .= '<tr><td style="font-weight:600; color:#003366;">Data e lëshimit:</td><td>' . $date . '</td></tr>';
        $html .= '</table>';
        // Linjë ndarëse
        $html .= '<hr style="border:0; border-top:2px solid #cfa856; margin:24px 0;">';
        // Përmbajtja e dokumentit
        $html .= '<div style="background:#fffbe7; border:1px solid #ffe08a; border-radius:8px; padding:18px 20px; margin-bottom:32px; font-size:15px; color:#222;">' . $documentContent . '</div>';
        // Seksioni për vulë dhe nënshkrim
        $html .= '<div style="display:flex; justify-content:space-between; align-items:center; margin-top:40px;">';
        $html .= '<div style="text-align:center;">';
        $html .= '<div style="font-size:13px; color:#888; margin-bottom:8px;">Vula zyrtare</div>';
        $html .= '<div style="width:90px; height:90px; border:2px dashed #cfa856; border-radius:50%; display:inline-block;"></div>';
        $html .= '</div>';
        $html .= '<div style="text-align:center;">';
        $html .= '<div style="font-size:13px; color:#888; margin-bottom:8px;">Nënshkrimi i noterit</div>';
        $html .= '<div style="width:180px; height:40px; border-bottom:2px solid #003366; display:inline-block;"></div>';
        $html .= '</div>';
        $html .= '</div>';
        // Footer
        $html .= '<div style="margin-top:48px; font-size:12px; color:#888; text-align:center;">Ky dokument është kopje e vërtetuar nga arkivi noterial. Çdo përdorim i paautorizuar është i ndaluar. Kontrolloni vlefshmërinë në institucionin përkatës.</div>';
        $html .= '</div>';
        
        $fileName = 'kopje_vertetuar_' . uniqid() . '.pdf';
        $filePath = __DIR__ . '/pdf_output/' . $fileName;
        if (!is_dir(__DIR__ . '/pdf_output')) {
            mkdir(__DIR__ . '/pdf_output', 0777, true);
        }
        $mpdf->WriteHTML($html);
        $mpdf->Output($filePath, \Mpdf\Output\Destination::FILE);
        return $filePath;
    } catch (Exception $e) {
        error_log('Gabim gjatë gjenerimit të PDF: ' . $e->getMessage());
        return false;
    }
}
