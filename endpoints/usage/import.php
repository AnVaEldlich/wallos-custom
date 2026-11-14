<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/usage_sessions.php';

$postData = file_get_contents('php://input');
$data = json_decode($postData, true);
$subscriptionId = (int) ($data['subscription_id'] ?? 0);
$dates = preg_split('/[\s,;]+/', (string) ($data['dates'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
if ($subscriptionId <= 0 || $dates === []) {
    die(json_encode(['success' => false, 'message' => translate('error', $i18n)]));
}

$saved = 0;
foreach ($dates as $date) {
    $timestamp = strtotime($date);
    if ($timestamp === false || $timestamp <= 0) {
        continue;
    }
    $failure = usage_save_session($db, $userId, [
        'id' => 'import-' . substr(sha1($userId . '|' . $subscriptionId . '|' . $timestamp), 0, 32),
        'subscription_id' => $subscriptionId,
        'started_at' => $timestamp,
        'ended_at' => $timestamp,
        'source' => 'import',
        'device' => '',
    ]);
    if ($failure === null) {
        $saved++;
    }
}

echo json_encode([
    'success' => $saved > 0,
    'message' => $saved > 0 ? translate('success', $i18n) : translate('error', $i18n),
    'count' => $saved,
]);
