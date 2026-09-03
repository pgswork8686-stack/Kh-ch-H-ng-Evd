<?php
/**
 * EZEV Theme Helper Functions
 */

if (!defined('ABSPATH')) { exit; }

/**
 * Render Data Mode Badge according to Phase 4.1 specification
 */
function ezev_render_data_badge(string $mode = 'manual', bool $is_demo = false): string {
    if ($is_demo || $mode === 'demo') {
        return '<span class="ezev-badge ezev-badge-demo"><span class="ezev-badge-dot"></span>Demo Data</span>';
    }
    if ($mode === 'manual') {
        return '<span class="ezev-badge ezev-badge-manual"><span class="ezev-badge-dot"></span>Manual Data</span>';
    }
    if ($mode === 'api') {
        return '<span class="ezev-badge ezev-badge-available"><span class="ezev-badge-dot"></span>Live API</span>';
    }
    return '<span class="ezev-badge ezev-badge-manual"><span class="ezev-badge-dot"></span>' . esc_html(ucfirst($mode)) . '</span>';
}

/**
 * Render Operational Status Badge
 */
function ezev_render_status_badge(string $status = 'active'): string {
    $status = strtolower($status);
    switch ($status) {
        case 'active':
        case 'available':
            return '<span class="ezev-badge ezev-badge-available"><span class="ezev-badge-dot"></span>Available</span>';
        case 'in_use':
        case 'charging':
            return '<span class="ezev-badge ezev-badge-charging"><span class="ezev-badge-dot"></span>In Use</span>';
        case 'maintenance':
            return '<span class="ezev-badge ezev-badge-maintenance"><span class="ezev-badge-dot"></span>Maintenance</span>';
        case 'offline':
        default:
            return '<span class="ezev-badge ezev-badge-offline"><span class="ezev-badge-dot"></span>Offline</span>';
    }
}

/**
 * Server-side resolver: Resolve station_id and station array from slug or current post
 */
function ezev_resolve_current_station(): ?array {
    $station_id = null;

    if (is_singular('ezev_station')) {
        $post_id = get_the_ID();
        $station_id = (string) get_post_meta($post_id, '_ezev_station_id', true);
    } else {
        $slug = (string) get_query_var('ezev_station_slug', '');
        if (!$slug) {
            $slug = (string) get_query_var('name', '');
        }
        if ($slug && class_exists('EZEV_Core_Stations')) {
            $station_id = EZEV_Core_Stations::get_station_id_by_slug($slug);
        }
    }

    if ($station_id && class_exists('EZEV_Core_Stations')) {
        $post_id = EZEV_Core_Stations::find_by_station_id($station_id);
        if ($post_id && get_post_status($post_id) === 'publish') {
            return EZEV_Core_Stations::to_domain_array($post_id);
        }
    }

    return null;
}