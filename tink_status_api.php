<?php
// tink_status_api.php
// API endpoint për polling të statusit të pagesës Tink
require_once 'TinkPaymentVerifier.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$payment_id = $input['payment_id'] ?? null;

$tink_client_id = '7c9d51b261e2448b9ead293f7d706eac';
$tink_client_secret = 'SHKRUANI_KETU_CLIENT_SECRET'; // Zëvendëso me Client Secret tënd

if (!$payment_id) {
    echo json_encode(['error' => 'Mungon payment_id']);
    exit;
}

$tink = new TinkPaymentVerifier($tink_client_id, $tink_client_secret);
if (!$tink->authenticate()) {
    echo json_encode(['error' => 'Autentikimi me Tink dështoi']);
    exit;
}

$status = $tink->getPaymentStatus($payment_id);
if (isset($status['status'])) {
    echo json_encode(['status' => $status['status'], 'raw' => $status]);
} else {
    echo json_encode(['error' => 'Nuk u gjet statusi', 'raw' => $status]);
}
