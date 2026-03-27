<?php
// Konfigurimi i raportimit të gabimeve
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Për kërkesat AJAX, fshij gabimet për të shmangur output ekstra
if (isset($_POST['ajax']) || isset($_GET['fetch_messages'])) {
    error_reporting(0);
}

// Inicializimi i sesionit
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Force HTTPS (përveç për AJAX)
if (!isset($_POST['ajax']) && !isset($_GET['fetch_messages']) && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) {
    $url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('Location: ' . $url);
    exit;
}

// Shtresa shtesë sigurie: Content Security Policy
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src *; img-src 'self' data:;");

// HSTS për siguri shtesë
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

// Regjenerimi i sesionit çdo 5 minuta
if (time() - ($_SESSION['last_regen'] ?? 0) > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regen'] = time();
}

// Gjenero token CSRF nëse nuk ekziston
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Kontrolli i autentifikimit
$is_logged_in = !empty($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;

if (!$is_logged_in) {
    if (isset($_POST['ajax']) || isset($_GET['fetch_messages'])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
    header('Location: login.php');
    exit;
}

// Inicializimi i lidhjes me databazën
if (!class_exists('mysqli')) {
    die('mysqli extension not available. Please enable it in PHP.');
}
require_once 'db_connection.php';

// Merr të dhënat e përdoruesit
$user = null;
if ($is_logged_in) {
    try {
        $stmt = $conn->prepare("SELECT id, emri, mbiemri, email, zyra_id FROM users WHERE id = ?");
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
    } catch (Exception $e) {
        error_log("User query error: " . $e->getMessage());
        $user = null;
    }
}

$zyra_id = $user['zyra_id'] ?? null;
$selected_zyra_id = $_GET['zyra_id'] ?? $zyra_id;
$noter_id = null;
if ($selected_zyra_id) {
    try {
        $stmt = $conn->prepare("SELECT id FROM users WHERE zyra_id = ? AND roli = 'admin' LIMIT 1");
        $stmt->bind_param("s", $selected_zyra_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $noter_row = $result->fetch_assoc();
        $noter_id = $noter_row['id'] ?? null;
        $stmt->close();
    } catch (Exception $e) {
        error_log("Noter query error: " . $e->getMessage());
        $noter_id = null;
    }
}

$messages = [];
if ($noter_id) {
    try {
        $stmt = $conn->prepare("SELECT m.message_text, m.id, u.emri, u.mbiemri, m.sender_id = ? as is_mine
            FROM messages m
            JOIN users u ON (m.sender_id = u.id)
            WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.id DESC LIMIT 50");
        $stmt->bind_param("sssss", $user_id, $user_id, $noter_id, $noter_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        $messages = array_reverse($messages); // Reverse to ASC order
        $stmt->close();
    } catch (Exception $e) {
        error_log("Chat query error: " . $e->getMessage());
        $messages = [];
    }
}

$zyrat_display = [];
try {
    $stmt = $conn->prepare("SELECT DISTINCT z.id, z.emri, z.qyteti, z.shteti, z.telefoni, z.email, z.adresa FROM zyrat z INNER JOIN users u ON z.id = u.zyra_id WHERE u.roli = 'admin' ORDER BY z.emri ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $zyrat_display[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Zyrat query error: " . $e->getMessage());
    $zyrat_display = [];
}

// Trajto dërgimin e mesazhit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    if (isset($_POST['ajax']) && !$noter_id) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Please select a notary office first']);
        exit;
    }

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        if (isset($_POST['ajax'])) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid request']);
            exit;
        }
        error_log("CSRF token invalid");
        die('Invalid request');
    }

    if (!$noter_id) {
        header('Location: chat.php');
        exit;
    }

    $msg = trim($_POST['message']);

    // Shtresa shtesë: Sanitizimi i inputit
    $msg = strip_tags($msg); // Heq HTML tags
    $msg = filter_var($msg, FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);

    // Validimi i inputit
    if (strlen($msg) < 1 || strlen($msg) > 1000) {
        if (isset($_POST['ajax'])) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Message too short or too long']);
            exit;
        }
        error_log("Message length invalid");
        die('Message too short or too long');
    }

    // Rate limiting: një mesazh çdo 5 sekonda
    $last_message_time = $_SESSION['last_message_time'] ?? 0;
    if (time() - $last_message_time < 5) {
        if (isset($_POST['ajax'])) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Please wait before sending another message']);
            exit;
        }
        error_log("Rate limit exceeded");
        die('Please wait before sending another message');
    }

    try {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $user_id, $noter_id, $msg);
        $stmt->execute();
        $stmt->close();
        $_SESSION['last_message_time'] = time();

        // Shtresa shtesë: Log për audit
        error_log("Message sent: User $user_id to Noter $noter_id: " . substr($msg, 0, 100) . "...");

        if (isset($_POST['ajax'])) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }

        header('Location: chat.php?zyra_id=' . urlencode($selected_zyra_id));
        exit;
    } catch (Exception $e) {
        if (isset($_POST['ajax'])) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Database error']);
            exit;
        }
        error_log("Insert message error: " . $e->getMessage());
        // Perhaps show error to user
    }
}

