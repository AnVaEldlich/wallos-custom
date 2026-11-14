<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/usage_api.php';

header('Content-Type: application/json; charset=UTF-8');

[$userId, $payload, $error] = usage_api_user($db);
if ($error !== null) {
    echo json_encode($error);
    exit;
}

$watchlist = [];
foreach (usage_watch_targets(usage_active_subscriptions($db, $userId)) as $target) {
    if ($target['hosts'] === [] && $target['packages'] === []) {
        continue;
    }
    $watchlist[] = [
        'id' => $target['id'],
        'name' => $target['name'],
        'hosts' => $target['hosts'],
        'packages' => $target['packages'],
    ];
}

echo json_encode([
    'success' => true,
    'title' => 'watchlist',
    'subscriptions' => $watchlist,
]);
