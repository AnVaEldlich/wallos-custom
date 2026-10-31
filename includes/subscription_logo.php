<?php

require_once __DIR__ . '/provider_catalog.php';
require_once __DIR__ . '/logo_theme_variant.php';

/**
 * Find a catalogue entry by the subscription display name or an exact alias.
 *
 * @param string $name
 * @return array|null
 */
function find_provider_catalog_entry_by_subscription_name($name)
{
    $normalized = strtolower(trim($name));
    if ($normalized === '') {
        return null;
    }

    foreach (get_provider_catalog() as $provider) {
        if (strtolower($provider['name']) === $normalized) {
            return $provider;
        }
        foreach ($provider['search_terms'] as $term) {
            if (strtolower(trim($term)) === $normalized) {
                return $provider;
            }
        }
    }

    return null;
}

/**
 * Resolve the stable catalogue id for a subscription row or form payload.
 *
 * @param string $name
 * @param string $providerCatalogId
 * @return string
 */
function resolve_provider_catalog_id_for_subscription($name, $providerCatalogId = '')
{
    $providerCatalogId = trim($providerCatalogId);
    if ($providerCatalogId !== '' && get_provider_catalog_entry($providerCatalogId) !== null) {
        return $providerCatalogId;
    }

    $matched = find_provider_catalog_entry_by_subscription_name($name);
    return $matched !== null ? $matched['id'] : '';
}

/**
 * @param array<string, mixed> $row
 * @return string|null
 */
function subscription_catalog_logo_url_for_row($row)
{
    $catalogId = $row['provider_catalog_id'] ?? '';
    if ($catalogId !== '') {
        $url = provider_catalog_logo_url_for_id($catalogId);
        if ($url !== null) {
            return $url;
        }
    }

    $name = $row['name'] ?? '';
    $matched = find_provider_catalog_entry_by_subscription_name($name);
    if ($matched !== null) {
        return provider_catalog_logo_url_for_id($matched['id']);
    }

    return null;
}

/**
 * @param array<string, mixed> $row
 */
function subscription_row_has_uploaded_logo($row)
{
    $logo = $row['logo'] ?? '';
    return $logo !== '' && $logo !== null;
}

/**
 * Whether the subscription list should treat this row as having a visual logo.
 *
 * @param array<string, mixed> $row
 */
function subscription_row_has_display_logo($row)
{
    if (subscription_row_has_uploaded_logo($row)) {
        return true;
    }

    return subscription_catalog_logo_url_for_row($row) !== null;
}

/**
 * Render the logo area for a subscription card in the list view.
 *
 * @param array<string, mixed> $row
 * @param string $class
 * @param string $extraAttrs
 * @return string
 */
function render_subscription_card_logo($row, $class = '', $extraAttrs = '')
{
    $name = htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8');
    $altAttrs = $extraAttrs !== '' ? $extraAttrs : 'alt="' . $name . '"';

    // Prefer the catalogue icon whenever we can identify the provider. That
    // covers rows created before logos were auto-saved (logo column empty) and
    // keeps cards consistent with the details modal.
    $catalogUrl = subscription_catalog_logo_url_for_row($row);
    if ($catalogUrl !== null) {
        return '<img src="' . htmlspecialchars($catalogUrl, ENT_QUOTES, 'UTF-8') . '" class="'
            . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . ' subscription-catalog-logo" ' . $altAttrs . ' loading="lazy">';
    }

    if (subscription_row_has_uploaded_logo($row)) {
        $originalSrc = $row['logo'];
        if (strpos($originalSrc, 'images/') !== 0) {
            $originalSrc = 'images/uploads/logos/' . $originalSrc;
        }
        $variantSrc = !empty($row['logo_variant']) ? $row['logo_variant'] : null;
        if ($variantSrc !== null && strpos($variantSrc, 'images/') !== 0) {
            $variantSrc = 'images/uploads/logos/' . $variantSrc;
        }

        return renderThemedLogoImg(
            $originalSrc,
            $variantSrc,
            $row['logo_text_color'] ?? null,
            $class,
            $altAttrs
        );
    }

    $initial = mb_strtoupper(mb_substr($row['name'] ?? '?', 0, 1));

    return '<span class="subscription-logo-fallback ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" '
        . $altAttrs . '>' . htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') . '</span>';
}
