<?php

require_once __DIR__ . '/usage_hosts.php';
require_once __DIR__ . '/usage_sessions.php';

function usage_api_user($db)
{
    $payload = [];
    $raw = file_get_contents('php://input');
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }
    $apiKey = $payload['api_key'] ?? $payload['apiKey'] ?? $_REQUEST['api_key'] ?? $_REQUEST['apiKey'] ?? null;
    if (!$apiKey) {
        return [null, $payload, ['success' => false, 'title' => 'Missing parameters']];
    }
    $stmt = $db->prepare('SELECT id FROM user WHERE api_key = :apiKey');
    $stmt->bindValue(':apiKey', (string) $apiKey, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
    if (!$user) {
        return [null, $payload, ['success' => false, 'title' => 'Invalid API key']];
    }
    return [(int) $user['id'], $payload, null];
}

function usage_active_subscriptions($db, $userId)
{
    $stmt = $db->prepare('SELECT id, name, provider_catalog_id, url FROM subscriptions WHERE user_id = :userId AND inactive = 0');
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $subscriptions = [];
    while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
        $subscriptions[] = $row;
    }
    return $subscriptions;
}
