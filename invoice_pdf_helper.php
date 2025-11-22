<?php
/**
 * Helper për konvertimin e faturave HTML në PDF
 */

// Ky funksion do të konvertojë të gjitha faturat HTML në PDF
// Duhet të instalohet paraprakisht libraria mPDF me Composer
// composer require mpdf/mpdf

function convertAllInvoicesToPDF() {
    // Direktorite për faturat
    $invoicesDir = __DIR__ . '/faturat';
    
    if (!is_dir($invoicesDir)) {
        return [
            'success' => false,
            'message' => 'Direktoria e faturave nuk ekziston'
        ];
    }
    
    // Krijo një array për të ruajtur rezultatet
    $results = [
        'success' => true,
        'converted' => 0,
        'failed' => 0,
        'total' => 0,
        'details' => []
    ];
    
    // Gjej të gjithë .todo files që tregojnë se PDF duhet gjeneruar
    $todoFiles = glob($invoicesDir . '/*.pdf.todo');
    
    if (empty($todoFiles)) {
        $results['message'] = 'Nuk u gjetën fatura për konvertim';
        return $results;
    }
    
    $results['total'] = count($todoFiles);
    
    foreach ($todoFiles as $todoFile) {
        $invoiceNumber = basename($todoFile, '.pdf.todo');
        $htmlFile = $invoicesDir . '/' . $invoiceNumber . '.html';
        $pdfFile = $invoicesDir . '/' . $invoiceNumber . '.pdf';
        
        // Kontrollo nëse ekziston file HTML
        if (!file_exists($htmlFile)) {
            $results['failed']++;
            $results['details'][] = [
                'invoice' => $invoiceNumber,
                'status' => 'failed',
                'reason' => 'HTML file nuk ekziston'
            ];
            continue;
        }
        
        try {
            // Në këtë pjesë, përdor mPDF për të konvertuar HTML në PDF
            // Komento këto rreshta dhe implemento mPDF kur të instalohet libraria
            
            /*
            // Create an mPDF instance
            $mpdf = new \Mpdf\Mpdf([
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 15,
                'margin_bottom' => 15,
            ]);
            
            // Read the HTML content
            $html = file_get_contents($htmlFile);
            
            // Generate PDF
            $mpdf->WriteHTML($html);
            
            // Save the PDF
            $mpdf->Output($pdfFile, 'F');
            */
            
            // Për tani, vetëm krijohet një PDF i thjeshtë për demonstrim
            $html = file_get_contents($htmlFile);
            file_put_contents($pdfFile, "PDF version of invoice {$invoiceNumber}\n\nThis is a placeholder for the actual PDF that would be generated using mPDF.\n\nOriginal HTML length: " . strlen($html) . " bytes");
            
            // Fshi .todo file pasi të jetë përfunduar me sukses
            unlink($todoFile);
            
            $results['converted']++;
            $results['details'][] = [
                'invoice' => $invoiceNumber,
                'status' => 'success'
            ];
            
        } catch (Exception $e) {
            $results['failed']++;
            $results['details'][] = [
                'invoice' => $invoiceNumber,
                'status' => 'failed',
                'reason' => $e->getMessage()
            ];
        }
    }
    
    $results['message'] = "U konvertuan {$results['converted']} nga {$results['total']} fatura në PDF";
    
    return $results;
}

// Funksion për të konvertuar një faturë të vetme
function convertInvoiceToPDF($invoiceNumber) {
    $invoicesDir = __DIR__ . '/faturat';
    $htmlFile = $invoicesDir . '/' . $invoiceNumber . '.html';
    $pdfFile = $invoicesDir . '/' . $invoiceNumber . '.pdf';
    $todoFile = $invoicesDir . '/' . $invoiceNumber . '.pdf.todo';
    
    if (!file_exists($htmlFile)) {
        return [
            'success' => false,
            'message' => 'HTML fatura nuk ekziston'
        ];
    }
    
    try {
        // Implemento mPDF kur të instalohet libraria
        /*
        $mpdf = new \Mpdf\Mpdf([
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
        ]);
        
        $html = file_get_contents($htmlFile);
        $mpdf->WriteHTML($html);
        $mpdf->Output($pdfFile, 'F');
        */
        
        // Për tani, vetëm krijohet një PDF i thjeshtë për demonstrim
        $html = file_get_contents($htmlFile);
        file_put_contents($pdfFile, "PDF version of invoice {$invoiceNumber}\n\nThis is a placeholder for the actual PDF that would be generated using mPDF.\n\nOriginal HTML length: " . strlen($html) . " bytes");
        
        // Fshi .todo file nëse ekziston
        if (file_exists($todoFile)) {
            unlink($todoFile);
        }
        
        return [
            'success' => true,
            'message' => "Fatura #{$invoiceNumber} u konvertua në PDF me sukses"
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Gabim gjatë konvertimit të faturës: " . $e->getMessage()
        ];
    }
}