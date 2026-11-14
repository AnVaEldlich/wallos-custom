<?php

require_once __DIR__ . '/usage_hosts.php';
require_once __DIR__ . '/usage_sessions.php';

const USAGE_DNS_GAP_SECONDS = 900;

/**
 * Turns DNS query rows into estimated sessions. The domain is used only to
 * match a subscription and is not part of the returned session.
 *
 * @param array<int, array{time: int, domain: string, client: string}> $queries
 * @param array<int, array{id: int, catalog: bool, hosts: string[], dns_hosts: string[], packages: string[]}> $targets
 * @return array<int, array{id: string, subscription_id: int, started_at: int, ended_at: int, source: string, device: string}>
 */
function usage_dns_group_queries(array $queries, array $targets, $gapSeconds = USAGE_DNS_GAP_SECONDS)
{
    $buckets = [];
    foreach ($queries as $query) {
        $domain = strtolower((string) ($query['domain'] ?? ''));
        $target = usage_match_target($targets, $domain, 'dns_hosts');
        if ($target === null) {
            continue;
        }
        $client = substr(trim((string) ($query['client'] ?? '')), 0, 80);
        $key = $target['id'] . "\0" . $client;
        $buckets[$key][] = (int) $query['time'];
    }

    $sessions = [];
    foreach ($buckets as $key => $times) {
        sort($times);
        [$subscriptionId, $client] = explode("\0", $key, 2);
        $runStart = null;
        $runLast = null;
        $close = function () use (&$sessions, &$runStart, &$runLast, $subscriptionId, $client, $gapSeconds) {
            if ($runStart === null) {
                return;
            }
            $startedAt = $runStart;
            $sessions[] = [
                'id' => 'dns-' . substr(sha1($subscriptionId . '|' . $client . '|' . $startedAt), 0, 32),
                'subscription_id' => (int) $subscriptionId,
                'started_at' => $startedAt,
                'ended_at' => $runLast + $gapSeconds,
                'source' => 'dns',
                'device' => $client,
            ];
            $runStart = null;
            $runLast = null;
        };
        foreach ($times as $time) {
            if ($runStart === null) {
                $runStart = $time;
                $runLast = $time;
                continue;
            }
            if ($time - $runLast > $gapSeconds) {
                $close();
                $runStart = $time;
            }
            $runLast = $time;
        }
        $close();
    }
    return $sessions;
}

/**
 * @return array<int, array{time: int, domain: string, client: string}>
 */
function usage_dns_fetch_queries($provider, $baseUrl, $token)
{
    $baseUrl = rtrim((string) $baseUrl, '/');
    if ($baseUrl === '' || !preg_match('#^https?://#i', $baseUrl)) {
        return [];
    }
    if ($provider === 'adguard') {
        return usage_dns_fetch_adguard($baseUrl, $token);
    }
    return usage_dns_fetch_pihole($baseUrl, $token);
}

function usage_dns_http_get($url, array $headers = [])
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    return is_string($body) ? $body : '';
}

function usage_dns_fetch_pihole($baseUrl, $token)
{
    $url = $baseUrl . '/admin/api.php?getAllQueries&auth=' . rawurlencode((string) $token);
    $decoded = json_decode(usage_dns_http_get($url), true);
    $rows = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
    $queries = [];
    foreach ($rows as $row) {
        if (!is_array($row) || count($row) < 4) {
            continue;
        }
        $queries[] = [
            'time' => (int) $row[0],
            'domain' => (string) $row[2],
            'client' => (string) $row[3],
        ];
    }
    return $queries;
}

function usage_dns_fetch_adguard($baseUrl, $token)
{
    $credentials = (string) $token;
    $header = 'Authorization: Basic ' . base64_encode($credentials);
    $url = $baseUrl . '/control/querylog?limit=2000';
    $decoded = json_decode(usage_dns_http_get($url, [$header]), true);
    $rows = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
    $queries = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $time = strtotime((string) ($row['time'] ?? ''));
        $domain = (string) ($row['question']['name'] ?? $row['question'] ?? '');
        if ($time === false || $domain === '') {
            continue;
        }
        $queries[] = [
            'time' => $time,
            'domain' => $domain,
            'client' => (string) ($row['client'] ?? ''),
        ];
    }
    return $queries;
}

function usage_dns_sync_user($db, $userId, $provider, $baseUrl, $token)
{
    $queries = usage_dns_fetch_queries($provider, $baseUrl, $token);
    $stmt = $db->prepare('SELECT id, name, provider_catalog_id, url FROM subscriptions WHERE user_id = :userId AND inactive = 0');
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $subscriptions = [];
    while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
        $subscriptions[] = $row;
    }
    $sessions = usage_dns_group_queries($queries, usage_watch_targets($subscriptions));
    $saved = 0;
    foreach ($sessions as $session) {
        if (usage_save_session($db, $userId, $session) === null) {
            $saved++;
        }
    }
    return $saved;
}
