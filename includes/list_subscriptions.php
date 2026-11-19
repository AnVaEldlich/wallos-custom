<?php

require_once 'i18n/getlang.php';
require_once __DIR__ . '/currency_rates.php';
require_once __DIR__ . '/subscription_logo.php';
require_once __DIR__ . '/subscription_usage_alerts.php';

function getBillingCycle($cycle, $frequency, $i18n)
{
    switch ($cycle) {
        case 1:
            return $frequency == 1 ? translate('Daily', $i18n) : $frequency . " " . translate('days', $i18n);
        case 2:
            return $frequency == 1 ? translate('Weekly', $i18n) : $frequency . " " . translate('weeks', $i18n);
        case 3:
            return $frequency == 1 ? translate('Monthly', $i18n) : $frequency . " " . translate('months', $i18n);
        case 4:
            return $frequency == 1 ? translate('Yearly', $i18n) : $frequency . " " . translate('years', $i18n);
        case 5:
            return translate('One-time', $i18n);
    }
}

function getSubscriptionProgress($cycle, $frequency, $next_payment)
{
    if ($cycle === 5) {
        return 0;
    }

    $nextPaymentDate = new DateTime($next_payment);
    $currentDate = new DateTime((new DateTime('now'))->format('Y-m-d'));

    $paymentCycleDays = 30; // Default to monthly
    if ($cycle === 1) {
        $paymentCycleDays = 1 * $frequency;
    } else if ($cycle === 2) {
        $paymentCycleDays = 7 * $frequency;
    } else if ($cycle === 3) {
        $paymentCycleDays = 30 * $frequency;
    } else if ($cycle === 4) {
        $paymentCycleDays = 365 * $frequency;
    }

    if ($paymentCycleDays <= 0) {
        return 0;
    }

    // next_payment can be many cycles away from today (a stale value, or
    // several missed renewal runs), so we can't always assume it's within a
    // single cycle of "now". Walk back however many whole cycles are needed
    // so the window we measure progress against is the one that actually
    // contains today.
    $daysUntilNextPayment = $currentDate->diff($nextPaymentDate)->days;
    $cyclesBack = $currentDate <= $nextPaymentDate
        ? max(1, (int) ceil($daysUntilNextPayment / $paymentCycleDays))
        : 1;

    $lastPaymentDate = clone $nextPaymentDate;
    $lastPaymentDate->modify('-' . ($cyclesBack * $paymentCycleDays) . ' days');

    $daysSinceLastPayment = $lastPaymentDate->diff($currentDate)->days;
    $subscriptionProgress = ($daysSinceLastPayment / $paymentCycleDays) * 100;

    return floor($subscriptionProgress);
}

function getPricePerMonth($cycle, $frequency, $price)
{
    switch ($cycle) {
        case 1:
            $numberOfPaymentsPerMonth = (30 / $frequency);
            return $price * $numberOfPaymentsPerMonth;
        case 2:
            $numberOfPaymentsPerMonth = (4.35 / $frequency);
            return $price * $numberOfPaymentsPerMonth;
        case 3:
            $numberOfPaymentsPerMonth = (1 / $frequency);
            return $price * $numberOfPaymentsPerMonth;
        case 4:
            $numberOfMonths = (12 * $frequency);
            return $price / $numberOfMonths;
        case 5:
            return 0;
    }
}


function getPriceConverted($price, $currency, $database)
{
    return diamon_convert_price($price, $currency, $database);
}

/**
 * The price and currency code a card shows.
 *
 * A display currency chosen in Settings converts every card into that
 * currency and leaves the amount stored on the subscription alone. With no
 * display currency, the older "convert to my main currency" switch still
 * applies.
 */
function applyDisplayedSubscriptionPrice(&$printRow, $rawPrice, $currencyId, $mainCurrencyId, $settings, $currencies, $database)
{
    $price = floatval($rawPrice);
    $code = $currencies[$currencyId]['code'] ?? '';
    $displayCurrencyId = (int) ($settings['display_currency_id'] ?? 0);

    if ($displayCurrencyId > 0 && isset($currencies[$displayCurrencyId]) && $displayCurrencyId !== (int) $currencyId) {
        $price = diamon_convert_between($price, (int) $currencyId, $displayCurrencyId, $database);
        $code = $currencies[$displayCurrencyId]['code'];
    } elseif (isset($settings['convertCurrency']) && $settings['convertCurrency'] === 'true' && (int) $currencyId !== (int) $mainCurrencyId) {
        $price = getPriceConverted($price, $currencyId, $database);
        $code = $currencies[$mainCurrencyId]['code'] ?? $code;
    }

    $printRow['price'] = $price;
    $printRow['currency_code'] = $code;
}

