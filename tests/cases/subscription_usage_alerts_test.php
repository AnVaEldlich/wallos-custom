<?php

require_once DIAMON_ROOT . '/includes/subscription_usage_alerts.php';

diamon_test('unused ending alerts include unused renewals within a week', function () {
    $db = diamon_test_open_database();
    diamon_test_create_user($db, 1, 'alice');

    $currencyId = diamon_test_currency_id(1, 0);
    $inThreeDays = date('Y-m-d', strtotime('+3 days'));
    $inTwentyDays = date('Y-m-d', strtotime('+20 days'));

    $stmt = $db->prepare(
        'INSERT INTO subscriptions (user_id, name, price, currency_id, next_payment, cycle, frequency, inactive, auto_renew)
         VALUES (1, :name, 9.99, :currencyId, :nextPayment, 3, 1, 0, 1)'
    );

    $stmt->bindValue(':name', 'Unused Soon', SQLITE3_TEXT);
    $stmt->bindValue(':currencyId', $currencyId, SQLITE3_INTEGER);
    $stmt->bindValue(':nextPayment', $inThreeDays, SQLITE3_TEXT);
    $stmt->execute();
    $unusedId = (int) $db->lastInsertRowID();

    $stmt->bindValue(':name', 'Far Away', SQLITE3_TEXT);
    $stmt->bindValue(':currencyId', $currencyId, SQLITE3_INTEGER);
    $stmt->bindValue(':nextPayment', $inTwentyDays, SQLITE3_TEXT);
    $stmt->execute();

    $alerts = get_unused_ending_subscription_alerts($db, 1);
    assert_same(1, count($alerts), 'only the near unused renewal is alerted');
    assert_same($unusedId, $alerts[0]['id'], 'the unused near renewal is returned');
    assert_same('Unused Soon', $alerts[0]['name'], 'the alert keeps the subscription name');
    assert_true($alerts[0]['days_left'] <= 7, 'the alert is within a week');

    $db->close();
});

diamon_test('recent usage suppresses the unused ending alert', function () {
    $db = diamon_test_open_database();
    diamon_test_create_user($db, 1, 'alice');

    $currencyId = diamon_test_currency_id(1, 0);
    $inTwoDays = date('Y-m-d', strtotime('+2 days'));

    $stmt = $db->prepare(
        'INSERT INTO subscriptions (user_id, name, price, currency_id, next_payment, cycle, frequency, inactive, auto_renew)
         VALUES (1, :name, 9.99, :currencyId, :nextPayment, 3, 1, 0, 1)'
    );
    $stmt->bindValue(':name', 'Recently Used', SQLITE3_TEXT);
    $stmt->bindValue(':currencyId', $currencyId, SQLITE3_INTEGER);
    $stmt->bindValue(':nextPayment', $inTwoDays, SQLITE3_TEXT);
    $stmt->execute();
    $subscriptionId = (int) $db->lastInsertRowID();

    $now = time();
    assert_same(null, usage_save_session($db, 1, [
        'id' => 'manual-recent',
        'subscription_id' => $subscriptionId,
        'started_at' => $now - 120,
        'ended_at' => $now - 60,
        'source' => 'manual',
        'device' => 'import',
        'confidence' => 'manual',
        'is_active' => false,
    ]), 'recent use is saved');

    $alerts = get_unused_ending_subscription_alerts($db, 1);
    assert_same(0, count($alerts), 'recent use removes the unused-ending alert');

    $db->close();
});
