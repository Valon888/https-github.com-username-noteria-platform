<?php
// subscription_notifications.php - Script për dërgimin e njoftimeve për abonim të skaduar/pezulluar
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';
require_once 'Phpmailer.php';

// Kontrollo që $pdo është inicializuar nga config.php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    try {
        if (!isset($dsn) || !isset($db_user) || !isset($db_pass)) {
            throw new Exception("Parametrat e lidhjes me databazën mungojnë.");
        }
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Exception $e) {
        error_log("Gabim në lidhjen me databazën: " . $e->getMessage());
        $pdo = null;
    }
}

// Kontrollo sërish që $pdo është objekt përpara përdorimit
if (!$pdo || !($pdo instanceof PDO)) {
    error_log("PDO nuk është inicializuar si duhet.");
    exit("Gabim: Nuk mund të lidhem me databazën.");
}

// Në këtë pikë, $pdo duhet të jetë një objekt PDO i vlefshëm
if (!is_object($pdo)) {
    error_log("Gabim: $pdo nuk është objekt.");
    exit("Gabim: Lidhja me databazën dështoi.");
}

// Nëse ekzekutohet nga CLI, shfaq mesazhet e progresit
$isCLI = (php_sapi_name() === 'cli');

// Kontrolli i sigurisë për akses nga browseri
if (!$isCLI) {
    // Nëse ka një token të sigurtë, kontrollo nëse përputhet
    if (isset($_GET['secure_token'])) {
        $providedToken = $_GET['secure_token'];
        
        try {
            // Merr token-in e ruajtur nga databaza
            $stmt = $pdo->prepare("SELECT value FROM system_settings WHERE name = 'subscription_secure_token' LIMIT 1");
            $stmt->execute();
            $storedToken = $stmt->fetchColumn();
            
            // Nëse token-i nuk përputhet, ndalo
            if (!$storedToken || $providedToken !== $storedToken) {
                echo "Akses i paautorizuar";
                exit();
            }
        } catch (PDOException $e) {
            echo "Gabim në kontrollimin e token-it të sigurisë";
            exit();
        }
    } else {
        // Nëse nuk ka token, kontrollo nëse është admin i loguar
        session_start();
        if (!isset($_SESSION['admin_id'])) {
            echo "Akses i paautorizuar";
            exit();
        }
    }
}

// Funksion për ruajtjen e log-eve
function logActivity($message, $level = 'info') {
    global $pdo;
    
    try {
        if (!$pdo || !is_object($pdo) || !($pdo instanceof PDO)) {
            error_log("Gabim: Lidhja me databazën nuk është inicializuar si duhet për logActivity.");
            return;
        }
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (log_type, message, created_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute(["subscription_notification_{$level}", $message]);
    } catch (PDOException $e) {
        error_log("Gabim në ruajtjen e log-ut: " . $e->getMessage());
    }
}

// Funksion për shfaqjen e mesazheve në CLI
function showMessage($message, $isError = false) {
    global $isCLI;
    
    if ($isCLI) {
        echo ($isError ? "\033[31m" : "\033[32m") . $message . "\033[0m" . PHP_EOL;
    }
    
    // Ruajmë edhe në log
    logActivity($message, $isError ? 'error' : 'info');
}

// Funksion për dërgimin e emailit duke përdorur PHPMailer
function sendEmail($to, $subject, $body, $fromEmail, $fromName) {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        // Konfigurimi bazë i dërguesit
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $body;

        // Mund të shtoni këtu konfigurime shtesë SMTP nëse përdorni SMTP
        // p.sh. $mail->isSMTP(); $mail->Host = ...

        $mail->send();
        return true;
    } catch (Exception $e) {
        throw new Exception("Dërgimi i emailit dështoi: " . $mail->ErrorInfo);
    }
}

