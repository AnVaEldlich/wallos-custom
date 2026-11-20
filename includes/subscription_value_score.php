<?php

require_once __DIR__ . '/usage_sessions.php';

const SUBSCRIPTION_VALUE_LOW_USE_SECONDS = 1800;
const SUBSCRIPTION_VALUE_SOON_DAYS = 14;

function subscription_monthly_price($cycle, $frequency, $price)
{
    $frequency = max(1, (int) $frequency);
    $price = (float) $price;

    return match ((int) $cycle) {
        1 => $price * (30 / $frequency),
        2 => $price * (4.35 / $frequency),
        3 => $price / $frequency,
        4 => $price / (12 * $frequency),
        default => 0.0,
    };
}

/**
 * @return array{band: string, reasons: array<int, string>}
 */
function subscription_value_score($monthlyPrice, $usageSeconds, $confidence, $daysUntilRenewal, $isLive)
{
    $usageSeconds = max(0, (int) $usageSeconds);
    $paid = (float) $monthlyPrice > 0;
    $lowUse = $usageSeconds < SUBSCRIPTION_VALUE_LOW_USE_SECONDS;
    $days = $daysUntilRenewal === null ? null : (int) $daysUntilRenewal;
    $soon = $days !== null && $days >= 0 && $days <= SUBSCRIPTION_VALUE_SOON_DAYS;
    $confidence = in_array($confidence, ['measured', 'estimated', 'manual'], true) ? $confidence : 'none';

    if ($isLive) {
        return ['band' => 'keep', 'reasons' => ['live']];
    }
    if (!$lowUse && $confidence === 'measured') {
        return ['band' => 'keep', 'reasons' => ['measured_use']];
    }
    if (!$lowUse) {
        return ['band' => 'keep', 'reasons' => ['recent_use']];
    }
    if ($paid && $soon) {
        return ['band' => 'review', 'reasons' => ['low_use_soon']];
    }

    return ['band' => 'neutral', 'reasons' => ['no_signal']];
}

/**
 * @param array<int, array<string, mixed>> $sessions
 * @return array{seconds: int, confidence: string}
 */
function subscription_window_usage(array $sessions, $from, $to)
{
    $from = (int) $from;
    $to = (int) $to;
    $clipped = [];
    $rank = ['none' => 0, 'estimated' => 1, 'manual' => 2, 'measured' => 3];
    $confidence = 'none';

    foreach ($sessions as $session) {
        $start = max((int) ($session['started_at'] ?? 0), $from);
        $end = min((int) ($session['ended_at'] ?? 0), $to);
        if ($end <= $start) {
            continue;
        }
        $clipped[] = ['started_at' => $start, 'ended_at' => $end];
        $sessionConfidence = (string) ($session['confidence'] ?? 'none');
        if (($rank[$sessionConfidence] ?? 0) > $rank[$confidence]) {
            $confidence = $sessionConfidence;
        }
    }

    return [
        'seconds' => (int) usage_interval_seconds(usage_union_intervals(usage_normalize_intervals($clipped))),
        'confidence' => $confidence,
    ];
}

/**
 * @return array{start: int, end: int, days: int}|null
 */
function subscription_billing_window($nextPaymentIso, $cycle, $frequency, $today = null)
{
    $cycle = (int) $cycle;
    if ($cycle < 1 || $cycle > 4 || $nextPaymentIso === '') {
        return null;
    }

    $next = DateTime::createFromFormat('Y-m-d', (string) $nextPaymentIso);
    if (!$next) {
        return null;
    }
    $next->setTime(0, 0, 0);
    $days = [1 => 1, 2 => 7, 3 => 30, 4 => 365][$cycle] * max(1, (int) $frequency);
    $start = clone $next;
    $start->modify('-' . $days . ' days');
    $today = $today instanceof DateTime ? clone $today : new DateTime('today');
    $today->setTime(0, 0, 0);
    $daysUntil = $next < $today ? 0 : (int) $today->diff($next)->days;

    $end = clone $next;
    $end->modify('+1 day');

    return [
        'start' => $start->getTimestamp(),
        'end' => $end->getTimestamp(),
        'days' => $daysUntil,
    ];
}

/**
 * @param array<int|string, array<string, mixed>> $print
 */
function attach_subscription_value_bands($db, $userId, array &$print)
{
    $grouped = [];
    $stmt = $db->prepare('SELECT subscription_id, started_at, ended_at, confidence, source, is_active
        FROM subscription_usage_sessions WHERE user_id = :userId');
    if ($stmt) {
        $stmt->bindValue(':userId', (int) $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            $grouped[(int) $row['subscription_id']][] = $row;
        }
    }

    $today = new DateTime('today');
    foreach ($print as $key => $row) {
        $window = subscription_billing_window(
            (string) ($row['next_payment_iso'] ?? ''),
            $row['cycle'] ?? 0,
            $row['frequency'] ?? 1,
            $today
        );
        $sessions = $grouped[(int) ($row['id'] ?? 0)] ?? [];
        $usage = $window
            ? subscription_window_usage($sessions, $window['start'], $window['end'])
            : ['seconds' => 0, 'confidence' => 'none'];
        $isLive = array_key_exists('usage_live', $row)
            ? !empty($row['usage_live'])
            : subscription_sessions_are_live($sessions, time());
        $monthlyPrice = array_key_exists('score_monthly_price', $row)
            ? $row['score_monthly_price']
            : subscription_monthly_price($row['cycle'] ?? 0, $row['frequency'] ?? 1, $row['price'] ?? 0);
        $score = subscription_value_score(
            $monthlyPrice,
            $usage['seconds'],
            $usage['confidence'],
            $window['days'] ?? null,
            $isLive
        );
        if (!empty($row['inactive']) || !empty($row['one_time'])) {
            $score = ['band' => 'neutral', 'reasons' => ['inactive_or_one_time']];
        }
        $print[$key]['value_band'] = $score['band'];
        $print[$key]['value_reasons'] = $score['reasons'];
        $print[$key]['period_usage_seconds'] = $usage['seconds'];
        $print[$key]['usage_confidence'] = $usage['confidence'];
        $print[$key]['days_until_renewal'] = $window['days'] ?? null;
    }
}

function subscription_sessions_are_live(array $sessions, $now)
{
    $now = (int) $now;
    foreach ($sessions as $session) {
        $end = (int) ($session['ended_at'] ?? 0);
        if (($session['source'] ?? '') === 'extension'
            && !empty($session['is_active'])
            && $end >= $now - USAGE_SESSION_LIVE_TTL_SECONDS
            && $end <= $now + 5) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<int, array<string, mixed>> $subscriptions
 * @return array<int, array<string, mixed>>
 */
function subscription_value_facts_for_subscriptions($db, $userId, array $subscriptions)
{
    $print = [];
    foreach ($subscriptions as $row) {
        $id = (int) ($row['id'] ?? 0);
        $cycle = (int) ($row['cycle'] ?? 0);
        $frequency = (int) ($row['frequency'] ?? 1);
        $next = strtotime((string) ($row['next_payment'] ?? ''));
        $print[$id] = [
            'id' => $id,
            'cycle' => $cycle,
            'frequency' => $frequency,
            'one_time' => $cycle === 5,
            'inactive' => !empty($row['inactive']),
            'next_payment_iso' => $next ? date('Y-m-d', $next) : '',
            'score_monthly_price' => subscription_monthly_price($cycle, $frequency, $row['price'] ?? 0),
        ];
    }
    attach_subscription_value_bands($db, $userId, $print);

    return $print;
}
