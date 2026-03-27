<?php
/**
 * Script për të ekzekutuar skemën e databazës dhe vendosur orarit
 * 
 * PËRDORIMI:
 * 1. Hap në shfletues: http://localhost/noteria/setup_database.php
 * 2. Kliko butonin për të ekzekutuar skemën dhe orarit
 */

session_start();

// KONFIGURIMI I DATABAZËS
$db_host = 'localhost';
$db_username = 'root';  // Ndryshoje nëse është ndryshe
$db_password = '';      // Ndryshoje nëse ka password
$db_name = 'noteria';

// Lidhu me MySQL
$conn = new mysqli($db_host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
    die("Gabim në lidhjen me bazën e të dhënave: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['action'] ?? null) : null;
$message = '';
$message_type = '';
$schema_created = false;
$schedules_created = false;
$stats = [
    'tables_created' => 0,
    'employees_count' => 0,
    'schedules_created' => 0
];

// ============================================
// HAPI 1: EKZEKUTO SKEMËN
// ============================================
if ($action === 'create_schema') {
    $schema_file = 'd:/Laragon/www/noteria/db/noteria_staff_schema.sql';
    
    if (file_exists($schema_file)) {
        $sql_content = file_get_contents($schema_file);
        
        // Ndaj queryet me ; dhe ekzekuto çdo një
        $queries = explode(';', $sql_content);
        $error_count = 0;
        $success_count = 0;
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                if (!$conn->query($query)) {
                    // Disa errore mund të jenë në rregull (p.sh. DROP IF NOT EXISTS)
                    if (strpos($conn->error, 'already exists') === false) {
                        $error_count++;
                    }
                } else {
                    $success_count++;
                    $stats['tables_created']++;
                }
            }
        }
        
        $message = "✓ Skema e databazës u ekzekutua! " . $success_count . " queries të suksesshëm.";
        $message_type = 'success';
        $schema_created = true;
    } else {
        $message = "✗ Dosja e skemës nuk u gjet: " . $schema_file;
        $message_type = 'error';
    }
}

// ============================================
// HAPI 2: VENDOS ORARIT
// ============================================
if ($action === 'create_schedules' || ($action === 'create_schema' && $schema_created)) {
    // Kontrollo nëse tabela punonjesit ekziston
    $table_check = $conn->query("SHOW TABLES LIKE 'punonjesit'");
    if (!$table_check || $table_check->num_rows == 0) {
        $message = "✗ Tabela 'punonjesit' nuk ekziston. Duhet të ekzekutosh skemën fillimisht!";
        $message_type = 'error';
    } else {
        // Numaro punonjesit
        $result = $conn->query("SELECT COUNT(*) as count FROM `punonjesit` WHERE `statusi` = 'aktiv'");
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['employees_count'] = $row['count'];
        }
    }
}
    
    // Deaktivizo orarit e vjetër
    $conn->query("UPDATE `oraret` SET `aktiv` = 0 WHERE `data_mbarimit` IS NULL AND `data_fillimit` < '2026-03-19'");
    
    // Krijo orarit e reja
    $insert_sql = "INSERT INTO `oraret` (
        `punonjes_id`,
        `data_fillimit`,
        `hene_fillim`, `hene_mbarim`,
        `marte_fillim`, `marte_mbarim`,
        `merkure_fillim`, `merkure_mbarim`,
        `enjte_fillim`, `enjte_mbarim`,
        `premte_fillim`, `premte_mbarim`,
        `shtune_fillim`, `shtune_mbarim`,
        `diele_fillim`, `diele_mbarim`,
        `pershkrimi`,
        `krijuar_nga`,
        `aktiv`
    )
    SELECT
        `id`,
        '2026-03-19',
        '08:00:00', '16:00:00',
        '08:00:00', '16:00:00',
        '08:00:00', '16:00:00',
        '08:00:00', '16:00:00',
        '08:00:00', '16:00:00',
        NULL, NULL,
        NULL, NULL,
        'Orari i rregullt 08:00-16:00 duke filluar pas Festa e Fitër Bajramit',
        1,
        1
    FROM `punonjesit`
    WHERE `statusi` = 'aktiv'
    AND (`data_mbarimit` IS NULL OR `data_mbarimit` >= '2026-03-19')";
    
    if ($conn->query($insert_sql)) {
        $stats['schedules_created'] = $conn->affected_rows;
        $message = "✓ Orarit u vendosën me sukses për " . $stats['schedules_created'] . " punonjës!";
        $message_type = 'success';
        $schedules_created = true;
    } else {
        $message = "✗ Gabim gjatë vendosjes së orareve: " . $conn->error;
        $message_type = 'error';
    }
}

// ============================================
// SHFAQ TABELAT E EKZISTUESE
// ============================================
$tables = [];
$result = $conn->query("SHOW TABLES FROM `noteria`");
if ($result) {
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
}

