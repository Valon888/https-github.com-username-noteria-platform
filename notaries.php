<?php
// Konfigurimi i raportimit të gabimeve
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Fillimi i sigurt i sesionit
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

require_once 'confidb.php';

// Përcaktoni numrin e shërbimeve për faqe
$entries_per_page = isset($_GET['perPage']) ? (int)$_GET['perPage'] : 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Ndërtoni query-n
$where_clause = '';
if (!empty($search)) {
    $search = '%' . $search . '%';
    $where_clause = " WHERE emri_mbiemri LIKE ? OR kontakti LIKE ? OR email LIKE ? OR qyteti LIKE ?";
}

// Merr numrin total të notarëve
try {
    if (!empty($where_clause)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notaret" . $where_clause);
        $stmt->execute(array_fill(0, 4, $search));
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM notaret");
    }
    $total = $stmt->fetch()['total'];
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $total = 0;
}

$total_pages = ceil($total / $entries_per_page);
if ($page > $total_pages) $page = $total_pages;
if ($page < 1) $page = 1;

$offset = ($page - 1) * $entries_per_page;

// Merr notarët
try {
    $sql = "SELECT id, emri_mbiemri, kontakti, email, adresa, qyteti FROM notaret" . $where_clause . " ORDER BY qyteti, emri_mbiemri LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
    if (!empty($where_clause)) {
        $params = [$search, $search, $search, $search, $entries_per_page, $offset];
    } else {
        $params = [$entries_per_page, $offset];
    }
    $stmt->execute($params);
    $notaret = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $notaret = [];
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista e Noterëve - Noteria</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 30px 20px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #2d3748;
            font-size: 2.2rem;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #718096;
            font-size: 1rem;
            margin-bottom: 20px;
        }
        
        .search-section {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .search-section input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
        }
        
        .search-section button {
            padding: 12px 24px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .search-section button:hover {
            background: #764ba2;
        }
        
        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }
        
        .entries-select {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .table-wrapper {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.95rem;
        }
        
        tr:hover {
            background: #f7fafc;
        }
        
        .notary-name {
            font-weight: 600;
            color: #2d3748;
        }
        
        .notary-email {
            color: #667eea;
            text-decoration: none;
        }
        
        .notary-email:hover {
            text-decoration: underline;
        }
        
        .notary-phone {
            color: #667eea;
            text-decoration: none;
        }
        
        .notary-phone:hover {
            text-decoration: underline;
        }
        
        .btn-profile {
            background: #667eea;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            transition: background 0.3s;
        }
        
        .btn-profile:hover {
            background: #764ba2;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .pagination a, .pagination span {
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: white;
            text-decoration: none;
            color: #667eea;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .pagination a:hover {
            background: #667eea;
            color: white;
        }
        
        .pagination .active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .info-bar {
            color: #718096;
            font-size: 0.9rem;
            margin-top: 15px;
        }
        
        .no-results {
            background: white;
            padding: 40px;
            border-radius: 12px;
            text-align: center;
            color: #718096;
            font-size: 1.1rem;
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.6rem;
            }
            
            .search-section {
                flex-direction: column;
            }
            
            .controls {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            table {
                font-size: 0.85rem;
            }
            
            th, td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Lista e Noterëve</h1>
            <p>Shërbim profesional noterialit në të gjithë vendin</p>
            
            <form method="GET" class="search-section">
                <input type="text" name="search" placeholder="Kërko sipas emrit, kontaktit, email-it ose qytetit..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">🔍 Kërko</button>
            </form>
            
            <div class="controls">
                <label>
                    Shfaq për faqe:
                    <select class="entries-select" onchange="window.location.href='?perPage=' + this.value + '&page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>'">
                        <option value="10" <?php echo $entries_per_page == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="25" <?php echo $entries_per_page == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $entries_per_page == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $entries_per_page == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                </label>
                <div class="info-bar">Gjithëj: <strong><?php echo $total; ?></strong> noterë</div>
            </div>
        </div>
        
        <?php if (!empty($notaret)): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Emri dhe mbiemri i noterit/es</th>
                            <th>Kontakti</th>
                            <th>E-mail</th>
                            <th>Adresa</th>
                            <th>Qyteti</th>
                            <th>Detajet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notaret as $notary): ?>
                            <tr>
                                <td><span class="notary-name"><?php echo htmlspecialchars($notary['emri_mbiemri']); ?></span></td>
                                <td>
                                    <?php if (!empty($notary['kontakti'])): ?>
                                        <a href="tel:<?php echo htmlspecialchars($notary['kontakti']); ?>" class="notary-phone"><?php echo htmlspecialchars($notary['kontakti']); ?></a>
                                    <?php else: ?>
                                        <span style="color: #ccc;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($notary['email'])): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($notary['email']); ?>" class="notary-email"><?php echo htmlspecialchars($notary['email']); ?></a>
                                    <?php else: ?>
                                        <span style="color: #ccc;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($notary['adresa'] ?? '-'); ?></td>
                                <td><strong><?php echo htmlspecialchars($notary['qyteti'] ?? '-'); ?></strong></td>
                                <td>
                                    <button class="btn-profile" onclick="alert('Profili i noterit: <?php echo htmlspecialchars($notary['emri_mbiemri']); ?>');">Vizito profilin</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1&perPage=<?php echo $entries_per_page; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">« Fillim</a>
                    <a href="?page=<?php echo $page - 1; ?>&perPage=<?php echo $entries_per_page; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">‹ Mbrapa</a>
                <?php else: ?>
                    <span class="disabled">« Fillim</span>
                    <span class="disabled">‹ Mbrapa</span>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1) echo '<span>...</span>';
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    if ($i == $page) {
                        echo '<span class="active">' . $i . '</span>';
                    } else {
                        echo '<a href="?page=' . $i . '&perPage=' . $entries_per_page . ($search ? '&search=' . urlencode($search) : '') . '">' . $i . '</a>';
                    }
                }
                
                if ($end_page < $total_pages) echo '<span>...</span>';
                ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&perPage=<?php echo $entries_per_page; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Para ›</a>
                    <a href="?page=<?php echo $total_pages; ?>&perPage=<?php echo $entries_per_page; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Fundi »</a>
                <?php else: ?>
                    <span class="disabled">Para ›</span>
                    <span class="disabled">Fundi »</span>
                <?php endif; ?>
            </div>
            
            <div class="info-bar" style="text-align: center; margin-top: 20px;">
                Shfaqja e noterëve <strong><?php echo $offset + 1; ?></strong> deri në <strong><?php echo min($offset + $entries_per_page, $total); ?></strong> nga <strong><?php echo $total; ?></strong>
            </div>
        <?php else: ?>
            <div class="no-results">
                <p>Nuk u gjet asnjë notere me kriteret e kërkimit.</p>
                <p style="margin-top: 10px; font-size: 0.9rem;"><a href="notaries.php" style="color: #667eea; text-decoration: none;">Shiko të gjithë</a></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