try {
    // Sigurohu që $pdo është një objekt i vlefshëm përpara përdorimit
    if (!$pdo || !is_object($pdo)) {
        $errorMsg = "Gabim: Lidhja me databazën nuk është inicializuar si duhet.";
        showMessage($errorMsg, true);
        error_log($errorMsg);
        exit(1);
    }
    if (!($pdo instanceof PDO)) {
        $errorMsg = "Gabim: PDO nuk është inicializuar si objekt PDO.";
        showMessage($errorMsg, true);
        error_log($errorMsg);
        exit(1);
    }
    // Kontrollo nëse duhet dërguar njoftime për abonimet
    $stmt = $pdo->prepare("SELECT value FROM system_settings WHERE name = 'send_subscription_notifications' LIMIT 1");
    $stmt->execute();
    $sendNotifications = $stmt->fetchColumn();
    
    if ($sendNotifications != '1') {
        showMessage("Dërgimi i njoftimeve është i çaktivizuar në konfigurime. Duke përfunduar...");
        exit();
    }
    
    // Merr ditët e pezullimit pas skadimit të abonimit
    $stmt = $pdo->prepare("SELECT value FROM system_settings WHERE name = 'subscription_grace_period' LIMIT 1");
    $stmt->execute();
    $gracePeriod = (int)$stmt->fetchColumn();
    
    // Merr periudhën në ditë për të dërguar njoftimin para skadimit
    $stmt = $pdo->prepare("SELECT value FROM system_settings WHERE name = 'subscription_reminder_days' LIMIT 1");
    $stmt->execute();
    $reminderDays = (int)$stmt->fetchColumn() ?: 3; // Default 3 ditë
    
    // Merr informacionin e sistemit për email
    $stmt = $pdo->prepare("SELECT value FROM system_settings WHERE name = 'system_email' LIMIT 1");
    $stmt->execute();
    $systemEmail = $stmt->fetchColumn() ?: 'info@noteria.al';
    
    $stmt = $pdo->prepare("SELECT value FROM system_settings WHERE name = 'system_name' LIMIT 1");
    $stmt->execute();
    $systemName = $stmt->fetchColumn() ?: 'Noteria';
    
    showMessage("Duke kontrolluar për njoftime abonimesh...");
    
    // ==== DËRGO NJOFTIMET PËR ABONIME QË DO TË SKADOJNË SHPEJT ====
    
    // Merr të gjithë noterët që kanë abonim aktiv por që do të skadojnë brenda periudhës së kujtesës
    $stmt = $pdo->prepare("
        SELECT 
            n.id, 
            n.username, 
            n.email,
            n.last_subscription_payment,
            DATEDIFF(DATE_ADD(n.last_subscription_payment, INTERVAL 1 MONTH), CURDATE()) as days_left,
            CASE 
                WHEN n.custom_price IS NOT NULL THEN n.custom_price 
                ELSE (SELECT value FROM system_settings WHERE name = 'subscription_price' LIMIT 1)
            END AS subscription_amount
        FROM 
            noteri n
        WHERE 
            n.status = 'active' 
            AND n.subscription_status = 'active'
            AND n.last_subscription_payment IS NOT NULL
            AND DATEDIFF(DATE_ADD(n.last_subscription_payment, INTERVAL 1 MONTH), CURDATE()) <= ?
            AND DATEDIFF(DATE_ADD(n.last_subscription_payment, INTERVAL 1 MONTH), CURDATE()) > 0
    ");
    $stmt->execute([$reminderDays]);
    $upcomingExpirations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($upcomingExpirations) > 0) {
        showMessage("U gjetën " . count($upcomingExpirations) . " noterë me abonime që skadojnë së shpejti.");
        
        foreach ($upcomingExpirations as $noteri) {
            $expirationDate = date('d-m-Y', strtotime($noteri['last_subscription_payment'] . ' + 1 month'));
            
            $subject = "Kujtesë për abonimin tuaj në Noteria";
            
            $body = "
                <p>I/E nderuar {$noteri['username']},</p>
                
                <p>Ju njoftojmë se abonimenti juaj mujor në platformën Noteria do të skadojë më datë <strong>{$expirationDate}</strong> 
                (pas {$noteri['days_left']} ditësh).</p>
                
                <p>Detajet e abonimit:</p>
                <ul>
                    <li>Shuma e abonimit: <strong>{$noteri['subscription_amount']} EUR</strong></li>
                    <li>Data e skadimit: <strong>{$expirationDate}</strong></li>
                </ul>
                
                <p>Për të vazhduar përdorimin e pandërprerë të platformës, ju lutemi të siguroheni që keni fonde të mjaftueshme 
                në llogarinë tuaj bankare për pagesën automatike që do të procesohet në datën e skadimit.</p>
                
                <p>Nëse keni ndonjë pyetje ose nevojë për informacion shtesë, ju lutem na kontaktoni.</p>
                
                <p>Me respekt,<br>
                Ekipi i Noterisë</p>
            ";
            
            try {
                sendEmail($noteri['email'], $subject, $body, $systemEmail, $systemName);
                showMessage("- Njoftimi për skadim u dërgua me sukses te: {$noteri['username']} ({$noteri['email']})");
                
                // Ruaj në log dërgimin e njoftimit
                $logMsg = "Njoftim për skadim të abonimit u dërgua te noteri {$noteri['username']} (ID: {$noteri['id']}) për datën {$expirationDate}";
                logActivity($logMsg);
                
            } catch (Exception $e) {
                showMessage("- Gabim në dërgimin e emailit te {$noteri['username']} ({$noteri['email']}): " . $e->getMessage(), true);
            }
            
            // Pauzë e shkurtër për të shmangur mbingarkesën e serverit të emailit
            if ($isCLI) sleep(1);
        }
    } else {
        showMessage("Nuk u gjetën noterë me abonime që skadojnë së shpejti.");
    }
    
    // ==== DËRGO NJOFTIMET PËR ABONIMET E SKADUARA ====
    
    // Merr të gjithë noterët që kanë abonim të skaduar por që janë ende brenda periudhës së pezullimit
    $stmt = $pdo->prepare("
        SELECT 
            n.id, 
            n.username, 
            n.email,
            n.last_subscription_payment,
            DATEDIFF(CURDATE(), DATE_ADD(n.last_subscription_payment, INTERVAL 1 MONTH)) as days_expired,
            CASE 
                WHEN n.custom_price IS NOT NULL THEN n.custom_price 
                ELSE (SELECT value FROM system_settings WHERE name = 'subscription_price' LIMIT 1)
            END AS subscription_amount
        FROM 
            noteri n
        WHERE 
            n.status = 'active' 
            AND n.subscription_status = 'active'
            AND n.last_subscription_payment IS NOT NULL
            AND DATEDIFF(CURDATE(), DATE_ADD(n.last_subscription_payment, INTERVAL 1 MONTH)) BETWEEN 1 AND ?
    ");
    $stmt->execute([$gracePeriod]);
    $expiredSubscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($expiredSubscriptions) > 0) {
        showMessage("U gjetën " . count($expiredSubscriptions) . " noterë me abonime të skaduara.");
        
        foreach ($expiredSubscriptions as $noteri) {
            $expirationDate = date('d-m-Y', strtotime($noteri['last_subscription_payment'] . ' + 1 month'));
            $suspensionDate = date('d-m-Y', strtotime($noteri['last_subscription_payment'] . ' + 1 month + ' . $gracePeriod . ' days'));
            
            $subject = "URGJENT: Abonimi juaj në Noteria ka skaduar";
            
            $body = "
                <p>I/E nderuar {$noteri['username']},</p>
                
                <p>Ju njoftojmë se abonimenti juaj mujor në platformën Noteria ka skaduar më datë <strong>{$expirationDate}</strong> 
                ({$noteri['days_expired']} ditë më parë).</p>
                
                <p>Detajet e abonimit:</p>
                <ul>
                    <li>Shuma e abonimit: <strong>{$noteri['subscription_amount']} EUR</strong></li>
                    <li>Data e skadimit: <strong>{$expirationDate}</strong></li>
                    <li>Data e pezullimit të llogarisë: <strong>{$suspensionDate}</strong></li>
                </ul>
                
                <p><strong>VËMENDJE:</strong> Nëse pagesa nuk kryhet brenda {$gracePeriod} ditëve nga data e skadimit, 
                llogaria juaj do të pezullohet automatikisht dhe ju nuk do të mund të aksesoni më platformën.</p>
                
                <p>Për të riaktivizuar abonimin tuaj, ju lutemi të:</p>
                <ol>
                    <li>Siguroheni që keni fonde të mjaftueshme në llogarinë tuaj bankare</li>
                    <li>Kontaktoni administratorët e platformës nëse keni ndryshuar të dhënat bankare</li>
                </ol>
                
                <p>Në rast se keni kryer pagesën dhe keni marrë këtë njoftim gabimisht, ju kërkojmë ndjesë për shqetësimin dhe 
                ju lutemi të na kontaktoni për të verifikuar transaksionin.</p>
                
                <p>Me respekt,<br>
                Ekipi i Noterisë</p>
            ";
            
            try {
                sendEmail($noteri['email'], $subject, $body, $systemEmail, $systemName);
                showMessage("- Njoftimi për abonim të skaduar u dërgua me sukses te: {$noteri['username']} ({$noteri['email']})");
                
                // Ruaj në log dërgimin e njoftimit
                $logMsg = "Njoftim për abonim të skaduar u dërgua te noteri {$noteri['username']} (ID: {$noteri['id']}) - skaduar më datë {$expirationDate}";
                logActivity($logMsg);
                
            } catch (Exception $e) {
                showMessage("- Gabim në dërgimin e emailit te {$noteri['username']} ({$noteri['email']}): " . $e->getMessage(), true);
            }
            
            // Pauzë e shkurtër për të shmangur mbingarkesën e serverit të emailit
            if ($isCLI) sleep(1);
        }
    } else {
        showMessage("Nuk u gjetën noterë me abonime të skaduara.");
    }
    
    // ==== PEZULLO ABONIMET QË KANË TEJKALUAR PERIUDHËN E PEZULLIMIT ====
    
    // Gjej dhe pezullo abonimet që kanë tejkaluar periudhën e pezullimit
    $stmt = $pdo->prepare("
        SELECT 
            n.id, 
            n.username, 
            n.email,
            n.last_subscription_payment,
            DATEDIFF(CURDATE(), DATE_ADD(n.last_subscription_payment, INTERVAL 1 MONTH)) as days_expired
        FROM 
            noteri n
        WHERE 
            n.status = 'active' 
            AND n.subscription_status = 'active'
            AND n.last_subscription_payment IS NOT NULL
            AND DATEDIFF(CURDATE(), DATE_ADD(n.last_subscription_payment, INTERVAL 1 MONTH)) > ?
    ");
    $stmt->execute([$gracePeriod]);
    $accountsToSuspend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($accountsToSuspend) > 0) {
        showMessage("U gjetën " . count($accountsToSuspend) . " llogari që duhet të pezullohen për shkak të abonimit të skaduar.");
        
        foreach ($accountsToSuspend as $noteri) {
            // Pezullo abonimin e noterit
            $updateStmt = $pdo->prepare("
                UPDATE noteri 
                SET subscription_status = 'inactive', 
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $updateStmt->execute([$noteri['id']]);
            
            $expirationDate = date('d-m-Y', strtotime($noteri['last_subscription_payment'] . ' + 1 month'));
            
            $subject = "Llogaria juaj në Noteria është pezulluar";
            
            $body = "
                <p>I/E nderuar {$noteri['username']},</p>
                
                <p>Ju njoftojmë se llogaria juaj në platformën Noteria është pezulluar për shkak të mos-pagesës së abonimit mujor.</p>
                
                <p>Detajet:</p>
                <ul>
                    <li>Data e skadimit të abonimit: <strong>{$expirationDate}</strong></li>
                    <li>Ditë të kaluara nga skadimi: <strong>{$noteri['days_expired']}</strong></li>
                    <li>Periudha e pezullimit (ditë): <strong>{$gracePeriod}</strong></li>
                </ul>
                
                <p>Për të riaktivizuar llogarinë tuaj, ju lutemi të kontaktoni administratorët e platformës dhe të rregulloni 
                situatën e pagesës së abonimit.</p>
                
                <p>Me respekt,<br>
                Ekipi i Noterisë</p>
            ";
            
            try {
                sendEmail($noteri['email'], $subject, $body, $systemEmail, $systemName);
                showMessage("- Llogaria u pezullua dhe njoftimi u dërgua me sukses te: {$noteri['username']} ({$noteri['email']})");
                
                // Ruaj në log pezullimin e llogarisë
                $logMsg = "Llogaria e noterit {$noteri['username']} (ID: {$noteri['id']}) u pezullua për shkak të abonimit të skaduar";
                logActivity($logMsg, 'warning');
                
            } catch (Exception $e) {
                showMessage("- Llogaria u pezullua por pati gabim në dërgimin e emailit te {$noteri['username']} ({$noteri['email']}): " . $e->getMessage(), true);
            }
            
            // Pauzë e shkurtër për të shmangur mbingarkesën e serverit të emailit
            if ($isCLI) sleep(1);
        }
    } else {
        showMessage("Nuk u gjetën llogari që duhet të pezullohen.");
    }
    
    showMessage("Procesi i dërgimit të njoftimeve përfundoi me sukses.");
    
} catch (PDOException $e) {
    $errorMsg = "Ndodhi një gabim gjatë dërgimit të njoftimeve: " . $e->getMessage();
    showMessage($errorMsg, true);
    error_log($errorMsg);
    exit(1);
}
?>