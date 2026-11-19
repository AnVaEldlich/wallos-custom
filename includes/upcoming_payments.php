<?php

require_once __DIR__ . '/currency_rates.php';

const UPCOMING_PAYMENTS_LIMITS = [3, 5, 10, 20];

/**
 * Parse a dashboard limit and return null for unsupported values.
 *
 * @param mixed $limit
 * @return int|null
 */
function parse_upcoming_payments_limit($limit)
{
    if (is_string($limit) && ctype_digit($limit)) {
        $limit = (int) $limit;
    }

    return is_int($limit) && in_array($limit, UPCOMING_PAYMENTS_LIMITS, true) ? $limit : null;
}

/**
 * Return a supported dashboard limit, falling back to the current default.
 *
 * @param mixed $limit
 * @return int
 */
function normalize_upcoming_payments_limit($limit)
{
    return parse_upcoming_payments_limit($limit) ?? 3;
}

/**
 * Fetch the upcoming subscriptions shown on the dashboard.
 *
 * @param SQLite3 $db
 * @param int      $userId
 * @param mixed    $limit
 * @return array
 */
function get_upcoming_payments($db, $userId, $limit)
{
    $limit = normalize_upcoming_payments_limit($limit);

    $stmt = $db->prepare("SELECT id, logo, logo_text_color, logo_variant, name, price, currency_id, next_payment, inactive
        FROM subscriptions
        WHERE user_id = :userId
          AND next_payment >= date('now')
          AND inactive = 0
          AND cycle != 5
        ORDER BY next_payment ASC
        LIMIT :limit");
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $subscriptions = [];
    while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
        $subscriptions[] = $row;
    }

    return $subscriptions;
}

/**
 * Currency the dashboard should show: the Settings display currency when set,
 * otherwise the user's main currency.
 *
 * @param int   $mainCurrencyId
 * @param array $settings
 * @param array $currencies
 * @return int
 */
function dashboard_preferred_currency_id($mainCurrencyId, $settings, $currencies)
{
    $mainCurrencyId = (int) $mainCurrencyId;
    $displayCurrencyId = (int) ($settings['display_currency_id'] ?? 0);

    if ($displayCurrencyId > 0 && isset($currencies[$displayCurrencyId])) {
        return $displayCurrencyId;
    }

    return $mainCurrencyId;
}

/**
 * Turn a total that statistics already computed in the main currency into the
 * dashboard currency, using the same saved rates as the other cards.
 *
 * @param float|int|string $amount
 * @param int              $mainCurrencyId
 * @param array            $settings
 * @param array            $currencies
 * @param SQLite3          $db
 * @param int|null         $userId
 * @return array{price: float, currency_id: int, currency_code: string}
 */
function convert_dashboard_total($amount, $mainCurrencyId, $settings, $currencies, $db, $userId = null)
{
    $mainCurrencyId = (int) $mainCurrencyId;
    $targetId = dashboard_preferred_currency_id($mainCurrencyId, $settings, $currencies);

    if (!isset($currencies[$targetId])) {
        $targetId = $mainCurrencyId;
    }

    $price = diamon_convert_between((float) $amount, $mainCurrencyId, $targetId, $db, $userId);

    return [
        'price' => $price,
        'currency_id' => $targetId,
        'currency_code' => $currencies[$targetId]['code'] ?? '',
    ];
}

/**
 * Resolve a dashboard amount into the currency the user prefers to see.
 *
 * Uses the Settings "show subscriptions in" currency when set; otherwise the
 * user's main currency (chosen at registration or in Settings). Converts with
 * the user's saved rates so budget and upcoming payments share one currency.
 *
 * @param float|int|string $price
 * @param int              $currencyId
 * @param int              $mainCurrencyId
 * @param array            $settings
 * @param array            $currencies
 * @param SQLite3          $db
 * @param int|null         $userId
 * @return array{price: float, currency_id: int, currency_code: string}
 */
function resolve_dashboard_display_price($price, $currencyId, $mainCurrencyId, $settings, $currencies, $db, $userId = null)
{
    $price = (float) $price;
    $currencyId = (int) $currencyId;
    $mainCurrencyId = (int) $mainCurrencyId;
    $targetId = dashboard_preferred_currency_id($mainCurrencyId, $settings, $currencies);

    if (!isset($currencies[$targetId])) {
        $targetId = isset($currencies[$currencyId]) ? $currencyId : $mainCurrencyId;
    }

    if ($currencyId !== $targetId && isset($currencies[$currencyId]) && isset($currencies[$targetId])) {
        $price = diamon_convert_between($price, $currencyId, $targetId, $db, $userId);
    }

    return [
        'price' => $price,
        'currency_id' => $targetId,
        'currency_code' => $currencies[$targetId]['code'] ?? ($currencies[$currencyId]['code'] ?? ''),
    ];
}
