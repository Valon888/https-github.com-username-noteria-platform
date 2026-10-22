<?php
// filepath: api/reservations.php
// REST API endpoint for MCP Server integration
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-MCP-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

require_once __DIR__ . '/../config.php';

// ==================== AUTH ====================
$mcp_token = $_SERVER['HTTP_X_MCP_TOKEN'] ?? '';
if ($mcp_token !== MCP_SECRET_TOKEN) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'message' => 'Invalid MCP token']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

// ==================== ROUTER ====================
switch ($method) {

    // GET /api/reservations.php → lista e rezervimeve
    // GET /api/reservations.php?id=X → rezervim specifik
    // GET /api/reservations.php?user_id=X → rezervimet e userit
    // GET /api/reservations.php?status=pending → sipas statusit
    case 'GET':
        handleGet($pdo);
        break;

    // POST /api/reservations.php → krijo rezervim të ri
    case 'POST':
        handlePost($pdo, $input);
        break;

    // PUT /api/reservations.php → përditëso rezervim
    case 'PUT':
        handlePut($pdo, $input);
        break;

    // DELETE /api/reservations.php?id=X → fshi rezervim
    case 'DELETE':
        handleDelete($pdo);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

// ==================== HANDLERS ====================

function handleGet($pdo) {
    $id      = $_GET['id']      ?? null;
    $user_id = $_GET['user_id'] ?? null;
    $status  = $_GET['status']  ?? null;
    $limit   = min((int)($_GET['limit'] ?? 20), 100);
    $offset  = (int)($_GET['offset'] ?? 0);

    try {
        if ($id) {
            // Rezervim specifik
            $stmt = $pdo->prepare("
                SELECT r.*, u.emri, u.mbiemri, u.email, z.emri as zyra_emri, z.qyteti
                FROM reservations r
                JOIN users u ON r.user_id = u.id
                JOIN zyrat z ON r.zyra_id = z.id
                WHERE r.id = ?
            ");
            $stmt->execute([$id]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reservation) {
                http_response_code(404);
                echo json_encode(['error' => 'Reservation not found']);
                return;
            }

            echo json_encode(['success' => true, 'data' => $reservation]);

        } elseif ($user_id) {
            // Rezervimet e një useri
            $stmt = $pdo->prepare("
                SELECT r.*, z.emri as zyra_emri, z.qyteti
                FROM reservations r
                JOIN zyrat z ON r.zyra_id = z.id
                WHERE r.user_id = ?
                ORDER BY r.date DESC, r.time DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$user_id, $limit, $offset]);
            $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data'    => $reservations,
                'count'   => count($reservations)
            ]);

        } elseif ($status) {
            // Sipas statusit të pagesës
            $stmt = $pdo->prepare("
                SELECT r.*, u.emri, u.mbiemri, u.email, z.emri as zyra_emri, z.qyteti
                FROM reservations r
                JOIN users u ON r.user_id = u.id
                JOIN zyrat z ON r.zyra_id = z.id
                WHERE r.payment_status = ?
                ORDER BY r.date DESC, r.time DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$status, $limit, $offset]);
            $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data'    => $reservations,
                'count'   => count($reservations)
            ]);

        } else {
            // Të gjitha rezervimet
            $stmt = $pdo->prepare("
                SELECT r.*, u.emri, u.mbiemri, u.email, z.emri as zyra_emri, z.qyteti
                FROM reservations r
                JOIN users u ON r.user_id = u.id
                JOIN zyrat z ON r.zyra_id = z.id
                ORDER BY r.date DESC, r.time DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$limit, $offset]);
            $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Total count
            $total = $pdo->query("SELECT COUNT(*) FROM reservations")->fetchColumn();

            echo json_encode([
                'success' => true,
                'data'    => $reservations,
                'count'   => count($reservations),
                'total'   => (int)$total,
                'limit'   => $limit,
                'offset'  => $offset
            ]);
        }

    } catch (PDOException $e) {
        error_log('MCP API GET error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
}

function handlePost($pdo, $input) {
    // Validate required fields
    $required = ['user_id', 'zyra_id', 'service', 'date', 'time'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    $user_id        = (int)$input['user_id'];
    $zyra_id        = (int)$input['zyra_id'];
    $service        = trim($input['service']);
    $date           = $input['date'];
    $time           = $input['time'];
    $payment_method = trim($input['payment_method'] ?? 'card');
    $punonjesi_id   = !empty($input['punonjesi_id']) ? (int)$input['punonjesi_id'] : null;

    try {
        // Kontrollo nëse orari është i zënë
        $stmtChk = $pdo->prepare("SELECT id FROM reservations WHERE zyra_id = ? AND date = ? AND time = ?");
        $stmtChk->execute([$zyra_id, $date, $time]);
        if ($stmtChk->rowCount() > 0) {
            http_response_code(409);
            echo json_encode(['error' => 'Ky orar është i zënë për këtë zyrë']);
            return;
        }

        // Krijo rezervimin
        $stmt = $pdo->prepare("
            INSERT INTO reservations (user_id, zyra_id, punonjesi_id, service, date, time, payment_method, payment_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$user_id, $zyra_id, $punonjesi_id, $service, $date, $time, $payment_method]);
        $id = $pdo->lastInsertId();

        http_response_code(201);
        echo json_encode([
            'success'        => true,
            'message'        => 'Rezervimi u krijua me sukses',
            'reservation_id' => (int)$id,
            'data' => [
                'id'             => (int)$id,
                'user_id'        => $user_id,
                'zyra_id'        => $zyra_id,
                'service'        => $service,
                'date'           => $date,
                'time'           => $time,
                'payment_status' => 'pending'
            ]
        ]);

    } catch (PDOException $e) {
        error_log('MCP API POST error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
}

function handlePut($pdo, $input) {
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing reservation id']);
        return;
    }

    $id             = (int)$input['id'];
    $payment_status = $input['payment_status'] ?? null;
    $date           = $input['date']           ?? null;
    $time           = $input['time']           ?? null;

    try {
        $updates = [];
        $params  = [];

        if ($payment_status) {
            $allowed = ['pending', 'paid', 'failed', 'cancelled'];
            if (!in_array($payment_status, $allowed)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid payment_status value']);
                return;
            }
            $updates[] = "payment_status = ?";
            $params[]  = $payment_status;
        }

        if ($date) { $updates[] = "date = ?"; $params[] = $date; }
        if ($time) { $updates[] = "time = ?"; $params[] = $time; }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            return;
        }

        $params[] = $id;
        $sql = "UPDATE reservations SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode([
            'success' => true,
            'message' => 'Rezervimi u përditësua',
            'id'      => $id
        ]);

    } catch (PDOException $e) {
        error_log('MCP API PUT error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
}

function handleDelete($pdo) {
    $id = $_GET['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing reservation id']);
        return;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM reservations WHERE id = ?");
        $stmt->execute([(int)$id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Reservation not found']);
            return;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Rezervimi u fshi',
            'id'      => (int)$id
        ]);

    } catch (PDOException $e) {
        error_log('MCP API DELETE error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
}
?>