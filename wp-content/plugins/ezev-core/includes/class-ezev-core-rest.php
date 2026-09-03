<?php
if (!defined('ABSPATH')) { exit; }

final class EZEV_Core_REST {
    public static function init(): void {
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function routes(): void {
        register_rest_route('ezev/v1', '/stations', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'stations'],
                'permission_callback' => '__return_true',
                'args' => ['country' => ['sanitize_callback' => 'sanitize_text_field']],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'create_station'],
                'permission_callback' => [self::class, 'can_manage_stations'],
            ],
        ]);
        register_rest_route('ezev/v1', '/stations/(?P<station_id>[A-Za-z0-9._-]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'station'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [self::class, 'update_station'],
                'permission_callback' => [self::class, 'can_manage_stations'],
            ],
        ]);
        register_rest_route('ezev/v1', '/me', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'me'],
            'permission_callback' => static fn() => is_user_logged_in(),
        ]);
        register_rest_route('ezev/v1', '/me/stations', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'my_stations'],
            'permission_callback' => [self::class, 'authenticated'],
        ]);
        register_rest_route('ezev/v1', '/me/stations/(?P<station_id>[A-Za-z0-9._-]+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'my_station'],
            'permission_callback' => [self::class, 'authenticated'],
        ]);
        register_rest_route('ezev/v1', '/saved-stations', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'saved_stations'],
                'permission_callback' => static fn() => is_user_logged_in(),
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'save_station'],
                'permission_callback' => static fn() => is_user_logged_in(),
            ],
        ]);
        register_rest_route('ezev/v1', '/saved-stations/(?P<station_id>[A-Za-z0-9._-]+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [self::class, 'remove_saved_station'],
            'permission_callback' => static fn() => is_user_logged_in(),
        ]);
        register_rest_route('ezev/v1', '/auth/login', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'login'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('ezev/v1', '/auth/logout', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'logout'],
            'permission_callback' => static fn() => is_user_logged_in(),
        ]);
    }

    public static function stations(WP_REST_Request $request): WP_REST_Response {
        $country = (string) $request->get_param('country');
        $rows = EZEV_Core_Stations::domain_list($country ? ['country_code' => $country] : []);
        return rest_ensure_response([
            'count' => count($rows),
            'mode' => 'station-master-data',
            'stations' => $rows,
        ]);
    }

    public static function can_manage_stations(): bool|WP_Error {
        if (!is_user_logged_in()) { return new WP_Error('ezev_authentication_required', 'Authentication required.', ['status' => 401]); }
        if (!current_user_can('ezev_manage_stations')) { return new WP_Error('ezev_forbidden', 'You are not allowed to manage stations.', ['status' => 403]); }
        return true;
    }

    public static function authenticated(): bool|WP_Error {
        return is_user_logged_in() ? true : new WP_Error('ezev_authentication_required', 'Authentication required.', ['status' => 401]);
    }

    public static function station(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $station_id = EZEV_Core_Domain::normalize_id((string) $request['station_id']);
        $post_id = EZEV_Core_Stations::find_by_station_id($station_id);
        if (!$post_id || get_post_status($post_id) !== 'publish') {
            return new WP_Error('ezev_station_not_found', 'Station not found.', ['status' => 404]);
        }
        return rest_ensure_response(['station' => EZEV_Core_Stations::to_domain_array($post_id)]);
    }

    public static function create_station(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $data = self::station_payload($request);
        if (is_wp_error($data)) { return $data; }
        if (EZEV_Core_Stations::find_by_station_id($data['station_id'])) {
            return new WP_Error('ezev_station_exists', 'Station ID already exists.', ['status' => 409]);
        }
        $result = EZEV_Core_Stations::create($data);
        if (is_wp_error($result)) { return $result; }
        $response = rest_ensure_response(['station' => EZEV_Core_Stations::to_domain_array((int) $result)]);
        $response->set_status(201);
        return $response;
    }

    public static function update_station(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $station_id = EZEV_Core_Domain::normalize_id((string) $request['station_id']);
        $post_id = EZEV_Core_Stations::find_by_station_id($station_id);
        if (!$post_id) { return new WP_Error('ezev_station_not_found', 'Station not found.', ['status' => 404]); }
        $data = self::station_payload($request, $station_id, EZEV_Core_Stations::to_array($post_id));
        if (is_wp_error($data)) { return $data; }
        $result = EZEV_Core_Stations::create($data);
        if (is_wp_error($result)) { return $result; }
        return rest_ensure_response(['station' => EZEV_Core_Stations::to_domain_array((int) $result)]);
    }

    private static function station_payload(WP_REST_Request $request, string $route_station_id = '', array $base = []): array|WP_Error {
        $body = $request->get_json_params();
        if (!is_array($body)) { $body = $request->get_params(); }
        $station_id = EZEV_Core_Domain::normalize_id($route_station_id ?: (string) ($body['station_id'] ?? ''));
        if ($station_id === '') { return new WP_Error('ezev_station_id_required', 'station_id is required.', ['status' => 400]); }
        if ($route_station_id !== '' && isset($body['station_id']) && EZEV_Core_Domain::normalize_id((string) $body['station_id']) !== $station_id) {
            return new WP_Error('ezev_station_id_immutable', 'station_id cannot be changed.', ['status' => 409]);
        }
        $location = is_array($body['location'] ?? null) ? $body['location'] : [];
        $address = is_array($body['address'] ?? null) ? $body['address'] : [];
        $ports = is_array($body['ports'] ?? null) ? $body['ports'] : [];
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $ownership = is_array($body['ownership'] ?? null) ? $body['ownership'] : [];
        $lat = $location['lat'] ?? $body['latitude'] ?? null;
        $lng = $location['lng'] ?? $body['longitude'] ?? null;
        if ($lat !== null && (!is_numeric($lat) || (float) $lat < -90 || (float) $lat > 90)) { return new WP_Error('ezev_invalid_latitude', 'Latitude must be between -90 and 90.', ['status' => 422]); }
        if ($lng !== null && (!is_numeric($lng) || (float) $lng < -180 || (float) $lng > 180)) { return new WP_Error('ezev_invalid_longitude', 'Longitude must be between -180 and 180.', ['status' => 422]); }
        $organization_id = EZEV_Core_Domain::normalize_id((string) ($ownership['organization_id'] ?? $body['organization_id'] ?? $base['organization_id'] ?? ''));
        $site_id = EZEV_Core_Domain::normalize_id((string) ($ownership['site_id'] ?? $body['site_id'] ?? $base['site_id'] ?? ''));
        $organization = $organization_id !== '' ? EZEV_Core_Domain::organization_by_id($organization_id) : null;
        if ($organization_id !== '' && !$organization) { return new WP_Error('ezev_invalid_organization', 'Organization not found.', ['status' => 422]); }
        $site = $site_id !== '' ? EZEV_Core_Domain::site_by_id($site_id) : null;
        if ($site_id !== '' && !$site) { return new WP_Error('ezev_invalid_site', 'Site not found.', ['status' => 422]); }
        if ($site && $organization_id !== '' && (string) $site['organization_ref'] !== $organization_id) { return new WP_Error('ezev_site_organization_mismatch', 'Site does not belong to the organization.', ['status' => 422]); }
        if ($site && $organization_id === '') { $organization_id = (string) $site['organization_ref']; }
        return [
            'station_id' => $station_id,
            'name' => sanitize_text_field((string) ($body['name'] ?? $base['name'] ?? $station_id)),
            'description' => sanitize_textarea_field((string) ($body['description'] ?? $base['description'] ?? '')),
            'address' => sanitize_text_field((string) ($address['line'] ?? $body['address_line'] ?? $base['address'] ?? '')),
            'city' => sanitize_text_field((string) ($address['city'] ?? $body['city'] ?? $base['city'] ?? '')),
            'region' => sanitize_text_field((string) ($address['region'] ?? $body['region'] ?? $base['region'] ?? '')),
            'country' => sanitize_text_field((string) ($address['country'] ?? $body['country'] ?? $base['country'] ?? '')),
            'country_code' => strtoupper(sanitize_text_field((string) ($address['country_code'] ?? $body['country_code'] ?? $base['country_code'] ?? ''))),
            'latitude' => (float) ($lat ?? $base['latitude'] ?? 0),
            'longitude' => (float) ($lng ?? $base['longitude'] ?? 0),
            'connector_types' => (array) ($body['connectors'] ?? $body['connector_types'] ?? $base['connector_types'] ?? []),
            'max_power_kw' => max(0, (float) ($body['max_power_kw'] ?? $base['max_power_kw'] ?? 0)),
            'ports_total' => max(0, (int) ($ports['total'] ?? $body['ports_total'] ?? $base['ports_total'] ?? 0)),
            'ports_available_manual' => max(0, (int) ($ports['available'] ?? $body['ports_available_manual'] ?? $base['ports_available_manual'] ?? 0)),
            'opening_hours' => sanitize_text_field((string) ($body['opening_hours'] ?? $base['opening_hours'] ?? '24/7')),
            'operational_status_manual' => sanitize_key((string) ($body['status'] ?? $body['operational_status_manual'] ?? $base['operational_status_manual'] ?? 'active')),
            'amenities' => (array) ($body['amenities'] ?? $base['amenities'] ?? []),
            'data_mode' => sanitize_key((string) ($data['mode'] ?? $body['data_mode'] ?? $base['data_mode'] ?? 'manual')),
            'is_demo' => (bool) ($data['is_demo'] ?? $body['is_demo'] ?? $base['is_demo'] ?? false),
            'organization_id' => $organization_id,
            'site_id' => $site_id,
            'public_notes' => sanitize_textarea_field((string) ($body['public_notes'] ?? $base['public_notes'] ?? '')),
            'post_status' => 'publish',
        ];
    }

    public static function me(): WP_REST_Response {
        $user = wp_get_current_user();
        return rest_ensure_response([
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'roles' => array_values($user->roles),
            'memberships' => EZEV_Core_Auth::user_access($user->ID),
            'allowed_station_ids' => EZEV_Core_Auth::allowed_station_keys($user->ID),
        ]);
    }

    public static function my_stations(): WP_REST_Response {
        $stations = [];
        foreach (EZEV_Core_Auth::allowed_station_keys(get_current_user_id()) as $station_id) {
            $post_id = EZEV_Core_Stations::find_by_station_id($station_id);
            if ($post_id && get_post_status($post_id) === 'publish') { $stations[] = EZEV_Core_Stations::to_domain_array($post_id); }
        }
        return rest_ensure_response(['count' => count($stations), 'stations' => $stations]);
    }

    public static function my_station(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $station_id = EZEV_Core_Domain::normalize_id((string) $request['station_id']);
        $post_id = EZEV_Core_Stations::find_by_station_id($station_id);
        if (!$post_id) { return new WP_Error('ezev_station_not_found', 'Station not found.', ['status' => 404]); }
        if (!EZEV_Core_Auth::can_access_station(get_current_user_id(), $station_id)) {
            return new WP_Error('ezev_station_forbidden', 'You are not allowed to access this station.', ['status' => 403]);
        }
        return rest_ensure_response(['station' => EZEV_Core_Stations::to_domain_array($post_id)]);
    }

    public static function saved_stations(): WP_REST_Response {
        global $wpdb;
        $user_id = get_current_user_id();
        $ids = array_values(array_filter(array_map('strval', $wpdb->get_col($wpdb->prepare(
            "SELECT station_id FROM " . EZEV_Core_DB::table('saved_stations') . " WHERE user_id=%d ORDER BY created_at DESC", $user_id
        )) ?: [])));
        $stations = [];
        foreach ($ids as $id) {
            $post_id = EZEV_Core_Stations::find_by_station_id($id);
            if ($post_id) { $stations[] = EZEV_Core_Stations::to_domain_array($post_id); }
        }
        return rest_ensure_response(['stations' => $stations]);
    }

    public static function save_station(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $station_id = EZEV_Core_Domain::normalize_id((string) $request->get_param('station_id'));
        $post_id = EZEV_Core_Stations::find_by_station_id($station_id);
        $post = $post_id ? get_post($post_id) : null;
        if (!$post || $post->post_type !== EZEV_Core_Stations::POST_TYPE) { return new WP_Error('invalid_station', 'Station not found.', ['status' => 404]); }
        $wpdb->replace(EZEV_Core_DB::table('saved_stations'), [
            'user_id' => get_current_user_id(),
            'station_post_id' => $post_id,
            'station_id' => $station_id,
            'created_at' => current_time('mysql', true),
        ], ['%d','%d','%s','%s']);
        EZEV_Core_DB::log('station_saved', 'station', (string) $station_id);
        return rest_ensure_response(['saved' => true]);
    }

    public static function remove_saved_station(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $station_id = EZEV_Core_Domain::normalize_id((string) $request['station_id']);
        $wpdb->delete(EZEV_Core_DB::table('saved_stations'), ['user_id' => get_current_user_id(), 'station_id' => $station_id], ['%d','%s']);
        EZEV_Core_DB::log('station_unsaved', 'station', (string) $station_id);
        return rest_ensure_response(['saved' => false]);
    }

    public static function login(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $username = sanitize_text_field((string) $request->get_param('username'));
        $password = (string) $request->get_param('password');
        $remember = (bool) $request->get_param('remember');

        if (empty($username) || empty($password)) {
            return new WP_Error('missing_credentials', 'Vui lòng nhập tên đăng nhập/email và mật khẩu.', ['status' => 400]);
        }

        $user = wp_authenticate($username, $password);
        if (is_wp_error($user)) {
            return new WP_Error('invalid_credentials', 'Tên đăng nhập hoặc mật khẩu không chính xác.', ['status' => 401]);
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, $remember);

        $roles = (array) $user->roles;
        $redirect_url = EZEV_Core_Auth::destination_for_user($user);

        EZEV_Core_DB::log('frontend_login_success', 'user', (string) $user->ID, ['redirect' => $redirect_url]);

        return rest_ensure_response([
            'success' => true,
            'message' => 'Đăng nhập thành công.',
            'redirect_url' => $redirect_url,
            'rest_nonce' => wp_create_nonce('wp_rest'),
            'user' => [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'roles' => $roles,
            ],
        ]);
    }

    public static function logout(): WP_REST_Response {
        wp_logout();
        return rest_ensure_response([
            'success' => true,
            'message' => 'Đã đăng xuất.',
            'redirect_url' => home_url('/login/'),
        ]);
    }
}
