<?php
/**
 * Script për shkarkimin e të gjitha faturave si ZIP arkiv
 */

session_start();
require_once 'config.php';
require_once 'confidb.php';
require_once 'developer_config.php';
require_once 'functions.php';

// Define database connection variables if they're not already defined
// These should normally come from config.php or confidb.php, but we're adding them here as a fallback
if (!isset($db_host)) $db_host = 'localhost';
if (!isset($db_name)) $db_name = 'noteria';
if (!isset($db_username)) $db_username = 'root';
if (!isset($db_password)) $db_password = '';

// Create database connection
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("ERROR: Could not connect to database. " . $e->getMessage());
}

// Kontrollo autorizimin
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php?error=auth_required");
    exit();
}

// Kontrollo nëse është admin ose super-admin (zhvillues)
$isSuperAdmin = isDeveloper($_SESSION['admin_id'] ?? 0);
$isAdmin = true;  // Të gjithë administratorët mund ta përdorin këtë funksion

if (!$isAdmin) {
    header("Location: billing_dashboard.php?error=admin_required");
    exit();
}

// Opsionet për filtrim
$filterMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$filterYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$filterType = isset($_GET['type']) ? $_GET['type'] : 'all'; // 'html', 'pdf', 'all'

// Krijo direktori temp nëse nuk ekziston
$tempDir = __DIR__ . '/temp';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

// Emri i ZIP file bazuar në filtrin
$zipFileName = "faturat_{$filterYear}_" . str_pad($filterMonth, 2, '0', STR_PAD_LEFT);
if ($filterType !== 'all') {
    $zipFileName .= "_{$filterType}";
}
$zipFileName .= '.zip';
$zipFilePath = $tempDir . '/' . $zipFileName;

// Fshi ZIP e vjetër nëse ekziston
if (file_exists($zipFilePath)) {
    unlink($zipFilePath);
}

