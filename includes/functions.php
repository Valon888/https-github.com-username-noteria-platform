<?php
// Functions.php - Library of functions for the noteria system

// Include payment functions
require_once __DIR__ . '/payment_functions.php';

/**
 * Function to generate invoice number
 * @param string $prefix Prefix for the invoice number
 * @return string Formatted invoice number
 */
function generateInvoiceNumber($prefix = 'INV') {
    return $prefix . '-' . date('Ymd') . '-' . mt_rand(1000, 9999);
}

/**
 * Function to log automatic payment
 * @param PDO $pdo Database connection
 * @param int $zyra_id ID of the notary office
 * @param float $amount Payment amount
 * @param string $payment_type Type of payment
 * @param string $reference_id Reference ID for the payment
 * @return bool True if successful, false otherwise
 */
function logAutomaticPayment($pdo, $zyra_id, $amount, $payment_type, $reference_id = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO automatic_payments 
            (zyra_id, amount, payment_type, reference_id, payment_date) 
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)");
        
        return $stmt->execute([$zyra_id, $amount, $payment_type, $reference_id]);
    } catch (PDOException $e) {
        error_log('Error logging automatic payment: ' . $e->getMessage());
        return false;
    }
}

/**
 * Function to generate electronic invoice
 * @param PDO $pdo Database connection
 * @param int $zyra_id ID of the notary office
 * @param float $amount Payment amount
 * @param string $description Invoice description
 * @param string $start_date Start date for service period
 * @param string $end_date End date for service period
 * @return array|bool Invoice data if successful, false otherwise
 */
function generateElectronicInvoice($pdo, $zyra_id, $amount, $description, $start_date, $end_date) {
    try {
        // Get notary office information
        $stmt = $pdo->prepare("SELECT emri, adresa, nipt FROM zyrat WHERE id = ?");
        $stmt->execute([$zyra_id]);
        $zyra = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$zyra) {
            error_log('Zyra me ID ' . $zyra_id . ' nuk u gjet.');
            return false;
        }
        
        // Generate invoice number
        $invoiceNumber = generateInvoiceNumber('NOTA');
        
        // Calculate VAT (20%)
        $vat = $amount * 0.2;
        $totalAmount = $amount + $vat;
        
        // Create invoice data
        $invoiceData = [
            'invoice_number' => $invoiceNumber,
            'date_issued' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+15 days')),
            'client_name' => $zyra['emri'],
            'client_address' => $zyra['adresa'],
            'client_nipt' => $zyra['nipt'] ?? 'N/A',
            'description' => $description,
            'amount' => $amount,
            'vat' => $vat,
            'total_amount' => $totalAmount,
            'service_period_start' => $start_date,
            'service_period_end' => $end_date,
        ];
        
        // Insert invoice into database
        $stmt = $pdo->prepare("INSERT INTO invoices 
            (invoice_number, zyra_id, amount, vat, total_amount, description, date_issued, due_date, 
            service_period_start, service_period_end, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'issued')");
        
        $stmt->execute([
            $invoiceData['invoice_number'],
            $zyra_id,
            $invoiceData['amount'],
            $invoiceData['vat'],
            $invoiceData['total_amount'],
            $invoiceData['description'],
            $invoiceData['date_issued'],
            $invoiceData['due_date'],
            $invoiceData['service_period_start'],
            $invoiceData['service_period_end']
        ]);
        
        $invoiceData['id'] = $pdo->lastInsertId();
        
        return $invoiceData;
    } catch (PDOException $e) {
        error_log('Error generating electronic invoice: ' . $e->getMessage());
        return false;
    }
}

/**
 * Send payment notification email
 * @param string $email Recipient email address
 * @param string $subject Email subject
 * @param array $data Data for email content
 * @return bool True if sent successfully, false otherwise
 */
function sendPaymentNotification($email, $subject, $data) {
    try {
        // In a real system, this would use a proper email sending library
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: noteria@example.com'
        ];
        
        // Simple HTML template for the email
        $message = '<html><body>';
        $message .= '<h2 style="color: #2d6cdf;">Njoftim Pagese</h2>';
        $message .= '<p>Pershendetje,</p>';
        $message .= '<p>' . ($data['message'] ?? 'Ju keni nje njoftim te ri pagese.') . '</p>';
        
        if (isset($data['invoice_number'])) {
            $message .= '<p><strong>Numri i fatures:</strong> ' . $data['invoice_number'] . '</p>';
        }
        
        if (isset($data['amount'])) {
            $message .= '<p><strong>Shuma:</strong> ' . number_format($data['amount'], 2) . ' €</p>';
        }
        
        if (isset($data['due_date'])) {
            $message .= '<p><strong>Afati i pageses:</strong> ' . $data['due_date'] . '</p>';
        }
        
        $message .= '<p>Ju faleminderit,<br>Ekipi i Noteria</p>';
        $message .= '</body></html>';
        
        // For demonstration purposes, we'll just return true
        // In production, you'd use mail() or a library like PHPMailer
        
        // Simulate email sending success
        error_log("Email would be sent to {$email} with subject: {$subject}");
        return true;
    } catch (Exception $e) {
        error_log('Error sending payment notification: ' . $e->getMessage());
        return false;
    }
}