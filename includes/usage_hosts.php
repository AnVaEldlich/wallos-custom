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
        'chatgpt' => [
            'hosts' => ['chatgpt.com', 'chat.openai.com'],
            'dns_hosts' => ['chatgpt.com', 'openai.com'],
            'packages' => ['com.openai.chatgpt'],
        ],
        'claude' => [
            'hosts' => ['claude.ai'],
            'dns_hosts' => ['claude.ai', 'anthropic.com'],
            'packages' => ['com.anthropic.claude'],
        ],
        'claude-code' => [
            'hosts' => ['code.claude.com'],
            'dns_hosts' => ['code.claude.com'],
            'packages' => [],
        ],
        'google-gemini' => [
            'hosts' => ['gemini.google.com'],
            'dns_hosts' => ['gemini.google.com'],
            'packages' => ['com.google.android.apps.bard'],
        ],
        'perplexity' => [
            'hosts' => ['perplexity.ai'],
            'dns_hosts' => ['perplexity.ai'],
            'packages' => ['ai.perplexity.app.android'],
        ],
        'grok' => [
            'hosts' => ['grok.x.com'],
            'dns_hosts' => ['grok.x.com'],
            'packages' => [],
        ],
        'cursor' => [
            'hosts' => ['cursor.com'],
            'dns_hosts' => ['cursor.com'],
            'packages' => [],
        ],
        'google-antigravity' => [
            'hosts' => ['antigravity.google'],
            'dns_hosts' => ['antigravity.google'],
            'packages' => [],
        ],
        'github-copilot' => [
            // github.com alone would count every GitHub visit as Copilot use.
            'hosts' => ['copilot.microsoft.com'],
            'dns_hosts' => ['copilot.microsoft.com'],
            'packages' => [],
        ],
        'midjourney' => [
            'hosts' => ['midjourney.com'],
            'dns_hosts' => ['midjourney.com'],
            'packages' => [],
        ],
        'duolingo' => [
            'hosts' => ['duolingo.com'],
            'dns_hosts' => ['duolingo.com'],
            'packages' => ['com.duolingo'],
        ],
        'coursera' => [
            'hosts' => ['coursera.org'],
            'dns_hosts' => ['coursera.org'],
            'packages' => ['org.coursera.android'],
        ],
        'notion' => [
            'hosts' => ['notion.so', 'notion.com'],
            'dns_hosts' => ['notion.so', 'notion.com'],
            'packages' => ['notion.id'],
        ],
        'figma' => [
            'hosts' => ['figma.com'],
            'dns_hosts' => ['figma.com'],
            'packages' => ['com.figma.mirror'],
        ],
        'discord' => [
            'hosts' => ['discord.com'],
            'dns_hosts' => ['discord.com', 'discord.gg'],
            'packages' => ['com.discord'],
        ],
        'slack' => [
            'hosts' => ['slack.com', 'app.slack.com'],
            'dns_hosts' => ['slack.com'],
            'packages' => ['com.Slack'],
        ],
        'twitch' => [
            'hosts' => ['twitch.tv'],
            'dns_hosts' => ['twitch.tv'],
            'packages' => ['tv.twitch.android.app'],
        ],
        'hulu' => [
            'hosts' => ['hulu.com'],
            'dns_hosts' => ['hulu.com'],
            'packages' => ['com.hulu.plus'],
        ],
        'paramount-plus' => [
            'hosts' => ['paramountplus.com'],
            'dns_hosts' => ['paramountplus.com'],
            'packages' => ['com.cbs.app'],
        ],
        'peacock' => [
            'hosts' => ['peacocktv.com'],
            'dns_hosts' => ['peacocktv.com'],
            'packages' => ['air.com.vudu.air.DownloaderTablet'],
        ],
        'crunchyroll' => [
            'hosts' => ['crunchyroll.com'],
            'dns_hosts' => ['crunchyroll.com'],
            'packages' => ['com.crunchyroll.crunchyroid'],
        ],
        'audible' => [
            'hosts' => ['audible.com'],
            'dns_hosts' => ['audible.com'],
            'packages' => ['com.audible.application'],
        ],
        'deezer' => [
            'hosts' => ['deezer.com'],
            'dns_hosts' => ['deezer.com'],
            'packages' => ['deezer.android.app'],
        ],
        'tidal' => [
            'hosts' => ['tidal.com'],
            'dns_hosts' => ['tidal.com'],
            'packages' => ['com.aspiro.tidal'],
        ],
        'soundcloud' => [
            'hosts' => ['soundcloud.com'],
            'dns_hosts' => ['soundcloud.com'],
            'packages' => ['com.soundcloud.android'],
        ],
        'todoist' => [
            'hosts' => ['todoist.com', 'app.todoist.com'],
            'dns_hosts' => ['todoist.com'],
            'packages' => ['com.todoist'],
        ],
        'linear' => [
            'hosts' => ['linear.app'],
            'dns_hosts' => ['linear.app'],
            'packages' => [],
        ],
        'trello' => [
            'hosts' => ['trello.com'],
            'dns_hosts' => ['trello.com'],
            'packages' => ['com.trello'],
        ],
        'airtable' => [
            'hosts' => ['airtable.com'],
            'dns_hosts' => ['airtable.com'],
            'packages' => ['com.formagrid.airtable'],
        ],
        'zoom' => [
            'hosts' => ['zoom.us'],
            'dns_hosts' => ['zoom.us'],
            'packages' => ['us.zoom.videomeetings'],
        ],
        'microsoft-teams' => [
            'hosts' => ['teams.microsoft.com', 'teams.live.com'],
            'dns_hosts' => ['teams.microsoft.com'],
            'packages' => ['com.microsoft.teams'],
        ],
        'linkedin' => [
            'hosts' => ['linkedin.com'],
            'dns_hosts' => ['linkedin.com'],
            'packages' => ['com.linkedin.android'],
        ],
        'patreon' => [
            'hosts' => ['patreon.com'],
            'dns_hosts' => ['patreon.com'],
            'packages' => ['com.patreon.android'],
        ],
        'google-drive' => [
            'hosts' => ['drive.google.com'],
            'dns_hosts' => ['drive.google.com'],
            'packages' => ['com.google.android.apps.docs'],
        ],
        'icloud' => [
            'hosts' => ['icloud.com'],
            'dns_hosts' => ['icloud.com'],
            'packages' => [],
        ],
        'box' => [
            'hosts' => ['box.com', 'app.box.com'],
            'dns_hosts' => ['box.com'],
            'packages' => ['com.box.android'],
        ],
        'mega' => [
            'hosts' => ['mega.nz', 'mega.io'],
            'dns_hosts' => ['mega.nz', 'mega.io'],
            'packages' => ['mega.privacy.android.app'],
        ],
        'proton' => [
            'hosts' => ['proton.me', 'mail.proton.me', 'account.proton.me'],
            'dns_hosts' => ['proton.me'],
            'packages' => ['ch.protonmail.android'],
        ],
        'nordvpn' => [
            'hosts' => ['nordvpn.com', 'nordaccount.com'],
            'dns_hosts' => ['nordvpn.com', 'nordaccount.com'],
            'packages' => ['com.nordvpn.android'],
        ],
        'onepassword' => [
            'hosts' => ['1password.com', 'my.1password.com'],
            'dns_hosts' => ['1password.com'],
            'packages' => ['com.onepassword.android'],
        ],
        'bitwarden' => [
            'hosts' => ['bitwarden.com', 'vault.bitwarden.com'],
            'dns_hosts' => ['bitwarden.com'],
            'packages' => ['com.x8bit.bitwarden'],
        ],
        'jetbrains' => [
            'hosts' => ['jetbrains.com', 'account.jetbrains.com'],
            'dns_hosts' => ['jetbrains.com'],
            'packages' => [],
        ],
        'obsidian' => [
            'hosts' => ['obsidian.md'],
            'dns_hosts' => ['obsidian.md'],
            'packages' => ['md.obsidian'],
        ],
        'plex' => [
            'hosts' => ['plex.tv', 'app.plex.tv'],
            'dns_hosts' => ['plex.tv'],
            'packages' => ['com.plexapp.android'],
        ],
        'capcut' => [
            'hosts' => ['capcut.com'],
            'dns_hosts' => ['capcut.com'],
            'packages' => ['com.lemon.lvoverseas'],
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
