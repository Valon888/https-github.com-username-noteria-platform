
<?php
// --- MASA TË LARTA SIGURIE ---
// 1. Session hardening
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
ini_set('session.use_strict_mode', 1);
if (session_status() === PHP_SESSION_NONE) session_start();
// 2. CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// 3. XSS & Clickjacking protection headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; connect-src \'self\' https://cdn.jsdelivr.net; script-src \'self\' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; style-src \'self\' https://cdn.jsdelivr.net; img-src \'self\' data: https://images.unsplash.com; font-src \'self\' https://cdn.jsdelivr.net;');
header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');
// 4. Cloudflare protection info (user must enable on DNS)
// 5. Session timeout (30min)
if (!isset($_SESSION['LAST_ACTIVITY'])) {
    $_SESSION['LAST_ACTIVITY'] = time();
} elseif (time() - $_SESSION['LAST_ACTIVITY'] > 1800) {
    session_unset(); session_destroy(); header('Location: login.php?timeout=1'); exit();
} else {
    $_SESSION['LAST_ACTIVITY'] = time();
}

require_once 'cases_functions.php';
require_once 'confidb.php';

// --- Import all reservations as cases if not already imported ---
if (isset($_GET['import_reservations']) && $_GET['import_reservations'] === '1') {
    $imported = 0;
    $skipped = 0;
    $reservations = $pdo->query("SELECT * FROM reservations")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($reservations as $r) {
        // Use reservation id as unique case_number if possible
        $case_number = 'RES-' . $r['id'];
        // Check if already imported
        $exists = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE case_number = ?");
        $exists->execute([$case_number]);
        if ($exists->fetchColumn() > 0) { $skipped++; continue; }
        $title = $r['service'] ?? 'Rezervim Noterial';
        $description = 'Automatikisht importuar nga rezervimet. Data: ' . ($r['date'] ?? '') . ', Ora: ' . ($r['time'] ?? '');
        $client_id = $r['user_id'] ?? 0;
        $opened_by = $r['zyra_id'] ?? 1;
        shtoLende($pdo, $case_number, $title, $description, (int)$client_id, (int)$opened_by);
        $imported++;
    }
    $message = '<div class="alert alert-success">Importimi përfundoi: ' . $imported . ' lëndë të reja u shtuan, ' . $skipped . ' u anashkaluan.</div>';
}

// Kërkim ose listim i të gjitha lëndëve
$search_case_number = $_GET['case_number'] ?? '';
$search_client_id = $_GET['client_id'] ?? '';
$cases = [];
if ($search_case_number || $search_client_id) {
    $cases = kerkoLende($pdo, $search_case_number, (int)$search_client_id);
} else {
    $stmt = $pdo->query("SELECT * FROM cases ORDER BY opened_at DESC LIMIT 50");
    $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Shto lëndë të re
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_case'])) {
    $case_number = trim($_POST['case_number']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $client_id = (int)$_POST['client_id'];
    $opened_by = 1; // Ndrysho sipas user-it të kyçur
    if ($case_number && $title && $client_id) {
        $id = shtoLende($pdo, $case_number, $title, $description, $client_id, $opened_by);
        $message = '<div class="alert alert-success">Lënda u shtua me sukses!</div>';
        header('Location: cases_dashboard.php');
        exit;
    } else {
        $message = '<div class="alert alert-danger">Ju lutem plotësoni të gjitha fushat e detyrueshme.</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arkiva e Lëndëve | e-Noteria</title>
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <link href="cases_dashboard.custom.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-card">
        <h2 class="mb-4 text-primary"><i class="fas fa-archive me-2"></i>Arkiva e Lëndëve</h2>
        <div class="alert alert-info security-info">
            <b>Siguria maksimale:</b> Kjo faqe është e mbrojtur me masa të avancuara të sigurisë (session hardening, CSRF, XSS, Clickjacking, HSTS, CSP, Cloudflare).<br>
            <span class="security-note">Për mbrojtje të plotë, sigurohuni që domeni juaj të përdorë <b>Cloudflare</b> me Firewall, DDoS, Bot Management dhe SSL "Full (Strict)".</span>
        </div>
        <?= $message ?>
        <div class="mb-3">
            <a href="cases_dashboard.php?import_reservations=1" class="btn btn-warning">Importo të gjitha terminet ekzistuese si lëndë</a>
        </div>
        <div class="form-section mb-4">
            <form class="row g-3" method="get">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="case_number" placeholder="Numri i lëndës" value="<?= htmlspecialchars($search_case_number) ?>">
                </div>
                <div class="col-md-4">
                    <input type="number" class="form-control" name="client_id" placeholder="ID e klientit" value="<?= htmlspecialchars($search_client_id) ?>">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100" type="submit"><i class="fas fa-search me-1"></i>Kërko</button>
                </div>
            </form>
        </div>
        <div class="form-section mb-4">
            <h5 class="mb-3">Shto Lëndë të Re</h5>
            <form class="row g-3" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="col-md-3">
                    <input type="text" class="form-control" name="case_number" placeholder="Numri unik i lëndës" required>
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control" name="title" placeholder="Titulli i lëndës" required>
                </div>
                <div class="col-md-3">
                    <input type="number" class="form-control" name="client_id" placeholder="ID e klientit" required>
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control" name="description" placeholder="Përshkrimi (opsionale)">
                </div>
                <div class="col-12 text-end">
                    <button class="btn btn-success" type="submit" name="add_case"><i class="fas fa-plus me-1"></i>Shto Lëndë</button>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover case-table align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Numri</th>
                        <th>Titulli</th>
                        <th>Klienti</th>
                        <th>Statusi</th>
                        <th>Hapur më</th>
                        <th>Dokumente</th>
                        <th>Veprime</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cases as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['id']) ?></td>
                        <td><?= htmlspecialchars($c['case_number']) ?></td>
                        <td><?= htmlspecialchars($c['title']) ?></td>
                        <td><?= htmlspecialchars($c['client_id']) ?></td>
                        <td><?= htmlspecialchars($c['status']) ?></td>
                        <td><?= htmlspecialchars($c['opened_at']) ?></td>
                        <td>
                            <?php
                            $docs = listDokumenteLende($pdo, $c['id']);
                            if (count($docs) > 0) {
                                echo '<span class="badge bg-success">' . count($docs) . ' dokumente</span><br>';
                                $latest = $docs[0];
                                echo '<a href="' . htmlspecialchars($latest['file_path']) . '" target="_blank">' . htmlspecialchars($latest['file_name']) . '</a>';
                            } else {
                                echo '<span class="text-muted">Asnjë dokument</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <a href="case_view.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-folder-open"></i> Shiko</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- FontAwesome now loaded from jsdelivr above -->
</body>
</html>

