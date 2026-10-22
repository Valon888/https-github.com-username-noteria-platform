<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';
checkSessionTimeout(getenv('SESSION_TIMEOUT') ?: 1800, 'login.php?message=session_expired');
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

require_once 'confidb.php';
if (!isset($pdo) || !$pdo) {
    die("<div style='color:red;text-align:center;margin-top:60px;'>Gabim në lidhjen me databazën.</div>");
}

$user_id = $_SESSION['user_id'];
$roli    = $_SESSION['roli'] ?? null;

$stmtU = $pdo->prepare("SELECT emri, mbiemri FROM users WHERE id = ?");
$stmtU->execute([$user_id]);
$user = $stmtU->fetch();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Pagination & Filter ───────────────────────────────
$filter  = in_array($_GET['filter'] ?? '', ['completed', 'pending']) ? $_GET['filter'] : 'all';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

// ── Build base query ──────────────────────────────────
if ($roli === 'admin') {
    $whereClause = '1=1';
    $bindParams  = [];
} else {
    $whereClause = 'p.user_id = ?';
    $bindParams  = [$user_id];
}
if ($filter === 'completed') { $whereClause .= " AND p.status = 'completed'"; }
if ($filter === 'pending')   { $whereClause .= " AND p.status = 'pending'"; }

