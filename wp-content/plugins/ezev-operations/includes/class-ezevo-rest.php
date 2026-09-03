<?php
if (!defined('ABSPATH')) { exit; }
final class EZEV_Operations_REST {
    public static function init(): void { add_action('rest_api_init',[self::class,'routes']); }
    public static function routes(): void {
        register_rest_route('ezev-ops/v1','/overview',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'overview'],'permission_callback'=>[self::class,'can_view']]);
        register_rest_route('ezev-ops/v1','/chargers',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'chargers'],'permission_callback'=>[self::class,'can_view']]);
        register_rest_route('ezev-ops/v1','/connectors',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'connectors'],'permission_callback'=>[self::class,'can_view']]);
        register_rest_route('ezev-ops/v1','/energy',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'energy'],'permission_callback'=>[self::class,'can_view']]);
        register_rest_route('ezev-ops/v1','/sessions',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'sessions'],'permission_callback'=>[self::class,'can_view']]);
        register_rest_route('ezev-ops/v1','/alerts',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'alerts'],'permission_callback'=>[self::class,'can_view']]);
        register_rest_route('ezev-ops/v1','/webhook/(?P<integration_id>\d+)',['methods'=>WP_REST_Server::CREATABLE,'callback'=>[self::class,'webhook'],'permission_callback'=>'__return_true']);
    }
    public static function can_view(): bool|WP_Error {
        if (!is_user_logged_in()) {
            return new WP_Error('rest_not_logged_in', 'Authentication required.', ['status' => 401]);
        }
        // ezev_view_internal alone does NOT grant Operations access.
        // Only manage_options or ezev_view_operations grants access.
        if (current_user_can('manage_options') || current_user_can('ezev_view_operations')) {
            return true;
        }
        return new WP_Error('rest_forbidden', 'Forbidden: missing operational capability.', ['status' => 403]);
    }

    private static function allowed_station_keys(): ?array {
        // Only manage_options gets unconstrained (all) access.
        if (current_user_can('manage_options')) {
            return null;
        }
        if (!class_exists('EZEV_Core_Auth') || !class_exists('EZEV_Core_Stations')) {
            return [];
        }
        // Tenant and internal operational users are scoped to their assigned stations
        return EZEV_Core_Auth::allowed_station_keys(get_current_user_id());
    }

    private static function filter_rows(array $rows): array {
        $allowed = self::allowed_station_keys();
        if ($allowed === null) return $rows;
        if (!$allowed) return [];
        return array_values(array_filter($rows, static fn($r) => isset($r['station_id']) && in_array((string)$r['station_id'], $allowed, true)));
    }

    public static function overview(): WP_REST_Response {
        global $wpdb;
        $allowed = self::allowed_station_keys();
        $station_clause = '';
        if ($allowed !== null) {
            if (empty($allowed)) {
                $station_clause = " WHERE 1=0";
            } else {
                $escaped = implode("','", array_map('esc_sql', $allowed));
                $station_clause = " WHERE station_id IN ('$escaped')";
            }
        }

        $chargers_t = EZEV_Operations_DB::table('chargers');
        $connectors_t = EZEV_Operations_DB::table('connectors');
        $sessions_t = EZEV_Operations_DB::table('sessions');
        $energy_t = EZEV_Operations_DB::table('energy');
        $alerts_t = EZEV_Operations_DB::table('alerts');
        $today = gmdate('Y-m-d');

        $c_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $chargers_t" . $station_clause);
        $c_avail = (int) $wpdb->get_var("SELECT COUNT(*) FROM $chargers_t" . ($station_clause ? $station_clause . " AND status='available'" : " WHERE status='available'"));
        $c_charge = (int) $wpdb->get_var("SELECT COUNT(*) FROM $chargers_t" . ($station_clause ? $station_clause . " AND status='charging'" : " WHERE status='charging'"));
        $c_fault = (int) $wpdb->get_var("SELECT COUNT(*) FROM $chargers_t" . ($station_clause ? $station_clause . " AND status='faulted'" : " WHERE status='faulted'"));
        $conn_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $connectors_t" . $station_clause);
        $curr_power = (float) $wpdb->get_var("SELECT COALESCE(SUM(current_power_kw),0) FROM $chargers_t" . $station_clause);
        $energy_today = (float) $wpdb->get_var("SELECT COALESCE(SUM(ev_kwh),0) FROM $energy_t" . ($station_clause ? $station_clause . " AND DATE(recorded_at)='$today'" : " WHERE DATE(recorded_at)='$today'"));
        $sessions_today = (int) $wpdb->get_var("SELECT COUNT(*) FROM $sessions_t" . ($station_clause ? $station_clause . " AND DATE(started_at)='$today'" : " WHERE DATE(started_at)='$today'"));
        $open_alerts = (int) $wpdb->get_var("SELECT COUNT(*) FROM $alerts_t" . ($station_clause ? $station_clause . " AND status='open'" : " WHERE status='open'"));

        $p = EZEV_Operations_Provider_Manager::active();
        $data = [
            'chargers_total' => $c_total,
            'chargers_available' => $c_avail,
            'chargers_charging' => $c_charge,
            'chargers_faulted' => $c_fault,
            'connectors_total' => $conn_total,
            'current_power' => $curr_power,
            'energy_today' => $energy_today,
            'sessions_today' => $sessions_today,
            'open_alerts' => $open_alerts,
        ];
        return rest_ensure_response([
            'provider' => $p->label(),
            'scope' => $allowed === null ? 'all' : 'restricted',
            'data' => $data,
        ]);
    }

    public static function serialize_charger(array $r): array {
        return [
            'charger_id' => (string) ($r['charger_id'] ?? ''),
            'station_id' => (string) ($r['station_id'] ?? ''),
            'connector_id' => (string) ($r['connector_id'] ?? ''),
            'connector_type' => (string) ($r['connector_type'] ?? 'CCS2'),
            'max_power_kw' => (float) ($r['max_power_kw'] ?? 0),
            'status' => (string) ($r['status'] ?? 'available'),
            'current_power_kw' => (float) ($r['current_power_kw'] ?? 0),
            'serial_number' => (string) ($r['serial_number'] ?? ''),
            'firmware' => (string) ($r['firmware'] ?? ''),
            'last_seen' => (string) ($r['last_seen'] ?? ''),
            'provider' => (string) ($r['provider'] ?? 'manual'),
            'updated_at' => (string) ($r['updated_at'] ?? ''),
        ];
    }

    public static function serialize_connector(array $r): array {
        return [
            'connector_id' => (string) ($r['connector_id'] ?? ''),
            'charger_id' => (string) ($r['charger_id'] ?? ''),
            'station_id' => (string) ($r['station_id'] ?? ''),
            'connector_type' => (string) ($r['connector_type'] ?? 'CCS2'),
            'max_power_kw' => (float) ($r['max_power_kw'] ?? 0),
            'status' => (string) ($r['status'] ?? 'available'),
            'current_power_kw' => (float) ($r['current_power_kw'] ?? 0),
            'last_seen' => (string) ($r['last_seen'] ?? ''),
            'provider' => (string) ($r['provider'] ?? 'manual'),
            'updated_at' => (string) ($r['updated_at'] ?? ''),
        ];
    }

    public static function serialize_session(array $r): array {
        return [
            'session_id' => (string) ($r['session_id'] ?? ''),
            'station_id' => (string) ($r['station_id'] ?? ''),
            'charger_id' => (string) ($r['charger_id'] ?? ''),
            'connector_id' => (string) ($r['connector_id'] ?? ''),
            'user_ref' => (string) ($r['user_ref'] ?? ''),
            'started_at' => (string) ($r['started_at'] ?? ''),
            'ended_at' => !empty($r['ended_at']) ? (string) $r['ended_at'] : null,
            'duration_seconds' => (int) ($r['duration_seconds'] ?? 0),
            'energy_kwh' => (float) ($r['energy_kwh'] ?? 0),
            'status' => (string) ($r['status'] ?? 'completed'),
            'provider' => (string) ($r['provider'] ?? 'manual'),
        ];
    }

    public static function serialize_energy(array $r): array {
        return [
            'station_id' => (string) ($r['station_id'] ?? ''),
            'recorded_at' => (string) ($r['recorded_at'] ?? ''),
            'grid_kwh' => (float) ($r['grid_kwh'] ?? 0),
            'ev_kwh' => (float) ($r['ev_kwh'] ?? 0),
            'solar_kwh' => (float) ($r['solar_kwh'] ?? 0),
            'bess_charge_kwh' => (float) ($r['bess_charge_kwh'] ?? 0),
            'bess_discharge_kwh' => (float) ($r['bess_discharge_kwh'] ?? 0),
            'peak_kw' => (float) ($r['peak_kw'] ?? 0),
            'provider' => (string) ($r['provider'] ?? 'manual'),
            'provider_record_id' => !empty($r['provider_record_id']) ? (string) $r['provider_record_id'] : null,
        ];
    }

    public static function serialize_alert(array $r): array {
        return [
            'alert_id' => (string) ($r['alert_id'] ?? ''),
            'station_id' => (string) ($r['station_id'] ?? ''),
            'charger_id' => (string) ($r['charger_id'] ?? ''),
            'severity' => (string) ($r['severity'] ?? 'medium'),
            'code' => (string) ($r['code'] ?? ''),
            'title' => (string) ($r['title'] ?? ''),
            'message' => (string) ($r['message'] ?? ''),
            'status' => (string) ($r['status'] ?? 'open'),
            'occurred_at' => (string) ($r['occurred_at'] ?? ''),
            'acknowledged_at' => !empty($r['acknowledged_at']) ? (string) $r['acknowledged_at'] : null,
            'resolved_at' => !empty($r['resolved_at']) ? (string) $r['resolved_at'] : null,
        ];
    }

    public static function chargers(): WP_REST_Response {
        global $wpdb;
        $allowed = self::allowed_station_keys();
        if ($allowed === null) {
            $rows = $wpdb->get_results("SELECT * FROM " . EZEV_Operations_DB::table('chargers') . " ORDER BY station_id, charger_id LIMIT 500", ARRAY_A) ?: [];
        } elseif (empty($allowed)) {
            $rows = [];
        } else {
            $escaped = implode("','", array_map('esc_sql', $allowed));
            $rows = $wpdb->get_results("SELECT * FROM " . EZEV_Operations_DB::table('chargers') . " WHERE station_id IN ('$escaped') ORDER BY station_id, charger_id LIMIT 500", ARRAY_A) ?: [];
        }
        return rest_ensure_response(['chargers' => array_map([self::class, 'serialize_charger'], $rows)]);
    }

    public static function connectors(): WP_REST_Response {
        global $wpdb;
        $allowed = self::allowed_station_keys();
        if ($allowed === null) {
            $rows = $wpdb->get_results("SELECT * FROM " . EZEV_Operations_DB::table('connectors') . " ORDER BY station_id, charger_id, connector_id LIMIT 500", ARRAY_A) ?: [];
        } elseif (empty($allowed)) {
            $rows = [];
        } else {
            $escaped = implode("','", array_map('esc_sql', $allowed));
            $rows = $wpdb->get_results("SELECT * FROM " . EZEV_Operations_DB::table('connectors') . " WHERE station_id IN ('$escaped') ORDER BY station_id, charger_id, connector_id LIMIT 500", ARRAY_A) ?: [];
        }
        return rest_ensure_response(['connectors' => array_map([self::class, 'serialize_connector'], $rows)]);
    }

    public static function sessions(): WP_REST_Response {
        global $wpdb;
        $allowed = self::allowed_station_keys();
        if ($allowed === null) {
            $rows = $wpdb->get_results("SELECT * FROM " . EZEV_Operations_DB::table('sessions') . " ORDER BY started_at DESC LIMIT 500", ARRAY_A) ?: [];
        } elseif (empty($allowed)) {
            $rows = [];
        } else {
            $escaped = implode("','", array_map('esc_sql', $allowed));
            $rows = $wpdb->get_results("SELECT * FROM " . EZEV_Operations_DB::table('sessions') . " WHERE station_id IN ('$escaped') ORDER BY started_at DESC LIMIT 500", ARRAY_A) ?: [];
        }
        return rest_ensure_response(['sessions' => array_map([self::class, 'serialize_session'], $rows)]);
    }

    public static function energy(): WP_REST_Response {
        global $wpdb;
        $allowed = self::allowed_station_keys();
        if ($allowed === null) {
            $rows = $wpdb->get_results("SELECT * FROM " . EZEV_Operations_DB::table('energy') . " ORDER BY recorded_at DESC LIMIT 500", ARRAY_A) ?: [];
        } elseif (empty($allowed)) {
            $rows = [];
        } else {
            $escaped = implode("','", array_map('esc_sql', $allowed));
            $rows = $wpdb->get_results("SELECT * FROM " . EZEV_Operations_DB::table('energy') . " WHERE station_id IN ('$escaped') ORDER BY recorded_at DESC LIMIT 500", ARRAY_A) ?: [];
        }
        return rest_ensure_response(['energy' => array_map([self::class, 'serialize_energy'], $rows)]);
    }

    public static function alerts(): WP_REST_Response {
        global $wpdb;
        $allowed = self::allowed_station_keys();
        if ($allowed === null) {
            $rows = $wpdb->get_results("SELECT * FROM " . EZEV_Operations_DB::table('alerts') . " ORDER BY occurred_at DESC LIMIT 500", ARRAY_A) ?: [];
        } elseif (empty($allowed)) {
            $rows = [];
        } else {
            $escaped = implode("','", array_map('esc_sql', $allowed));
            $rows = $wpdb->get_results("SELECT * FROM " . EZEV_Operations_DB::table('alerts') . " WHERE station_id IN ('$escaped') ORDER BY occurred_at DESC LIMIT 500", ARRAY_A) ?: [];
        }
        return rest_ensure_response(['alerts' => array_map([self::class, 'serialize_alert'], $rows)]);
    }

    public static function webhook(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $id = absint($request['integration_id']);
        $integration = EZEV_Operations_Provider_Manager::integration($id);
        if (!$integration) {
            return new WP_Error('not_found', 'Integration not found.', ['status' => 404]);
        }
        $secret = EZEV_Operations_Secrets::decrypt((string)($integration['webhook_secret_enc'] ?? ''));
        if ($secret === '') {
            return new WP_Error('missing_secret', 'Webhook secret is not configured for this integration.', ['status' => 401]);
        }
        $ts_header = (string)($request->get_header('x-ezev-timestamp') ?? '');
        if (!$ts_header || !is_numeric($ts_header)) {
            return new WP_Error('missing_timestamp', 'Missing or invalid timestamp header.', ['status' => 401]);
        }
        $ts = (int)$ts_header;
        $diff = abs(time() - $ts);
        if ($diff > 300) {
            return new WP_Error('replay_rejected', 'Webhook timestamp out of acceptable window (replay protection).', ['status' => 401]);
        }
        $raw = $request->get_body();
        $given = strtolower((string)$request->get_header('x-ezev-signature'));
        $expected = hash_hmac('sha256', $ts . '.' . $raw, $secret);
        if (!$given || !hash_equals($expected, $given)) {
            return new WP_Error('invalid_signature', 'Invalid webhook signature.', ['status' => 401]);
        }

        // True Webhook Replay Protection: Atomic deduplication scoped to integration_id + (event_id OR payload hash)
        $event_id = (string) ($request->get_header('x-ezev-event-id') ?? '');
        $dedup_key = $event_id !== '' ? 'evt_' . $event_id : 'fp_' . $ts . '_' . hash('sha256', $raw);
        $dedup_hash = hash('sha256', $id . '|' . $dedup_key);

        // Atomic insert into webhook_receipts table
        $receipt_table = EZEV_Operations_DB::table('webhook_receipts');
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO $receipt_table (integration_id, dedup_hash, event_id, created_at) VALUES (%d, %s, %s, %s)",
            $id,
            $dedup_hash,
            $event_id ?: null,
            current_time('mysql', true)
        ));

        // If duplicate key exists, INSERT IGNORE returns 0 rows affected
        if ($inserted === 0) {
            return new WP_Error('duplicate_webhook', 'Duplicate webhook delivery rejected.', ['status' => 409]);
        }

        // Fallback transient cache with 10-minute TTL for secondary fast check
        set_transient('ezevo_wh_' . substr($dedup_hash, 0, 32), 1, 600);

        EZEV_Operations_DB::log('webhook_received', 'Webhook verified and received from ' . $integration['name'], 'info', $id, ['payload' => json_decode($raw, true)]);
        return rest_ensure_response(['received' => true]);
    }
}
