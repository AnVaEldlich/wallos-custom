<?php

// Backfill catalogue logos for subscriptions that already have a
// provider_catalog_id (or a matching catalogue name) but no uploaded logo.
// Those rows previously fell back to initials in the details modal.

require_once __DIR__ . '/../includes/subscription_logo.php';
require_once __DIR__ . '/../includes/ssrf_helper.php';

$uploadDir = __DIR__ . '/../images/uploads/logos/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

/**
 * Minimal logo fetch for migrations (same public-CDN path the add form uses).
 *
 * @param string $url
 * @param string $uploadDir
 * @param string $name
 * @return string|null saved filename or null on failure
 */
function migration_000063_fetch_logo($url, $uploadDir, $name)
{
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $url)) {
        return null;
    }

    $parts = parse_url($url);
    $host = $parts['host'] ?? '';
    if ($host === '') {
        return null;
    }

    $ip = gethostbyname($host);
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return null;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    $imageData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);

    if ($imageData === false || $httpCode !== 200) {
        return null;
    }

    $image = @imagecreatefromstring($imageData);
    if ($image === false) {
        return null;
    }

    $safeName = preg_replace('/[^a-zA-Z0-9]+/', '-', $name);
    $safeName = trim($safeName, '-');
    if ($safeName === '') {
        $safeName = 'provider';
    }
    $fileName = time() . '-' . $safeName . '.png';
    $path = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $fileName;

    imagealphablending($image, false);
    imagesavealpha($image, true);
    $saved = imagepng($image, $path);
    imagedestroy($image);

    return $saved ? $fileName : null;
}

$hasProviderColumn = $db->querySingle("SELECT COUNT(*) FROM pragma_table_info('subscriptions') WHERE name='provider_catalog_id'");
if (!$hasProviderColumn) {
    return;
}

$query = $db->query("SELECT id, name, logo, provider_catalog_id FROM subscriptions
    WHERE (logo IS NULL OR logo = '')");
while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
    $catalogId = resolve_provider_catalog_id_for_subscription($row['name'] ?? '', $row['provider_catalog_id'] ?? '');
    if ($catalogId === '') {
        continue;
    }

    $logoUrl = provider_catalog_logo_url_for_id($catalogId);
    if ($logoUrl === null) {
        continue;
    }

    $fileName = migration_000063_fetch_logo($logoUrl, $uploadDir, $row['name'] ?? $catalogId);
    if ($fileName === null) {
        // Still persist the catalogue id so the UI can render from CDN.
        if (empty($row['provider_catalog_id'])) {
            $update = $db->prepare('UPDATE subscriptions SET provider_catalog_id = :catalogId WHERE id = :id');
            $update->bindValue(':catalogId', $catalogId, SQLITE3_TEXT);
            $update->bindValue(':id', $row['id'], SQLITE3_INTEGER);
            $update->execute();
        }
        continue;
    }

    $update = $db->prepare('UPDATE subscriptions
        SET logo = :logo, provider_catalog_id = :catalogId
        WHERE id = :id');
    $update->bindValue(':logo', $fileName, SQLITE3_TEXT);
    $update->bindValue(':catalogId', $catalogId, SQLITE3_TEXT);
    $update->bindValue(':id', $row['id'], SQLITE3_INTEGER);
    $update->execute();

    // Avoid colliding timestamps when several logos are saved in the same second.
    usleep(1000000);
}

?>
