<?php
// subscription_payments.php - Faqja për shikimin dhe menaxhimin e pagesave të abonimit
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';

// Kontrollo autorizimin (vetëm administratorët mund ta aksesojnë këtë faqe)
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Procesi i përditësimit të një pagese specifike
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'update_status') {
            $paymentId = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT);
            $newStatus = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
            $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
            
            if (!$paymentId) {
                throw new Exception("ID e pagesës është e pavlefshme.");
            }
            
            // Përditëso statusin e pagesës
            $stmt = $pdo->prepare("
                UPDATE subscription_payments 
                SET status = :status, notes = :notes, updated_at = NOW() 
                WHERE id = :payment_id
            ");
            
            $stmt->execute([
                'status' => $newStatus,
                'notes' => $notes,
                'payment_id' => $paymentId
            ]);
            
            // Merr të dhënat e noterit për të cilin është bërë pagesa
            $stmt = $pdo->prepare("
                SELECT n.id, n.emri, n.mbiemri, n.email, p.amount, p.reference 
                FROM subscription_payments p
                JOIN noteri n ON p.noter_id = n.id
                WHERE p.id = :payment_id
            ");
            $stmt->execute(['payment_id' => $paymentId]);
            $paymentData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Regjistro veprimin në logjet e aktivitetit
            if ($paymentData) {
                $message = "Statusi i pagesës së abonimit për noterin {$paymentData['emri']} {$paymentData['mbiemri']} u ndryshua në '$newStatus'. Ref: {$paymentData['reference']}";
                
                $logStmt = $pdo->prepare("
                    INSERT INTO activity_logs (log_type, user_id, status, message, created_at) 
                    VALUES (:log_type, :user_id, :status, :message, NOW())
                ");
                
                $logStmt->execute([
                    'log_type' => 'payment_update',
                    'user_id' => $paymentData['id'],
                    'status' => 'info',
                    'message' => $message
                ]);
                
                // Dërgo email noterit nëse statusi është completed ose failed
                if ($newStatus === 'completed' || $newStatus === 'failed') {
                    // Kodin për dërgimin e emailit mund ta shtosh këtu
                }
            }
            
            $updateMessage = "Statusi i pagesës u përditësua me sukses.";
            $updateStatus = 'success';
        }
        elseif ($_POST['action'] === 'delete_payment') {
            $paymentId = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT);
            
            if (!$paymentId) {
                throw new Exception("ID e pagesës është e pavlefshme.");
            }
            
            // Merr të dhënat e pagesës përpara se të fshihet
            $stmt = $pdo->prepare("
                SELECT n.id, n.emri, n.mbiemri, p.amount, p.reference, p.status 
                FROM subscription_payments p
                JOIN noteri n ON p.noter_id = n.id
                WHERE p.id = :payment_id
            ");
            $stmt->execute(['payment_id' => $paymentId]);
            $paymentData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Kontrollo nëse pagesa është completed - nëse po, mos lejo fshirjen
            if ($paymentData && $paymentData['status'] === 'completed') {
                throw new Exception("Nuk mund të fshihet një pagesë e kompletuar. Ju lutemi ndryshoni statusin përpara se të fshini.");
            }
            
            // Fshi pagesën
            $stmt = $pdo->prepare("DELETE FROM subscription_payments WHERE id = :payment_id");
            $stmt->execute(['payment_id' => $paymentId]);
            
            // Regjistro veprimin në logjet e aktivitetit
            if ($paymentData) {
                $message = "Pagesa e abonimit për noterin {$paymentData['emri']} {$paymentData['mbiemri']} u fshi. Ref: {$paymentData['reference']}";
                
                $logStmt = $pdo->prepare("
                    INSERT INTO activity_logs (log_type, user_id, status, message, created_at) 
                    VALUES (:log_type, :user_id, :status, :message, NOW())
                ");
                
                $logStmt->execute([
                    'log_type' => 'payment_delete',
                    'user_id' => $paymentData['id'],
                    'status' => 'warning',
                    'message' => $message
                ]);
            }
            
            $updateMessage = "Pagesa u fshi me sukses.";
            $updateStatus = 'success';
        }
    } catch (Exception $e) {
        $updateMessage = $e->getMessage();
        $updateStatus = 'error';
    }
}

// Filtrat për kërkim
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$dateStart = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$dateEnd = isset($_GET['date_end']) ? $_GET['date_end'] : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$sortOrder = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';

// Function to check if a column exists in a table
function columnExists($pdo, $table, $column) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM $table LIKE '$column'");
        return ($stmt && $stmt->rowCount() > 0);
    } catch (PDOException $e) {
        return false;
    }
}

