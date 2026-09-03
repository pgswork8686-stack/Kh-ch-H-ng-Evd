<?php
if (!defined('ABSPATH')) { exit; }

final class EZEV_Core_Auth {
    public static function init(): void {
        add_filter('login_redirect', [self::class, 'login_redirect'], 10, 3);
        add_action('admin_init', [self::class, 'protect_wp_admin']);
    }

    public static function login_redirect(string $redirect_to, string $requested, $user): string {
        if (!($user instanceof WP_User) || is_wp_error($user)) { return $redirect_to; }
        $roles = (array) $user->roles;
        if (array_intersect($roles, ['ezev_customer'])) { return home_url('/account/'); }
        if (array_intersect($roles, ['ezev_business'])) { return home_url('/business/'); }
        if (array_intersect($roles, ['ezev_partner','ezev_investor'])) { return home_url('/partner/'); }
        if (array_filter($roles, static fn($r) => str_starts_with($r, 'ezev_internal_'))) { return home_url('/internal/'); }
        return $redirect_to;
    }

    public static function protect_wp_admin(): void {
        if (!is_admin() || wp_doing_ajax() || !is_user_logged_in()) { return; }
        $user = wp_get_current_user();
        $roles = (array) $user->roles;
        $frontend_only = ['ezev_customer','ezev_business','ezev_partner','ezev_investor'];
        if (array_intersect($roles, $frontend_only) && !current_user_can('edit_posts')) {
            wp_safe_redirect(home_url('/account/'));
            exit;
        }
    }

    public static function user_access(int $user_id): array {
        global $wpdb;
        $members = $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, o.org_code, o.name AS organization_name, o.type AS organization_type
             FROM " . EZEV_Core_DB::table('org_members') . " m
             INNER JOIN " . EZEV_Core_DB::table('organizations') . " o ON o.id=m.organization_id
             WHERE m.user_id=%d AND m.status='active'", $user_id
        ), ARRAY_A) ?: [];
        foreach ($members as &$member) {
            $member_id = (int) $member['id'];
            $member['site_ids'] = array_map('intval', $wpdb->get_col($wpdb->prepare(
                "SELECT site_id FROM " . EZEV_Core_DB::table('member_site_access') . " WHERE member_id=%d", $member_id
            )) ?: []);
            $member['station_post_ids'] = array_map('intval', $wpdb->get_col($wpdb->prepare(
                "SELECT station_post_id FROM " . EZEV_Core_DB::table('member_station_access') . " WHERE member_id=%d", $member_id
            )) ?: []);
        }
        unset($member);
        return $members;
    }

    public static function allowed_station_ids(int $user_id): array {
        if (user_can($user_id, 'manage_options') || user_can($user_id, 'ezev_view_internal')) {
            return array_map(static fn($s) => (int) $s['post_id'], EZEV_Core_Stations::list());
        }
        $access = self::user_access($user_id);
        $allowed = [];
        global $wpdb;
        foreach ($access as $membership) {
            if (in_array($membership['role_key'], ['owner','admin'], true) && empty($membership['site_ids']) && empty($membership['station_post_ids'])) {
                $org_id = (int) $membership['organization_id'];
                $ids = get_posts([
                    'post_type' => EZEV_Core_Stations::POST_TYPE,
                    'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids',
                    'meta_key' => '_ezev_organization_id', 'meta_value' => $org_id,
                ]);
                $allowed = array_merge($allowed, array_map('intval', $ids));
            }
            foreach ($membership['site_ids'] as $site_id) {
                $ids = get_posts([
                    'post_type' => EZEV_Core_Stations::POST_TYPE,
                    'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids',
                    'meta_key' => '_ezev_site_id', 'meta_value' => $site_id,
                ]);
                $allowed = array_merge($allowed, array_map('intval', $ids));
            }
            $allowed = array_merge($allowed, $membership['station_post_ids']);
        }
        return array_values(array_unique(array_map('intval', $allowed)));
    }
}
