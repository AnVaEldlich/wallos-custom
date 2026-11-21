<?php

$columnQuery = $db->query("SELECT name FROM pragma_table_info('subscription_usage_sessions') WHERE name='is_active'");
if (!$columnQuery || !$columnQuery->fetchArray()) {
    $db->exec('ALTER TABLE subscription_usage_sessions ADD COLUMN is_active INTEGER NOT NULL DEFAULT 0');
}
