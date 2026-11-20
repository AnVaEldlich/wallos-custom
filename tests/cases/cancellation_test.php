<?php
// Assisted cancellation (includes/cancellation.php): attempts, their outcome
// on the subscription, ownership, and restoring the date on resume.

require_once DIAMON_ROOT . '/includes/cancellation.php';

function cancellation_test_subscription($db, $userId, $fields = [])
{
    $fields = array_merge([
        'name' => 'Spotify',
        'url' => '',
        'provider_catalog_id' => null,
        'cycle' => 3,
        'next_payment' => date('Y-m-d', strtotime('+12 days')),
        'cancellation_date' => null,
    ], $fields);

    $stmt = $db->prepare('INSERT INTO subscriptions (user_id, name, price, currency_id, next_payment, cycle, frequency, inactive, url, provider_catalog_id, cancellation_date)
        VALUES (:userId, :name, 10, :currencyId, :next, :cycle, 1, 0, :url, :catalogId, :cancellationDate)');
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':name', $fields['name'], SQLITE3_TEXT);
    $stmt->bindValue(':currencyId', diamon_test_currency_id($userId, 0), SQLITE3_INTEGER);
    $stmt->bindValue(':next', $fields['next_payment'], SQLITE3_TEXT);
    $stmt->bindValue(':cycle', $fields['cycle'], SQLITE3_INTEGER);
    $stmt->bindValue(':url', $fields['url'], SQLITE3_TEXT);
    $stmt->bindValue(':catalogId', $fields['provider_catalog_id'], $fields['provider_catalog_id'] === null ? SQLITE3_NULL : SQLITE3_TEXT);
    $stmt->bindValue(':cancellationDate', $fields['cancellation_date'], $fields['cancellation_date'] === null ? SQLITE3_NULL : SQLITE3_TEXT);
    $stmt->execute();

    return $db->lastInsertRowID();
}

function cancellation_test_row($db, $subscriptionId)
{
    return $db->querySingle('SELECT inactive, cancellation_date, next_payment FROM subscriptions WHERE id = ' . (int) $subscriptionId, true);
}

diamon_test('every cancellation rule names a known catalogue provider and an https page', function () {
    foreach (get_provider_cancellation_rules() as $id => $rule) {
        assert_true(get_provider_catalog_entry($id) !== null, 'Rule for unknown provider ' . $id);
        assert_true(strpos($rule['cancel_url'], 'https://') === 0, 'Cancel URL must be https for ' . $id);
        foreach ($rule['url_patterns'] ?? [] as $pattern) {
            assert_true(@preg_match('#' . str_replace('#', '\#', $pattern) . '#i', '') !== false, 'Invalid URL pattern for ' . $id);
        }
    }
});

diamon_test('the cancel page comes from the catalogue, then the subscription URL, then nothing', function () {
    $catalogue = cancellation_target(['name' => 'Spotify', 'provider_catalog_id' => 'spotify', 'url' => 'https://example.com']);
    assert_same('https://www.spotify.com/account/subscription/', $catalogue['cancel_url'], 'Catalogue page wins');
    assert_true($catalogue['detectable'], 'Catalogue providers with hosts are detectable');

    $own = cancellation_target(['name' => 'My gym', 'provider_catalog_id' => null, 'url' => 'gym.example.com/account']);
    assert_same('https://gym.example.com/account', $own['cancel_url'], 'Subscription URL without scheme gets https');
    assert_same(false, $own['detectable'], 'A URL outside the catalogue is not detectable');

    $unsafe = cancellation_target(['name' => 'My gym', 'provider_catalog_id' => null, 'url' => 'javascript:alert(1)']);
    assert_same(null, $unsafe['cancel_url'], 'Non-http URLs are never opened');

    $none = cancellation_target(['name' => 'My gym', 'provider_catalog_id' => null, 'url' => '']);
    assert_same(null, $none['cancel_url'], 'No URL at all');
});

diamon_test('confirming marks the subscription cancelled until the next payment', function () {
    $db = diamon_test_open_database();
    diamon_test_create_user($db, 1, 'ana');
    $subscriptionId = cancellation_test_subscription($db, 1);

    $started = cancellation_start($db, 1, $subscriptionId);
    assert_same('pending', $started['attempt']['status'], 'New attempt is pending');
    assert_same((int) $started['attempt']['id'], (int) cancellation_pending_for_user($db, 1)['id'], 'It is the open attempt');

    $attempt = cancellation_resolve($db, 1, $started['attempt']['id'], CANCELLATION_CONFIRMED, 'user');
    assert_same('confirmed', $attempt['status'], 'Attempt confirmed');

    $row = cancellation_test_row($db, $subscriptionId);
    assert_same(1, (int) $row['inactive'], 'Subscription inactive');
    assert_same($row['next_payment'], $row['cancellation_date'], 'Access until the next payment');
    assert_same(null, cancellation_pending_for_user($db, 1), 'Nothing left to ask');
});

