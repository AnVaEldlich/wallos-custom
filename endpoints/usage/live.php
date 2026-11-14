<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/usage_sessions.php';

header('Content-Type: application/json; charset=UTF-8');

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'title' => 'Unauthorized']);
    exit;
}

$asOf = time();
$summaries = usage_summaries_for_user($db, $userId, $asOf);
$subscriptions = [];
foreach ($summaries as $subscriptionId => $summary) {
    $subscriptions[] = [
        'id' => (int) $subscriptionId,
        'seconds' => (int) $summary['total'],
        'live' => (bool) $summary['live'],
        'as_of' => (int) $summary['as_of'],
    ];
}

echo json_encode([
    'success' => true,
    'as_of' => $asOf,
    'subscriptions' => $subscriptions,
]);
