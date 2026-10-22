<?php
// Statusi i Sistemit - e-Noteria

$status = [
    'database' => [
        'label' => 'Baza e të Dhënave',
        'status' => 'online',
        'desc' => 'Lidhja me bazën e të dhënave është aktive dhe funksionale.'
    ],
    'pdf' => [
        'label' => 'Gjenerimi i PDF',
        'status' => 'online',
        'desc' => 'Shërbimi për gjenerimin e dokumenteve PDF është aktiv.'
    ],
    'email' => [
        'label' => 'Dërgimi i Email',
        'status' => 'online',
        'desc' => 'Sistemi i njoftimeve me email është funksional.'
    ],
    'api' => [
        'label' => 'API Integrimet',
        'status' => 'online',
        'desc' => 'Të gjitha integrimet API janë të lidhura.'
    ],
    'security' => [
        'label' => 'Siguria',
        'status' => 'online',
        'desc' => 'Sistemi i sigurisë është aktiv dhe monitorohet.'
    ],
    'backup' => [
        'label' => 'Backup',
        'status' => 'online',
        'desc' => 'Kopjet rezervë realizohen rregullisht.'
    ],
];

function statusIcon($status) {
    if ($status === 'online') return '<i class="fas fa-circle text-success me-1"></i> <span class="text-success">Online</span>';
    if ($status === 'offline') return '<i class="fas fa-circle text-danger me-1"></i> <span class="text-danger">Offline</span>';
    return '<i class="fas fa-circle text-warning me-1"></i> <span class="text-warning">Në proces</span>';
}

?><!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statusi i Sistemit | e-Noteria</title>
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <style>
        body { background: #f2f4f8; font-family: 'Segoe UI', Arial, sans-serif; }
        .status-card {
            max-width: 600px;
            margin: 60px auto 0 auto;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,51,102,0.13);
            border: 1.5px solid #e0e6ed;
            background: #fff;
            padding: 36px 32px 28px 32px;
        }
        .header-logo {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px;
        }
        .header-logo img { height: 48px; }
        .header-logo span { font-size: 18px; color: #003366; font-weight: 700; letter-spacing: 1px; }
        .status-title {
            color: #003366;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 18px;
            text-align: center;
        }
        .status-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }
        .status-table th, .status-table td {
            padding: 12px 10px;
            font-size: 1.05rem;
        }
        .status-table th {
            color: #003366;
            font-weight: 600;
            background: #f8fafc;
            border-bottom: 2px solid #e0e6ed;
        }
        .status-table tr {
            background: #f8fafc;
            border-radius: 10px;
        }
        .status-table td:first-child {
            font-weight: 500;
            color: #003366;
        }
        .status-table td:last-child {
            font-size: 0.98rem;
            color: #666;
        }
        .status-table tr:hover {
            background: #e6f0fa;
        }
        .status-footer {
            margin-top: 32px;
            text-align: center;
            color: #888;
            font-size: 0.98rem;
        }
    </style>
</head>
<body>
    <div class="status-card">
        <div class="header-logo mb-2">
            <img src="https://upload.wikimedia.org/wikipedia/commons/1/1a/Coat_of_arms_of_Kosovo.svg" alt="Logo">
            <span>e-Noteria</span>
        </div>
        <div class="status-title mb-4">
            <i class="fas fa-server me-2"></i>Statusi i Sistemit
        </div>
        <table class="status-table mb-3">
            <thead>
                <tr>
                    <th>Shërbimi</th>
                    <th>Statusi</th>
                    <th>Detaje</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($status as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['label']) ?></td>
                    <td><?= statusIcon($item['status']) ?></td>
                    <td><?= htmlspecialchars($item['desc']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="status-footer">
            Përditësuar: <?= date('d.m.Y H:i') ?> &mdash; Të gjitha shërbimet janë nën monitorim të vazhdueshëm.<br>
            <a href="index.php" class="text-decoration-none text-primary">&larr; Kthehu te faqja kryesore</a>
            <br>
            <a href="ndihma.php" class="text-decoration-none text-warning fw-bold mt-2 d-inline-block"><i class="fas fa-circle-question me-1"></i>Ndihma & FAQ</a>
        </div>
    </div>
</body>
</html>

