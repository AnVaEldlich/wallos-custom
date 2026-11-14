<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents('php://input');
$data = json_decode($postData, true);
$enabled = !empty($data['value']) ? 1 : 0;

$stmt = $db->prepare('UPDATE settings SET usage_manual_prompt = :enabled WHERE user_id = :userId');
$stmt->bindValue(':enabled', $enabled, SQLITE3_INTEGER);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    die(json_encode(['success' => true, 'message' => translate('success', $i18n)]));
}

die(json_encode(['success' => false, 'message' => translate('error', $i18n)]));
