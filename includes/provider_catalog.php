<?php
/*
  Static catalogue of popular subscription providers used by the "add
  subscription" modal. This is a convenience layer for starting a manual
  registration: selecting a provider never creates a subscription, never
  claims an account is linked, and never talks to a third-party API.

  Kept as a single PHP list so the same source of truth can be rendered into
  the page as JSON for client-side search/filter. Extending the catalogue is
  adding a row here - not a new component per brand.

  Future integration metadata (account linking, status checks, API
  cancellation) may be attached to an entry later. Those fields exist as
  explicit false/null placeholders so callers can read a stable shape without
  implying any capability that is not implemented yet.
*/

/**
 * @return array<int, array{
 *   id: string,
 *   name: string,
 *   key: string,
 *   search_terms: string[],
 *   category: string|null,
 *   url: string|null,
 *   icon: array{type: string, class?: string, color?: string, text?: string},
 *   account_linking: bool,
 *   status_check: bool,
 *   api_cancellation: bool,
 *   integration_type: string|null
 * }>
 */
function get_provider_catalog()
{
    return [
        [
            'id' => 'spotify',
            'name' => 'Spotify',
            'key' => 'spotify',
            'search_terms' => ['spotify', 'music'],
            'category' => 'music',
            'url' => 'https://www.spotify.com',
            'icon' => ['type' => 'fa-brands', 'class' => 'fa-spotify', 'color' => '#1DB954'],
            'account_linking' => false,
            'status_check' => false,
            'api_cancellation' => false,
            'integration_type' => null,
        ],
        [
            'id' => 'netflix',
            'name' => 'Netflix',
            'key' => 'netflix',
            'search_terms' => ['netflix', 'streaming'],
            'category' => 'entertainment',
            'url' => 'https://www.netflix.com',
            'icon' => ['type' => 'initials', 'text' => 'N', 'color' => '#E50914'],
            'account_linking' => false,
            'status_check' => false,
            'api_cancellation' => false,
            'integration_type' => null,
        ],
        [
            'id' => 'disney-plus',
            'name' => 'Disney+',
            'key' => 'disney_plus',
            'search_terms' => ['disney+', 'disney plus', 'disney'],
            'category' => 'entertainment',
            'url' => 'https://www.disneyplus.com',
            'icon' => ['type' => 'initials', 'text' => 'D+', 'color' => '#113CCF'],
            'account_linking' => false,
            'status_check' => false,
            'api_cancellation' => false,
            'integration_type' => null,
        ],
        [
            'id' => 'youtube-premium',
            'name' => 'YouTube Premium',
            'key' => 'youtube_premium',
            'search_terms' => ['youtube premium', 'youtube', 'yt premium'],
            'category' => 'entertainment',
            'url' => 'https://www.youtube.com/premium',
            'icon' => ['type' => 'fa-brands', 'class' => 'fa-youtube', 'color' => '#FF0000'],
            'account_linking' => false,
            'status_check' => false,
            'api_cancellation' => false,
            'integration_type' => null,
        ],
        [
            'id' => 'amazon-prime',
            'name' => 'Amazon Prime',
            'key' => 'amazon_prime',
            'search_terms' => ['amazon prime', 'prime video', 'amazon', 'prime'],
            'category' => 'entertainment',
            'url' => 'https://www.amazon.com/prime',
            'icon' => ['type' => 'fa-brands', 'class' => 'fa-amazon', 'color' => '#FF9900'],
            'account_linking' => false,
            'status_check' => false,
            'api_cancellation' => false,
            'integration_type' => null,
        ],
        [
            'id' => 'apple-music',
            'name' => 'Apple Music',
            'key' => 'apple_music',
            'search_terms' => ['apple music', 'apple'],
            'category' => 'music',
            'url' => 'https://www.apple.com/apple-music/',
            'icon' => ['type' => 'fa-brands', 'class' => 'fa-apple', 'color' => '#FA243C'],
            'account_linking' => false,
            'status_check' => false,
            'api_cancellation' => false,
            'integration_type' => null,
        ],
        [
            'id' => 'apple-tv-plus',
            'name' => 'Apple TV+',
            'key' => 'apple_tv_plus',
            'search_terms' => ['apple tv+', 'apple tv plus', 'apple tv', 'tv+'],
            'category' => 'entertainment',
            'url' => 'https://tv.apple.com',
            'icon' => ['type' => 'fa-brands', 'class' => 'fa-apple', 'color' => '#000000'],
            'account_linking' => false,
            'status_check' => false,
            'api_cancellation' => false,
            'integration_type' => null,
        ],
        [
            'id' => 'max',
            'name' => 'Max',
            'key' => 'max',
            'search_terms' => ['max', 'hbo max', 'hbo', 'warner'],
            'category' => 'entertainment',
            'url' => 'https://www.max.com',
            'icon' => ['type' => 'initials', 'text' => 'M', 'color' => '#002BE7'],
            'account_linking' => false,
            'status_check' => false,
            'api_cancellation' => false,
            'integration_type' => null,
        ],
        [
            'id' => 'microsoft-365',
            'name' => 'Microsoft 365',
            'key' => 'microsoft_365',
            'search_terms' => ['microsoft 365', 'office 365', 'microsoft', 'office', '365'],
            'category' => 'productivity',
            'url' => 'https://www.microsoft.com/microsoft-365',
            'icon' => ['type' => 'fa-brands', 'class' => 'fa-microsoft', 'color' => '#00A4EF'],
            'account_linking' => false,
            'status_check' => false,
            'api_cancellation' => false,
            'integration_type' => null,
        ],
        [
            'id' => 'google-one',
            'name' => 'Google One',
            'key' => 'google_one',
            'search_terms' => ['google one', 'google', 'storage'],
            'category' => 'cloud_services',
            'url' => 'https://one.google.com',
            'icon' => ['type' => 'fa-brands', 'class' => 'fa-google', 'color' => '#4285F4'],
            'account_linking' => false,
            'status_check' => false,
            'api_cancellation' => false,
            'integration_type' => null,
        ],
        [
            'id' => 'dropbox',
            'name' => 'Dropbox',
            'key' => 'dropbox',
            'search_terms' => ['dropbox', 'storage', 'cloud'],
            'category' => 'cloud_services',
            'url' => 'https://www.dropbox.com',
            'icon' => ['type' => 'fa-brands', 'class' => 'fa-dropbox', 'color' => '#0061FF'],
            'account_linking' => false,
            'status_check' => false,
            'api_cancellation' => false,
            'integration_type' => null,
        ],
        [
            'id' => 'playstation-plus',
            'name' => 'PlayStation Plus',
            'key' => 'playstation_plus',
            'search_terms' => ['playstation plus', 'ps plus', 'ps+', 'playstation', 'sony'],
            'category' => 'gaming',
            'url' => 'https://www.playstation.com/ps-plus/',
            'icon' => ['type' => 'fa-brands', 'class' => 'fa-playstation', 'color' => '#003791'],
            'account_linking' => false,
            'status_check' => false,
            'api_cancellation' => false,
            'integration_type' => null,
        ],
        [
            'id' => 'xbox-game-pass',
            'name' => 'Xbox Game Pass',
            'key' => 'xbox_game_pass',
            'search_terms' => ['xbox game pass', 'game pass', 'xbox', 'microsoft'],
            'category' => 'gaming',
            'url' => 'https://www.xbox.com/xbox-game-pass',
            'icon' => ['type' => 'fa-brands', 'class' => 'fa-xbox', 'color' => '#107C10'],
            'account_linking' => false,
            'status_check' => false,
            'api_cancellation' => false,
            'integration_type' => null,
        ],
        [
            'id' => 'adobe-creative-cloud',
            'name' => 'Adobe Creative Cloud',
            'key' => 'adobe_creative_cloud',
            'search_terms' => ['adobe creative cloud', 'adobe', 'creative cloud', 'photoshop', 'illustrator'],
            'category' => 'software',
            'url' => 'https://www.adobe.com/creativecloud.html',
            'icon' => ['type' => 'initials', 'text' => 'Ad', 'color' => '#FF0000'],
            'account_linking' => false,
            'status_check' => false,
            'api_cancellation' => false,
            'integration_type' => null,
        ],
    ];
}

/**
 * Find one catalogue entry by its stable id, or null when unknown.
 *
 * @param string $id
 * @return array|null
 */
function get_provider_catalog_entry($id)
{
    foreach (get_provider_catalog() as $provider) {
        if ($provider['id'] === $id) {
            return $provider;
        }
    }

    return null;
}
