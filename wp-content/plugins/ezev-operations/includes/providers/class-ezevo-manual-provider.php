<?php
if (!defined('ABSPATH')) { exit; }
final class EZEV_Operations_Manual_Provider implements EZEV_Operations_Provider {
    public function key(): string { return 'manual'; }
    public function label(): string { return 'Manual / Demo Provider'; }
    public function mode(): string { return 'manual'; }
    public function test_connection(): array { return ['ok'=>true,'message'=>'Manual provider uses local WordPress operations tables.']; }
    public function fetch_chargers(): array { global $wpdb; return $wpdb->get_results("SELECT * FROM ".EZEV_Operations_DB::table('chargers')." ORDER BY station_id,charger_id",ARRAY_A)?:[]; }
    public function fetch_sessions(): array { global $wpdb; return $wpdb->get_results("SELECT * FROM ".EZEV_Operations_DB::table('sessions')." ORDER BY started_at DESC LIMIT 500",ARRAY_A)?:[]; }
    public function fetch_energy(): array { global $wpdb; return $wpdb->get_results("SELECT * FROM ".EZEV_Operations_DB::table('energy')." ORDER BY recorded_at DESC LIMIT 1000",ARRAY_A)?:[]; }
    public function fetch_alerts(): array { global $wpdb; return $wpdb->get_results("SELECT * FROM ".EZEV_Operations_DB::table('alerts')." ORDER BY occurred_at DESC LIMIT 500",ARRAY_A)?:[]; }
}
