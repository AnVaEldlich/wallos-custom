<?php

// Re-checks logos that were given a themed variant before
// classifyLogoTextColor() learned to skip icons with their own colored
// background. Those icons (e.g. Duolingo's green owl) got their white or black
// details inverted in the other theme -- the owl's white eyes turned black.
// Rows whose original logo no longer qualifies lose the variant, and the
// variant file is deleted once nothing else names it.

require_once __DIR__ . '/../includes/logo_theme_variant.php';
require_once __DIR__ . '/../includes/logo_cleanup.php';

$hasVariantColumn = $db->querySingle("SELECT COUNT(*) FROM pragma_table_info('subscriptions') WHERE name='logo_variant'");
if ($hasVariantColumn && function_exists('imagecreatefromstring')) {
    $logosDir = __DIR__ . '/../images/uploads/logos/';

    $rows = [];
    $query = $db->query("SELECT id, logo, logo_variant FROM subscriptions
        WHERE logo_variant IS NOT NULL AND logo_variant != ''
          AND logo IS NOT NULL AND logo != ''");
    while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $row;
    }

    $classified = [];
    foreach ($rows as $row) {
        $logo = basename($row['logo']);
        if (!array_key_exists($logo, $classified)) {
            $classified[$logo] = false; // unreadable: leave the row as it is
            $path = $logosDir . $logo;
            if (is_file($path)) {
                $image = @imagecreatefromstring((string) file_get_contents($path));
                if ($image !== false) {
                    $classified[$logo] = classifyLogoTextColor($image);
                    imagedestroy($image);
                }
            }
        }

        if ($classified[$logo] !== null) {
            continue; // still a black/white-ink logo, or the file could not be read
        }

        $update = $db->prepare('UPDATE subscriptions SET logo_text_color = NULL, logo_variant = NULL WHERE id = :id');
        $update->bindValue(':id', $row['id'], SQLITE3_INTEGER);
        $update->execute();

        deleteLogoFileIfUnused($db, $row['logo_variant'], $logosDir);
    }
}

?>
