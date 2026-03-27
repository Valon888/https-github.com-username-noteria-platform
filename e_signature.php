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
    $document_name = htmlspecialchars($_POST['document_name'] ?? 'Document', ENT_QUOTES, 'UTF-8');
    $file = $_FILES['document'];
    
    // Validate file
    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    if (!in_array($file['type'], $allowed_types)) {
        $message = "❌ Vetëm PDF dhe Word dokumente lejohen.";
        $status = 'error';
        // Log security event
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, details, ip_address, user_agent) VALUES (?, 'file_upload_failed', ?, ?, ?)");
        $details = "Përpjekje për ngarkim dokumenti me tip të palejuar: " . $file['type'];
        $stmt->bind_param("ssss", $user_id, $details, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB
        $message = "❌ Dokumenti nuk duhet të jetë më i madh se 5MB.";
        $status = 'error';
        // Log security event
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, details, ip_address, user_agent) VALUES (?, 'file_upload_failed', ?, ?, ?)");
        $details = "Përpjekje për ngarkim dokumenti shumë të madh: " . $file['size'] . " bytes";
        $stmt->bind_param("ssss", $user_id, $details, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
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
                
                // Log activity with high security details
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
                $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, details, ip_address, user_agent) VALUES (?, 'document_sent_for_signature', ?, ?, ?)");
                $desc = "Dokumenti '$document_name' u dërgua për nënshkrim";
                $stmt->bind_param("ssss", $user_id, $desc, $ip_address, $user_agent);
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
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: linear-gradient(135deg, #1e40af 0%, #0f172a 50%, #1e3a8a 100%);
            color: white;
            padding: 50px 40px;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 40px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        
        .header-content {
            position: relative;
            z-index: 1;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .header p {
            font-size: 1.05rem;
            opacity: 0.9;
            font-weight: 300;
            letter-spacing: 0.5px;
        }
        
        .user-info {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-left: 5px solid #1e40af;
        }
        
        .user-info-content {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .user-details h3 {
            color: #0f172a;
            margin-bottom: 4px;
            font-size: 1.2rem;
        }
        
        .user-details p {
            color: #64748b;
            font-size: 0.95rem;
        }
        
        .content {
            background: white;
            border-radius: 20px;
            padding: 50px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.1);
        }
        
        .message {
            padding: 18px 24px;
            border-radius: 12px;
            margin-bottom: 30px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease-out;
            border-left: 5px solid;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message.success {
            background: #f0fdf4;
            color: #15803d;
            border-left-color: #22c55e;
        }
        
        .message.error {
            background: #fef2f2;
            color: #b91c1c;
            border-left-color: #ef4444;
        }
        
        .section-title {
            font-size: 1.5rem;
            color: #0f172a;
            margin-bottom: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-divider {
            height: 1px;
            background: linear-gradient(to right, transparent, #e2e8f0, transparent);
            margin: 40px 0;
        }
        
        .upload-section {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 2px dashed #0284c7;
            border-radius: 16px;
            padding: 50px 40px;
            text-align: center;
            margin-bottom: 30px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .upload-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(2,132,199,0.1) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.4s;
        }
        
        .upload-section:hover {
            background: linear-gradient(135deg, #e0f2fe 0%, #cffafe 100%);
            border-color: #0369a1;
            box-shadow: 0 20px 40px rgba(2,132,199,0.15);
            transform: translateY(-2px);
        }
        
        .upload-section:hover::before {
            opacity: 1;
        }
        
        .upload-content {
            position: relative;
            z-index: 1;
        }
        
        .upload-section i {
            font-size: 3.5rem;
            color: #0284c7;
            margin-bottom: 20px;
            display: block;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .upload-section h3 {
            color: #0c4a6e;
            margin-bottom: 8px;
            font-size: 1.3rem;
            font-weight: 700;
        }
        
        .upload-section p {
            color: #0369a1;
            margin-bottom: 16px;
            font-size: 0.95rem;
        }
        
        .format-hint {
            font-size: 0.85rem;
            color: #0284c7;
            opacity: 0.8;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        label {
            display: block;
            margin-bottom: 10px;
            font-weight: 700;
            color: #0f172a;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
        }
        
        input[type="text"],
        input[type="file"] {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.95rem;
            transition: all 0.3s;
            background: #f8fafc;
        }
        
        input[type="text"]:focus,
        input[type="file"]:focus {
            outline: none;
            border-color: #0284c7;
            background: white;
            box-shadow: 0 0 0 4px rgba(2,132,199,0.1);
        }
        
        input[type="text"]::placeholder {
            color: #94a3b8;
        }
        
        .button-group {
            display: flex;
            gap: 12px;
        }
        
        button {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: white;
            border: none;
            padding: 16px 36px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 20px rgba(2,132,199,0.3);
        }
        
        button:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(2,132,199,0.4);
        }
        
        button:active {
            transform: translateY(-1px);
        }
        
        .history-section {
            margin-top: 20px;
        }
        
        .history-section h2 {
            color: #0f172a;
            margin-bottom: 28px;
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .history-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        
        .history-table th {
            background: #f1f5f9;
            padding: 16px;
            text-align: left;
            font-weight: 700;
            color: #0f172a;
            border-bottom: 2px solid #e2e8f0;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }
        
        .history-table tr {
            transition: all 0.3s;
        }
        
        .history-table tbody tr {
            background: #f8fafc;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .history-table tbody tr:hover {
            background: #eff6ff;
            box-shadow: 0 4px 12px rgba(2,132,199,0.1);
            transform: translateX(4px);
        }
        
        .history-table td {
            padding: 16px;
            border-bottom: none;
            color: #334155;
            font-weight: 500;
        }
        
        .history-table tbody tr:first-child td:first-child {
            border-radius: 10px 0 0 10px;
        }
        
        .history-table tbody tr:first-child td:last-child {
            border-radius: 0 10px 10px 0;
        }
        
        .doc-name {
            color: #0f172a;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .doc-icon {
            font-size: 1.2rem;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }
        
        .status-sent {
            background: #fef3c7;
            color: #92400e;
            box-shadow: 0 4px 12px rgba(245,158,11,0.2);
        }
        
        .status-delivered {
            background: #dbeafe;
            color: #1e40af;
            box-shadow: 0 4px 12px rgba(2,132,199,0.2);
        }
        
        .status-signed,
        .status-completed {
            background: #dcfce7;
            color: #15803d;
            box-shadow: 0 4px 12px rgba(34,197,94,0.2);
        }
        
        .status-declined,
        .status-voided {
            background: #fee2e2;
            color: #b91c1c;
            box-shadow: 0 4px 12px rgba(239,68,68,0.2);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            color: #94a3b8;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #cbd5e1;
            opacity: 0.7;
        }
        
        .empty-state h3 {
            color: #0f172a;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        
        .empty-state p {
            color: #94a3b8;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(20px);
            }
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 35px 25px;
            }
            
            .header h1 {
                font-size: 1.8rem;
            }
            
            .content {
                padding: 35px 25px;
            }
            
            .history-table {
                font-size: 0.9rem;
            }
            
            .history-table td,
            .history-table th {
                padding: 12px;
            }
            
            button {
                padding: 14px 28px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <h1><i class="fas fa-pen-fancy"></i> E-Nënshkrime Elektronike</h1>
                <p>Nënshkruani dokumente në mënyrë të sigurt dhe profesionale | Sistem i Sertifikuar</p>
            </div>
        </div>
        
        <?php if ($user): ?>
        <div class="user-info">
            <div class="user-info-content">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['emri'], 0, 1) . substr($user['mbiemri'], 0, 1)); ?>
                </div>
                <div class="user-details">
                    <h3><?php echo htmlspecialchars($user['emri'] . ' ' . $user['mbiemri']); ?></h3>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo $status; ?>">
                    <i class="fas fa-<?php echo ($status === 'success') ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Upload Section -->
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <h2 class="section-title"><i class="fas fa-cloud-upload-alt"></i> Ngarkoni Dokumentin</h2>
                
                <div class="upload-section" onclick="document.getElementById('fileInput').click();">
                    <div class="upload-content">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <h3>Zvarritni dokumentin këtu</h3>
                        <p>ose kliko për të zgjedhur nga kompjuteri</p>
                        <span class="format-hint">🔒 PDF, Word (Maksimal 5MB)</span>
                    </div>
                    <input type="file" id="fileInput" name="document" accept=".pdf,.doc,.docx" style="display: none;">
                </div>
                
                <div class="form-group">
                    <label for="documentName"><i class="fas fa-file-alt"></i> Emri i Dokumentit</label>
                    <input type="text" id="documentName" name="document_name" placeholder="p.sh. Marrëveshje Pronësie, Akt Notarët, etj..." required>
                </div>
                
                <button type="submit"><i class="fas fa-paper-plane"></i> Dërgo për Nënshkrim</button>
            </form>
            
            <div class="section-divider"></div>
            
            <!-- History Section -->
            <div class="history-section">
                <h2><i class="fas fa-history"></i> Historiku i Nënshkrimeve</h2>
                
                <?php if (count($envelopes) > 0): ?>
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Dokumenti</th>
                                <th>Data Dërgimit</th>
                                <th>Statusi</th>
                                <th>Nënshkruar më</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($envelopes as $envelope): ?>
                                <tr>
                                    <td>
                                        <span class="doc-name">
                                            <span class="doc-icon"><i class="fas fa-file-pdf"></i></span>
                                            <?php echo htmlspecialchars($envelope['document_name']); ?>
                                        </span>
                                    </td>
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
                        <p>Ngarkoni dokumentin tuaj të parë më lart për të filluar procesin</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Drag and drop functionality
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
            uploadSection.style.background = 'linear-gradient(135deg, #e0f2fe 0%, #cffafe 100%)';
        }
        
        function unhighlight(e) {
            uploadSection.style.background = 'linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%)';
        }
        
        uploadSection.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
        }
        
        // Show selected file name
        fileInput.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                console.log('Dokumenti i zgjedhur:', this.files[0].name);
            }
        });
    </script>
</body>
</html>
