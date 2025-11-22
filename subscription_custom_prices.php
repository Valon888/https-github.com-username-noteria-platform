<?php
// subscription_custom_prices.php - Faqja për menaxhimin e çmimeve të personalizuara për noterët
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

// Merr çmimin e paracaktuar të abonimit nga sistemi
try {
    $stmt = $pdo->query("SELECT subscription_price FROM system_settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaultPrice = isset($settings['subscription_price']) ? $settings['subscription_price'] : 25.00;
} catch (PDOException $e) {
    $defaultPrice = 25.00; // Nëse nuk mund të merret nga databaza, vendos një vlerë të paracaktuar
}

// Procesi i përditësimit të të dhënave për një noter specifik
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $noterId = filter_input(INPUT_POST, 'noter_id', FILTER_VALIDATE_INT);
    
    if (!$noterId) {
        $updateMessage = "ID e noterit e pavlefshme.";
        $updateStatus = 'error';
    } else {
        try {
            if ($_POST['action'] === 'update_price') {
                // Përditëso çmimin e personalizuar
                $customPrice = filter_input(INPUT_POST, 'custom_price', FILTER_VALIDATE_FLOAT);
                $subscriptionStatus = filter_input(INPUT_POST, 'subscription_status', FILTER_SANITIZE_STRING);
                
                // Nëse çmimi është i barabartë me vlerën e paracaktuar, vendose NULL
                if ($customPrice == $defaultPrice) {
                    $customPrice = null;
                }
                
                $sql = "UPDATE noteri SET 
                        custom_price = :custom_price, 
                        subscription_status = :subscription_status 
                        WHERE id = :noter_id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'custom_price' => $customPrice,
                    'subscription_status' => $subscriptionStatus,
                    'noter_id' => $noterId
                ]);
                
                $updateMessage = "Të dhënat e abonimit për noterin u përditësuan me sukses.";
                $updateStatus = 'success';
            } 
            elseif ($_POST['action'] === 'update_bank') {
                // Përditëso të dhënat bankare
                $accountNumber = filter_input(INPUT_POST, 'account_number', FILTER_SANITIZE_STRING);
                $bankName = filter_input(INPUT_POST, 'bank_name', FILTER_SANITIZE_STRING);
                
                $sql = "UPDATE noteri SET 
                        account_number = :account_number, 
                        bank_name = :bank_name 
                        WHERE id = :noter_id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'account_number' => $accountNumber,
                    'bank_name' => $bankName,
                    'noter_id' => $noterId
                ]);
                
                $updateMessage = "Të dhënat bankare për noterin u përditësuan me sukses.";
                $updateStatus = 'success';
            }
            elseif ($_POST['action'] === 'bulk_update') {
                // Përditësim masiv i statusit të abonimit
                $selectedNoters = isset($_POST['selected_noters']) ? $_POST['selected_noters'] : [];
                $bulkStatus = filter_input(INPUT_POST, 'bulk_status', FILTER_SANITIZE_STRING);
                
                if (empty($selectedNoters) || !$bulkStatus) {
                    throw new Exception("Nuk u zgjodh asnjë noter ose statusi është i pavlefshëm.");
                }
                
                $placeholders = implode(',', array_fill(0, count($selectedNoters), '?'));
                
                $sql = "UPDATE noteri SET subscription_status = ? WHERE id IN ($placeholders)";
                
                $params = array_merge([$bulkStatus], $selectedNoters);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                $updateMessage = "Statusi i abonimit u përditësua për " . count($selectedNoters) . " noterë.";
                $updateStatus = 'success';
            }
            
        } catch (Exception $e) {
            $updateMessage = "Gabim në përditësimin e të dhënave: " . $e->getMessage();
            $updateStatus = 'error';
        }
    }
}

// Filtrat për kërkim
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$sortOrder = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';

// Ndërto query bazuar në filtrat
$sqlParams = [];
$sql = "SELECT n.id, n.emri, n.mbiemri, n.email, n.status, n.custom_price, 
               n.subscription_status, n.account_number, n.bank_name 
        FROM noteri n 
        WHERE 1=1 ";

// Shtimi i kushteve të kërkimit
if (!empty($searchTerm)) {
    $sql .= "AND (n.emri LIKE ? OR n.mbiemri LIKE ? OR n.email LIKE ?) ";
    $sqlParams[] = "%$searchTerm%";
    $sqlParams[] = "%$searchTerm%";
    $sqlParams[] = "%$searchTerm%";
}

