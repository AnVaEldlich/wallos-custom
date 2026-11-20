<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents('php://input');
$data = json_decode($postData, true);
$subscriptionId = (int) ($data['subscription_id'] ?? 0);
if ($subscriptionId <= 0) {
    die(json_encode(['success' => false, 'message' => translate('error', $i18n)]));
}

$owned = $db->prepare('SELECT id FROM subscriptions WHERE id = :id AND user_id = :userId');
$owned->bindValue(':id', $subscriptionId, SQLITE3_INTEGER);
$owned->bindValue(':userId', $userId, SQLITE3_INTEGER);
$ownedResult = $owned->execute();
if (!$ownedResult || !$ownedResult->fetchArray(SQLITE3_ASSOC)) {
    die(json_encode(['success' => false, 'message' => translate('error', $i18n)]));
}

$month = date('Y-m');
$stmt = $db->prepare('INSERT OR IGNORE INTO usage_prompt_dismissals (user_id, subscription_id, month)
    VALUES (:userId, :subscriptionId, :month)');
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$stmt->bindValue(':subscriptionId', $subscriptionId, SQLITE3_INTEGER);
$stmt->bindValue(':month', $month, SQLITE3_TEXT);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => translate('success', $i18n)]);
    exit;
}

echo json_encode(['success' => false, 'message' => translate('error', $i18n)]);
