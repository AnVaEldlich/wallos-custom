<?php

require_once DIAMON_ROOT . '/includes/ai_subscription_context.php';

diamon_test('AI subscription costs use one target currency and separate recurring totals', function () {
    $currencies = [
        1 => ['id' => 1, 'code' => 'EUR'],
        2 => ['id' => 2, 'code' => 'USD'],
        3 => ['id' => 3, 'code' => 'GBP'],
    ];
    $subscriptions = [
        [
            'id' => 1, 'name' => 'Monthly', 'price' => 10, 'currency_id' => 1,
            'cycle' => 3, 'frequency' => 1, 'inactive' => 0,
        ],
        [
            'id' => 2, 'name' => 'Yearly', 'price' => 22, 'currency_id' => 2,
            'cycle' => 4, 'frequency' => 1, 'inactive' => 0,
        ],
        [
            'id' => 3, 'name' => 'Lifetime', 'price' => 30, 'currency_id' => 1,
            'cycle' => 5, 'frequency' => 1, 'inactive' => 0,
        ],
        [
            'id' => 4, 'name' => 'Archived', 'price' => 25, 'currency_id' => 3,
            'cycle' => 3, 'frequency' => 1, 'inactive' => 1,
        ],
    ];

    $context = ai_subscription_cost_context($subscriptions, $currencies, 2, [1 => 1.0, 2 => 1.1]);

    assert_same(11.0, $context['subscriptions'][1]['price'], 'EUR price is converted to the display currency');
    assert_same(11.0, $context['subscriptions'][1]['monthly_cost'], 'monthly price is provided in the target currency');
    assert_same(22.0, $context['subscriptions'][2]['annualized_cost'], 'annual price remains an annual amount');
    assert_same(33.0, $context['subscriptions'][3]['one_time_cost'], 'one-time purchases are converted but not recurring');
    assert_same(null, $context['subscriptions'][3]['monthly_cost'], 'one-time purchases have no monthly cost');
    assert_same(12.83, $context['totals']['monthly_cost'], 'monthly total sums only active recurring costs');
    assert_same(154.0, $context['totals']['annualized_cost'], 'annual total uses the same active recurring costs');
    assert_same(true, $context['totals']['complete'], 'an unavailable inactive price does not make totals incomplete');
});

diamon_test('AI financial facts never pass through an unconvertible foreign amount', function () {
    $currencies = [
        1 => ['id' => 1, 'code' => 'EUR'],
        2 => ['id' => 2, 'code' => 'USD'],
        3 => ['id' => 3, 'code' => 'GBP'],
    ];
    $subscriptions = [[
        'id' => 1, 'name' => 'Unpriced', 'price' => 12, 'currency_id' => 3,
        'cycle' => 3, 'frequency' => 1, 'inactive' => 0,
    ]];

    $context = ai_subscription_cost_context($subscriptions, $currencies, 2, [1 => 1.0, 2 => 1.1]);

    assert_same(null, $context['subscriptions'][1]['price'], 'unconvertible subscription amount is omitted');
    assert_same(null, $context['subscriptions'][1]['monthly_cost'], 'unconvertible monthly cost is omitted');
    assert_same(null, $context['totals']['monthly_cost'], 'incomplete data is not reported as a complete total');
    assert_same(null, $context['totals']['annualized_cost'], 'incomplete annualized total is omitted');
    assert_same(1, $context['totals']['active_recurring_subscriptions'], 'unconvertible recurring subscriptions remain counted');
    assert_same(1, $context['totals']['unavailable_recurring_count'], 'missing conversion is counted');
});

diamon_test('AI target currency prefers the selected display currency over the main currency', function () {
    $target = dashboard_preferred_currency_id(
        1,
        ['display_currency_id' => 2],
        [1 => ['code' => 'EUR'], 2 => ['code' => 'USD']]
    );

    assert_same(2, $target, 'the display preference is used for model context');
});

diamon_test('AI budget values convert to the same reporting currency as subscriptions', function () {
    $budget = ai_user_budget_context([
        'user' => ['budget' => 50, 'period_budget' => 20, 'budget_period_type' => 'weekly'],
        'main_currency_id' => 1,
        'currency_id' => 2,
        'currency_rates' => [1 => 1.0, 2 => 1.1],
    ]);

    assert_same(55.0, $budget['monthly_budget'], 'monthly budget converts from main to display currency');
    assert_same(22.0, $budget['period_budget'], 'period budget uses the same target currency');
    assert_same('weekly', $budget['period_type'], 'budget period remains available to the assistant');
});

diamon_test('AI financial context loads the selected display currency and account rates', function () {
    $db = diamon_test_open_database();
    $userId = 19;
    diamon_test_create_user($db, $userId, 'currency-context');
    $displayCurrencyId = diamon_test_currency_id($userId, 1);

    $settings = $db->prepare('INSERT INTO settings (user_id, display_currency_id) VALUES (:userId, :currencyId)');
    $settings->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $settings->bindValue(':currencyId', $displayCurrencyId, SQLITE3_INTEGER);
    $settings->execute();

    $context = ai_user_financial_context($db, $userId);

    assert_same($displayCurrencyId, $context['currency_id'], 'display currency overrides the main currency');
    assert_same('USD', $context['currency_code'], 'the model receives the selected currency code');
    assert_same(1.1, $context['currency_rates'][$displayCurrencyId], 'conversion uses the user-scoped stored rate');

    $db->exec('UPDATE settings SET display_currency_id = 0 WHERE user_id = ' . $userId);
    $mainCurrencyContext = ai_user_financial_context($db, $userId);
    assert_same(diamon_test_currency_id($userId, 0), $mainCurrencyContext['currency_id'], 'main currency is used when no display preference is set');

    $db->close();
});

