<?php
/**
 * Subscription Plans API Controller
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
require_once __DIR__ . '/../../classes/SubscriptionPlan.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$plans = new SubscriptionPlan($pdo);

try {
    switch ($action) {
        case 'list':
            // Merr të gjitha pakete
            $result = $plans->getActivePlans();
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        case 'get':
            // Merr paketë sipas ID
            $id = intval($_GET['id'] ?? 0);
            if (!$id) throw new Exception('ID mungon');
            
            $plan = $plans->getPlanById($id);
            if (!$plan) throw new Exception('Paketa nuk u gjet');
            
            echo json_encode(['success' => true, 'data' => $plan]);
            break;

        case 'create':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) $data = $_POST;
            
            if (!isset($data['name'], $data['slug'], $data['monthly_price'])) {
                throw new Exception('Fushat e nevojshme mungojnë');
            }
            
            if ($plans->createPlan($data)) {
                echo json_encode(['success' => true, 'message' => 'Paketa u krijua me sukses']);
            } else {
                throw new Exception('Gabim gjatë krijimit të pakete');
            }
            break;

        case 'update':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            
            $id = intval($_POST['id'] ?? 0);
            if (!$id) throw new Exception('ID mungon');
            
            $data = $_POST;
            unset($data['id']);
            
            if ($plans->updatePlan($id, $data)) {
                echo json_encode(['success' => true, 'message' => 'Paketa u përditësua']);
            } else {
                throw new Exception('Gabim gjatë përditësimit');
            }
            break;

        case 'delete':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            
            $id = intval($_POST['id'] ?? 0);
            if (!$id) throw new Exception('ID mungon');
            
            if ($plans->deletePlan($id)) {
                echo json_encode(['success' => true, 'message' => 'Paketa u fshi']);
            } else {
                throw new Exception('Gabim gjatë fshirjes');
            }
            break;

        case 'create_defaults':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            
            if ($plans->createDefaultPlans()) {
                echo json_encode(['success' => true, 'message' => 'Pakete standarde u krijuan']);
            } else {
                throw new Exception('Gabim gjatë krijimit të paketave standarde');
            }
            break;

        default:
            throw new Exception('Aksion i panjohur: ' . $action);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
