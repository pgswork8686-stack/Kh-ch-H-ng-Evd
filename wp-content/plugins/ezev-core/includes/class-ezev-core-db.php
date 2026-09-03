<?php
if (!defined('ABSPATH')) { exit; }

final class EZEV_Core_DB {
    public static function table(string $name): string {
        global $wpdb;
        return $wpdb->prefix . 'ezev_' . $name;
    }

    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $sql = [];
        $sql[] = "CREATE TABLE " . self::table('organizations') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            org_code VARCHAR(80) NOT NULL,
            name VARCHAR(191) NOT NULL,
            type VARCHAR(40) NOT NULL DEFAULT 'business',
            country_code VARCHAR(8) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY org_code (org_code), KEY type (type), KEY status (status)
        ) $charset;";

        $sql[] = "CREATE TABLE " . self::table('sites') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id BIGINT UNSIGNED NULL,
            site_code VARCHAR(80) NOT NULL,
            name VARCHAR(191) NOT NULL,
            address TEXT NULL,
            city VARCHAR(120) NULL,
            country_code VARCHAR(8) NULL,
            latitude DECIMAL(10,7) NULL,
            longitude DECIMAL(10,7) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY site_code (site_code), KEY organization_id (organization_id)
        ) $charset;";

        $sql[] = "CREATE TABLE " . self::table('org_members') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            role_key VARCHAR(80) NOT NULL DEFAULT 'viewer',
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY org_user (organization_id,user_id), KEY user_id (user_id), KEY role_key (role_key)
        ) $charset;";

        $sql[] = "CREATE TABLE " . self::table('member_site_access') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id BIGINT UNSIGNED NOT NULL,
            site_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY member_site (member_id,site_id)
        ) $charset;";

        $sql[] = "CREATE TABLE " . self::table('member_station_access') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id BIGINT UNSIGNED NOT NULL,
            station_post_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY member_station (member_id,station_post_id), KEY station_post_id (station_post_id)
        ) $charset;";

        $sql[] = "CREATE TABLE " . self::table('saved_stations') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            station_post_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY user_station (user_id,station_post_id), KEY station_post_id (station_post_id)
        ) $charset;";

        $sql[] = "CREATE TABLE " . self::table('invitations') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id BIGINT UNSIGNED NULL,
            email VARCHAR(191) NOT NULL,
            role_key VARCHAR(80) NOT NULL,
            token_hash VARCHAR(191) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            expires_at DATETIME NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id), KEY email (email), KEY token_hash (token_hash), KEY status (status)
        ) $charset;";

        $sql[] = "CREATE TABLE " . self::table('audit_logs') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            action VARCHAR(120) NOT NULL,
            object_type VARCHAR(80) NULL,
            object_id VARCHAR(120) NULL,
            context_json LONGTEXT NULL,
            ip_hash VARCHAR(191) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id), KEY user_id (user_id), KEY action (action), KEY created_at (created_at)
        ) $charset;";

        foreach ($sql as $statement) { dbDelta($statement); }
        update_option('ezev_core_db_version', EZEV_CORE_VERSION, false);
    }

    public static function log(string $action, string $object_type = '', string $object_id = '', array $context = []): void {
        global $wpdb;
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $wpdb->insert(self::table('audit_logs'), [
            'user_id' => get_current_user_id() ?: null,
            'action' => sanitize_key($action),
            'object_type' => sanitize_key($object_type),
            'object_id' => sanitize_text_field($object_id),
            'context_json' => wp_json_encode($context),
            'ip_hash' => $ip ? hash_hmac('sha256', $ip, wp_salt('auth')) : null,
            'created_at' => current_time('mysql', true),
        ]);
    }
}