if (!empty($statusFilter)) {
    $sql .= "AND n.subscription_status = ? ";
    $sqlParams[] = $statusFilter;
}

// Renditja
switch ($sortBy) {
    case 'name':
        $sql .= "ORDER BY n.emri $sortOrder, n.mbiemri $sortOrder";
        break;
    case 'status':
        $sql .= "ORDER BY n.subscription_status $sortOrder, n.emri ASC";
        break;
    case 'price':
        $sql .= "ORDER BY COALESCE(n.custom_price, $defaultPrice) $sortOrder, n.emri ASC";
        break;
    default:
        $sql .= "ORDER BY n.emri ASC, n.mbiemri ASC";
}

// Ekzekutimi i query
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($sqlParams);
    $noters = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $updateMessage = "Gabim në marrjen e të dhënave: " . $e->getMessage();
    $updateStatus = 'error';
    $noters = [];
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Çmimet e Personalizuara të Abonimeve | Noteria</title>
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
        
        .status-active {
            color: var(--success-color);
            font-weight: 500;
        }
        
        .status-inactive {
            color: var(--danger-color);
            font-weight: 500;
        }
        
        .status-pending {
            color: var(--warning-color);
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
        
        .button-success {
            background-color: var(--success-color);
        }
        
        .button-success:hover {
            background-color: #15803d;
        }
        
        .button-danger {
            background-color: var(--danger-color);
        }
        
        .button-danger:hover {
            background-color: #b91c1c;
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
        input[type="email"],
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background-color: white;
            font-family: inherit;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        
        input[type="text"]:focus,
        input[type="number"]:focus,
        input[type="email"]:focus,
        select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(26, 86, 219, 0.2);
        }
        
        .help-text {
            color: var(--secondary-color);
            font-size: 0.85rem;
            margin-top: 5px;
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
        
        .price-column {
            font-weight: 600;
            text-align: right;
        }
        
        .custom-price {
            color: var(--primary-color);
        }
        
        .default-price {
            color: var(--secondary-color);
        }
        
        .bulk-actions {
            background-color: #f1f5f9;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .bulk-actions select {
            width: auto;
            margin: 0 10px;
        }
        
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .info-text {
            color: var(--secondary-color);
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .search-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .bulk-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .toolbar {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .toolbar div {
                margin-top: 15px;
            }
        }
        
        .note-cell {
            font-size: 0.85rem;
            color: var(--secondary-color);
            display: block;
            margin-top: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="toolbar">
            <h1><i class="fas fa-tags"></i> Çmimet e Personalizuara të Abonimeve</h1>
            
            <div>
                <a href="subscription_settings.php" class="button">
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
        
        <div class="panel">
            <div class="info-text">
                <strong>Çmimi i paracaktuar i abonimit:</strong> <?php echo number_format($defaultPrice, 2); ?> EUR
            </div>
            
            <form method="get" action="" class="search-form">
                <div>
                    <input type="text" name="search" placeholder="Kërko sipas emrit ose emailit" value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>
                
                <div>
                    <select name="status">
                        <option value="">Të gjitha statuset</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Aktiv</option>
                        <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Joaktiv</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Në pritje</option>
                    </select>
                </div>
                
                <div>
                    <button type="submit" class="button">Kërko</button>
                    <?php if (!empty($searchTerm) || !empty($statusFilter)): ?>
                        <a href="?sort=<?php echo $sortBy; ?>&order=<?php echo strtolower($sortOrder); ?>" class="button button-secondary">Pastro</a>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($searchTerm) || !empty($statusFilter)): ?>
                    <div class="filter-text">
                        <?php 
                        $filters = [];
                        if (!empty($searchTerm)) $filters[] = "kërkimi: \"" . htmlspecialchars($searchTerm) . "\"";
                        if (!empty($statusFilter)) $filters[] = "statusi: \"" . htmlspecialchars($statusFilter) . "\"";
                        echo "Filtrat aktualë: " . implode(", ", $filters);
                        ?>
                    </div>
                <?php endif; ?>
            </form>
            
            <form method="post" action="">
                <input type="hidden" name="action" value="bulk_update">
                
                <?php if (count($noters) > 0): ?>
                    <div class="bulk-actions">
                        <div>
                            <label>
                                <input type="checkbox" id="select-all"> Zgjidh të gjithë
                            </label>
                        </div>
                        
                        <div style="display: flex; align-items: center;">
                            <span>Për noterët e zgjedhur:</span>
                            <select name="bulk_status">
                                <option value="">Zgjidhni një veprim</option>
                                <option value="active">Aktivizo abonimin</option>
                                <option value="inactive">Çaktivizo abonimin</option>
                                <option value="pending">Vendos në pritje</option>
                            </select>
                            
                            <button type="submit" class="button button-small">Apliko</button>
                        </div>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 40px;"></th>
                                <th>
                                    <a href="?sort=name&order=<?php echo $sortBy === 'name' && $sortOrder === 'ASC' ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($statusFilter); ?>">
                                        Noteri
                                        <?php if ($sortBy === 'name'): ?>
                                            <span class="sort-indicator"><?php echo $sortOrder === 'ASC' ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Email</th>
                                <th>
                                    <a href="?sort=status&order=<?php echo $sortBy === 'status' && $sortOrder === 'ASC' ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($statusFilter); ?>">
                                        Statusi
                                        <?php if ($sortBy === 'status'): ?>
                                            <span class="sort-indicator"><?php echo $sortOrder === 'ASC' ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?sort=price&order=<?php echo $sortBy === 'price' && $sortOrder === 'ASC' ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($statusFilter); ?>">
                                        Çmimi
                                        <?php if ($sortBy === 'price'): ?>
                                            <span class="sort-indicator"><?php echo $sortOrder === 'ASC' ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Të dhënat bankare</th>
                                <th>Veprime</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($noters as $noter): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_noters[]" value="<?php echo $noter['id']; ?>" class="noter-checkbox">
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($noter['emri'] . ' ' . $noter['mbiemri']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($noter['email']); ?></td>
                                    <td>
                                        <?php 
                                        $statusClass = '';
                                        $statusText = '';
                                        
                                        switch ($noter['subscription_status']) {
                                            case 'active':
                                                $statusClass = 'status-active';
                                                $statusText = 'Aktiv';
                                                break;
                                            case 'inactive':
                                                $statusClass = 'status-inactive';
                                                $statusText = 'Joaktiv';
                                                break;
                                            case 'pending':
                                                $statusClass = 'status-pending';
                                                $statusText = 'Në pritje';
                                                break;
                                            default:
                                                $statusClass = 'status-active'; // Parazgjedhja është aktiv
                                                $statusText = 'Aktiv';
                                                break;
                                        }
                                        ?>
                                        <span class="<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                    </td>
                                    <td class="price-column">
                                        <?php 
                                        $price = isset($noter['custom_price']) ? $noter['custom_price'] : $defaultPrice;
                                        $priceClass = isset($noter['custom_price']) ? 'custom-price' : 'default-price';
                                        echo '<span class="' . $priceClass . '">' . number_format($price, 2) . ' EUR</span>';
                                        
                                        if (isset($noter['custom_price'])) {
                                            echo '<span class="note-cell">Çmim i personalizuar</span>';
                                        } else {
                                            echo '<span class="note-cell">Çmim i paracaktuar</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($noter['account_number'])): ?>
                                            <?php echo htmlspecialchars($noter['account_number']); ?>
                                            <?php if (!empty($noter['bank_name'])): ?>
                                                <span class="note-cell"><?php echo htmlspecialchars($noter['bank_name']); ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="note-cell">Nuk ka të dhëna bankare</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-column">
                                        <button type="button" class="button button-small" 
                                                onclick="openPriceModal(<?php echo $noter['id']; ?>, 
                                                         '<?php echo htmlspecialchars($noter['emri'] . ' ' . $noter['mbiemri']); ?>', 
                                                         <?php echo isset($noter['custom_price']) ? $noter['custom_price'] : 'null'; ?>, 
                                                         '<?php echo htmlspecialchars($noter['subscription_status'] ?? 'active'); ?>')">
                                            <i class="fas fa-edit"></i> Çmimi
                                        </button>
                                        
                                        <button type="button" class="button button-small button-secondary" 
                                                onclick="openBankModal(<?php echo $noter['id']; ?>, 
                                                        '<?php echo htmlspecialchars($noter['emri'] . ' ' . $noter['mbiemri']); ?>', 
                                                        '<?php echo htmlspecialchars($noter['account_number'] ?? ''); ?>', 
                                                        '<?php echo htmlspecialchars($noter['bank_name'] ?? ''); ?>')">
                                            <i class="fas fa-university"></i> Banka
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info">
                        Nuk u gjetën noterë që përputhen me filtrat. Ju lutemi provoni kritere të tjera kërkimi.
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Modal për ndryshimin e çmimit -->
    <div id="priceModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closePriceModal()">&times;</span>
            <h2>Ndrysho çmimin e abonimit</h2>
            <div id="noterName" style="margin-bottom: 20px; font-weight: bold;"></div>
            
            <form method="post" action="" id="priceForm">
                <input type="hidden" name="action" value="update_price">
                <input type="hidden" name="noter_id" id="noter_id_price">
                
                <div class="form-group">
                    <label for="custom_price">Çmimi i personalizuar (EUR)</label>
                    <input type="number" id="custom_price" name="custom_price" step="0.01" min="0">
                    <div class="help-text">
                        Lini vlerën e çmimit të paracaktuar (<?php echo number_format($defaultPrice, 2); ?> EUR) për të përdorur çmimin e sistemit.
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="subscription_status">Statusi i abonimit</label>
                    <select id="subscription_status" name="subscription_status">
                        <option value="active">Aktiv</option>
                        <option value="inactive">Joaktiv</option>
                        <option value="pending">Në pritje</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <button type="submit" class="button">Ruaj ndryshimet</button>
                    <button type="button" class="button button-secondary" onclick="closePriceModal()">Anulo</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal për ndryshimin e të dhënave bankare -->
    <div id="bankModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeBankModal()">&times;</span>
            <h2>Ndrysho të dhënat bankare</h2>
            <div id="noterNameBank" style="margin-bottom: 20px; font-weight: bold;"></div>
            
            <form method="post" action="" id="bankForm">
                <input type="hidden" name="action" value="update_bank">
                <input type="hidden" name="noter_id" id="noter_id_bank">
                
                <div class="form-group">
                    <label for="account_number">Numri i llogarisë bankare</label>
                    <input type="text" id="account_number" name="account_number">
                    <div class="help-text">
                        Vendosni numrin e llogarisë bankare të noterit për pagesën automatike.
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="bank_name">Emri i bankës</label>
                    <input type="text" id="bank_name" name="bank_name">
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <button type="submit" class="button">Ruaj ndryshimet</button>
                    <button type="button" class="button button-secondary" onclick="closeBankModal()">Anulo</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Funksionet për modalin e çmimit
        function openPriceModal(id, name, price, status) {
            document.getElementById('noter_id_price').value = id;
            document.getElementById('noterName').textContent = name;
            document.getElementById('custom_price').value = price !== null ? price : <?php echo $defaultPrice; ?>;
            document.getElementById('subscription_status').value = status;
            document.getElementById('priceModal').style.display = 'block';
        }
        
        function closePriceModal() {
            document.getElementById('priceModal').style.display = 'none';
        }
        
        // Funksionet për modalin e të dhënave bankare
        function openBankModal(id, name, account, bank) {
            document.getElementById('noter_id_bank').value = id;
            document.getElementById('noterNameBank').textContent = name;
            document.getElementById('account_number').value = account;
            document.getElementById('bank_name').value = bank;
            document.getElementById('bankModal').style.display = 'block';
        }
        
        function closeBankModal() {
            document.getElementById('bankModal').style.display = 'none';
        }
        
        // Mbyllja e modalëve kur klikohet jashtë
        window.onclick = function(event) {
            const priceModal = document.getElementById('priceModal');
            const bankModal = document.getElementById('bankModal');
            
            if (event.target == priceModal) {
                priceModal.style.display = 'none';
            }
            
            if (event.target == bankModal) {
                bankModal.style.display = 'none';
            }
        }
        
        // Funksion për zgjedhjen e të gjithë checkbox-ave
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.noter-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
        
        // Përditëso "Zgjidh të gjithë" bazuar në checkbox-at individualë
        document.addEventListener('change', function(event) {
            if (event.target.classList.contains('noter-checkbox')) {
                const allCheckboxes = document.querySelectorAll('.noter-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.noter-checkbox:checked');
                const selectAll = document.getElementById('select-all');
                
                selectAll.checked = allCheckboxes.length === checkedCheckboxes.length;
                selectAll.indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
            }
        });
    </script>
</body>
</html>