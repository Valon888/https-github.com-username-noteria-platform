<?php
// test_email.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'Phpmailer.php';

echo "<h1>Testimi i Dërgimit të Email-it</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = $_POST['email'];
    $subject = "Test Email nga Noteria";
    $body = "
    <div style='font-family: sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>
        <h2 style='color: #2c3e50;'>Përshëndetje!</h2>
        <p>Ky është një email testues për të verifikuar konfigurimin e SMTP.</p>
        <p>Nëse e lexoni këtë, konfigurimi është i suksesshëm! ✅</p>
        <hr>
        <p><small>Dërguar nga sistemi Noteria</small></p>
    </div>";

    echo "<p>Duke provuar dërgimin te: <strong>$to</strong>...</p>";
    
    $result = sendMail($to, $subject, $body);
    
    if ($result['success']) {
        echo "<div style='color: green; padding: 10px; border: 1px solid green; background: #e8f5e9;'>
            <strong>SUKSES:</strong> Email-i u dërgua me sukses!
        </div>";
    } else {
        echo "<div style='color: red; padding: 10px; border: 1px solid red; background: #ffebee;'>
            <strong>GABIM:</strong> " . htmlspecialchars($result['message']) . "
        </div>";
        
        echo "<h3>Hapat për zgjidhjen e problemit (nëse përdorni Gmail):</h3>
        <ol>
            <li>Sigurohuni që keni aktivizuar <strong>2-Step Verification</strong> në llogarinë tuaj Google.</li>
            <li>Krijoni një <strong>App Password</strong> (jo fjalëkalimin tuaj normal).</li>
            <li>Përditësoni skedarin <code>mail_config.php</code> me App Password-in e ri.</li>
        </ol>";
    }
}
?>

<form method="post" style="margin-top: 20px; padding: 20px; background: #f9f9f9; border-radius: 5px; max-width: 400px;">
    <div style="margin-bottom: 15px;">
        <label>Shkruani email-in tuaj për testim:</label><br>
        <input type="email" name="email" required style="width: 100%; padding: 8px; margin-top: 5px;" placeholder="emaili.juaj@example.com">
    </div>
    <button type="submit" style="background: #0033A0; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px;">Dërgo Test Email</button>
</form>
