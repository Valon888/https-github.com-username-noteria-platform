<?php
// mail_config.php

// Konfigurimi i SMTP për dërgimin e emailave
// Ju lutemi plotësoni të dhënat e mëposhtme me ato të serverit tuaj SMTP

if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.gmail.com');        // Serveri SMTP
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);                     // Porti SMTP
if (!defined('SMTP_USER')) define('SMTP_USER', 'noteria.kosove@gmail.com'); // Emaili juaj dërgues
if (!defined('SMTP_PASS')) define('SMTP_PASS', 'vendos_ketu_app_password'); // Fjalëkalimi ose App Password
if (!defined('SMTP_SECURE')) define('SMTP_SECURE', 'tls');                 // Protokolli i sigurisë
if (!defined('SMTP_FROM_EMAIL')) define('SMTP_FROM_EMAIL', 'no-reply@noteria.com'); // Emaili dërgues
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'Noteria Elektronike');   // Emri dërgues
if (!defined('SMTP_DEBUG')) define('SMTP_DEBUG', 0);                      // Debug level

// Shënim për Gmail:
// Nëse përdorni Gmail, duhet të aktivizoni "2-Step Verification" dhe të krijoni një "App Password".
// Shkoni te: Google Account > Security > 2-Step Verification > App passwords
?>
