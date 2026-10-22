<?php
/**
 * Discounts API Controller
 */
header('Content-Type: application/json');
session_start();

// Kontrolloni aksesin admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../classes/DiscountManager.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$discounts = new DiscountManager($pdo);

try {
    switch ($action) {
        case 'list':
            // Merr të gjitha zbritjet aktive
            $result = $discounts->getActiveDiscounts();
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        case 'get':
            // Merr zbritje sipas ID
            $id = intval($_GET['id'] ?? 0);
            if (!$id) throw new Exception('ID mungon');
            
            $discount = $discounts->getDiscountById($id);
            if (!$discount) throw new Exception('Zbritja nuk u gjet');
            
            echo json_encode(['success' => true, 'data' => $discount]);
            break;

        case 'validate':
            // Validimo kod zbritjeje
            $code = trim($_GET['code'] ?? '');
            $planId = intval($_GET['plan_id'] ?? 0);
            $months = intval($_GET['months'] ?? 1);
            
            if (!$code) throw new Exception('Kodi i zbritjes mungon');
            
            $result = $discounts->validateDiscount($code, $planId, $months);
            
            if ($result['valid']) {
                $discount = $result['discount'];
                $discountAmount = $discounts->calculateDiscountAmount(100, $discount); // Për 100 EUR
                echo json_encode([
                    'success' => true,
                    'valid' => true,
                    'discount_amount' => $discountAmount,
                    'discount_type' => $discount['discount_type'],
                    'message' => 'Kodi i zbritjes është i vlefshëm'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'valid' => false,
                    'error' => $result['error']
                ]);
            }
            break;

        case 'create':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) $data = $_POST;
            
            if (!isset($data['code'], $data['name'], $data['discount_value'], 
                      $data['valid_from'], $data['valid_until'])) {
                throw new Exception('Fushat e nevojshme mungojnë');
            }
            
            if ($discounts->createDiscount($data)) {
                echo json_encode(['success' => true, 'message' => 'Zbritja u krijua']);
            } else {
                throw new Exception('Gabim gjatë krijimit të zbritjes');
            }
            break;

        case 'update':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            
            $id = intval($_POST['id'] ?? 0);
            if (!$id) throw new Exception('ID mungon');
            
            $data = $_POST;
            unset($data['id']);
            
            if ($discounts->updateDiscount($id, $data)) {
                echo json_encode(['success' => true, 'message' => 'Zbritja u përditësua']);
            } else {
                throw new Exception('Gabim gjatë përditësimit');
            }
            break;

        case 'delete':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            
            $id = intval($_POST['id'] ?? 0);
            if (!$id) throw new Exception('ID mungon');
            
            if ($discounts->deleteDiscount($id)) {
                echo json_encode(['success' => true, 'message' => 'Zbritja u fshi']);
            } else {
                throw new Exception('Gabim gjatë fshirjes');
            }
            break;

        default:
            throw new Exception('Aksion i panjohur: ' . $action);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
