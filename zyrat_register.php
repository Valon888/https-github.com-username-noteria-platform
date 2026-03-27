<?php
// filepath: c:\xampp\htdocs\noteria\zyrat_register.php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
session_start();
require_once 'config.php';

// Përcaktojmë planet fikse sipas kërkesës
$plans = [
    [
        'id' => 1, 
        'emri' => 'Abonim Mujor', 
        'cmimi' => 30.00, 
        'kohezgjatja' => 1,
        'label' => 'Abonim Mujor - 30.00 €'
    ],
    [
        'id' => 2, 
        'emri' => 'Abonim Vjetor', 
        'cmimi' => 360.00, 
        'kohezgjatja' => 12,
        'label' => 'Abonim Vjetor - 360.00 € (Kurseni 240€)'
    ]
];

// Provo të marrësh ID-të reale nga databaza nëse ekzistojnë, përndryshe përdor ID fiktive
try {
    $stmt_plans = $pdo->query("SELECT id, kohezgjatja FROM abonimet WHERE status = 'aktiv'");
    $db_plans = $stmt_plans->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($db_plans as $db_plan) {
        if ($db_plan['kohezgjatja'] == 1) {
            $plans[0]['id'] = $db_plan['id'];
        } elseif ($db_plan['kohezgjatja'] == 12) {
            $plans[1]['id'] = $db_plan['id'];
        }
    }
} catch (Exception $e) {
    // Injoro gabimet e DB, përdor ID default
}

$success = null;
$error = null;

// Lista e qyteteve të Kosovës
$qytetet = [
    "Prishtinë", "Mitrovicë", "Pejë", "Gjakovë", "Ferizaj", "Gjilan", "Prizren",
    "Vushtrri", "Fushë Kosovë", "Podujevë", "Suharekë", "Rahovec", "Drenas",
    "Malishevë", "Lipjan", "Deçan", "Istog", "Kamenicë", "Dragash", "Kaçanik",
    "Obiliq", "Klinë", "Viti", "Skenderaj", "Shtime", "Shtërpcë", "Novobërdë",
    "Mamushë", "Junik", "Hani i Elezit", "Zubin Potok", "Zveçan", "Leposaviq",
    "Graçanicë", "Ranillug", "Kllokot", "Parteš", "Mitrovicë e Veriut"
];

