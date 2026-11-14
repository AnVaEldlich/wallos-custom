<?php

require_once WALLOS_ROOT . '/includes/currency_amount.php';

wallos_test('currency amounts follow the smallest real unit of each currency', function () {
    assert_true(!currency_amount_is_valid(10, 'COP'), '10 COP does not exist');
    assert_true(!currency_amount_is_valid(49, 'COP'), 'below the 50 peso coin');
    assert_true(currency_amount_is_valid(50, 'COP'), '50 COP is the smallest coin');
    assert_true(currency_amount_is_valid(3000, 'COP'), '3000 COP is a normal price');
    assert_true(!currency_amount_is_valid(10.5, 'COP'), 'COP has no centavos');

    assert_true(currency_amount_is_valid(10, 'USD'), '10 dollars exists');
    assert_true(currency_amount_is_valid(0.01, 'USD'), 'one cent exists');
    assert_true(!currency_amount_is_valid(0.001, 'USD'), 'a tenth of a cent does not exist');

    assert_true(currency_amount_is_valid(10, 'JPY'), '10 yen exists');
    assert_true(!currency_amount_is_valid(9, 'KRW'), 'below the 10 won coin');
    assert_true(!currency_amount_is_valid(50, 'IDR'), 'below the 100 rupiah coin');
    assert_true(!currency_amount_is_valid(4, 'HUF'), 'below the 5 forint coin');
    assert_true(currency_amount_is_valid(0, 'USD'), 'zero stays available for a free subscription');
});
