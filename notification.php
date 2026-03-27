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

// Fetch user info
$stmtU = $pdo->prepare("SELECT emri, mbiemri FROM users WHERE id = ?");
$stmtU->execute([$user_id]);
$user = $stmtU->fetch();

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Actions ──────────────────────────────────────────────
$action_msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $action_msg = ['type' => 'danger', 'text' => 'Veprim i paautorizuar (CSRF).'];
    } elseif (isset($_POST['mark_all_read'])) {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user_id]);
        $action_msg = ['type' => 'success', 'text' => 'Të gjitha njoftimet u shënuan si të lexuara.'];
    } elseif (isset($_POST['mark_read'], $_POST['notif_id'])) {
        $nid = (int)$_POST['notif_id'];
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$nid, $user_id]);
    } elseif (isset($_POST['delete_notif'], $_POST['notif_id'])) {
        $nid = (int)$_POST['notif_id'];
        $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?")->execute([$nid, $user_id]);
        $action_msg = ['type' => 'success', 'text' => 'Njoftimi u fshi.'];
    } elseif (isset($_POST['delete_all'])) {
        $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$user_id]);
        $action_msg = ['type' => 'success', 'text' => 'Të gjitha njoftimet u fshinë.'];
    }
    // Redirect to avoid resubmit, carry action message via session
    if ($action_msg) { $_SESSION['notif_flash'] = $action_msg; }
    header("Location: notification.php" . (isset($_GET['filter']) ? '?filter='.$_GET['filter'] : ''));
    exit();
}

if (!empty($_SESSION['notif_flash'])) {
    $action_msg = $_SESSION['notif_flash'];
    unset($_SESSION['notif_flash']);
}

// ── Fetch notifications ───────────────────────────────────
$filter = in_array($_GET['filter'] ?? '', ['unread', 'read']) ? $_GET['filter'] : 'all';
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$whereExtra = '';
if ($filter === 'unread') $whereExtra = ' AND is_read = 0';
if ($filter === 'read')   $whereExtra = ' AND is_read = 1';

