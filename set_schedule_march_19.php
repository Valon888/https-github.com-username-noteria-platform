<?php
/**
 * Script për të vendosur orarir e punës duke filluar nga 19 Mars 2026
 * 
 * PËRDORIMI:
 * 1. Ruaj këtë file si "set_schedule_march_19.php" në root directory të projektit
 * 2. Hap në shfletues: http://localhost/noteria/set_schedule_march_19.php
 * 3. Kliko butonin e konfirmimit për të ekzekutuar
 * 
 * OSE PËRDOR SQL SCRIPTIN DIREKT:
 * phpMyAdmin -> SQL tab -> Kopjo/pastezo përmbajtjen e update_schedule_march_19_2026.sql
 */

// Kontrollo nëse ky ështe POST request (form submission)
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['action'] ?? null) : null;

// ============================================
// KONFIGURIM DATABAZE
// ============================================
$db_host = 'localhost';
$db_username = 'root';  // Ndryshoje nëse është ndryshe
$db_password = '';      // Ndryshoje nëse ka password
$db_name = 'noteria';   // Emri i databazës

// Provo lidhjen
$conn = new mysqli($db_host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
    die("Gabim në lidhjen me bazën e të dhënave: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// ============================================
// PROCESIMI I KËRKESËS
// ============================================
$result_message = '';
$result_type = '';  // 'success', 'error', 'info'
$schedule_data = [];

if ($action === 'create_schedule') {
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
        1
    FROM `punonjesit`
    WHERE `statusi` = 'aktiv'";
    
    if ($conn->query($insert_sql)) {
        $result_type = 'success';
        $result_message = '✓ Orarit u vendosën me sukses! ' . $conn->affected_rows . ' punonjës u përditësuan.';
    } else {
        $result_type = 'error';
        $result_message = '✗ Gabim gjatë vendosjes së orareve: ' . $conn->error;
    }
}

// ============================================
// SHFAQ ORARIT E VENDOSURA
// ============================================
$schedules = [];
$query = "SELECT 
    p.id,
    p.emri,
    p.mbiemri,
    o.data_fillimit,
    TIME_FORMAT(o.hene_fillim, '%H:%i') as hene_fillim,
    TIME_FORMAT(o.hene_mbarim, '%H:%i') as hene_mbarim,
    o.pershkrimi,
    o.aktiv
FROM `oraret` o
JOIN `punonjesit` p ON o.punonjes_id = p.id
WHERE o.data_fillimit = '2026-03-19'
AND o.aktiv = 1
ORDER BY p.emri, p.mbiemri";

$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
}

// Kontrolloje punonjesit pa orare
$unscheduled = [];
$query = "SELECT p.id, p.emri, p.mbiemri, COUNT(o.id) as schedule_count
FROM `punonjesit` p
LEFT JOIN `oraret` o ON p.id = o.punonjes_id AND o.aktiv = 1
WHERE p.statusi = 'aktiv'
GROUP BY p.id
HAVING COUNT(o.id) = 0
ORDER BY p.emri";

$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $unscheduled[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendosja e Orareve - 19 Mars 2026</title>
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
        
        .schedule-grid {
            display: grid;
            gap: 16px;
        }
        
        .schedule-card {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 20px;
        }
        
        .schedule-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1em;
        }
        
        .schedule-time {
            color: #555;
            font-size: 0.95em;
        }
        
        .schedule-time strong {
            color: #667eea;
        }
        
        .unscheduled-list {
            list-style: none;
        }
        
        .unscheduled-list li {
            padding: 12px;
            background: #fff3cd;
            margin-bottom: 8px;
            border-radius: 6px;
            border-left: 4px solid #ffc107;
            color: #856404;
        }
        
        .button-group {
            display: flex;
            gap: 12px;
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
        
        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .info-box {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #2196f3;
            margin-bottom: 20px;
        }
        
        .info-box h3 {
            color: #1565c0;
            margin-bottom: 10px;
        }
        
        .info-box ul {
            margin-left: 20px;
            color: #0d47a1;
        }
        
        .info-box li {
            margin-bottom: 6px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
            color: #2c3e50;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .confirmation-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .confirmation-modal.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 90%;
        }
        
        .modal-content h2 {
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        .modal-content p {
            margin-bottom: 10px;
            color: #555;
            line-height: 1.6;
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            justify-content: flex-end;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>🕐 Vendosja e Orareve</h1>
        <p class="subtitle">Orari i rregullt për të gjithë punonjesit, duke filluar nga 19 Mars 2026</p>
        
        <?php if ($result_message): ?>
            <div class="alert <?php echo $result_type; ?>">
                <?php echo $result_message; ?>
            </div>
        <?php endif; ?>
        
        <!-- SISTEMI I INFORMACIONIT -->
        <div class="section">
            <div class="info-box">
                <h3>📋 Informacioni i Vendosjes</h3>
                <ul>
                    <li><strong>Data e fillimit:</strong> 19 Mars 2026 (pas Festa e Fitër Bajramit)</li>
                    <li><strong>Orari ditor:</strong> 08:00 - 16:00</li>
                    <li><strong>Ditë pune:</strong> Hënë - Premte</li>
                    <li><strong>Ditë pushimi:</strong> Shtunë - Diele</li>
                    <li><strong>Zbatohet për:</strong> Të gjithë punonjesit aktivë</li>
                </ul>
            </div>
        </div>
        
        <!-- BUTON AKSIONI -->
        <div class="section">
            <?php if (empty($schedules)): ?>
                <div class="button-group">
                    <button class="btn-primary" onclick="showConfirmation()">
                        ✓ Vendos Orarit Tani
                    </button>
                </div>
                <p style="color: #666; font-size: 0.95em;">
                    Kjo ekzekutar SQL query për të krijuar orarit të reja në bazën e të dhënave.
                </p>
            <?php else: ?>
                <div class="alert info">
                    ℹ️ Orarit janë tashmë të vendosur për 19 Mars 2026. Shiko detajet më poshtë.
                </div>
            <?php endif; ?>
        </div>
        
        <!-- STATISTIKA -->
        <?php if (!empty($schedules)): ?>
            <div class="section">
                <div class="stats">
                    <div class="stat-box">
                        <div class="stat-number"><?php echo count($schedules); ?></div>
                        <div class="stat-label">Punonjës me Orare</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo count($unscheduled); ?></div>
                        <div class="stat-label">Pa Orare</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- LISTA E ORAREVE -->
        <?php if (!empty($schedules)): ?>
            <div class="section">
                <h2>✓ Orarit e Vendosura</h2>
                <table>
                    <tr>
                        <th>Emri</th>
                        <th>Data Fillimit</th>
                        <th>Orari (Hënë-Premte)</th>
                        <th>Statusi</th>
                    </tr>
                    <?php foreach ($schedules as $schedule): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($schedule['emri'] . ' ' . $schedule['mbiemri']); ?></td>
                            <td><?php echo $schedule['data_fillimit']; ?></td>
                            <td><strong><?php echo $schedule['hene_fillim']; ?> - <?php echo $schedule['hene_mbarim']; ?></strong></td>
                            <td><span style="color: #28a745; font-weight: 600;">Aktiv</span></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
        
        <!-- LISTA E PUNONJËSVE PA ORARE -->
        <?php if (!empty($unscheduled)): ?>
            <div class="section">
                <h2>⚠️ Punonjës pa Orare</h2>
                <ul class="unscheduled-list">
                    <?php foreach ($unscheduled as $emp): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($emp['emri'] . ' ' . $emp['mbiemri']); ?></strong>
                            <br>
                            <small>ID: <?php echo $emp['id']; ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- MODAL I KONFIRMIMIT -->
    <div class="confirmation-modal" id="confirmationModal">
        <div class="modal-content">
            <h2>🔒 Konfirmo Vendosjen e Orareve</h2>
            <p>Kjo veprim do të:</p>
            <ul style="margin-left: 20px; margin-bottom: 15px;">
                <li>Deaktivizojë orarit e vjetër</li>
                <li>Krijoni orarit të reja për të gjithë punonjesit aktivë</li>
                <li>Vendosje orare: <strong>08:00 - 16:00</strong> (Hënë-Premte)</li>
                <li>Data e fillimit: <strong>19 Mars 2026</strong></li>
            </ul>
            <p style="color: #d32f2f; font-weight: 600; margin-top: 15px;">
                ⚠️ Kjo veprim nuk mund të anulohet lehtë. Sigurohu që të dhënat janë të sakta.
            </p>
            
            <div class="modal-buttons">
                <button class="btn-secondary" onclick="hideConfirmation()">Anulo</button>
                <button class="btn-primary" onclick="submitSchedule()">Vazhdo me Vendosjen</button>
            </div>
        </div>
    </div>
    
    <!-- FORMA E FSHEHUR -->
    <form id="scheduleForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="create_schedule">
    </form>
    
    <script>
        function showConfirmation() {
            document.getElementById('confirmationModal').classList.add('show');
        }
        
        function hideConfirmation() {
            document.getElementById('confirmationModal').classList.remove('show');
        }
        
        function submitSchedule() {
            document.getElementById('scheduleForm').submit();
        }
        
        // Mbyll modal-in kur klikon përjashta
        document.getElementById('confirmationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideConfirmation();
            }
        });
    </script>
</body>
</html>
