<?php

require_once 'validate.php';
require_once __DIR__ . '/../../includes/connect_endpoint_crontabs.php';
require_once __DIR__ . '/../../includes/usage_dns.php';

$stmt = $db->prepare("SELECT user_id, usage_dns_provider, usage_dns_url, usage_dns_token
    FROM settings
    WHERE usage_dns_enabled = 1 AND usage_dns_url != ''");
if (!$stmt) {
    echo "DNS usage settings are not available.\n";
    exit;
}
$result = $stmt->execute();
$saved = 0;
while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
    $saved += usage_dns_sync_user(
        $db,
        (int) $row['user_id'],
        (string) ($row['usage_dns_provider'] ?? 'pihole'),
        (string) $row['usage_dns_url'],
        (string) $row['usage_dns_token']
    );
}

echo "DNS usage sessions saved: $saved\n";
