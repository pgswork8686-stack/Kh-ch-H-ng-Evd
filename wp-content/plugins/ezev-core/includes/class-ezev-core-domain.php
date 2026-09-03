<?php
if (!defined('ABSPATH')) { exit; }

/** Stable business identifiers and Core aggregate lookups. */
final class EZEV_Core_Domain {
    public static function normalize_id(string $value): string {
        $value = strtoupper(trim(sanitize_text_field($value)));
        $value = preg_replace('/[^A-Z0-9._-]+/', '-', $value) ?: '';
        return trim($value, '-');
    }

    public static function new_id(string $kind): string {
        $prefixes = [
            'organization' => 'EZEV-ORG',
            'site' => 'EZEV-SITE',
            'membership' => 'EZEV-MEM',
            'station' => 'EZEV-STN',
            'invitation' => 'EZEV-INV',
        ];
        $prefix = $prefixes[$kind] ?? 'EZEV-ID';
        return $prefix . '-' . strtoupper(wp_generate_password(12, false, false));
    }

    public static function organization_by_id(string $organization_id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . EZEV_Core_DB::table('organizations') . ' WHERE organization_id=%s',
            self::normalize_id($organization_id)
        ), ARRAY_A);
        return $row ?: null;
    }

    public static function site_by_id(string $site_id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . EZEV_Core_DB::table('sites') . ' WHERE site_id=%s',
            self::normalize_id($site_id)
        ), ARRAY_A);
        return $row ?: null;
    }

    public static function membership_by_id(string $membership_id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . EZEV_Core_DB::table('org_members') . ' WHERE membership_id=%s',
            self::normalize_id($membership_id)
        ), ARRAY_A);
        return $row ?: null;
    }
}