// Ndërto query bazuar në filtrat
$params = [];
$sql = "
    SELECT p.id, p.noter_id, p.amount, p.payment_date, p.status, p.reference, 
           p.transaction_id, p.payment_method, p.description, p.notes, p.created_at,
           n.emri, n.mbiemri, n.email
    FROM subscription_payments p
    JOIN noteri n ON p.noter_id = n.id
    WHERE 1=1 
";

// Shto filtrat në query
if (!empty($searchTerm)) {
    $sql .= "AND (n.emri LIKE ? OR n.mbiemri LIKE ? OR n.email LIKE ? OR p.reference LIKE ? OR p.transaction_id LIKE ?) ";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

if (!empty($statusFilter)) {
    $sql .= "AND p.status = ? ";
    $params[] = $statusFilter;
}

if (!empty($dateStart)) {
    $sql .= "AND p.payment_date >= ? ";
    $params[] = $dateStart . ' 00:00:00';
}

if (!empty($dateEnd)) {
    $sql .= "AND p.payment_date <= ? ";
    $params[] = $dateEnd . ' 23:59:59';
}

// Renditja
switch ($sortBy) {
    case 'date':
        $sql .= "ORDER BY p.payment_date $sortOrder";
        break;
    case 'amount':
        $sql .= "ORDER BY p.amount $sortOrder, p.payment_date DESC";
        break;
    case 'status':
        $sql .= "ORDER BY p.status $sortOrder, p.payment_date DESC";
        break;
    case 'noter':
        $sql .= "ORDER BY n.emri $sortOrder, n.mbiemri $sortOrder, p.payment_date DESC";
        break;
    default:
        $sql .= "ORDER BY p.payment_date DESC";
}

// Ekzekuto query
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $updateMessage = "Gabim në marrjen e të dhënave: " . $e->getMessage();
    $updateStatus = 'error';
    $payments = [];
}

