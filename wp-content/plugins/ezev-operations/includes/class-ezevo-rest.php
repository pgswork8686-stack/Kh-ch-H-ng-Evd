<?php
if (!defined('ABSPATH')) { exit; }
final class EZEV_Operations_REST {
    public static function init(): void { add_action('rest_api_init',[self::class,'routes']); }
    public static function routes(): void {
        // --- Metrics / Reports (Accessible by Investor, Partner, Business, Ops, Admin) ---
        register_rest_route('ezev-ops/v1','/overview',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'overview'],'permission_callback'=>[self::class,'can_view_metrics']]);
        register_rest_route('ezev-ops/v1','/reports/summary',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'report_summary'],'permission_callback'=>[self::class,'can_view_metrics']]);
        register_rest_route('ezev-ops/v1','/reports/performance',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'report_performance'],'permission_callback'=>[self::class,'can_view_metrics']]);

        // --- Telemetry Collections (Partner, Business, Ops, Admin; Investor/Customer excluded) ---
        register_rest_route('ezev-ops/v1','/chargers',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'chargers'],'permission_callback'=>[self::class,'can_view_telemetry']]);
        register_rest_route('ezev-ops/v1','/chargers/(?P<charger_id>[A-Za-z0-9._-]+)',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'charger'],'permission_callback'=>[self::class,'can_view_telemetry']]);

        register_rest_route('ezev-ops/v1','/connectors',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'connectors'],'permission_callback'=>[self::class,'can_view_telemetry']]);
        register_rest_route('ezev-ops/v1','/connectors/(?P<connector_id>[A-Za-z0-9._-]+)',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'connector'],'permission_callback'=>[self::class,'can_view_telemetry']]);

        register_rest_route('ezev-ops/v1','/sessions',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'sessions'],'permission_callback'=>[self::class,'can_view_telemetry']]);
        register_rest_route('ezev-ops/v1','/sessions/(?P<session_id>[A-Za-z0-9._-]+)',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'session'],'permission_callback'=>[self::class,'can_view_telemetry']]);

        register_rest_route('ezev-ops/v1','/energy',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'energy'],'permission_callback'=>[self::class,'can_view_telemetry']]);

        register_rest_route('ezev-ops/v1','/alerts',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'alerts'],'permission_callback'=>[self::class,'can_view_telemetry']]);
        register_rest_route('ezev-ops/v1','/alerts/(?P<alert_id>[A-Za-z0-9._-]+)',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'alert'],'permission_callback'=>[self::class,'can_view_telemetry']]);
        register_rest_route('ezev-ops/v1','/alerts/(?P<alert_id>[A-Za-z0-9._-]+)/acknowledge',['methods'=>WP_REST_Server::CREATABLE,'callback'=>[self::class,'acknowledge_alert'],'permission_callback'=>[self::class,'can_manage_alerts']]);
        register_rest_route('ezev-ops/v1','/alerts/(?P<alert_id>[A-Za-z0-9._-]+)/resolve',['methods'=>WP_REST_Server::CREATABLE,'callback'=>[self::class,'resolve_alert'],'permission_callback'=>[self::class,'can_manage_alerts']]);
        register_rest_route('ezev-ops/v1','/alerts/(?P<alert_id>[A-Za-z0-9._-]+)/create-ticket',['methods'=>WP_REST_Server::CREATABLE,'callback'=>[self::class,'create_alert_ticket'],'permission_callback'=>[self::class,'can_manage_alerts']]);

        // --- Maintenance Lifecycle (Ops, Technical, Business Manager, Admin) ---
        register_rest_route('ezev-ops/v1','/maintenance',[
            ['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'maintenance_list'],'permission_callback'=>[self::class,'can_manage_maintenance']],
            ['methods'=>WP_REST_Server::CREATABLE,'callback'=>[self::class,'create_maintenance'],'permission_callback'=>[self::class,'can_manage_maintenance']],
        ]);
        register_rest_route('ezev-ops/v1','/maintenance/(?P<ticket_id>[A-Za-z0-9._-]+)',[
            ['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'maintenance_ticket'],'permission_callback'=>[self::class,'can_manage_maintenance']],
            ['methods'=>WP_REST_Server::EDITABLE,'callback'=>[self::class,'update_maintenance'],'permission_callback'=>[self::class,'can_manage_maintenance']],
        ]);
        register_rest_route('ezev-ops/v1','/maintenance/(?P<ticket_id>[A-Za-z0-9._-]+)/transition',['methods'=>WP_REST_Server::CREATABLE,'callback'=>[self::class,'transition_maintenance'],'permission_callback'=>[self::class,'can_manage_maintenance']]);

        // --- Webhook (Provider Authentication) ---
        register_rest_route('ezev-ops/v1','/webhook/(?P<integration_id>\d+)',['methods'=>WP_REST_Server::CREATABLE,'callback'=>[self::class,'webhook'],'permission_callback'=>'__return_true']);
    }

    // Granular RBAC Permissions
    public static function can_view_metrics(): bool|WP_Error {
        if (!is_user_logged_in()) {
            return new WP_Error('rest_not_logged_in', 'Authentication required.', ['status' => 401]);
        }
        if (current_user_can('manage_options') || current_user_can('ezev_view_operations')) {
            return true;
        }
        return new WP_Error('rest_forbidden', 'Forbidden: missing operational capability.', ['status' => 403]);
    }

    public static function can_view(): bool|WP_Error {
        return self::can_view_metrics();
    }

    public static function can_view_telemetry(): bool|WP_Error {
        if (!is_user_logged_in()) {
            return new WP_Error('rest_not_logged_in', 'Authentication required.', ['status' => 401]);
        }
        if (current_user_can('manage_options')) {
            return true;
        }
        if (!current_user_can('ezev_view_operations')) {
            return new WP_Error('rest_forbidden', 'Forbidden: missing operational capability.', ['status' => 403]);
        }
        // Investor role is restricted to aggregate and performance metrics only, not raw telemetry/sessions
        $user = wp_get_current_user();
        $roles = (array) $user->roles;
        if (in_array('ezev_investor', $roles, true) && !in_array('administrator', $roles, true) && !in_array('ezev_internal_ops', $roles, true) && !in_array('ezev_internal_technical', $roles, true) && !in_array('ezev_business', $roles, true) && !in_array('ezev_partner', $roles, true)) {
            return new WP_Error('rest_forbidden_data_tier', 'Forbidden: investors only have access to aggregate performance data.', ['status' => 403]);
        }
        return true;
    }

    public static function can_manage_maintenance(): bool|WP_Error {
        if (!is_user_logged_in()) {
            return new WP_Error('rest_not_logged_in', 'Authentication required.', ['status' => 401]);
        }
        if (current_user_can('manage_options')) {
            return true;
        }
        $user = wp_get_current_user();
        $roles = (array) $user->roles;

        // Customer strictly 403
        if (in_array('ezev_customer', $roles, true) && !in_array('administrator', $roles, true)) {
            return new WP_Error('rest_forbidden', 'Forbidden: customers cannot access operations.', ['status' => 403]);
        }

        // Investor strictly 403
        if (in_array('ezev_investor', $roles, true) && !in_array('administrator', $roles, true)) {
            return new WP_Error('rest_forbidden', 'Forbidden: investors cannot perform operational mutations.', ['status' => 403]);
        }

        // Internal Ops and Technical WP roles have technical mutation privilege
        if (in_array('ezev_internal_ops', $roles, true) || in_array('ezev_internal_technical', $roles, true)) {
            return true;
        }

        // GATE 3.1: For Business and Partner roles, enforce membership role_key:
        // Must be owner, admin, operations, or site_manager. Viewer and finance are forbidden.
        if (class_exists('EZEV_Core_Auth')) {
            $access = EZEV_Core_Auth::user_access($user->ID);
            $has_mutation_role = false;
            foreach ($access as $m) {
                $rk = (string) ($m['role_key'] ?? '');
                if (in_array($rk, ['owner', 'admin', 'operations', 'site_manager'], true)) {
                    $has_mutation_role = true;
                    break;
                }
            }
            if ($has_mutation_role) {
                return true;
            }
            return new WP_Error('rest_forbidden', 'Forbidden: viewer and finance roles cannot perform operational mutations.', ['status' => 403]);
        }

        return new WP_Error('rest_forbidden', 'Forbidden: insufficient privileges for maintenance operations.', ['status' => 403]);
    }

    public static function can_manage_alerts(): bool|WP_Error {
        return self::can_manage_maintenance();
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

    public static function serialize_maintenance(array $r): array {
        $u = !empty($r['assigned_user_id']) ? get_userdata((int) $r['assigned_user_id']) : null;
        return [
            'ticket_id'          => (string) ($r['ticket_id'] ?? ''),
            'station_id'         => (string) ($r['station_id'] ?? ''),
            'charger_id'         => (string) ($r['charger_id'] ?? ''),
            'priority'           => (string) ($r['priority'] ?? 'medium'),
            'status'             => (string) ($r['status'] ?? 'open'),
            'assigned_user_id'   => !empty($r['assigned_user_id']) ? (int) $r['assigned_user_id'] : null,
            'assigned_user_name' => $u ? $u->display_name : null,
            'summary'            => (string) ($r['summary'] ?? ''),
            'details'            => (string) ($r['details'] ?? ''),
            'opened_at'          => (string) ($r['opened_at'] ?? ''),
            'updated_at'         => (string) ($r['updated_at'] ?? ''),
            'closed_at'          => !empty($r['closed_at']) ? (string) $r['closed_at'] : null,
        ];
    }

    private static function build_station_where(string $alias = ''): array {
        $allowed = self::allowed_station_keys();
        $col = $alias ? "$alias.station_id" : "station_id";
        if ($allowed === null) {
            return ["1=1", []];
        }
        if (empty($allowed)) {
            return ["1=0", []];
        }
        $escaped = implode("','", array_map('esc_sql', $allowed));
        return ["$col IN ('$escaped')", []];
    }

    private static function calculate_freshness(string $table = 'chargers', string $time_col = 'updated_at'): array {
        global $wpdb;
        $p = EZEV_Operations_Provider_Manager::active();
        $key = $p->key();
        $mode = ($key === 'demo') ? 'demo' : (($key === 'manual') ? 'manual' : 'api');

        $tableName = EZEV_Operations_DB::table($table);
        $last_updated = $wpdb->get_var("SELECT MAX($time_col) FROM $tableName");
        if (!$last_updated) {
            $last_updated = current_time('mysql', true);
        }

        $now_ts = current_time('timestamp', true);
        $up_ts = strtotime($last_updated) ?: $now_ts;
        $freshness_seconds = max(0, $now_ts - $up_ts);

        // Manual or Demo providers are NEVER considered realtime.
        // API providers are marked stale if no update within 10 minutes (600s).
        $is_stale = ($mode === 'api') && ($freshness_seconds > 600);

        return [
            'source'            => $p->label(),
            'data_source'       => $key,
            'data_mode'         => $mode,
            'last_updated'      => $last_updated,
            'fetched_at'        => current_time('mysql', true),
            'freshness_seconds' => $freshness_seconds,
            'is_stale'          => $is_stale,
        ];
    }

    private static function wrap_collection(string $legacy_key, array $serialized_rows, int $total, int $page, int $per_page, string $table = 'chargers', string $time_col = 'updated_at'): array {
        $meta = self::calculate_freshness($table, $time_col);
        return [
            $legacy_key  => $serialized_rows,
            'data'       => $serialized_rows,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => $total,
                'total_pages' => $per_page > 0 ? (int) ceil($total / $per_page) : 1,
            ],
            'meta'       => $meta,
        ];
    }

    public static function chargers(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table = EZEV_Operations_DB::table('chargers');
        [$st_clause] = self::build_station_where();
        $where = [$st_clause];
        $args = [];

        $station_id = sanitize_text_field((string) $request->get_param('station_id'));
        if ($station_id !== '') { $where[] = "station_id = %s"; $args[] = $station_id; }
        $status = sanitize_key((string) $request->get_param('status'));
        if ($status !== '') { $where[] = "status = %s"; $args[] = $status; }

        $page = max(1, absint($request->get_param('page') ?: 1));
        $per_page = max(1, min(500, absint($request->get_param('per_page') ?: 50)));
        $offset = ($page - 1) * $per_page;

        $where_sql = implode(' AND ', $where);
        $total_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
        $total = (int) (!empty($args) ? $wpdb->get_var($wpdb->prepare($total_sql, ...$args)) : $wpdb->get_var($total_sql));

        $sql = "SELECT * FROM $table WHERE $where_sql ORDER BY station_id, charger_id LIMIT %d OFFSET %d";
        $fetch_args = array_merge($args, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$fetch_args), ARRAY_A) ?: [];

        $serialized = array_map([self::class, 'serialize_charger'], $rows);
        return rest_ensure_response(self::wrap_collection('chargers', $serialized, $total, $page, $per_page));
    }

    public static function charger(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $cid = sanitize_text_field((string) $request['charger_id']);
        $table = EZEV_Operations_DB::table('chargers');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE charger_id = %s", $cid), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', 'Charger not found.', ['status' => 404]);
        }
        $allowed = self::allowed_station_keys();
        if ($allowed !== null && !in_array($row['station_id'], $allowed, true)) {
            return new WP_Error('forbidden', 'Forbidden: station not in scope.', ['status' => 403]);
        }
        return rest_ensure_response(['charger' => self::serialize_charger($row)]);
    }

    public static function connectors(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table = EZEV_Operations_DB::table('connectors');
        [$st_clause] = self::build_station_where();
        $where = [$st_clause];
        $args = [];

        $station_id = sanitize_text_field((string) $request->get_param('station_id'));
        if ($station_id !== '') { $where[] = "station_id = %s"; $args[] = $station_id; }
        $charger_id = sanitize_text_field((string) $request->get_param('charger_id'));
        if ($charger_id !== '') { $where[] = "charger_id = %s"; $args[] = $charger_id; }
        $status = sanitize_key((string) $request->get_param('status'));
        if ($status !== '') { $where[] = "status = %s"; $args[] = $status; }

        $page = max(1, absint($request->get_param('page') ?: 1));
        $per_page = max(1, min(500, absint($request->get_param('per_page') ?: 50)));
        $offset = ($page - 1) * $per_page;

        $where_sql = implode(' AND ', $where);
        $total_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
        $total = (int) (!empty($args) ? $wpdb->get_var($wpdb->prepare($total_sql, ...$args)) : $wpdb->get_var($total_sql));

        $sql = "SELECT * FROM $table WHERE $where_sql ORDER BY station_id, charger_id, connector_id LIMIT %d OFFSET %d";
        $fetch_args = array_merge($args, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$fetch_args), ARRAY_A) ?: [];

        $serialized = array_map([self::class, 'serialize_connector'], $rows);
        return rest_ensure_response(self::wrap_collection('connectors', $serialized, $total, $page, $per_page, 'connectors', 'updated_at'));
    }

    public static function connector(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $cid = sanitize_text_field((string) $request['connector_id']);
        $table = EZEV_Operations_DB::table('connectors');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE connector_id = %s", $cid), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', 'Connector not found.', ['status' => 404]);
        }
        $allowed = self::allowed_station_keys();
        if ($allowed !== null && !in_array($row['station_id'], $allowed, true)) {
            return new WP_Error('forbidden', 'Forbidden: station not in scope.', ['status' => 403]);
        }
        return rest_ensure_response(['connector' => self::serialize_connector($row)]);
    }

    public static function sessions(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table = EZEV_Operations_DB::table('sessions');
        [$st_clause] = self::build_station_where();
        $where = [$st_clause];
        $args = [];

        $station_id = sanitize_text_field((string) $request->get_param('station_id'));
        if ($station_id !== '') { $where[] = "station_id = %s"; $args[] = $station_id; }
        $from = sanitize_text_field((string) $request->get_param('from_date'));
        if ($from !== '') { $where[] = "started_at >= %s"; $args[] = $from; }
        $to = sanitize_text_field((string) $request->get_param('to_date'));
        if ($to !== '') { $where[] = "started_at <= %s"; $args[] = $to; }

        $page = max(1, absint($request->get_param('page') ?: 1));
        $per_page = max(1, min(500, absint($request->get_param('per_page') ?: 50)));
        $offset = ($page - 1) * $per_page;

        $where_sql = implode(' AND ', $where);
        $total_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
        $total = (int) (!empty($args) ? $wpdb->get_var($wpdb->prepare($total_sql, ...$args)) : $wpdb->get_var($total_sql));

        $sql = "SELECT * FROM $table WHERE $where_sql ORDER BY started_at DESC LIMIT %d OFFSET %d";
        $fetch_args = array_merge($args, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$fetch_args), ARRAY_A) ?: [];

        $serialized = array_map([self::class, 'serialize_session'], $rows);
        return rest_ensure_response(self::wrap_collection('sessions', $serialized, $total, $page, $per_page, 'sessions', 'started_at'));
    }

    public static function session(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $sid = sanitize_text_field((string) $request['session_id']);
        $table = EZEV_Operations_DB::table('sessions');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE session_id = %s", $sid), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', 'Session not found.', ['status' => 404]);
        }
        $allowed = self::allowed_station_keys();
        if ($allowed !== null && !in_array($row['station_id'], $allowed, true)) {
            return new WP_Error('forbidden', 'Forbidden: station not in scope.', ['status' => 403]);
        }
        return rest_ensure_response(['session' => self::serialize_session($row)]);
    }

    public static function energy(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table = EZEV_Operations_DB::table('energy');
        [$st_clause] = self::build_station_where();
        $where = [$st_clause];
        $args = [];

        $station_id = sanitize_text_field((string) $request->get_param('station_id'));
        if ($station_id !== '') { $where[] = "station_id = %s"; $args[] = $station_id; }
        $from = sanitize_text_field((string) $request->get_param('from_date'));
        if ($from !== '') { $where[] = "recorded_at >= %s"; $args[] = $from; }
        $to = sanitize_text_field((string) $request->get_param('to_date'));
        if ($to !== '') { $where[] = "recorded_at <= %s"; $args[] = $to; }

        $page = max(1, absint($request->get_param('page') ?: 1));
        $per_page = max(1, min(500, absint($request->get_param('per_page') ?: 50)));
        $offset = ($page - 1) * $per_page;

        $where_sql = implode(' AND ', $where);
        $total_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
        $total = (int) (!empty($args) ? $wpdb->get_var($wpdb->prepare($total_sql, ...$args)) : $wpdb->get_var($total_sql));

        $sql = "SELECT * FROM $table WHERE $where_sql ORDER BY recorded_at DESC LIMIT %d OFFSET %d";
        $fetch_args = array_merge($args, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$fetch_args), ARRAY_A) ?: [];

        $serialized = array_map([self::class, 'serialize_energy'], $rows);
        return rest_ensure_response(self::wrap_collection('energy', $serialized, $total, $page, $per_page, 'energy', 'recorded_at'));
    }

    public static function alerts(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table = EZEV_Operations_DB::table('alerts');
        [$st_clause] = self::build_station_where();
        $where = [$st_clause];
        $args = [];

        $station_id = sanitize_text_field((string) $request->get_param('station_id'));
        if ($station_id !== '') { $where[] = "station_id = %s"; $args[] = $station_id; }
        $status = sanitize_key((string) $request->get_param('status'));
        if ($status !== '') { $where[] = "status = %s"; $args[] = $status; }

        $page = max(1, absint($request->get_param('page') ?: 1));
        $per_page = max(1, min(500, absint($request->get_param('per_page') ?: 50)));
        $offset = ($page - 1) * $per_page;

        $where_sql = implode(' AND ', $where);
        $total_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
        $total = (int) (!empty($args) ? $wpdb->get_var($wpdb->prepare($total_sql, ...$args)) : $wpdb->get_var($total_sql));

        $sql = "SELECT * FROM $table WHERE $where_sql ORDER BY occurred_at DESC LIMIT %d OFFSET %d";
        $fetch_args = array_merge($args, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$fetch_args), ARRAY_A) ?: [];

        $serialized = array_map([self::class, 'serialize_alert'], $rows);
        return rest_ensure_response(self::wrap_collection('alerts', $serialized, $total, $page, $per_page, 'alerts', 'occurred_at'));
    }

    public static function alert(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $aid = sanitize_text_field((string) $request['alert_id']);
        $table = EZEV_Operations_DB::table('alerts');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE alert_id = %s", $aid), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', 'Alert not found.', ['status' => 404]);
        }
        $allowed = self::allowed_station_keys();
        if ($allowed !== null && !in_array($row['station_id'], $allowed, true)) {
            return new WP_Error('forbidden', 'Forbidden: station not in scope.', ['status' => 403]);
        }
        return rest_ensure_response(['alert' => self::serialize_alert($row)]);
    }

    public static function acknowledge_alert(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $aid = sanitize_text_field((string) $request['alert_id']);
        $table = EZEV_Operations_DB::table('alerts');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE alert_id = %s", $aid), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', 'Alert not found.', ['status' => 404]);
        }
        $now = current_time('mysql', true);
        $wpdb->update($table, ['status' => 'acknowledged', 'acknowledged_at' => $now], ['alert_id' => $aid]);
        EZEV_Operations_DB::log('alert_acknowledged', 'Alert ' . $aid . ' acknowledged by user ' . get_current_user_id(), 'info', null, ['alert_id' => $aid]);
        $updated = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE alert_id = %s", $aid), ARRAY_A);
        return rest_ensure_response(['alert' => self::serialize_alert($updated ?: [])]);
    }

    public static function resolve_alert(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $aid = sanitize_text_field((string) $request['alert_id']);
        $table = EZEV_Operations_DB::table('alerts');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE alert_id = %s", $aid), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', 'Alert not found.', ['status' => 404]);
        }
        $now = current_time('mysql', true);
        $wpdb->update($table, ['status' => 'resolved', 'resolved_at' => $now], ['alert_id' => $aid]);
        EZEV_Operations_DB::log('alert_resolved', 'Alert ' . $aid . ' resolved by user ' . get_current_user_id(), 'info', null, ['alert_id' => $aid]);
        $updated = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE alert_id = %s", $aid), ARRAY_A);
        return rest_ensure_response(['alert' => self::serialize_alert($updated ?: [])]);
    }

    public static function create_alert_ticket(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $aid = sanitize_text_field((string) $request['alert_id']);
        $table = EZEV_Operations_DB::table('alerts');
        $alert = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE alert_id = %s", $aid), ARRAY_A);
        if (!$alert) {
            return new WP_Error('not_found', 'Alert not found.', ['status' => 404]);
        }
        $priority = $alert['severity'] === 'critical' ? 'critical' : ($alert['severity'] === 'high' ? 'high' : 'medium');
        $ticket_id = 'TKT-ALT-' . wp_rand(10000, 99999);
        $now = current_time('mysql', true);
        $maintTable = EZEV_Operations_DB::table('maintenance');
        $wpdb->insert($maintTable, [
            'ticket_id'        => $ticket_id,
            'station_id'       => $alert['station_id'],
            'charger_id'       => $alert['charger_id'],
            'priority'         => $priority,
            'status'           => 'open',
            'assigned_user_id' => get_current_user_id(),
            'summary'          => 'Escalated from Alert ' . $aid . ': ' . $alert['title'],
            'details'          => (string) $alert['message'],
            'opened_at'        => $now,
            'updated_at'       => $now,
        ]);
        // Also mark alert acknowledged
        $wpdb->update($table, ['status' => 'acknowledged', 'acknowledged_at' => $now], ['alert_id' => $aid]);
        $ticket = $wpdb->get_row($wpdb->prepare("SELECT * FROM $maintTable WHERE ticket_id = %s", $ticket_id), ARRAY_A);
        return new WP_REST_Response(['ticket' => self::serialize_maintenance($ticket ?: [])], 201);
    }

    // --- Maintenance Lifecycle Handlers ---
    public static function maintenance_list(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table = EZEV_Operations_DB::table('maintenance');
        [$st_clause] = self::build_station_where();
        $where = [$st_clause];
        $args = [];

        $station_id = sanitize_text_field((string) $request->get_param('station_id'));
        if ($station_id !== '') { $where[] = "station_id = %s"; $args[] = $station_id; }
        $status = sanitize_key((string) $request->get_param('status'));
        if ($status !== '') { $where[] = "status = %s"; $args[] = $status; }
        $priority = sanitize_key((string) $request->get_param('priority'));
        if ($priority !== '') { $where[] = "priority = %s"; $args[] = $priority; }

        $page = max(1, absint($request->get_param('page') ?: 1));
        $per_page = max(1, min(500, absint($request->get_param('per_page') ?: 50)));
        $offset = ($page - 1) * $per_page;

        $where_sql = implode(' AND ', $where);
        $total_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
        $total = (int) (!empty($args) ? $wpdb->get_var($wpdb->prepare($total_sql, ...$args)) : $wpdb->get_var($total_sql));

        $sql = "SELECT * FROM $table WHERE $where_sql ORDER BY opened_at DESC LIMIT %d OFFSET %d";
        $fetch_args = array_merge($args, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$fetch_args), ARRAY_A) ?: [];

        $serialized = array_map([self::class, 'serialize_maintenance'], $rows);
        return rest_ensure_response(self::wrap_collection('maintenance', $serialized, $total, $page, $per_page, 'maintenance', 'updated_at'));
    }

    public static function maintenance_ticket(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $tid = sanitize_text_field((string) $request['ticket_id']);
        $table = EZEV_Operations_DB::table('maintenance');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE ticket_id = %s", $tid), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', 'Maintenance ticket not found.', ['status' => 404]);
        }
        $allowed = self::allowed_station_keys();
        if ($allowed !== null && !in_array($row['station_id'], $allowed, true)) {
            return new WP_Error('forbidden', 'Forbidden: station not in scope.', ['status' => 403]);
        }
        return rest_ensure_response(['ticket' => self::serialize_maintenance($row)]);
    }

    public static function create_maintenance(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $body = (array) $request->get_json_params();
        $station_id = sanitize_text_field((string) ($body['station_id'] ?? ''));
        $summary = sanitize_text_field((string) ($body['summary'] ?? ''));
        if ($station_id === '' || $summary === '') {
            return new WP_Error('invalid_data', 'station_id and summary are required.', ['status' => 400]);
        }
        $allowed = self::allowed_station_keys();
        if ($allowed !== null && !in_array($station_id, $allowed, true)) {
            return new WP_Error('forbidden', 'Forbidden: station not in scope.', ['status' => 403]);
        }
        $ticket_id = sanitize_text_field((string) ($body['ticket_id'] ?? 'TKT-' . wp_rand(10000, 99999)));
        $now = current_time('mysql', true);
        $table = EZEV_Operations_DB::table('maintenance');
        $wpdb->insert($table, [
            'ticket_id'        => $ticket_id,
            'station_id'       => $station_id,
            'charger_id'       => sanitize_text_field((string) ($body['charger_id'] ?? '')),
            'priority'         => sanitize_key((string) ($body['priority'] ?? 'medium')),
            'status'           => 'open',
            'assigned_user_id' => absint($body['assigned_user_id'] ?? get_current_user_id()) ?: null,
            'summary'          => $summary,
            'details'          => sanitize_textarea_field((string) ($body['details'] ?? '')),
            'opened_at'        => $now,
            'updated_at'       => $now,
        ]);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE ticket_id = %s", $ticket_id), ARRAY_A);
        return new WP_REST_Response(['ticket' => self::serialize_maintenance($row ?: [])], 201);
    }

    public static function update_maintenance(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $tid = sanitize_text_field((string) $request['ticket_id']);
        $table = EZEV_Operations_DB::table('maintenance');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE ticket_id = %s", $tid), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', 'Maintenance ticket not found.', ['status' => 404]);
        }
        $body = (array) $request->get_json_params();
        $fields = [];
        if (isset($body['summary'])) { $fields['summary'] = sanitize_text_field((string) $body['summary']); }
        if (isset($body['details'])) { $fields['details'] = sanitize_textarea_field((string) $body['details']); }
        if (isset($body['priority'])) { $fields['priority'] = sanitize_key((string) $body['priority']); }
        if (isset($body['assigned_user_id'])) { $fields['assigned_user_id'] = absint($body['assigned_user_id']) ?: null; }
        $fields['updated_at'] = current_time('mysql', true);
        $wpdb->update($table, $fields, ['ticket_id' => $tid]);
        $updated = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE ticket_id = %s", $tid), ARRAY_A);
        return rest_ensure_response(['ticket' => self::serialize_maintenance($updated ?: [])]);
    }

    public static function transition_maintenance(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $tid = sanitize_text_field((string) $request['ticket_id']);
        $table = EZEV_Operations_DB::table('maintenance');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE ticket_id = %s", $tid), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', 'Maintenance ticket not found.', ['status' => 404]);
        }
        $body = (array) $request->get_json_params();
        $new_status = sanitize_key((string) ($body['status'] ?? ''));
        $valid_transitions = [
            'open'        => ['in_progress', 'resolved', 'closed'],
            'in_progress' => ['resolved', 'closed', 'open'],
            'resolved'    => ['closed', 'in_progress'],
            'closed'      => ['open'], // re-open
        ];
        $current = $row['status'] ?: 'open';
        if (!isset($valid_transitions[$current]) || !in_array($new_status, $valid_transitions[$current], true)) {
            return new WP_Error('invalid_transition', "Cannot transition ticket from $current to $new_status.", ['status' => 422]);
        }
        $now = current_time('mysql', true);
        $fields = ['status' => $new_status, 'updated_at' => $now];
        if (in_array($new_status, ['resolved', 'closed'], true)) {
            $fields['closed_at'] = $now;
        } elseif ($new_status === 'open') {
            $fields['closed_at'] = null;
        }
        $wpdb->update($table, $fields, ['ticket_id' => $tid]);
        $updated = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE ticket_id = %s", $tid), ARRAY_A);
        return rest_ensure_response(['ticket' => self::serialize_maintenance($updated ?: [])]);
    }

    // --- Report Handlers ---
    public static function report_summary(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        [$st_clause] = self::build_station_where();
        $energy_t = EZEV_Operations_DB::table('energy');
        $sess_t = EZEV_Operations_DB::table('sessions');
        $ch_t = EZEV_Operations_DB::table('chargers');

        $from = sanitize_text_field((string) $request->get_param('from_date')) ?: gmdate('Y-m-d', strtotime('-30 days'));
        $to = sanitize_text_field((string) $request->get_param('to_date')) ?: gmdate('Y-m-d');

        $total_ev_kwh = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(ev_kwh),0) FROM $energy_t WHERE $st_clause AND DATE(recorded_at) BETWEEN %s AND %s",
            $from, $to
        ));
        $total_grid_kwh = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(grid_kwh),0) FROM $energy_t WHERE $st_clause AND DATE(recorded_at) BETWEEN %s AND %s",
            $from, $to
        ));
        $total_solar_kwh = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(solar_kwh),0) FROM $energy_t WHERE $st_clause AND DATE(recorded_at) BETWEEN %s AND %s",
            $from, $to
        ));
        $session_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sess_t WHERE $st_clause AND DATE(started_at) BETWEEN %s AND %s",
            $from, $to
        ));
        $total_chargers = (int) $wpdb->get_var("SELECT COUNT(*) FROM $ch_t WHERE $st_clause");

        return rest_ensure_response([
            'from_date'        => $from,
            'to_date'          => $to,
            'total_chargers'   => $total_chargers,
            'total_sessions'   => $session_count,
            'total_ev_kwh'     => $total_ev_kwh,
            'total_grid_kwh'   => $total_grid_kwh,
            'total_solar_kwh'  => $total_solar_kwh,
            'green_energy_pct' => $total_grid_kwh > 0 ? round(($total_solar_kwh / $total_grid_kwh) * 100, 2) : 0,
        ]);
    }

    public static function report_performance(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        [$st_clause] = self::build_station_where();
        $energy_t = EZEV_Operations_DB::table('energy');
        $sess_t = EZEV_Operations_DB::table('sessions');
        $ch_t = EZEV_Operations_DB::table('chargers');

        $rows = $wpdb->get_results(
            "SELECT e.station_id,
                    COALESCE(SUM(e.ev_kwh), 0) AS total_energy_kwh,
                    COALESCE(MAX(e.peak_kw), 0) AS max_peak_kw
             FROM $energy_t e
             WHERE $st_clause
             GROUP BY e.station_id
             ORDER BY total_energy_kwh DESC LIMIT 100", ARRAY_A
        ) ?: [];

        $stations_perf = [];
        foreach ($rows as $r) {
            $sid = (string) $r['station_id'];
            $sc = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $sess_t WHERE station_id = %s", $sid));
            $cc = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $ch_t WHERE station_id = %s", $sid));
            $stations_perf[] = [
                'station_id'       => $sid,
                'chargers_count'   => $cc,
                'sessions_count'   => $sc,
                'total_energy_kwh' => (float) $r['total_energy_kwh'],
                'peak_power_kw'    => (float) $r['max_peak_kw'],
            ];
        }

        return rest_ensure_response([
            'mode'        => 'network-performance',
            'performance' => $stations_perf,
        ]);
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

        // Atomic INSERT IGNORE into webhook_receipts — fail-closed:
        // false   = DB failure (table missing, connection error) → 503 receipt_storage_failure
        // 0       = duplicate key (INSERT IGNORE suppressed) → 409 duplicate_webhook
        // 1       = new row inserted → process webhook
        $receipt_table = EZEV_Operations_DB::table('webhook_receipts');
        $expires_at = gmdate('Y-m-d H:i:s', time() + 86400); // 24-hour TTL
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO $receipt_table (integration_id, dedup_hash, event_id, created_at, expires_at) VALUES (%d, %s, %s, %s, %s)",
            $id,
            $dedup_hash,
            $event_id ?: null,
            current_time('mysql', true),
            $expires_at
        ));

        if ($inserted === false) {
            // DB failure — fail-closed, do NOT process the webhook
            return new WP_Error('receipt_storage_failure', 'Webhook receipt storage failed. Delivery rejected.', ['status' => 503]);
        }
        if ($inserted === 0) {
            // Duplicate detected — reject with 409
            return new WP_Error('duplicate_webhook', 'Duplicate webhook delivery rejected.', ['status' => 409]);
        }

        EZEV_Operations_DB::log('webhook_received', 'Webhook verified and received from ' . $integration['name'], 'info', $id, ['payload' => json_decode($raw, true)]);
        return rest_ensure_response(['received' => true]);
    }
}
