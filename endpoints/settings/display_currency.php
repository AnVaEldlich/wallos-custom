<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents('php://input');
$data = json_decode($postData, true);
$currencyId = isset($data['value']) ? (int) $data['value'] : 0;

if ($currencyId !== 0) {
    $stmt = $db->prepare('SELECT id FROM currencies WHERE id = :id AND user_id = :userId');
    $stmt->bindValue(':id', $currencyId, SQLITE3_INTEGER);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;

    if (!$row) {
        die(json_encode([
            'success' => false,
            'message' => translate('error', $i18n),
        ]));
    }
}

$stmt = $db->prepare('UPDATE settings SET display_currency_id = :currencyId WHERE user_id = :userId');
$stmt->bindValue(':currencyId', $currencyId, SQLITE3_INTEGER);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    die(json_encode([
        'success' => true,
        'message' => translate('success', $i18n),
    ]));
}

die(json_encode([
    'success' => false,
    'message' => translate('error', $i18n),
]));