// Lista e operatorëve telefonikë në Kosovë
$operatoret = [
    "Vala" => "+383 4",
    "IPKO" => "+383 5"
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Debugging logs
    error_log("Registration attempt started in zyrat_register.php");
    // error_log("POST Data: " . print_r($_POST, true)); // Uncomment for detailed data logging

    $emri = trim($_POST["emri"]);
    $fjalekalimi = trim($_POST["fjalekalimi"] ?? '');
    $qyteti = $_POST["qyteti"] ?? '';
    $adresa = trim($_POST["adresa"] ?? '');
    $email = trim($_POST["email"]);
    $email2 = trim($_POST["email2"]);
    $telefoni = trim($_POST["telefoni"]);
    $operator = $_POST["operator"] ?? '';
    $shteti = "Kosova";
    $banka = trim($_POST["banka"] ?? '');
    $iban = trim($_POST["iban"] ?? '');
    $llogaria = trim($_POST["llogaria"] ?? '');
    $pagesa = trim($_POST["pagesa"] ?? '');
    // Get abonim_id from POST or session
    $abonim_id = intval($_POST["abonim_id"] ?? 0);
    if ($abonim_id <= 0 && isset($_SESSION['selected_abonim']['id'])) {
        $abonim_id = intval($_SESSION['selected_abonim']['id']);
    }
    $payment_method = trim($_POST["payment_method"] ?? $_SESSION['selected_abonim']['payment_method'] ?? 'bank_transfer');
    
    // Të dhënat fiskale dhe të licencimit
    $numri_fiskal = trim($_POST["numri_fiskal"] ?? '');
    $numri_biznesit = trim($_POST["numri_biznesit"] ?? '');
    $numri_licences = trim($_POST["numri_licences"] ?? '');
    $data_licences = trim($_POST["data_licences"] ?? '');

    // Validime
    // Kontrollo nëse email-i ekziston në tabelën zyrat
    $stmt_check = $pdo->prepare("SELECT id FROM zyrat WHERE email = ? LIMIT 1");
    $stmt_check->execute([$email]);
    if ($stmt_check->fetch()) {
        $error = "Ky email ekziston tashmë në sistem. Ju lutemi përdorni një email tjetër.";
        error_log("Registration failed: Email already exists");
    } elseif (
        empty($emri) || empty($qyteti) || empty($adresa) || empty($email) || empty($email2) || 
        empty($telefoni) || empty($operator) || empty($banka) || empty($iban) || 
        empty($llogaria) || empty($pagesa) || empty($numri_fiskal) || 
        empty($numri_biznesit) || empty($numri_licences) || empty($data_licences) || empty($fjalekalimi)
    ) {
        $error = "Ju lutemi plotësoni të gjitha fushat e kërkuara.";
        error_log("Validation failed: Empty fields");
    } elseif (strlen($fjalekalimi) < 8) {
        $error = "Fjalëkalimi duhet të ketë të paktën 8 karaktere.";
        error_log("Validation failed: Password too short");
    } elseif (!in_array($qyteti, $qytetet)) {
        $error = "Qyteti i zgjedhur nuk është valid.";
        error_log("Validation failed: Invalid city");
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email-i nuk është valid.";
        error_log("Validation failed: Invalid email");
    } elseif ($email !== $email2) {
        $error = "Email-at nuk përputhen.";
        error_log("Validation failed: Emails do not match");
    } elseif (!preg_match('/^\+383\d{8}$/', $telefoni)) {
        $error = "Numri i telefonit duhet të fillojë me +383 dhe të ketë gjithsej 12 shifra (p.sh. +38344123456).";
        error_log("Validation failed: Invalid phone format: $telefoni");
    } elseif (!array_key_exists($operator, $operatoret)) {
        $error = "Ju lutemi zgjidhni një operator valid të telefonisë mobile.";
        error_log("Validation failed: Invalid operator");
    } elseif (!preg_match('/^[A-Z0-9]{15,34}$/', $iban)) {
        $error = "IBAN nuk është valid.";
        error_log("Validation failed: Invalid IBAN");
    } elseif (!preg_match('/^\d{8,20}$/', $llogaria)) {
        $error = "Numri i llogarisë duhet të përmbajë vetëm shifra (8-20 shifra).";
        error_log("Validation failed: Invalid account number");
    } elseif (!is_numeric($pagesa) || $pagesa < 10) {
        $error = "Shuma e pagesës duhet të jetë numerike dhe të paktën 10€.";
        error_log("Validation failed: Invalid payment amount");
    } elseif (!preg_match('/^\d{9}$/', $numri_fiskal)) {
        $error = "Numri fiskal duhet të përmbajë 9 shifra.";
        error_log("Validation failed: Invalid fiscal number");
    } elseif (!preg_match('/^[A-Z0-9]{10}$/', $numri_biznesit)) {
        $error = "Numri i biznesit nga ARBK duhet të përmbajë 10 karaktere alfanumerike.";
        error_log("Validation failed: Invalid business number");
    } elseif (!preg_match('/^[A-Z0-9]{5,15}$/', $numri_licences)) {
        $error = "Numri i licencës duhet të përmbajë 5-15 karaktere alfanumerike.";
        error_log("Validation failed: Invalid license number");
    } else {
        try {
            // Auto-fix: Check if fjalekalimi column exists and add it if missing
            try {
                $checkPwd = $pdo->query("SHOW COLUMNS FROM zyrat LIKE 'fjalekalimi'");
                if ($checkPwd->rowCount() == 0) {
                    $pdo->exec("ALTER TABLE zyrat ADD COLUMN fjalekalimi VARCHAR(255) NULL");
                    error_log("Added missing column 'fjalekalimi' to 'zyrat' table");
                }
            } catch (Exception $e) {
                error_log("Failed to check/add fjalekalimi column: " . $e->getMessage());
            }

            // Check columns BEFORE transaction
            $columnExists = false;
            try {
                $checkColumnStmt = $pdo->prepare("SHOW COLUMNS FROM zyrat LIKE 'abonim_id'");
                $checkColumnStmt->execute();
                $columnExists = ($checkColumnStmt->rowCount() > 0);
            } catch (PDOException $e) {
                $columnExists = false;
            }

            $logColumnExists = false;
            try {
                $checkLogColumnStmt = $pdo->prepare("SHOW COLUMNS FROM payment_logs LIKE 'abonim_id'");
                $checkLogColumnStmt->execute();
                $logColumnExists = ($checkLogColumnStmt->rowCount() > 0);
            } catch (PDOException $e) {
                $logColumnExists = false;
            }

            $pdo->beginTransaction();

            // Të dhënat e stafit - merri përpara se të përdoren në query
            $emri_noterit = trim($_POST["emri_noterit"] ?? '');
            $vitet_pervoje = intval($_POST["vitet_pervoje"] ?? 0);
            $numri_punetoreve = intval($_POST["numri_punetoreve"] ?? 1);
            $gjuhet = trim($_POST["gjuhet"] ?? '');
            
            // Të dhënat e punëtorëve
            $staff_names = $_POST["staff_name"] ?? [];
            $staff_positions = $_POST["staff_position"] ?? [];
            
            // Përgatit të dhënat e stafit në JSON
            $staff_data = [];
            for ($i = 0; $i < count($staff_names); $i++) {
                if (!empty($staff_names[$i])) {
                    $staff_data[] = [
                        'emri' => $staff_names[$i],
                        'pozita' => $staff_positions[$i] ?? ''
                    ];
                }
            }
            $staff_json = json_encode($staff_data, JSON_UNESCAPED_UNICODE);
            
            // Abonim ID from session if available - USE THE ONE FROM TOP OF SCRIPT
            // $abonim_id = isset($_SESSION['selected_abonim']['id']) ? $_SESSION['selected_abonim']['id'] : null;
            if ($abonim_id <= 0) {
                $abonim_id = null;
            }
            
            // Prepare the SQL statement based on whether the column exists
            if ($columnExists) {
                $stmt = $pdo->prepare("
                    INSERT INTO zyrat (
                        emri, qyteti, adresa, shteti, lloji_biznesit, email, telefoni, operator, 
                        banka, iban, llogaria, pagesa, nr_fiskal, nr_biznesi, 
                        numri_licences, data_licences, emri_noterit, vitet_pervoje,
                        numri_punetoreve, gjuhet, staff_data, fjalekalimi, data_regjistrimit, abonim_id
                    ) VALUES (?, ?, ?, ?, 'Zyre Noteriale', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                ");
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO zyrat (
                        emri, qyteti, adresa, shteti, lloji_biznesit, email, telefoni, operator, 
                        banka, iban, llogaria, pagesa, nr_fiskal, nr_biznesi, 
                        numri_licences, data_licences, emri_noterit, vitet_pervoje,
                        numri_punetoreve, gjuhet, staff_data, fjalekalimi, data_regjistrimit
                    ) VALUES (?, ?, ?, ?, 'Zyre Noteriale', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
            }
            
            // Execute with or without abonim_id based on column existence
            $fjalekalimi_hash = password_hash($fjalekalimi, PASSWORD_DEFAULT);
            $params = [
                $emri, $qyteti, $adresa, $shteti, $email, $telefoni, $operator,
                $banka, $iban, $llogaria, $pagesa, $numri_fiskal, $numri_biznesit, 
                $numri_licences, $data_licences, $emri_noterit, $vitet_pervoje,
                $numri_punetoreve, $gjuhet, $staff_json, $fjalekalimi_hash
            ];
            
            // Only add abonim_id parameter if column exists
            if ($columnExists) {
                $params[] = $abonim_id;
            }
            
            if (!$stmt->execute($params)) {
                throw new Exception("Gabim gjatë regjistrimit të zyrës: " . implode(", ", $stmt->errorInfo()));
            }
            
            $zyra_id = $pdo->lastInsertId();
            
            // Gjenerimi i ID-së së transaksionit
            $transaction_id = 'TXN_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
            
            // Upload file if provided
            $file_path = null;
            $payment_proof = $_FILES['payment_proof'] ?? null;
            if ($payment_proof && $payment_proof['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/payment_proofs/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $file_path = $upload_dir . $transaction_id . '.' . pathinfo($payment_proof['name'], PATHINFO_EXTENSION);
                move_uploaded_file($payment_proof['tmp_name'], $file_path);
            }

            // Shto në payment_logs për verifikim të shpejtë me të dhëna telefoni
            $payment_details = "IBAN: $iban, Banka: $banka, Llogaria: $llogaria, Numri Fiskal: $numri_fiskal";
            $payment_method = isset($_SESSION['payment_method']) ? $_SESSION['payment_method'] : "bank_transfer";
            
            // Add subscription details if available
            if (isset($_SESSION['selected_abonim_id'])) {
                $payment_details .= ", Abonim ID: " . $_SESSION['selected_abonim_id'];
                
                // If we have the plan price, use that instead of the default payment amount
                if (isset($_SESSION['selected_abonim']['price'])) {
                    $pagesa = $_SESSION['selected_abonim']['price'];
                }
            }
            // Get abonim_id from session if it exists
            $abonim_id_param = isset($_SESSION['selected_abonim']['id']) ? $_SESSION['selected_abonim']['id'] : null;
            
            // Check columns for payment_logs
            $logColumnExists = false;
            $amountColumnExists = false;
            try {
                $checkLogColumnStmt = $pdo->prepare("SHOW COLUMNS FROM payment_logs LIKE 'abonim_id'");
                $checkLogColumnStmt->execute();
                $logColumnExists = ($checkLogColumnStmt->rowCount() > 0);
                
                $checkAmountStmt = $pdo->prepare("SHOW COLUMNS FROM payment_logs LIKE 'amount'");
                $checkAmountStmt->execute();
                $amountColumnExists = ($checkAmountStmt->rowCount() > 0);
            } catch (PDOException $e) {
                $logColumnExists = false;
                $amountColumnExists = false;
            }

            // Prepare SQL based on column existence
            $sql = "INSERT INTO payment_logs (office_email, office_name, phone_number, operator, payment_method, payment_amount, payment_details, transaction_id, verification_status, file_path, numri_fiskal, numri_biznesit, created_at";
            
            if ($logColumnExists) $sql .= ", abonim_id";
            if ($amountColumnExists) $sql .= ", amount";
            
            $sql .= ") VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, NOW()";
            
            if ($logColumnExists) $sql .= ", ?";
            if ($amountColumnExists) $sql .= ", ?";
            
            $sql .= ")";
            
            $stmt = $pdo->prepare($sql);
            
            $params = [
                $email, $emri, $telefoni, $operator, $payment_method, $pagesa, 
                $payment_details, $transaction_id, $file_path, $numri_fiskal, $numri_biznesit
            ];
            
            if ($logColumnExists) $params[] = $abonim_id_param;
            if ($amountColumnExists) $params[] = $pagesa;
            
            $stmt->execute($params);
            
            // If we have an abonim_id, add entry to noteri_abonimet table
            if (isset($_SESSION['selected_abonim']['id']) && !empty($_SESSION['selected_abonim']['id'])) {
                // Get information about the selected subscription
                $stmt_abonim = $pdo->prepare("SELECT kohezgjatja FROM abonimet WHERE id = ?");
                $stmt_abonim->execute([$_SESSION['selected_abonim']['id']]);
                $abonim_info = $stmt_abonim->fetch(PDO::FETCH_ASSOC);
                
                if ($abonim_info) {
                    // Calculate start and end dates
                    $start_date = date('Y-m-d');
                    $end_date = date('Y-m-d', strtotime("+{$abonim_info['kohezgjatja']} months"));
                    
                    // Insert into noteri_abonimet table
                    $stmt_insert_abonim = $pdo->prepare("
                        INSERT INTO noteri_abonimet 
                        (noter_id, abonim_id, data_fillimit, data_mbarimit, status, paguar, menyra_pageses, transaksion_id) 
                        VALUES (?, ?, ?, ?, 'aktiv', ?, ?, ?)
                    ");
                    $stmt_insert_abonim->execute([
                        $zyra_id, 
                        $_SESSION['selected_abonim']['id'],
                        $start_date,
                        $end_date,
                        $pagesa,
                        $payment_method,
                        $transaction_id
                    ]);
                }
            }
            
            $pdo->commit();
            // Pas regjistrimit, pastro të dhënat nga sesioni
            unset($_SESSION['teb_payment_success']);
            unset($_SESSION['selected_abonim']);
            unset($_SESSION['payment_method']);
            
            // Dërgo email konfirmimi
            sendConfirmationEmail($email, $emri, $transaction_id);
            
            // Sukses mesazhi
            $success = "✅ Zyra u regjistrua me sukses!<br>📱 Do të merrni një SMS për verifikim<br>💳 Pagesa është duke u verifikuar";

                        // Shto automatikisht edhe në tabelën users për login
                        try {
                            // Kontrollo nëse ekziston përdorues me këtë email
                            $stmt_user_check = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                            $stmt_user_check->execute([$email]);
                            if (!$stmt_user_check->fetch()) {
                                $stmt_user_insert = $pdo->prepare("INSERT INTO users (emri, mbiemri, email, password, roli, zyra_id) VALUES (?, ?, ?, ?, ?, ?)");
                                // Përdor emrin e plotë si emri, lë mbiemrin bosh, roli 'noter', lidhe zyra_id
                                $stmt_user_insert->execute([
                                    $emri,
                                    '',
                                    $email,
                                    $fjalekalimi_hash,
                                    'noter',
                                    $zyra_id
                                ]);
                            }
                        } catch (Exception $e) {
                            error_log("Nuk u shtua përdoruesi automatikisht: " . $e->getMessage());
                        }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Ndodhi një gabim gjatë regjistrimit: " . $e->getMessage();
            error_log("Registration error: " . $e->getMessage());
            
            // DIAGNOSTIC: Check for triggers causing the issue
            try {
                $stmt = $pdo->prepare("SHOW TRIGGERS WHERE `Table` = 'zyrat'");
                $stmt->execute();
                $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("Triggers on zyrat table:");
                foreach ($triggers as $trigger) {
                    error_log("Trigger: " . $trigger['Trigger']);
                    error_log("Statement: " . $trigger['Statement']);
                }
            } catch (Exception $ex) {
                error_log("Could not list triggers: " . $ex->getMessage());
            }
        }
    }
}

/**
 * Send confirmation email to the office after registration.
 */
function sendConfirmationEmail($to, $officeName, $transactionId) {
    $subject = "Konfirmimi i Regjistrimit të Zyrës Noteriale | Noteria";
    $message = "Përshëndetje $officeName,\n\n"
        . "Zyra juaj noteriale është regjistruar me sukses në platformën Noteria.\n"
        . "ID e transaksionit tuaj është: $transactionId\n\n"
        . "Ky konfirmim shërben si provë e regjistrimit tuaj në sistemin tonë. Ju do të kontaktoheni së shpejti nga ekipi ynë për verifikimin e të dhënave dhe aktivizimin e plotë të llogarisë suaj.\n\n"
        . "Për çdo pyetje apo sqarim shtesë, ju lutemi të na kontaktoni në support@noteria.com ose në numrin +383 44 123 456.\n\n"
        . "Ju faleminderit që zgjodhët platformën Noteria!\n\n"
        . "Me respekt,\n"
        . "Ekipi i Noterisë";
    $headers = "From: Noteria <noreply@noteria.com>\r\n"
        . "Reply-To: support@noteria.com\r\n"
        . "Content-Type: text/plain; charset=UTF-8";
    
    // Try to send email, but don't stop execution if it fails (development environment)
    $mail_sent = @mail($to, $subject, $message, $headers);
    
    // Log instead of showing errors to user
    if (!$mail_sent) {
        error_log("Could not send confirmation email to: $to. Mail server might not be configured.");
        // For development, you can write the email to a file instead
        $log_dir = __DIR__ . '/email_logs';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        $email_log = $log_dir . '/email_' . date('Y-m-d_H-i-s') . '_' . md5($to) . '.txt';
        file_put_contents($email_log, "To: $to\nSubject: $subject\n\n$message");
    }
    
    return true; // Return true regardless of mail sending to prevent breaking the registration flow
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Regjistro Zyrën | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <link rel="stylesheet" href="css/payment_styles.css">
    <style>
        :root {
            --primary: #1a56db;
            --primary-light: #3b82f6;
            --primary-dark: #1e40af;
            --secondary: #0f172a;
            --accent: #10b981;
            --accent-dark: #059669;
            --light-bg: #f1f5f9;
            --light-accent: #e2e8f0;
            --success: #059669;
            --warning: #fbbf24;
            --error: #ef4444;
            --white: #ffffff;
            --black: #111827;
            --gray: #6b7280;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --border-radius-sm: 0.375rem;
            --border-radius: 0.5rem;
            --border-radius-md: 0.75rem;
            --border-radius-lg: 1rem;
            --transition: all 0.3s ease;
        }
        
        /* Base Styles */
        /* Password strength bar */
        #password-strength {
            width: 100%;
            background: #e2e8f0;
            border-radius: 4px;
            margin-top: 0.5rem;
            height: 6px;
        }
        #password-strength-bar {
            height: 100%;
            width: 0;
            background: #ef4444;
            border-radius: 4px;
            transition: width 0.3s, background 0.3s;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body { 
            background: linear-gradient(145deg, #e2eafc 0%, #f8fafc 100%); 
            font-family: 'Montserrat', Arial, sans-serif; 
            color: var(--secondary);
            line-height: 1.6;
            min-height: 100vh;
            padding: 1.5rem 0;
        }
        
        /* Layout & Containers */
        .container { 
            max-width: 900px; 
            margin: 2rem auto; 
            background: var(--white); 
            border-radius: var(--border-radius-lg); 
            box-shadow: var(--shadow-md); 
            padding: 2.5rem;
            animation: fadeIn 0.6s ease-in-out;
        }
        
        .container:first-of-type {
            margin-top: 3rem;
        }
        
        .container:last-of-type {
            margin-bottom: 3rem;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Header & Logo */
        header {
            margin-bottom: 2rem;
            text-align: center;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--light-accent);
        }
        
        .logo {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 0.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        
        .logo i {
            margin-right: 0.75rem;
            font-size: 1.75rem;
        }
        
        h2 { 
            color: var(--secondary); 
            margin-bottom: 1.25rem; 
            font-size: 2.25rem; 
            font-weight: 700;
            position: relative;
            display: inline-block;
        }
        
        h2:after {
            content: '';
            position: absolute;
            bottom: -0.5rem;
            left: 50%;
            transform: translateX(-50%);
            width: 4rem;
            height: 0.25rem;
            background: var(--primary-light);
            border-radius: 1rem;
        }
        
        h3 {
            color: var(--primary);
            margin-bottom: 1rem;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        /* Form Container */
        .form-container {
            background: var(--light-bg);
            border-radius: var(--border-radius-md);
            box-shadow: var(--shadow-sm);
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            height: 0.25rem;
            width: 100%;
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-light) 100%);
        }
        
        /* Form Layout */
        .form-columns {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        
        .form-group { 
            margin-bottom: 1.25rem; 
            text-align: left;
            position: relative;
        }
        
        /* Form Elements */
        label { 
            display: block; 
            margin-bottom: 0.5rem; 
            color: var(--secondary); 
            font-weight: 600;
            font-size: 0.95rem;
            transition: var(--transition);
        }
        
        input[type="text"], 
        input[type="email"], 
        input[type="number"], 
        input[type="date"], 
        input[type="file"],
        select { 
            width: 100%; 
            padding: 0.875rem 1rem; 
            border: 1px solid var(--light-accent); 
            border-radius: var(--border-radius); 
            font-size: 1rem; 
            font-family: inherit;
            background: var(--white); 
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
            color: var(--secondary);
        }
        
        input[type="file"] {
            padding: 0.625rem;
            cursor: pointer;
        }
        
        input[type="text"]:hover, 
        input[type="email"]:hover, 
        input[type="number"]:hover,
        input[type="date"]:hover,
        select:hover { 
            border-color: var(--primary-light);
        }
        
        input[type="text"]:focus, 
        input[type="email"]:focus, 
        input[type="number"]:focus,
        input[type="date"]:focus,
        select:focus { 
            border-color: var(--primary); 
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
        }
        
        input:required, select:required {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8' viewBox='0 0 8 8'%3E%3Cpath fill='%23ef4444' d='M0 0h8v8h-8z'/%3E%3Cpath fill='%23ffffff' d='M4 1v4h-1v-4h1zm0 5v1h-1v-1h1z'/%3E%3C/svg%3E");
            background-position: right 12px center;
            background-repeat: no-repeat;
            padding-right: 36px;
        }
        
        /* Input with Icons */
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            transition: var(--transition);
        }
        
        .input-with-icon input:focus + i,
        .input-with-icon select:focus + i {
            color: var(--primary);
        }
        
        .input-with-icon input, 
        .input-with-icon select {
            padding-left: 2.75rem;
        }
        
        /* Button Styles */
        button[type="submit"] { 
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-light) 100%);
            color: var(--white); 
            border: none; 
            border-radius: var(--border-radius); 
            padding: 1rem 0; 
            width: 100%; 
            font-size: 1.25rem; 
            font-weight: 600; 
            cursor: pointer; 
            transition: var(--transition);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }
        
        button[type="submit"]::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: var(--transition);
        }
        
        button[type="submit"]:hover {
            background: linear-gradient(90deg, var(--primary-dark) 0%, var(--primary) 100%);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        button[type="submit"]:hover::before {
            left: 100%;
            transition: 0.7s;
        }
        
        button[type="submit"]:active { 
            transform: translateY(0);
            box-shadow: var(--shadow);
        }
        
        button[type="submit"]:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
        }
        
        button[type="submit"] i {
            margin-right: 0.5rem;
        }
        
        /* Alert Messages */
        .success, .error { 
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            animation: slideIn 0.3s ease-out;
        }
        
        .success { 
            color: var(--success);
            background: #ecfdf5; 
            border-left: 4px solid var(--success);
        }
        
        .error { 
            color: var(--error);
            background: #fef2f2;
            border-left: 4px solid var(--error);
        }
        
        .success i, .error i {
            font-size: 1.25rem;
            margin-right: 0.75rem;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Section Titles & Dividers */
        .section-title { 
            color: var(--primary); 
            margin-top: 2rem; 
            margin-bottom: 1rem;
            font-size: 1.25rem; 
            font-weight: 600;
            display: flex;
            align-items: center;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--light-accent);
            position: relative;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 4rem;
            height: 2px;
            background: var(--primary);
        }
        
        .section-title i {
            margin-right: 0.5rem;
            color: var(--primary);
        }
        
        .section-divider {
            height: 1px;
            background: var(--light-accent);
            margin: 2rem 0;
        }
        
        /* Helper Classes */
        .field-info {
            font-size: 0.8rem;
            color: var(--gray);
            margin-top: 0.375rem;
            display: flex;
            align-items: center;
        }
        
        .field-info i {
            margin-right: 0.25rem;
            font-size: 0.75rem;
        }
        
        .terms-checkbox {
            display: flex;
            align-items: flex-start;
            margin: 1.5rem 0;
            padding: 1rem;
            background: #f8fafc;
            border-radius: var(--border-radius);
            border: 1px solid var(--light-accent);
        }
        
        .terms-checkbox input {
            margin-right: 0.75rem;
            margin-top: 0.375rem;
            transform: scale(1.2);
        }
        
        .terms-checkbox label {
            margin-bottom: 0;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        .terms-checkbox a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .terms-checkbox a:hover {
            text-decoration: underline;
        }
        
        .required-note {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            background: #f8fafc;
            padding: 0.75rem 1rem;
            border-radius: var(--border-radius);
            border-left: 3px solid var(--primary-light);
        }
        
        .required-note i {
            margin-right: 0.5rem;
            color: var(--primary);
        }
        
        /* Support Section */
        .support-info {
            text-align: center;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            background: #f8fafc;
            border: 1px dashed var(--light-accent);
        }
        
        .support-info h3 {
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .support-info p {
            margin-bottom: 0.75rem;
        }
        
        .support-info i {
            margin-right: 0.5rem;
            color: var(--primary);
        }
        
        .support-info a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .support-info a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .container {
                max-width: 90%;
                padding: 2rem 1.5rem;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                max-width: 95%;
                padding: 1.5rem;
                margin: 1.5rem auto;
            }
            
            .form-columns {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .form-container {
                padding: 1.5rem;
            }
            
            h2 {
                font-size: 1.75rem;
            }
            
            .section-title {
                font-size: 1.15rem;
            }
            
            button[type="submit"] {
                font-size: 1.1rem;
                padding: 0.875rem 0;
            }
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 1.25rem;
                margin: 1rem auto;
                border-radius: var(--border-radius);
            }
            
            .form-container {
                padding: 1.25rem;
                border-radius: var(--border-radius);
            }
            
            h2 {
                font-size: 1.5rem;
            }
            
            .logo {
                font-size: 1.5rem;
            }
            
            .terms-checkbox {
                padding: 0.75rem;
            }
        }
        
        /* Print Styles */
        /* Password strength bar */
        #password-strength {
            width: 100%;
            background: #e2e8f0;
            border-radius: 4px;
            margin-top: 0.5rem;
            height: 6px;
        }
        #password-strength-bar {
            height: 100%;
            width: 0;
            background: #ef4444;
            border-radius: 4px;
            transition: width 0.3s, background 0.3s;
        }
        @media print {
            body {
                background: white;
                font-size: 12pt;
            }
            
            .container {
                width: 100%;
                max-width: 100%;
                margin: 0;
                padding: 1cm;
                box-shadow: none;
                border: none;
            }
            
            button, .support-info {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Logo shown only in header below -->
    <div class="container">
        <header>
            <div class="logo" style="justify-content:center;">
                <img src="images/pngwing.com (1).png" alt="Noteria Logo" style="height:100px;width:auto;display:inline-block;margin-bottom:8px;">
                <span style="display:block;font-size:2rem;color:#1a56db;font-weight:700;margin-top:8px;">Noteria</span>
            </div>
            <h2>Regjistro Zyrën Noteriale</h2>
        </header>
        
        <?php if ($success): ?>
            <div class="success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="required-note"><i class="fas fa-info-circle"></i> Fushat e shënuara me <span style="color: #d32f2f;">*</span> janë të detyrueshme</div>
        
        <div class="form-container">
            <!-- Zyra Noteriale Info Form -->
            <form method="POST" enctype="multipart/form-data" id="office-info-form">
            <div class="section-title"><i class="fas fa-building"></i> Të dhënat e zyrës noteriale</div>
            <div class="form-columns">
                <div class="form-group">
                <div class="form-group">
                    <label for="username">Emri i përdoruesit (username):</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" id="username" placeholder="Vendosni username" required autocomplete="username">
                    </div>
                </div>
                <div class="form-group">
                    <label for="fjalekalimi">Fjalëkalimi për llogarinë:</label>
                    <div class="input-with-icon" style="position:relative;">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="fjalekalimi" id="fjalekalimi" placeholder="Të paktën 8 karaktere" required autocomplete="new-password" style="padding-right:2.5rem;">
                        <span id="togglePassword" style="position:absolute; right:1rem; top:50%; transform:translateY(-50%); cursor:pointer; color:#6b7280;">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    <div id="password-strength" style="margin-top:0.5rem; height:6px; border-radius:4px; background:#e2e8f0;">
                        <div id="password-strength-bar" style="height:100%; width:0; background:#ef4444; border-radius:4px; transition:width 0.3s;"></div>
                    </div>
                    <div class="field-info" id="password-hint">
                        Të paktën 8 karaktere, përfshi shkronja të mëdha, të vogla, numra dhe simbole.
                    </div>
                </div>
                    <label for="emri">Emri i plotë i zyrës noteriale:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-signature"></i>
                        <input type="text" name="emri" id="emri" placeholder="p.sh. Zyra Noteriale 'Emri Mbiemri'" required>
                    </div>
                    <div class="field-info">Emri zyrtar i zyrës siç figuron në dokumentet e regjistrimit</div>
                </div>
                <div class="form-group">
                    <label for="adresa">Adresa e saktë:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-map-marker-alt"></i>
                        <input type="text" name="adresa" id="adresa" placeholder="Rr. Emri i rrugës, Nr. X, Kati X" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="qyteti">Qyteti/Komuna:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-city"></i>
                        <select name="qyteti" id="qyteti" required>
                            <option value="">Zgjidh qytetin</option>
                            <?php foreach ($qytetet as $q): ?>
                                <option value="<?php echo htmlspecialchars($q); ?>"><?php echo htmlspecialchars($q); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="shteti">Shteti:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-flag"></i>
                        <input type="text" name="shteti" id="shteti" value="Kosova" readonly>
                    </div>
                </div>
            </div>
            
            <!-- Kontakti Form -->
            <!-- <form method="POST" enctype="multipart/form-data" id="contact-form"> -->
                <div class="section-title"><i class="fas fa-envelope"></i> Kontakti</div>
                <div class="form-columns">
                    <div class="form-group">
                        <label for="email">Email zyrtar i zyrës:</label>
                        <div class="input-with-icon">
                            <i class="fas fa-at"></i>
                            <input type="email" name="email" id="email" placeholder="zyra@domain.com" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="email2">Përsërit Email-in:</label>
                        <div class="input-with-icon">
                            <i class="fas fa-check-double"></i>
                            <input type="email" name="email2" id="email2" placeholder="zyra@domain.com" required>
                        </div>
                    </div>
                </div>
            <!-- </form> -->
                <div class="form-group">
                    <label for="telefoni">Numri i Telefonit:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-phone-alt"></i>
                        <input type="text" name="telefoni" id="telefoni" placeholder="+38344123456" required>
                    </div>
                    <div class="field-info">Numri do të përdoret për verifikim me SMS</div>
                </div>
                <div class="form-group">
                    <label for="operator">Operatori telefonik:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-broadcast-tower"></i>
                        <select name="operator" id="operator" required>
                            <option value="">Zgjidhni operatorin</option>
                            <?php foreach ($operatoret as $key => $value): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($key); ?> (<?php echo htmlspecialchars($value); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="section-title"><i class="fas fa-file-contract"></i> Të dhënat e regjistrimit zyrtar</div>
            <div class="form-columns">
                <div class="form-group">
                    <label for="numri_fiskal">Numri Fiskal i ATK-së:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-hashtag"></i>
                        <input type="text" name="numri_fiskal" id="numri_fiskal" placeholder="9 shifra, p.sh. 123456789" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="numri_biznesit">Numri i biznesit në ARBK:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-id-card"></i>
                        <input type="text" name="numri_biznesit" id="numri_biznesit" placeholder="p.sh. AB1234567C" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="numri_licences">Numri i licencës noteriale:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-certificate"></i>
                        <input type="text" name="numri_licences" id="numri_licences" placeholder="p.sh. NT12345" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="data_licences">Data e lëshimit të licencës:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="date" name="data_licences" id="data_licences" required>
                    </div>
                </div>
            </div>
            
            <div class="section-divider"></div>
            <div class="section-title"><i class="fas fa-university"></i> Të dhënat bankare të zyrës</div>
            <div class="form-columns">
                <div class="form-group">
                    <label for="banka">Emri i Bankës:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-landmark"></i>
                        <input type="text" name="banka" id="banka" placeholder="p.sh. ProCredit Bank" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="iban">IBAN:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-money-check"></i>
                        <input type="text" name="iban" id="iban" required placeholder="p.sh. XK051212012345678906">
                    </div>
                    <div class="field-info">Formati standard IBAN për Kosovën (15-34 karaktere)</div>
                </div>
                <div class="form-group">
                    <label for="llogaria">Numri i Llogarisë:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-credit-card"></i>
                        <input type="text" name="llogaria" id="llogaria" required placeholder="Vetëm shifra">
                    </div>
                </div>
            </div>
            
            <div class="section-title"><i class="fas fa-users"></i> Të dhënat e stafit</div>
            <div class="form-columns">
                <div class="form-group">
                    <label for="emri_noterit">Emri i noterit kryesor:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user-tie"></i>
                        <input type="text" name="emri_noterit" id="emri_noterit" placeholder="Emri dhe mbiemri i noterit" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="vitet_pervoje">Vitet e përvojës si noter:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-hourglass-half"></i>
                        <input type="number" name="vitet_pervoje" id="vitet_pervoje" min="0" max="50" placeholder="p.sh. 10" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="numri_punetoreve">Numri total i punëtorëve:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-users-cog"></i>
                        <input type="number" name="numri_punetoreve" id="numri_punetoreve" min="1" placeholder="p.sh. 5" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="gjuhet">Gjuhët e folura në zyrë:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-language"></i>
                        <input type="text" name="gjuhet" id="gjuhet" placeholder="p.sh. Shqip, Serbisht, Anglisht" required>
                    </div>
                </div>
            </div>
            
            <div id="punetoret_container">
                <!-- Seksioni dinamik për të dhënat e punëtorëve -->
                <div class="staff-member" data-index="1">
                    <div class="section-title staff-title">
                        <i class="fas fa-user"></i> Punëtori #1
                        <button type="button" class="btn-remove-staff" style="display:none;"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="form-columns">
                        <div class="form-group">
                            <label for="staff_name_1">Emri dhe mbiemri:</label>
                            <div class="input-with-icon">
                                <i class="fas fa-user-alt"></i>
                                <input type="text" name="staff_name[]" id="staff_name_1" placeholder="Emri dhe mbiemri">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="staff_position_1">Pozita/Roli:</label>
                            <div class="input-with-icon">
                                <i class="fas fa-briefcase"></i>
                                <input type="text" name="staff_position[]" id="staff_position_1" placeholder="p.sh. Asistent Noter, Sekretar">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="text-align: center; margin: 1rem 0 2rem;">
                <button type="button" id="shto_punetor" class="btn-secondary">
                    <i class="fas fa-plus-circle"></i> Shto punëtor tjetër
                </button>
            </div>
            
            <div class="section-title"><i class="fas fa-file-upload"></i> Dokumentacioni</div>
            <div class="form-columns">
                <div class="form-group">
                    <label for="certifikata_biznesit">Certifikata e regjistrimit të biznesit:</label>
                    <input type="file" name="certifikata_biznesit" id="certifikata_biznesit" accept=".pdf,.jpg,.jpeg,.png">
                    <div class="field-info">Format i pranuar: PDF, JPG, PNG (max 5MB)</div>
                </div>
                <div class="form-group">
                    <label for="licenca_noteriale">Licenca e noterit:</label>
                    <input type="file" name="licenca_noteriale" id="licenca_noteriale" accept=".pdf,.jpg,.jpeg,.png">
                    <div class="field-info">Format i pranuar: PDF, JPG, PNG (max 5MB)</div>
                </div>
            </div>
            
            <div class="section-title"><i class="fas fa-euro-sign"></i> Zgjedhja e Planit dhe Pagesa</div>
            <div class="form-columns">
                <div class="form-group">
                    <label for="abonim_selector">Zgjidhni Planin e Abonimit <span style="color:red">*</span></label>
                    <div class="input-with-icon">
                        <i class="fas fa-tags"></i>
                        <select name="abonim_id" id="abonim_selector" required>
                            <option value="" disabled <?php echo !isset($_SESSION['selected_abonim']['id']) ? 'selected' : ''; ?>>-- Zgjidhni Planin --</option>
                            <?php foreach ($plans as $plan): ?>
                                <?php 
                                    $isSelected = (isset($_SESSION['selected_abonim']['id']) && $_SESSION['selected_abonim']['id'] == $plan['id']) || 
                                                  (!isset($_SESSION['selected_abonim']['id']) && $plan['cmimi'] == 30.00);
                                ?>
                                <option value="<?php echo $plan['id']; ?>" data-price="<?php echo $plan['cmimi']; ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                                    <?php echo isset($plan['label']) ? $plan['label'] : htmlspecialchars($plan['emri']) . ' - ' . number_format($plan['cmimi'], 2) . ' €'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="pagesa">Shuma për pagesë (€):</label>
                    <div class="input-with-icon">
                        <i class="fas fa-money-bill-wave"></i>
                        <input type="number" name="pagesa" id="pagesa" step="0.01" readonly required>
                    </div>
                    <div class="field-info">
                        <i class="fas fa-info-circle"></i> Shuma llogaritet automatikisht bazuar në planin e zgjedhur
                    </div>
                </div>

                <div class="form-group">
                    <label for="payment_method">Mënyra e Pagesës:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-wallet"></i>
                        <select name="payment_method" id="payment_method" onchange="togglePaymentFields()">
                            <option value="bank_transfer">Transfertë Bankare</option>
                            <option value="credit_card">Kartelë Krediti / Debiti</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Partnerët Bankarë:</label>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; justify-content: center;">
                        <img src="images/BKT_Logo.jpg" alt="BKT" style="height: 50px; object-fit: contain; mix-blend-mode: multiply;">
                        <img src="images/tink_logo.png" alt="Tink" style="height: 50px; object-fit: contain; mix-blend-mode: multiply;">
                        <img src="images/MoneyGram.jpg" alt="MoneyGram" style="height: 50px; object-fit: contain; mix-blend-mode: multiply;">
                        <img src="images/OIP.webp" alt="Partner" style="height: 50px; object-fit: contain; mix-blend-mode: multiply;">
                        <!-- Other logos provided -->
                        <img src="images/324278851_3137678529855889_4430967934292159700_n.png" alt="Partner" style="height: 50px; object-fit: contain; mix-blend-mode: multiply;">
                        <img src="images/347553484_643310674493687_8963447518322596944_n.jpg" alt="Partner" style="height: 50px; object-fit: contain; mix-blend-mode: multiply;">
                        <img src="images/352363796_6603753096342750_5975088970274800558_n.jpg" alt="Partner" style="height: 50px; object-fit: contain; mix-blend-mode: multiply;">
                        <img src="images/464949490_954715033354926_4354965209175578191_n.jpg" alt="Partner" style="height: 50px; object-fit: contain; mix-blend-mode: multiply;">
                    </div>
                </div>

                <div id="card_payment_form" style="display:none; grid-column: 1 / -1; margin-bottom: 1.5rem; padding: 1.5rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                        <h4 style="margin:0; color: #334155;">Të dhënat e kartelës</h4>
                        <div style="color: #64748b;">
                            <i class="fab fa-cc-visa" style="font-size: 1.5rem; margin-right: 0.5rem;"></i>
                            <i class="fab fa-cc-mastercard" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label style="font-size: 0.9rem;">Emri në kartelë</label>
                        <input type="text" name="card_name" placeholder="EMRI MBIEMRI" style="text-transform: uppercase;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label style="font-size: 0.9rem;">Numri i kartelës</label>
                        <div class="input-with-icon">
                            <i class="far fa-credit-card"></i>
                            <input type="text" name="card_number" placeholder="0000 0000 0000 0000" maxlength="19">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-size: 0.9rem;">Skadon (MM/VV)</label>
                            <input type="text" name="card_expiry" placeholder="MM/VV" maxlength="5">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-size: 0.9rem;">CVC</label>
                            <div class="input-with-icon">
                                <i class="fas fa-lock"></i>
                                <input type="text" name="card_cvc" placeholder="123" maxlength="4">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" id="payment_proof_container">
                    <label for="payment_proof">Dëshmi e pagesës (opsionale):</label>
                    <input type="file" name="payment_proof" id="payment_proof" accept=".pdf,.jpg,.jpeg,.png">
                    <div class="field-info">Ngarko dëshmi të pagesës nëse e keni kryer paraprakisht</div>
                </div>
                <?php if(isset($_SESSION['payment_method'])): ?>
                <input type="hidden" name="payment_method" value="<?php echo htmlspecialchars($_SESSION['payment_method']); ?>">
                <?php endif; ?>
            </div>
            
            <div class="section-divider"></div>
            
            <div class="terms-checkbox">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">
                    Konfirmoj që të gjitha të dhënat e ofruara janë të sakta dhe pranoj
                    <a href="terms.php" target="_blank">Kushtet e Përdorimit</a>
                    dhe
                    <a href="Privatesia.php" target="_blank">Politikën e Privatësisë</a>
                </label>
            </div>
            
            <button type="submit"><i class="fas fa-paper-plane"></i> Regjistro zyrën noteriale</button>
        </form>
    </div>
    
    <div class="container">
        <div class="support-info">
            <h3><i class="fas fa-headset"></i> Keni nevojë për ndihmë?</h3>
            <p>Kontaktoni ekipin tonë të mbështetjes për çdo pyetje ose problem:</p>
            <p><i class="fas fa-envelope"></i> Email: <a href="mailto:support@noteria.com">support@noteria.com</a></p>
            <p><i class="fas fa-phone"></i> Tel: +383 44 123 456</p>
        </div>
    </div>
    <script>
// Form validation and enhancements
document.addEventListener('DOMContentLoaded', function() {
    // Password show/hide toggle
    const passwordInput = document.getElementById('fjalekalimi');
    const togglePassword = document.getElementById('togglePassword');
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
    });

    // Password strength meter
    passwordInput.addEventListener('input', function() {
        const val = this.value;
        const bar = document.getElementById('password-strength-bar');
        let score = 0;
        if (val.length >= 8) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[a-z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;
        let percent = (score / 5) * 100;
        bar.style.width = percent + '%';
        if (score <= 2) bar.style.background = '#ef4444'; // red
        else if (score === 3) bar.style.background = '#fbbf24'; // yellow
        else if (score >= 4) bar.style.background = '#059669'; // green
    });
    const form = document.querySelector('form');
    const inputs = form.querySelectorAll('input, select');
    
    // Highlight fields with validation errors
    inputs.forEach(input => {
        input.addEventListener('invalid', function() {
            this.classList.add('error-field');
            
            // Create or update error message
            let errorMessage = this.parentNode.querySelector('.field-error-msg');
            if (!errorMessage) {
                errorMessage = document.createElement('div');
                errorMessage.className = 'field-error-msg';
                this.parentNode.appendChild(errorMessage);
            }
            
            // Set appropriate error message based on validation type
            if (this.validity.valueMissing) {
                errorMessage.textContent = 'Kjo fushë është e detyrueshme';
            } else if (this.validity.typeMismatch) {
                errorMessage.textContent = 'Formati i futur nuk është i saktë';
            } else if (this.validity.patternMismatch) {
                errorMessage.textContent = 'Ju lutemi ndiqni formatin e kërkuar';
            }
        });
        
        input.addEventListener('input', function() {
            this.classList.remove('error-field');
            
            // Remove error message if exists
            const errorMessage = this.parentNode.querySelector('.field-error-msg');
            if (errorMessage) {
                errorMessage.remove();
            }
        });
        
        // Add focus styles
        input.addEventListener('focus', function() {
            this.classList.add('field-focus');
            
            // Highlight label
            const label = this.closest('.form-group').querySelector('label');
            if (label) {
                label.classList.add('label-focus');
            }
            
            // Highlight icon
            const icon = this.parentNode.querySelector('i');
            if (icon) {
                icon.classList.add('icon-focus');
            }
        });
        
        input.addEventListener('blur', function() {
            this.classList.remove('field-focus');
            
            // Remove label highlight
            const label = this.closest('.form-group').querySelector('label');
            if (label) {
                label.classList.remove('label-focus');
            }
            
            // Remove icon highlight
            const icon = this.parentNode.querySelector('i');
            if (icon) {
                icon.classList.remove('icon-focus');
            }
        });
    });
    
    // Add styling for validation errors and focus states
    const style = document.createElement('style');
    style.textContent = `
        .error-field {
            border-color: var(--error) !important;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.15) !important;
        }
        
        .field-error-msg {
            color: var(--error);
            font-size: 0.75rem;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            animation: fadeIn 0.2s ease-in;
        }
        
        .field-error-msg::before {
            content: '⚠️';
            margin-right: 0.25rem;
            font-size: 0.75rem;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .error-field:invalid {
            animation: shake 0.3s;
        }
        
        .field-focus {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25) !important;
        }
        
        .label-focus {
            color: var(--primary) !important;
        }
        
        .icon-focus {
            color: var(--primary) !important;
            opacity: 1 !important;
        }
        
        input[type="file"] {
            border: 1px dashed var(--light-accent);
            transition: all 0.3s ease;
        }
        
        input[type="file"]:hover {
            border-color: var(--primary-light);
            background-color: rgba(59, 130, 246, 0.05);
        }
        
        input[type="checkbox"] {
            accent-color: var(--primary);
        }
    `;
    document.head.appendChild(style);
    
    // Phone number format validation with visual formatting
    const phoneInput = document.getElementById('telefoni');
    phoneInput.addEventListener('input', function() {
        let value = this.value;
        if (!value.startsWith('+383')) {
            value = '+383' + value.replace(/\D/g, '');
        } else {
            value = '+383' + value.substring(4).replace(/\D/g, '');
        }
        
        // Limit to 12 characters (+383 + 8 digits)
        if (value.length > 12) {
            value = value.substring(0, 12);
        }
        
        this.value = value;
        
        // Add visual indicator for valid phone number
        if (value.length === 12) {
            this.classList.add('valid-input');
        } else {
            this.classList.remove('valid-input');
        }
    });
    
    // IBAN format helper with visual feedback
    const ibanInput = document.getElementById('iban');
    ibanInput.addEventListener('input', function() {
        let value = this.value;
        value = value.replace(/[^A-Z0-9]/gi, '').toUpperCase();
        
        if (!value.startsWith('XK')) {
            value = 'XK' + value;
        }
        
        if (value.length > 34) {
            value = value.substring(0, 34);
        }
        
        // Format IBAN with spaces for readability (optional)
        // let formattedValue = '';
        // for (let i = 0; i < value.length; i++) {
        //     if (i > 0 && i % 4 === 0) {
        //         formattedValue += ' ';
        //     }
        //     formattedValue += value[i];
        // }
        // this.value = formattedValue;
        
        this.value = value;
        
        // Visual indicator for valid IBAN length
        if (value.length >= 15 && value.length <= 34) {
            this.classList.add('valid-input');
        } else {
            this.classList.remove('valid-input');
        }
    });
    
    // Fiscal number format with completion indicator
    const fiscalInput = document.getElementById('numri_fiskal');
    fiscalInput.addEventListener('input', function() {
        let value = this.value;
        value = value.replace(/\D/g, '');
        
        if (value.length > 9) {
            value = value.substring(0, 9);
        }
        
        this.value = value;
        
        // Add progress indicator
        const progress = Math.min(value.length / 9 * 100, 100);
        this.style.background = `linear-gradient(to right, rgba(59, 130, 246, 0.1) ${progress}%, transparent ${progress}%)`;
        
        if (value.length === 9) {
            this.classList.add('valid-input');
        } else {
            this.classList.remove('valid-input');
        }
    });
    
    // Business number formatting
    const bizNumberInput = document.getElementById('numri_biznesit');
    if (bizNumberInput) {
        bizNumberInput.addEventListener('input', function() {
            let value = this.value.toUpperCase();
            this.value = value;
            
            if (value.length === 10) {
                this.classList.add('valid-input');
            } else {
                this.classList.remove('valid-input');
            }
        });
    }
    
    // Form submission animation and validation
    form.addEventListener('submit', function(e) {
        // Custom final validation
        let valid = true;
        
        // Check all required fields
        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if (!field.value) {
                valid = false;
                field.classList.add('error-field');
                
                // Add temporary error message
                let errorMessage = field.parentNode.querySelector('.field-error-msg');
                if (!errorMessage) {
                    errorMessage = document.createElement('div');
                    errorMessage.className = 'field-error-msg';
                    errorMessage.textContent = 'Kjo fushë është e detyrueshme';
                    field.parentNode.appendChild(errorMessage);
                }
            }
        });
        
        // Check if emails match
        const email1 = document.getElementById('email');
        const email2 = document.getElementById('email2');
        if (email1 && email2 && email1.value !== email2.value) {
            valid = false;
            email2.classList.add('error-field');
            
            // Add temporary error message
            let errorMessage = email2.parentNode.querySelector('.field-error-msg');
            if (!errorMessage) {
                errorMessage = document.createElement('div');
                errorMessage.className = 'field-error-msg';
                errorMessage.textContent = 'Emailat nuk përputhen';
                email2.parentNode.appendChild(errorMessage);
            }
        }
        
        if (!valid) {
            e.preventDefault();
            
            // Scroll to first error
            const firstError = form.querySelector('.error-field');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            return;
        }
        
        // Show loading animation if form is valid
        const button = this.querySelector('button[type="submit"]');
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Duke procesuar...';
        button.disabled = true;
    });
    
    // Add style for valid inputs
    const validStyle = document.createElement('style');
    validStyle.textContent = `
        .valid-input {
            background-position: right 12px center !important;
            background-repeat: no-repeat !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24'%3E%3Cpath fill='%23059669' d='M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z'/%3E%3C/svg%3E") !important;
        }
        
        /* Stili për seksionin e stafit */
        .staff-member {
            background-color: rgba(241, 245, 249, 0.5);
            border-radius: var(--border-radius);
            border: 1px solid var(--light-accent);
            padding: 1.25rem;
            margin-bottom: 1rem;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .staff-member:hover {
            border-color: var(--primary-light);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .staff-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.5rem !important;
            padding-bottom: 0.75rem;
        }
        
        .btn-remove-staff {
            background-color: #fee2e2;
            color: var(--error);
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-remove-staff:hover {
            background-color: var(--error);
            color: white;
            transform: scale(1.1);
        }
        
        .btn-secondary {
            background: var(--white);
            color: var(--primary);
            border: 2px solid var(--primary);
            border-radius: var(--border-radius);
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-secondary:hover {
            background: var(--primary-light);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .btn-secondary:active {
            transform: translateY(0);
        }
    `;
    document.head.appendChild(validStyle);
    
    // Menaxhimi i stafit
    let staffCount = 1;
    const maxStaff = 10;
    
    // Funksioni për të shtuar një punëtor të ri
    document.getElementById('shto_punetor').addEventListener('click', function() {
        staffCount++;
        if (staffCount > maxStaff) {
            alert(`Ju mund të shtoni maksimum ${maxStaff} punëtorë.`);
            return;
        }
        
        const container = document.getElementById('punetoret_container');
        const newStaff = document.createElement('div');
        newStaff.className = 'staff-member';
        newStaff.dataset.index = staffCount;
        
        newStaff.innerHTML = `
            <div class="section-title staff-title">
                <i class="fas fa-user"></i> Punëtori #${staffCount}
                <button type="button" class="btn-remove-staff"><i class="fas fa-times"></i></button>
            </div>
            <div class="form-columns">
                <div class="form-group">
                    <label for="staff_name_${staffCount}">Emri dhe mbiemri:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user-alt"></i>
                        <input type="text" name="staff_name[]" id="staff_name_${staffCount}" placeholder="Emri dhe mbiemri">
                    </div>
                </div>
                <div class="form-group">
                    <label for="staff_position_${staffCount}">Pozita/Roli:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-briefcase"></i>
                        <input type="text" name="staff_position[]" id="staff_position_${staffCount}" placeholder="p.sh. Asistent Noter, Sekretar">
                    </div>
                </div>
            </div>
        `;
        
        container.appendChild(newStaff);
        
        // Shto dëgjuesin e eventit për butonin e fshirjes
        newStaff.querySelector('.btn-remove-staff').addEventListener('click', function() {
            container.removeChild(newStaff);
            
            // Rinumëro stafin
            const staffMembers = container.querySelectorAll('.staff-member');
            staffMembers.forEach((member, index) => {
                const staffNumber = index + 1;
                member.dataset.index = staffNumber;
                member.querySelector('.staff-title').firstChild.textContent = ` Punëtori #${staffNumber} `;
                
                // Përditëso ID-të e inputeve
                const nameInput = member.querySelector('[id^="staff_name_"]');
                const positionInput = member.querySelector('[id^="staff_position_"]');
                
                nameInput.id = `staff_name_${staffNumber}`;
                positionInput.id = `staff_position_${staffNumber}`;
            });
            
            staffCount = staffMembers.length;
            
            // Fsheh butonin e fshirjes nëse ka mbetur vetëm një punëtor
            if (staffCount === 1) {
                container.querySelector('.btn-remove-staff').style.display = 'none';
            }
        });
        
        // Shfaq të gjithë butonat e fshirjes kur ka më shumë se një punëtor
        if (staffCount > 1) {
            const removeButtons = container.querySelectorAll('.btn-remove-staff');
            removeButtons.forEach(button => {
                button.style.display = 'flex';
            });
        }
        
        // Bëj scroll poshtë për të treguar punëtorin e ri
        newStaff.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    // Logic for updating price based on selected plan
    const abonimSelector = document.getElementById('abonim_selector');
    const pagesaInput = document.getElementById('pagesa');
    
    function updatePrice() {
        const selectedOption = abonimSelector.options[abonimSelector.selectedIndex];
        if (selectedOption && selectedOption.dataset.price) {
            pagesaInput.value = selectedOption.dataset.price;
        }
    }
    
    if (abonimSelector) {
        abonimSelector.addEventListener('change', updatePrice);
        // Initialize on load
        updatePrice();
    }

    // Toggle payment fields
    window.togglePaymentFields = function() {
        const method = document.getElementById('payment_method').value;
        const cardForm = document.getElementById('card_payment_form');
        const proofField = document.getElementById('payment_proof_container');
        
        if (method === 'credit_card') {
            cardForm.style.display = 'block';
            if(proofField) proofField.style.display = 'none';
        } else {
            cardForm.style.display = 'none';
            if(proofField) proofField.style.display = 'block';
        }
    };
});

// Payment status checker (if available)
if (document.getElementById('check-tink-status')) {
    document.getElementById('check-tink-status').onclick = function() {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Duke kontrolluar...';
        fetch('tink_status_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ payment_id: '<?php echo $_SESSION['tink_payment_id'] ?? ""; ?>' })
        })
        .then(r => r.json())
        .then((data) => {
            let icon = '<i class="fas fa-clock"></i>', txt = 'Duke pritur konfirmimin nga banka...';
            if (data.status === 'EXECUTED') { icon = '<i class="fas fa-check-circle"></i>'; txt = 'Pagesa u ekzekutua me sukses!'; }
            else if (data.status === 'FAILED') { icon = '<i class="fas fa-times-circle"></i>'; txt = 'Pagesa dështoi!'; }
            else if (data.status === 'CANCELLED') { icon = '<i class="fas fa-ban"></i>'; txt = 'Pagesa u anulua.'; }
            
            document.getElementById('tink-status-icon').innerHTML = icon;
            document.getElementById('tink-status-text').innerHTML = txt;
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sync"></i> Kontrollo Statusin e Pagesës';
        })
        .catch(() => {
            document.getElementById('tink-status-icon').innerHTML = '<i class="fas fa-question-circle"></i>';
            document.getElementById('tink-status-text').innerHTML = 'Nuk u mor statusi. Provo përsëri.';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sync"></i> Kontrollo Statusin e Pagesës';
        });
    };
}
</script>
</body>
</html>