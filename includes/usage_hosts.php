<?php

/*
  Hosts the extension can see, CDN names only the home DNS should match, and
  Android package names. Marketing URLs in the provider catalogue are not
  playback hosts, so they are not used here.
*/

/**
 * @return array<string, array{hosts: string[], dns_hosts: string[], packages: string[]}>
 */
function usage_catalog_signals()
{
    return [
        'spotify' => [
            'hosts' => ['open.spotify.com', 'spotify.com'],
            'dns_hosts' => ['spotify.com', 'scdn.co'],
            'packages' => ['com.spotify.music'],
        ],
        'netflix' => [
            'hosts' => ['netflix.com'],
            'dns_hosts' => ['netflix.com', 'nflxvideo.net'],
            'packages' => ['com.netflix.mediaclient'],
        ],
        'disney-plus' => [
            'hosts' => ['disneyplus.com'],
            'dns_hosts' => ['disneyplus.com', 'bamgrid.com'],
            'packages' => ['com.disney.disneyplus'],
        ],
        'youtube-premium' => [
            'hosts' => ['youtube.com', 'music.youtube.com'],
            'dns_hosts' => ['youtube.com', 'music.youtube.com', 'googlevideo.com'],
            'packages' => ['com.google.android.youtube'],
        ],
        'amazon-prime' => [
            'hosts' => ['primevideo.com'],
            'dns_hosts' => ['primevideo.com', 'aiv-cdn.net'],
            'packages' => ['com.amazon.avod.thirdpartyclient'],
        ],
        'apple-music' => [
            'hosts' => ['music.apple.com'],
            'dns_hosts' => ['music.apple.com'],
            'packages' => ['com.apple.android.music'],
        ],
        'apple-tv-plus' => [
            'hosts' => ['tv.apple.com'],
            'dns_hosts' => ['tv.apple.com'],
            'packages' => ['com.apple.atve.androidtv.appletv'],
        ],
        'max' => [
            'hosts' => ['max.com'],
            'dns_hosts' => ['max.com'],
            'packages' => ['com.wbd.stream'],
        ],
        'microsoft-365' => [
            'hosts' => ['microsoft365.com', 'office.com', 'office365.com'],
            'dns_hosts' => ['office.com', 'office365.com'],
            'packages' => [],
        ],
        'google-one' => [
            'hosts' => ['one.google.com'],
            'dns_hosts' => ['one.google.com'],
            'packages' => ['com.google.android.apps.subscriptions.red'],
        ],
        'dropbox' => [
            'hosts' => ['dropbox.com'],
            'dns_hosts' => ['dropbox.com'],
            'packages' => ['com.dropbox.android'],
        ],
        'playstation-plus' => [
            'hosts' => ['playstation.com'],
            'dns_hosts' => ['playstation.com', 'playstation.net'],
            'packages' => [],
        ],
        'xbox-game-pass' => [
            'hosts' => ['xbox.com'],
            'dns_hosts' => ['xbox.com', 'xboxlive.com'],
            'packages' => ['com.microsoft.xboxone.smartglass'],
        ],
        'adobe-creative-cloud' => [
            'hosts' => ['adobe.com'],
            'dns_hosts' => ['adobe.com'],
            'packages' => ['com.adobe.creativeapps.gather'],
        ],
    ];
}

function usage_host_matches($domain, $host)
{
    $domain = strtolower(rtrim((string) $domain, '.'));
    $host = strtolower((string) $host);
    if ($domain === '' || $host === '') {
        return false;
    }
    return $domain === $host || str_ends_with($domain, '.' . $host);
}

function usage_host_from_url($url)
{
    $host = parse_url((string) $url, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return '';
    }
    $host = strtolower($host);
    if (str_starts_with($host, 'www.')) {
        $host = substr($host, 4);
    }
    return $host;
}

/**
 * @param array<int, array{id: int, provider_catalog_id?: string, url?: string}> $subscriptions
 * @return array<int, array{id: int, name: string, hosts: string[], packages: string[], dns_hosts: string[], catalog: bool}>
 */
function usage_watch_targets(array $subscriptions)
{
    $signals = usage_catalog_signals();
    $targets = [];
    foreach ($subscriptions as $subscription) {
        $catalogId = (string) ($subscription['provider_catalog_id'] ?? '');
        $signal = $signals[$catalogId] ?? null;
        $hosts = $signal['hosts'] ?? [];
        $dnsHosts = $signal['dns_hosts'] ?? $hosts;
        $packages = $signal['packages'] ?? [];
        if ($hosts === []) {
            $host = usage_host_from_url($subscription['url'] ?? '');
            if ($host !== '') {
                $hosts = [$host];
                $dnsHosts = [$host];
            }
        }
        $targets[] = [
            'id' => (int) $subscription['id'],
            'name' => (string) ($subscription['name'] ?? ''),
            'hosts' => $hosts,
            'packages' => $packages,
            'dns_hosts' => $dnsHosts,
            'catalog' => $catalogId !== '',
        ];
    }
    return $targets;
}

/**
 * @param array<int, array{id: int, hosts?: string[], packages?: string[], dns_hosts?: string[], catalog: bool}> $targets
 */
function usage_match_target(array $targets, $value, $field)
{
    $matches = [];
    foreach ($targets as $target) {
        foreach ($target[$field] ?? [] as $candidate) {
            $matched = $field === 'packages'
                ? strtolower((string) $value) === strtolower((string) $candidate)
                : usage_host_matches($value, $candidate);
            if ($matched) {
                $matches[] = $target;
                break;
            }
        }
    }
    if (count($matches) === 1) {
        return $matches[0];
    }
    $catalogMatches = array_values(array_filter($matches, function ($target) {
        return !empty($target['catalog']);
    }));
    if (count($catalogMatches) === 1) {
        return $catalogMatches[0];
    }
    return null;
}
