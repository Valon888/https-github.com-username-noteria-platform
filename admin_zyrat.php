<?php
// Admin Dashboard për Zyrat Noteriale
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

session_start();

require_once 'config.php';
require_once 'confidb.php';

// Kontrolloni nëse përdoruesi është admin
if (!isset($_SESSION['user_id']) || ($_SESSION['roli'] ?? 'user') !== 'admin') {
    header("Location: login.php");
    exit();
}

// Merr statistikat e zyrave
try {
    $total_zyrat = $pdo->query("SELECT COUNT(*) as count FROM zyrat")->fetch()['count'];
    $zyrat_aktive = $pdo->query("SELECT COUNT(*) as count FROM zyrat WHERE status = 'aktiv'")->fetch()['count'];
    $zyrat_ne_verifikim = $pdo->query("SELECT COUNT(*) as count FROM zyrat WHERE status = 'ne_verifikim'")->fetch()['count'];
    $total_pagesat = $pdo->query("SELECT SUM(pagesa) as total FROM zyrat")->fetch()['total'] ?? 0;
    
    // Merr listën e zyrave me pagesë
    $stmt = $pdo->query("
        SELECT id, emri, qyteti, email, telefoni, status, pagesa, data_regjistrimit 
        FROM zyrat 
        ORDER BY data_regjistrimit DESC 
        LIMIT 20
    ");
    $zyrat = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching zyrat: " . $e->getMessage());
    $total_zyrat = 0;
    $zyrat_aktive = 0;
    $zyrat_ne_verifikim = 0;
    $total_pagesat = 0;
    $zyrat = [];
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Menaxhimi i Zyrave</title>
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        
        .navbar {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar h1 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.8rem;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }
        
        .stat-content h3 {
            color: #666;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #667eea;
        }
        
        .stat-icon {
            font-size: 3rem;
            color: #667eea;
            opacity: 0.2;
        }
        
        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .table-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .table-header h2 {
            margin: 0;
            font-size: 1.5rem;
            color: #333;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f8f9fa;
        }
        
        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #667eea;
            border-bottom: 2px solid #667eea;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-aktiv {
            background: #ecfdf5;
            color: #059669;
        }
        
        .status-ne_verifikim {
            background: #fef3c7;
            color: #d97706;
        }
        
        .status-pasiv {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .action-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 0.85rem;
        }
        
        .action-btn:hover {
            background: #764ba2;
        }
        
        .action-btn.danger {
            background: #dc2626;
        }
        
        .action-btn.danger:hover {
            background: #991b1b;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .search-filter {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .search-filter input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .search-filter select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 0.9rem;
            }
            
            th, td {
                padding: 10px;
            }
            
            .search-filter {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1><i class="fas fa-building"></i> Menaxhimi i Zyrave Noteriale</h1>
    </div>
    
    <div class="container">
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-content">
                    <h3>Gjithsej Zyra</h3>
                    <div class="stat-number"><?php echo $total_zyrat; ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-building"></i></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <h3>Zyra Aktive</h3>
                    <div class="stat-number"><?php echo $zyrat_aktive; ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <h3>Në Verifikim</h3>
                    <div class="stat-number"><?php echo $zyrat_ne_verifikim; ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <h3>Gjithsej Pagesat</h3>
                    <div class="stat-number">€<?php echo number_format($total_pagesat, 2); ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-euro-sign"></i></div>
            </div>
        </div>
        
        <!-- Table -->
        <div class="table-container">
            <div class="table-header">
                <h2>Listat e Zyrave Noteriale</h2>
            </div>
            
            <?php if (!empty($zyrat)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Emri</th>
                            <th>Qyteti</th>
                            <th>Email</th>
                            <th>Telefoni</th>
                            <th>Status</th>
                            <th>Pagesa</th>
                            <th>Data Regjistrimit</th>
                            <th>Veprime</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($zyrat as $zyra): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($zyra['emri']); ?></strong></td>
                                <td><?php echo htmlspecialchars($zyra['qyteti']); ?></td>
                                <td><?php echo htmlspecialchars($zyra['email']); ?></td>
                                <td><?php echo htmlspecialchars($zyra['telefoni']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $zyra['status']; ?>">
                                        <?php echo ucfirst($zyra['status']); ?>
                                    </span>
                                </td>
                                <td>€<?php echo number_format($zyra['pagesa'], 2); ?></td>
                                <td><?php echo date('d.m.Y', strtotime($zyra['data_regjistrimit'])); ?></td>
                                <td>
                                    <button class="action-btn" onclick="viewDetails(<?php echo $zyra['id']; ?>)">Shiko</button>
                                    <?php if ($zyra['status'] === 'ne_verifikim'): ?>
                                        <button class="action-btn" onclick="approveOffice(<?php echo $zyra['id']; ?>)">Aprovo</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-inbox" style="font-size: 3rem; color: #ccc; display: block; margin-bottom: 10px;"></i>
                    <p>Nuk ka zyra të regjistruara akoma.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function viewDetails(id) {
            window.location.href = `zyra_details.php?id=${id}`;
        }
        
        function approveOffice(id) {
            if (confirm('Dëshironi të aprovoni këtë zyrë?')) {
                fetch('approve_office.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: id})
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('Zyra u aprovua me sukses!');
                        location.reload();
                    } else {
                        alert('Gabim: ' + data.message);
                    }
                });
            }
        }
    </script>
</body>
</html>
