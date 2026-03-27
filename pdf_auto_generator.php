<?php
// pdf_auto_generator.php
require_once __DIR__ . '/vendor/autoload.php'; // FPDF

function autoGenerateInvoicePDF($reservationId, $pdo) {
    // Merr të dhënat e rezervimit
    $stmt = $pdo->prepare("SELECT r.*, u.emri, u.mbiemri FROM reservations r JOIN users u ON r.user_id = u.id WHERE r.id = ? LIMIT 1");
    $stmt->execute([$reservationId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$data) return false;

    $pdf = new \FPDF();
    $pdf->AddPage();
    // Logo (vendos path të logos nëse ekziston)
    $logoPath = __DIR__ . '/logo.png';
    if (file_exists($logoPath)) {
        $pdf->Image($logoPath, 10, 10, 30);
    }
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->Cell(0, 20, 'FATURË REZERVIMI', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, 'Data: ' . date('d.m.Y H:i'), 0, 1, 'R');
    $pdf->Ln(5);
    // Tabela me detajet
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(60, 10, 'Emri:', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(130, 10, ($data['emri'] ?? '') . ' ' . ($data['mbiemri'] ?? ''), 1, 1);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(60, 10, 'Shërbimi:', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(130, 10, ($data['service'] ?? ''), 1, 1);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(60, 10, 'Data:', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(130, 10, ($data['date'] ?? ''), 1, 1);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(60, 10, 'Ora:', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(130, 10, ($data['time'] ?? ''), 1, 1);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(60, 10, 'Shuma:', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(130, 10, ($data['amount'] ?? '25.50') . ' EUR', 1, 1);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(60, 10, 'Rezervimi ID:', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(130, 10, ($reservationId ?? ''), 1, 1);
    $pdf->Ln(15);
    $pdf->SetFont('Arial', 'I', 11);
    $pdf->Cell(0, 10, 'Fatura është gjeneruar automatikisht nga platforma Noteria.', 0, 1, 'C');
    $pdf->Ln(10);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, 'Nënshkrimi i platformës:', 0, 1);
    $pdf->Cell(0, 10, '_________________________', 0, 1);
    $file = __DIR__ . '/fatura/fatura_' . ($reservationId ?? '0') . '.pdf';
    if (!is_dir(__DIR__ . '/fatura')) {
        mkdir(__DIR__ . '/fatura', 0777, true);
    }
    $pdf->Output('F', $file);
    return 'fatura/' . basename($file);
}
