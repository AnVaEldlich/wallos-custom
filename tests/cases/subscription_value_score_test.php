<?php

require_once DIAMON_ROOT . '/includes/subscription_value_score.php';

diamon_test('measured use above 30 minutes is keep', function () {
    $score = subscription_value_score(12.99, 3600, 'measured', 3, false);

    assert_same('keep', $score['band'], 'measured use keeps the subscription');
    assert_same(['measured_use'], $score['reasons'], 'the reason is measured use');
});

diamon_test('paid unused subscription renewing in 3 days is review', function () {
    $score = subscription_value_score(15.99, 0, 'none', 3, false);

    assert_same('review', $score['band'], 'low use before renewal is review');
    assert_same(['low_use_soon'], $score['reasons'], 'the reason is low use soon');

    $free = subscription_value_score(0, 0, 'none', 3, false);
    assert_same('neutral', $free['band'], 'a free subscription is not flagged for review');
});

diamon_test('a live session stays keep even when recorded use is low', function () {
    $score = subscription_value_score(19.99, 0, 'none', 1, true);

    assert_same('keep', $score['band'], 'a live session is keep');
    assert_same(['live'], $score['reasons'], 'the reason is the live session');
});

diamon_test('missing usage and a distant renewal stay neutral', function () {
    $score = subscription_value_score(9.99, 0, 'none', 40, false);

    assert_same('neutral', $score['band'], 'no signal stays neutral');
    assert_same(['no_signal'], $score['reasons'], 'the reason is no signal');
});
