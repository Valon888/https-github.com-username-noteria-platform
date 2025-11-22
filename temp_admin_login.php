<?php
/**
 * Temporary Admin Login for Testing Billing Dashboard
 * Hyrja e përkohshme e adminit për testimin e dashboard-it
 */

session_start();

// Set admin session for testing
$_SESSION['admin_id'] = 1; // ID 1 është zhvillues
$_SESSION['admin_name'] = 'Developer Admin';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Admin Login Success</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .success { color: green; font-size: 18px; margin: 20px; }
        .button { 
            background: #007cba; 
            color: white; 
            padding: 15px 30px; 
            text-decoration: none; 
            border-radius: 5px; 
            display: inline-block; 
            margin: 10px;
        }
    </style>
</head>
<body>
    <h1>✅ Admin Session Created</h1>
    <div class='success'>You are now logged in as admin for testing.</div>
    
    <a href='billing_dashboard.php' class='button'>
        🚀 Open Billing Dashboard
    </a>
    
    <a href='admin_noters.php' class='button'>
        👥 Manage Notaries
    </a>
    
    <a href='admin_login.php' class='button' style='background: #f59e0b;'>
        🔑 Use Real Admin Login
    </a>
    
    <div style='margin-top: 30px; color: #666;'>
        <small>This is a temporary admin session for testing purposes.<br>
        Use Real Admin Login for the production login system.</small>
    </div>
</body>
</html>";
?>