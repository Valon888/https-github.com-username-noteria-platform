<?php
/**
 * PHPMailer Configuration and Helper Function
 * This file provides email sending functionality using PHPMailer
 */

// Load configuration
if (file_exists(__DIR__ . '/mail_config.php')) {
    require_once __DIR__ . '/mail_config.php';
} else {
    // Default fallback values if config is missing
    define('SMTP_HOST', 'smtp.gmail.com');
    define('SMTP_PORT', 587);
    define('SMTP_USER', '');
    define('SMTP_PASS', '');
    define('SMTP_SECURE', 'tls');
    define('SMTP_FROM_EMAIL', 'no-reply@noteria.local');
    define('SMTP_FROM_NAME', 'Noteria');
    define('SMTP_DEBUG', 0);
}

// Check if PHPMailer is installed via Composer
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

/**
 * Send email using PHPMailer
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML supported)
 * @param string $fromEmail Sender email (optional, defaults to config)
 * @param string $fromName Sender name (optional, defaults to config)
 * @param array $attachments Array of file paths to attach (optional)
 * @return array ['success' => bool, 'message' => string]
 */
function sendMail($to, $subject, $body, $fromEmail = null, $fromName = null, $attachments = []) {
    // Use defaults from config if not provided
    $fromEmail = $fromEmail ?? SMTP_FROM_EMAIL;
    $fromName = $fromName ?? SMTP_FROM_NAME;

    // Check if PHPMailer class exists
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        // Fallback to PHP's built-in mail function
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: $fromName <$fromEmail>\r\n";
        
        if (mail($to, $subject, $body, $headers)) {
            return ['success' => true, 'message' => 'Email sent via PHP mail()'];
        } else {
            return ['success' => false, 'message' => 'Failed to send via PHP mail()'];
        }
    }
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = SMTP_DEBUG;
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE; // PHPMailer::ENCRYPTION_STARTTLS or PHPMailer::ENCRYPTION_SMTPS
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        // Recipients
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);
        
        // Attachments
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $mail->addAttachment($attachment);
                }
            }
        }
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);
        
        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return ['success' => false, 'message' => $mail->ErrorInfo];
    }
}
?>