try {
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?$whereExtra");
    $stmtCount->execute([$user_id]);
    $total = (int)$stmtCount->fetchColumn();

    $stmtN = $pdo->prepare("SELECT id, title, message, type, is_read, created_at FROM notifications WHERE user_id = ?$whereExtra ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
    $stmtN->execute([$user_id]);
    $notifs = $stmtN->fetchAll();
} catch (PDOException $e) {
    error_log("Notifications fetch error: " . $e->getMessage());
    $notifs = [];
    $total  = 0;
}

$totalPages   = max(1, (int)ceil($total / $perPage));
$unreadCount  = 0;
try {
    $stmtUR = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmtUR->execute([$user_id]);
    $unreadCount = (int)$stmtUR->fetchColumn();
} catch (PDOException $e) { /* skip */ }

// ── Navbar notification bell ──────────────────────────────
$navNotifs = [];
try {
    $stmtNav = $pdo->prepare("SELECT id, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmtNav->execute([$user_id]);
    $navNotifs = $stmtNav->fetchAll();
} catch (PDOException $e) { /* skip */ }

// ── Helpers ───────────────────────────────────────────────
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Tani';
    if ($diff < 3600)   return floor($diff/60).' min më parë';
    if ($diff < 86400)  return floor($diff/3600).' orë më parë';
    if ($diff < 604800) return floor($diff/86400).' ditë më parë';
    return date('d.m.Y', strtotime($datetime));
}

function typeConfig(string $type): array {
    return match($type) {
        'success' => ['icon' => 'fas fa-check-circle',    'color' => '#1a5c35', 'bg' => '#d4edda', 'label' => 'Sukses'],
        'warning' => ['icon' => 'fas fa-exclamation-triangle','color' => '#856404','bg' => '#fff3cd','label' => 'Paralajmërim'],
        'error'   => ['icon' => 'fas fa-times-circle',    'color' => '#721c24', 'bg' => '#f8d7da', 'label' => 'Gabim'],
        default   => ['icon' => 'fas fa-info-circle',     'color' => '#003366', 'bg' => '#dce8ff', 'label' => 'Informacion'],
    };
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Njoftimet | e-Noteria</title>
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
        .top-navbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1030;
            height: var(--navbar-h);
            background: var(--gov-blue);
            border-bottom: 3px solid var(--gov-gold);
            display: flex; align-items: center; padding: 0 20px;
            box-shadow: 0 2px 16px rgba(0,0,0,.25);
        }
        .brand { color: #fff; font-family: 'Merriweather', serif; font-size: 1.3rem; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .brand img { height: 36px; width: auto; border-radius: 4px; }
        .brand-sub { font-size: .72rem; font-weight: 300; opacity: .75; letter-spacing: .5px; display: block; }
        .nav-right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
        .nav-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--gov-gold); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .9rem; color: var(--gov-blue); flex-shrink: 0; }
        .nav-user-name { font-size: .85rem; color: #fff; font-weight: 600; }
        .nav-user-role { font-size: .7rem; color: rgba(255,255,255,.6); }

        /* ── SIDEBAR ── */
        .sidebar {
            position: fixed; left: 0; top: var(--navbar-h); bottom: 0;
            width: var(--sidebar-w); background: #fff;
            border-right: 1px solid #e0e4ea; overflow-y: auto;
            z-index: 1020; box-shadow: 2px 0 8px rgba(0,0,0,.04);
            transition: transform .28s ease;
        }
        .sidebar-section { padding: 16px 20px 4px; font-size: .7rem; font-weight: 700; color: #b0b5c0; text-transform: uppercase; letter-spacing: 1.2px; }
        .sidebar-link { display: flex; align-items: center; gap: 11px; padding: 10px 20px; color: #555; font-size: .88rem; text-decoration: none; border-left: 3px solid transparent; transition: all .18s; }
        .sidebar-link i { width: 18px; text-align: center; color: #bbb; font-size: .9rem; transition: color .18s; }
        .sidebar-link:hover { background: #f3f6ff; color: var(--gov-blue); border-left-color: var(--gov-blue); }
        .sidebar-link:hover i { color: var(--gov-blue); }
        .sidebar-link.active { background: #eef2ff; color: var(--gov-blue); border-left-color: var(--gov-blue); font-weight: 600; }
        .sidebar-link.active i { color: var(--gov-blue); }
        .sidebar-link.link-danger { color: #c0392b; }
        .sidebar-link.link-danger i { color: #e74c3c; }
        .sidebar-link.link-danger:hover { background: #fff0f0; border-left-color: #e74c3c; }

        /* ── MAIN ── */
        .main-content { margin-left: var(--sidebar-w); margin-top: var(--navbar-h); padding: 28px; min-height: calc(100vh - var(--navbar-h)); }

        /* ── PAGE HEADER ── */
        .page-header {
            background: linear-gradient(135deg, var(--gov-blue) 0%, #00408c 100%);
            border-radius: 14px; padding: 28px 32px; color: white;
            margin-bottom: 24px; position: relative; overflow: hidden;
        }
        .page-header::after  { content:''; position:absolute; right:-40px; top:-40px; width:220px; height:220px; border-radius:50%; background:rgba(255,255,255,.05); }
        .page-header::before { content:''; position:absolute; right:60px; bottom:-60px; width:160px; height:160px; border-radius:50%; background:rgba(196,154,108,.12); }
        .page-header h1 { color:white; font-size:1.6rem; margin-bottom:4px; position:relative; }
        .page-header p  { color:rgba(255,255,255,.7); margin:0; font-size:.88rem; position:relative; }

        /* ── FILTER TABS ── */
        .notif-tabs { display:flex; gap:6px; flex-wrap:wrap; }
        .notif-tab {
            display:inline-flex; align-items:center; gap:7px;
            padding:8px 18px; border-radius:30px; font-size:.84rem; font-weight:600;
            text-decoration:none; transition:all .18s;
            border:2px solid #e0e4ea; background:#fff; color:#666;
        }
        .notif-tab:hover { border-color:var(--gov-blue); color:var(--gov-blue); background:#eef2ff; }
        .notif-tab.active { background:var(--gov-blue); border-color:var(--gov-blue); color:#fff; }
        .notif-tab .tab-badge { background:rgba(255,255,255,.25); color:#fff; border-radius:30px; padding:1px 8px; font-size:.72rem; }
        .notif-tab:not(.active) .tab-badge { background:#f0f2f5; color:#666; }
        .notif-tab.active:hover { background:#002244; border-color:#002244; color:#fff; }

        /* ── NOTIFICATION CARD ── */
        .notif-card {
            background:#fff; border-radius:14px;
            box-shadow:0 2px 8px rgba(0,0,0,.06);
            border:1px solid #e9ecef;
            overflow:hidden;
        }
        .notif-row {
            display:flex; align-items:flex-start; gap:16px;
            padding:18px 22px;
            border-bottom:1px solid #f5f6f8;
            transition:background .15s;
            position:relative;
        }
        .notif-row:last-child { border-bottom:none; }
        .notif-row:hover { background:#fafbff; }
        .notif-row.unread { background:#f7f9ff; }
        .notif-row.unread::before {
            content:''; position:absolute; left:0; top:0; bottom:0; width:4px;
            background:var(--gov-blue); border-radius:0 3px 3px 0;
        }

        .notif-icon-wrap {
            width:46px; height:46px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            font-size:1.1rem; flex-shrink:0; margin-top:2px;
        }
        .notif-body { flex:1; min-width:0; }
        .notif-title { font-weight:700; font-size:.93rem; color:#222; margin-bottom:3px; line-height:1.3; }
        .notif-row.unread .notif-title { color:var(--gov-blue); }
        .notif-message { font-size:.85rem; color:#666; line-height:1.5; }
        .notif-meta { margin-top:7px; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .notif-time { font-size:.73rem; color:#aaa; display:flex; align-items:center; gap:4px; }
        .notif-type-badge { font-size:.7rem; font-weight:700; padding:2px 9px; border-radius:20px; }
        .notif-unread-dot { width:9px; height:9px; border-radius:50%; background:var(--gov-blue); flex-shrink:0; }

        .notif-actions { display:flex; align-items:center; gap:6px; flex-shrink:0; }
        .btn-notif-action {
            width:34px; height:34px; border-radius:50%; border:1px solid #e0e4ea;
            background:#fff; display:flex; align-items:center; justify-content:center;
            cursor:pointer; transition:all .16s; color:#999; font-size:.82rem;
        }
        .btn-notif-action:hover { background:#fff0f0; border-color:#e74c3c; color:#e74c3c; }
        .btn-notif-action.btn-mark:hover { background:#eef2ff; border-color:var(--gov-blue); color:var(--gov-blue); }

        /* ── EMPTY STATE ── */
        .empty-state { text-align:center; padding:60px 20px; }
        .empty-state-icon { width:90px; height:90px; border-radius:50%; background:#eef2ff; display:flex; align-items:center; justify-content:center; font-size:2.4rem; color:var(--gov-blue); margin:0 auto 20px; }
        .empty-state h5 { color:#555; font-size:1.1rem; margin-bottom:8px; }
        .empty-state p  { color:#aaa; font-size:.88rem; }

        /* ── TOOLBAR ── */
        .toolbar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:18px; }
        .toolbar-left  { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .toolbar-right { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }

        .btn-gov { background:var(--gov-blue); color:#fff; border:none; border-radius:8px; padding:8px 18px; font-size:.85rem; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:7px; transition:all .18s; text-decoration:none; }
        .btn-gov:hover { background:#002244; color:#fff; }
        .btn-gov-outline { background:#fff; color:var(--gov-blue); border:2px solid var(--gov-blue); border-radius:8px; padding:7px 16px; font-size:.85rem; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:7px; transition:all .18s; text-decoration:none; }
        .btn-gov-outline:hover { background:var(--gov-blue); color:#fff; }
        .btn-danger-sm { background:#fff; color:#c0392b; border:2px solid #e5b8b8; border-radius:8px; padding:7px 14px; font-size:.83rem; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:all .18s; }
        .btn-danger-sm:hover { background:#c0392b; color:#fff; border-color:#c0392b; }

        /* ── PAGINATION ── */
        .pagination-wrap { display:flex; justify-content:center; gap:6px; padding-top:20px; flex-wrap:wrap; }
        .page-btn { display:inline-flex; align-items:center; justify-content:center; width:38px; height:38px; border-radius:8px; font-size:.85rem; font-weight:600; text-decoration:none; border:1.5px solid #e0e4ea; color:#555; background:#fff; transition:all .16s; }
        .page-btn:hover { border-color:var(--gov-blue); color:var(--gov-blue); background:#eef2ff; }
        .page-btn.active { background:var(--gov-blue); border-color:var(--gov-blue); color:#fff; }
        .page-btn.disabled { opacity:.4; pointer-events:none; }

        /* ── SUMMARY BANNER ── */
        .summary-banner { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
        .summary-pill {
            flex:1; min-width:110px; padding:14px 18px; border-radius:12px;
            display:flex; align-items:center; gap:12px;
            box-shadow:0 2px 6px rgba(0,0,0,.06);
        }
        .summary-pill .sp-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
        .summary-pill .sp-val { font-size:1.5rem; font-weight:700; font-family:'Inter',sans-serif; line-height:1; }
        .summary-pill .sp-lbl { font-size:.75rem; color:#888; }

        /* ── MOBILE ── */
        @media (max-width:991.98px) {
            .sidebar { transform:translateX(-100%); }
            .sidebar.open { transform:translateX(0); box-shadow:4px 0 20px rgba(0,0,0,.15); }
            .main-content { margin-left:0; }
        }
        @media (max-width:575.98px) {
            .main-content { padding:14px; }
            .page-header { padding:20px 18px; }
            .page-header h1 { font-size:1.25rem; }
            .notif-row { padding:14px 14px; }
            .notif-actions { flex-direction:column; }
        }
    </style>
</head>
<body>

<!-- ╔══════════════════════ TOP NAVBAR ══════════════════════ -->
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
        <div class="dropdown">
            <button class="btn text-white position-relative p-2" style="background:none;border:none;" data-bs-toggle="dropdown" aria-label="Njoftimet">
                <i class="fas fa-bell"></i>
                <?php if ($unreadCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.55rem;padding:3px 5px;">
                    <?php echo $unreadCount; ?>
                </span>
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

<!-- ╔══════════════════════ SIDEBAR ══════════════════════ -->
<aside class="sidebar" id="sidebar">
    <nav style="padding:8px 0 24px;">
        <div class="sidebar-section">Kryesore</div>
        <a href="dashboard.php#overview"  class="sidebar-link"><i class="fas fa-th-large"></i>Pasqyra</a>
        <a href="reservation.php"         class="sidebar-link"><i class="fas fa-calendar-plus"></i>Rezervo Termin</a>
        <a href="dashboard.php#terminet"  class="sidebar-link"><i class="fas fa-calendar-check"></i>Terminet e Mia</a>

        <div class="sidebar-section">Shërbime</div>
        <a href="invoice_list.php"         class="sidebar-link"><i class="fas fa-file-invoice"></i>Faturat</a>
        <a href="dashboard.php#mesazhe"   class="sidebar-link"><i class="fas fa-comments"></i>Mesazhe</a>
        <a href="notification.php"        class="sidebar-link active">
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

<!-- ╔══════════════════════ MAIN CONTENT ══════════════════════ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col">
                <h1><i class="fas fa-bell me-2" style="opacity:.8;"></i>Njoftimet</h1>
                <p><i class="fas fa-user me-1"></i><?php echo htmlspecialchars(($user['emri'] ?? '') . ' ' . ($user['mbiemri'] ?? '')); ?> &bull; Qendra e njoftimeve tuaja</p>
            </div>
            <?php if ($unreadCount > 0): ?>
            <div class="col-auto d-none d-sm-block" style="position:relative;z-index:1;">
                <div class="text-center">
                    <span style="font-size:2.2rem;font-weight:700;font-family:'Inter',sans-serif;color:#fff;"><?php echo $unreadCount; ?></span>
                    <div style="font-size:.8rem;color:rgba(255,255,255,.7);">të palexuara</div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Flash message -->
    <?php if ($action_msg): ?>
    <div class="alert alert-<?php echo htmlspecialchars($action_msg['type']); ?> alert-dismissible d-flex align-items-center gap-2 mb-4 rounded-3" role="alert" style="border-radius:12px!important;">
        <i class="fas fa-<?php echo $action_msg['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <span><?php echo htmlspecialchars($action_msg['text']); ?></span>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Summary pills -->
    <?php
    $countByType = ['info'=>0,'success'=>0,'warning'=>0,'error'=>0];
    try {
        $stmtTypes = $pdo->prepare("SELECT type, COUNT(*) as cnt FROM notifications WHERE user_id = ? GROUP BY type");
        $stmtTypes->execute([$user_id]);
        foreach ($stmtTypes->fetchAll() as $row) {
            $t = $row['type'] ?? 'info';
            if (isset($countByType[$t])) $countByType[$t] = (int)$row['cnt'];
        }
    } catch (PDOException $e) { /* skip */ }
    $totalAll = array_sum($countByType);
    ?>
    <?php if ($totalAll > 0): ?>
    <div class="summary-banner">
        <div class="summary-pill" style="background:#dce8ff;">
            <div class="sp-icon" style="background:#c3d8ff;color:var(--gov-blue);"><i class="fas fa-bell"></i></div>
            <div><div class="sp-val text-gov-blue" style="color:var(--gov-blue);"><?php echo $totalAll; ?></div><div class="sp-lbl">Gjithsej</div></div>
        </div>
        <div class="summary-pill" style="background:#fff3e0;">
            <div class="sp-icon" style="background:#ffe0b2;color:#e65100;"><i class="fas fa-envelope"></i></div>
            <div><div class="sp-val" style="color:#e65100;"><?php echo $unreadCount; ?></div><div class="sp-lbl">Të palexuara</div></div>
        </div>
        <div class="summary-pill" style="background:#d4edda;">
            <div class="sp-icon" style="background:#b2dfdb;color:#1a5c35;"><i class="fas fa-check-double"></i></div>
            <div><div class="sp-val" style="color:#1a5c35;"><?php echo $totalAll - $unreadCount; ?></div><div class="sp-lbl">Të lexuara</div></div>
        </div>
        <?php if ($countByType['warning'] + $countByType['error'] > 0): ?>
        <div class="summary-pill" style="background:#fff3cd;">
            <div class="sp-icon" style="background:#ffe69c;color:#856404;"><i class="fas fa-exclamation-triangle"></i></div>
            <div><div class="sp-val" style="color:#856404;"><?php echo $countByType['warning'] + $countByType['error']; ?></div><div class="sp-lbl">Paralajmërime</div></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Toolbar -->
    <div class="toolbar">
        <div class="toolbar-left">
            <div class="notif-tabs">
                <?php
                $tabs = [
                    'all'    => ['label' => 'Të gjitha', 'icon' => 'fa-list', 'count' => $totalAll],
                    'unread' => ['label' => 'Palexuara',  'icon' => 'fa-envelope',       'count' => $unreadCount],
                    'read'   => ['label' => 'Lexuara',    'icon' => 'fa-envelope-open',  'count' => $totalAll - $unreadCount],
                ];
                foreach ($tabs as $key => $tab):
                    $active = ($filter === $key) ? ' active' : '';
                ?>
                <a href="notification.php?filter=<?php echo $key; ?>" class="notif-tab<?php echo $active; ?>">
                    <i class="fas <?php echo $tab['icon']; ?>"></i>
                    <?php echo $tab['label']; ?>
                    <span class="tab-badge"><?php echo $tab['count']; ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="toolbar-right">
            <?php if ($unreadCount > 0): ?>
            <form method="post" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="mark_all_read" value="1">
                <button type="submit" class="btn-gov-outline">
                    <i class="fas fa-check-double"></i>Shëno të gjitha si të lexuara
                </button>
            </form>
            <?php endif; ?>
            <?php if ($totalAll > 0): ?>
            <form method="post" style="margin:0;" onsubmit="return confirm('A jeni të sigurt? Kjo do të fshijë të gjitha njoftimet.');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="delete_all" value="1">
                <button type="submit" class="btn-danger-sm">
                    <i class="fas fa-trash-alt"></i>Fshi të gjitha
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notifications List -->
    <?php if (empty($notifs)): ?>
    <div class="notif-card">
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fas fa-bell-slash"></i></div>
            <h5>Nuk ka njoftime<?php echo $filter !== 'all' ? ' ' . ($filter === 'unread' ? 'të palexuara' : 'të lexuara') : ''; ?></h5>
            <p><?php echo $filter === 'unread' ? 'Të gjitha njoftimet janë lexuar.' : 'Keni parë të gjitha njoftimet tuaja.'; ?></p>
            <?php if ($filter !== 'all'): ?>
            <a href="notification.php" class="btn-gov mt-3" style="display:inline-flex;text-decoration:none;">
                <i class="fas fa-list"></i>Shiko të gjitha
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="notif-card">
        <?php foreach ($notifs as $n):
            $cfg  = typeConfig($n['type'] ?? 'info');
            $isUnread = !(bool)$n['is_read'];
            $title = $n['title'] ? htmlspecialchars($n['title']) : $cfg['label'];
        ?>
        <div class="notif-row <?php echo $isUnread ? 'unread' : ''; ?>">
            <!-- Icon -->
            <div class="notif-icon-wrap" style="background:<?php echo $cfg['bg']; ?>;color:<?php echo $cfg['color']; ?>;">
                <i class="<?php echo $cfg['icon']; ?>"></i>
            </div>

            <!-- Body -->
            <div class="notif-body">
                <div class="notif-title">
                    <?php if ($isUnread): ?>
                    <span class="notif-unread-dot d-inline-block me-2" style="vertical-align:middle;"></span>
                    <?php endif; ?>
                    <?php echo $title; ?>
                </div>
                <div class="notif-message"><?php echo nl2br(htmlspecialchars($n['message'])); ?></div>
                <div class="notif-meta">
                    <span class="notif-time"><i class="far fa-clock"></i><?php echo timeAgo($n['created_at']); ?></span>
                    <span class="notif-time"><i class="far fa-calendar-alt"></i><?php echo date('d.m.Y H:i', strtotime($n['created_at'])); ?></span>
                    <span class="notif-type-badge" style="background:<?php echo $cfg['bg']; ?>;color:<?php echo $cfg['color']; ?>;">
                        <?php echo $cfg['label']; ?>
                    </span>
                    <?php if (!$isUnread): ?>
                    <span class="notif-time"><i class="fas fa-check text-muted"></i>Lexuar</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Actions -->
            <div class="notif-actions">
                <?php if ($isUnread): ?>
                <form method="post" style="margin:0;" title="Shëno si të lexuar">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="mark_read" value="1">
                    <input type="hidden" name="notif_id"  value="<?php echo (int)$n['id']; ?>">
                    <button type="submit" class="btn-notif-action btn-mark" title="Shëno si të lexuar">
                        <i class="fas fa-check"></i>
                    </button>
                </form>
                <?php endif; ?>
                <form method="post" style="margin:0;" onsubmit="return confirm('Fshi këtë njoftim?');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="delete_notif" value="1">
                    <input type="hidden" name="notif_id"    value="<?php echo (int)$n['id']; ?>">
                    <button type="submit" class="btn-notif-action" title="Fshi">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-wrap">
        <?php
        $prevClass = ($page <= 1) ? ' disabled' : '';
        $nextClass = ($page >= $totalPages) ? ' disabled' : '';
        echo "<a href='notification.php?filter=$filter&page=".max(1,$page-1)."' class='page-btn$prevClass'><i class='fas fa-chevron-left'></i></a>";
        $start = max(1, $page-2); $end = min($totalPages, $page+2);
        if ($start > 1) { echo "<a href='notification.php?filter=$filter&page=1' class='page-btn'>1</a>"; if ($start > 2) echo "<span class='page-btn' style='border:none;background:none;color:#aaa;cursor:default;'>…</span>"; }
        for ($i = $start; $i <= $end; $i++) {
            $cls = ($i === $page) ? ' active' : '';
            echo "<a href='notification.php?filter=$filter&page=$i' class='page-btn$cls'>$i</a>";
        }
        if ($end < $totalPages) { if ($end < $totalPages-1) echo "<span class='page-btn' style='border:none;background:none;color:#aaa;cursor:default;'>…</span>"; echo "<a href='notification.php?filter=$filter&page=$totalPages' class='page-btn'>$totalPages</a>"; }
        echo "<a href='notification.php?filter=$filter&page=".min($totalPages,$page+1)."' class='page-btn$nextClass'><i class='fas fa-chevron-right'></i></a>";
        ?>
    </div>
    <div class="text-center mt-2" style="font-size:.78rem;color:#bbb;">
        Duke shfaqur <?php echo (($page-1)*$perPage)+1; ?>–<?php echo min($page*$perPage,$total); ?> nga <?php echo $total; ?> njoftime
    </div>
    <?php endif; ?>
    <?php endif; ?>

</main>

<!-- ══════ FOOTER ══════ -->
<footer style="background:#1a1f2e;color:#8b949e;padding:24px 0 18px;margin-top:40px;text-align:center;margin-left:var(--sidebar-w);">
    <div style="font-size:.83rem;">
        &copy; <?php echo date('Y'); ?> <strong style="color:var(--gov-gold);">e-Noteria</strong> &mdash; Platformë SaaS Noteriale &bull;
        <a href="Privacy_policy.php" style="color:#8b949e;text-decoration:none;"  onmouseover="this.style.color='#c49a6c'" onmouseout="this.style.color='#8b949e'">Privatësia</a> &bull;
        <a href="terms.php"          style="color:#8b949e;text-decoration:none;"  onmouseover="this.style.color='#c49a6c'" onmouseout="this.style.color='#8b949e'">Kushtet</a>
    </div>
</footer>

<!-- Scripts -->
<script src="/assets/bootstrap/bootstrap.bundle.min.js"></script>
<script>
// Sidebar toggle (mobile)
const sidebarEl  = document.getElementById('sidebar');
const backdropEl = document.getElementById('sidebar-backdrop');
document.getElementById('sidebar-toggle')?.addEventListener('click', () => {
    const open = sidebarEl.classList.toggle('open');
    backdropEl.style.display = open ? 'block' : 'none';
});
backdropEl?.addEventListener('click', () => {
    sidebarEl.classList.remove('open');
    backdropEl.style.display = 'none';
});

// Auto-dismiss flash messages after 5s
document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => {
        el.style.transition = 'opacity .5s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 500);
    }, 5000);
});
</script>
</body>
</html>
