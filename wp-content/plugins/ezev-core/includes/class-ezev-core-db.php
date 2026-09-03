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
            organization_id VARCHAR(100) NULL,
            org_code VARCHAR(80) NOT NULL,
            name VARCHAR(191) NOT NULL,
            type VARCHAR(40) NOT NULL DEFAULT 'business',
            country_code VARCHAR(8) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY organization_id (organization_id), UNIQUE KEY org_code (org_code), KEY type (type), KEY status (status)
        ) $charset;";

        $sql[] = "CREATE TABLE " . self::table('sites') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id BIGINT UNSIGNED NULL,
            organization_ref VARCHAR(100) NULL,
            site_id VARCHAR(100) NULL,
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
            PRIMARY KEY (id), UNIQUE KEY site_id (site_id), UNIQUE KEY site_code (site_code), KEY organization_id (organization_id), KEY organization_ref (organization_ref)
        ) $charset;";

        $sql[] = "CREATE TABLE " . self::table('org_members') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id BIGINT UNSIGNED NOT NULL,
            organization_ref VARCHAR(100) NULL,
            membership_id VARCHAR(100) NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            role_key VARCHAR(80) NOT NULL DEFAULT 'viewer',
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY membership_id (membership_id), UNIQUE KEY org_user (organization_id,user_id), KEY organization_ref (organization_ref), KEY user_id (user_id), KEY role_key (role_key)
        ) $charset;";

        $sql[] = "CREATE TABLE " . self::table('member_site_access') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id BIGINT UNSIGNED NOT NULL,
            site_id BIGINT UNSIGNED NOT NULL,
            membership_ref VARCHAR(100) NULL,
            site_ref VARCHAR(100) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY member_site (member_id,site_id), KEY membership_ref (membership_ref), KEY site_ref (site_ref)
        ) $charset;";

        $sql[] = "CREATE TABLE " . self::table('member_station_access') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id BIGINT UNSIGNED NOT NULL,
            station_post_id BIGINT UNSIGNED NOT NULL,
            membership_ref VARCHAR(100) NULL,
            station_id VARCHAR(100) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY member_station (member_id,station_post_id), KEY membership_ref (membership_ref), KEY station_id (station_id), KEY station_post_id (station_post_id)
        ) $charset;";

        $sql[] = "CREATE TABLE " . self::table('saved_stations') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            station_post_id BIGINT UNSIGNED NOT NULL,
            station_id VARCHAR(100) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY user_station (user_id,station_post_id), UNIQUE KEY user_station_key (user_id,station_id), KEY station_id (station_id), KEY station_post_id (station_post_id)
        ) $charset;";

        $sql[] = "CREATE TABLE " . self::table('invitations') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            invitation_ref VARCHAR(100) NULL,
            organization_id BIGINT UNSIGNED NULL,
            organization_ref VARCHAR(100) NULL,
            email VARCHAR(191) NOT NULL,
            role_key VARCHAR(80) NOT NULL,
            token_hash VARCHAR(191) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            expires_at DATETIME NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY invitation_ref (invitation_ref), KEY organization_ref (organization_ref), KEY email (email), KEY token_hash (token_hash), KEY status (status)
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
        self::backfill_business_ids();
        update_option('ezev_core_db_version', EZEV_CORE_DB_VERSION, false);
        EZEV_Core_Roles::install();
    }

    public static function maybe_upgrade(): void {
        $installed = (string) get_option('ezev_core_db_version', '0');
        // Explicit known legacy versions from before independent DB versioning was introduced
        $legacy_versions = ['4.0.0', '4.0.1', '4.1.0'];
        $needs_upgrade = version_compare($installed, EZEV_CORE_DB_VERSION, '<') || in_array($installed, $legacy_versions, true);
        if ($needs_upgrade) {
            self::install();
        }
    }

    private static function backfill_business_ids(): void {
        global $wpdb;
        $organizations = self::table('organizations');
        $sites = self::table('sites');
        $members = self::table('org_members');
        $member_sites = self::table('member_site_access');
        $member_stations = self::table('member_station_access');
        $saved = self::table('saved_stations');

        $wpdb->query("UPDATE $organizations SET organization_id=UPPER(org_code) WHERE organization_id IS NULL OR organization_id=''");
        $wpdb->query("UPDATE $sites SET site_id=UPPER(site_code) WHERE site_id IS NULL OR site_id=''");
        $wpdb->query("UPDATE $sites s INNER JOIN $organizations o ON o.id=s.organization_id SET s.organization_ref=o.organization_id WHERE s.organization_ref IS NULL OR s.organization_ref=''");

        $rows = $wpdb->get_results("SELECT id FROM $members WHERE membership_id IS NULL OR membership_id=''", ARRAY_A) ?: [];
        foreach ($rows as $row) {
            $wpdb->update($members, ['membership_id' => EZEV_Core_Domain::new_id('membership')], ['id' => (int) $row['id']], ['%s'], ['%d']);
        }
        $wpdb->query("UPDATE $members m INNER JOIN $organizations o ON o.id=m.organization_id SET m.organization_ref=o.organization_id WHERE m.organization_ref IS NULL OR m.organization_ref=''");
        $wpdb->query("UPDATE $member_sites ms INNER JOIN $members m ON m.id=ms.member_id INNER JOIN $sites s ON s.id=ms.site_id SET ms.membership_ref=m.membership_id, ms.site_ref=s.site_id WHERE ms.membership_ref IS NULL OR ms.membership_ref='' OR ms.site_ref IS NULL OR ms.site_ref=''");

        $station_posts = $wpdb->posts;
        $postmeta = $wpdb->postmeta;
        $wpdb->query($wpdb->prepare(
            "UPDATE $member_stations msa INNER JOIN $station_posts p ON p.ID=msa.station_post_id INNER JOIN $postmeta pm ON pm.post_id=p.ID AND pm.meta_key=%s SET msa.station_id=pm.meta_value WHERE msa.station_id IS NULL OR msa.station_id=''",
            '_ezev_station_id'
        ));
        $wpdb->query("UPDATE $member_stations msa INNER JOIN $members m ON m.id=msa.member_id SET msa.membership_ref=m.membership_id WHERE msa.membership_ref IS NULL OR msa.membership_ref=''");
        $wpdb->query($wpdb->prepare(
            "UPDATE $saved ss INNER JOIN $station_posts p ON p.ID=ss.station_post_id INNER JOIN $postmeta pm ON pm.post_id=p.ID AND pm.meta_key=%s SET ss.station_id=pm.meta_value WHERE ss.station_id IS NULL OR ss.station_id=''",
            '_ezev_station_id'
        ));

        $inv_table = self::table('invitations');
        $has_inv_ref = (bool) $wpdb->get_var("SHOW COLUMNS FROM $inv_table LIKE 'invitation_ref'");
        if (!$has_inv_ref) {
            $wpdb->query("ALTER TABLE $inv_table ADD COLUMN invitation_ref VARCHAR(100) NULL AFTER id, ADD UNIQUE KEY invitation_ref (invitation_ref)");
        }
        $has_org_ref = (bool) $wpdb->get_var("SHOW COLUMNS FROM $inv_table LIKE 'organization_ref'");
        if (!$has_org_ref) {
            $wpdb->query("ALTER TABLE $inv_table ADD COLUMN organization_ref VARCHAR(100) NULL AFTER organization_id, ADD KEY organization_ref (organization_ref)");
        }
        $wpdb->query("UPDATE $inv_table i INNER JOIN $organizations o ON o.id=i.organization_id SET i.organization_ref=o.organization_id WHERE i.organization_ref IS NULL OR i.organization_ref=''");
        $inv_rows = $wpdb->get_results("SELECT id FROM $inv_table WHERE invitation_ref IS NULL OR invitation_ref=''", ARRAY_A) ?: [];
        foreach ($inv_rows as $irow) {
            $wpdb->update($inv_table, ['invitation_ref' => EZEV_Core_Domain::new_id('invitation')], ['id' => (int) $irow['id']], ['%s'], ['%d']);
        }

        self::migrate_station_relationship_meta();
    }

    private static function migrate_station_relationship_meta(): void {
        global $wpdb;
        $organizations = self::table('organizations');
        $sites = self::table('sites');
        $postmeta = $wpdb->postmeta;
        $org_meta = $wpdb->get_results($wpdb->prepare("SELECT meta_id,meta_value FROM $postmeta WHERE meta_key=%s AND meta_value REGEXP '^[0-9]+$'", '_ezev_organization_id'), ARRAY_A) ?: [];
        foreach ($org_meta as $meta) {
            $stable = $wpdb->get_var($wpdb->prepare("SELECT organization_id FROM $organizations WHERE id=%d", (int) $meta['meta_value']));
            if ($stable) { $wpdb->update($postmeta, ['meta_value' => $stable], ['meta_id' => (int) $meta['meta_id']], ['%s'], ['%d']); }
        }
        $site_meta = $wpdb->get_results($wpdb->prepare("SELECT meta_id,meta_value FROM $postmeta WHERE meta_key=%s AND meta_value REGEXP '^[0-9]+$'", '_ezev_site_id'), ARRAY_A) ?: [];
        foreach ($site_meta as $meta) {
            $stable = $wpdb->get_var($wpdb->prepare("SELECT site_id FROM $sites WHERE id=%d", (int) $meta['meta_value']));
            if ($stable) { $wpdb->update($postmeta, ['meta_value' => $stable], ['meta_id' => (int) $meta['meta_id']], ['%s'], ['%d']); }
        }
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
