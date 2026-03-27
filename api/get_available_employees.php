<?php
/**
 * API Endpoint: Get Available Employees
 * Fetches active employees of an office that don't have conflicting reservations at a given time
 */

header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error.log');

session_start();
require_once __DIR__ . '/../config.php';

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Validate required parameters
$zyra_id = isset($_GET['zyra_id']) ? (int)$_GET['zyra_id'] : 0;
$date = isset($_GET['date']) ? trim($_GET['date']) : '';
$time = isset($_GET['time']) ? trim($_GET['time']) : '';

if (!$zyra_id || !$date || !$time) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters: zyra_id, date, time']);
    exit;
}

// Validate date and time format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD']);
    exit;
}

if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid time format. Use HH:MM']);
    exit;
}

$employees = [];
$found = false;

try {
    // Try with punonjesit table first (newer schema)
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.emri,
            p.mbiemri,
            p.email,
            p.telefoni,
            COALESCE(p.pozicioni, p.pozita) as pozita,
            COUNT(r.id) as reservation_count
        FROM punonjesit p
        LEFT JOIN reservations r ON 
            p.id = r.punonjesi_id AND 
            r.date = ? AND 
            r.time = ? AND 
            r.payment_status != 'failed'
        WHERE 
            p.zyra_id = ? AND 
            p.statusi = 'aktiv'
        GROUP BY p.id
        HAVING reservation_count = 0
        ORDER BY p.emri, p.mbiemri
    ");
    
    $stmt->execute([$date, $time, $zyra_id]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($employees)) {
        $found = true;
    }
    
} catch (PDOException $e) {
    // Table doesn't exist or query failed, continue to next attempt
    error_log("punonjesit table error: " . $e->getMessage());
}

// If no employees found, try punetoret table (legacy schema)
if (!$found && empty($employees)) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.emri,
                p.mbiemri,
                p.email,
                p.telefoni,
                p.pozita,
                COUNT(r.id) as reservation_count
            FROM punetoret p
            LEFT JOIN reservations r ON 
                p.id = r.punonjesi_id AND 
                r.date = ? AND 
                r.time = ? AND 
                r.payment_status != 'failed'
            WHERE 
                p.zyra_id = ? AND 
                p.active = 1
            GROUP BY p.id
            HAVING reservation_count = 0
            ORDER BY p.emri, p.mbiemri
        ");
        
        $stmt->execute([$date, $time, $zyra_id]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($employees)) {
            $found = true;
        }
        
    } catch (PDOException $e) {
        error_log("punetoret table error: " . $e->getMessage());
    }
}

// If no employees found in dedicated tables, try to fetch from zyrat.staff_data JSON (from zyrat_register.php)
if (!$found && empty($employees)) {
    try {
        $stmt = $pdo->prepare("SELECT staff_data FROM zyrat WHERE id = ? LIMIT 1");
        $stmt->execute([$zyra_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row && !empty($row['staff_data'])) {
            $staff_data = json_decode($row['staff_data'], true);
            if (is_array($staff_data)) {
                // Convert staff_data from zyrat_register.php to employee format
                $emp_id = 1000; // Use ID starting from 1000 to avoid conflicts
                foreach ($staff_data as $staff) {
                    $emp_id++;
                    // Parse emri field - might contain both first and last name
                    $full_name = isset($staff['emri']) ? trim($staff['emri']) : '';
                    $name_parts = array_filter(explode(' ', $full_name));
                    
                    $emri = isset($name_parts[0]) ? $name_parts[0] : '';
                    $mbiemri = isset($name_parts[1]) ? implode(' ', array_slice($name_parts, 1)) : '';
                    
                    $employees[] = [
                        'id' => $emp_id,
                        'emri' => $emri,
                        'mbiemri' => $mbiemri,
                        'email' => '',  // Not stored in staff_data from zyrat_register
                        'telefoni' => '',  // Not stored in staff_data from zyrat_register
                        'pozita' => isset($staff['pozita']) ? trim($staff['pozita']) : 'Noterit'
                    ];
                }
                $found = true;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching from zyrat.staff_data: " . $e->getMessage());
    }
}

if ($found && !empty($employees)) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'count' => count($employees),
        'employees' => $employees
    ]);
} else {
    // Return empty list but success=true to show "no employees available"
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'count' => 0,
        'employees' => []
    ]);
}
