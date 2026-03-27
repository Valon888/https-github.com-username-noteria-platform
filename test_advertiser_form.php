<?php
/**
 * Test Form - Për testimin e regjistrimit
 * Aksesohet direkt në browser: http://localhost/noteria/test_advertiser_form.php
 */

session_start();
require_once 'confidb.php';

$message = '';
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = true;
    
    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
        
        $company_name = trim($_POST['company_name'] ?? '');
        $company_email = trim($_POST['company_email'] ?? '');
        $company_phone = trim($_POST['company_phone'] ?? '');
        $company_website = trim($_POST['company_website'] ?? '');
        $company_category = trim($_POST['company_category'] ?? '');
        $company_description = trim($_POST['company_description'] ?? '');
        $business_registration = trim($_POST['business_registration'] ?? '');
        
        // Validim
        if (empty($company_name)) {
            $message = '❌ Emri i kompanisë është i detyrueshëm!';
        } elseif (empty($company_email)) {
            $message = '❌ Email-i është i detyrueshëm!';
        } elseif (!filter_var($company_email, FILTER_VALIDATE_EMAIL)) {
            $message = '❌ Email-i nuk është në formatin e saktë!';
        } elseif (empty($company_category)) {
            $message = '❌ Kategoria është e detyrueshme!';
        } else {
            // Kontrolloni nëse email ekziston
            $check = $pdo->prepare("SELECT id FROM advertisers WHERE email = ?");
            $check->execute([$company_email]);
            
            if ($check->rowCount() > 0) {
                $message = '❌ Ky email është tashmë i regjistruar!';
            } else {
                // Futni në databazë - me flexible INSERT
                try {
                    $stmt = $pdo->prepare("INSERT INTO advertisers (company_name, email, phone, website, category, description, business_registration, subscription_status) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $result = $stmt->execute([
                        $company_name,
                        $company_email,
                        $company_phone,
                        $company_website,
                        $company_category,
                        $company_description,
                        $business_registration,
                        'pending'
                    ]);
                    
                    if ($result) {
                        $message = '✅ Regjistrimi u arrit me sukses! ID: ' . $pdo->lastInsertId();
                        $_POST = []; // Reset form
                    }
                } catch (PDOException $innerException) {
                    // Nëse gabim për 'category', përpiquni pa atë
                    if (strpos($innerException->getMessage(), 'category') !== false || 
                        strpos($innerException->getMessage(), '1054') !== false) {
                        
                        $stmt = $pdo->prepare("INSERT INTO advertisers (company_name, email, phone, website, description, business_registration, subscription_status) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?)");
                        
                        $result = $stmt->execute([
                            $company_name,
                            $company_email,
                            $company_phone,
                            $company_website,
                            $company_description,
                            $business_registration,
                            'pending'
                        ]);
                        
                        if ($result) {
                            $message = '✅ Regjistrimi u arrit me sukses! ID: ' . $pdo->lastInsertId();
                            $_POST = [];
                        } else {
                            $message = '❌ Dështim gjatë insertos!';
                        }
                    } else {
                        $message = '❌ Gabim në databazë: ' . $innerException->getMessage();
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $message = '❌ Gabim në databazë: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Test Form - Reklamues</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1e40af 0%, #2d6cdf 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 30px;
            border-radius: 16px 16px 0 0;
            text-align: center;
        }
        .header h1 {
            color: #1e40af;
            margin-bottom: 10px;
        }
        .header p {
            color: #666;
            font-size: 0.95rem;
        }
        .form-wrapper {
            background: white;
            padding: 40px;
            border-radius: 0 0 16px 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 0.95rem;
        }
        .message-success {
            background: #dcfce7;
            border-left: 4px solid #22c55e;
            color: #166534;
        }
        .message-error {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            color: #1e40af;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #2d6cdf;
            box-shadow: 0 0 0 3px rgba(45, 108, 223, 0.1);
        }
        textarea { resize: vertical; min-height: 100px; }
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #1e40af 0%, #2d6cdf 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(30, 64, 175, 0.4);
        }
        .info {
            background: #f0f9ff;
            border-left: 4px solid #0ea5e9;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 0.9rem;
            color: #0c4a6e;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #2d6cdf;
            text-decoration: none;
            font-weight: 600;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 Test Form - Reklamues</h1>
            <p>Testim i formës së regjistrimit</p>
        </div>
        
        <div class="form-wrapper">
            <?php if ($submitted && $message): ?>
                <?php 
                $is_success = strpos($message, '✅') === 0;
                $class = $is_success ? 'message-success' : 'message-error';
                ?>
                <div class="message <?php echo $class; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="company_name">Emri i Kompanisë *</label>
                    <input type="text" id="company_name" name="company_name" required 
                           value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>"
                           placeholder="P.sh. Teknologia Shqiptare">
                </div>
                
                <div class="form-group">
                    <label for="company_email">Email *</label>
                    <input type="email" id="company_email" name="company_email" required
                           value="<?php echo htmlspecialchars($_POST['company_email'] ?? ''); ?>"
                           placeholder="info@kompania.com">
                </div>
                
                <div class="form-group">
                    <label for="company_phone">Telefoni</label>
                    <input type="tel" id="company_phone" name="company_phone"
                           value="<?php echo htmlspecialchars($_POST['company_phone'] ?? ''); ?>"
                           placeholder="+355 69 XXX XXXX">
                </div>
                
                <div class="form-group">
                    <label for="company_website">Faqja Zyrtare</label>
                    <input type="url" id="company_website" name="company_website"
                           value="<?php echo htmlspecialchars($_POST['company_website'] ?? ''); ?>"
                           placeholder="https://www.kompania.com">
                </div>
                
                <div class="form-group">
                    <label for="company_category">Kategoria *</label>
                    <select id="company_category" name="company_category" required>
                        <option value="">-- Zgjedhni Kategorinë --</option>
                        <option value="produktet" <?php echo ($_POST['company_category'] ?? '') === 'produktet' ? 'selected' : ''; ?>>Produktet</option>
                        <option value="sherbimet" <?php echo ($_POST['company_category'] ?? '') === 'sherbimet' ? 'selected' : ''; ?>>Shërbime</option>
                        <option value="edukimi" <?php echo ($_POST['company_category'] ?? '') === 'edukimi' ? 'selected' : ''; ?>>Edukimi</option>
                        <option value="ushqim" <?php echo ($_POST['company_category'] ?? '') === 'ushqim' ? 'selected' : ''; ?>>Ushqim & Pije</option>
                        <option value="shendeti" <?php echo ($_POST['company_category'] ?? '') === 'shendeti' ? 'selected' : ''; ?>>Shëndetësi</option>
                        <option value="teknologjia" <?php echo ($_POST['company_category'] ?? '') === 'teknologjia' ? 'selected' : ''; ?>>Teknologjia</option>
                        <option value="fashion" <?php echo ($_POST['company_category'] ?? '') === 'fashion' ? 'selected' : ''; ?>>Fashion</option>
                        <option value="tjeter" <?php echo ($_POST['company_category'] ?? '') === 'tjeter' ? 'selected' : ''; ?>>Tjeter</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="company_description">Përshkrimi i Biznesit</label>
                    <textarea id="company_description" name="company_description"
                              placeholder="Tregoni se çfarë bën biznesit juaj..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="business_registration">Numri i Regjistrimit</label>
                    <input type="text" id="business_registration" name="business_registration"
                           value="<?php echo htmlspecialchars($_POST['business_registration'] ?? ''); ?>"
                           placeholder="P.sh. L123456789">
                </div>
                
                <button type="submit">📤 Dorëzo Formën</button>
            </form>
            
            <div class="info">
                <strong>ℹ️ Informacion:</strong>
                <p>Kjo është forma e testit për të verifikuar dalimet e regjistrimit. Të gjitha të dhënat do të ruhen në databazën 'advertisers'.</p>
            </div>
            
            <a href="diagnostic.php" class="back-link">← Kthehu në Diagnostic</a>
        </div>
    </div>
</body>
</html>
