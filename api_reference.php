<?php
// api_reference.php
header('Content-Type: text/html; charset=utf-8');
// ...existing code...
// Pas header, vazhdo direkt me HTML pa ?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Reference | E-Noteria SaaS</title>
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e0e7ff 100%);
            color: #1f2937;
            min-height: 100vh;
        }
        .api-header {
            background: linear-gradient(90deg, #0033A0 0%, #10B981 100%);
            color: #fff;
            padding: 48px 0 32px 0;
            text-align: center;
            border-radius: 0 0 32px 32px;
            box-shadow: 0 8px 32px rgba(0,51,160,0.08);
            margin-bottom: 32px;
        }
        .api-header h1 {
            font-size: 2.8rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            letter-spacing: -1px;
        }
        .api-header p {
            font-size: 1.2rem;
            opacity: 0.92;
        }
        .container-api {
            max-width: 900px;
            margin: 0 auto 40px auto;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 32px rgba(0,0,0,0.09);
            padding: 40px 32px 32px 32px;
            position: relative;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 32px;
            color: #0033A0;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: color 0.2s;
        }
        .back-link:hover { color: #10B981; text-decoration: underline; }
        h1, h2, h3 { color: #0033A0; font-weight: 700; }
        h2 { margin-top: 2.5rem; font-size: 2rem; }
        h3 { margin-top: 2rem; font-size: 1.2rem; }
        pre {
            background: #181c2a;
            color: #e0e7ff;
            border-radius: 8px;
            padding: 18px 18px 18px 24px;
            font-size: 1.05rem;
            margin: 1.2rem 0;
            overflow-x: auto;
            box-shadow: 0 2px 12px rgba(0,51,160,0.07);
        }
        code {
            background: #f3f4f6;
            color: #0033A0;
            border-radius: 6px;
            padding: 3px 8px;
            font-size: 1.01em;
        }
        hr { margin: 2.5rem 0; border: none; border-top: 2px solid #e5e7eb; }
        .api-card {
            background: #f3f4f6;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(16,185,129,0.07);
            padding: 24px 24px 18px 24px;
            margin-bottom: 2.2rem;
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .api-card:hover {
            box-shadow: 0 8px 32px rgba(0,51,160,0.13);
            transform: translateY(-4px) scale(1.01);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,51,160,0.04);
        }
        th, td {
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
        }
        th { background: #0033A0; color: #fff; font-weight: 600; }
        tr:last-child td { border-bottom: none; }
        @media (max-width: 700px) {
            .container-api { padding: 18px 6vw; }
            .api-header { padding: 32px 0 18px 0; }
            h1 { font-size: 2rem; }
            h2 { font-size: 1.2rem; }
        }
    </style>
</head>
<body>
    <div class="api-header">
        <div style="font-size:2.5rem;"><i class="fas fa-code"></i></div>
        <h1>API Reference</h1>
        <p>Dokumentacioni i plotë për endpoint-et e E-Noteria SaaS.<br>Shembuj, struktura, dhe udhëzime për përdorim të sigurt.</p>
    </div>
    <div class="container-api">
        <a href="index.php" class="back-link"><i class="fas fa-arrow-left me-2"></i>Kthehu në faqen kryesore</a>
        <?php
        $md = file_get_contents('API-REFERENCE.md');
        // Markdown to HTML (pak më i avancuar)
        $md = preg_replace('/^# (.*)$/m', '<h1>$1</h1>', $md);
        $md = preg_replace('/^## (.*)$/m', '<h2>$1</h2>', $md);
        $md = preg_replace('/^---$/m', '<hr>', $md);
        $md = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $md);
        $md = preg_replace('/```([\s\S]*?)```/', '<pre>$1</pre>', $md);
        $md = preg_replace('/`([^`]+)`/', '<code>$1</code>', $md);
        // API card for each endpoint
        $md = preg_replace('/(<h2>.*?\n)([\s\S]*?)(?=<h2>|$)/', '<div class="api-card">$1$2</div>', $md);
        $md = nl2br($md);
        echo $md;
        ?>
    </div>
</body>
</html>
