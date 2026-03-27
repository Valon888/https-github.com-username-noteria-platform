<?php
header('Content-Type: text/html; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = null;
$error = null;
$fullName = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $error = 'Kërkesa nuk u verifikua. Ju lutemi rifreskoni faqen dhe provoni përsëri.';
    } elseif ($fullName === '' || $email === '' || $subject === '' || $message === '') {
        $error = 'Ju lutemi plotësoni të gjitha fushat e detyrueshme.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresa e email-it nuk është e vlefshme.';
    } else {
        $safeLogLine = sprintf(
            "[%s] Contact form | Name: %s | Email: %s | Phone: %s | Subject: %s\nMessage: %s\n\n",
            date('Y-m-d H:i:s'),
            str_replace(["\r", "\n"], ' ', $fullName),
            str_replace(["\r", "\n"], ' ', $email),
            str_replace(["\r", "\n"], ' ', $phone),
            str_replace(["\r", "\n"], ' ', $subject),
            str_replace(["\r"], '', $message)
        );

        @file_put_contents(__DIR__ . '/contact_messages.log', $safeLogLine, FILE_APPEND);

        $success = 'Faleminderit! Mesazhi juaj u pranua me sukses. Ekipi ynë do t’ju kontaktojë sa më shpejt.';
        $fullName = '';
        $email = '';
        $phone = '';
        $subject = '';
        $message = '';

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Na kontaktoni për pyetje rreth shërbimeve noteriale dhe platformës e-Noteria.">
    <title>Kontakti | e-Noteria</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1f4fa3;
            --primary-dark: #173c7a;
            --accent: #d4a53a;
            --bg-soft: #f3f7ff;
            --text: #13213a;
            --muted: #5f6d86;
            --white: #ffffff;
            --success-bg: #e9f8ef;
            --success-text: #1f8b4c;
            --error-bg: #ffeded;
            --error-text: #c62828;
            --shadow: 0 16px 38px rgba(22, 62, 128, 0.14);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Inter', Arial, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 15% 20%, rgba(31,79,163,0.14) 0%, transparent 35%),
                radial-gradient(circle at 85% 10%, rgba(212,165,58,0.15) 0%, transparent 40%),
                linear-gradient(135deg, #f7f9ff 0%, #edf3ff 45%, #fdfcff 100%);
            min-height: 100vh;
        }

        .container {
            width: min(1120px, 92%);
            margin: 0 auto;
        }

        .navbar {
            position: sticky;
            top: 0;
            z-index: 10;
            background: rgba(255, 255, 255, 0.86);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid rgba(31, 79, 163, 0.12);
        }

        .nav-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 0;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            color: var(--primary);
            text-decoration: none;
            letter-spacing: 0.2px;
            font-size: 1.1rem;
        }

        .brand-logo {
            width: 38px;
            height: 38px;
            object-fit: contain;
            border-radius: 50%;
            box-shadow: 0 6px 16px rgba(0,0,0,0.18);
            background: #000;
        }

        .nav-links {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .nav-link {
            text-decoration: none;
            color: var(--primary-dark);
            font-weight: 600;
            padding: 9px 14px;
            border-radius: 10px;
            transition: all .2s ease;
        }

        .nav-link:hover {
            background: rgba(31, 79, 163, 0.1);
        }

        .nav-link.active {
            background: linear-gradient(135deg, var(--primary), #2f66cb);
            color: var(--white);
        }

        .hero {
            padding: 54px 0 26px;
        }

        .hero-card {
            background: linear-gradient(150deg, #ffffff, #f7faff);
            border: 1px solid rgba(31, 79, 163, 0.14);
            border-radius: 22px;
            box-shadow: var(--shadow);
            padding: 34px;
        }

        .eyebrow {
            display: inline-block;
            background: rgba(31,79,163,0.1);
            color: var(--primary-dark);
            font-weight: 700;
            padding: 7px 12px;
            border-radius: 999px;
            margin-bottom: 14px;
            font-size: 0.86rem;
        }

        h1 {
            margin: 0;
            font-size: clamp(1.8rem, 3vw, 2.6rem);
            line-height: 1.18;
            color: #0f2f66;
        }

        .hero p {
            margin: 14px 0 0;
            color: var(--muted);
            max-width: 760px;
            line-height: 1.7;
            font-size: 1rem;
        }

        .layout {
            display: grid;
            grid-template-columns: 1fr 1.15fr;
            gap: 24px;
            padding: 16px 0 52px;
            align-items: start;
        }

        .card {
            background: var(--white);
            border-radius: 18px;
            border: 1px solid rgba(31,79,163,0.1);
            box-shadow: var(--shadow);
            padding: 24px;
        }

        .card h2 {
            margin: 0 0 16px;
            font-size: 1.25rem;
            color: #173b79;
        }

        .contact-list {
            display: grid;
            gap: 14px;
        }

        .item {
            background: #f8fbff;
            border: 1px solid #e3ecfb;
            border-radius: 14px;
            padding: 13px 14px;
        }

        .item strong {
            display: block;
            margin-bottom: 4px;
            color: #1a3f80;
        }

        .item span,
        .item a {
            color: #455779;
            text-decoration: none;
            word-break: break-word;
        }

        .item a:hover {
            color: var(--primary);
            text-decoration: underline;
        }

        .alert {
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 16px;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .alert.success {
            background: var(--success-bg);
            color: var(--success-text);
            border: 1px solid #bae6c8;
        }

        .alert.error {
            background: var(--error-bg);
            color: var(--error-text);
            border: 1px solid #ffc9c9;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .field,
        .field-full {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .field-full { grid-column: 1 / -1; }

        label {
            font-weight: 600;
            color: #254775;
            font-size: 0.93rem;
        }

        input,
        textarea {
            border: 1px solid #d0def5;
            border-radius: 12px;
            background: #fdfefe;
            padding: 12px 13px;
            font: inherit;
            color: #1b2c46;
            transition: border-color .18s ease, box-shadow .18s ease;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #2e66cc;
            box-shadow: 0 0 0 3px rgba(46, 102, 204, 0.18);
        }

        textarea {
            resize: vertical;
            min-height: 140px;
        }

        .btn {
            border: none;
            border-radius: 12px;
            padding: 13px 16px;
            background: linear-gradient(135deg, var(--primary), #2f66cb);
            color: var(--white);
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform .14s ease, box-shadow .2s ease;
            box-shadow: 0 10px 20px rgba(31,79,163,0.26);
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 24px rgba(31,79,163,0.34);
        }

        .note {
            margin-top: 12px;
            color: #607096;
            font-size: 0.86rem;
            line-height: 1.5;
        }

        .map-wrap {
            margin-top: 16px;
            border: 1px solid #d8e4fa;
            border-radius: 14px;
            overflow: hidden;
            background: #f7fbff;
        }

        .map-wrap iframe {
            width: 100%;
            height: 240px;
            border: 0;
            display: block;
        }

        .map-actions {
            padding: 10px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            border-top: 1px solid #d8e4fa;
            background: #f2f8ff;
            font-size: 0.86rem;
        }

        .map-actions a {
            color: var(--primary);
            font-weight: 700;
            text-decoration: none;
        }

        .map-actions a:hover {
            text-decoration: underline;
        }

        .footer {
            text-align: center;
            color: #5f6d86;
            font-size: 0.88rem;
            padding: 8px 0 30px;
        }

        @media (prefers-color-scheme: dark) {
            body {
                color: #e9f0ff;
                background:
                    radial-gradient(circle at 15% 20%, rgba(76,125,214,0.22) 0%, transparent 35%),
                    radial-gradient(circle at 85% 10%, rgba(212,165,58,0.20) 0%, transparent 40%),
                    linear-gradient(135deg, #0e1628 0%, #101f3b 45%, #0e1424 100%);
            }

            .navbar {
                background: rgba(12, 21, 39, 0.82);
                border-bottom-color: rgba(135, 170, 233, 0.24);
            }

            .brand { color: #8eb3ff; }
            .nav-link { color: #c3d5ff; }
            .nav-link:hover { background: rgba(142, 179, 255, 0.14); }

            .hero-card,
            .card {
                background: rgba(15, 28, 52, 0.92);
                border-color: rgba(131, 170, 240, 0.25);
                box-shadow: 0 18px 40px rgba(1, 7, 18, 0.45);
            }

            h1,
            .card h2,
            .item strong,
            label {
                color: #dce8ff;
            }

            .hero p,
            .item span,
            .item a,
            .note,
            .footer,
            .map-actions span {
                color: #afc3ea;
            }

            .item {
                background: rgba(21, 39, 68, 0.9);
                border-color: rgba(128, 165, 230, 0.24);
            }

            input,
            textarea {
                background: #101f38;
                border-color: #365a95;
                color: #e6efff;
            }

            .map-wrap {
                border-color: #365a95;
                background: #0f1d34;
            }

            .map-actions {
                border-top-color: #365a95;
                background: #122340;
            }

            .alert.success {
                background: rgba(34, 139, 84, 0.2);
                color: #8de0b3;
                border-color: rgba(108, 212, 152, 0.4);
            }

            .alert.error {
                background: rgba(198, 40, 40, 0.2);
                color: #ffb3b3;
                border-color: rgba(255, 164, 164, 0.45);
            }
        }

        @media (max-width: 900px) {
            .layout { grid-template-columns: 1fr; }
            .hero-card { padding: 24px; }
            .card { padding: 20px; }
        }

        @media (max-width: 640px) {
            .nav-inner { align-items: flex-start; flex-direction: column; gap: 10px; }
            .form-grid { grid-template-columns: 1fr; }
            .hero { padding-top: 28px; }
            .hero-card { border-radius: 16px; }
        }
    </style>
</head>
<body>
    <header class="navbar">
        <div class="container nav-inner">
            <a class="brand" href="index.php">
                <img class="brand-logo" src="assets/img/noteria-logo.png" alt="e-Noteria Logo">
                <span>e-Noteria</span>
            </a>
            <nav class="nav-links" aria-label="Navigimi kryesor">
                <a class="nav-link" href="index.php">Kreu</a>
                <a class="nav-link" href="services.php">Shërbimet</a>
                <a class="nav-link" href="news.php">Lajme</a>
                <a class="nav-link" href="ndihma.php">Ndihma</a>
                <a class="nav-link active" href="contact.php">Kontakti</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <section class="hero">
            <div class="hero-card">
                <span class="eyebrow">Kontaktoni Ekipin e platformës e-Noteria</span>
                <h1>Jemi këtu për t’ju ndihmuar në çdo hap</h1>
                <p>
                    Për pyetje rreth rezervimeve, dokumenteve, pagesave ose mbështetjes teknike,
                    dërgoni kërkesën tuaj dhe ekipi ynë profesional do t’ju përgjigjet sa më shpejt.
                </p>
            </div>
        </section>

        <section class="layout">
            <aside class="card">
                <h2>Informacion Kontakti</h2>
                <div class="contact-list">
                    <div class="item">
                        <strong>Email</strong>
                        <a href="mailto:support@e-noteria.com">support@e-noteria.com</a>
                    </div>
                    <div class="item">
                        <strong>Telefon</strong>
                        <a href="tel:+38344111222">+383 44 111 222</a>
                    </div>
                    <div class="item">
                        <strong>Orari i punës</strong>
                        <span>E Hënë - E Premte, 08:00 - 17:00</span>
                    </div>
                    <div class="item">
                        <strong>Adresa</strong>
                        <span>Prishtinë, Kosovë</span>
                    </div>
                </div>

                <div class="map-wrap" aria-label="Harta e lokacionit">
                    <iframe
                        title="Noteria në Prishtinë"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        src="https://www.google.com/maps?q=Prishtin%C3%AB%2C%20Kosov%C3%AB&z=14&output=embed">
                    </iframe>
                    <div class="map-actions">
                        <span>Lokacioni ynë në hartë</span>
                        <a href="https://maps.google.com/?q=Prishtin%C3%AB%2C%20Kosov%C3%AB" target="_blank" rel="noopener noreferrer">Hape në Google Maps</a>
                    </div>
                </div>
            </aside>

            <section class="card">
                <h2>Dërgo Mesazh</h2>

                <?php if ($success): ?>
                    <div class="alert success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="form-grid">
                        <div class="field">
                            <label for="full_name">Emri i plotë *</label>
                            <input id="full_name" name="full_name" type="text" maxlength="120" required value="<?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="field">
                            <label for="email">Email *</label>
                            <input id="email" name="email" type="email" maxlength="140" required value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="field">
                            <label for="phone">Telefoni</label>
                            <input id="phone" name="phone" type="text" maxlength="40" value="<?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="field">
                            <label for="subject">Subjekti *</label>
                            <input id="subject" name="subject" type="text" maxlength="150" required value="<?php echo htmlspecialchars($subject, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="field-full">
                            <label for="message">Mesazhi *</label>
                            <textarea id="message" name="message" maxlength="3000" required><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>

                        <div class="field-full">
                            <button type="submit" class="btn">Dërgo Mesazhin</button>
                            <p class="note">Duke dërguar formularin, ju pranoni që të dhënat të përdoren vetëm për trajtimin e kërkesës suaj.</p>
                        </div>
                    </div>
                </form>
            </section>
        </section>

        <div class="footer">© <?php echo date('Y'); ?> e-Noteria. Të gjitha të drejtat e rezervuara.</div>
    </main>
</body>
</html>
