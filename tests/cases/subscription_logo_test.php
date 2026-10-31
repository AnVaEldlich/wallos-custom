<?php

require_once WALLOS_ROOT . '/includes/subscription_logo.php';

wallos_test('subscription logo resolves catalogue icons by id and by name', function () {
    $byId = [
        'name' => 'Netflix',
        'logo' => '',
        'provider_catalog_id' => 'netflix',
    ];
    assert_true(subscription_row_has_display_logo($byId), 'catalog id should count as a display logo');
    assert_same(
        provider_catalog_logo_url_for_id('netflix'),
        subscription_catalog_logo_url_for_row($byId),
        'catalog url from id'
    );

    $byName = [
        'name' => 'Spotify',
        'logo' => '',
        'provider_catalog_id' => '',
    ];
    assert_true(subscription_row_has_display_logo($byName), 'known name should match catalogue');
    assert_contains('spotify.png', subscription_catalog_logo_url_for_row($byName), 'spotify slug in url');
});

wallos_test('subscription card render never falls back to the Wallos site logo markup', function () {
    $html = render_subscription_card_logo([
        'name' => 'Netflix',
        'logo' => '',
        'provider_catalog_id' => 'netflix',
    ]);
    assert_contains('subscription-catalog-logo', $html, 'uses catalogue image');
    assert_not_contains('siteicons', $html, 'does not embed Wallos svg logo');
});

wallos_test('catalogue icon wins over an uploaded logo for catalogue providers', function () {
    $html = render_subscription_card_logo([
        'name' => 'Netflix',
        'logo' => 'images/uploads/logos/custom-netflix.png',
        'provider_catalog_id' => 'netflix',
    ]);
    assert_contains('subscription-catalog-logo', $html, 'catalogue icon preferred in cards');
    assert_contains('netflix.png', $html, 'selfh.st netflix slug');
    assert_not_contains('custom-netflix.png', $html, 'uploaded file not used when catalogue matches');
});
