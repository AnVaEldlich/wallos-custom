<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/cancellation.php';

header('Content-Type: application/json; charset=UTF-8');

$data = json_decode(file_get_contents('php://input'), true);
$subscriptionId = (int) ($data['id'] ?? 0);

$started = $subscriptionId > 0 ? cancellation_start($db, $userId, $subscriptionId) : null;
if ($started === null) {
    echo json_encode(['success' => false, 'message' => translate('error', $i18n)]);
    $db->close();
    exit;
}

echo json_encode(array_merge(cancellation_attempt_payload($started['attempt']), [
    'success' => true,
    'subscription_name' => $started['subscription']['name'],
    'cancel_url' => $started['target']['cancel_url'],
    'detectable' => $started['target']['detectable'],
]));

$db->close();
