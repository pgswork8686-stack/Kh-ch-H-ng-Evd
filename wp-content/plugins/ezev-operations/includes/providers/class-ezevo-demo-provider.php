<?php
if (!defined('ABSPATH')) { exit; }

final class EZEV_Operations_Demo_Provider implements EZEV_Operations_Provider {
    public function key(): string { return 'demo'; }
    public function label(): string { return 'Demo / Simulation Provider'; }
    public function mode(): string { return 'demo'; }
    public function test_connection(): array { return ['ok' => true, 'message' => 'Demo provider simulates operational charging and energy data for testing.']; }

    public function fetch_chargers(): array {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM " . EZEV_Operations_DB::table('chargers') . " WHERE provider='demo' OR provider='manual' ORDER BY station_id, charger_id", ARRAY_A) ?: [];
    }

    public function fetch_connectors(): array {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM " . EZEV_Operations_DB::table('connectors') . " ORDER BY station_id, charger_id, connector_id", ARRAY_A) ?: [];
    }

    public function fetch_sessions(): array {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM " . EZEV_Operations_DB::table('sessions') . " WHERE provider='demo' OR provider='manual' ORDER BY started_at DESC LIMIT 500", ARRAY_A) ?: [];
    }

    public function fetch_energy(): array {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM " . EZEV_Operations_DB::table('energy') . " WHERE provider='demo' OR provider='manual' ORDER BY recorded_at DESC LIMIT 1000", ARRAY_A) ?: [];
    }

    public function fetch_alerts(): array {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM " . EZEV_Operations_DB::table('alerts') . " ORDER BY occurred_at DESC LIMIT 500", ARRAY_A) ?: [];
    }
}
