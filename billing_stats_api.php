<?php
/**
 * API për Statistikat e Pagesave në Kohë Reale
 * Real-time Payment Statistics API for Dashboard
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Session security check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authorization
if (!isset($_SESSION['user_id']) || $_SESSION['roli'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'message' => 'Admin access required']);
    exit();
}

try {
    require_once 'confidb.php';
    
    // REAL-TIME METRICS
    $stats = [];
    
    // Total Revenue
    $stats['total_revenue'] = $pdo->query("
        SELECT SUM(amount) FROM subscription_payments 
        WHERE status = 'completed'
    ")->fetchColumn() ?: 0;
    
    // This Month Revenue
    $stats['monthly_revenue'] = $pdo->query("
        SELECT SUM(amount) FROM subscription_payments 
        WHERE status = 'completed' 
        AND MONTH(payment_date) = MONTH(CURDATE()) 
        AND YEAR(payment_date) = YEAR(CURDATE())
    ")->fetchColumn() ?: 0;
    
    // Today's Revenue
    $stats['today_revenue'] = $pdo->query("
        SELECT SUM(amount) FROM subscription_payments 
        WHERE status = 'completed' 
        AND DATE(payment_date) = CURDATE()
    ")->fetchColumn() ?: 0;
    
    // Pending Payments
    $stats['pending_count'] = $pdo->query("
        SELECT COUNT(*) FROM subscription_payments 
        WHERE status = 'pending'
    ")->fetchColumn() ?: 0;
    
    // Auto Processed Today
    $stats['auto_processed_today'] = $pdo->query("
        SELECT COUNT(*) FROM subscription_payments 
        WHERE payment_type = 'automatic' 
        AND DATE(processed_at) = CURDATE()
    ")->fetchColumn() ?: 0;
    
    // Active Notaries
    $stats['active_notaries'] = $pdo->query("
        SELECT COUNT(*) FROM noteri 
        WHERE aktiv = 1
    ")->fetchColumn() ?: 0;
    
    // Churn at Risk
    $stats['churn_at_risk'] = $pdo->query("
        SELECT COUNT(DISTINCT n.id) FROM noteri n
        LEFT JOIN subscription_payments sp ON n.id = sp.noter_id
        WHERE n.aktiv = 1
        AND DATEDIFF(CURDATE(), MAX(sp.payment_date)) > 45
    ")->fetchColumn() ?: 0;
    
    // Success Rate (%)
    $totalPayments = $pdo->query("SELECT COUNT(*) FROM subscription_payments")->fetchColumn();
    $successfulPayments = $pdo->query("SELECT COUNT(*) FROM subscription_payments WHERE status = 'completed'")->fetchColumn();
    $stats['success_rate'] = ($totalPayments > 0) ? round(($successfulPayments / $totalPayments) * 100, 2) : 0;
    
    // Average Payment Amount
    $stats['avg_payment_amount'] = $pdo->query("
        SELECT AVG(amount) FROM subscription_payments 
        WHERE status = 'completed'
    ")->fetchColumn() ?: 0;
    
    // Top Payment Method
    $topMethod = $pdo->query("
        SELECT payment_method, COUNT(*) as count FROM subscription_payments
        WHERE MONTH(payment_date) = MONTH(CURDATE())
        GROUP BY payment_method
        ORDER BY count DESC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    $stats['top_payment_method'] = $topMethod['payment_method'] ?? 'N/A';
    
    // Last Payment Time
    $lastPaymentTime = $pdo->query("
        SELECT MAX(payment_date) FROM subscription_payments 
        WHERE status = 'completed'
    ")->fetchColumn();
    $stats['last_payment_time'] = $lastPaymentTime ?: null;
    
    // Add timestamp
    $stats['timestamp'] = date('Y-m-d H:i:s');
    
    // Add metadata
    $stats['status'] = 'success';
    $stats['message'] = 'Real-time statistics retrieved successfully';
    
    echo json_encode($stats, JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