// Merr statistikat e pagesave
try {
    // Totali i pagesave të suksesshme
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, SUM(amount) as total
        FROM subscription_payments
        WHERE status = 'completed'
    ");
    $stmt->execute();
    $successStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Totali i pagesave të dështuara
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM subscription_payments
        WHERE status = 'failed'
    ");
    $stmt->execute();
    $failedStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Totali i pagesave në pritje
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM subscription_payments
        WHERE status = 'pending'
    ");
    $stmt->execute();
    $pendingStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Pagesat e muajit aktual
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, SUM(amount) as total
        FROM subscription_payments
        WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) 
        AND YEAR(payment_date) = YEAR(CURRENT_DATE())
        AND status = 'completed'
    ");
    $stmt->execute();
    $currentMonthStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $updateMessage = "Gabim në marrjen e statistikave: " . $e->getMessage();
    $updateStatus = 'error';
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagesat e Abonimeve | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #1a56db;
            --primary-hover: #1e40af;
            --secondary-color: #6b7280;
            --light-bg: #f8fafc;
            --border-color: #e2e8f0;
            --text-color: #374151;
            --heading-color: #1e293b;
            --success-color: #16a34a;
            --warning-color: #f59e0b;
            --danger-color: #dc2626;
        }
        
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: var(--light-bg);
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .panel {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        h1 {
            color: var(--primary-color);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
        }
        
        h1 i {
            margin-right: 12px;
        }
        
        h2 {
            color: var(--heading-color);
            margin-top: 30px;
            margin-bottom: 15px;
        }
        
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border-left: 5px solid #16a34a;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 5px solid #dc2626;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background-color: white;
        }
        
        th, td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            text-align: left;
        }
        
        th {
            background-color: #f1f5f9;
            color: var(--heading-color);
            font-weight: 600;
            cursor: pointer;
        }
        
        th:hover {
            background-color: #e2e8f0;
        }
        
        tr:hover {
            background-color: #f8fafc;
        }
        
        .status-completed {
            color: var(--success-color);
            font-weight: 500;
        }
        
        .status-failed {
            color: var(--danger-color);
            font-weight: 500;
        }
        
        .status-pending {
            color: var(--warning-color);
            font-weight: 500;
        }
        
        .status-test {
            color: var(--secondary-color);
            font-weight: 500;
        }
        
        .button {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
            border: none;
            text-decoration: none;
            font-family: inherit;
            font-weight: 500;
            font-size: 1rem;
            cursor: pointer;
            margin-right: 10px;
            transition: background-color 0.2s;
        }
        
        .button:hover {
            background-color: var(--primary-hover);
        }
        
        .button-small {
            padding: 6px 12px;
            font-size: 0.9rem;
        }
        
        .button-secondary {
            background-color: var(--secondary-color);
        }
        
        .button-secondary:hover {
            background-color: #4b5563;
        }
        
        .button-danger {
            background-color: var(--danger-color);
        }
        
        .button-danger:hover {
            background-color: #b91c1c;
        }
        
        .search-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            background-color: #f1f5f9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .search-form input,
        .search-form select {
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .search-form button {
            padding: 10px 15px;
        }
        
        .sort-indicator {
            margin-left: 5px;
        }
        
        .filter-text {
            margin-left: 10px;
            color: var(--secondary-color);
            font-style: italic;
        }
        
        .actions-column {
            text-align: right;
            white-space: nowrap;
        }
        
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            width: 500px;
            max-width: 90%;
        }
        
        .modal-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .modal-close:hover {
            color: var(--text-color);
        }
        
        .modal h2 {
            margin-top: 0;
            color: var(--primary-color);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--heading-color);
        }
        
        input[type="text"],
        input[type="number"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background-color: white;
            font-family: inherit;
            font-size: 1rem;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        
        textarea {
            height: 100px;
            resize: vertical;
        }
        
        input[type="text"]:focus,
        input[type="number"]:focus,
        input[type="date"]:focus,
        select:focus,
        textarea:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(26, 86, 219, 0.2);
        }
        
        .note-cell {
            font-size: 0.85rem;
            color: var(--secondary-color);
            display: block;
            margin-top: 3px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-label {
            color: var(--secondary-color);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-success .stat-value { color: var(--success-color); }
        .stat-warning .stat-value { color: var(--warning-color); }
        .stat-danger .stat-value { color: var(--danger-color); }
        .stat-info .stat-value { color: var(--primary-color); }
        
        .export-buttons {
            display: flex;
            gap: 10px;
            margin: 20px 0;
        }
        
        @media (max-width: 768px) {
            .search-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .toolbar {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .toolbar div {
                margin-top: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .export-buttons {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="toolbar">
            <h1><i class="fas fa-file-invoice-dollar"></i> Pagesat e Abonimeve</h1>
            
            <div>
                <a href="subscription_reports.php" class="button">
                    <i class="fas fa-chart-line"></i> Raportet
                </a>
                <a href="subscription_settings.php" class="button button-secondary">
                    <i class="fas fa-cog"></i> Konfigurimet
                </a>
                <a href="dashboard.php" class="button button-secondary">
                    <i class="fas fa-arrow-left"></i> Kthehu
                </a>
            </div>
        </div>
        
        <?php if (isset($updateMessage)): ?>
            <div class="alert alert-<?php echo $updateStatus; ?>">
                <?php echo $updateMessage; ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistika e pagesave -->
        <div class="stats-grid">
            <div class="stat-card stat-success">
                <div class="stat-label">Pagesat e suksesshme</div>
                <div class="stat-value"><?php echo number_format($successStats['count']); ?></div>
                <div><?php echo number_format($successStats['total'], 2); ?> EUR</div>
            </div>
            
            <div class="stat-card stat-danger">
                <div class="stat-label">Pagesat e dështuara</div>
                <div class="stat-value"><?php echo number_format($failedStats['count']); ?></div>
            </div>
            
            <div class="stat-card stat-warning">
                <div class="stat-label">Pagesat në pritje</div>
                <div class="stat-value"><?php echo number_format($pendingStats['count']); ?></div>
            </div>
            
            <div class="stat-card stat-info">
                <div class="stat-label">Muaji aktual</div>
                <div class="stat-value"><?php echo number_format($currentMonthStats['count']); ?></div>
                <div><?php echo number_format($currentMonthStats['total'], 2); ?> EUR</div>
            </div>
        </div>
        
        <div class="panel">
            <!-- Forma e kërkimit -->
            <form method="get" action="" class="search-form">
                <div>
                    <input type="text" name="search" placeholder="Kërko me emër, email, referencë..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>
                
                <div>
                    <select name="status">
                        <option value="">Të gjitha statuset</option>
                        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Të kompletuara</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Në pritje</option>
                        <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>>Të dështuara</option>
                        <option value="test" <?php echo $statusFilter === 'test' ? 'selected' : ''; ?>>Test</option>
                    </select>
                </div>
                
                <div>
                    <input type="date" name="date_start" placeholder="Data fillestare" value="<?php echo htmlspecialchars($dateStart); ?>">
                </div>
                
                <div>
                    <input type="date" name="date_end" placeholder="Data përfundimtare" value="<?php echo htmlspecialchars($dateEnd); ?>">
                </div>
                
                <div>
                    <button type="submit" class="button">Kërko</button>
                    <?php if (!empty($searchTerm) || !empty($statusFilter) || !empty($dateStart) || !empty($dateEnd)): ?>
                        <a href="?sort=<?php echo $sortBy; ?>&order=<?php echo strtolower($sortOrder); ?>" class="button button-secondary">Pastro</a>
                    <?php endif; ?>
                </div>
            </form>
            
            <!-- Butonat për eksport -->
            <div class="export-buttons">
                <button type="button" class="button" onclick="exportToCSV()">
                    <i class="fas fa-file-csv"></i> Eksporto në CSV
                </button>
                <button type="button" class="button" onclick="printPayments()">
                    <i class="fas fa-print"></i> Printo
                </button>
            </div>
            
            <!-- Tabela e pagesave -->
            <?php if (count($payments) > 0): ?>
                <table id="payments-table">
                    <thead>
                        <tr>
                            <th>
                                <a href="?sort=date&order=<?php echo $sortBy === 'date' && $sortOrder === 'ASC' ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($statusFilter); ?>&date_start=<?php echo urlencode($dateStart); ?>&date_end=<?php echo urlencode($dateEnd); ?>">
                                    Data
                                    <?php if ($sortBy === 'date'): ?>
                                        <span class="sort-indicator"><?php echo $sortOrder === 'ASC' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=noter&order=<?php echo $sortBy === 'noter' && $sortOrder === 'ASC' ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($statusFilter); ?>&date_start=<?php echo urlencode($dateStart); ?>&date_end=<?php echo urlencode($dateEnd); ?>">
                                    Noteri
                                    <?php if ($sortBy === 'noter'): ?>
                                        <span class="sort-indicator"><?php echo $sortOrder === 'ASC' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=amount&order=<?php echo $sortBy === 'amount' && $sortOrder === 'ASC' ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($statusFilter); ?>&date_start=<?php echo urlencode($dateStart); ?>&date_end=<?php echo urlencode($dateEnd); ?>">
                                    Shuma
                                    <?php if ($sortBy === 'amount'): ?>
                                        <span class="sort-indicator"><?php echo $sortOrder === 'ASC' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=status&order=<?php echo $sortBy === 'status' && $sortOrder === 'ASC' ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($statusFilter); ?>&date_start=<?php echo urlencode($dateStart); ?>&date_end=<?php echo urlencode($dateEnd); ?>">
                                    Statusi
                                    <?php if ($sortBy === 'status'): ?>
                                        <span class="sort-indicator"><?php echo $sortOrder === 'ASC' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Referenca/ID</th>
                            <th>Përshkrimi</th>
                            <th class="actions-column">Veprime</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td>
                                    <?php echo date('d.m.Y H:i', strtotime($payment['payment_date'])); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($payment['emri'] . ' ' . $payment['mbiemri']); ?>
                                    <span class="note-cell"><?php echo htmlspecialchars($payment['email']); ?></span>
                                </td>
                                <td>
                                    <?php echo number_format($payment['amount'], 2); ?> EUR
                                </td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    $statusText = '';
                                    
                                    switch ($payment['status']) {
                                        case 'completed':
                                            $statusClass = 'status-completed';
                                            $statusText = 'Kompletuar';
                                            break;
                                        case 'pending':
                                            $statusClass = 'status-pending';
                                            $statusText = 'Në pritje';
                                            break;
                                        case 'failed':
                                            $statusClass = 'status-failed';
                                            $statusText = 'Dështuar';
                                            break;
                                        case 'test':
                                            $statusClass = 'status-test';
                                            $statusText = 'Test';
                                            break;
                                        default:
                                            $statusClass = '';
                                            $statusText = $payment['status'];
                                    }
                                    ?>
                                    <span class="<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($payment['reference']); ?>
                                    <?php if (!empty($payment['transaction_id'])): ?>
                                        <span class="note-cell">TRX: <?php echo htmlspecialchars($payment['transaction_id']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($payment['description']); ?>
                                    <?php if (!empty($payment['notes'])): ?>
                                        <span class="note-cell"><?php echo htmlspecialchars($payment['notes']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-column">
                                    <button type="button" class="button button-small" onclick="openStatusModal(<?php echo $payment['id']; ?>, '<?php echo htmlspecialchars($payment['status']); ?>', '<?php echo htmlspecialchars($payment['notes'] ?? ''); ?>')">
                                        <i class="fas fa-edit"></i> Statusi
                                    </button>
                                    
                                    <?php if ($payment['status'] !== 'completed'): ?>
                                        <button type="button" class="button button-small button-danger" onclick="confirmDelete(<?php echo $payment['id']; ?>)">
                                            <i class="fas fa-trash"></i> Fshi
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-info">
                    Nuk u gjetën pagesa që përputhen me filtrat. Ju lutemi provoni kritere të tjera kërkimi.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal për ndryshimin e statusit -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('statusModal')">&times;</span>
            <h2>Ndrysho statusin e pagesës</h2>
            
            <form method="post" action="" id="statusForm">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="payment_id" id="payment_id">
                
                <div class="form-group">
                    <label for="status">Statusi</label>
                    <select id="status" name="status">
                        <option value="completed">Kompletuar</option>
                        <option value="pending">Në pritje</option>
                        <option value="failed">Dështuar</option>
                        <option value="test">Test</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="notes">Shënime</label>
                    <textarea id="notes" name="notes"></textarea>
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <button type="submit" class="button">Ruaj ndryshimet</button>
                    <button type="button" class="button button-secondary" onclick="closeModal('statusModal')">Anulo</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal për konfirmimin e fshirjes -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('deleteModal')">&times;</span>
            <h2>Konfirmo fshirjen</h2>
            
            <p>A jeni i sigurt se dëshironi të fshini këtë pagesë? Ky veprim nuk mund të kthehet.</p>
            
            <form method="post" action="" id="deleteForm">
                <input type="hidden" name="action" value="delete_payment">
                <input type="hidden" name="payment_id" id="delete_payment_id">
                
                <div class="form-group" style="margin-top: 20px;">
                    <button type="submit" class="button button-danger">Po, fshije</button>
                    <button type="button" class="button button-secondary" onclick="closeModal('deleteModal')">Anulo</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Funksionet për modal
        function openStatusModal(paymentId, status, notes) {
            document.getElementById('payment_id').value = paymentId;
            document.getElementById('status').value = status;
            document.getElementById('notes').value = notes;
            document.getElementById('statusModal').style.display = 'block';
        }
        
        function confirmDelete(paymentId) {
            document.getElementById('delete_payment_id').value = paymentId;
            document.getElementById('deleteModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Mbyllja e modalëve kur klikohet jashtë
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let modal of modals) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            }
        }
        
        // Funksion për eksportimin e të dhënave në CSV
        function exportToCSV() {
            const table = document.getElementById('payments-table');
            if (!table) return;
            
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    // Marr vetëm tekstin kryesor, jo shënimet
                    let text = cols[j].innerText.split('\n')[0];
                    // Përjashto kolonën e veprimeve
                    if (j === cols.length - 1 && i > 0) continue;
                    
                    // Pastro tekstin
                    text = text.replace(/"/g, '""'); // Dyfisho thonjëzat
                    row.push('"' + text + '"');
                }
                csv.push(row.join(','));
            }
            
            const csvString = csv.join('\n');
            const filename = 'pagesat_abonimeve_' + new Date().toISOString().slice(0, 10) + '.csv';
            
            const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            
            if (navigator.msSaveBlob) { // IE 10+
                navigator.msSaveBlob(blob, filename);
            } else {
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }
        
        // Funksion për printimin e tabelës së pagesave
        function printPayments() {
            const printWindow = window.open('', '_blank');
            
            // Krijo stilin për printin
            const style = `
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    h1 { color: #1a56db; }
                    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                    th, td { padding: 10px; border: 1px solid #e2e8f0; text-align: left; }
                    th { background-color: #f1f5f9; font-weight: bold; }
                    .status-completed { color: #16a34a; font-weight: bold; }
                    .status-failed { color: #dc2626; font-weight: bold; }
                    .status-pending { color: #f59e0b; font-weight: bold; }
                    .status-test { color: #6b7280; font-weight: bold; }
                    .note-cell { font-size: 0.85rem; color: #6b7280; display: block; margin-top: 3px; }
                    @media print {
                        body { font-size: 12px; }
                        h1 { font-size: 18px; }
                    }
                </style>
            `;
            
            // Krijo përmbajtjen
            let content = `
                <!DOCTYPE html>
                <html lang="sq">
                <head>
                    <meta charset="UTF-8">
                    <title>Pagesat e Abonimeve - Noteria</title>
                    ${style}
                </head>
                <body>
                    <h1>Pagesat e Abonimeve - Noteria</h1>
                    <div>Data e raportit: ${new Date().toLocaleDateString('sq-AL')}</div>
            `;
            
            // Shto filtrat nëse janë përdorur
            const filters = [];
            if ('<?php echo $searchTerm; ?>') filters.push(`Kërkimi: "${'<?php echo htmlspecialchars($searchTerm); ?>'}" `);
            if ('<?php echo $statusFilter; ?>') filters.push(`Statusi: "${'<?php echo htmlspecialchars($statusFilter); ?>'}" `);
            if ('<?php echo $dateStart; ?>') filters.push(`Data fillestare: "${'<?php echo htmlspecialchars($dateStart); ?>'}" `);
            if ('<?php echo $dateEnd; ?>') filters.push(`Data përfundimtare: "${'<?php echo htmlspecialchars($dateEnd); ?>'}" `);
            
            if (filters.length > 0) {
                content += `<div style="margin: 10px 0;">Filtrat e aplikuar: ${filters.join(', ')}</div>`;
            }
            
            // Shto statistikat
            content += `
                <div style="margin: 20px 0;">
                    <div style="margin-bottom: 10px;"><strong>Pagesat e suksesshme:</strong> <?php echo number_format($successStats['count']); ?> (<?php echo number_format($successStats['total'], 2); ?> EUR)</div>
                    <div style="margin-bottom: 10px;"><strong>Pagesat e dështuara:</strong> <?php echo number_format($failedStats['count']); ?></div>
                    <div style="margin-bottom: 10px;"><strong>Pagesat në pritje:</strong> <?php echo number_format($pendingStats['count']); ?></div>
                    <div><strong>Muaji aktual:</strong> <?php echo number_format($currentMonthStats['count']); ?> (<?php echo number_format($currentMonthStats['total'], 2); ?> EUR)</div>
                </div>
            `;
            
            // Kopjo tabelën, por pa kolonën e veprimeve
            const table = document.getElementById('payments-table');
            
            if (table) {
                content += '<table>';
                
                // Shto header-at
                content += '<thead><tr>';
                const headerCells = table.querySelectorAll('thead th');
                for (let i = 0; i < headerCells.length - 1; i++) { // Përjashto kolonën e fundit (veprime)
                    const headerText = headerCells[i].innerText.split('\n')[0].trim();
                    content += `<th>${headerText}</th>`;
                }
                content += '</tr></thead>';
                
                // Shto rreshtat
                content += '<tbody>';
                const rows = table.querySelectorAll('tbody tr');
                for (let row of rows) {
                    content += '<tr>';
                    const cells = row.querySelectorAll('td');
                    for (let i = 0; i < cells.length - 1; i++) { // Përjashto kolonën e fundit (veprime)
                        const cellContent = cells[i].innerHTML;
                        content += `<td>${cellContent}</td>`;
                    }
                    content += '</tr>';
                }
                content += '</tbody>';
                content += '</table>';
            } else {
                content += '<p>Nuk u gjetën pagesa.</p>';
            }
            
            content += '</body></html>';
            
            // Shfaq përmbajtjen në dritaren e re dhe printo
            printWindow.document.open();
            printWindow.document.write(content);
            printWindow.document.close();
            
            printWindow.onload = function() {
                printWindow.print();
            };
        }
    </script>
</body>
</html>