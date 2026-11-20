<?php

/*
  Several sources can report the same stretch of time. The total is the union
  of the intervals. The breakdown splits that union so the three figures add
  up to it: measured time, estimated time not already measured, and manual
  time covered by neither.
*/

const USAGE_SOURCES = ['extension', 'dns', 'android', 'ios_shortcut', 'import', 'manual'];
const USAGE_CONFIDENCE = ['measured', 'estimated', 'manual'];
const USAGE_SESSION_MAX_SECONDS = 18 * 60 * 60;
const USAGE_SESSION_LIVE_TTL_SECONDS = 90;

/**
 * @param array<int, array{started_at: int, ended_at: int}> $intervals
 * @return array<int, array{0: int, 1: int}>
 */
function usage_normalize_intervals(array $intervals)
{
    $normalized = [];
    foreach ($intervals as $interval) {
        $start = (int) $interval['started_at'];
        $end = (int) $interval['ended_at'];
        if ($end < $start) {
            continue;
        }
        $normalized[] = [$start, $end];
    }
    usort($normalized, function ($a, $b) {
        return $a[0] <=> $b[0];
    });
    return $normalized;
}

/**
 * @param array<int, array{0: int, 1: int}> $intervals
 * @return array<int, array{0: int, 1: int}>
 */
function usage_union_intervals(array $intervals)
{
    $merged = [];
    foreach ($intervals as $interval) {
        if ($merged === []) {
            $merged[] = $interval;
            continue;
        }
        $last = count($merged) - 1;
        if ($interval[0] <= $merged[$last][1]) {
            $merged[$last][1] = max($merged[$last][1], $interval[1]);
            continue;
        }
        $merged[] = $interval;
    }
    return $merged;
}

/**
 * @param array<int, array{0: int, 1: int}> $intervals
 */
function usage_interval_seconds(array $intervals)
{
    $seconds = 0;
    foreach ($intervals as $interval) {
        $seconds += $interval[1] - $interval[0];
    }
    return $seconds;
}

/**
 * Pieces of $target that are not covered by $blockers.
 *
 * @param array<int, array{0: int, 1: int}> $target
 * @param array<int, array{0: int, 1: int}> $blockers
 * @return array<int, array{0: int, 1: int}>
 */
function usage_subtract_intervals(array $target, array $blockers)
{
    $remaining = [];
    $blockers = usage_union_intervals($blockers);
    foreach ($target as $piece) {
        $cursor = $piece[0];
        $end = $piece[1];
        foreach ($blockers as $blocker) {
            if ($blocker[1] <= $cursor) {
                continue;
            }
            if ($blocker[0] >= $end) {
                break;
            }
            if ($blocker[0] > $cursor) {
                $remaining[] = [$cursor, min($blocker[0], $end)];
            }
            $cursor = max($cursor, $blocker[1]);
            if ($cursor >= $end) {
                break;
            }
        }
        if ($cursor < $end) {
            $remaining[] = [$cursor, $end];
        }
    }
    return $remaining;
}

/**
 * @param array<int, array{started_at: int, ended_at: int, confidence: string, device?: string, source?: string, is_active?: int}> $sessions
 * @return array{total: int, measured: int, estimated: int, manual: int, last_used: int, device: string, live: bool, as_of: int}
 */
