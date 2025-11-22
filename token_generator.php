<?php
// token_generator.php - Gjenerues i thjeshtë i token-ave për API
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';
session_start();

// Kontrolloni nëse përdoruesi është admin
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Gjeneroni token nëse është kërkuar
$message = '';
$token = '';
$expiry = date('Y-m-d H:i:s', strtotime('+1 year'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = $_POST['description'] ?? 'API Token';
    $expiry = $_POST['expiry'] ?? date('Y-m-d H:i:s', strtotime('+1 year'));
    
    // Gjeneroni token
    $token = bin2hex(random_bytes(32));
    
    try {
        // Krijo tabelën nëse nuk ekziston
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS api_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                token VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expired_at TIMESTAMP DEFAULT NULL,
                description VARCHAR(255),
                UNIQUE(token)
            )
        ");
        
        // Ruani token në databazë
        $stmt = $pdo->prepare("INSERT INTO api_tokens (token, expired_at, description) VALUES (?, ?, ?)");
        $stmt->execute([$token, $expiry, $description]);
        
        $message = "Token u gjenerua me sukses!";
    } catch (PDOException $e) {
        $message = "Gabim: " . $e->getMessage();
    }
}

// Merrni të gjithë token-at ekzistues
$tokens = [];
try {
    $stmt = $pdo->query("SELECT * FROM api_tokens ORDER BY created_at DESC");
    $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Gabim gjatë marrjes së token-ave: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gjeneruesi i Token-ave API | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f7fa;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 25px;
        }
        h1 {
            color: #1a56db;
            margin-bottom: 25px;
        }
        form {
            margin-bottom: 30px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        input[type="text"],
        input[type="datetime-local"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        button {
            background-color: #1a56db;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        button:hover {
            background-color: #0f46c4;
        }
        .message {
            padding: 10px;
            margin: 20px 0;
            border-radius: 4px;
            font-weight: 600;
        }
        .success {
            background-color: #d1fae5;
            color: #047857;
        }
        .error {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        .token-display {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            word-break: break-all;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f5f7fa;
            font-weight: 600;
        }
        .token-table {
            overflow-x: auto;
        }
        .truncate {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gjeneruesi i Token-ave API</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'Gabim') === false ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($token): ?>
            <div>
                <h3>Token i ri:</h3>
                <div class="token-display"><?php echo $token; ?></div>
                <p><strong>Skadon më:</strong> <?php echo $expiry; ?></p>
                <p><strong>Përdorimi:</strong> Vendosni këtë token në header të kërkesave të API si:</p>
                <div class="token-display">Authorization: Bearer <?php echo $token; ?></div>
            </div>
        <?php endif; ?>
        
        <form method="post">
            <div>
                <label for="description">Përshkrimi i Token-it:</label>
                <input type="text" id="description" name="description" value="API Token" required>
            </div>
            <div>
                <label for="expiry">Data e skadimit:</label>
                <input type="datetime-local" id="expiry" name="expiry" value="<?php echo date('Y-m-d\TH:i', strtotime('+1 year')); ?>" required>
            </div>
            <button type="submit">Gjenero Token</button>
        </form>
        
        <div class="token-table">
            <h3>Token-at ekzistues (<?php echo count($tokens); ?>)</h3>
            <?php if (count($tokens) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Token (pjesërisht)</th>
                            <th>Përshkrimi</th>
                            <th>Krijuar</th>
                            <th>Skadon</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tokens as $t): ?>
                            <tr>
                                <td><?php echo $t['id']; ?></td>
                                <td class="truncate"><?php echo substr($t['token'], 0, 10) . '...'; ?></td>
                                <td><?php echo htmlspecialchars($t['description']); ?></td>
                                <td><?php echo $t['created_at']; ?></td>
                                <td><?php echo $t['expired_at']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Nuk ka token-a të ruajtur ende.</p>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 30px;">
            <h3>Dokumentacioni API</h3>
            <p>API e verifikimit të pagesave përdor këto endpoints:</p>
            <ul>
                <li><code>/mcp_api.php?endpoint=payments</code> - Liston të gjitha pagesat (GET)</li>
                <li><code>/mcp_api.php?endpoint=verify_payment</code> - Verifikon një pagesë (POST)</li>
                <li><code>/mcp_api.php?endpoint=payment_details</code> - Merr detajet e një pagese (GET)</li>
            </ul>
            <p>Për më shumë detaje, vizitoni <a href="mcp_api.php">dokumentacionin e API</a>.</p>
        </div>
    </div>
</body>
</html>