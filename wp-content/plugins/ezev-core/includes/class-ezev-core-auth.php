<?php
if (!defined('ABSPATH')) { exit; }

final class EZEV_Core_Auth {
    public static function init(): void {
        add_filter('login_redirect', [self::class, 'login_redirect'], 10, 3);
        add_action('admin_init', [self::class, 'protect_wp_admin']);
    }

    public static function login_redirect(string $redirect_to, string $requested, $user): string {
        if (!($user instanceof WP_User) || is_wp_error($user)) { return $redirect_to; }
        return self::destination_for_user($user);
    }

    public static function destination_for_user(WP_User $user): string {
        $roles = (array) $user->roles;
        if (user_can($user, 'manage_options')) { return admin_url(); }
        if (array_intersect($roles, ['ezev_customer'])) { return home_url('/account/'); }
        if (array_intersect($roles, ['ezev_business'])) { return home_url('/business/'); }
        if (array_intersect($roles, ['ezev_partner','ezev_investor'])) { return home_url('/partner/'); }
        if (array_filter($roles, static fn($r) => str_starts_with($r, 'ezev_internal_'))) { return home_url('/internal/'); }
        return home_url('/account/');
    }

    public static function protect_wp_admin(): void {
        if (!is_admin() || wp_doing_ajax() || !is_user_logged_in()) { return; }
        if (!current_user_can('manage_options')) {
            wp_safe_redirect(self::destination_for_user(wp_get_current_user()));
            exit;
        }
    }

    public static function user_access(int $user_id): array {
        global $wpdb;
        $members = $wpdb->get_results($wpdb->prepare(
            "SELECT m.membership_id, m.organization_ref AS organization_id, m.user_id, m.role_key, m.status,
                    o.name AS organization_name, o.type AS organization_type
             FROM " . EZEV_Core_DB::table('org_members') . " m
             INNER JOIN " . EZEV_Core_DB::table('organizations') . " o ON o.organization_id=m.organization_ref
             WHERE m.user_id=%d AND m.status='active'", $user_id
        ), ARRAY_A) ?: [];
        foreach ($members as &$member) {
            $membership_id = (string) $member['membership_id'];
            $member['site_ids'] = array_values(array_filter(array_map('strval', $wpdb->get_col($wpdb->prepare(
                "SELECT site_ref FROM " . EZEV_Core_DB::table('member_site_access') . " WHERE membership_ref=%s", $membership_id
            )) ?: [])));
            $member['station_ids'] = array_values(array_filter(array_map('strval', $wpdb->get_col($wpdb->prepare(
                "SELECT station_id FROM " . EZEV_Core_DB::table('member_station_access') . " WHERE membership_ref=%s", $membership_id
            )) ?: [])));
        }
        unset($member);
        return $members;
    }

