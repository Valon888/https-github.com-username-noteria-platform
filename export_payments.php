<?php
/**
 * Advanced Analytics & Export Module
 * Exportim në CSV, PDF dhe BI Tool Integration
 */

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=pagesat_' . date('Y-m-d_H-i-s') . '.csv');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['roli'] !== 'admin') {
    exit('Unauthorized access');
}

try {
    require_once 'confidb.php';
    
    // Get filter parameters
    $filterStatus = $_GET['status'] ?? 'all';
    $filterMonth = $_GET['month'] ?? '';
    $filterMethod = $_GET['method'] ?? 'all';
    
    $whereClause = "1=1";
    $params = [];
    
    if ($filterStatus !== 'all') {
        $whereClause .= " AND sp.status = ?";
        $params[] = $filterStatus;
    }
    
    if (!empty($filterMonth)) {
        $whereClause .= " AND DATE_FORMAT(sp.payment_date, '%Y-%m') = ?";
        $params[] = $filterMonth;
    }
    
    if ($filterMethod !== 'all') {
        $whereClause .= " AND sp.payment_method = ?";
        $params[] = $filterMethod;
    }
    
    // Prepare CSV output with BOM for Excel
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    
    // CSV Header
    echo implode(",", [
        "Data",
        "Noter",
        "Email",
        "Shuma (€)",
        "Statusi",
        "Metoda",
        "ID Transaksioni",
        "Faturë Nr.",
        "Lloji",
        "Përshkrim"
    ]) . "\n";
    
    // Fetch data
    $stmt = $pdo->prepare("
        SELECT 
            sp.payment_date,
            CONCAT(n.emri, ' ', n.mbiemri) as noter,
            n.email,
            sp.amount,
            sp.status,
            sp.payment_method,
            sp.transaction_id,
            sp.invoice_number,
            sp.payment_type,
            sp.notes
        FROM subscription_payments sp
        JOIN noteri n ON sp.noter_id = n.id
        WHERE $whereClause
        ORDER BY sp.payment_date DESC
    ");
    
    $stmt->execute($params);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $csvRow = [
            date('d.m.Y H:i', strtotime($row['payment_date'])),
            '"' . str_replace('"', '""', $row['noter']) . '"',
            '"' . str_replace('"', '""', $row['email']) . '"',
            number_format($row['amount'], 2, ',', '.'),
            ucfirst($row['status']),
            ucfirst(str_replace('_', ' ', $row['payment_method'])),
            isset($row['transaction_id']) ? $row['transaction_id'] : 'N/A',
            isset($row['invoice_number']) ? $row['invoice_number'] : 'N/A',
            (strtoupper($row['payment_type']) === 'AUTOMATIC') ? 'Automatik' : 'Manual',
            '"' . str_replace('"', '""', $row['notes'] ?? '') . '"'
        ];
        
        echo implode(",", $csvRow) . "\n";
    }
    
} catch (Exception $e) {
    header('Content-Type: text/plain');
    exit('Error: ' . $e->getMessage());
}
?>
