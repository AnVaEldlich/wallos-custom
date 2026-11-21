<?php

// The settings page shows the last DNS sync without printing the token, and
// the monthly question can be postponed until next month.

$syncColumn = $db->query("SELECT * FROM pragma_table_info('settings') WHERE name='usage_dns_last_sync'");
if (!$syncColumn->fetchArray()) {
    $db->exec("ALTER TABLE settings ADD COLUMN usage_dns_last_sync TEXT DEFAULT ''");
    $db->exec("ALTER TABLE settings ADD COLUMN usage_dns_last_error TEXT DEFAULT ''");
}

$db->exec("CREATE TABLE IF NOT EXISTS usage_prompt_dismissals (
    user_id INTEGER NOT NULL,
    subscription_id INTEGER NOT NULL,
    month TEXT NOT NULL,
    PRIMARY KEY (user_id, subscription_id, month)
)");