try {
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM payments p WHERE $whereClause");
    $stmtCount->execute($bindParams);
    $total = (int)$stmtCount->fetchColumn();

    $stmtP = $pdo->prepare("
        SELECT
            p.id, p.amount, p.payment_method, p.status, p.transaction_id, p.created_at,
            p.reservation_id,
            r.service, r.date AS res_date, r.time AS res_time,
            u.emri, u.mbiemri, u.email AS user_email,
            z.emri AS zyra_emri
        FROM payments p
        LEFT JOIN reservations r ON p.reservation_id = r.id
        LEFT JOIN users u ON u.id = COALESCE(p.user_id, r.user_id)
        LEFT JOIN zyrat z ON r.zyra_id = z.id
        WHERE $whereClause
        ORDER BY p.created_at DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmtP->execute($bindParams);
    $payments = $stmtP->fetchAll();
} catch (PDOException $e) {
    error_log("invoice_list error: " . $e->getMessage());
    $payments = [];
    $total    = 0;
}

$totalPages = max(1, (int)ceil($total / $perPage));

// ── Stats ─────────────────────────────────────────────
$statsWhere  = $roli === 'admin' ? '1=1' : 'user_id = ?';
$statsParams = $roli === 'admin' ? [] : [$user_id];
try {
    $s = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as paid, SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending_count, SUM(CASE WHEN status='completed' THEN amount ELSE 0 END) as total_amount FROM payments WHERE $statsWhere");
    $s->execute($statsParams);
    $stats = $s->fetch();
} catch (PDOException $e) {
    $stats = ['total' => 0, 'paid' => 0, 'pending_count' => 0, 'total_amount' => 0];
}

// ── Navbar notifications ──────────────────────────────
$navNotifs   = [];
$unreadCount = 0;
try {
    $sn = $pdo->prepare("SELECT id, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $sn->execute([$user_id]);
    $navNotifs = $sn->fetchAll();
    $su = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $su->execute([$user_id]);
    $unreadCount = (int)$su->fetchColumn();
} catch (PDOException $e) { /* skip */ }

function paymentStatusLabel(string $status): array {
    return match($status) {
        'completed' => ['bg' => '#d4edda', 'color' => '#155724', 'icon' => 'fa-check-circle',    'text' => 'E Paguar'],
        'failed'    => ['bg' => '#f8d7da', 'color' => '#721c24', 'icon' => 'fa-times-circle',    'text' => 'Dështoi'],
        'cancelled' => ['bg' => '#f5e6ff', 'color' => '#5a0099', 'icon' => 'fa-ban',             'text' => 'Anuluar'],
        default     => ['bg' => '#fff3cd', 'color' => '#856404', 'icon' => 'fa-clock',           'text' => 'Në Pritje'],
    };
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Faturat | e-Noteria</title>
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --gov-blue: #003366;
            --gov-gold: #c49a6c;
            --sidebar-w: 260px;
            --navbar-h: 64px;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; color: #333; margin: 0; }
        h1,h2,h3,h4,h5,h6 { font-family: 'Merriweather', serif; }

        /* ── NAVBAR ── */
        .top-navbar { position:fixed; top:0; left:0; right:0; z-index:1030; height:var(--navbar-h); background:var(--gov-blue); border-bottom:3px solid var(--gov-gold); display:flex; align-items:center; padding:0 20px; box-shadow:0 2px 16px rgba(0,0,0,.25); }
        .brand { color:#fff; font-family:'Merriweather',serif; font-size:1.3rem; font-weight:700; text-decoration:none; display:flex; align-items:center; gap:10px; flex-shrink:0; }
        .brand img { height:36px; width:auto; border-radius:4px; }
        .brand-sub { font-size:.72rem; font-weight:300; opacity:.75; letter-spacing:.5px; display:block; }
        .nav-right { margin-left:auto; display:flex; align-items:center; gap:10px; }
        .nav-avatar { width:36px; height:36px; border-radius:50%; background:var(--gov-gold); display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.9rem; color:var(--gov-blue); flex-shrink:0; }
        .nav-user-name { font-size:.85rem; color:#fff; font-weight:600; }
        .nav-user-role { font-size:.7rem; color:rgba(255,255,255,.6); }

        /* ── SIDEBAR ── */
        .sidebar { position:fixed; left:0; top:var(--navbar-h); bottom:0; width:var(--sidebar-w); background:#fff; border-right:1px solid #e0e4ea; overflow-y:auto; z-index:1020; box-shadow:2px 0 8px rgba(0,0,0,.04); transition:transform .28s ease; }
        .sidebar-section { padding:16px 20px 4px; font-size:.7rem; font-weight:700; color:#b0b5c0; text-transform:uppercase; letter-spacing:1.2px; }
        .sidebar-link { display:flex; align-items:center; gap:11px; padding:10px 20px; color:#555; font-size:.88rem; text-decoration:none; border-left:3px solid transparent; transition:all .18s; }
        .sidebar-link i { width:18px; text-align:center; color:#bbb; font-size:.9rem; transition:color .18s; }
        .sidebar-link:hover { background:#f3f6ff; color:var(--gov-blue); border-left-color:var(--gov-blue); }
        .sidebar-link:hover i { color:var(--gov-blue); }
        .sidebar-link.active { background:#eef2ff; color:var(--gov-blue); border-left-color:var(--gov-blue); font-weight:600; }
        .sidebar-link.active i { color:var(--gov-blue); }
        .sidebar-link.link-danger { color:#c0392b; }
        .sidebar-link.link-danger i { color:#e74c3c; }
        .sidebar-link.link-danger:hover { background:#fff0f0; border-left-color:#e74c3c; }

        /* ── MAIN ── */
        .main-content { margin-left:var(--sidebar-w); margin-top:var(--navbar-h); padding:28px; min-height:calc(100vh - var(--navbar-h)); }

        /* ── PAGE HEADER ── */
        .page-header { background:linear-gradient(135deg,var(--gov-blue) 0%,#00408c 100%); border-radius:14px; padding:28px 32px; color:white; margin-bottom:24px; position:relative; overflow:hidden; }
        .page-header::after  { content:''; position:absolute; right:-40px; top:-40px; width:220px; height:220px; border-radius:50%; background:rgba(255,255,255,.05); }
        .page-header::before { content:''; position:absolute; right:60px; bottom:-60px; width:160px; height:160px; border-radius:50%; background:rgba(196,154,108,.12); }
        .page-header h1 { color:white; font-size:1.6rem; margin-bottom:4px; position:relative; }
        .page-header p  { color:rgba(255,255,255,.7); margin:0; font-size:.88rem; position:relative; }

        /* ── SUMMARY PILLS ── */
        .summary-banner { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
        .summary-pill { flex:1; min-width:130px; padding:16px 18px; border-radius:12px; display:flex; align-items:center; gap:12px; box-shadow:0 2px 6px rgba(0,0,0,.06); }
        .summary-pill .sp-icon { width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
        .summary-pill .sp-val { font-size:1.6rem; font-weight:700; font-family:'Inter',sans-serif; line-height:1; }
        .summary-pill .sp-lbl { font-size:.74rem; color:#888; margin-top:2px; }

        /* ── FILTER TABS ── */
        .inv-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:16px; }
        .inv-tab { display:inline-flex; align-items:center; gap:7px; padding:8px 18px; border-radius:30px; font-size:.84rem; font-weight:600; text-decoration:none; transition:all .18s; border:2px solid #e0e4ea; background:#fff; color:#666; }
        .inv-tab:hover { border-color:var(--gov-blue); color:var(--gov-blue); background:#eef2ff; }
        .inv-tab.active { background:var(--gov-blue); border-color:var(--gov-blue); color:#fff; }
        .inv-tab .tab-count { background:rgba(255,255,255,.25); color:#fff; border-radius:30px; padding:1px 8px; font-size:.72rem; }
        .inv-tab:not(.active) .tab-count { background:#f0f2f5; color:#666; }

        /* ── INVOICE CARD ── */
        .inv-card { background:#fff; border-radius:14px; box-shadow:0 2px 8px rgba(0,0,0,.06); border:1px solid #e9ecef; overflow:hidden; }

        .inv-row { display:flex; align-items:center; gap:14px; padding:16px 20px; border-bottom:1px solid #f5f6f8; transition:background .14s; text-decoration:none; color:inherit; }
        .inv-row:last-child { border-bottom:none; }
        .inv-row:hover { background:#f7f9ff; }
        .inv-row-compact { display:none; }

        .inv-num { font-weight:700; font-size:.82rem; color:var(--gov-blue); min-width:70px; }
        .inv-service { flex:1; min-width:0; }
        .inv-service-name { font-weight:600; font-size:.9rem; color:#222; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .inv-service-sub  { font-size:.75rem; color:#aaa; margin-top:2px; }
        .inv-method { font-size:.8rem; color:#666; min-width:120px; display:flex; align-items:center; gap:5px; }
        .inv-date   { font-size:.78rem; color:#aaa; min-width:90px; text-align:right; }
        .inv-amount { font-weight:700; font-size:1rem; color:var(--gov-blue); min-width:90px; text-align:right; white-space:nowrap; }
        .inv-status { min-width:110px; text-align:center; }
        .inv-actions { display:flex; gap:6px; flex-shrink:0; }

        .status-pill { display:inline-flex; align-items:center; gap:5px; font-size:.72rem; font-weight:700; padding:4px 10px; border-radius:20px; }

        .btn-view { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; background:var(--gov-blue); color:#fff; border:none; border-radius:8px; font-size:.8rem; font-weight:600; cursor:pointer; text-decoration:none; transition:all .18s; white-space:nowrap; }
        .btn-view:hover { background:#002244; color:#fff; }
        .btn-view-outline { background:#fff; color:var(--gov-blue); border:1.5px solid var(--gov-blue); }
        .btn-view-outline:hover { background:var(--gov-blue); color:#fff; }

        /* ── EMPTY STATE ── */
        .empty-state { text-align:center; padding:60px 20px; }
        .empty-icon { width:90px; height:90px; border-radius:50%; background:#eef2ff; display:flex; align-items:center; justify-content:center; font-size:2.4rem; color:var(--gov-blue); margin:0 auto 20px; }

        /* ── PAGINATION ── */
        .pagination-wrap { display:flex; justify-content:center; gap:6px; padding-top:20px; flex-wrap:wrap; }
        .page-btn { display:inline-flex; align-items:center; justify-content:center; width:38px; height:38px; border-radius:8px; font-size:.85rem; font-weight:600; text-decoration:none; border:1.5px solid #e0e4ea; color:#555; background:#fff; transition:all .16s; }
        .page-btn:hover { border-color:var(--gov-blue); color:var(--gov-blue); background:#eef2ff; }
        .page-btn.active { background:var(--gov-blue); border-color:var(--gov-blue); color:#fff; }
        .page-btn.disabled { opacity:.4; pointer-events:none; }

        /* ── SECTION TITLE ── */
        .section-title { font-size:1rem; color:var(--gov-blue); font-family:'Merriweather',serif; margin-bottom:16px; padding-bottom:8px; border-bottom:3px solid var(--gov-gold); display:inline-block; }

        /* ── MOBILE ── */
        @media (max-width:991.98px) {
            .sidebar { transform:translateX(-100%); }
            .sidebar.open { transform:translateX(0); box-shadow:4px 0 20px rgba(0,0,0,.15); }
            .main-content { margin-left:0; }
        }
        @media (max-width:767.98px) {
            .inv-method, .inv-date { display:none; }
            .main-content { padding:14px; }
        }
        @media (max-width:575.98px) {
            .page-header { padding:20px 18px; }
            .page-header h1 { font-size:1.25rem; }
            .inv-row { flex-wrap:wrap; }
        }
    </style>
</head>
<body>

<!-- ╔══════════ TOP NAVBAR ══════════ -->
<nav class="top-navbar">
    <button class="btn text-white d-lg-none me-2 p-1" id="sidebar-toggle" style="background:none;border:none;" aria-label="Menu">
        <i class="fas fa-bars fa-lg"></i>
    </button>
    <a href="dashboard.php" class="brand">
        <img src="images/pngwing.com%20(1).png" alt="Logo" onerror="this.style.display='none'">
        <div>
            <span>e-Noteria</span>
            <span class="brand-sub">Platformë SaaS Noteriale</span>
        </div>
    </a>
    <div class="nav-right">
        <!-- Notifications -->
        <div class="dropdown">
            <button class="btn text-white position-relative p-2" style="background:none;border:none;" data-bs-toggle="dropdown" aria-label="Njoftimet">
                <i class="fas fa-bell"></i>
                <?php if ($unreadCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.55rem;padding:3px 5px;"><?php echo $unreadCount; ?></span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="min-width:300px;border-radius:12px;">
                <li><h6 class="dropdown-header fw-bold" style="color:var(--gov-blue);">Njoftimet</h6></li>
                <?php if ($navNotifs): foreach ($navNotifs as $n): ?>
                <li>
                    <a class="dropdown-item py-2<?php echo (!$n['is_read']) ? ' fw-semibold' : ''; ?>" href="notification.php">
                        <div class="small"><?php echo htmlspecialchars($n['message']); ?></div>
                        <div class="text-muted" style="font-size:.7rem;"><?php echo date('d.m.Y H:i', strtotime($n['created_at'])); ?></div>
                    </a>
                </li>
                <?php endforeach; else: ?>
                <li><span class="dropdown-item text-muted small">Nuk ka njoftime.</span></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider my-1"></li>
                <li><a class="dropdown-item text-center small fw-bold" style="color:var(--gov-blue);" href="notification.php">Shiko të gjitha</a></li>
            </ul>
        </div>
        <!-- User -->
        <div class="dropdown">
            <button class="btn d-flex align-items-center gap-2 px-2" style="background:none;border:none;" data-bs-toggle="dropdown">
                <div class="nav-avatar"><?php echo strtoupper(substr($user['emri'] ?? 'U', 0, 1)); ?></div>
                <div class="d-none d-md-block text-start">
                    <div class="nav-user-name"><?php echo htmlspecialchars(($user['emri'] ?? '') . ' ' . ($user['mbiemri'] ?? '')); ?></div>
                    <div class="nav-user-role"><?php echo $roli === 'admin' ? 'Administrator' : 'Klient'; ?></div>
                </div>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius:12px;min-width:180px;">
                <li><a class="dropdown-item py-2" href="dashboard.php#profili"><i class="fas fa-user-cog me-2 text-muted"></i>Profili Im</a></li>
                <li><hr class="dropdown-divider my-1"></li>
                <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Shkyçu</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- ╔══════════ SIDEBAR ══════════ -->
<aside class="sidebar" id="sidebar">
    <nav style="padding:8px 0 24px;">
        <div class="sidebar-section">Kryesore</div>
        <a href="dashboard.php#overview"  class="sidebar-link"><i class="fas fa-th-large"></i>Pasqyra</a>
        <a href="reservation.php"         class="sidebar-link"><i class="fas fa-calendar-plus"></i>Rezervo Termin</a>
        <a href="dashboard.php#terminet"  class="sidebar-link"><i class="fas fa-calendar-check"></i>Terminet e Mia</a>

        <div class="sidebar-section">Shërbime</div>
        <a href="invoice_list.php"        class="sidebar-link active"><i class="fas fa-file-invoice"></i>Faturat</a>
        <a href="dashboard.php#mesazhe"   class="sidebar-link"><i class="fas fa-comments"></i>Mesazhe</a>
        <a href="notification.php"        class="sidebar-link">
            <i class="fas fa-bell"></i>Njoftimet
            <?php if ($unreadCount > 0): ?>
            <span class="ms-auto badge rounded-pill" style="background:var(--gov-blue);color:#fff;font-size:.65rem;"><?php echo $unreadCount; ?></span>
            <?php endif; ?>
        </a>

        <?php if ($roli === 'admin'): ?>
        <div class="sidebar-section">Administrim</div>
        <a href="dashboard.php#admin-terminet" class="sidebar-link"><i class="fas fa-calendar-alt"></i>Të gjitha Terminet</a>
        <a href="dashboard.php#faturat-admin"  class="sidebar-link"><i class="fas fa-file-alt"></i>Faturat Fiskale</a>
        <a href="dashboard.php#statistikat"    class="sidebar-link"><i class="fas fa-chart-bar"></i>Statistikat</a>
        <a href="dashboard.php#kalendar"       class="sidebar-link"><i class="fas fa-calendar"></i>Kalendari</a>
        <?php endif; ?>

        <div class="sidebar-section">Llogaria</div>
        <a href="dashboard.php#profili"   class="sidebar-link"><i class="fas fa-user-cog"></i>Profili Im</a>
        <a href="dashboard.php#lajme"     class="sidebar-link"><i class="fas fa-newspaper"></i>Lajme</a>
        <a href="dashboard.php#video-kons" class="sidebar-link"><i class="fas fa-video"></i>Video Konsultim</a>
        <a href="logout.php"              class="sidebar-link link-danger"><i class="fas fa-sign-out-alt"></i>Shkyçu</a>
    </nav>
</aside>
<div id="sidebar-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1015;"></div>

<!-- ╔══════════ MAIN CONTENT ══════════ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col">
                <h1><i class="fas fa-file-invoice me-2" style="opacity:.8;"></i>Faturat & Pagesat</h1>
                <p>
                    <i class="fas fa-user me-1"></i><?php echo htmlspecialchars(($user['emri'] ?? '') . ' ' . ($user['mbiemri'] ?? '')); ?>
                    &bull; Historia e plotë e transaksioneve tuaja
                </p>
            </div>
            <div class="col-auto d-none d-sm-block" style="position:relative;z-index:1;">
                <a href="reservation.php" class="btn btn-warning fw-bold shadow px-4">
                    <i class="fas fa-calendar-plus me-2"></i>Rezervo Termin
                </a>
            </div>
        </div>
    </div>

    <!-- Summary pills -->
    <div class="summary-banner">
        <div class="summary-pill" style="background:#dce8ff;">
            <div class="sp-icon" style="background:#c3d8ff;color:var(--gov-blue);"><i class="fas fa-receipt"></i></div>
            <div>
                <div class="sp-val" style="color:var(--gov-blue);"><?php echo (int)$stats['total']; ?></div>
                <div class="sp-lbl">Gjithsej Transaksione</div>
            </div>
        </div>
        <div class="summary-pill" style="background:#d4edda;">
            <div class="sp-icon" style="background:#b2dfdb;color:#1a5c35;"><i class="fas fa-check-circle"></i></div>
            <div>
                <div class="sp-val" style="color:#1a5c35;"><?php echo (int)$stats['paid']; ?></div>
                <div class="sp-lbl">Pagesat e Kryera</div>
            </div>
        </div>
        <div class="summary-pill" style="background:#fff3cd;">
            <div class="sp-icon" style="background:#ffe69c;color:#856404;"><i class="fas fa-clock"></i></div>
            <div>
                <div class="sp-val" style="color:#856404;"><?php echo (int)$stats['pending_count']; ?></div>
                <div class="sp-lbl">Në Pritje</div>
            </div>
        </div>
        <div class="summary-pill" style="background:#fff8ee;">
            <div class="sp-icon" style="background:#ffdeb3;color:#8a6500;"><i class="fas fa-euro-sign"></i></div>
            <div>
                <div class="sp-val" style="color:#8a6500;">€<?php echo number_format((float)$stats['total_amount'], 0); ?></div>
                <div class="sp-lbl">Totali i Paguar</div>
            </div>
        </div>
    </div>

    <!-- Filter tabs -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
        <div class="inv-tabs">
            <?php
            $tabs = [
                'all'       => ['label' => 'Të gjitha', 'icon' => 'fa-list'],
                'completed' => ['label' => 'Të kryera', 'icon' => 'fa-check-circle'],
                'pending'   => ['label' => 'Në pritje',  'icon' => 'fa-clock'],
            ];
            foreach ($tabs as $key => $tab):
                $active = ($filter === $key) ? ' active' : '';
            ?>
            <a href="invoice_list.php?filter=<?php echo $key; ?>" class="inv-tab<?php echo $active; ?>">
                <i class="fas <?php echo $tab['icon']; ?>"></i>
                <?php echo $tab['label']; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="text-muted" style="font-size:.8rem;">
            <?php echo $total; ?> rekord<?php echo $total !== 1 ? 'e' : ''; ?> gjithsej
        </div>
    </div>

    <!-- Invoice list -->
    <?php if (empty($payments)): ?>
    <div class="inv-card">
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-file-invoice"></i></div>
            <h5 style="color:#555;">Nuk ka fatura<?php echo $filter !== 'all' ? ' ' . ($filter === 'completed' ? 'të kryera' : 'në pritje') : ''; ?></h5>
            <p style="color:#aaa;font-size:.88rem;">Pasi të kryeni një pagesë, do të shfaqet këtu.</p>
            <a href="reservation.php" class="btn-view mt-3" style="display:inline-flex;margin-top:16px;">
                <i class="fas fa-calendar-plus"></i>Rezervo Termin
            </a>
        </div>
    </div>
    <?php else: ?>
    <div class="inv-card">
        <!-- Table header -->
        <div class="inv-row" style="background:#f8f9fb;border-bottom:2px solid #ececec;pointer-events:none;">
            <div class="inv-num" style="color:#777;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;">ID</div>
            <div class="inv-service" style="color:#777;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;">Shërbimi / Rezervimi</div>
            <div class="inv-method" style="color:#777;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;">Metoda</div>
            <div class="inv-date"   style="color:#777;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;">Data</div>
            <div class="inv-amount" style="color:#777;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;">Shuma</div>
            <div class="inv-status" style="color:#777;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;text-align:center;">Statusi</div>
            <div style="min-width:120px;"></div>
        </div>

        <?php foreach ($payments as $p):
            $stl = paymentStatusLabel($p['status'] ?? 'pending');
            $svc = $p['service'] ?? 'Pagesë Shërbimi';
            $amt = (float)($p['amount'] ?? 0);
        ?>
        <div class="inv-row">
            <div class="inv-num">#<?php echo str_pad((int)$p['id'], 5, '0', STR_PAD_LEFT); ?></div>

            <div class="inv-service">
                <div class="inv-service-name"><?php echo htmlspecialchars($svc); ?></div>
                <div class="inv-service-sub">
                    <?php if (!empty($p['zyra_emri'])): ?><i class="fas fa-building me-1"></i><?php echo htmlspecialchars($p['zyra_emri']); ?><?php endif; ?>
                    <?php if (!empty($p['res_date'])): ?>
                        &nbsp;&bull;&nbsp;<i class="far fa-calendar me-1"></i><?php echo date('d.m.Y', strtotime($p['res_date'])); ?>
                        <?php if (!empty($p['res_time'])): ?> <?php echo substr($p['res_time'], 0, 5); ?><?php endif; ?>
                    <?php endif; ?>
                    <?php if ($roli === 'admin' && !empty($p['emri'])): ?>
                        &nbsp;&bull;&nbsp;<i class="fas fa-user me-1"></i><?php echo htmlspecialchars($p['emri'] . ' ' . $p['mbiemri']); ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="inv-method">
                <i class="fas fa-credit-card" style="color:#aaa;"></i>
                <?php echo htmlspecialchars($p['payment_method'] ?? 'N/A'); ?>
            </div>

            <div class="inv-date"><?php echo date('d.m.Y', strtotime($p['created_at'])); ?></div>

            <div class="inv-amount">
                <?php echo $amt > 0 ? '€' . number_format($amt, 2) : '<span style="color:#bbb;">—</span>'; ?>
            </div>

            <div class="inv-status">
                <span class="status-pill" style="background:<?php echo $stl['bg']; ?>;color:<?php echo $stl['color']; ?>;">
                    <i class="fas <?php echo $stl['icon']; ?>"></i><?php echo $stl['text']; ?>
                </span>
            </div>

            <div class="inv-actions">
                <a href="invoice.php?payment_id=<?php echo (int)$p['id']; ?><?php echo !empty($p['reservation_id']) ? '&reservation_id='.(int)$p['reservation_id'] : ''; ?>"
                   class="btn-view" target="_blank" title="Shiko Faturën">
                    <i class="fas fa-eye"></i> Fatura
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-wrap">
        <?php
        $pc = ($page <= 1) ? ' disabled' : '';
        $nc = ($page >= $totalPages) ? ' disabled' : '';
        echo "<a href='invoice_list.php?filter=$filter&page=".max(1,$page-1)."' class='page-btn$pc'><i class='fas fa-chevron-left'></i></a>";
        $s = max(1, $page-2); $e = min($totalPages, $page+2);
        if ($s > 1) { echo "<a href='invoice_list.php?filter=$filter&page=1' class='page-btn'>1</a>"; if ($s>2) echo "<span class='page-btn' style='border:none;background:none;color:#aaa;cursor:default;'>…</span>"; }
        for ($i=$s; $i<=$e; $i++) { $cls=($i===$page)?' active':''; echo "<a href='invoice_list.php?filter=$filter&page=$i' class='page-btn$cls'>$i</a>"; }
        if ($e < $totalPages) { if ($e<$totalPages-1) echo "<span class='page-btn' style='border:none;background:none;color:#aaa;cursor:default;'>…</span>"; echo "<a href='invoice_list.php?filter=$filter&page=$totalPages' class='page-btn'>$totalPages</a>"; }
        echo "<a href='invoice_list.php?filter=$filter&page=".min($totalPages,$page+1)."' class='page-btn$nc'><i class='fas fa-chevron-right'></i></a>";
        ?>
    </div>
    <div class="text-center mt-2" style="font-size:.78rem;color:#bbb;">
        Duke shfaqur <?php echo ($offset+1); ?>–<?php echo min($offset+$perPage,$total); ?> nga <?php echo $total; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</main>

<!-- Footer -->
<footer style="background:#1a1f2e;color:#8b949e;padding:24px 0 18px;margin-top:40px;text-align:center;margin-left:var(--sidebar-w);">
    <div style="font-size:.83rem;">
        &copy; <?php echo date('Y'); ?> <strong style="color:var(--gov-gold);">e-Noteria</strong> &mdash; Platformë SaaS Noteriale &bull;
        <a href="Privacy_policy.php" style="color:#8b949e;text-decoration:none;" onmouseover="this.style.color='#c49a6c'" onmouseout="this.style.color='#8b949e'">Privatësia</a> &bull;
        <a href="terms.php"          style="color:#8b949e;text-decoration:none;" onmouseover="this.style.color='#c49a6c'" onmouseout="this.style.color='#8b949e'">Kushtet</a>
    </div>
</footer>

<script src="/assets/bootstrap/bootstrap.bundle.min.js"></script>
<script>
const sb = document.getElementById('sidebar'), bd = document.getElementById('sidebar-backdrop');
document.getElementById('sidebar-toggle')?.addEventListener('click', () => {
    const o = sb.classList.toggle('open');
    bd.style.display = o ? 'block' : 'none';
});
bd?.addEventListener('click', () => { sb.classList.remove('open'); bd.style.display = 'none'; });
</script>
</body>
</html>