    public static function allowed_station_post_ids(int $user_id): array {
        // Only manage_options or explicit ezev_view_all_stations cap grants all-station scope.
        // ezev_view_internal is a portal-access cap only — it does NOT bypass resource scope.
        if (user_can($user_id, 'manage_options') || user_can($user_id, 'ezev_view_all_stations')) {
            return array_map(static fn($s) => (int) $s['post_id'], EZEV_Core_Stations::list());
        }
        $access = self::user_access($user_id);
        $allowed = [];
        global $wpdb;
        foreach ($access as $membership) {
            if (in_array($membership['role_key'], ['owner','admin'], true) && empty($membership['site_ids']) && empty($membership['station_ids'])) {
                $org_id = (string) $membership['organization_id'];
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
            foreach ($membership['station_ids'] as $station_id) {
                $post_id = EZEV_Core_Stations::find_by_station_id($station_id);
                if ($post_id) { $allowed[] = $post_id; }
            }
        }
        return array_values(array_unique(array_map('intval', $allowed)));
    }

    /** @deprecated Internal compatibility alias; public contracts must use allowed_station_keys(). */
    public static function allowed_station_ids(int $user_id): array {
        return self::allowed_station_post_ids($user_id);
    }

    public static function allowed_station_keys(int $user_id): array {
        return array_values(array_filter(array_map(static function (int $post_id): string {
            $station = EZEV_Core_Stations::to_array($post_id);
            return (string) ($station['station_id'] ?? '');
        }, self::allowed_station_post_ids($user_id))));
    }

    public static function can_access_station(int $user_id, string $station_id, string $action = 'read'): bool {
        if ($user_id <= 0) { return false; }
        if (user_can($user_id, 'manage_options')) { return true; }
        $station_id = EZEV_Core_Domain::normalize_id($station_id);
        if (!in_array($station_id, self::allowed_station_keys($user_id), true)) { return false; }
        if ($action === 'read') { return true; }
        foreach (self::user_access($user_id) as $membership) {
            if (!in_array($membership['role_key'], ['owner', 'admin', 'operations', 'site_manager'], true)) { continue; }
            if (in_array($station_id, $membership['station_ids'], true)) { return true; }
            $post_id = EZEV_Core_Stations::find_by_station_id($station_id);
            $station = $post_id ? EZEV_Core_Stations::to_array($post_id) : [];
            if (!empty($station['site_id']) && in_array($station['site_id'], $membership['site_ids'], true)) { return true; }
            if (in_array($membership['role_key'], ['owner', 'admin'], true) && ($station['organization_id'] ?? '') === $membership['organization_id']) { return true; }
        }
        return false;
    }

    /**
     * GATE 3.1: Reusable Core Authorizer
     */
    public static function user_organization_ids(int $user_id): array {
        if ($user_id <= 0) return [];
        $access = self::user_access($user_id);
        $orgs = [];
        foreach ($access as $m) {
            if (!empty($m['organization_id'])) {
                $orgs[] = (string) $m['organization_id'];
            }
        }
        return array_values(array_unique($orgs));
    }

    public static function can_read_organization(int $user_id, string $org_ref): bool {
        if ($user_id <= 0) return false;
        if (user_can($user_id, 'manage_options') || user_can($user_id, 'ezev_view_all_stations')) {
            return true;
        }
        $org_ref = EZEV_Core_Domain::normalize_id($org_ref);
        return in_array($org_ref, self::user_organization_ids($user_id), true);
    }

    public static function can_manage_organization(int $user_id, string $org_ref): bool {
        if ($user_id <= 0) return false;
        if (user_can($user_id, 'manage_options') || user_can($user_id, 'ezev_manage_organizations')) {
            return true;
        }
        $org_ref = EZEV_Core_Domain::normalize_id($org_ref);
        foreach (self::user_access($user_id) as $m) {
            if (($m['organization_id'] ?? '') === $org_ref && in_array($m['role_key'] ?? '', ['owner', 'admin'], true)) {
                return true;
            }
        }
        return false;
    }

    public static function user_allowed_site_ids(int $user_id): ?array {
        if ($user_id <= 0) return [];
        if (user_can($user_id, 'manage_options') || user_can($user_id, 'ezev_view_all_stations')) {
            return null; // All sites
        }
        global $wpdb;
        $sites = [];
        $sitesTable = EZEV_Core_DB::table('sites');
        foreach (self::user_access($user_id) as $m) {
            $org_ref = (string) ($m['organization_id'] ?? '');
            if (in_array($m['role_key'], ['owner', 'admin'], true) && empty($m['site_ids'])) {
                // Org owner/admin without explicit site filter gets all sites in organization
                $allOrgSites = $wpdb->get_col($wpdb->prepare("SELECT site_id FROM $sitesTable WHERE organization_ref = %s", $org_ref));
                $sites = array_merge($sites, $allOrgSites ?: []);
            }
            if (!empty($m['site_ids'])) {
                $sites = array_merge($sites, $m['site_ids']);
            }
        }
        return array_values(array_unique(array_filter($sites)));
    }

    public static function can_read_site(int $user_id, string $site_ref): bool {
        if ($user_id <= 0) return false;
        $allowed = self::user_allowed_site_ids($user_id);
        if ($allowed === null) return true;
        $site_ref = EZEV_Core_Domain::normalize_id($site_ref);
        return in_array($site_ref, $allowed, true);
    }

    public static function can_manage_site(int $user_id, string $site_ref): bool {
        if ($user_id <= 0) return false;
        if (user_can($user_id, 'manage_options') || user_can($user_id, 'ezev_manage_organizations')) {
            return true;
        }
        $site = EZEV_Core_Domain::site_by_id($site_ref);
        if (!$site) return false;
        $org_ref = (string) ($site['organization_ref'] ?? '');
        if (self::can_manage_organization($user_id, $org_ref)) {
            return true;
        }
        $site_ref = EZEV_Core_Domain::normalize_id($site_ref);
        foreach (self::user_access($user_id) as $m) {
            if ($m['role_key'] === 'site_manager' && in_array($site_ref, $m['site_ids'], true)) {
                return true;
            }
        }
        return false;
    }

    public static function can_manage_membership(int $user_id, string $org_ref): bool {
        if ($user_id <= 0) return false;
        if (user_can($user_id, 'manage_options') || user_can($user_id, 'ezev_manage_access')) {
            return true;
        }
        return self::can_manage_organization($user_id, $org_ref);
    }
}
