<?php

require_once WALLOS_ROOT . '/includes/usage_sessions.php';
require_once WALLOS_ROOT . '/includes/usage_hosts.php';

wallos_test('usage duration keeps sub-minute time in seconds', function () {
    assert_same('1 s', usage_format_duration(1), 'one second stays one second');
    assert_same('59 s', usage_format_duration(59), '59 seconds does not round up');
    assert_same('1 min 0 s', usage_format_duration(60), 'one minute keeps its seconds');
    assert_same('1 min 23 s', usage_format_duration(83), 'minutes keep their remaining seconds');
    assert_same('1 h 1 min 1 s', usage_format_duration(3661), 'hours include minute and second remainders');
});

wallos_test('a fresh active extension session advances until its heartbeat expires', function () {
    $session = [[
        'started_at' => 1000,
        'ended_at' => 1060,
        'source' => 'extension',
        'is_active' => 1,
        'confidence' => 'measured',
        'device' => 'browser',
    ]];

    $liveSummary = usage_summarize_sessions($session, 1083);
    assert_same(83, $liveSummary['total'], 'a live session includes time since its latest heartbeat');
    assert_same(true, $liveSummary['live'], 'a recent active heartbeat stays live');
    assert_same(1083, $liveSummary['as_of'], 'the summary records its calculation time');

    $staleSummary = usage_summarize_sessions($session, 1151);
    assert_same(60, $staleSummary['total'], 'a stale heartbeat is not extended');
    assert_same(false, $staleSummary['live'], 'a stale heartbeat is no longer live');
});

wallos_test('the active extension heartbeat is saved and can be stopped', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');
    $stmt = $db->prepare("INSERT INTO subscriptions (name, price, currency_id, next_payment, cycle, frequency, user_id, inactive)
        VALUES ('Netflix', 12, 1, '2026-12-01', 3, 1, 1, 0)");
    $stmt->execute();
    $subscriptionId = $db->lastInsertRowID();
    $startedAt = 1700000000;
    $heartbeatAt = $startedAt + 60;

    $session = [
        'id' => 'browser-session',
        'subscription_id' => $subscriptionId,
        'started_at' => $startedAt,
        'ended_at' => $heartbeatAt,
        'source' => 'extension',
        'device' => 'browser',
        'is_active' => true,
    ];
    assert_same(null, usage_save_session($db, 1, $session), 'an active heartbeat saves');
    $live = usage_summaries_for_user($db, 1, $heartbeatAt + 23)[$subscriptionId];
    assert_same(83, $live['total'], 'the active card total includes elapsed seconds');
    assert_same(true, $live['live'], 'the card knows the session is active');

    $session['ended_at'] = $heartbeatAt + 30;
    $session['is_active'] = false;
    assert_same(null, usage_save_session($db, 1, $session), 'the stop heartbeat saves');
    $stopped = usage_summaries_for_user($db, 1, $heartbeatAt + 40)[$subscriptionId];
    assert_same(90, $stopped['total'], 'the stopped session keeps its final duration');
    assert_same(false, $stopped['live'], 'the stopped session no longer advances');
    $db->close();
});

wallos_test('overlapping sessions from two sources count once', function () {
    $summary = usage_summarize_sessions([
        ['started_at' => 1000, 'ended_at' => 4600, 'confidence' => 'measured', 'device' => 'laptop'],
        ['started_at' => 2000, 'ended_at' => 5000, 'confidence' => 'estimated', 'device' => 'tv'],
    ]);

    assert_same(4000, $summary['total'], 'the union is one hour, not two');
    assert_same(3600, $summary['measured'], 'measured keeps its own length');
    assert_same(400, $summary['estimated'], 'only the estimated tail is extra');
    assert_same(0, $summary['manual'], 'no manual time');
    assert_same(3600 + 400, $summary['measured'] + $summary['estimated'] + $summary['manual'], 'the breakdown adds up to the total');
});

