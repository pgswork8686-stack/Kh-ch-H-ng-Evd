<?php
if (!defined('ABSPATH')) { exit; }
final class EZEV_Operations_DB {
    public static function table(string $name): string { global $wpdb; return $wpdb->prefix.'ezev_'.$name; }
    public static function install(): void {
        global $wpdb; require_once ABSPATH.'wp-admin/includes/upgrade.php'; $c=$wpdb->get_charset_collate(); $sql=[];
        $sql[]="CREATE TABLE ".self::table('chargers')." (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, charger_id VARCHAR(100) NOT NULL, station_id VARCHAR(100) NOT NULL,
            connector_id VARCHAR(80) NULL, connector_type VARCHAR(50) NOT NULL DEFAULT 'CCS2', max_power_kw DECIMAL(10,2) NOT NULL DEFAULT 0,
            status VARCHAR(40) NOT NULL DEFAULT 'available', current_power_kw DECIMAL(10,2) NOT NULL DEFAULT 0,
            serial_number VARCHAR(120) NULL, firmware VARCHAR(120) NULL, last_seen DATETIME NULL, provider VARCHAR(80) NOT NULL DEFAULT 'manual', updated_at DATETIME NOT NULL,
            PRIMARY KEY(id), UNIQUE KEY charger_id(charger_id), KEY station_id(station_id), KEY status(status)
        ) $c;";
        $sql[]="CREATE TABLE ".self::table('connectors')." (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, connector_id VARCHAR(100) NOT NULL, charger_id VARCHAR(100) NOT NULL,
            station_id VARCHAR(100) NOT NULL, connector_type VARCHAR(50) NOT NULL DEFAULT 'CCS2', max_power_kw DECIMAL(10,2) NOT NULL DEFAULT 0,
            status VARCHAR(40) NOT NULL DEFAULT 'available', current_power_kw DECIMAL(10,2) NOT NULL DEFAULT 0,
            last_seen DATETIME NULL, provider VARCHAR(80) NOT NULL DEFAULT 'manual', updated_at DATETIME NOT NULL,
            PRIMARY KEY(id), UNIQUE KEY connector_id(connector_id), KEY charger_id(charger_id), KEY station_id(station_id), KEY status(status)
        ) $c;";
        $sql[]="CREATE TABLE ".self::table('sessions')." (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, session_id VARCHAR(120) NOT NULL, station_id VARCHAR(100) NOT NULL,
            charger_id VARCHAR(100) NOT NULL, connector_id VARCHAR(100) NULL,
            user_ref VARCHAR(120) NULL, started_at DATETIME NOT NULL, ended_at DATETIME NULL, duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            energy_kwh DECIMAL(12,3) NOT NULL DEFAULT 0, status VARCHAR(40) NOT NULL DEFAULT 'completed', provider VARCHAR(80) NOT NULL DEFAULT 'manual', provider_payload LONGTEXT NULL,
            PRIMARY KEY(id), UNIQUE KEY session_id(session_id), KEY station_id(station_id), KEY charger_id(charger_id), KEY connector_id(connector_id), KEY started_at(started_at)
        ) $c;";
        $sql[]="CREATE TABLE ".self::table('energy')." (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, station_id VARCHAR(100) NOT NULL, recorded_at DATETIME NOT NULL,
            grid_kwh DECIMAL(14,3) NOT NULL DEFAULT 0, ev_kwh DECIMAL(14,3) NOT NULL DEFAULT 0, solar_kwh DECIMAL(14,3) NOT NULL DEFAULT 0,
            bess_charge_kwh DECIMAL(14,3) NOT NULL DEFAULT 0, bess_discharge_kwh DECIMAL(14,3) NOT NULL DEFAULT 0, peak_kw DECIMAL(12,3) NOT NULL DEFAULT 0,
            provider VARCHAR(80) NOT NULL DEFAULT 'manual', provider_record_id VARCHAR(120) NULL,
            PRIMARY KEY(id), UNIQUE KEY provider_station_time(provider,station_id,recorded_at), KEY station_id(station_id), KEY recorded_at(recorded_at), KEY provider_record_id(provider_record_id)
        ) $c;";
        $sql[]="CREATE TABLE ".self::table('alerts')." (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, alert_id VARCHAR(120) NOT NULL, station_id VARCHAR(100) NOT NULL, charger_id VARCHAR(100) NULL,
            severity VARCHAR(30) NOT NULL DEFAULT 'medium', code VARCHAR(80) NULL, title VARCHAR(191) NOT NULL, message TEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'open', occurred_at DATETIME NOT NULL, acknowledged_at DATETIME NULL, resolved_at DATETIME NULL,
            PRIMARY KEY(id), UNIQUE KEY alert_id(alert_id), KEY station_id(station_id), KEY severity(severity), KEY status(status)
        ) $c;";
        $sql[]="CREATE TABLE ".self::table('maintenance')." (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ticket_id VARCHAR(120) NOT NULL, station_id VARCHAR(100) NOT NULL, charger_id VARCHAR(100) NULL,
            priority VARCHAR(30) NOT NULL DEFAULT 'medium', status VARCHAR(40) NOT NULL DEFAULT 'open', assigned_user_id BIGINT UNSIGNED NULL,
            summary VARCHAR(191) NOT NULL, details TEXT NULL, opened_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, closed_at DATETIME NULL,
            PRIMARY KEY(id), UNIQUE KEY ticket_id(ticket_id), KEY station_id(station_id), KEY status(status), KEY priority(priority)
        ) $c;";
        $sql[]="CREATE TABLE ".self::table('integrations')." (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(191) NOT NULL, provider_type VARCHAR(80) NOT NULL DEFAULT 'generic_http',
            environment VARCHAR(30) NOT NULL DEFAULT 'sandbox', base_url TEXT NULL, auth_type VARCHAR(40) NOT NULL DEFAULT 'bearer', api_key_enc LONGTEXT NULL,
            api_secret_enc LONGTEXT NULL, client_id VARCHAR(191) NULL, client_secret_enc LONGTEXT NULL, webhook_secret_enc LONGTEXT NULL,
            mapping_json LONGTEXT NULL, settings_json LONGTEXT NULL, enabled TINYINT(1) NOT NULL DEFAULT 0,
            last_test_status VARCHAR(30) NULL, last_test_message TEXT NULL, last_test_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            PRIMARY KEY(id), KEY enabled(enabled), KEY provider_type(provider_type)
        ) $c;";
        $sql[]="CREATE TABLE ".self::table('sync_logs')." (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, integration_id BIGINT UNSIGNED NULL, level VARCHAR(20) NOT NULL DEFAULT 'info', event VARCHAR(120) NOT NULL,
            message TEXT NULL, context_json LONGTEXT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id), KEY integration_id(integration_id), KEY created_at(created_at), KEY level(level)
        ) $c;";
        foreach($sql as $s){dbDelta($s);}
        self::ensure_schema_columns();
        self::migrate_legacy_connectors();
        update_option('ezevo_db_version',defined('EZEVO_DB_VERSION')?EZEVO_DB_VERSION:'1.1.0',false);
    }

    private static function ensure_schema_columns(): void {
        global $wpdb;
        $sess_table = self::table('sessions');
        $has_conn = $wpdb->get_var("SHOW COLUMNS FROM $sess_table LIKE 'connector_id'");
        if (!$has_conn) {
            $wpdb->query("ALTER TABLE $sess_table ADD COLUMN connector_id VARCHAR(100) NULL AFTER charger_id, ADD INDEX connector_id(connector_id)");
        }
        $energy_table = self::table('energy');
        $has_prov_rec = $wpdb->get_var("SHOW COLUMNS FROM $energy_table LIKE 'provider_record_id'");
        if (!$has_prov_rec) {
            $wpdb->query("ALTER TABLE $energy_table ADD COLUMN provider_record_id VARCHAR(120) NULL AFTER provider, ADD INDEX provider_record_id(provider_record_id)");
        }
        $has_prov_key = $wpdb->get_var("SHOW INDEX FROM $energy_table WHERE Key_name = 'provider_station_time'");
        if (!$has_prov_key) {
            // Drop old station_time index if exists, and add provider_station_time
            $old_idx = $wpdb->get_var("SHOW INDEX FROM $energy_table WHERE Key_name = 'station_time'");
            if ($old_idx) {
                $wpdb->query("ALTER TABLE $energy_table DROP INDEX station_time");
            }
            $wpdb->query("ALTER TABLE $energy_table ADD UNIQUE KEY provider_station_time (provider, station_id, recorded_at)");
        }
    }

    public static function maybe_upgrade(): void {
        $installed = (string) get_option('ezevo_db_version', '0');
        $target = defined('EZEVO_DB_VERSION') ? EZEVO_DB_VERSION : '1.1.0';
        $legacy_versions = ['0', '1.0.0', '1.0.1', '1.0.2', '1.0.3'];
        $needs_upgrade = version_compare($installed, $target, '<') || in_array($installed, $legacy_versions, true);
        if ($needs_upgrade) {
            self::install();
        }
    }

    private static function migrate_legacy_connectors(): void {
        global $wpdb;
        $conn_table = self::table('connectors');
        $chargers_table = self::table('chargers');
        $chargers = $wpdb->get_results("SELECT * FROM $chargers_table", ARRAY_A) ?: [];
        foreach ($chargers as $c) {
            $cid = (string) ($c['connector_id'] ?: ($c['charger_id'] . '-CONN-1'));
            $wpdb->replace($conn_table, [
                'connector_id' => $cid,
                'charger_id' => (string) $c['charger_id'],
                'station_id' => (string) $c['station_id'],
                'connector_type' => (string) ($c['connector_type'] ?: 'CCS2'),
                'max_power_kw' => (float) ($c['max_power_kw'] ?: 0),
                'status' => (string) ($c['status'] ?: 'available'),
                'current_power_kw' => (float) ($c['current_power_kw'] ?: 0),
                'last_seen' => $c['last_seen'] ?: current_time('mysql', true),
                'provider' => (string) ($c['provider'] ?: 'manual'),
                'updated_at' => current_time('mysql', true),
            ]);
        }
    }

    public static function log(string $event,string $message='',string $level='info',?int $integration_id=null,array $context=[]): void { global $wpdb; $wpdb->insert(self::table('sync_logs'),['integration_id'=>$integration_id,'level'=>sanitize_key($level),'event'=>sanitize_key($event),'message'=>sanitize_textarea_field($message),'context_json'=>wp_json_encode($context),'created_at'=>current_time('mysql',true)]); }

    public static function seed_demo(): array {
        global $wpdb;
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . self::table('chargers'));
        if ($count > 0) return ['created' => 0, 'skipped' => $count];
        $stations = [];
        if (class_exists('EZEV_Core_Stations')) { $stations = EZEV_Core_Stations::list(); }
        if (!$stations) {
            for ($i = 1; $i <= 60; $i++) {
                $cc = $i <= 20 ? 'VN' : ($i <= 40 ? 'PH' : 'CN');
                $stations[] = ['station_id' => sprintf('EZEV-%s-DEMO-%03d', $cc, (($i - 1) % 20) + 1), 'max_power_kw' => 180];
            }
        }
        $now = current_time('mysql', true);
        $created_chargers = 0;
        $created_connectors = 0;
        $idx = 0;
        foreach ($stations as $s) {
            $idx++;
            $sid = (string) $s['station_id'];
            $p = (float) ($s['max_power_kw'] ?: 180);
            for ($c = 1; $c <= 2; $c++) {
                $cid = $sid . '-C' . $c;
                $ch_status = (($idx + $c) % 19 === 0) ? 'faulted' : ((($idx + $c) % 7 === 0) ? 'charging' : 'available');
                $ch_power = $ch_status === 'charging' ? round(($p / 2) * 0.62, 1) : 0;
                $wpdb->insert(self::table('chargers'), [
                    'charger_id' => $cid,
                    'station_id' => $sid,
                    'connector_id' => $cid . '-CONN-1',
                    'connector_type' => 'CCS2',
                    'max_power_kw' => $p / 2,
                    'status' => $ch_status,
                    'current_power_kw' => $ch_power,
                    'serial_number' => 'DEMO-' . str_pad((string) ($idx * 10 + $c), 6, '0', STR_PAD_LEFT),
                    'firmware' => 'demo-1.0',
                    'last_seen' => $now,
                    'provider' => 'demo',
                    'updated_at' => $now,
                ]);
                $created_chargers++;

                // Station -> Charger -> Connector
                for ($cn = 1; $cn <= 2; $cn++) {
                    $conn_id = $cid . '-CONN-' . $cn;
                    $conn_type = $cn === 1 ? 'CCS2' : 'Type 2';
                    $conn_status = $cn === 1 ? $ch_status : 'available';
                    $conn_power = $cn === 1 ? $ch_power : 0;
                    $wpdb->insert(self::table('connectors'), [
                        'connector_id' => $conn_id,
                        'charger_id' => $cid,
                        'station_id' => $sid,
                        'connector_type' => $conn_type,
                        'max_power_kw' => ($p / 2) / 2,
                        'status' => $conn_status,
                        'current_power_kw' => $conn_power,
                        'last_seen' => $now,
                        'provider' => 'demo',
                        'updated_at' => $now,
                    ]);
                    $created_connectors++;
                }
            }

            // Energy (Idempotent per station and recorded_at)
            for ($d = 6; $d >= 0; $d--) {
                $date = gmdate('Y-m-d H:i:s', strtotime('-' . $d . ' days 12:00:00'));
                $base = 35 + ($idx % 9) * 4 + $d * 1.2;
                $wpdb->replace(self::table('energy'), [
                    'station_id' => $sid,
                    'recorded_at' => $date,
                    'grid_kwh' => $base * 1.08,
                    'ev_kwh' => $base,
                    'solar_kwh' => ($idx % 4) * 3.2,
                    'bess_charge_kwh' => ($idx % 3) * 1.5,
                    'bess_discharge_kwh' => ($idx % 5) * 1.1,
                    'peak_kw' => min($p, $p * (.35 + (($idx + $d) % 6) * .08)),
                    'provider' => 'demo',
                ]);
            }

            // Sessions (Station -> Charger -> Connector -> Session)
            for ($j = 1; $j <= 3; $j++) {
                $start = gmdate('Y-m-d H:i:s', strtotime('-' . (($idx + $j) % 5) . ' days +' . (($idx * $j) % 18) . ' hours'));
                $energy = 12 + (($idx * $j) % 38);
                $charger_sel = $sid . '-C' . (($j % 2) + 1);
                $conn_sel = $charger_sel . '-CONN-1';
                $wpdb->insert(self::table('sessions'), [
                    'session_id' => $sid . '-S' . str_pad((string) $j, 2, '0', STR_PAD_LEFT),
                    'station_id' => $sid,
                    'charger_id' => $charger_sel,
                    'connector_id' => $conn_sel,
                    'user_ref' => 'DEMO-USER-' . (($idx + $j) % 30 + 1),
                    'started_at' => $start,
                    'ended_at' => gmdate('Y-m-d H:i:s', strtotime($start . ' +35 minutes')),
                    'duration_seconds' => 2100,
                    'energy_kwh' => $energy,
                    'status' => 'completed',
                    'provider' => 'demo',
                    'provider_payload' => '',
                ]);
            }

            if ($idx % 13 === 0) {
                $wpdb->insert(self::table('alerts'), [
                    'alert_id' => 'ALT-' . $idx,
                    'station_id' => $sid,
                    'charger_id' => $sid . '-C1',
                    'severity' => $idx % 26 === 0 ? 'critical' : 'high',
                    'code' => 'DEMO_COMM',
                    'title' => 'Communication warning',
                    'message' => 'Demo alert for local operations testing.',
                    'status' => 'open',
                    'occurred_at' => $now,
                ]);
            }
        }
        update_option('ezevo_active_provider', 'demo', false);
        self::log('demo_seeded', 'Seeded demo operational data for ' . count($stations) . ' stations.', 'info', null, ['chargers' => $created_chargers, 'connectors' => $created_connectors]);
        return ['created' => $created_chargers, 'connectors' => $created_connectors, 'stations' => count($stations)];
    }

    public static function overview(): array {
        global $wpdb;
        $chargers = self::table('chargers');
        $connectors = self::table('connectors');
        $sessions = self::table('sessions');
        $energy = self::table('energy');
        $alerts = self::table('alerts');
        $today = gmdate('Y-m-d');
        return [
            'chargers_total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $chargers"),
            'chargers_available' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $chargers WHERE status='available'"),
            'chargers_charging' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $chargers WHERE status='charging'"),
            'chargers_faulted' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $chargers WHERE status='faulted'"),
            'connectors_total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $connectors"),
            'energy_today' => (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(ev_kwh),0) FROM $energy WHERE DATE(recorded_at)=%s", $today)),
            'sessions_today' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $sessions WHERE DATE(started_at)=%s", $today)),
            'open_alerts' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $alerts WHERE status='open'"),
            'current_power' => (float) $wpdb->get_var("SELECT COALESCE(SUM(current_power_kw),0) FROM $chargers"),
        ];
    }
}
