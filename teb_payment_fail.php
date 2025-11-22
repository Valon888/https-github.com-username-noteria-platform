<?php
// teb_payment_fail.php
// Faqe dështimi pas pagesës me TEB Bank
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagesa dështoi</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <style>
        body { background: linear-gradient(120deg, #e2eafc 0%, #f8fafc 100%); font-family: Montserrat, Arial, sans-serif; margin: 0; padding: 0; }
        .container { max-width: 420px; margin: 60px auto; background: #fff; border-radius: 18px; box-shadow: 0 8px 32px rgba(0,0,0,0.10); padding: 40px 32px; text-align: center; }
        h1 { color: #d32f2f; font-size: 2rem; font-weight: 700; margin-bottom: 18px; }
        .desc { color: #444; font-size: 1.1rem; margin-bottom: 18px; }
        .fail-icon { font-size: 3.5rem; color: #d32f2f; margin-bottom: 18px; }
        a { color: #2d6cdf; text-decoration: none; font-weight: 600; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="container">
    <div class="fail-icon">&#10008;</div>
    <h1>Pagesa dështoi!</h1>
    <div class="desc">Na vjen keq, pagesa juaj nuk u përfundua me sukses.<br>Ju lutem provoni përsëri ose kontaktoni mbështetjen.<br><br>
        <a href="teb_payment_form.php">Provo përsëri</a>
    </div>
</div>
</body>
</html>