wallos_test('a measured stretch hides the estimated and manual time underneath it', function () {
    $summary = usage_summarize_sessions([
        ['started_at' => 0, 'ended_at' => 100, 'confidence' => 'measured', 'device' => ''],
        ['started_at' => 0, 'ended_at' => 100, 'confidence' => 'estimated', 'device' => ''],
        ['started_at' => 0, 'ended_at' => 100, 'confidence' => 'manual', 'device' => ''],
        ['started_at' => 100, 'ended_at' => 130, 'confidence' => 'manual', 'device' => 'phone'],
    ]);

    assert_same(130, $summary['total'], 'the manual tail extends the union');
    assert_same(100, $summary['measured'], 'measured is the covered hour');
    assert_same(0, $summary['estimated'], 'estimated was fully covered');
    assert_same(30, $summary['manual'], 'only the uncovered manual tail remains');
    assert_same('phone', $summary['device'], 'the latest end names the device');
});

wallos_test('a zero-length import updates last use and adds no time', function () {
    $summary = usage_summarize_sessions([
        ['started_at' => 50, 'ended_at' => 50, 'confidence' => 'estimated', 'device' => 'import'],
    ]);

    assert_same(0, $summary['total'], 'a point has no duration');
    assert_same(50, $summary['last_used'], 'the point is still a last use');
});

wallos_test('a host matches the service and not a lookalike name', function () {
    assert_true(usage_host_matches('www.netflix.com', 'netflix.com'), 'a subdomain counts');
    assert_true(usage_host_matches('netflix.com', 'netflix.com'), 'the host itself counts');
    assert_true(!usage_host_matches('notnetflix.com', 'netflix.com'), 'a suffix without a dot does not count');
    assert_true(!usage_host_matches('netflix.com.evil.test', 'netflix.com'), 'a longer name does not count');
});

wallos_test('two subscriptions on the same host are skipped unless one comes from the catalogue', function () {
    $custom = ['id' => 1, 'catalog' => false, 'hosts' => ['netflix.com'], 'dns_hosts' => ['netflix.com'], 'packages' => []];
    $catalog = ['id' => 2, 'catalog' => true, 'hosts' => ['netflix.com'], 'dns_hosts' => ['netflix.com'], 'packages' => []];
    $other = ['id' => 3, 'catalog' => false, 'hosts' => ['netflix.com'], 'dns_hosts' => ['netflix.com'], 'packages' => []];

    assert_same(2, usage_match_target([$custom, $catalog], 'www.netflix.com', 'hosts')['id'], 'the catalogue entry wins');
    assert_same(null, usage_match_target([$custom, $other], 'www.netflix.com', 'hosts'), 'two custom entries are ambiguous');
});

wallos_test('dns queries within fifteen minutes become one estimated session', function () {
    require_once WALLOS_ROOT . '/includes/usage_dns.php';

    $targets = [[
        'id' => 7,
        'catalog' => true,
        'hosts' => ['netflix.com'],
        'dns_hosts' => ['netflix.com', 'nflxvideo.net'],
        'packages' => [],
    ]];
    $sessions = usage_dns_group_queries([
        ['time' => 1000, 'domain' => 'www.netflix.com', 'client' => 'tv'],
        ['time' => 1200, 'domain' => 'x.nflxvideo.net', 'client' => 'tv'],
        ['time' => 1200 + 901, 'domain' => 'netflix.com', 'client' => 'tv'],
        ['time' => 1000, 'domain' => 'example.com', 'client' => 'tv'],
    ], $targets, 900);

    assert_same(2, count($sessions), 'a gap longer than the margin starts a new session, and an unknown domain is dropped');
    assert_same(7, $sessions[0]['subscription_id'], 'the query was matched to the subscription');
    assert_same(1000, $sessions[0]['started_at'], 'the session starts at the first query');
    assert_same(1200 + 900, $sessions[0]['ended_at'], 'the session ends at the last query plus the margin');
    assert_same('dns', $sessions[0]['source'], 'dns sessions name their source');
    assert_same('tv', $sessions[0]['device'], 'the client name is the device');
});
