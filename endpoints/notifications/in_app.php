<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/subscription_usage_alerts.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => translate('session_expired', $i18n),
        'notifications' => [],
    ]);
    exit;
}

$alerts = get_unused_ending_subscription_alerts($db, (int) $userId);

echo json_encode([
    'success' => true,
    'count' => count($alerts),
    'notifications' => $alerts,
]);
