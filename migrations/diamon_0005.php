<?php

// Usage is stored as intervals from several sources. Totals are the union of
// those intervals, computed in PHP, so overlapping reports are not added twice.

$db->exec("CREATE TABLE IF NOT EXISTS subscription_usage_sessions (
    id TEXT PRIMARY KEY,
    user_id INTEGER NOT NULL,
    subscription_id INTEGER NOT NULL,
    started_at INTEGER NOT NULL,
    ended_at INTEGER NOT NULL,
    source TEXT NOT NULL,
    device TEXT NOT NULL DEFAULT '',
    confidence TEXT NOT NULL,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id)
)");

$db->exec('CREATE INDEX IF NOT EXISTS idx_usage_sessions_user_subscription
    ON subscription_usage_sessions (user_id, subscription_id, started_at)');

$columnQuery = $db->query("SELECT * FROM pragma_table_info('settings') WHERE name='usage_manual_prompt'");
if (!$columnQuery->fetchArray()) {
    $db->exec('ALTER TABLE settings ADD COLUMN usage_manual_prompt INTEGER DEFAULT 1');
    $db->exec("ALTER TABLE settings ADD COLUMN usage_dns_enabled INTEGER DEFAULT 0");
    $db->exec("ALTER TABLE settings ADD COLUMN usage_dns_provider TEXT DEFAULT 'pihole'");
    $db->exec("ALTER TABLE settings ADD COLUMN usage_dns_url TEXT DEFAULT ''");
    $db->exec("ALTER TABLE settings ADD COLUMN usage_dns_token TEXT DEFAULT ''");
}

$db->exec('UPDATE settings SET usage_manual_prompt = 1 WHERE usage_manual_prompt IS NULL');
