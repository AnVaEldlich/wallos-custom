<?php
// Expects $db to be set by the caller (includes/run_migrations.php).
//
// Diamon's own migrations used to be numbered 000062-000070, the same file
// names upstream Wallos later shipped with completely different contents.
// Migrations are tracked by file name, so a database that ran Diamon's
// 000062 would silently skip upstream's 000062 after a merge, and vice versa.
//
// Diamon's migrations now live under migrations/diamon_NNNN.php, which sorts
// after every numeric upstream migration and can never collide with one.
// This runs before the list of completed migrations is read and rewrites the
// old rows of a Diamon database to the new names, so nothing is re-run and the
// upstream files with those numbers will run when they arrive.
//
// Upstream's 000062 adds subscriptions.paid_at. A database without that column
// has never run any upstream migration from 000062 on, so every row with those
// numbers belongs to Diamon. When the column exists the rows are upstream's
// and are left untouched.

function diamon_rename_legacy_migrations(SQLite3 $db)
{
    $legacyNames = [
        'migrations/000062.php' => 'migrations/diamon_0001.php',
        'migrations/000063.php' => 'migrations/diamon_0002.php',
        'migrations/000064.php' => 'migrations/diamon_0003.php',
        'migrations/000065.php' => 'migrations/diamon_0004.php',
        'migrations/000066.php' => 'migrations/diamon_0005.php',
        'migrations/000067.php' => 'migrations/diamon_0006.php',
        'migrations/000068.php' => 'migrations/diamon_0007.php',
        'migrations/000069.php' => 'migrations/diamon_0008.php',
        'migrations/000070.php' => 'migrations/diamon_0009.php',
    ];

    $hasUpstreamSchema = $db->querySingle(
        "SELECT COUNT(*) FROM pragma_table_info('subscriptions') WHERE name = 'paid_at'"
    );
    if ($hasUpstreamSchema) {
        return;
    }

    $db->exec('BEGIN');
    foreach ($legacyNames as $oldName => $newName) {
        // Very old installs stored the path with a '../../' prefix.
        $find = $db->prepare('SELECT COUNT(*) FROM migrations WHERE migration IN (:name, :prefixed)');
        $find->bindValue(':name', $oldName, SQLITE3_TEXT);
        $find->bindValue(':prefixed', '../../' . $oldName, SQLITE3_TEXT);
        $row = $find->execute()->fetchArray(SQLITE3_NUM);
        if (!$row || (int) $row[0] === 0) {
            continue;
        }

        $delete = $db->prepare('DELETE FROM migrations WHERE migration IN (:name, :prefixed)');
        $delete->bindValue(':name', $oldName, SQLITE3_TEXT);
        $delete->bindValue(':prefixed', '../../' . $oldName, SQLITE3_TEXT);
        $delete->execute();

        $insert = $db->prepare('INSERT INTO migrations (migration)
            SELECT :new WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = :new)');
        $insert->bindValue(':new', $newName, SQLITE3_TEXT);
        $insert->execute();

        echo sprintf("Renamed migration record %s to %s.\n", $oldName, $newName);
    }
    $db->exec('COMMIT');
}
