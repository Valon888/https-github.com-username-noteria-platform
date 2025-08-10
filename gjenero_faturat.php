<?php
require_once __DIR__ . '/fpdf.php';

function utf8_to_win1252($text) {
    return iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
}

// Shembull array me klientë dhe të dhëna të faturës
$klientet = [
    [
        'emri' => 'Agim Hoxha',
        'adresa' => 'Rr. Shembull, Prishtinë',
        'nr_fatures' => '2025-001',
        'pershkrimi' => 'Shërbim Noterial',
        'sasia' => 1,
        'cmimi' => 50.00,
    ],
    [
        'emri' => 'Blerina Gashi',
        'adresa' => 'Rr. Dardania, Prishtinë',
        'nr_fatures' => '2025-002',
        'pershkrimi' => 'Legalizim Dokumenti',
        'sasia' => 2,
        'cmimi' => 30.00,
    ],
    // Shto sa të duash...
];

foreach ($klientet as $klient) {
    $pdf = new FPDF();
    $pdf->AddPage();

    // Titulli
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, utf8_to_win1252('Faturë për Pagesë'), 0, 1, 'C');
    $pdf->Ln(5);

    // Të dhënat e klientit dhe faturës
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(100, 8, utf8_to_win1252('Klienti: ' . $klient['emri']), 0, 0);
    $pdf->Cell(0, 8, utf8_to_win1252('Data: ') . date('d.m.Y'), 0, 1);
    $pdf->Cell(100, 8, utf8_to_win1252('Adresa: ' . $klient['adresa']), 0, 1);
    $pdf->Cell(100, 8, utf8_to_win1252('Nr. Faturës: ' . $klient['nr_fatures']), 0, 1);
    $pdf->Ln(8);

    // Tabela e produkteve/shërbimeve
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(80, 8, utf8_to_win1252('Përshkrimi'), 1);
    $pdf->Cell(30, 8, utf8_to_win1252('Sasia'), 1, 0, 'C');
    $pdf->Cell(40, 8, utf8_to_win1252('Çmimi'), 1, 0, 'C');
    $pdf->Cell(40, 8, utf8_to_win1252('Totali'), 1, 1, 'C');

    $pdf->SetFont('Arial', '', 12);
    $totali = $klient['sasia'] * $klient['cmimi'];
    $pdf->Cell(80, 8, utf8_to_win1252($klient['pershkrimi']), 1);
    $pdf->Cell(30, 8, $klient['sasia'], 1, 0, 'C');
    $pdf->Cell(40, 8, number_format($klient['cmimi'], 2) . ' EUR', 1, 0, 'C');
    $pdf->Cell(40, 8, number_format($totali, 2) . ' EUR', 1, 1, 'C');

    // Totali
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(150, 8, utf8_to_win1252('Totali:'), 1);
    $pdf->Cell(40, 8, number_format($totali, 2) . ' EUR', 1, 1, 'C');

    $pdf->Ln(10);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, utf8_to_win1252('Faleminderit për pagesën!'), 0, 1, 'C');

    // Ruaj PDF-në për secilin klient
    $emri_file = 'fatura_' . $klient['nr_fatures'] . '.pdf';
    $pdf->Output('F', $emri_file);
    // Mund të dërgosh ose printosh direkt nëse është për API
}
echo "Faturat u gjeneruan me sukses!";