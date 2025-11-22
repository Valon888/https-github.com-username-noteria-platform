<?php
// subscription_reports_export.php - Eksportimi i raporteve të abonimeve në formate të ndryshme
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';

// Kontrollo autorizimin (vetëm administratorët mund ta aksesojnë këtë fajll)
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Parametrat e kërkesës
$reportType = $_GET['type'] ?? 'monthly'; // monthly, yearly, detailed
$format = $_GET['format'] ?? 'csv';       // csv, excel

// Funksion për të gjeneruar një emër të sigurt për fajllin
function sanitizeFilename($name) {
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
    return $name;
}

// Funksion për përgatitjen e eksportit në CSV
function exportToCsv($data, $filename) {
    // Vendos headerat e skedarit
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Hap output stream
    $output = fopen('php://output', 'w');
    
    // Shtimi i BOM për UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Shkruaj headerat e kolonave
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        
        // Shkruaj rreshtat e të dhënave
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    // Mbyll output stream
    fclose($output);
    exit();
}

try {
    // Gjenero raportin në varësi të tipit të kërkuar
    switch ($reportType) {
        case 'monthly':
            // Raporti mujor me pagesat e abonimeve për muajin aktual
            $stmt = $pdo->prepare("
                SELECT 
                    n.username AS 'Emri i Noter',
                    n.email AS 'Email',
                    sp.payment_date AS 'Data e pagesës',
                    sp.amount AS 'Shuma',
                    sp.status AS 'Statusi',
                    sp.payment_method AS 'Metoda e pagesës',
                    sp.reference_id AS 'Referenca',
                    sp.description AS 'Përshkrimi'
                FROM 
                    subscription_payments sp
                JOIN 
                    noteri n ON sp.noteri_id = n.id
                WHERE 
                    MONTH(sp.payment_date) = MONTH(CURRENT_DATE())
                    AND YEAR(sp.payment_date) = YEAR(CURRENT_DATE())
                ORDER BY 
                    sp.payment_date DESC
            ");
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $filename = 'Abonimi_Raport_Mujor_' . date('Y_m') . '.csv';
            break;
            
        case 'yearly':
            // Raporti vjetor me pagesat e abonimeve për vitin aktual
            $stmt = $pdo->prepare("
                SELECT 
                    YEAR(sp.payment_date) AS 'Viti',
                    MONTH(sp.payment_date) AS 'Muaji',
                    COUNT(sp.id) AS 'Numri i pagesave',
                    SUM(CASE WHEN sp.status = 'completed' THEN sp.amount ELSE 0 END) AS 'Totali i pagesave të përfunduara',
                    SUM(CASE WHEN sp.status = 'failed' THEN 1 ELSE 0 END) AS 'Pagesat e dështuara',
                    SUM(CASE WHEN sp.status = 'pending' THEN 1 ELSE 0 END) AS 'Pagesat në pritje',
                    AVG(CASE WHEN sp.status = 'completed' THEN sp.amount ELSE NULL END) AS 'Shuma mesatare'
                FROM 
                    subscription_payments sp
                WHERE 
                    YEAR(sp.payment_date) = YEAR(CURRENT_DATE())
                GROUP BY 
                    YEAR(sp.payment_date), MONTH(sp.payment_date)
                ORDER BY 
                    YEAR(sp.payment_date), MONTH(sp.payment_date)
            ");
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Shtojmë emrat e muajve
            $monthNames = [
                1 => 'Janar', 2 => 'Shkurt', 3 => 'Mars', 4 => 'Prill', 
                5 => 'Maj', 6 => 'Qershor', 7 => 'Korrik', 8 => 'Gusht', 
                9 => 'Shtator', 10 => 'Tetor', 11 => 'Nëntor', 12 => 'Dhjetor'
            ];
            
            foreach ($data as &$row) {
                $monthNum = $row['Muaji'];
                $row['Emri i muajit'] = $monthNames[$monthNum] ?? '';
                
                // Riformatojmë shifrat me dy shifra pas presjes dhjetore
                $row['Totali i pagesave të përfunduara'] = number_format($row['Totali i pagesave të përfunduara'], 2, '.', '');
                $row['Shuma mesatare'] = number_format($row['Shuma mesatare'], 2, '.', '');
            }
            
            $filename = 'Abonimi_Raport_Vjetor_' . date('Y') . '.csv';
            break;
            
        case 'detailed':
            // Raport i detajuar me të gjitha pagesat dhe statistikat
            $stmt = $pdo->prepare("
                SELECT 
                    n.id AS 'ID Noter',
                    n.username AS 'Emri i Noter',
                    n.email AS 'Email',
                    CASE 
                        WHEN n.custom_price IS NOT NULL THEN n.custom_price 
                        ELSE (SELECT value FROM system_settings WHERE name = 'subscription_price' LIMIT 1)
                    END AS 'Çmimi i abonimit',
                    n.subscription_status AS 'Statusi i abonimit',
                    n.bank_account AS 'Llogaria bankare',
                    (
                        SELECT COUNT(*) 
                        FROM subscription_payments sp 
                        WHERE sp.noteri_id = n.id AND sp.status = 'completed'
                    ) AS 'Numri total i pagesave',
                    (
                        SELECT SUM(amount) 
                        FROM subscription_payments sp 
                        WHERE sp.noteri_id = n.id AND sp.status = 'completed'
                    ) AS 'Totali i pagesave (EUR)',
                    (
                        SELECT MAX(payment_date) 
                        FROM subscription_payments sp 
                        WHERE sp.noteri_id = n.id AND sp.status = 'completed'
                    ) AS 'Data e fundit e pagesës',
                    (
                        SELECT COUNT(*) 
                        FROM subscription_payments sp 
                        WHERE sp.noteri_id = n.id AND sp.status = 'failed'
                    ) AS 'Pagesat e dështuara'
                FROM 
                    noteri n
                WHERE 
                    n.status = 'active'
                ORDER BY 
                    n.username
            ");
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $filename = 'Abonimi_Raport_Detajuar_' . date('Y_m_d') . '.csv';
            break;
            
        default:
            // Raport i paspecifikuar
            header('HTTP/1.1 400 Bad Request');
            echo 'Tipi i raportit i pavlefshëm.';
            exit();
    }
    
    // Kontrollo nëse ka të dhëna
    if (empty($data)) {
        $data = [['Mesazhi' => 'Nuk ka të dhëna për këtë raport.']];
    }
    
    // Eksporto të dhënat bazuar në formatin e kërkuar
    switch ($format) {
        case 'csv':
            exportToCsv($data, $filename);
            break;
            
        // Të shtohen formate të tjera në të ardhmen (Excel, PDF etj.)
        
        default:
            header('HTTP/1.1 400 Bad Request');
            echo 'Formati i kërkuar nuk mbështetet.';
            exit();
    }
    
} catch (PDOException $e) {
    // Trajtimi i gabimit
    error_log('Gabim në eksportimin e raportit: ' . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Ndodhi një gabim gjatë gjenerimit të raportit. Ju lutem provoni përsëri.';
    exit();
}
?>