// Shfaq orarit e vendosura
$schedules = [];
$table_check = $conn->query("SHOW TABLES LIKE 'oraret'");
if ($table_check && $table_check->num_rows > 0) {
    $result = $conn->query("SELECT 
        p.id,
        p.emri,
        p.mbiemri,
        o.data_fillimit,
        TIME_FORMAT(o.hene_fillim, '%H:%i') as hene_fillim,
        TIME_FORMAT(o.hene_mbarim, '%H:%i') as hene_mbarim,
        o.aktiv
    FROM `oraret` o
    JOIN `punonjesit` p ON o.punonjes_id = p.id
    WHERE o.data_fillimit = '2026-03-19'
    AND o.aktiv = 1
    ORDER BY p.emri, p.mbiemri
    LIMIT 20");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $schedules[] = $row;
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfigurimi i Sistemit - Noteria</title>
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
            max-width: 1100px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2.2em;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 1.1em;
        }
        
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section h2 {
            color: #2c3e50;
            font-size: 1.5em;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .setup-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            margin-bottom: 16px;
        }
        
        .setup-step {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .setup-step:last-child {
            border-bottom: none;
        }
        
        .step-number {
            background: #667eea;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
        }
        
        .step-content {
            flex: 1;
        }
        
        .step-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        
        .step-description {
            color: #666;
            font-size: 0.95em;
        }
        
        .button-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        button {
            padding: 14px 28px;
            font-size: 1em;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
        }
        
        .status {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #ffc107;
        }
        
        .status.complete {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        
        .status.pending {
            background: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 15px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .info-box {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #2196f3;
            margin-bottom: 20px;
        }
        
        .info-box strong {
            color: #1565c0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚙️ Konfigurimi i Sistemit - Noteria</h1>
        <p class="subtitle">Përgatitja e databazës dhe vendosja e orareve</p>
        
        <?php if ($message): ?>
            <div class="alert <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <!-- HAPI 1: SKEMA E DATABAZËS -->
        <div class="section">
            <h2>1️⃣ Skema e Databazës</h2>
            
            <div class="setup-card">
                <div class="setup-step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <div class="step-title">Krijoni Tabelat</div>
                        <div class="step-description">Ekzekutoni skemat e databazës (zyra_noteriale, punonjesit, oraret, etj.)</div>
                    </div>
                    <div class="status <?php echo count($tables) > 0 ? 'complete' : 'pending'; ?>">
                        <span><?php echo count($tables) > 0 ? '✓ Përfunduar' : '⏳ Në pritje'; ?></span>
                    </div>
                </div>
            </div>
            
            <?php if (count($tables) > 0): ?>
                <div class="alert info">
                    ✓ Tabelat e mëposhtme ekzistojnë në bazën e të dhënave:
                    <br><strong><?php echo implode(', ', array_slice($tables, 0, 5)); ?><?php echo count($tables) > 5 ? '... (+' . (count($tables) - 5) . ' më shumë)' : ''; ?></strong>
                </div>
            <?php else: ?>
                <form method="POST" style="margin-top: 15px;">
                    <input type="hidden" name="action" value="create_schema">
                    <button type="submit" class="btn-primary">📊 Ekzekuto Skemën e Databazës</button>
                </form>
            <?php endif; ?>
        </div>
        
        <!-- HAPI 2: VENDOSJA E ORAREVE -->
        <div class="section">
            <h2>2️⃣ Vendosja e Orareve</h2>
            
            <div class="setup-card">
                <div class="setup-step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <div class="step-title">Orarit e Punës 08:00-16:00</div>
                        <div class="step-description">Data e fillimit: 19 Mars 2026 | Ditë pune: Hënë-Premte | Ditë pushimi: Shtunë-Diele</div>
                    </div>
                    <div class="status <?php echo $stats['schedules_created'] > 0 ? 'complete' : 'pending'; ?>">
                        <span><?php echo $stats['schedules_created'] > 0 ? '✓ Përfunduar' : '⏳ Në pritje'; ?></span>
                    </div>
                </div>
            </div>
            
            <?php if (count($tables) > 0): ?>
                <?php if ($stats['schedules_created'] > 0): ?>
                    <div class="alert success">
                        ✓ Orarit u vendosën për <?php echo $stats['schedules_created']; ?> punonjës!
                    </div>
                    
                    <div class="stats">
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $stats['schedules_created']; ?></div>
                            <div class="stat-label">Punonjës me Orare</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number">08:00</div>
                            <div class="stat-label">Fillim Dite</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number">16:00</div>
                            <div class="stat-label">Përfundim Dite</div>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="POST" style="margin-top: 15px;">
                        <input type="hidden" name="action" value="create_schedules">
                        <button type="submit" class="btn-success">🕐 Vendos Orarit Tani</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- LISTA E ORAREVE -->
        <?php if (!empty($schedules)): ?>
            <div class="section">
                <h2>✓ Orarit e Vendosura</h2>
                <p style="color: #666; margin-bottom: 15px;">Shfaqen 20 punonjësi të parë me orarit të vendosura:</p>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Emri</th>
                                <th>Data Fillimit</th>
                                <th>Orari (Hënë-Premte)</th>
                                <th>Statusi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schedules as $schedule): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($schedule['emri'] . ' ' . $schedule['mbiemri']); ?></td>
                                    <td><?php echo $schedule['data_fillimit']; ?></td>
                                    <td><strong><?php echo $schedule['hene_fillim']; ?> - <?php echo $schedule['hene_mbarim']; ?></strong></td>
                                    <td><span style="color: #28a745; font-weight: 600;">✓ Aktiv</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- FINALIZIM -->
        <?php if (count($tables) > 0 && $stats['schedules_created'] > 0): ?>
            <div class="section">
                <div class="alert success">
                    <strong>🎉 Sistemi u konfigurua me sukses!</strong>
                    <br>
                    Të gjitha tabelat janë krijuar dhe orarit janë vendosur. Mund ta mbyllesh këtë faqe dhe të fillosh përdorjen e sistemit.
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
