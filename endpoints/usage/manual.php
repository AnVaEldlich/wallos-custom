<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/usage_sessions.php';

$postData = file_get_contents('php://input');
$data = json_decode($postData, true);
$subscriptionId = (int) ($data['subscription_id'] ?? 0);
$minutes = isset($data['minutes']) ? (int) $data['minutes'] : 0;
if ($subscriptionId <= 0) {
    die(json_encode(['success' => false, 'message' => translate('error', $i18n)]));
}

$now = time();
$startedAt = $minutes > 0 ? $now - ($minutes * 60) : $now;
$failure = usage_save_session($db, $userId, [
    'id' => 'manual-' . substr(sha1($userId . '|' . $subscriptionId . '|' . $now), 0, 32),
    'subscription_id' => $subscriptionId,
    'started_at' => $startedAt,
    'ended_at' => $now,
    'source' => 'manual',
    'device' => '',
]);

if ($failure !== null) {
    die(json_encode(['success' => false, 'message' => translate('error', $i18n)]));
}

echo json_encode(['success' => true, 'message' => translate('success', $i18n)]);
