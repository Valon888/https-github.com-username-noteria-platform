<?php
// pdf_generator.php
require_once __DIR__ . '/vendor/autoload.php'; // FPDF or TCPDF

function generateReservationPDF($data) {
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
    $pdf->Cell(0, 10, 'Data: ' . ($data['data'] ?? ''), 0, 1, 'R');
    $pdf->Ln(5);
    // Tabela me detajet
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(60, 10, 'Emri:', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(130, 10, ($data['emri'] ?? '') . ' ' . ($data['mbiemri'] ?? ''), 1, 1);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(60, 10, 'Banka:', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(130, 10, ($data['bank_name'] ?? ''), 1, 1);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(60, 10, 'ID Transaksionit:', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(130, 10, ($data['transaction_id'] ?? ''), 1, 1);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(60, 10, 'Shuma totale:', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(130, 10, ($data['amount'] ?? '') . ' EUR', 1, 1);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(60, 10, 'Shuma pa TVSH:', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(130, 10, ($data['pa_tvsh'] ?? '') . ' EUR', 1, 1);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(60, 10, 'TVSH (12%):', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(130, 10, ($data['tvsh'] ?? '') . ' EUR', 1, 1);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(60, 10, 'Provizion platforme (2%):', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(130, 10, ($data['provizioni'] ?? '') . ' EUR', 1, 1);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(60, 10, 'IBAN:', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(130, 10, ($data['payer_iban'] ?? ''), 1, 1);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(60, 10, 'Përshkrimi:', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(130, 10, ($data['description'] ?? ''), 1, 1);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(60, 10, 'Rezervimi ID:', 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(130, 10, ($data['reservation_id'] ?? ''), 1, 1);
    $pdf->Ln(15);
    $pdf->SetFont('Arial', 'I', 11);
    $pdf->Cell(0, 10, 'Fatura është gjeneruar automatikisht nga platforma Noteria.', 0, 1, 'C');
    $pdf->Ln(10);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, 'Nënshkrimi i platformës:', 0, 1);
    $pdf->Cell(0, 10, '_________________________', 0, 1);
    $file = __DIR__ . '/fatura/fatura_' . ($data['reservation_id'] ?? '0') . '_' . ($data['transaction_id'] ?? '0') . '.pdf';
    if (!is_dir(__DIR__ . '/fatura')) {
        mkdir(__DIR__ . '/fatura', 0777, true);
    }
    $pdf->Output('F', $file);
    return 'fatura/' . basename($file);
}
