<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents('php://input');
$data = json_decode($postData, true);
$enabled = !empty($data['enabled']) ? 1 : 0;
$provider = ($data['provider'] ?? '') === 'adguard' ? 'adguard' : 'pihole';
$url = trim((string) ($data['url'] ?? ''));
$token = trim((string) ($data['token'] ?? ''));

if ($url !== '' && !preg_match('#^https?://#i', $url)) {
    die(json_encode(['success' => false, 'message' => translate('error', $i18n)]));
}

$stmt = $db->prepare('UPDATE settings
    SET usage_dns_enabled = :enabled, usage_dns_provider = :provider, usage_dns_url = :url, usage_dns_token = :token
    WHERE user_id = :userId');
$stmt->bindValue(':enabled', $enabled, SQLITE3_INTEGER);
$stmt->bindValue(':provider', $provider, SQLITE3_TEXT);
$stmt->bindValue(':url', $url, SQLITE3_TEXT);
$stmt->bindValue(':token', $token, SQLITE3_TEXT);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    die(json_encode(['success' => true, 'message' => translate('success', $i18n)]));
}

die(json_encode(['success' => false, 'message' => translate('error', $i18n)]));
