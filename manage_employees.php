<?php
// filepath: d:\xampp\htdocs\noteria\manage_employees.php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
session_start();
require_once 'config.php';

$success = null;
$error = null;
$employees = [];
$zyra_info = [];

// Check if user is logged in and has admin role
$is_admin = isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Get office ID from various sources
$zyra_id = 0;
if (isset($_GET['zyra_id'])) {
    // From URL parameter
    $zyra_id = intval($_GET['zyra_id']);
} elseif (isset($_SESSION['zyra_id'])) {
    // From session (if user is associated with an office)
    $zyra_id = intval($_SESSION['zyra_id']);
} elseif (isset($_SESSION['last_registered_zyra'])) {
    // From last registration (when coming from zyrat_register.php)
    $zyra_id = intval($_SESSION['last_registered_zyra']);
}

// Log for debugging
error_log("Managing employees for zyra_id: $zyra_id, User: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not logged in'));

// Kontrollo nëse zyra ekziston
if ($zyra_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM zyrat WHERE id = ?");
    $stmt->execute([$zyra_id]);
    $zyra_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$zyra_info) {
        $error = "Zyra noteriale me ID $zyra_id nuk u gjet në databazë!";
        error_log("Office not found: $zyra_id");
    } else {
        // Kontrollo nëse përdoruesi ka të drejtë për të menaxhuar këtë zyrë
        $has_permission = $is_admin || 
                         (isset($_SESSION['zyra_id']) && $_SESSION['zyra_id'] == $zyra_id) || 
                         (isset($_SESSION['last_registered_zyra']) && $_SESSION['last_registered_zyra'] == $zyra_id);
                         
        if (!$has_permission) {
            $error = "Nuk keni të drejta për të menaxhuar këtë zyrë noteriale!";
            error_log("Permission denied for user: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not logged in'));
        } else {
            // Merr punëtorët e regjistruar deri tani
            $stmt = $pdo->prepare("SELECT * FROM punetoret WHERE zyra_id = ?");
            $stmt->execute([$zyra_id]);
            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} else {
    $error = "Zyra noteriale nuk u specifikua. Ju lutemi zgjidhni një zyrë për të menaxhuar punëtorët.";
    error_log("No office ID provided when accessing manage_employees.php");
}

// Regjistrimi i punëtorit të ri
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_employee'])) {
    $emri = trim($_POST["emri"]);
    $mbiemri = trim($_POST["mbiemri"]);
    $email = trim($_POST["email"]);
    $pozita = trim($_POST["pozita"]);
    $telefoni = trim($_POST["telefoni"] ?? '');
    
    // Validime
    if (empty($emri) || empty($mbiemri) || empty($email) || empty($pozita)) {
        $error = "Ju lutemi plotësoni të gjitha fushat e kërkuara.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email-i nuk është valid.";
    } elseif (!empty($telefoni) && !preg_match('/^\+383\d{8}$/', $telefoni)) {
        $error = "Numri i telefonit duhet të fillojë me +383 dhe të ketë gjithsej 12 shifra (p.sh. +38344123456).";
    } else {
        // Gjenerimi i një fjalëkalimi të përkohshëm
        $temp_password = bin2hex(random_bytes(8)); // 16 karaktere
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO punetoret (zyra_id, emri, mbiemri, email, pozita, telefoni, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$zyra_id, $emri, $mbiemri, $email, $pozita, $telefoni, $hashed_password])) {
            $success = "Punëtori u shtua me sukses! Fjalëkalimi i përkohshëm: " . $temp_password;
            
            // Dërgo email punëtorit me fjalëkalimin e përkohshëm (kodi për dërgimin e email-it)
            // TODO: Implement email sending functionality
            
            // Rifresh listën e punëtorëve
            $stmt = $pdo->prepare("SELECT * FROM punetoret WHERE zyra_id = ?");
            $stmt->execute([$zyra_id]);
            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $error = "Ndodhi një gabim gjatë regjistrimit të punëtorit.";
        }
    }
}

