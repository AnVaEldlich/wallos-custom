<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$subscriptionId = (int) ($data['id'] ?? 0);
$inactive = !empty($data['inactive']) ? 1 : 0;

if ($subscriptionId <= 0) {
    echo json_encode([
        "success" => false,
        "message" => translate('error', $i18n),
    ]);
    $db->close();
    exit;
}

$check = $db->prepare('SELECT id FROM subscriptions WHERE id = :id AND user_id = :userId');
$check->bindValue(':id', $subscriptionId, SQLITE3_INTEGER);
$check->bindValue(':userId', $userId, SQLITE3_INTEGER);
$exists = $check->execute();
if (!$exists || !$exists->fetchArray(SQLITE3_ASSOC)) {
    echo json_encode([
        "success" => false,
        "message" => translate('error', $i18n),
    ]);
    $db->close();
    exit;
}

$update = $db->prepare('UPDATE subscriptions SET inactive = :inactive WHERE id = :id AND user_id = :userId');
$update->bindValue(':inactive', $inactive, SQLITE3_INTEGER);
$update->bindValue(':id', $subscriptionId, SQLITE3_INTEGER);
$update->bindValue(':userId', $userId, SQLITE3_INTEGER);

if ($update->execute()) {
    if (!$inactive) {
        require_once '../../includes/cancellation.php';
        cancellation_restore_on_resume($db, $userId, $subscriptionId);
    }
    echo json_encode([
        "success" => true,
        "inactive" => $inactive,
        "message" => translate($inactive ? 'subscription_cancelled' : 'subscription_resumed', $i18n),
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => translate('error', $i18n),
    ]);
}

$db->close();
