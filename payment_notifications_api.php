<?php
// API për notifikime të shpejta për pagesa të reja
// filepath: d:\xampp\htdocs\noteria\payment_notifications_api.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'payment_config.php';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'check_new':
        // Kontrollon për pagesa të reja brenda 5 minutave të fundit
        $stmt = $pdo->query("
            SELECT COUNT(*) as new_count,
                   MAX(created_at) as latest_payment
            FROM payment_logs 
            WHERE verification_status = 'pending' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Merr pagesat e reja
        $stmt = $pdo->query("
            SELECT id, office_name, office_email, payment_amount, created_at
            FROM payment_logs 
            WHERE verification_status = 'pending' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ORDER BY created_at DESC
        ");
        $new_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'new_count' => $result['new_count'],
            'latest_payment' => $result['latest_payment'],
            'new_payments' => $new_payments,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
        
    case 'get_pending_count':
        // Merr numrin total të pagesave në pritje
        $stmt = $pdo->query("
            SELECT COUNT(*) as pending_count 
            FROM payment_logs 
            WHERE verification_status = 'pending'
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'pending_count' => $result['pending_count'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
        
    case 'get_stats':
        // Merr statistikat e përditësuara
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 ELSE 0 END) as last_hour
            FROM payment_logs
        ");
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'stats' => $stats,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>