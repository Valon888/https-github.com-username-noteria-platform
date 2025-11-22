<?php
// Session & Authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'db_connection.php';
require_once 'docusign_config.php';

$user_id = $_SESSION['user_id'];
$message = '';
$status = '';

// Get user info
$stmt = $conn->prepare("SELECT emri, mbiemri, email FROM users WHERE id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle document upload for signature
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $document_name = $_POST['document_name'] ?? 'Document';
    $file = $_FILES['document'];
    
    // Validate file
    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    if (!in_array($file['type'], $allowed_types)) {
        $message = "❌ Vetëm PDF dhe Word dokumente lejohen.";
        $status = 'error';
    } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB
        $message = "❌ Dokumenti nuk duhet të jetë më i madh se 5MB.";
        $status = 'error';
    } else {
        // Save document
        $upload_dir = __DIR__ . '/uploads/documents/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $filename = time() . '_' . basename($file['name']);
        $filepath = $upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Create DocuSign envelope
            $result = createDocuSignEnvelope(
                $document_name,
                $filepath,
                $user['email'],
                $user['emri'] . ' ' . $user['mbiemri']
            );
            
            if ($result['success']) {
                $message = "✅ " . $result['message'] . " Kontrolloni email-in tuaj.";
                $status = 'success';
                
                // Log activity
                $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, description) VALUES (?, 'document_sent_for_signature', ?)");
                $desc = "Dokumenti '$document_name' u dërgua për nënshkrim";
                $stmt->bind_param("ss", $user_id, $desc);
                $stmt->execute();
                $stmt->close();
            } else {
                $message = "❌ Gabim gjatë dërgimit të dokumentit për nënshkrim.";
                $status = 'error';
            }
        } else {
            $message = "❌ Gabim gjatë ngarkimit të dokumentit.";
            $status = 'error';
        }
    }
}

// Get signed documents history
$stmt = $conn->prepare("SELECT * FROM docusign_envelopes WHERE signer_email = ? ORDER BY created_at DESC");
$stmt->bind_param("s", $user['email']);
$stmt->execute();
$envelopes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Nënshkrime - Noteria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 16px 48px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2rem;
            margin-bottom: 8px;
        }
        
        .header p {
            font-size: 0.95rem;
            opacity: 0.9;
        }
        
        .content {
            padding: 40px;
        }
        
        .message {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-weight: 600;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .upload-section {
            background: #f5f7fa;
            border: 2px dashed #667eea;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            margin-bottom: 30px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .upload-section:hover {
            background: #e8eaf6;
            border-color: #764ba2;
        }
        
        .upload-section i {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 16px;
        }
        
        .upload-section h3 {
            color: #333;
            margin-bottom: 8px;
        }
        
        .upload-section p {
            color: #666;
            margin-bottom: 16px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        input[type="text"],
        input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
        }
        
        input[type="text"]:focus,
        input[type="file"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px 32px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        }
        
        .history-section h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5rem;
        }
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .history-table th {
            background: #f5f7fa;
            padding: 12px;
            text-align: left;
            font-weight: 700;
            color: #333;
            border-bottom: 2px solid #ddd;
        }
        
        .history-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
        }
        
        .status-sent {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-signed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-declined {
            background: #f8d7da;
            color: #721c24;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-pen-fancy"></i> E-Nënshkrime</h1>
            <p>Nënshkruani dokumente elektronikisht në mënyrë të sigurt</p>
        </div>
        
        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo $status; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Upload Section -->
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="upload-section" onclick="document.getElementById('fileInput').click();">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <h3>Ngarkoni Dokumentin</h3>
                    <p>Zvarritni dhe uleni dokumentin këtu ose kliko për të zgjedhur</p>
                    <input type="file" id="fileInput" name="document" accept=".pdf,.doc,.docx" style="display: none;">
                </div>
                
                <div class="form-group">
                    <label for="documentName">Emri i Dokumentit:</label>
                    <input type="text" id="documentName" name="document_name" placeholder="p.sh. Marrëveshje Pronësie" required>
                </div>
                
                <button type="submit"><i class="fas fa-paper-plane"></i> Dërgo për Nënshkrim</button>
            </form>
            
            <hr style="margin: 40px 0; border: none; border-top: 1px solid #eee;">
            
            <!-- History Section -->
            <div class="history-section">
                <h2><i class="fas fa-history"></i> Historiku i Nënshkrimeve</h2>
                
                <?php if (count($envelopes) > 0): ?>
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Dokumenti</th>
                                <th>Data</th>
                                <th>Statusi</th>
                                <th>Nënshkruar më</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($envelopes as $envelope): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($envelope['document_name']); ?></td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($envelope['created_at'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $envelope['status']; ?>">
                                            <?php 
                                            $status_labels = [
                                                'sent' => '📤 Dërguar',
                                                'delivered' => '✉️ Dorëzuar',
                                                'signed' => '✍️ Nënshkruar',
                                                'completed' => '✅ Përfunduar',
                                                'declined' => '❌ Refuzuar',
                                                'voided' => '🚫 Anuluar'
                                            ];
                                            echo $status_labels[$envelope['status']] ?? $envelope['status'];
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo $envelope['signed_at'] ? date('d.m.Y H:i', strtotime($envelope['signed_at'])) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>Nuk keni dokumenta të nënshkruar</h3>
                        <p>Ngarkoni dokumentin tuaj të parë më lart</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Drag and drop
        const uploadSection = document.querySelector('.upload-section');
        const fileInput = document.getElementById('fileInput');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadSection.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadSection.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            uploadSection.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight(e) {
            uploadSection.style.background = '#e8eaf6';
        }
        
        function unhighlight(e) {
            uploadSection.style.background = '#f5f7fa';
        }
        
        uploadSection.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
        }
    </script>
</body>
</html>
