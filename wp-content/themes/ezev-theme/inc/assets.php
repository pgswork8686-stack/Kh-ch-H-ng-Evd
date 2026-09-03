<?php
/**
 * Theme Assets Enqueue & Localization
 */

if (!defined('ABSPATH')) { exit; }

function ezev_theme_enqueue_assets(): void {
    $theme_uri = get_template_directory_uri();
    $version = '1.0.0';

    // Google Fonts: Inter
    wp_enqueue_style(
        'ezev-fonts',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
        [],
        null
    );

    // CSS Variables & Main Styles
    wp_enqueue_style('ezev-variables', $theme_uri . '/assets/css/variables.css', [], $version);
    wp_enqueue_style('ezev-main', $theme_uri . '/assets/css/main.css', ['ezev-variables'], $version);

    // Page-specific styles
    if (is_front_page()) {
        wp_enqueue_style('ezev-home', $theme_uri . '/assets/css/home.css', ['ezev-main'], $version);
    } elseif (is_page('find-a-charger') || is_page_template('page-find-a-charger.php')) {
        wp_enqueue_style('ezev-find-charger', $theme_uri . '/assets/css/find-charger.css', ['ezev-main'], $version);
    } elseif (is_singular('ezev_station')) {
        wp_enqueue_style('ezev-station-detail', $theme_uri . '/assets/css/station-detail.css', ['ezev-main'], $version);
    } elseif (is_page('charging-network') || is_page_template('page-charging-network.php')) {
        wp_enqueue_style('ezev-charging-network', $theme_uri . '/assets/css/charging-network.css', ['ezev-main'], $version);
    }

    // Google Maps Script (if key configured)
    $maps_config = EZEV_Theme_Maps::get_config();
    if ($maps_config['hasKey']) {
        wp_enqueue_script('google-maps', EZEV_Theme_Maps::get_api_script_url(), [], null, true);
    }

    // Shared Data Client SDK
    wp_enqueue_script('ezev-data-client', $theme_uri . '/assets/js/ezev-data-client.js', [], $version, true);

    // Localize theme configuration for JavaScript
    wp_localize_script('ezev-data-client', 'ezevThemeData', [
        'apiRoot'     => esc_url_raw(rest_url('ezev/v1')),
        'mapsConfig'  => $maps_config,
        'homeUrl'     => esc_url(home_url('/')),
        'findUrl'     => esc_url(home_url('/find-a-charger/')),
        'networkUrl'  => esc_url(home_url('/charging-network/')),
        'imagesUrl'   => esc_url($theme_uri . '/assets/images'),
    ]);
}
add_action('wp_enqueue_scripts', 'ezev_theme_enqueue_assets');