diamon_test('"not cancelled" leaves the subscription untouched', function () {
    $db = diamon_test_open_database();
    diamon_test_create_user($db, 1, 'ana');
    $subscriptionId = cancellation_test_subscription($db, 1);

    $started = cancellation_start($db, 1, $subscriptionId);
    $attempt = cancellation_resolve($db, 1, $started['attempt']['id'], CANCELLATION_DECLINED, 'user');

    assert_same('declined', $attempt['status'], 'Attempt declined');
    $row = cancellation_test_row($db, $subscriptionId);
    assert_same(0, (int) $row['inactive'], 'Still active');
    assert_same(null, $row['cancellation_date'], 'No date set');
});

diamon_test('the extension detection and a late answer do not apply twice', function () {
    $db = diamon_test_open_database();
    diamon_test_create_user($db, 1, 'ana');
    $subscriptionId = cancellation_test_subscription($db, 1, ['provider_catalog_id' => 'spotify']);

    $started = cancellation_start($db, 1, $subscriptionId);
    $watch = cancellation_watch_list($db, 1);
    assert_same(1, count($watch), 'The extension is told to watch it');
    assert_same(['spotify.com'], $watch[0]['hosts'], 'Spotify hosts');

    cancellation_resolve($db, 1, $started['attempt']['id'], CANCELLATION_DETECTED, 'extension');
    $late = cancellation_resolve($db, 1, $started['attempt']['id'], CANCELLATION_DECLINED, 'user');

    assert_same('detected', $late['status'], 'A late "not cancelled" does not undo the detection');
    assert_same(1, (int) cancellation_test_row($db, $subscriptionId)['inactive'], 'Still cancelled');
    assert_same([], cancellation_watch_list($db, 1), 'Nothing left to watch');
});

diamon_test('another user cannot see or resolve the attempt', function () {
    $db = diamon_test_open_database();
    diamon_test_create_user($db, 1, 'ana');
    diamon_test_create_user($db, 2, 'bob');
    $subscriptionId = cancellation_test_subscription($db, 1);

    assert_same(null, cancellation_start($db, 2, $subscriptionId), 'Bob cannot start it');
    $started = cancellation_start($db, 1, $subscriptionId);
    assert_same(null, cancellation_resolve($db, 2, $started['attempt']['id'], CANCELLATION_CONFIRMED, 'user'), 'Bob cannot resolve it');
    assert_same(null, cancellation_attempt($db, 2, $started['attempt']['id']), 'Bob cannot read it');
    assert_same(0, (int) cancellation_test_row($db, $subscriptionId)['inactive'], 'Still active');
});

diamon_test('starting a new attempt supersedes the open one, and deleted subscriptions stop asking', function () {
    $db = diamon_test_open_database();
    diamon_test_create_user($db, 1, 'ana');
    $first = cancellation_test_subscription($db, 1, ['name' => 'Netflix']);
    $second = cancellation_test_subscription($db, 1, ['name' => 'Spotify']);

    $old = cancellation_start($db, 1, $first);
    $new = cancellation_start($db, 1, $second);
    assert_same('expired', cancellation_attempt($db, 1, $old['attempt']['id'])['status'], 'Old attempt superseded');
    assert_same((int) $new['attempt']['id'], (int) cancellation_pending_for_user($db, 1)['id'], 'Only the new one is open');

    $db->exec('DELETE FROM subscriptions WHERE id = ' . (int) $second);
    assert_same(null, cancellation_pending_for_user($db, 1), 'A deleted subscription is not asked about');
    assert_same('expired', cancellation_attempt($db, 1, $new['attempt']['id'])['status'], 'Its attempt is closed');
});

diamon_test('resuming puts back the cancellation date the subscription had', function () {
    $db = diamon_test_open_database();
    diamon_test_create_user($db, 1, 'ana');
    $reminder = date('Y-m-d', strtotime('+40 days'));
    $subscriptionId = cancellation_test_subscription($db, 1, ['cancellation_date' => $reminder]);

    $started = cancellation_start($db, 1, $subscriptionId);
    cancellation_resolve($db, 1, $started['attempt']['id'], CANCELLATION_CONFIRMED, 'user');
    assert_true(cancellation_test_row($db, $subscriptionId)['cancellation_date'] !== $reminder, 'Date replaced while cancelled');

    $db->exec('UPDATE subscriptions SET inactive = 0 WHERE id = ' . (int) $subscriptionId);
    cancellation_restore_on_resume($db, 1, $subscriptionId);
    assert_same($reminder, cancellation_test_row($db, $subscriptionId)['cancellation_date'], 'Original reminder restored');
});

diamon_test('one-time purchases are cancelled without an access date', function () {
    $db = diamon_test_open_database();
    diamon_test_create_user($db, 1, 'ana');
    $subscriptionId = cancellation_test_subscription($db, 1, ['cycle' => 5]);

    $started = cancellation_start($db, 1, $subscriptionId);
    cancellation_resolve($db, 1, $started['attempt']['id'], CANCELLATION_CONFIRMED, 'user');

    $row = cancellation_test_row($db, $subscriptionId);
    assert_same(1, (int) $row['inactive'], 'Inactive');
    assert_same(null, $row['cancellation_date'], 'No date for a one-time purchase');
});
