<?php
/*
  Dashboard upcoming/overdue amounts must use the user's preferred currency
  (main currency, or the Settings display currency when chosen).
*/

require_once DIAMON_ROOT . '/includes/upcoming_payments.php';

/**
 * @param SQLite3 $db
 * @param int     $userId
 * @return array
 */
function dashboard_display_currency_fixture($db, $userId = 1)
{
    diamon_test_create_user($db, $userId, 'alice');

    $currencies = [];
    $result = $db->query('SELECT * FROM currencies WHERE user_id = ' . (int) $userId);
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $currencies[$row['id']] = $row;
    }

    return $currencies;
}

diamon_test('dashboard prices convert into the user main currency', function () {
    $db = diamon_test_open_database();
    $currencies = dashboard_display_currency_fixture($db);

    $eur = diamon_test_currency_id(1, 0);
    $usd = diamon_test_currency_id(1, 1);

    $resolved = resolve_dashboard_display_price(
        11.0,
        $usd,
        $eur,
        ['display_currency_id' => 0],
        $currencies,
        $db,
        1
    );

    assert_same($eur, $resolved['currency_id'], 'target is the main currency');
    assert_same('EUR', $resolved['currency_code'], 'currency code follows the main currency');
    assert_same(10.0, $resolved['price'], 'USD converts into EUR through the stored rate');

    $db->close();
});

diamon_test('dashboard prices respect an explicit display currency setting', function () {
    $db = diamon_test_open_database();
    $currencies = dashboard_display_currency_fixture($db);

    $eur = diamon_test_currency_id(1, 0);
    $usd = diamon_test_currency_id(1, 1);

    $resolved = resolve_dashboard_display_price(
        10.0,
        $eur,
        $eur,
        ['display_currency_id' => $usd],
        $currencies,
        $db,
        1
    );

    assert_same($usd, $resolved['currency_id'], 'target is the display currency');
    assert_same('USD', $resolved['currency_code'], 'currency code follows the display currency');
    assert_same(11.0, $resolved['price'], 'EUR converts into the chosen display currency');

    $db->close();
});

diamon_test('dashboard totals convert from the main currency into the display currency', function () {
    $db = diamon_test_open_database();
    $currencies = dashboard_display_currency_fixture($db);

    $eur = diamon_test_currency_id(1, 0);
    $usd = diamon_test_currency_id(1, 1);

    $monthly = convert_dashboard_total(48.0, $eur, ['display_currency_id' => $usd], $currencies, $db, 1);
    $yearly = convert_dashboard_total(48.0 * 12, $eur, ['display_currency_id' => $usd], $currencies, $db, 1);

    assert_same('USD', $monthly['currency_code'], 'monthly total uses the display currency');
    assert_same(52.8, round($monthly['price'], 2), '48 EUR becomes 52.80 USD at the stored rate');
    assert_same(633.6, round($yearly['price'], 2), 'the yearly total uses the same conversion');

    $db->close();
});

diamon_test('dashboard prices stay put when already in the preferred currency', function () {
    $db = diamon_test_open_database();
    $currencies = dashboard_display_currency_fixture($db);

    $eur = diamon_test_currency_id(1, 0);

    $resolved = resolve_dashboard_display_price(
        7.5,
        $eur,
        $eur,
        ['display_currency_id' => 0],
        $currencies,
        $db,
        1
    );

    assert_same($eur, $resolved['currency_id'], 'currency is unchanged');
    assert_same(7.5, $resolved['price'], 'amount is unchanged');

    $db->close();
});
