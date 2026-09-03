<?php
/**
 * Google Maps Configuration & Integration Loader
 * Reads API key strictly from EZEV Core settings.
 */

if (!defined('ABSPATH')) { exit; }

final class EZEV_Theme_Maps {
    public static function get_config(): array {
        $key = '';
        $map_id = '';
        $default_lat = 14.5547; // Default Manila / BGC center
        $default_lng = 121.0244;
        $default_zoom = 12;

        // Retrieve from Core settings if available
        if (class_exists('EZEV_Core_Admin')) {
            $settings = get_option('ezev_core_maps_settings', []);
            $key = (string) ($settings['api_key'] ?? get_option('_ezev_google_maps_api_key', ''));
            $map_id = (string) ($settings['map_id'] ?? '');
            if (!empty($settings['default_lat'])) { $default_lat = (float) $settings['default_lat']; }
            if (!empty($settings['default_lng'])) { $default_lng = (float) $settings['default_lng']; }
            if (!empty($settings['default_zoom'])) { $default_zoom = (int) $settings['default_zoom']; }
        } else {
            $key = (string) get_option('_ezev_google_maps_api_key', '');
        }

        // Environment fallback for local dev if set
        if (!$key && defined('EZEV_GOOGLE_MAPS_API_KEY')) {
            $key = (string) EZEV_GOOGLE_MAPS_API_KEY;
        }

        return [
            'hasKey'       => !empty($key),
            'apiKey'       => $key,
            'mapId'        => $map_id,
            'defaultCenter'=> ['lat' => $default_lat, 'lng' => $default_lng],
            'defaultZoom'  => $default_zoom,
        ];
    }

    public static function get_api_script_url(): string {
        $config = self::get_config();
        if (!$config['hasKey']) {
            return '';
        }
        return sprintf(
            'https://maps.googleapis.com/maps/api/js?key=%s&libraries=places,geometry&loading=async',
            rawurlencode($config['apiKey'])
        );
    }
}