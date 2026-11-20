<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/cancellation.php';

header('Content-Type: application/json; charset=UTF-8');

$pending = cancellation_pending_for_user($db, $userId);
if ($pending === null) {
    echo json_encode(['success' => true, 'pending' => null]);
    $db->close();
    exit;
}

$target = cancellation_target(cancellation_subscription($db, $userId, $pending['subscription_id']));

echo json_encode([
    'success' => true,
    'pending' => array_merge(cancellation_attempt_payload($pending), [
        'subscription_name' => $pending['subscription_name'],
        'cancel_url' => $target['cancel_url'],
        'detectable' => $target['detectable'],
    ]),
]);

$db->close();