// Fshirja e punëtorit
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_employee'])) {
    $employee_id = intval($_POST['employee_id']);
    
    $stmt = $pdo->prepare("DELETE FROM punetoret WHERE id = ? AND zyra_id = ?");
    if ($stmt->execute([$employee_id, $zyra_id])) {
        $success = "Punëtori u fshi me sukses!";
        
        // Rifresh listën e punëtorëve
        $stmt = $pdo->prepare("SELECT * FROM punetoret WHERE zyra_id = ?");
        $stmt->execute([$zyra_id]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error = "Ndodhi një gabim gjatë fshirjes së punëtorit.";
    }
}

// Merr numrin e punëtorëve të lejuar për këtë zyrë
$max_punetore = isset($zyra_info['num_punetore']) ? $zyra_info['num_punetore'] : 0;
$current_punetore = count($employees);
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menaxho Punëtorët | Noteria</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2d6cdf;
            --primary-dark: #184fa3;
            --primary-light: #e2eafc;
            --primary-lighter: #f8fafc;
            --success: #388e3c;
            --success-light: #eafaf1;
            --error: #d32f2f;
            --error-light: #ffeaea;
            --dark: #333;
            --gray: #888;
            --white: #fff;
            --shadow: rgba(44,108,223,0.12);
            --transition: all 0.3s ease;
        }
        
        body { 
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-lighter) 100%);
            font-family: 'Montserrat', sans-serif; 
            margin: 0; 
            padding: 0;
            min-height: 100vh;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .container { 
            width: 100%;
            max-width: 800px; 
            margin: 40px auto;
            background: var(--white);
            border-radius: 16px; 
            box-shadow: 0 12px 32px var(--shadow);
            padding: 36px 32px; 
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: var(--primary);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .logo i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 12px;
        }
        
        h2 { 
            color: var(--primary);
            margin-bottom: 25px; 
            font-size: 1.8rem; 
            font-weight: 700;
            letter-spacing: -0.3px;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 5px;
        }
        
        .form-group { 
            margin-bottom: 18px; 
            text-align: left;
            flex: 1;
            position: relative;
        }
        
        .form-group i {
            position: absolute;
            left: 12px;
            top: 42px;
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        label { 
            display: block; 
            margin-bottom: 8px; 
            color: var(--dark); 
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        input[type="text"], input[type="email"], input[type="number"], select { 
            width: 100%; 
            padding: 12px 12px 12px 35px; 
            border: 1px solid #e2e8f0; 
            border-radius: 8px; 
            font-size: 0.95rem; 
            background: var(--white); 
            transition: var(--transition);
            box-sizing: border-box;
        }
        
        input:focus, select:focus { 
            border-color: var(--primary); 
            box-shadow: 0 0 0 3px var(--primary-light);
            outline: none;
        }
        
        button, .btn { 
            background: var(--primary); 
            color: var(--white); 
            border: none; 
            border-radius: 8px; 
            padding: 12px 25px; 
            font-size: 0.95rem; 
            font-weight: 600; 
            cursor: pointer; 
            transition: var(--transition);
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(44,108,223,0.25);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        button:hover, .btn:hover { 
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(44,108,223,0.3);
        }
        
        button:active, .btn:active {
            transform: translateY(1px);
        }
        
        .success { 
            color: var(--success);
            background: var(--success-light);
            border-left: 4px solid var(--success);
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 22px; 
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            text-align: left;
        }
        
        .success i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        .error { 
            color: var(--error);
            background: var(--error-light);
            border-left: 4px solid var(--error);
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 22px; 
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            text-align: left;
        }
        
        .error i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        .section-title { 
            color: var(--primary-dark);
            margin-top: 28px; 
            margin-bottom: 15px; 
            font-size: 1.1rem; 
            font-weight: 700;
            border-bottom: 1px solid var(--primary-light);
            padding-bottom: 8px;
            display: flex;
            align-items: center;
            text-align: left;
        }
        
        .section-title i {
            margin-right: 10px;
            color: var(--primary);
        }
        
        .help-text {
            color: var(--gray);
            font-size: 0.85rem;
            margin-top: 5px;
            display: block;
        }
        
        .employees-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .employees-table th, .employees-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .employees-table th {
            background-color: var(--primary-light);
            color: var(--primary-dark);
            font-weight: 600;
        }
        
        .employees-table tr:hover {
            background-color: rgba(226, 234, 252, 0.5);
        }
        
        .employees-table .actions {
            text-align: center;
        }
        
        .btn-delete {
            background: var(--error);
            padding: 8px 12px;
            font-size: 0.8rem;
        }
        
        .btn-delete:hover {
            background: #b71c1c;
        }
        
        .progress-container {
            margin: 20px 0;
            background-color: #e2e8f0;
            border-radius: 8px;
            height: 15px;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            background-color: var(--primary);
            border-radius: 8px;
        }
        
        .status-text {
            margin-top: 5px;
            font-size: 0.9rem;
            color: var(--dark);
            font-weight: 500;
        }
        
        .office-info {
            background-color: var(--primary-light);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: left;
        }
        
        .office-info h3 {
            margin-top: 0;
            color: var(--primary-dark);
            font-size: 1.2rem;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 8px;
        }
        
        .info-label {
            font-weight: 600;
            width: 100px;
            color: var(--dark);
        }
        
        .info-value {
            flex: 1;
        }
        
        .office-selection {
            margin: 20px 0;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            text-align: left;
        }
        
        .office-selection h3 {
            margin-top: 0;
            color: var(--primary-dark);
            font-size: 1.2rem;
        }
        
        .office-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .office-item a {
            display: block;
            width: 100%;
            text-align: left;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 20px;
                padding: 30px 20px;
                max-width: none;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .employees-table {
                font-size: 0.85rem;
            }
            
            .employees-table th, .employees-table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <i class="fas fa-users"></i>
        </div>
        <h2>Menaxhimi i Punëtorëve</h2>
        
        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($zyra_info): ?>
            <div class="office-info">
                <h3><i class="fas fa-building"></i> Të dhënat e zyrës</h3>
                <div class="info-row">
                    <div class="info-label">Emri:</div>
                    <div class="info-value"><?php echo htmlspecialchars($zyra_info['emri']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Qyteti:</div>
                    <div class="info-value"><?php echo htmlspecialchars($zyra_info['qyteti']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Adresa:</div>
                    <div class="info-value"><?php echo htmlspecialchars($zyra_info['adresa']); ?></div>
                </div>
            </div>
            
            <div class="section-title"><i class="fas fa-user-plus"></i> Regjistrimi i punëtorëve</div>
            
            <div class="progress-container">
                <div class="progress-bar" style="width: <?php echo ($current_punetore / $max_punetore) * 100; ?>%"></div>
            </div>
            <div class="status-text">
                <?php echo $current_punetore; ?> nga <?php echo $max_punetore; ?> punëtorë të regjistruar
                (<?php echo round(($current_punetore / $max_punetore) * 100); ?>%)
            </div>
            
            <?php if ($current_punetore < $max_punetore): ?>
                <form method="POST" id="employeeForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="emri">Emri:</label>
                            <i class="fas fa-user"></i>
                            <input type="text" name="emri" id="emri" required>
                        </div>
                        <div class="form-group">
                            <label for="mbiemri">Mbiemri:</label>
                            <i class="fas fa-user"></i>
                            <input type="text" name="mbiemri" id="mbiemri" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" id="email" required>
                            <span class="help-text">Email-i do të përdoret për kyçje në platformë</span>
                        </div>
                        <div class="form-group">
                            <label for="telefoni">Numri i telefonit:</label>
                            <i class="fas fa-phone"></i>
                            <input type="text" name="telefoni" id="telefoni" placeholder="+38344123456">
                            <span class="help-text">Opsional, formati: +383 dhe 8 shifra</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="pozita">Pozita në zyrë:</label>
                        <i class="fas fa-briefcase"></i>
                        <select name="pozita" id="pozita" required>
                            <option value="">Zgjidhni pozitën</option>
                            <option value="Noter">Noter</option>
                            <option value="Sekretar">Sekretar/e</option>
                            <option value="Asistent">Asistent/e</option>
                            <option value="Administrator">Administrator</option>
                            <option value="Praktikant">Praktikant</option>
                            <option value="Tjeter">Tjetër</option>
                        </select>
                    </div>
                    
                    <input type="hidden" name="add_employee" value="1">
                    <button type="submit"><i class="fas fa-plus"></i> Shto Punëtorin</button>
                </form>
            <?php else: ?>
                <div class="error">
                    <i class="fas fa-exclamation-triangle"></i>
                    Keni arritur numrin maksimal të punëtorëve (<?php echo $max_punetore; ?>). Për të shtuar më shumë punëtorë, ju lutemi kontaktoni administratorin.
                </div>
            <?php endif; ?>
            
            <div class="section-title"><i class="fas fa-user-friends"></i> Lista e punëtorëve</div>
            
            <?php if (count($employees) > 0): ?>
                <table class="employees-table">
                    <thead>
                        <tr>
                            <th>Nr.</th>
                            <th>Emri</th>
                            <th>Mbiemri</th>
                            <th>Email</th>
                            <th>Pozita</th>
                            <th>Veprime</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $count = 1; foreach ($employees as $emp): ?>
                            <tr>
                                <td><?php echo $count++; ?></td>
                                <td><?php echo htmlspecialchars($emp['emri']); ?></td>
                                <td><?php echo htmlspecialchars($emp['mbiemri']); ?></td>
                                <td><?php echo htmlspecialchars($emp['email']); ?></td>
                                <td><?php echo htmlspecialchars($emp['pozita']); ?></td>
                                <td class="actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="employee_id" value="<?php echo $emp['id']; ?>">
                                        <input type="hidden" name="delete_employee" value="1">
                                        <button type="submit" class="btn-delete" onclick="return confirm('A jeni i sigurt që dëshironi ta fshini punëtorin?');">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="help-text" style="text-align: center; padding: 20px;">
                    <i class="fas fa-info-circle"></i> Nuk ka punëtorë të regjistruar ende. Përdorni formën më lart për të shtuar punëtorë.
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 30px;">
                <a href="dashboard.php" class="btn" style="background-color: #6c757d;">
                    <i class="fas fa-arrow-left"></i> Kthehu në Dashboard
                </a>
            </div>
        <?php else: ?>
            <?php 
            // Check if user has access to any offices
            $available_offices = [];
            
            if ($is_admin) {
                // Admin can see all offices
                $stmt = $pdo->query("SELECT id, emri, qyteti FROM zyrat ORDER BY emri");
                $available_offices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif (isset($_SESSION['user_id'])) {
                // Regular users can only see their associated office
                $stmt = $pdo->prepare("SELECT z.id, z.emri, z.qyteti FROM zyrat z 
                                     JOIN users u ON z.id = u.zyra_id 
                                     WHERE u.id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $available_offices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            if (count($available_offices) > 0):
            ?>
                <div class="error">
                    <i class="fas fa-exclamation-triangle"></i>
                    Ju lutemi zgjidhni një zyrë noteriale për të menaxhuar punëtorët e saj:
                </div>
                
                <div class="office-selection">
                    <h3>Zgjidhni zyrën noteriale:</h3>
                    <div class="office-list">
                        <?php foreach ($available_offices as $office): ?>
                            <div class="office-item">
                                <a href="manage_employees.php?zyra_id=<?php echo $office['id']; ?>" class="btn">
                                    <i class="fas fa-building"></i>
                                    <?php echo htmlspecialchars($office['emri']); ?> (<?php echo htmlspecialchars($office['qyteti']); ?>)
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="error">
                    <i class="fas fa-exclamation-triangle"></i>
                    Zyra noteriale nuk u gjet ose nuk keni të drejta për të menaxhuar asnjë zyrë. 
                    Ju lutemi kthehuni dhe provoni përsëri.
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 30px;">
                <a href="dashboard.php" class="btn">
                    <i class="fas fa-arrow-left"></i> Kthehu në Dashboard
                </a>
                <?php if ($is_admin): ?>
                <a href="zyrat_register.php" class="btn" style="background-color: #388e3c;">
                    <i class="fas fa-plus-circle"></i> Regjistro Zyrë të Re
                </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>