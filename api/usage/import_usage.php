<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/usage_api.php';

header('Content-Type: application/json; charset=UTF-8');

[$userId, $payload, $error] = usage_api_user($db);
if ($error !== null) {
    echo json_encode($error);
    exit;
}

$subscriptionId = (int) ($payload['subscription_id'] ?? 0);
$dates = $payload['dates'] ?? [];
if ($subscriptionId <= 0 || !is_array($dates)) {
    echo json_encode(['success' => false, 'title' => 'Missing parameters']);
    exit;
}

$saved = 0;
foreach ($dates as $date) {
    $timestamp = is_numeric($date) ? (int) $date : strtotime((string) $date);
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
    'success' => true,
    'title' => 'usage imported',
    'count' => $saved,
]);
