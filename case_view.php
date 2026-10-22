<?php
require_once 'cases_functions.php';
require_once 'confidb.php';

// Merr ID e lëndës
$case_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$case_id) {
    die('Lënda nuk u gjet.');
}

// Merr të dhënat e lëndës
$stmt = $pdo->prepare("SELECT * FROM cases WHERE id = ?");
$stmt->execute([$case_id]);
$case = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$case) {
    die('Lënda nuk ekziston.');
}

// Ngarko dokument të ri
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_doc'])) {
    $file = $_FILES['document'] ?? null;
    $doc_type = trim($_POST['doc_type']);
    $uploaded_by = 1; // Ndrysho sipas user-it të kyçur
    if ($file && $file['tmp_name'] && $doc_type) {
        $upload_dir = 'uploads/case_docs/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_name = basename($file['name']);
        $file_path = $upload_dir . uniqid() . '_' . $file_name;
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            shtoDokumentLende($pdo, $case_id, $file_name, $file_path, $uploaded_by, $doc_type);
            regjistroAudit($pdo, $case_id, $uploaded_by, 'ngarkim dokumenti', 'Ngarkuar dokumenti: '.$file_name);
            $message = '<div class="alert alert-success">Dokumenti u ngarkua me sukses!</div>';
        } else {
            $message = '<div class="alert alert-danger">Gabim gjatë ngarkimit të dokumentit.</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">Ju lutem zgjidhni dokumentin dhe tipin.</div>';
    }
}

// Listo dokumentet e lëndës
$documents = listDokumenteLende($pdo, $case_id);
// Listo audit trail
$stmt = $pdo->prepare("SELECT a.*, u.emri as user_name FROM case_audit_trail a LEFT JOIN users u ON a.user_id = u.id WHERE a.case_id = ? ORDER BY a.action_time DESC");
$stmt->execute([$case_id]);
$audit_trail = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detajet e Lëndës | e-Noteria</title>
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <style>
        body { background: #f2f4f8; }
        .case-card { max-width: 950px; margin: 40px auto; border-radius: 18px; box-shadow: 0 8px 32px rgba(0,51,102,0.10); background: #fff; padding: 36px 28px; }
        .doc-table th { color: #003366; }
        .doc-table td, .doc-table th { vertical-align: middle; }
        .audit-table th { color: #003366; }
        .audit-table td, .audit-table th { vertical-align: middle; font-size: 0.98rem; }
    </style>
</head>
<body>
    <div class="case-card">
        <h2 class="mb-3 text-primary"><i class="fas fa-folder-open me-2"></i>Detajet e Lëndës</h2>
        <div class="mb-3">
            <b>Numri:</b> <?= htmlspecialchars($case['case_number']) ?> &nbsp;|
            <b>Titulli:</b> <?= htmlspecialchars($case['title']) ?> &nbsp;|
            <b>Statusi:</b> <?= htmlspecialchars($case['status']) ?> &nbsp;|
            <b>Hapur më:</b> <?= htmlspecialchars($case['opened_at']) ?>
        </div>
        <div class="mb-4"><b>Përshkrimi:</b> <?= nl2br(htmlspecialchars($case['description'])) ?></div>
        <a href="cases_dashboard.php" class="btn btn-outline-secondary mb-4">&larr; Kthehu te Arkiva</a>
        <?= $message ?>
        <div class="mb-4 p-3 bg-light rounded">
            <h5 class="mb-3">Ngarko Dokument të Ri</h5>
            <form class="row g-3" method="post" enctype="multipart/form-data">
                <div class="col-md-5">
                    <input type="file" class="form-control" name="document" required>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" name="doc_type" placeholder="Tipi i dokumentit (p.sh. kontratë, akt, etj.)" required>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-success w-100" type="submit" name="upload_doc"><i class="fas fa-upload me-1"></i>Ngarko Dokument</button>
                </div>
            </form>
        </div>
        <h5 class="mb-3">Dokumentet e Lëndës</h5>
        <div class="table-responsive mb-4">
            <table class="table table-bordered table-hover doc-table align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Emri</th>
                        <th>Tipi</th>
                        <th>Ngarkuar nga</th>
                        <th>Ngarkuar më</th>
                        <th>Shkarko</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($documents as $d): ?>
                    <tr>
                        <td><?= htmlspecialchars($d['id']) ?></td>
                        <td><?= htmlspecialchars($d['file_name']) ?></td>
                        <td><?= htmlspecialchars($d['doc_type']) ?></td>
                        <td><?= htmlspecialchars($d['uploaded_by']) ?></td>
                        <td><?= htmlspecialchars($d['uploaded_at']) ?></td>
                        <td><a href="<?= htmlspecialchars($d['file_path']) ?>" class="btn btn-sm btn-primary" target="_blank"><i class="fas fa-download"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <h5 class="mb-3">Auditimi i Veprimeve</h5>
        <div class="table-responsive">
            <table class="table table-bordered audit-table align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Data</th>
                        <th>Përdoruesi</th>
                        <th>Veprimi</th>
                        <th>Detaje</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($audit_trail as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['action_time']) ?></td>
                        <td><?= htmlspecialchars($a['user_name'] ?? $a['user_id']) ?></td>
                        <td><?= htmlspecialchars($a['action']) ?></td>
                        <td><?= htmlspecialchars($a['details']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script src="/assets/fontawesome/all.min.js"></script>
</body>
</html>

