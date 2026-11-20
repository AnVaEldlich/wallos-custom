<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/cancellation.php';

header('Content-Type: application/json; charset=UTF-8');

$data = json_decode(file_get_contents('php://input'), true);
$attempt = cancellation_attempt($db, $userId, (int) ($data['attempt_id'] ?? 0));

if ($attempt === null) {
    echo json_encode(['success' => false, 'message' => translate('error', $i18n)]);
    $db->close();
    exit;
}

echo json_encode(array_merge(cancellation_attempt_payload($attempt), ['success' => true]));

$db->close();
