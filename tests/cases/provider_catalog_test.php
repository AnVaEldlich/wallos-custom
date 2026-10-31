<?php
/*
  The provider catalogue is a static convenience list for starting a manual
  subscription registration. These checks guard the shape that the add-modal
  and future integration phases rely on: stable ids, no implied capabilities,
  and a single source of truth.
*/

require_once WALLOS_ROOT . '/includes/provider_catalog.php';

wallos_test('provider catalogue has unique stable ids and required fields', function () {
    $catalog = get_provider_catalog();
    assert_true(count($catalog) >= 10, 'expected a useful starter catalogue');

    $ids = [];
    foreach ($catalog as $provider) {
        assert_true(is_array($provider), 'each entry must be an array');
        foreach (['id', 'name', 'key', 'search_terms', 'icon', 'logo_slug', 'account_linking', 'status_check', 'api_cancellation', 'integration_type'] as $field) {
            assert_true(array_key_exists($field, $provider), "missing field {$field}");
        }

        assert_true($provider['id'] !== '', 'id must be non-empty');
        assert_true($provider['name'] !== '', 'name must be non-empty');
        assert_true($provider['key'] !== '', 'key must be non-empty');
        assert_true($provider['logo_slug'] !== '', 'logo_slug must be non-empty');
        assert_true(is_array($provider['search_terms']), 'search_terms must be an array');
        assert_true(is_array($provider['icon']), 'icon must be an array');
        assert_true(in_array($provider['icon']['type'], ['fa-brands', 'initials'], true), 'icon type must be known');

        if ($provider['icon']['type'] === 'fa-brands') {
            assert_true(!empty($provider['icon']['class']), 'fa-brands icons need a class');
        } else {
            assert_true(!empty($provider['icon']['text']), 'initials icons need text');
        }

        // Phase 1 must not pretend integrations exist.
        assert_same(false, $provider['account_linking'], 'account_linking must be false in phase 1');
        assert_same(false, $provider['status_check'], 'status_check must be false in phase 1');
        assert_same(false, $provider['api_cancellation'], 'api_cancellation must be false in phase 1');
        assert_same(null, $provider['integration_type'], 'integration_type must be null in phase 1');

        assert_true(!isset($ids[$provider['id']]), 'duplicate provider id: ' . $provider['id']);
        $ids[$provider['id']] = true;
    }
});

wallos_test('provider catalogue lookup finds known entries and rejects unknowns', function () {
    $spotify = get_provider_catalog_entry('spotify');
    assert_true($spotify !== null, 'spotify should exist');
    assert_same('Spotify', $spotify['name'], 'spotify display name');

    $max = get_provider_catalog_entry('max');
    assert_true($max !== null, 'max should exist under the current brand name');
    assert_true(in_array('hbo max', $max['search_terms'], true), 'max should remain findable as hbo max');

    assert_same(null, get_provider_catalog_entry('does-not-exist'), 'unknown ids return null');
});

wallos_test('provider catalogue search terms cover expected starter brands', function () {
    $names = array_map(function ($provider) {
        return $provider['name'];
    }, get_provider_catalog());

    foreach (['Spotify', 'Netflix', 'Disney+', 'YouTube Premium', 'Amazon Prime', 'Apple Music', 'Apple TV+', 'Max', 'Microsoft 365', 'Google One', 'Dropbox', 'PlayStation Plus', 'Xbox Game Pass', 'Adobe Creative Cloud'] as $expected) {
        assert_true(in_array($expected, $names, true), "missing starter provider: {$expected}");
    }
});

wallos_test('provider catalogue logo URLs use the selfh.st jsDelivr path', function () {
    assert_same(
        'https://cdn.jsdelivr.net/gh/selfhst/icons/png/netflix.png',
        provider_catalog_logo_url_for_id('netflix'),
        'netflix logo url'
    );
    assert_same(null, provider_catalog_logo_url_for_id('does-not-exist'), 'unknown provider has no logo url');
    assert_same(null, provider_catalog_logo_remote_url('../evil'), 'unsafe slug is rejected');
});

wallos_test('subscriptions page mounts the catalogue from the single PHP source', function () {
    $source = file_get_contents(WALLOS_ROOT . '/subscriptions.php');
    assert_true(strpos($source, "require_once 'includes/provider_catalog.php'") !== false, 'page loads the catalogue');
    assert_true(strpos($source, 'id="provider-catalog"') !== false, 'catalogue markup is present');
    assert_true(strpos($source, 'enterManualSubscriptionForm') !== false, 'manual entry remains available');
    $popupSource = file_get_contents(WALLOS_ROOT . '/includes/subscription_details_popup.php');
    assert_true(strpos($popupSource, 'window.providerCatalog') !== false, 'catalogue JSON is exposed for details modal');
    assert_true(!file_exists(WALLOS_ROOT . '/scripts/provider-catalog.js'), 'catalogue is not duplicated in a separate JS data file');
});
