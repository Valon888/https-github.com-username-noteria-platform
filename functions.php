<?php
/**
 * Shared functions used across the billing system
 */

/**
 * Log për pagesat automatike
 */
function logAutomaticPayment($message) {
    $logFile = __DIR__ . '/auto_payments.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

/**
 * Gjeneron faturë elektronike dhe e ruan në sistem
 */
function generateElectronicInvoice($payment, $pdo) {
    // Gjenero numrin unik të faturës
    $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad($payment['id'], 5, '0', STR_PAD_LEFT);
    
    // Rrumbullako shumën e TVSH-së dhe totalin
    $subtotal = round($payment['amount'] / 1.18, 2); // Supozojmë TVSH 18%
    $vat = round($payment['amount'] - $subtotal, 2);
    $total = $payment['amount'];
    
    // Gjej të dhënat e noterit
    $noterStmt = $pdo->prepare("SELECT emri, mbiemri, adresa, email, telefoni, nipt, zyra_emri FROM noteri WHERE id = ?");
    $noterStmt->execute([$payment['noter_id']]);
    $noter = $noterStmt->fetch(PDO::FETCH_ASSOC);
    
    // Gjenero HTML për faturën
    $invoiceHtml = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Faturë Elektronike - ' . $invoiceNumber . '</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .invoice-container { max-width: 800px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; }
            .invoice-header { display: flex; justify-content: space-between; padding-bottom: 20px; border-bottom: 2px solid #2d6cdf; }
            .invoice-title { font-size: 28px; color: #2d6cdf; font-weight: bold; }
            .invoice-details { margin-top: 20px; display: flex; justify-content: space-between; }
            .invoice-details-left, .invoice-details-right { width: 48%; }
            .invoice-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .invoice-table th, .invoice-table td { padding: 10px; border-bottom: 1px solid #ddd; text-align: left; }
            .invoice-table th { background-color: #f8f9fa; }
            .invoice-total { margin-top: 20px; display: flex; justify-content: flex-end; }
            .invoice-total-table { width: 300px; }
            .invoice-total-table td { padding: 5px; }
            .invoice-total-table .total { font-weight: bold; font-size: 18px; border-top: 2px solid #ddd; }
            .footer { margin-top: 30px; border-top: 1px solid #ddd; padding-top: 10px; font-size: 12px; text-align: center; color: #777; }
            .qr-code { text-align: right; margin-top: 20px; }
            .signature { margin-top: 40px; }
            .signature-line { width: 200px; border-bottom: 1px solid #333; margin-bottom: 5px; }
        </style>
    </head>
    <body>
        <div class="invoice-container">
            <div class="invoice-header">
                <div>
                    <div class="invoice-title">FATURË ELEKTRONIKE</div>
                    <div>Nr. ' . $invoiceNumber . '</div>
                </div>
                <div>
                    <img src="assets/logo.png" alt="Noteria Logo" style="max-height: 80px;">
                    <div>Platforma Noteriale e Kosovës</div>
                </div>
            </div>
            
            <div class="invoice-details">
                <div class="invoice-details-left">
                    <h3>Shitësi</h3>
                    <p>
                        <strong>Noteria Sh.p.k.</strong><br>
                        Adresa: Rr. "Gazmend Zajmi" Nr. 24<br>
                        10000 Prishtinë, Kosovë<br>
                        NIPT: K91234567A<br>
                        Tel: +383 44 123 456<br>
                        Email: finance@noteria.com
                    </p>
                </div>
                <div class="invoice-details-right">
                    <h3>Klienti</h3>
                    <p>
                        <strong>' . htmlspecialchars($noter['zyra_emri'] ?: ($noter['emri'] . ' ' . $noter['mbiemri'])) . '</strong><br>
                        Adresa: ' . htmlspecialchars($noter['adresa'] ?: 'N/A') . '<br>
                        NIPT: ' . htmlspecialchars($noter['nipt'] ?: 'N/A') . '<br>
                        Tel: ' . htmlspecialchars($noter['telefoni'] ?: 'N/A') . '<br>
                        Email: ' . htmlspecialchars($noter['email'] ?: 'N/A') . '
                    </p>
                </div>
            </div>
            
            <div class="invoice-details">
                <div class="invoice-details-left">
                    <h3>Të dhënat e faturës</h3>
                    <p>
                        Data e faturës: ' . date('d.m.Y') . '<br>
                        Data e pagesës: ' . date('d.m.Y', strtotime($payment['payment_date'])) . '<br>
                        Periudha e faturimit: ' . date('d.m.Y', strtotime($payment['billing_period_start'])) . ' - ' . 
                        date('d.m.Y', strtotime($payment['billing_period_end'])) . '<br>
                        Metoda e pagesës: ' . ucfirst($payment['payment_method']) . '<br>
                        ID Transaksioni: ' . htmlspecialchars($payment['transaction_id'])  . '
                    </p>
                </div>
                <div class="invoice-details-right">
                    <div class="qr-code">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' . urlencode($invoiceNumber) . '" alt="QR Code">
                    </div>
                </div>
            </div>
            
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Përshkrimi</th>
                        <th>Sasia</th>
                        <th>Çmimi</th>
                        <th>TVSH (18%)</th>
                        <th>Vlera</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Abonimi mujor në platformën Noteria</td>
                        <td>1</td>
                        <td>€' . number_format($subtotal, 2) . '</td>
                        <td>€' . number_format($vat, 2) . '</td>
                        <td>€' . number_format($total, 2) . '</td>
                    </tr>
                </tbody>
            </table>
            
            <div class="invoice-total">
                <table class="invoice-total-table">
                    <tr>
                        <td>Nëntotali:</td>
                        <td>€' . number_format($subtotal, 2) . '</td>
                    </tr>
                    <tr>
                        <td>TVSH (18%):</td>
                        <td>€' . number_format($vat, 2) . '</td>
                    </tr>
                    <tr class="total">
                        <td>Totali:</td>
                        <td>€' . number_format($total, 2) . '</td>
                    </tr>
                </table>
            </div>
            
            <div class="signature">
                <div class="signature-line"></div>
                <div>Nënshkrimi i autorizuar</div>
            </div>
            
            <div class="footer">
                <p>Kjo faturë është gjeneruar elektronikisht dhe është e vlefshme pa nënshkrim dhe vulë.</p>
                <p>Pagesa është procesuar automatikisht përmes sistemit të Noteria.</p>
                <p>&copy; ' . date('Y') . ' Noteria. Të gjitha të drejtat e rezervuara.</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Krijo direktorinë për faturat nëse nuk ekziston
    $invoicesDir = __DIR__ . '/faturat';
    if (!is_dir($invoicesDir)) {
        mkdir($invoicesDir, 0777, true);
    }
    
    // Ruaj faturën në sistem
    $invoicePath = $invoicesDir . '/' . $invoiceNumber . '.html';
    file_put_contents($invoicePath, $invoiceHtml);
    
    // Ruaj PDF version gjithashtu (në një implementim të plotë do të përdorej një librari si TCPDF ose mPDF)
    // Për demonstrim, po simulojmë gjenerimin e PDF-së
    $pdfPath = $invoicesDir . '/' . $invoiceNumber . '.pdf';
    // Në implementimin e plotë: $pdf = new TCPDF(); $pdf->writeHTML($invoiceHtml); $pdf->Output($pdfPath, 'F');
    // Për tani thjesht sinjalizojmë se PDF duhet gjeneruar më vonë
    file_put_contents($pdfPath . '.todo', 'PDF to be generated');
    
    // Shto referencën e faturës në bazën e të dhënave
    try {
        $stmt = $pdo->prepare("
            UPDATE subscription_payments
            SET invoice_number = ?, invoice_path = ?, invoice_created_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$invoiceNumber, $invoicePath, $payment['id']]);
        
        logAutomaticPayment("FATURE ELEKTRONIKE: U gjenerua fatura #{$invoiceNumber} për Noterin #{$payment['noter_id']}");
        return $invoiceNumber;
    } catch (Exception $e) {
        logAutomaticPayment("ERROR: Dështoi gjenerimi i faturës për Noterin #{$payment['noter_id']}: " . $e->getMessage());
        return false;
    }
}

/**
 * Dërgo njoftim për pagesën
 */
function sendPaymentNotification($payment, $status, $method = '') {
    $invoiceInfo = '';
    
    // Shto informacionin për faturën elektronike nëse ekziston
    if ($status === 'success' && isset($payment['invoice_number']) && $payment['invoice_number']) {
        $invoiceInfo = "\n\nFatura elektronike #{$payment['invoice_number']} u gjenerua automatikisht dhe është gati për shkarkim në panelin tuaj.";
    }
    
    $message = $status === 'success' 
        ? "✅ Pagesa juaj prej €{$payment['amount']} u procesua me sukses via $method.$invoiceInfo"
        : "❌ Pagesa juaj prej €{$payment['amount']} dështoi. Ju lutemi kontaktoni me ne.";
    
    logAutomaticPayment("NOTIFICATION: $message sent to {$payment['emri']} {$payment['mbiemri']} ({$payment['email']})");
}