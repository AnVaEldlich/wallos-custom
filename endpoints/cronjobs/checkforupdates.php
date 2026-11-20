<?php

require_once 'validate.php';
require_once __DIR__ . '/../../includes/connect_endpoint_crontabs.php';

$options = [
    'http' => [
        'header' => "User-Agent: Diamon\r\n"
    ]
];

// Diamon follows the upstream Wallos releases; point this at your own repository
// if you publish Diamon releases separately.
$repository = 'ellite/Wallos';
$url = "https://api.github.com/repos/$repository/releases/latest";

$context = stream_context_create($options);
$fetch = file_get_contents($url, false, $context);

if ($fetch === false) {
    die('Error fetching data from GitHub API');
}

$latestVersion = json_decode($fetch, true)['tag_name'];

// Check that $latestVersion is a valid version number
if (!preg_match('/^v\d+\.\d+\.\d+$/', $latestVersion)) {
    die('Error: Invalid version number from GitHub API');
}

$db->exec("UPDATE admin SET latest_version = '$latestVersion'");


if (php_sapi_name() !== 'cli') {
    include __DIR__ . '/../../includes/version.php';
    if (version_compare($latestVersion, $version) > 0) {
        echo "New version available: $latestVersion";
    } else {
        echo "No new version available, currently on $version";
    }
}
?>