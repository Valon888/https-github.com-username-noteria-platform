
<?php
// jobs.php - Menaxhimi i konkurseve për punësim dhe aplikimet
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
session_start();
require_once 'config.php';

// Sigurohu që përdoruesi është i kyçur dhe ka zyra_id në sesion
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Nëse përdoruesi është noter por nuk ka të lidhur zyrë, shfaq mesazh
$no_zyra_error = null;
if (!isset($_SESSION['zyra_id']) || empty($_SESSION['zyra_id'])) {
    $no_zyra_error = 'Nuk jeni të lidhur me asnjë zyrë noteriale. Ju lutemi kontaktoni administratorin ose regjistroni zyrën tuaj.';
}

// Krijo tabelat nëse nuk ekzistojnë
$pdo->exec("CREATE TABLE IF NOT EXISTS jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zyra_id INT NOT NULL,
    titulli VARCHAR(255) NOT NULL,
    pershkrimi TEXT NOT NULL,
    afati DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (zyra_id) REFERENCES zyrat(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("CREATE TABLE IF NOT EXISTS job_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    emri VARCHAR(100) NOT NULL,
    mbiemri VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    telefoni VARCHAR(20),
    cv_path VARCHAR(255),
    mesazhi TEXT,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Shto konkurs të ri (vetëm për noterë të kyçur)
$konkurs_shtuar = false;
$error = null;
if (isset($_POST['shto_konkurs']) && isset($_SESSION['zyra_id'])) {
    $titulli = trim($_POST['titulli']);
    $pershkrimi = trim($_POST['pershkrimi']);
    $afati = $_POST['afati'];
    $zyra_id = $_SESSION['zyra_id'];
    if ($titulli && $pershkrimi && $afati) {
        $stmt = $pdo->prepare("INSERT INTO jobs (zyra_id, titulli, pershkrimi, afati) VALUES (?, ?, ?, ?)");
        $konkurs_shtuar = $stmt->execute([$zyra_id, $titulli, $pershkrimi, $afati]);
    } else {
        $error = "Ju lutemi plotësoni të gjitha fushat e konkursit.";
    }
}

// Aplikim për punë
$aplikim_shtuar = false;
if (isset($_POST['apliko']) && isset($_POST['job_id'])) {
    $job_id = intval($_POST['job_id']);
    $emri = trim($_POST['emri']);
    $mbiemri = trim($_POST['mbiemri']);
    $email = trim($_POST['email']);
    $telefoni = trim($_POST['telefoni']);
    $mesazhi = trim($_POST['mesazhi']);
    $cv_path = null;
    if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('cv_') . '.' . $ext;
        $dest = __DIR__ . '/uploads/' . $filename;
        if (move_uploaded_file($_FILES['cv']['tmp_name'], $dest)) {
            $cv_path = 'uploads/' . $filename;
        }
    }
    if ($emri && $mbiemri && $email) {
        $stmt = $pdo->prepare("INSERT INTO job_applications (job_id, emri, mbiemri, email, telefoni, cv_path, mesazhi) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $aplikim_shtuar = $stmt->execute([$job_id, $emri, $mbiemri, $email, $telefoni, $cv_path, $mesazhi]);
    } else {
        $error = "Ju lutemi plotësoni të gjitha fushat e detyrueshme të aplikimit.";
    }
}

// Merr të gjitha konkurset aktive
$konkurset = $pdo->query("SELECT j.*, z.emri AS zyra_emri, z.qyteti FROM jobs j JOIN zyrat z ON j.zyra_id = z.id WHERE j.afati >= CURDATE() ORDER BY j.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mundësi Punësimi | Noteria</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Montserrat', sans-serif; background: #f8fafc; margin: 0; }
        .container { max-width: 900px; margin: 40px auto; background: #fff; border-radius: 12px; box-shadow: 0 8px 32px rgba(44,108,223,0.10); padding: 32px; }
        h1 { color: #2d6cdf; text-align: center; margin-bottom: 30px; }
        .success { color: #388e3c; background: #eafaf1; border-left: 4px solid #388e3c; border-radius: 4px; padding: 12px; margin-bottom: 18px; }
        .error { color: #d32f2f; background: #ffeaea; border-left: 4px solid #d32f2f; border-radius: 4px; padding: 12px; margin-bottom: 18px; }
        .konkurs-form, .aplikim-form { background: #f4f7fb; border-radius: 8px; padding: 22px; margin-bottom: 30px; }
        .form-row { display: flex; gap: 18px; }
        .form-group { flex: 1; margin-bottom: 16px; position: relative; }
        label { font-weight: 600; color: #333; margin-bottom: 7px; display: block; }
        input, textarea, select { width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 1rem; background: #fff; }
        input[type="file"] { padding: 0; }
        button { background: #2d6cdf; color: #fff; border: none; border-radius: 6px; padding: 12px 28px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        button:hover { background: #184fa3; }
        .jobs-list { margin-top: 30px; }
        .job-card { background: #f8fafd; border: 1px solid #e2eafc; border-radius: 10px; padding: 22px 24px; margin-bottom: 22px; box-shadow: 0 2px 8px rgba(44,108,223,0.06); }
        .job-title { color: #184fa3; font-size: 1.2rem; font-weight: 700; margin-bottom: 6px; }
        .job-meta { color: #555; font-size: 0.97rem; margin-bottom: 10px; }
        .job-desc { color: #333; margin-bottom: 12px; }
        .job-deadline { color: #d32f2f; font-size: 0.95rem; margin-bottom: 10px; }
        .apliko-btn { background: #388e3c; margin-top: 8px; }
        .apliko-btn:hover { background: #256029; }
        .aplikim-form { display: none; margin-top: 18px; }
        .show { display: block !important; }
        @media (max-width: 700px) {
            .container { padding: 12px; }
            .form-row { flex-direction: column; gap: 0; }
        }
    </style>
    <script>
    function toggleAplikimForm(jobId) {
        document.querySelectorAll('.aplikim-form').forEach(f => f.classList.remove('show'));
        document.getElementById('aplikim-'+jobId).classList.toggle('show');
    }
    </script>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-briefcase"></i> Mundësi Punësimi</h1>
        <?php if ($konkurs_shtuar): ?>
            <div class="success"><i class="fas fa-check-circle"></i> Konkursi u publikua me sukses!</div>
        <?php endif; ?>
        <?php if ($aplikim_shtuar): ?>
            <div class="success"><i class="fas fa-check-circle"></i> Aplikimi u dërgua me sukses!</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>


        <?php if ($no_zyra_error): ?>
            <div class="error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($no_zyra_error); ?></div>
        <?php else: ?>
            <form method="POST" class="konkurs-form">
                <h2><i class="fas fa-plus-circle"></i> Shto Konkurs të Ri</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label for="titulli">Titulli i Pozitës</label>
                        <input type="text" name="titulli" id="titulli" required>
                    </div>
                    <div class="form-group">
                        <label for="afati">Afati i Aplikimit</label>
                        <input type="date" name="afati" id="afati" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="pershkrimi">Përshkrimi i Pozitës</label>
                    <textarea name="pershkrimi" id="pershkrimi" rows="4" required></textarea>
                </div>
                <button type="submit" name="shto_konkurs"><i class="fas fa-upload"></i> Publiko Konkursin</button>
            </form>
        <?php endif; ?>

        <div class="jobs-list">
            <h2><i class="fas fa-list"></i> Konkurset Aktive</h2>
            <?php if (count($konkurset) === 0): ?>
                <div style="color:#888;">Nuk ka konkurse të hapura aktualisht.</div>
            <?php endif; ?>
            <?php foreach ($konkurset as $konkurs): ?>
                <div class="job-card">
                    <div class="job-title"><?php echo htmlspecialchars($konkurs['titulli']); ?></div>
                    <div class="job-meta">
                        <i class="fas fa-building"></i> <?php echo htmlspecialchars($konkurs['zyra_emri']); ?>,
                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($konkurs['qyteti']); ?>
                    </div>
                    <div class="job-desc"><?php echo nl2br(htmlspecialchars($konkurs['pershkrimi'])); ?></div>
                    <div class="job-deadline"><i class="fas fa-calendar-alt"></i> Afati: <?php echo htmlspecialchars($konkurs['afati']); ?></div>
                    <button class="apliko-btn" onclick="toggleAplikimForm(<?php echo $konkurs['id']; ?>)"><i class="fas fa-paper-plane"></i> Apliko</button>
                    <form method="POST" enctype="multipart/form-data" class="aplikim-form" id="aplikim-<?php echo $konkurs['id']; ?>">
                        <input type="hidden" name="job_id" value="<?php echo $konkurs['id']; ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="emri">Emri</label>
                                <input type="text" name="emri" required>
                            </div>
                            <div class="form-group">
                                <label for="mbiemri">Mbiemri</label>
                                <input type="text" name="mbiemri" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="telefoni">Telefoni</label>
                                <input type="text" name="telefoni">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="cv">CV (e detyrueshme, PDF/Word)</label>
                            <input type="file" name="cv" accept="application/pdf, application/msword" required>
                        </div>
                        <div class="form-group">
                            <label for="mesazhi">Mesazhi (opsionale)</label>
                            <textarea name="mesazhi" rows="3"></textarea>
                        </div>
                        <button type="submit" name="apliko"><i class="fas fa-paper-plane"></i> Dërgo Aplikimin</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
