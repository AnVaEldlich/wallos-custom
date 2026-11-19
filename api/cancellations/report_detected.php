<?php
/*
  For the Diamon browser extension: the service showed its cancellation
  confirmation for this attempt. Marks the subscription cancelled. Only an
  attempt that is still pending and belongs to the API key's user changes.
*/

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/usage_api.php';
require_once '../../includes/cancellation.php';

header('Content-Type: application/json; charset=UTF-8');

[$userId, $payload, $error] = usage_api_user($db);
if ($error !== null) {
    echo json_encode($error);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'title' => 'POST required']);
    exit;
}

$attemptId = (int) ($payload['attempt_id'] ?? $_POST['attempt_id'] ?? 0);
$attempt = cancellation_resolve($db, $userId, $attemptId, CANCELLATION_DETECTED, 'extension');

if ($attempt === null) {
    echo json_encode(['success' => false, 'title' => 'Unknown cancellation']);
    exit;
}

echo json_encode([
    'success' => true,
    'title' => 'cancellation recorded',
    'status' => $attempt['status'],
]);
