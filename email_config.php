<?php
// Konfiguraion email për platformën e noterisë
// filepath: d:\xampp\htdocs\noteria\email_config.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Konfigurimi i SMTP (për të ardhmen)
$email_config = [
    'smtp_enabled' => false, // Kthehet në test mode
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password', // Jo password normal!
    'smtp_encryption' => 'tls',
    'from_email' => 'noreply@noteria.com',
    'from_name' => 'Noteria Platform'
];

// Funksioni i përmirësuar për dërgimin e email-ave
function sendEmailWithSMTP($to_email, $subject, $message, $from_name = null) {
    global $email_config;
    
    // Sigurohu që $email_config është i inicializuar
    if (!isset($email_config) || !is_array($email_config)) {
        error_log("EMAIL LOG (Config Error): To: $to_email | Subject: $subject");
        return true;
    }
    
    // Nëse SMTP nuk është aktivizuar, vetëm logo
    if (!isset($email_config['smtp_enabled']) || !$email_config['smtp_enabled']) {
        error_log("EMAIL LOG: To: $to_email | Subject: $subject");
        return true;
    }
    
    // Ngarko PHPMailer nëse nuk është i disponueshëm
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        require_once 'PHPMailer-master/src/Exception.php';
        require_once 'PHPMailer-master/src/PHPMailer.php';
        require_once 'PHPMailer-master/src/SMTP.php';
    }
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("PHPMailer nuk është i instaluar. Duke përdorur log vetëm.");
        return true;
    }

    try {
        $mail = new PHPMailer(true);
        
        // Konfigurimi i SMTP
        $mail->isSMTP();
        $mail->Host = $email_config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $email_config['smtp_username'];
        $mail->Password = $email_config['smtp_password'];
        $mail->SMTPSecure = $email_config['smtp_encryption'];
        $mail->Port = $email_config['smtp_port'];
        $mail->CharSet = 'UTF-8';
        
        // Konfigurimi i dërguesit dhe marrësit
        $mail->setFrom($email_config['from_email'], $from_name ?: $email_config['from_name']);
        $mail->addAddress($to_email);
        
        // Përmbajtja e email-it
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->isHTML(false);
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Email gabim: " . $e->getMessage());
        return false;
    }
}

// Template email për regjistrimin e suksesshëm
function getRegistrationSuccessEmail($office_name, $transaction_id, $email) {
    return "
Përshëndetje,

Faleminderit që zgjodhët platformën tonë të noterisë!

DETAJET E REGJISTRIMIT:
- Emri i Zyrës: $office_name
- Email: $email
- Transaction ID: $transaction_id
- Data e Regjistrimit: " . date('d/m/Y H:i') . "

HAPAT E ARDHSHËM:
1. Dëshmi e pagesës është duke u verifikuar automatikisht nga sistemi ynë
2. ⚡ Verifikimi do të bëhet brenda 3 minutave (jo 24 orëve!)
3. Do të merrni një email konfirmimi kur pagesa të jetë aprovuar
4. Pas aprovimit, do të keni akses të plotë në platformë

INFORMACION I RËNDËSISHËM:
- Ruani Transaction ID-në për referenca të ardhshme
- Nëse keni pyetje, kontaktoni support@noteria.com
- Platforma është e disponueshme 24/7

Faleminderit për besimin tuaj!

Ekipi i Noteria Platform
Email: support@noteria.com
Website: www.noteria.com

---
Ky email u gjenerua automatikisht. Ju lutemi mos përgjigjuni drejtpërdrejt.
";
}

// Template email për konfirmimin e pagesës
function getPaymentVerificationEmail($office_name, $transaction_id, $status) {
    if ($status === 'approved') {
        return "
Përshëndetje,

PAGESA JUAJ U VERIFIKUA ME SUKSES! 🎉

DETAJET:
- Emri i Zyrës: $office_name
- Transaction ID: $transaction_id
- Data e Verifikimit: " . date('d/m/Y H:i') . "
- Statusi: APROVUAR

QASJA NË PLATFORMË:
Tani mund të aksesoni të gjitha shërbimet e platformës:
- Dashboard i kompletë
- Menaxhimi i dokumenteve
- Rezervimi i termineve
- Raportimi dhe analitika

LINKU I HYRJES:
http://localhost/noteria/login.php

Mirë se erdhët në familjen e Noteria Platform!

Ekipi i Noteria Platform
";
    } else {
        return "
Përshëndetje,

Na vjen keq, por pagesa juaj nuk mund të verifikohet.

DETAJET:
- Emri i Zyrës: $office_name
- Transaction ID: $transaction_id
- Statusi: REFUZUAR

ARSYET E MUNDSHME:
- Dëshmi e pagesës nuk është e qartë
- Shuma e pagesës nuk përputhet
- Të dhënat e transaksionit janë jo të sakta

HAPAT E ARDHSHËM:
1. Kontrolloni të dhënat e pagesës
2. Sigurohuni që shuma është e saktë
3. Dërgoni një dëshmi më të qartë
4. Ose kontaktoni support@noteria.com për ndihmë

Faleminderit për mirëkuptimin!

Ekipi i Noteria Platform
";
    }
}

// Funksioni për dërgimin e email-it të regjistrimit
function sendRegistrationEmail($email, $office_name, $transaction_id) {
    $subject = "Regjistrimi i Suksesshëm - Noteria Platform";
    $message = getRegistrationSuccessEmail($office_name, $transaction_id, $email);
    return sendEmailWithSMTP($email, $subject, $message);
}

// Funksioni për dërgimin e email-it të verifikimit të pagesës
function sendPaymentVerificationEmail($email, $office_name, $transaction_id, $status = 'approved') {
    $subject = $status === 'approved' 
        ? "Pagesa u Verifikua - Mirë se erdhët!" 
        : "Probleme me Verifikimin e Pagesës";
    $message = getPaymentVerificationEmail($office_name, $transaction_id, $status);
    return sendEmailWithSMTP($email, $subject, $message);
}

// Test funksioni për email
function testEmailConfiguration() {
    global $email_config;
    
    echo "<h3>🧪 Test Email Configuration</h3>";
    echo "<ul>";
    
    // Kontrollo nëse $email_config është i inicializuar
    if (!isset($email_config) || !is_array($email_config)) {
        echo "<li style='color: red;'><strong>Error:</strong> Email configuration nuk është i ngarkuar</li>";
        echo "</ul>";
        return;
    }
    
    echo "<li><strong>SMTP Enabled:</strong> " . (isset($email_config['smtp_enabled']) && $email_config['smtp_enabled'] ? 'YES' : 'NO') . "</li>";
    echo "<li><strong>SMTP Host:</strong> " . ($email_config['smtp_host'] ?? 'Not set') . "</li>";
    echo "<li><strong>SMTP Port:</strong> " . ($email_config['smtp_port'] ?? 'Not set') . "</li>";
    echo "<li><strong>From Email:</strong> " . ($email_config['from_email'] ?? 'Not set') . "</li>";
    echo "</ul>";
    
    if (isset($email_config['smtp_enabled']) && $email_config['smtp_enabled']) {
        echo "<p style='color: green;'>✓ Email sistem është aktiv dhe gati për përdorim.</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Email sistemi është në modalitetin test (vetëm log).</p>";
        echo "<p><small>Për të aktivizuar email-et, vendosni \$email_config['smtp_enabled'] = true në email_config.php</small></p>";
    }
}
?>