/**
 * The value a subscription is ranked by when the list is sorted by price: its
 * price in the main currency, so AED 71.00 does not outrank GBP 55.00 just
 * because 71 > 55 (#861). Follows the monthly-price display setting, so the
 * order matches the figures that are shown.
 */
function getSortPrice($rawPrice, $currencyId, $mainCurrencyId, $cycle, $frequency, $settings, $database)
{
    $price = floatval($rawPrice);
    if ($currencyId != $mainCurrencyId) {
        $price = floatval(getPriceConverted($price, $currencyId, $database));
    }
    if (isset($settings['showMonthlyPrice']) && $settings['showMonthlyPrice'] === 'true') {
        $price = getPricePerMonth($cycle, $frequency, $price);
    }
    return $price;
}

/**
 * Orders the printable list by sort_price, highest first. usort() is stable,
 * so ties keep the order the database gave them.
 */
function sortSubscriptionsByPrice(&$print, $disabledToBottom)
{
    usort($print, function ($a, $b) use ($disabledToBottom) {
        if ($disabledToBottom) {
            $byInactive = ($a['inactive'] ?? 0) <=> ($b['inactive'] ?? 0);
            if ($byInactive !== 0) {
                return $byInactive;
            }
        }
        return ($b['sort_price'] ?? $b['price'] ?? 0) <=> ($a['sort_price'] ?? $a['price'] ?? 0);
    });
}

function formatPrice($price, $currencyCode, $currencies)
{
    $formattedPrice = CurrencyFormatter::format($price, $currencyCode);
    if (strstr($formattedPrice, $currencyCode)) {
        $symbol = $currencyCode;
        
        foreach ($currencies as $currency) {

            if ($currency['code'] === $currencyCode) {
                if ($currency['symbol'] != "") {
                    $symbol = $currency['symbol'];
                }
                break;
            }
        }
        $formattedPrice = str_replace($currencyCode, $symbol, $formattedPrice);
    }

    return $formattedPrice;
}

function formatDate($date, $lang = 'en')
{
    $currentYear = date('Y');
    $dateYear = date('Y', strtotime($date));

    // Determine the date format based on whether the year matches the current year
    $dateFormat = ($currentYear == $dateYear) ? 'MMM d' : 'MMM yyyy';

    // Try to create an IntlDateFormatter; if it fails, fallback to 'en'
    try {
        $formatter = new IntlDateFormatter(
            $lang,
            IntlDateFormatter::SHORT,
            IntlDateFormatter::NONE,
            null,
            null,
            $dateFormat
        );

        if (!$formatter) {
            throw new Exception('Failed to create IntlDateFormatter with language: ' . $lang);
        }
    } catch (Throwable $e) {
        $lang = 'en'; // Fallback to English on error
        $formatter = new IntlDateFormatter(
            $lang,
            IntlDateFormatter::SHORT,
            IntlDateFormatter::NONE,
            null,
            null,
            $dateFormat
        );
    }

    // Format the date
    $formattedDate = $formatter->format(new DateTime($date));

    return $formattedDate;
}