// Trajto kërkesën për të marrë mesazhet (për AJAX)
if (isset($_GET['fetch_messages'])) {
    ob_clean();
    header('Content-Type: application/json');
    if (!$noter_id) {
        echo json_encode([]);
        exit;
    }
    $messages_json = [];
    try {
        $stmt = $conn->prepare("SELECT m.message_text, m.id, u.emri, u.mbiemri, m.sender_id = ? as is_mine
            FROM messages m
            JOIN users u ON (m.sender_id = u.id)
            WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.id DESC LIMIT 50");
        $stmt->bind_param("sssss", $user_id, $user_id, $noter_id, $noter_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $messages_json[] = $row;
        }
        $messages_json = array_reverse($messages_json);
        $stmt->close();
    } catch (Exception $e) {
        error_log("Chat query error: " . $e->getMessage());
        $messages_json = [];
    }
    echo json_encode($messages_json);
    exit;
}

// Mbyll lidhjen me databazën
$conn->close();

// Nëse është AJAX, ndalo këtu (asnjë HTML)
if (isset($_POST['ajax']) || isset($_GET['fetch_messages'])) {
    exit;
}

?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat me Noterin | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', Arial, sans-serif;
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            min-height: 100vh;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        .chat-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 85vh;
            transition: all 0.3s ease;
        }

        .chat-header {
            background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
            color: white;
            padding: 25px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .chat-header h1 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .chat-header p {
            font-size: 1rem;
            opacity: 0.9;
        }

        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #fafafa;
            scroll-behavior: smooth;
        }

        .message {
            margin-bottom: 20px;
            padding: 12px 18px;
            border-radius: 18px;
            max-width: 75%;
            position: relative;
            animation: fadeIn 0.3s ease-in;
            word-wrap: break-word;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message.mine {
            background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
            color: white;
            margin-left: auto;
            text-align: right;
            box-shadow: 0 2px 8px rgba(25,118,210,0.3);
        }

        .message.other {
            background: white;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .message .sender {
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .message .time {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 5px;
        }

        .chat-input {
            padding: 20px;
            background: white;
            border-top: 1px solid #e0e0e0;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
        }

        .chat-input form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }

        .chat-input textarea {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            resize: none;
            font-family: inherit;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            outline: none;
        }

        .chat-input textarea:focus {
            border-color: #1976d2;
        }

        .chat-input button {
            padding: 12px 24px;
            background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(25,118,210,0.3);
        }

        .chat-input button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(25,118,210,0.4);
        }

        .no-notary {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .no-notary i {
            font-size: 4rem;
            color: #1976d2;
            margin-bottom: 20px;
        }

        .no-notary h2 {
            margin-bottom: 10px;
            color: #333;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            .chat-container {
                height: 90vh;
                border-radius: 15px;
            }
            .chat-header {
                padding: 20px;
            }
            .chat-header h1 {
                font-size: 1.5rem;
            }
            .message {
                max-width: 85%;
            }
            .chat-input form {
                gap: 10px;
            }
            .chat-input button {
                padding: 10px 18px;
            }
        }

        /* Office cards */
        .office-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 24px;
            margin-top: 28px;
        }

        .office-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border: 2px solid #e3f2fd;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 16px rgba(25,118,210,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .office-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(25,118,210,0.15);
            border-color: #1976d2;
        }

        .office-card::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle, rgba(25,118,210,0.1) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
        }

        .office-card > div {
            position: relative;
            z-index: 1;
        }

        .office-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 2px solid #e3f2fd;
        }

        .office-card-header span {
            font-size: 2.5rem;
        }

        .office-card-header h3 {
            margin: 0;
            color: #1565c0;
            font-size: 1.4rem;
            font-weight: 700;
        }

        .office-card p {
            margin: 10px 0;
            color: #666;
            font-size: 1rem;
        }

        .office-card p strong {
            color: #1976d2;
        }

        .office-card a {
            color: #1976d2;
            text-decoration: none;
            font-weight: 500;
        }

        .office-card button {
            width: 100%;
            margin-top: 18px;
            background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .office-card button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(25,118,210,0.4);
        }

        .warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 10px;
            padding: 16px;
            color: #856404;
            text-align: center;
            font-size: 1.05rem;
            margin-top: 20px;
        }

        /* Navigation */
        .navbar {
            background: linear-gradient(135deg, #1565c0 0%, #0d47a1 100%);
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .navbar .logo {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            text-decoration: none;
        }

        .navbar .nav-links {
            display: flex;
            gap: 20px;
        }

        .navbar .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .navbar .nav-links a:hover {
            background: rgba(255,255,255,0.1);
            transform: translateY(-1px);
        }

        .navbar .nav-links a.active {
            background: rgba(255,255,255,0.2);
        }

        @media (max-width: 768px) {
            .navbar .nav-links {
                display: none; /* Could add mobile menu later */
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">Noteria</a>
            <div class="nav-links">
                <a href="index.php">Dashboard</a>
                <a href="offices.php" class="active">Zyrat Noteriale</a>
                <a href="chat.php">Chat</a>
                <a href="logout.php">Dil</a>
            </div>
        </div>
    </nav>
    <div class="container">
        <div class="chat-container">
            <div class="chat-header">
                <h1><i class="fas fa-comments"></i> Chat me Noterin</h1>
                <p>Komunikoni direkt me noterin për çdo pyetje apo problem</p>
            </div>
            <?php if ($noter_id): ?>
            <div class="chat-messages" id="chat-messages">
                <?php foreach ($messages as $message): ?>
                <div class="message <?php echo $message['is_mine'] ? 'mine' : 'other'; ?>">
                    <div class="sender"><?php echo htmlspecialchars($message['is_mine'] ? 'Ju' : $message['emri'] . ' ' . $message['mbiemri']); ?></div>
                    <div><?php echo htmlspecialchars($message['message_text']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="chat-input">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <textarea name="message" rows="2" placeholder="Shkruani mesazhin tuaj..." required></textarea>
                    <button type="submit" name="send_message"><i class="fas fa-paper-plane"></i> Dërgo</button>
                </form>
            </div>
            <?php else: ?>
            <div class="no-notary">
                <i class="fas fa-comments"></i>
                <h2>Zgjedh Zyrën Noteriale për të Chatuar</h2>
                <p>Zgjedh një zyrë noteriale për të filluar një bisedë me noterin përkatës.</p>
                <?php if (!empty($zyrat_display)): ?>
                <div class="office-grid">
                    <?php foreach ($zyrat_display as $zyra):
                        $emri = htmlspecialchars((string)($zyra['emri'] ?? 'Zyra pa emër'));
                        $qyteti = htmlspecialchars((string)($zyra['qyteti'] ?? ''));
                        $shteti = htmlspecialchars((string)($zyra['shteti'] ?? ''));
                        $telefoni = htmlspecialchars((string)($zyra['telefoni'] ?? 'N/A'));
                        $email = htmlspecialchars((string)($zyra['email'] ?? 'N/A'));
                        $adresa = htmlspecialchars((string)($zyra['adresa'] ?? ''));
                    ?>
                    <div class="office-card">
                        <div>
                            <div class="office-card-header">
                                <span>⚖️</span>
                                <h3><?php echo $emri; ?></h3>
                            </div>
                            <p><strong>📍 Vendndodhja:</strong> <?php if ($qyteti) echo $qyteti . ', '; echo $shteti; ?></p>
                            <?php if ($adresa): ?>
                            <p><strong>🏢 Adresa:</strong> <?php echo $adresa; ?></p>
                            <?php endif; ?>
                            <p><strong>📞 Telefoni:</strong> 
                                <?php if ($telefoni !== 'N/A'): ?>
                                <a href="tel:<?php echo $telefoni; ?>"><?php echo $telefoni; ?></a>
                                <?php else: echo 'N/A'; endif; ?>
                            </p>
                            <p><strong>✉️ Email:</strong> 
                                <?php if ($email !== 'N/A'): ?>
                                <a href="mailto:<?php echo $email; ?>"><?php echo $email; ?></a>
                                <?php else: echo 'N/A'; endif; ?>
                            </p>
                            <button onclick="window.location.href='chat.php?zyra_id=<?php echo $zyra['id']; ?>'">Chat me Noterin →</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="warning">
                    ⚠️ Nuk ka asnjë zyrë noteriale në sistem.
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Scroll to bottom on load
        window.onload = function() {
            var chatMessages = document.getElementById('chat-messages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        };
    </script>
</body>
</html>