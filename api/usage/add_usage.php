<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/usage_api.php';

header('Content-Type: application/json; charset=UTF-8');

[$userId, $payload, $error] = usage_api_user($db);
if ($error !== null) {
    echo json_encode($error);
    exit;
}

$source = (string) ($payload['source'] ?? $_POST['source'] ?? '');
if (!in_array($source, ['extension', 'android', 'ios_shortcut', 'import', 'manual'], true)) {
    echo json_encode(['success' => false, 'title' => 'Invalid source']);
    exit;
}

$session = [
    'id' => $payload['id'] ?? $_POST['id'] ?? '',
    'subscription_id' => $payload['subscription_id'] ?? $_POST['subscription_id'] ?? 0,
    'started_at' => $payload['started_at'] ?? $_POST['started_at'] ?? 0,
    'ended_at' => $payload['ended_at'] ?? $_POST['ended_at'] ?? 0,
    'source' => $source,
    'device' => $payload['device'] ?? $_POST['device'] ?? '',
    'is_active' => !empty($payload['is_active'] ?? $_POST['is_active'] ?? false),
];

if ($source === 'import') {
    $session['ended_at'] = $session['started_at'];
}

$failure = usage_save_session($db, $userId, $session);
if ($failure !== null) {
    echo json_encode(['success' => false, 'title' => $failure]);
    exit;
}

echo json_encode(['success' => true, 'title' => 'usage saved']);
