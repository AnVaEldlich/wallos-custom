<?php

/**
 * Smallest amount a currency can actually represent.
 * fraction_digits: decimal places the currency uses.
 * minor_unit: step between valid amounts (cents, whole units, etc.).
 * minimum: smallest positive amount. Zero stays allowed for a free subscription.
 */
function currency_amount_rule($currencyCode)
{
    $code = strtoupper((string) $currencyCode);
    $rules = [
        // Centavo withdrawn. There is no $10 coin; the smallest coin is $50.
        'COP' => ['fraction_digits' => 0, 'minor_unit' => 1, 'minimum' => 50],
        // Sen withdrawn. Smallest coin is Rp100.
        'IDR' => ['fraction_digits' => 0, 'minor_unit' => 1, 'minimum' => 100],
        // Jeon withdrawn. Smallest coin is 10 won.
        'KRW' => ['fraction_digits' => 0, 'minor_unit' => 1, 'minimum' => 10],
        // Fillér withdrawn. Smallest coin is 5 forint.
        'HUF' => ['fraction_digits' => 0, 'minor_unit' => 1, 'minimum' => 5],
        'JPY' => ['fraction_digits' => 0, 'minor_unit' => 1, 'minimum' => 1],
        'ISK' => ['fraction_digits' => 0, 'minor_unit' => 1, 'minimum' => 1],
        'CZK' => ['fraction_digits' => 0, 'minor_unit' => 1, 'minimum' => 1],
        'TWD' => ['fraction_digits' => 0, 'minor_unit' => 1, 'minimum' => 1],
    ];

    return $rules[$code] ?? ['fraction_digits' => 2, 'minor_unit' => 0.01, 'minimum' => 0.01];
}

function currency_amount_format_minimum($minimum)
{
    $text = number_format((float) $minimum, 2, '.', '');
    $text = rtrim(rtrim($text, '0'), '.');
    return $text === '' ? '0' : $text;
}

function currency_amount_is_valid($amount, $currencyCode)
{
    if (!is_numeric($amount)) {
        return false;
    }

    $value = (float) $amount;
    if ($value < 0 || !is_finite($value)) {
        return false;
    }
    if ($value == 0.0) {
        return true;
    }

    $rule = currency_amount_rule($currencyCode);
    $scale = 10 ** (int) $rule['fraction_digits'];
    $scaled = (int) round($value * $scale);
    $step = (int) round($rule['minor_unit'] * $scale);
    $minimum = (int) round($rule['minimum'] * $scale);
    if ($step <= 0 || $minimum <= 0) {
        return false;
    }

    return $scaled % $step === 0 && $scaled >= $minimum;
}