function usage_summarize_sessions(array $sessions, $now = null)
{
    $now = $now === null ? time() : (int) $now;
    $all = [];
    $measured = [];
    $estimated = [];
    $manual = [];
    $lastUsed = 0;
    $device = '';
    $live = false;

    foreach ($sessions as $session) {
        $start = (int) $session['started_at'];
        $end = (int) $session['ended_at'];
        if ($end < $start) {
            continue;
        }
        $isLive = ($session['source'] ?? '') === 'extension'
            && !empty($session['is_active'])
            && $end >= $now - USAGE_SESSION_LIVE_TTL_SECONDS
            && $end <= $now + 5;
        $effectiveEnd = $isLive ? $now : $end;
        if ($effectiveEnd < $start) {
            continue;
        }
        $all[] = ['started_at' => $start, 'ended_at' => $effectiveEnd];
        $confidence = $session['confidence'] ?? '';
        if ($confidence === 'measured') {
            $measured[] = ['started_at' => $start, 'ended_at' => $effectiveEnd];
        } elseif ($confidence === 'estimated') {
            $estimated[] = ['started_at' => $start, 'ended_at' => $effectiveEnd];
        } elseif ($confidence === 'manual') {
            $manual[] = ['started_at' => $start, 'ended_at' => $effectiveEnd];
        }
        if ($effectiveEnd >= $lastUsed) {
            $lastUsed = $effectiveEnd;
            $device = (string) ($session['device'] ?? '');
        }
        $live = $live || $isLive;
    }

    $measuredUnion = usage_union_intervals(usage_normalize_intervals($measured));
    $estimatedUnion = usage_union_intervals(usage_normalize_intervals($estimated));
    $manualOutsideMeasured = usage_subtract_intervals(
        usage_union_intervals(usage_normalize_intervals($manual)),
        $measuredUnion
    );
    $estimatedExclusive = usage_subtract_intervals($estimatedUnion, $measuredUnion);
    $manualExclusive = usage_subtract_intervals($manualOutsideMeasured, $estimatedUnion);

    return [
        'total' => usage_interval_seconds(usage_union_intervals(usage_normalize_intervals($all))),
        'measured' => usage_interval_seconds($measuredUnion),
        'estimated' => usage_interval_seconds($estimatedExclusive),
        'manual' => usage_interval_seconds($manualExclusive),
        'last_used' => $lastUsed,
        'device' => $device,
        'live' => $live,
        'as_of' => $now,
    ];
}

function usage_format_duration($seconds)
{
    $seconds = (int) $seconds;
    if ($seconds <= 0) {
        return '';
    }
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remainingSeconds = $seconds % 60;
    $parts = [];
    if ($hours > 0) {
        $parts[] = $hours . ' h';
    }
    if ($minutes > 0 || $hours > 0) {
        $parts[] = $minutes . ' min';
    }
    $parts[] = $remainingSeconds . ' s';
    return implode(' ', $parts);
}

/**
 * @return array<int, array{total: int, measured: int, estimated: int, manual: int, last_used: int, device: string, live: bool, as_of: int}>
 */
function usage_summaries_for_user($db, $userId, $now = null)
{
    $now = $now === null ? time() : (int) $now;
    $grouped = [];
    $stmt = $db->prepare('SELECT subscription_id, started_at, ended_at, confidence, device, source, is_active
        FROM subscription_usage_sessions WHERE user_id = :userId');
    if (!$stmt) {
        return [];
    }
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
        $grouped[(int) $row['subscription_id']][] = $row;
    }
    $summaries = [];
    foreach ($grouped as $subscriptionId => $sessions) {
        $summaries[$subscriptionId] = usage_summarize_sessions($sessions, $now);
    }
    return $summaries;
}

function usage_summary_for_subscription($db, $userId, $subscriptionId, $now = null)
{
    $summaries = usage_summaries_for_user($db, $userId, $now);
    return $summaries[(int) $subscriptionId] ?? usage_summarize_sessions([], $now);
}

function usage_empty_summary($now = null)
{
    return usage_summarize_sessions([], $now);
}

function usage_confidence_for_source($source)
{
    if (in_array($source, ['extension', 'android', 'ios_shortcut'], true)) {
        return 'measured';
    }
    if ($source === 'manual') {
        return 'manual';
    }
    if (in_array($source, ['dns', 'import'], true)) {
        return 'estimated';
    }
    return null;
}

/**
 * Inserts or replaces one session owned by this user.
 * A repeated id updates the end time instead of adding a second row.
 *
 * @param array{id: string, subscription_id: int, started_at: int, ended_at: int, source: string, device?: string, is_active?: bool} $session
 * @return string|null error message, or null when saved
 */
