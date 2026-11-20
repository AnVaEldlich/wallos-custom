<?php

require_once __DIR__ . '/usage_sessions.php';

/**
 * True when a recurring subscription has no recorded use in the current
 * billing period and renews within the next seven days.
 *
 * @param array<string, mixed> $subscription
 * @return bool
 */
function subscription_is_unused_and_ending($subscription)
{
    if (!empty($subscription['inactive']) || !empty($subscription['one_time']) || !empty($subscription['usage_live'])) {
        return false;
    }

    $iso = (string) ($subscription['next_payment_iso'] ?? '');
    $cycle = (int) ($subscription['cycle'] ?? 0);
    if ($iso === '' || $cycle < 1 || $cycle > 4) {
        return false;
    }

    $nextPayment = new DateTime($iso);
    $today = new DateTime((new DateTime('now'))->format('Y-m-d'));
    if ($nextPayment < $today) {
        return false;
    }
    if ((int) $today->diff($nextPayment)->days > 7) {
        return false;
    }

    $frequency = max(1, (int) ($subscription['frequency'] ?? 1));
    $cycleDays = [1 => 1, 2 => 7, 3 => 30, 4 => 365][$cycle] * $frequency;
    $periodStart = clone $nextPayment;
    $periodStart->modify('-' . $cycleDays . ' days');
    $lastUsed = (int) ($subscription['usage_last_used'] ?? 0);

    return $lastUsed < $periodStart->getTimestamp();
}

/**
 * In-app alerts for unused subscriptions about to renew.
 *
 * @param SQLite3 $db
 * @param int     $userId
 * @return array<int, array{id: int, name: string, next_payment: string, days_left: int, auto_renew: int}>
 */
function get_unused_ending_subscription_alerts($db, $userId)
{
    $userId = (int) $userId;
    $stmt = $db->prepare(
        "SELECT id, name, next_payment, cycle, frequency, inactive, auto_renew
         FROM subscriptions
         WHERE user_id = :userId AND inactive = 0 AND cycle != 5
         ORDER BY next_payment ASC"
    );
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $rows = [];
    while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
        $rows[] = $row;
    }

    if ($rows === []) {
        return [];
    }

    $asOf = time();
    $usageSummaries = usage_summaries_for_user($db, $userId, $asOf);
    $today = new DateTime((new DateTime('now'))->format('Y-m-d'));
    $alerts = [];

    foreach ($rows as $row) {
        $usage = $usageSummaries[(int) $row['id']] ?? usage_empty_summary($asOf);
        $candidate = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'next_payment' => (string) $row['next_payment'],
            'next_payment_iso' => (string) $row['next_payment'],
            'cycle' => (int) $row['cycle'],
            'frequency' => (int) $row['frequency'],
            'inactive' => (int) $row['inactive'],
            'auto_renew' => (int) $row['auto_renew'],
            'one_time' => ((int) $row['cycle'] === 5),
            'usage_last_used' => (int) ($usage['last_used'] ?? 0),
            'usage_live' => !empty($usage['live']),
        ];

        if (!subscription_is_unused_and_ending($candidate)) {
            continue;
        }

        $nextPayment = new DateTime($candidate['next_payment']);
        $alerts[] = [
            'id' => $candidate['id'],
            'name' => $candidate['name'],
            'next_payment' => $candidate['next_payment'],
            'days_left' => (int) $today->diff($nextPayment)->days,
            'auto_renew' => $candidate['auto_renew'],
            'type' => 'unused_ending',
        ];
    }

    return $alerts;
}