function printSubscriptions($subscriptions, $sort, $categories, $members, $i18n, $colorTheme, $imagePath, $disabledToBottom, $mobileNavigation, $showSubscriptionProgress, $currencies, $lang, $groupCancelled = true)
{
    if ($groupCancelled) {
        $active = [];
        $cancelled = [];
        foreach ($subscriptions as $subscription) {
            if (!empty($subscription['inactive'])) {
                $cancelled[] = $subscription;
            } else {
                $active[] = $subscription;
            }
        }
        printSubscriptions($active, $sort, $categories, $members, $i18n, $colorTheme, $imagePath, $disabledToBottom, $mobileNavigation, $showSubscriptionProgress, $currencies, $lang, false);
        if ($cancelled !== []) {
            ?>
            <div class="subscription-list-title" id="cancelled-subscriptions">
                <?= translate('cancelled_subscriptions', $i18n) ?>
            </div>
            <?php
            printSubscriptions($cancelled, $sort, $categories, $members, $i18n, $colorTheme, $imagePath, $disabledToBottom, $mobileNavigation, $showSubscriptionProgress, $currencies, $lang, false);
        }
        return;
    }

    if ($sort === "price") {
        // Ranked by the main-currency value (sort_price), not the raw number (#861).
        sortSubscriptionsByPrice($subscriptions, $disabledToBottom === 'true');
    }

    // One-time purchases always go to the bottom regardless of sort order
    usort($subscriptions, fn($a, $b) => ($a['one_time'] ? 1 : 0) - ($b['one_time'] ? 1 : 0));

    $currentCategory = 0;
    $currentPayerUserId = 0;
    $currentPaymentMethodId = 0;
    $oneTimeSectionShown = false;
    foreach ($subscriptions as $subscription) {
        if ($subscription['one_time'] && !$oneTimeSectionShown) {
            ?>
            <div class="subscription-list-title">
                <?= translate('lifetime_purchases', $i18n) ?>
            </div>
            <?php
            $oneTimeSectionShown = true;
        }
        if ($sort == "category_id" && $subscription['category_id'] != $currentCategory) {
            ?>
            <div class="subscription-list-title">
                <?php
                if ($subscription['category_id'] == 1) {
                    echo translate('no_category', $i18n);
                } else {
                    echo $categories[$subscription['category_id'] ?? ""]['name'] ?? "";
                }
                ?>
            </div>
            <?php
            $currentCategory = $subscription['category_id'];
        }
        if ($sort == "payer_user_id" && $subscription['payer_user_id'] != $currentPayerUserId) {
            ?>
            <div class="subscription-list-title">
                <?= $members[$subscription['payer_user_id'] ?? ""]['name'] ?? "" ?>
            </div>
            <?php
            $currentPayerUserId = $subscription['payer_user_id'];
        }
        if ($sort == "payment_method_id" && $subscription['payment_method_id'] != $currentPaymentMethodId) {
            ?>
            <div class="subscription-list-title">
                <?= $subscription['payment_method_name'] ?>
            </div>
            <?php
            $currentPaymentMethodId = $subscription['payment_method_id'];
        }
        ?>
        <div class="subscription-container">
            <?php
            if ($mobileNavigation === 'true') {
                ?>
                <div class="mobile-actions" data-id="<?= $subscription['id'] ?>">
                    <button class="mobile-action-clone"></button>
                    <button class="mobile-action-clone" onClick="cloneSubscription(event, <?= $subscription['id'] ?>)">
                        <?php include $imagePath . "images/siteicons/svg/mobile-menu/clone.php"; ?>
                        Clone
                    </button>
                    <button class="mobile-action-delete" onClick="deleteSubscription(event, <?= $subscription['id'] ?>)">
                        <?php include $imagePath . "images/siteicons/svg/mobile-menu/delete.php"; ?>
                        Delete
                    </button>
                    <?php
                    if ($subscription['auto_renew'] != 1 && !$subscription['one_time']) {
                        ?>
                        <button class="mobile-action-renew" onClick="renewSubscription(event, <?= $subscription['id'] ?>)">
                            <?php include $imagePath . "images/siteicons/svg/mobile-menu/renew.php"; ?>
                            Renew
                        </button>
                        <?php
                    }
                    ?>
                    <button class="mobile-action-edit" onClick="openEditSubscription(event, <?= $subscription['id'] ?>)">
                        <?php include $imagePath . "images/siteicons/svg/mobile-menu/edit.php"; ?>
                        Edit
                    </button>
                </div>
                <?php
            }

            $subscriptionExtraClasses = "";
            if ($subscription['inactive']) {
                $subscriptionExtraClasses .= " inactive";
            }
            if ($subscription['auto_renew'] != 1) {
                $subscriptionExtraClasses .= " manual";
            }
            if (subscription_is_unused_and_ending($subscription)) {
                $subscriptionExtraClasses .= " unused-ending";
            }

            $hasLogo = subscription_row_has_display_logo($subscription);

            ?>

            <label class="bulk-select">
                <input type="checkbox" class="bulk-select-checkbox" value="<?= $subscription['id'] ?>"
                    onChange="updateBulkSelection()">
            </label>
            <div class="subscription<?= $subscriptionExtraClasses ?>"
                onClick="showSubscriptionDetails(event, <?= $subscription['id'] ?>)" data-id="<?= $subscription['id'] ?>"
                data-name="<?= $subscription['name'] ?>"
                <?= str_contains($subscriptionExtraClasses, 'unused-ending') ? 'title="' . htmlspecialchars(translate('usage_unused_ending', $i18n), ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                <div class="subscription-main">
                    <span class="logo <?= !$hasLogo ? 'hideOnMobile' : '' ?>">
                        <?= render_subscription_card_logo($subscription) ?>
                    </span>
                    <span class="name <?= $hasLogo ? 'hideOnMobile' : '' ?>"><?= $subscription['name'] ?></span>
                    <span class="cycle"
                        title="<?= $subscription['one_time'] ? $subscription['billing_cycle'] : ($subscription['auto_renew'] ? translate("automatically_renews", $i18n) : translate("manual_renewal", $i18n)) ?>">
                        <?php
                        if (!$subscription['one_time']) {
                            if ($subscription['auto_renew']) {
                                include $imagePath . "images/siteicons/svg/automatic.php";
                            } else {
                                include $imagePath . "images/siteicons/svg/manual.php";
                            }
                        }
                        ?>
                        <?= $subscription['billing_cycle'] ?>
                    </span>
                    <span class="next"><?= formatDate($subscription['next_payment'], $lang) ?>
                        <?php
                        // A cancelled subscription the user can still use until
                        // the end of the paid period (set by assisted cancellation).
                        $accessUntil = (string) ($subscription['cancellation_date'] ?? '');
                        if (!empty($subscription['inactive']) && $accessUntil !== '' && $accessUntil >= date('Y-m-d')) {
                            ?>
                            <span class="access-until"><?= htmlspecialchars(str_replace('{date}', formatDate($accessUntil, $lang), translate('cancel_access_until', $i18n)), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php
                        }
                        ?>
                    </span>
                    <span class="price">
                        <span class="value">
                            <?= formatPrice($subscription['price'], $subscription['currency_code'], $currencies) ?>
                            <?php
                            if (isset($subscription['original_price']) && $subscription['original_price'] != $subscription['price']) {
                                ?>
                                <span
                                    class="original_price">(<?= formatPrice($subscription['original_price'], $subscription['original_currency_code'], $currencies) ?>)</span>
                                <?php
                            }
                            ?>
                        </span>
                        <span class="usage-time"
                            data-usage-id="<?= (int) $subscription['id'] ?>"
                            data-usage-seconds="<?= (int) ($subscription['usage_seconds'] ?? 0) ?>"
                            data-usage-live="<?= !empty($subscription['usage_live']) ? '1' : '0' ?>"
                            data-usage-as-of="<?= (int) ($subscription['usage_as_of'] ?? time()) ?>"
                            data-usage-empty="<?= htmlspecialchars(translate('usage_none', $i18n), ENT_QUOTES, 'UTF-8') ?>"><?= !empty($subscription['usage_label']) ? htmlspecialchars($subscription['usage_label']) : translate('usage_none', $i18n) ?></span>
                        <?php if (($subscription['value_band'] ?? '') === 'review') { ?>
                        <span class="value-review"><?= translate('value_review_before_renewal', $i18n) ?></span>
                        <?php } ?>
                    </span>
                    <span class="payment_method">
                        <?php if (!empty($subscription['payment_method_icon'])) { ?>
                        <img src="<?= $subscription['payment_method_icon'] ?>"
                            title="<?= translate('payment_method', $i18n) ?>: <?= $subscription['payment_method_name'] ?>" />
                        <?php } ?>
                    </span>
                    <?php
                    $desktopMenuButtonClass = ""; {
                    }
                    if ($mobileNavigation === "true") {
                        $desktopMenuButtonClass = "mobileNavigationHideOnMobile";
                    }
                    ?>
                    <button type="button" class="actions-expand <?= $desktopMenuButtonClass ?>"
                        onClick="expandActions(event, <?= $subscription['id'] ?>)">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <ul class="actions">
                        <li class="edit" title="<?= translate('edit_subscription', $i18n) ?>"
                            onClick="openEditSubscription(event, <?= $subscription['id'] ?>)">
                            <i class="fa-solid fa-pen-to-square"></i>
                            <?= translate('edit_subscription', $i18n) ?>
                        </li>
                        <li class="delete" title="<?= translate('delete', $i18n) ?>"
                            onClick="deleteSubscription(event, <?= $subscription['id'] ?>)">
                            <i class="fa-solid fa-trash-can"></i>
                            <?= translate('delete', $i18n) ?>
                        </li>
                        <?php if (!empty($subscription['inactive'])) { ?>
                        <li class="resume" title="<?= translate('resume_subscription', $i18n) ?>"
                            onClick="resumeSubscription(event, <?= $subscription['id'] ?>)">
                            <i class="fa-solid fa-rotate-left"></i>
                            <?= translate('resume_subscription', $i18n) ?>
                        </li>
                        <?php } else { ?>
                        <li class="cancel-subscription" title="<?= translate('cancel_subscription', $i18n) ?>"
                            onClick="cancelSubscription(event, <?= $subscription['id'] ?>)">
                            <i class="fa-solid fa-ban"></i>
                            <?= translate('cancel_subscription', $i18n) ?>
                        </li>
                        <?php } ?>
                        <li class="clone" title="<?= translate('clone', $i18n) ?>"
                            onClick="cloneSubscription(event, <?= $subscription['id'] ?>)">
                            <i class="fa-solid fa-copy"></i>
                            <?= translate('clone', $i18n) ?>
                        </li>
                        <?php
                        if ($subscription['auto_renew'] != 1 && !$subscription['one_time']) {
                            ?>
                            <li class="renew" title="<?= translate('renew', $i18n) ?>"
                                onClick="renewSubscription(event, <?= $subscription['id'] ?>)">
                                <i class="fa-solid fa-rotate-right"></i>
                                <?= translate('renew', $i18n) ?>
                            </li>
                            <?php
                        }
                        ?>
                    </ul>
                </div>
                <div class="subscription-back" inert>
                    <div class="subscription-back-header">
                        <span class="subscription-back-title"><?= $subscription['name'] ?></span>
                        <button type="button" class="subscription-back-close"
                            onClick="event.stopPropagation(); unflipCard(<?= $subscription['id'] ?>)"
                            title="<?= translate('cancel', $i18n) ?>">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <button type="button" class="back-action"
                        onClick="unflipCard(<?= $subscription['id'] ?>); openEditSubscription(event, <?= $subscription['id'] ?>)">
                        <i class="fa-solid fa-pen-to-square"></i>
                        <?= translate('edit', $i18n) ?>
                    </button>
                    <button type="button" class="back-action"
                        onClick="unflipCard(<?= $subscription['id'] ?>); cloneSubscription(event, <?= $subscription['id'] ?>)">
                        <i class="fa-solid fa-copy"></i>
                        <?= translate('clone', $i18n) ?>
                    </button>
                    <?php
                    if ($subscription['auto_renew'] != 1 && !$subscription['one_time']) {
                        ?>
                        <button type="button" class="back-action"
                            onClick="unflipCard(<?= $subscription['id'] ?>); renewSubscription(event, <?= $subscription['id'] ?>)">
                            <i class="fa-solid fa-rotate-right"></i>
                            <?= translate('renew', $i18n) ?>
                        </button>
                        <?php
                    }
                    ?>
                    <button type="button" class="back-action delete"
                        onClick="unflipCard(<?= $subscription['id'] ?>); deleteSubscription(event, <?= $subscription['id'] ?>)">
                        <i class="fa-solid fa-trash-can"></i>
                        <?= translate('delete', $i18n) ?>
                    </button>
                    <?php if (!empty($subscription['inactive'])) { ?>
                    <button type="button" class="back-action"
                        onClick="unflipCard(<?= $subscription['id'] ?>); resumeSubscription(event, <?= $subscription['id'] ?>)">
                        <i class="fa-solid fa-rotate-left"></i>
                        <?= translate('resume_subscription', $i18n) ?>
                    </button>
                    <?php } else { ?>
                    <button type="button" class="back-action"
                        onClick="unflipCard(<?= $subscription['id'] ?>); cancelSubscription(event, <?= $subscription['id'] ?>)">
                        <i class="fa-solid fa-ban"></i>
                        <?= translate('cancel_subscription', $i18n) ?>
                    </button>
                    <?php } ?>
                </div>
            </div>
            <?php
            $hasUsage = !empty($subscription['usage_live']) || (int) ($subscription['usage_seconds'] ?? 0) > 0;
            if ($showSubscriptionProgress === 'true' && !$subscription['inactive'] && $hasUsage) {
                $progress = $subscription['progress'] > 100 ? 100 : $subscription['progress'];
                ?>
                <div class="subscription-progress-container">
                    <span class="subscription-progress" style="width: <?= $progress ?>%;"></span>
                </div>
                <?php
            }
            ?>
        </div>
        <?php
    }
}

$query = "SELECT main_currency FROM user WHERE id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
if ($row !== false) {
    $mainCurrencyId = $row['main_currency'];
} else {
    $mainCurrencyId = $currencies[1]['id'];
}

?>