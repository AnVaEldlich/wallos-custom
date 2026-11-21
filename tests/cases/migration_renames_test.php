<?php
/*
  Diamon's migrations were renamed from 000062-000070 to diamon_0001-0009 so
  they cannot collide with upstream migrations of the same number. See
  includes/diamon_migration_renames.php.
*/

require_once DIAMON_ROOT . '/includes/diamon_migration_renames.php';

function renames_test_migrations($db)
{
    $names = [];
    $result = $db->query('SELECT migration FROM migrations ORDER BY migration');
    while ($row = $result->fetchArray(SQLITE3_NUM)) {
        $names[] = $row[0];
    }

    return $names;
}

function renames_test_legacy_database()
{
    $db = diamon_test_open_database();
    $db->exec("DELETE FROM migrations WHERE migration LIKE 'migrations/diamon_%'");
    foreach (range(62, 70) as $number) {
        $db->exec(sprintf("INSERT INTO migrations (migration) VALUES ('migrations/%06d.php')", $number));
    }

    return $db;
}

diamon_test('a fresh database records the diamon_ migration names', function () {
    $db = diamon_test_open_database();
    $names = renames_test_migrations($db);

    assert_contains('migrations/diamon_0001.php', implode(',', $names), 'first Diamon migration is recorded');
    assert_contains('migrations/diamon_0009.php', implode(',', $names), 'last Diamon migration is recorded');
    assert_not_contains('migrations/000062.php', implode(',', $names), 'no legacy number is recorded');

    $db->close();
});

diamon_test('legacy Diamon rows are renamed once and nothing else is touched', function () {
    $db = renames_test_legacy_database();
    $before = (int) $db->querySingle('SELECT COUNT(*) FROM migrations');

    ob_start();
    diamon_rename_legacy_migrations($db);
    ob_end_clean();
    ob_start();
    diamon_rename_legacy_migrations($db);
    $secondRun = ob_get_clean();
    $names = renames_test_migrations($db);

    assert_same($before, count($names), 'row count is unchanged');
    assert_not_contains('migrations/000062.php', implode(',', $names), 'legacy 000062 row is gone');
    assert_not_contains('migrations/000070.php', implode(',', $names), 'legacy 000070 row is gone');
    assert_contains('migrations/diamon_0005.php', implode(',', $names), 'renamed row is present');
    assert_contains('migrations/000061.php', implode(',', $names), 'upstream rows are kept');
    assert_same('', $secondRun, 'second run does nothing');

    $db->close();
});

diamon_test('rows are left alone when upstream 000062 already ran', function () {
    $db = renames_test_legacy_database();
    $db->exec('ALTER TABLE subscriptions ADD COLUMN paid_at TEXT DEFAULT NULL');

    ob_start();
    diamon_rename_legacy_migrations($db);
    ob_end_clean();
    $names = renames_test_migrations($db);

    assert_contains('migrations/000062.php', implode(',', $names), 'upstream 000062 row is kept');
    assert_contains('migrations/000070.php', implode(',', $names), 'upstream 000070 row is kept');

    $db->close();
});