function usage_save_session($db, $userId, array $session)
{
    $id = substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($session['id'] ?? '')), 0, 128);
    $subscriptionId = (int) ($session['subscription_id'] ?? 0);
    $startedAt = (int) ($session['started_at'] ?? 0);
    $endedAt = (int) ($session['ended_at'] ?? 0);
    $source = (string) ($session['source'] ?? '');
    $device = substr(trim((string) ($session['device'] ?? '')), 0, 80);
    $confidence = usage_confidence_for_source($source);
    $isActive = $source === 'extension' && !empty($session['is_active']) ? 1 : 0;

    if ($id === '' || $subscriptionId <= 0 || $startedAt <= 0 || $confidence === null) {
        return 'invalid session';
    }
    if ($endedAt < $startedAt) {
        return 'invalid session';
    }
    if ($endedAt - $startedAt > USAGE_SESSION_MAX_SECONDS) {
        $endedAt = $startedAt + USAGE_SESSION_MAX_SECONDS;
    }

    $owned = $db->prepare('SELECT id FROM subscriptions WHERE id = :id AND user_id = :userId');
    $owned->bindValue(':id', $subscriptionId, SQLITE3_INTEGER);
    $owned->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $ownedResult = $owned->execute();
    if (!$ownedResult || !$ownedResult->fetchArray(SQLITE3_ASSOC)) {
        return 'subscription not found';
    }

    $existing = $db->prepare('SELECT user_id, subscription_id FROM subscription_usage_sessions WHERE id = :id');
    $existing->bindValue(':id', $id, SQLITE3_TEXT);
    $existingResult = $existing->execute();
    $existingRow = $existingResult ? $existingResult->fetchArray(SQLITE3_ASSOC) : false;
    if ($existingRow && ((int) $existingRow['user_id'] !== (int) $userId || (int) $existingRow['subscription_id'] !== $subscriptionId)) {
        return 'invalid session';
    }

    if ($existingRow) {
        $stmt = $db->prepare('UPDATE subscription_usage_sessions
            SET started_at = :startedAt, ended_at = :endedAt, source = :source, device = :device,
                confidence = :confidence, is_active = :isActive
            WHERE id = :id AND user_id = :userId');
    } else {
        $stmt = $db->prepare('INSERT INTO subscription_usage_sessions
            (id, user_id, subscription_id, started_at, ended_at, source, device, confidence, is_active)
            VALUES (:id, :userId, :subscriptionId, :startedAt, :endedAt, :source, :device, :confidence, :isActive)');
        $stmt->bindValue(':subscriptionId', $subscriptionId, SQLITE3_INTEGER);
    }
    $stmt->bindValue(':id', $id, SQLITE3_TEXT);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':startedAt', $startedAt, SQLITE3_INTEGER);
    $stmt->bindValue(':endedAt', $endedAt, SQLITE3_INTEGER);
    $stmt->bindValue(':source', $source, SQLITE3_TEXT);
    $stmt->bindValue(':device', $device, SQLITE3_TEXT);
    $stmt->bindValue(':confidence', $confidence, SQLITE3_TEXT);
    $stmt->bindValue(':isActive', $isActive, SQLITE3_INTEGER);
    if (!$stmt->execute()) {
        return 'invalid session';
    }
    return null;
}

function usage_delete_for_subscription_ids($db, $userId, array $subscriptionIds)
{
    $subscriptionIds = array_values(array_unique(array_filter(array_map('intval', $subscriptionIds))));
    if ($subscriptionIds === []) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($subscriptionIds), '?'));
    $stmt = $db->prepare("DELETE FROM subscription_usage_sessions
        WHERE user_id = ? AND subscription_id IN ($placeholders)");
    if (!$stmt) {
        return;
    }
    $index = 1;
    $stmt->bindValue($index++, $userId, SQLITE3_INTEGER);
    foreach ($subscriptionIds as $subscriptionId) {
        $stmt->bindValue($index++, $subscriptionId, SQLITE3_INTEGER);
    }
    $stmt->execute();
}

function usage_delete_for_user($db, $userId)
{
    $stmt = $db->prepare('DELETE FROM subscription_usage_sessions WHERE user_id = :userId');
    if (!$stmt) {
        return;
    }
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $stmt->execute();
}
