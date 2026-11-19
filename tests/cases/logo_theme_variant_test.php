<?php
// classifyLogoTextColor() decides which logos get a recolored variant for the
// other theme. Only black or white "ink" on a transparent background should
// qualify; an icon with its own colored background must be left alone.

require_once DIAMON_ROOT . '/includes/logo_theme_variant.php';

function logo_theme_canvas($width, $height)
{
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    return $image;
}

if (function_exists('imagecreatetruecolor')) {
    diamon_test('A colored icon with white details gets no themed variant', function () {
        // Duolingo-like: green tile with two white eyes and dark pupils.
        $image = logo_theme_canvas(100, 100);
        imagefilledrectangle($image, 0, 0, 99, 99, imagecolorallocate($image, 88, 204, 2));
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 20, 20, 20);
        imagefilledellipse($image, 30, 45, 34, 34, $white);
        imagefilledellipse($image, 70, 45, 34, 34, $white);
        imagefilledellipse($image, 30, 45, 10, 10, $black);
        imagefilledellipse($image, 70, 45, 10, 10, $black);

        assert_same(null, classifyLogoTextColor($image), 'Icon with its own colored background should not be classified');
    });

    diamon_test('Black text on a transparent background is dark ink', function () {
        $image = logo_theme_canvas(100, 40);
        imagefilledrectangle($image, 10, 10, 90, 30, imagecolorallocate($image, 0, 0, 0));

        assert_same('dark', classifyLogoTextColor($image), 'Black ink should be classified as dark');
    });

    diamon_test('White text on a transparent background is light ink', function () {
        $image = logo_theme_canvas(100, 40);
        imagefilledrectangle($image, 10, 10, 90, 30, imagecolorallocate($image, 255, 255, 255));

        assert_same('light', classifyLogoTextColor($image), 'White ink should be classified as light');
    });

    diamon_test('Black text next to a small colored mark is still dark ink', function () {
        // Apple TV / Notion style: mostly black ink, small colored accent.
        $image = logo_theme_canvas(100, 40);
        imagefilledrectangle($image, 30, 10, 95, 30, imagecolorallocate($image, 0, 0, 0));
        imagefilledrectangle($image, 5, 15, 15, 25, imagecolorallocate($image, 230, 30, 30));

        assert_same('dark', classifyLogoTextColor($image), 'A small colored accent should not block the variant');
    });
}
