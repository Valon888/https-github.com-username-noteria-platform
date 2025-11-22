<?php
// PHPGangsta/GoogleAuthenticator wrapper for OTP MFA
// Vendos këtë file në vendor ose në një folder të veçantë dhe përfshije me require_once
// Shkarko nga: https://github.com/PHPGangsta/GoogleAuthenticator
// Ky është vetëm një shembull për integrim të OTP

require_once __DIR__ . '/PHPGangsta/GoogleAuthenticator.php';

// 1. Merr të dhënat nga forma
$username = $_POST['username'];
$email = $_POST['email'];
$password = $_POST['password'];

// 2. Hash password-in
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// 3. Gjenero OTP secret
$ga = new PHPGangsta_GoogleAuthenticator();
$otp_secret = $ga->createSecret();

// 4. Ruaj në databazë
$stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, otp_secret, mfa_enabled) VALUES (?, ?, ?, ?, 1)");
$stmt->execute([$username, $email, $password_hash, $otp_secret]);

// 5. Shfaq QR code për Google Authenticator
$qrCodeUrl = $ga->getQRCodeGoogleUrl('Noteria', $otp_secret);
echo "Skano këtë QR code me Google Authenticator:<br>";
echo "<img src='$qrCodeUrl' />";
echo "<br>Ose përdor këtë secret: <b>$otp_secret</b>";
?>
