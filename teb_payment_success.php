<?php
// teb_payment_success.php
// Faqe suksesi pas pagesës me TEB Bank
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagesa u krye me sukses</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <style>
        body { background: linear-gradient(120deg, #e2eafc 0%, #f8fafc 100%); font-family: Montserrat, Arial, sans-serif; margin: 0; padding: 0; }
        .container { max-width: 420px; margin: 60px auto; background: #fff; border-radius: 18px; box-shadow: 0 8px 32px rgba(0,0,0,0.10); padding: 40px 32px; text-align: center; }
        h1 { color: #2e7d32; font-size: 2rem; font-weight: 700; margin-bottom: 18px; }
        .desc { color: #444; font-size: 1.1rem; margin-bottom: 18px; }
        .success-icon { font-size: 3.5rem; color: #2e7d32; margin-bottom: 18px; }
        a { color: #2d6cdf; text-decoration: none; font-weight: 600; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="container">
    <div class="success-icon">&#10004;</div>
    <h1>Pagesa u krye me sukses!</h1>
    <div class="desc">Faleminderit për pagesën tuaj. Transaksioni u përfundua me sukses përmes TEB Bank.<br><br>
        <a href="index.php">Kthehu në faqen kryesore</a>
    </div>
</div>
</body>
</html>