// Kontrollo nëse duhet të krijojmë ZIP (kur formohet kërkesa)
if (isset($_GET['download']) && $_GET['download'] === 'true') {
    $invoicesDir = __DIR__ . '/faturat';
    $hasFiles = false;
    $filesToInclude = [];
    
    // Merr të gjitha faturat nga baza e të dhënave që përputhen me filtrat
    if (is_dir($invoicesDir)) {
        try {
            // We don't need to create a new connection here since we already have one,
            // but if we need to, we'll ensure the variables are defined
            if (!isset($db_host)) $db_host = 'localhost';
            if (!isset($db_name)) $db_name = 'noteria';
            if (!isset($db_username)) $db_username = 'root';
            if (!isset($db_password)) $db_password = '';
            
            // Use the existing PDO connection or create a new one if needed
            if (!isset($pdo) || $pdo === null) {
                $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_username, $db_password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
            
            // First check if invoice_number column exists
            try {
                $checkColumnSql = "SHOW COLUMNS FROM subscription_payments LIKE 'invoice_number'";
                $columnStmt = $pdo->query($checkColumnSql);
                $columnExists = $columnStmt->rowCount() > 0;
                
                if (!$columnExists) {
                    // If column doesn't exist, try finding the actual column name that might store invoice numbers
                    // Look for columns with 'invoice', 'fatura', or similar in the name
                    $tableInfoSql = "DESCRIBE subscription_payments";
                    $tableInfoStmt = $pdo->query($tableInfoSql);
                    $tableColumns = $tableInfoStmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    $possibleInvoiceColumns = array_filter($tableColumns, function($column) {
                        return stripos($column, 'invoice') !== false || 
                               stripos($column, 'fatura') !== false || 
                               stripos($column, 'bill') !== false;
                    });
                    
                    if (!empty($possibleInvoiceColumns)) {
                        // Use the first matching column
                        $invoiceColumn = reset($possibleInvoiceColumns);
                    } else {
                        // If no matching column, use ID as fallback
                        $invoiceColumn = 'id';
                    }
                } else {
                    $invoiceColumn = 'invoice_number';
                }
                
                // Build the SQL query with the correct column
                $sql = "SELECT $invoiceColumn AS invoice_number, payment_date FROM subscription_payments 
                       WHERE $invoiceColumn IS NOT NULL AND $invoiceColumn != ''";
                
                if ($filterMonth > 0) {
                    $sql .= " AND MONTH(payment_date) = :month";
                }
                
                if ($filterYear > 0) {
                    $sql .= " AND YEAR(payment_date) = :year";
                }
            } catch (Exception $e) {
                // If we can't determine the column, use a simple query as fallback
                $sql = "SELECT id AS invoice_number, payment_date FROM subscription_payments WHERE status = 'completed'";
                
                if ($filterMonth > 0) {
                    $sql .= " AND MONTH(payment_date) = :month";
                }
                
                if ($filterYear > 0) {
                    $sql .= " AND YEAR(payment_date) = :year";
                }
                
                error_log("Error checking invoice column: " . $e->getMessage());
            }
            
            $stmt = $pdo->prepare($sql);
            
            if ($filterMonth > 0) {
                $stmt->bindParam(':month', $filterMonth, PDO::PARAM_INT);
            }
            
            if ($filterYear > 0) {
                $stmt->bindParam(':year', $filterYear, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($invoices as $invoice) {
                $invoiceNumber = $invoice['invoice_number'];
                
                // Shto HTML file nëse filtri është 'all' ose 'html'
                if ($filterType === 'all' || $filterType === 'html') {
                    $htmlFile = $invoicesDir . '/' . $invoiceNumber . '.html';
                    if (file_exists($htmlFile)) {
                        $filesToInclude[] = [
                            'path' => $htmlFile,
                            'name' => 'html/' . $invoiceNumber . '.html'
                        ];
                        $hasFiles = true;
                    }
                }
                
                // Shto PDF file nëse filtri është 'all' ose 'pdf'
                if ($filterType === 'all' || $filterType === 'pdf') {
                    $pdfFile = $invoicesDir . '/' . $invoiceNumber . '.pdf';
                    if (file_exists($pdfFile)) {
                        $filesToInclude[] = [
                            'path' => $pdfFile,
                            'name' => 'pdf/' . $invoiceNumber . '.pdf'
                        ];
                        $hasFiles = true;
                    }
                }
            }
        } catch (PDOException $e) {
            die("Gabim në bazën e të dhënave: " . $e->getMessage());
        }
    }
    
    if ($hasFiles) {
        // Create ZIP file without ZipArchive (fallback method)
        if (class_exists('ZipArchive')) {
            // Use ZipArchive if available
            $zip = new ZipArchive();
            if ($zip->open($zipFilePath, ZipArchive::CREATE) === TRUE) {
                foreach ($filesToInclude as $file) {
                    $zip->addFile($file['path'], $file['name']);
                }
                $zip->close();
            } else {
                die('Gabim në krijimin e arkivit ZIP.');
            }
        } else {
            // Fallback method using PclZip or manual ZIP creation
            // Create a temporary directory structure
            $tempStructureDir = $tempDir . '/zip_structure_' . time();
            
            // Create directories for HTML and PDF if needed
            if (!is_dir($tempStructureDir)) {
                mkdir($tempStructureDir, 0777, true);
            }
            if (!is_dir($tempStructureDir . '/html') && ($filterType === 'all' || $filterType === 'html')) {
                mkdir($tempStructureDir . '/html', 0777, true);
            }
            if (!is_dir($tempStructureDir . '/pdf') && ($filterType === 'all' || $filterType === 'pdf')) {
                mkdir($tempStructureDir . '/pdf', 0777, true);
            }
            
            // Copy files to the temporary directory structure
            foreach ($filesToInclude as $file) {
                $destPath = $tempStructureDir . '/' . $file['name'];
                copy($file['path'], $destPath);
            }
            
            // Create ZIP file using shell command if available
            if (function_exists('exec') && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                // On Linux/Unix
                exec("cd " . escapeshellarg($tempStructureDir) . " && zip -r " . escapeshellarg($zipFilePath) . " .");
            } elseif (function_exists('exec') && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // On Windows with PowerShell
                $psCommand = "Compress-Archive -Path " . escapeshellarg($tempStructureDir . "\\*") . " -DestinationPath " . escapeshellarg($zipFilePath) . " -Force";
                exec("powershell -command \"{$psCommand}\"");
            } else {
                // Pure PHP method - create simple zip with just the files (no directory structure)
                $zipData = createZipFile($filesToInclude);
                file_put_contents($zipFilePath, $zipData);
            }
            
            // Remove temporary structure
            removeDirectory($tempStructureDir);
        }
        
        // Set headers për shkarkim
        header('Content-Type: application/zip');
        header('Content-disposition: attachment; filename=' . $zipFileName);
        header('Content-Length: ' . filesize($zipFilePath));
        readfile($zipFilePath);
        
        // Pastro file pas shkarkimit
        unlink($zipFilePath);
        exit;
    } else {
        // Nëse nuk ka fatura, dërgo përdoruesin prapa me një mesazh gabimi
        header('Location: billing_dashboard.php?error=no_invoices');
        exit;
    }
}

/**
 * Simple function to create a ZIP file without ZipArchive
 * @param array $files List of files to include
 * @return string ZIP file content
 */
function createZipFile($files) {
    // Simple ZIP header
    $zipData = "\x50\x4b\x03\x04";
    $centralDirectory = "";
    $eofCentralDirectory = "\x50\x4b\x05\x06\x00\x00\x00\x00";
    $entries = 0;
    $centralDirectorySize = 0;
    $centralDirectoryOffset = 0;
    
    foreach ($files as $file) {
        $filePath = $file['path'];
        $fileName = $file['name'];
        
        $fileData = file_get_contents($filePath);
        $fileSize = strlen($fileData);
        
        $fileHeader = "\x50\x4b\x03\x04";
        $fileHeader .= "\x14\x00";    // Version needed to extract
        $fileHeader .= "\x00\x00";    // General purpose bit flag
        $fileHeader .= "\x00\x00";    // Compression method (0 = none)
        $fileHeader .= "\x00\x00\x00\x00"; // Last mod time/date
        
        $crc = crc32($fileData);
        $fileHeader .= pack("V", $crc); // CRC32
        $fileHeader .= pack("V", $fileSize); // Compressed size
        $fileHeader .= pack("V", $fileSize); // Uncompressed size
        
        $fileNameLength = strlen($fileName);
        $extraFieldLength = 0;
        
        $fileHeader .= pack("v", $fileNameLength); // Filename length
        $fileHeader .= pack("v", $extraFieldLength); // Extra field length
        
        $fileHeader .= $fileName; // Filename
        
        $centralDirectoryOffset += strlen($fileHeader) + $fileSize;
        
        // Central directory entry
        $centralDirectoryEntry = "\x50\x4b\x01\x02";
        $centralDirectoryEntry .= "\x00\x00"; // Version made by
        $centralDirectoryEntry .= "\x14\x00"; // Version needed to extract
        $centralDirectoryEntry .= "\x00\x00"; // General purpose bit flag
        $centralDirectoryEntry .= "\x00\x00"; // Compression method
        $centralDirectoryEntry .= "\x00\x00\x00\x00"; // Last mod time/date
        $centralDirectoryEntry .= pack("V", $crc); // CRC32
        $centralDirectoryEntry .= pack("V", $fileSize); // Compressed size
        $centralDirectoryEntry .= pack("V", $fileSize); // Uncompressed size
        $centralDirectoryEntry .= pack("v", $fileNameLength); // Filename length
        $centralDirectoryEntry .= pack("v", $extraFieldLength); // Extra field length
        $centralDirectoryEntry .= pack("v", 0); // File comment length
        $centralDirectoryEntry .= pack("v", 0); // Disk number where file starts
        $centralDirectoryEntry .= pack("v", 0); // Internal file attributes
        $centralDirectoryEntry .= pack("V", 0); // External file attributes
        $centralDirectoryEntry .= pack("V", $entries); // Relative offset of local file header
        $centralDirectoryEntry .= $fileName; // Filename
        
        $zipData .= $fileHeader . $fileData;
        $centralDirectory .= $centralDirectoryEntry;
        
        $entries++;
        $centralDirectorySize += strlen($centralDirectoryEntry);
    }
    
    $zipData .= $centralDirectory;
    
    // End of central directory record
    $zipData .= $eofCentralDirectory;
    $zipData .= pack("v", 0); // Number of this disk
    $zipData .= pack("v", 0); // Disk where central directory starts
    $zipData .= pack("v", $entries); // Number of central directory records on this disk
    $zipData .= pack("v", $entries); // Total number of central directory records
    $zipData .= pack("V", $centralDirectorySize); // Size of central directory (bytes)
    $zipData .= pack("V", $centralDirectoryOffset); // Offset of start of central directory, relative to start of archive
    $zipData .= pack("v", 0); // Comment length
    
    return $zipData;
}

/**
 * Recursively removes a directory and all its contents
 * @param string $dir Directory to remove
 */
function removeDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        
        if (is_dir($path)) {
            removeDirectory($path);
        } else {
            unlink($path);
        }
    }
    
    rmdir($dir);
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shkarko Faturat | Noteria</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a56db;
            --success: #16a34a;
            --danger: #dc2626;
            --warning: #f59e0b;
            --info: #0ea5e9;
            --light: #f9fafb;
            --dark: #1f2937;
            --card-bg: #ffffff;
            --border: #e5e7eb;
            --text: #374151;
            --heading: #111827;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: var(--light);
            color: var(--text);
            line-height: 1.6;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            background: var(--gradient);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--heading);
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(26, 86, 219, 0.1);
        }

        .btn {
            padding: 0.875rem 1.75rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #1e40af;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(26, 86, 219, 0.3);
        }

        .btn-secondary {
            background: var(--border);
            color: var(--text);
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .download-option {
            display: flex;
            align-items: center;
            padding: 1.5rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            margin-bottom: 1.5rem;
            transition: all 0.2s;
            background: white;
        }

        .download-option:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
        }

        .download-option-icon {
            font-size: 2rem;
            margin-right: 1.5rem;
        }

        .download-option-content {
            flex-grow: 1;
        }

        .download-option-content h3 {
            margin-bottom: 0.5rem;
            color: var(--heading);
        }

        .download-option-content p {
            color: var(--text);
            font-size: 0.9rem;
        }

        .action-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            margin-top: 1rem;
            transition: all 0.2s;
        }

        .back-link:hover {
            color: #1e40af;
        }

        @media (max-width: 768px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .download-option {
                flex-direction: column;
                text-align: center;
                padding: 1.5rem 1rem;
            }
            
            .download-option-icon {
                margin-right: 0;
                margin-bottom: 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-file-invoice-dollar"></i> Shkarko Faturat Elektronike</h1>
            <p>Zgjidhni periudhën dhe formatin për faturat që dëshironi të shkarkoni</p>
        </div>

        <div class="card">
            <form action="" method="GET">
                <div class="filter-form">
                    <div class="form-group">
                        <label class="form-label" for="month">Muaji</label>
                        <select class="form-control" id="month" name="month">
                            <option value="0">Të gjithë muajt</option>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $filterMonth === $i ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $i, 1, 2000)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="year">Viti</label>
                        <select class="form-control" id="year" name="year">
                            <?php for ($i = date('Y'); $i >= date('Y')-3; $i--): ?>
                                <option value="<?php echo $i; ?>" <?php echo $filterYear === $i ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                            <option value="0" <?php echo $filterYear === 0 ? 'selected' : ''; ?>>Të gjitha vitet</option>
                        </select>
                    </div>
                </div>

                <h3 style="margin-bottom: 1.5rem; color: var(--heading);">
                    <i class="fas fa-file-export"></i> Formati i Shkarkimit
                </h3>
                
                <div class="download-options">
                    <div class="download-option" onclick="selectOption('html')">
                        <div class="download-option-icon" style="color: var(--primary);">
                            <i class="fas fa-file-code"></i>
                        </div>
                        <div class="download-option-content">
                            <h3>Fatura HTML</h3>
                            <p>Shkarkoni faturat në formatin HTML, të cilat mund të hapen në çfarëdo shfletuesi interneti.</p>
                        </div>
                        <input type="radio" name="type" value="html" <?php echo $filterType === 'html' ? 'checked' : ''; ?>
                               style="width: 20px; height: 20px;" id="html-option">
                    </div>
                    
                    <div class="download-option" onclick="selectOption('pdf')">
                        <div class="download-option-icon" style="color: var(--danger);">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div class="download-option-content">
                            <h3>Fatura PDF</h3>
                            <p>Shkarkoni faturat në formatin PDF, ideale për printim dhe ruajtje afatgjate.</p>
                        </div>
                        <input type="radio" name="type" value="pdf" <?php echo $filterType === 'pdf' ? 'checked' : ''; ?>
                               style="width: 20px; height: 20px;" id="pdf-option">
                    </div>
                    
                    <div class="download-option" onclick="selectOption('all')">
                        <div class="download-option-icon" style="color: var(--success);">
                            <i class="fas fa-file-archive"></i>
                        </div>
                        <div class="download-option-content">
                            <h3>Të Gjitha Formatet</h3>
                            <p>Shkarkoni faturat në të dy formatet (HTML dhe PDF) në një arkiv të vetëm.</p>
                        </div>
                        <input type="radio" name="type" value="all" <?php echo $filterType === 'all' ? 'checked' : ''; ?>
                               style="width: 20px; height: 20px;" id="all-option">
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="billing_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kthehu
                    </a>
                    
                    <div>
                        <button type="submit" class="btn btn-secondary" name="apply_filter" value="true">
                            <i class="fas fa-filter"></i> Apliko Filtrat
                        </button>
                        
                        <button type="submit" class="btn btn-primary" name="download" value="true">
                            <i class="fas fa-download"></i> Shkarko Faturat
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <div style="text-align: center;">
            <p>
                <i class="fas fa-info-circle"></i> Faturat do të shkarkohen si arkiv ZIP. 
                Për të aksesuar faturat individuale, shpërndaje arkivin pas shkarkimit.
            </p>
            
            <a href="billing_dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Kthehu te Paneli i Faturimit
            </a>
        </div>
    </div>

    <script>
        function selectOption(type) {
            document.getElementById('html-option').checked = (type === 'html');
            document.getElementById('pdf-option').checked = (type === 'pdf');
            document.getElementById('all-option').checked = (type === 'all');
        }

        // Highlight the selected option
        document.addEventListener('DOMContentLoaded', function() {
            const options = document.querySelectorAll('.download-option');
            
            options.forEach(option => {
                const radio = option.querySelector('input[type="radio"]');
                
                if (radio.checked) {
                    option.style.borderColor = 'var(--primary)';
                    option.style.backgroundColor = 'rgba(26, 86, 219, 0.05)';
                }
                
                option.addEventListener('click', function() {
                    // Reset all options
                    options.forEach(opt => {
                        opt.style.borderColor = 'var(--border)';
                        opt.style.backgroundColor = 'white';
                    });
                    
                    // Highlight the selected option
                    this.style.borderColor = 'var(--primary)';
                    this.style.backgroundColor = 'rgba(26, 86, 219, 0.05)';
                });
            });
        });
    </script>
</body>
</html>