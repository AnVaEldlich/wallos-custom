<?php

// The currency subscription cards are shown in. 0 keeps each subscription in
// the currency it was saved with.

$columnQuery = $db->query("SELECT * FROM pragma_table_info('settings') WHERE name='display_currency_id'");
$columnExists = $columnQuery->fetchArray();

if (!$columnExists) {
    $db->exec('ALTER TABLE settings ADD COLUMN display_currency_id INTEGER DEFAULT 0');
}

$db->exec('UPDATE settings SET display_currency_id = 0 WHERE display_currency_id IS NULL');
