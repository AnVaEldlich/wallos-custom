<?php

// Remember which catalogue provider a subscription was started from, so cards
// can always show the right icon even when no custom logo file was uploaded.

$columnQuery = $db->query("SELECT * FROM pragma_table_info('subscriptions') WHERE name='provider_catalog_id'");
$columnExists = $columnQuery->fetchArray();

if (!$columnExists) {
    $db->exec("ALTER TABLE subscriptions ADD COLUMN provider_catalog_id TEXT DEFAULT NULL");
}

require_once __DIR__ . '/../includes/provider_catalog.php';

foreach (get_provider_catalog() as $provider) {
    $stmt = $db->prepare('UPDATE subscriptions SET provider_catalog_id = :catalogId
        WHERE provider_catalog_id IS NULL AND lower(trim(name)) = lower(:name)');
    $stmt->bindValue(':catalogId', $provider['id'], SQLITE3_TEXT);
    $stmt->bindValue(':name', $provider['name'], SQLITE3_TEXT);
    $stmt->execute();
}

?>
