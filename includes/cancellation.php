<?php
/*
  Assisted cancellation.

  Diamon cannot cancel a subscription at the service itself. When the user
  presses "Cancel", Diamon opens the service's cancel page and records a
  pending attempt here. The attempt ends one of three ways:
    - 'detected':  the browser extension saw the service's confirmation page;
    - 'confirmed': the user answered "Cancelled";
    - 'declined':  the user answered "Not cancelled".
  A pending attempt is superseded ('expired') when the user starts another one,
  or when its subscription no longer exists.

  A cancelled subscription is marked inactive at once (it leaves the totals and
  the reminders) and its cancellation_date is set to the next payment: the
  date the user keeps access until. The previous cancellation_date is kept on
  the attempt so resuming the subscription puts it back.
*/

require_once __DIR__ . '/subscription_logo.php';

const CANCELLATION_PENDING = 'pending';
const CANCELLATION_DETECTED = 'detected';
const CANCELLATION_CONFIRMED = 'confirmed';
const CANCELLATION_DECLINED = 'declined';
const CANCELLATION_EXPIRED = 'expired';

/**
 * A subscription row owned by the user, or null.
 *
 * @return array|null
 */
function cancellation_subscription($db, $userId, $subscriptionId)
{
    $stmt = $db->prepare('SELECT id, name, url, provider_catalog_id, next_payment, cycle, cancellation_date, inactive
        FROM subscriptions WHERE id = :id AND user_id = :userId');
    $stmt->bindValue(':id', (int) $subscriptionId, SQLITE3_INTEGER);
    $stmt->bindValue(':userId', (int) $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;

    return $row ?: null;
}

/**
 * Where to send the user to cancel, and whether the extension can notice the
 * result. Catalogue cancel page first, then the subscription's own URL.
 *
 * @param array $subscription row from cancellation_subscription()
 * @return array{cancel_url: string|null, hosts: string[], url_patterns: string[], detectable: bool}
 */
function cancellation_target($subscription)
{
    $catalogId = resolve_provider_catalog_id_for_subscription(
        (string) ($subscription['name'] ?? ''),
        (string) ($subscription['provider_catalog_id'] ?? '')
    );
    $info = provider_catalog_cancel_info($catalogId);
    if ($info !== null) {
        return $info;
    }

    $url = trim((string) ($subscription['url'] ?? ''));
    if ($url !== '' && strpos($url, ':') === false) {
        // Stored without a scheme, e.g. "example.com/account". Anything with a
        // colon keeps its scheme and must be http(s) to pass the check below.
        $url = 'https://' . $url;
    }
    if ($url === '' || !preg_match('#^https?://#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
        $url = null;
    }

    return ['cancel_url' => $url, 'hosts' => [], 'url_patterns' => [], 'detectable' => false];
}

/**
 * @return array|null
 */
function cancellation_attempt($db, $userId, $attemptId)
{
    $stmt = $db->prepare('SELECT * FROM cancellation_attempts WHERE id = :id AND user_id = :userId');
    $stmt->bindValue(':id', (int) $attemptId, SQLITE3_INTEGER);
    $stmt->bindValue(':userId', (int) $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;

    return $row ?: null;
}

function cancellation_set_status($db, $attemptId, $status, $source = null)
{
    $stmt = $db->prepare('UPDATE cancellation_attempts
        SET status = :status, source = COALESCE(:source, source), resolved_at = :now
        WHERE id = :id');
    $stmt->bindValue(':status', $status, SQLITE3_TEXT);
    $stmt->bindValue(':source', $source, $source === null ? SQLITE3_NULL : SQLITE3_TEXT);
    $stmt->bindValue(':now', time(), SQLITE3_INTEGER);
    $stmt->bindValue(':id', (int) $attemptId, SQLITE3_INTEGER);
    $stmt->execute();
}

/**
 * Starts a cancellation attempt. Any other pending attempt of the user is
 * superseded, so only one blocking question is ever open.
 *
 * @return array{attempt: array, subscription: array, target: array}|null null when the subscription is not the user's
 */
function cancellation_start($db, $userId, $subscriptionId)
{
    $subscription = cancellation_subscription($db, $userId, $subscriptionId);
    if ($subscription === null) {
        return null;
    }

    $stmt = $db->prepare("UPDATE cancellation_attempts SET status = :expired, resolved_at = :now
        WHERE user_id = :userId AND status = :pending");
    $stmt->bindValue(':expired', CANCELLATION_EXPIRED, SQLITE3_TEXT);
    $stmt->bindValue(':now', time(), SQLITE3_INTEGER);
    $stmt->bindValue(':userId', (int) $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':pending', CANCELLATION_PENDING, SQLITE3_TEXT);
    $stmt->execute();

    $stmt = $db->prepare('INSERT INTO cancellation_attempts (user_id, subscription_id, status, created_at)
        VALUES (:userId, :subscriptionId, :pending, :now)');
    $stmt->bindValue(':userId', (int) $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':subscriptionId', (int) $subscription['id'], SQLITE3_INTEGER);
    $stmt->bindValue(':pending', CANCELLATION_PENDING, SQLITE3_TEXT);
    $stmt->bindValue(':now', time(), SQLITE3_INTEGER);
    $stmt->execute();

    return [
        'attempt' => cancellation_attempt($db, $userId, $db->lastInsertRowID()),
        'subscription' => $subscription,
        'target' => cancellation_target($subscription),
    ];
}

/**
 * Resolves a pending attempt. $cancelled true marks the subscription
 * cancelled; false leaves it untouched. Resolving an attempt that is no longer
 * pending changes nothing and reports its current status.
 *
 * @param string $status CANCELLATION_DETECTED, CANCELLATION_CONFIRMED or CANCELLATION_DECLINED
 * @param string $source 'extension' or 'user'
 * @return array|null the attempt after the change, or null when it is not the user's
 */
function cancellation_resolve($db, $userId, $attemptId, $status, $source)
{
    $attempt = cancellation_attempt($db, $userId, $attemptId);
    if ($attempt === null) {
        return null;
    }
    if ($attempt['status'] !== CANCELLATION_PENDING) {
        return $attempt;
    }

    $subscription = cancellation_subscription($db, $userId, $attempt['subscription_id']);
    if ($subscription === null) {
        cancellation_set_status($db, $attempt['id'], CANCELLATION_EXPIRED);
        return cancellation_attempt($db, $userId, $attempt['id']);
    }

    $db->exec('BEGIN');
    if ($status === CANCELLATION_DETECTED || $status === CANCELLATION_CONFIRMED) {
        $stmt = $db->prepare('UPDATE cancellation_attempts SET previous_cancellation_date = :previous WHERE id = :id');
        $previous = $subscription['cancellation_date'] ?? null;
        $stmt->bindValue(':previous', $previous, $previous === null || $previous === '' ? SQLITE3_NULL : SQLITE3_TEXT);
        $stmt->bindValue(':id', (int) $attempt['id'], SQLITE3_INTEGER);
        $stmt->execute();

        // One-time purchases have no renewal to cancel and keep no
        // cancellation date (the subscription form clears it as well).
        $accessUntil = (int) $subscription['cycle'] === 5 ? null : $subscription['next_payment'];
        $stmt = $db->prepare('UPDATE subscriptions SET inactive = 1, cancellation_date = :accessUntil
            WHERE id = :id AND user_id = :userId');
        $stmt->bindValue(':accessUntil', $accessUntil, $accessUntil === null ? SQLITE3_NULL : SQLITE3_TEXT);
        $stmt->bindValue(':id', (int) $subscription['id'], SQLITE3_INTEGER);
        $stmt->bindValue(':userId', (int) $userId, SQLITE3_INTEGER);
        $stmt->execute();
    } else {
        $status = CANCELLATION_DECLINED;
    }
    cancellation_set_status($db, $attempt['id'], $status, $source);
    $db->exec('COMMIT');

    return cancellation_attempt($db, $userId, $attempt['id']);
}

/**
 * The user's open attempt, if any. Attempts whose subscription was deleted or
 * already cancelled some other way are closed instead of being returned, so
 * the blocking question never asks about something that is gone.
 *
 * @return array|null attempt row plus subscription_name
 */
function cancellation_pending_for_user($db, $userId)
{
    $stmt = $db->prepare('SELECT a.*, s.name AS subscription_name, s.inactive AS subscription_inactive, s.id AS existing_subscription_id
        FROM cancellation_attempts a
        LEFT JOIN subscriptions s ON s.id = a.subscription_id AND s.user_id = a.user_id
        WHERE a.user_id = :userId AND a.status = :pending
        ORDER BY a.id DESC');
    $stmt->bindValue(':userId', (int) $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':pending', CANCELLATION_PENDING, SQLITE3_TEXT);
    $result = $stmt->execute();

    $open = null;
    $stale = [];
    while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
        if ($open === null && $row['existing_subscription_id'] !== null && (int) $row['subscription_inactive'] === 0) {
            $open = $row;
        } else {
            $stale[] = (int) $row['id'];
        }
    }
    foreach ($stale as $attemptId) {
        cancellation_set_status($db, $attemptId, CANCELLATION_EXPIRED);
    }

    return $open;
}

/**
 * Every open attempt across the user's subscriptions with what the extension
 * needs to watch for it. Only attempts it can actually detect are listed.
 *
 * @return array<int, array{attempt_id: int, subscription_id: int, name: string, hosts: string[], url_patterns: string[]}>
 */
function cancellation_watch_list($db, $userId)
{
    $open = cancellation_pending_for_user($db, $userId);
    if ($open === null) {
        return [];
    }

    $subscription = cancellation_subscription($db, $userId, $open['subscription_id']);
    $target = cancellation_target($subscription);
    if (!$target['detectable']) {
        return [];
    }

    return [[
        'attempt_id' => (int) $open['id'],
        'subscription_id' => (int) $open['subscription_id'],
        'name' => (string) $subscription['name'],
        'hosts' => $target['hosts'],
        'url_patterns' => $target['url_patterns'],
    ]];
}

/**
 * Called when a subscription is resumed: if Diamon set its cancellation date
 * through an attempt, the date it had before is put back.
 */
function cancellation_restore_on_resume($db, $userId, $subscriptionId)
{
    $stmt = $db->prepare('SELECT id, previous_cancellation_date FROM cancellation_attempts
        WHERE user_id = :userId AND subscription_id = :subscriptionId
          AND status IN (:detected, :confirmed) AND restored = 0
        ORDER BY id DESC LIMIT 1');
    $stmt->bindValue(':userId', (int) $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':subscriptionId', (int) $subscriptionId, SQLITE3_INTEGER);
    $stmt->bindValue(':detected', CANCELLATION_DETECTED, SQLITE3_TEXT);
    $stmt->bindValue(':confirmed', CANCELLATION_CONFIRMED, SQLITE3_TEXT);
    $result = $stmt->execute();
    $attempt = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
    if (!$attempt) {
        return;
    }

    $previous = $attempt['previous_cancellation_date'];
    $stmt = $db->prepare('UPDATE subscriptions SET cancellation_date = :previous WHERE id = :id AND user_id = :userId');
    $stmt->bindValue(':previous', $previous, $previous === null || $previous === '' ? SQLITE3_NULL : SQLITE3_TEXT);
    $stmt->bindValue(':id', (int) $subscriptionId, SQLITE3_INTEGER);
    $stmt->bindValue(':userId', (int) $userId, SQLITE3_INTEGER);
    $stmt->execute();

    $stmt = $db->prepare('UPDATE cancellation_attempts SET restored = 1 WHERE user_id = :userId AND subscription_id = :subscriptionId');
    $stmt->bindValue(':userId', (int) $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':subscriptionId', (int) $subscriptionId, SQLITE3_INTEGER);
    $stmt->execute();
}

/**
 * Shape sent to the web page for an attempt.
 *
 * @return array
 */
function cancellation_attempt_payload($attempt)
{
    return [
        'attempt_id' => (int) $attempt['id'],
        'subscription_id' => (int) $attempt['subscription_id'],
        'status' => (string) $attempt['status'],
        'source' => $attempt['source'],
    ];
}
