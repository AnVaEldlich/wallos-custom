<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/cancellation.php';

header('Content-Type: application/json; charset=UTF-8');

$data = json_decode(file_get_contents('php://input'), true);
$cancelled = !empty($data['cancelled']);

$attempt = cancellation_resolve(
    $db,
    $userId,
    (int) ($data['attempt_id'] ?? 0),
    $cancelled ? CANCELLATION_CONFIRMED : CANCELLATION_DECLINED,
    'user'
);

if ($attempt === null) {
    echo json_encode(['success' => false, 'message' => translate('error', $i18n)]);
    $db->close();
    exit;
}

$isCancelled = in_array($attempt['status'], [CANCELLATION_CONFIRMED, CANCELLATION_DETECTED], true);

echo json_encode(array_merge(cancellation_attempt_payload($attempt), [
    'success' => true,
    'cancelled' => $isCancelled,
    'message' => translate($isCancelled ? 'subscription_cancelled' : 'cancel_outcome_kept', $i18n),
]));

$db->close();
