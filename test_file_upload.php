<?php
// Test për ngarkimin e file-ave
// filepath: d:\xampp\htdocs\noteria\test_file_upload.php

echo "<!DOCTYPE html>";
echo "<html><head><title>Test File Upload</title>";
echo "<style>body{font-family:Arial;margin:20px;} .success{color:green;} .error{color:red;} .info{background:#e3f2fd;padding:10px;border-radius:5px;margin:10px 0;}</style>";
echo "</head><body>";

echo "<h1>🧪 Test për Ngarkimin e File-ave</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    $file = $_FILES['test_file'];
    
    echo "<h2>📊 Informacioni i File</h2>";
    echo "<ul>";
    echo "<li><strong>Emri:</strong> " . htmlspecialchars($file['name']) . "</li>";
    echo "<li><strong>Tipi:</strong> " . htmlspecialchars($file['type']) . "</li>";
    echo "<li><strong>Madhësia:</strong> " . number_format($file['size'] / 1024, 2) . " KB</li>";
    echo "<li><strong>Gabimi:</strong> " . $file['error'] . "</li>";
    echo "<li><strong>Temp path:</strong> " . htmlspecialchars($file['tmp_name']) . "</li>";
    echo "</ul>";
    
    // Testo funksionin validatePaymentProof
    function validatePaymentProof($file) {
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if ($file['size'] > $max_size) {
            return false;
        }
        
        if (!in_array($file['type'], $allowed_types)) {
            return false;
        }
        
        return true;
    }
    
    echo "<h2>✅ Rezultati i Validimit</h2>";
    if ($file['error'] === UPLOAD_ERR_OK) {
        if (validatePaymentProof($file)) {
            echo "<div class='success'>✓ File është i vlefshëm dhe mund të pranohet!</div>";
            
            // Testo ngarkimin
            $upload_dir = __DIR__ . '/uploads/payment_proofs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
                echo "<div class='info'>Direktoria uploads/payment_proofs/ u krijua.</div>";
            }
            
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'test_' . date('YmdHis') . '.' . $file_extension;
            $destination = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                echo "<div class='success'>✓ File u ngarkua me sukses: {$new_filename}</div>";
                
                // Fshi file-in test pas ngarkimit
                if (file_exists($destination)) {
                    unlink($destination);
                    echo "<div class='info'>File test u fshi pas testimit.</div>";
                }
            } else {
                echo "<div class='error'>✗ Gabim në ngarkimin e file.</div>";
            }
        } else {
            echo "<div class='error'>✗ File nuk është i vlefshëm:</div>";
            if ($file['size'] > 5 * 1024 * 1024) {
                echo "<div class='error'>- Madhësia është shumë e madhe (max 5MB)</div>";
            }
            if (!in_array($file['type'], ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'])) {
                echo "<div class='error'>- Tipi i file nuk është i lejuar (vetëm PDF, JPG, PNG)</div>";
            }
        }
    } else {
        echo "<div class='error'>✗ Gabim në ngarkimin e file (Error Code: {$file['error']})</div>";
        
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
                echo "<div class='error'>File është shumë i madh (PHP ini limit)</div>";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                echo "<div class='error'>File është shumë i madh (form limit)</div>";
                break;
            case UPLOAD_ERR_PARTIAL:
                echo "<div class='error'>File u ngarkua pjesërisht</div>";
                break;
            case UPLOAD_ERR_NO_FILE:
                echo "<div class='error'>Asnjë file nuk u ngarkua</div>";
                break;
            default:
                echo "<div class='error'>Gabim i panjohur</div>";
        }
    }
    
    echo "<hr>";
    echo "<h2>📋 Konfigurimi i PHP për Upload</h2>";
    echo "<ul>";
    echo "<li><strong>upload_max_filesize:</strong> " . ini_get('upload_max_filesize') . "</li>";
    echo "<li><strong>post_max_size:</strong> " . ini_get('post_max_size') . "</li>";
    echo "<li><strong>max_file_uploads:</strong> " . ini_get('max_file_uploads') . "</li>";
    echo "<li><strong>file_uploads:</strong> " . (ini_get('file_uploads') ? 'Enabled' : 'Disabled') . "</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<h2>📁 Formulari i Testit</h2>";
echo "<form method='POST' enctype='multipart/form-data'>";
echo "<p><label>Zgjidhni një file për test:</label></p>";
echo "<input type='file' name='test_file' accept='.pdf,.jpg,.jpeg,.png' required>";
echo "<br><br>";
echo "<input type='submit' value='Testo Ngarkimin'>";
echo "</form>";

echo "<hr>";
echo "<h2>💡 Udhëzime</h2>";
echo "<ul>";
echo "<li>Testoni me file PDF, JPG ose PNG</li>";
echo "<li>File duhet të jetë më pak se 5MB</li>";
echo "<li>Kontrolli i ngarkimit do të simulohet siç bëhet në formularin kryesor</li>";
echo "</ul>";

echo "<p><a href='zyrat_register.php'>← Kthehu te formulari i regjistrimit</a></p>";
echo "</body></html>";
?>