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

        // --- GATE 3: Organization CRUD ---
        register_rest_route('ezev/v1', '/organizations', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'organizations'],
                'permission_callback' => [self::class, 'authenticated'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'create_organization'],
                'permission_callback' => [self::class, 'can_manage_organizations'],
            ],
        ]);
        register_rest_route('ezev/v1', '/organizations/(?P<organization_id>[A-Za-z0-9._-]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'organization'],
                'permission_callback' => [self::class, 'authenticated'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [self::class, 'update_organization'],
                'permission_callback' => [self::class, 'can_manage_organizations'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [self::class, 'delete_organization'],
                'permission_callback' => [self::class, 'can_manage_organizations'],
            ],
        ]);

        // --- GATE 3: Site CRUD ---
        register_rest_route('ezev/v1', '/sites', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'sites'],
                'permission_callback' => [self::class, 'authenticated'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'create_site'],
                'permission_callback' => [self::class, 'can_manage_organizations'],
            ],
        ]);
        register_rest_route('ezev/v1', '/sites/(?P<site_id>[A-Za-z0-9._-]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'site'],
                'permission_callback' => [self::class, 'authenticated'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [self::class, 'update_site'],
                'permission_callback' => [self::class, 'can_manage_organizations'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [self::class, 'delete_site'],
                'permission_callback' => [self::class, 'can_manage_organizations'],
            ],
        ]);

        // --- GATE 3: Membership Management ---
        register_rest_route('ezev/v1', '/organizations/(?P<organization_id>[A-Za-z0-9._-]+)/members', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'members'],
                'permission_callback' => [self::class, 'authenticated'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'create_member'],
                'permission_callback' => [self::class, 'can_manage_organizations'],
            ],
        ]);
        register_rest_route('ezev/v1', '/organizations/(?P<organization_id>[A-Za-z0-9._-]+)/members/(?P<membership_id>[A-Za-z0-9._-]+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [self::class, 'update_member'],
                'permission_callback' => [self::class, 'can_manage_organizations'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [self::class, 'delete_member'],
                'permission_callback' => [self::class, 'can_manage_organizations'],
            ],
        ]);
        register_rest_route('ezev/v1', '/memberships/(?P<membership_id>[A-Za-z0-9._-]+)/sites', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'assign_member_site'],
            'permission_callback' => [self::class, 'can_manage_organizations'],
        ]);
        register_rest_route('ezev/v1', '/memberships/(?P<membership_id>[A-Za-z0-9._-]+)/stations', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'assign_member_station'],
            'permission_callback' => [self::class, 'can_manage_organizations'],
        ]);

        // --- GATE 3: Invitations Lifecycle ---
        register_rest_route('ezev/v1', '/organizations/(?P<organization_id>[A-Za-z0-9._-]+)/invitations', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'create_invitation'],
            'permission_callback' => [self::class, 'can_manage_organizations'],
        ]);
        register_rest_route('ezev/v1', '/invitations/(?P<token>[A-Za-z0-9._-]+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'verify_invitation'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('ezev/v1', '/invitations/(?P<token>[A-Za-z0-9._-]+)/accept', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'accept_invitation'],
            'permission_callback' => [self::class, 'authenticated'],
        ]);
        register_rest_route('ezev/v1', '/invitations/(?P<id>\d+)/revoke', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'revoke_invitation'],
            'permission_callback' => [self::class, 'can_manage_organizations'],
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

    public static function can_manage_organizations(): bool|WP_Error {
        if (!is_user_logged_in()) {
            return new WP_Error('ezev_authentication_required', 'Authentication required.', ['status' => 401]);
        }
        if (current_user_can('manage_options') || current_user_can('ezev_manage_organizations') || current_user_can('ezev_manage_access')) {
            return true;
        }
        return new WP_Error('ezev_forbidden', 'Forbidden: missing organization management capability.', ['status' => 403]);
    }

    public static function serialize_organization(array $row): array {
        return [
            'organization_id' => (string) ($row['organization_id'] ?? ''),
            'org_code'        => (string) ($row['org_code'] ?? ''),
            'name'            => (string) ($row['name'] ?? ''),
            'type'            => (string) ($row['type'] ?? 'business'),
            'country_code'    => (string) ($row['country_code'] ?? ''),
            'status'          => (string) ($row['status'] ?? 'active'),
            'created_at'      => (string) ($row['created_at'] ?? ''),
            'updated_at'      => (string) ($row['updated_at'] ?? ''),
        ];
    }

    public static function serialize_site(array $row): array {
        return [
            'site_id'          => (string) ($row['site_id'] ?? ''),
            'site_code'        => (string) ($row['site_code'] ?? ''),
            'organization_id'  => (string) ($row['organization_ref'] ?? ''),
            'name'             => (string) ($row['name'] ?? ''),
            'address'          => (string) ($row['address'] ?? ''),
            'city'             => (string) ($row['city'] ?? ''),
            'country_code'     => (string) ($row['country_code'] ?? ''),
            'latitude'         => !is_null($row['latitude'] ?? null) ? (float) $row['latitude'] : null,
            'longitude'        => !is_null($row['longitude'] ?? null) ? (float) $row['longitude'] : null,
            'status'           => (string) ($row['status'] ?? 'active'),
            'created_at'       => (string) ($row['created_at'] ?? ''),
            'updated_at'       => (string) ($row['updated_at'] ?? ''),
        ];
    }

    public static function serialize_member(array $row): array {
        $u = get_userdata((int) ($row['user_id'] ?? 0));
        return [
            'membership_id'    => (string) ($row['membership_id'] ?? ''),
            'organization_id'  => (string) ($row['organization_ref'] ?? ''),
            'user_id'          => (int) ($row['user_id'] ?? 0),
            'user_email'       => $u ? $u->user_email : '',
            'display_name'     => $u ? $u->display_name : '',
            'role_key'         => (string) ($row['role_key'] ?? 'viewer'),
            'status'           => (string) ($row['status'] ?? 'active'),
            'created_at'       => (string) ($row['created_at'] ?? ''),
            'updated_at'       => (string) ($row['updated_at'] ?? ''),
        ];
    }

    public static function serialize_invitation(array $row): array {
        return [
            'id'              => (int) $row['id'],
            'organization_id' => (string) ($row['organization_ref'] ?? $row['organization_id'] ?? ''),
            'email'           => (string) ($row['email'] ?? ''),
            'role_key'        => (string) ($row['role_key'] ?? 'viewer'),
            'status'          => (string) ($row['status'] ?? 'pending'),
            'expires_at'      => (string) ($row['expires_at'] ?? ''),
            'created_at'      => (string) ($row['created_at'] ?? ''),
        ];
    }

    // --- Organization Handlers ---
    public static function organizations(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table = EZEV_Core_DB::table('organizations');
        $user_id = get_current_user_id();
        $where = ["1=1"];
        $args = [];

        // Tenant Scoping: non-admin can only see organizations they belong to
        if (!current_user_can('manage_options') && !current_user_can('ezev_view_all_stations')) {
            $allowed_orgs = EZEV_Core_Auth::user_organization_ids($user_id);
            if (empty($allowed_orgs)) {
                return rest_ensure_response(['organizations' => []]);
            }
            $escaped = implode("','", array_map('esc_sql', $allowed_orgs));
            $where[] = "organization_id IN ('$escaped')";
        }

        $type = sanitize_key((string) $request->get_param('type'));
        $status = sanitize_key((string) $request->get_param('status'));
        if ($type) { $where[] = "type = %s"; $args[] = $type; }
        if ($status) { $where[] = "status = %s"; $args[] = $status; }
        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY name ASC";
        $rows = !empty($args) ? $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
        return rest_ensure_response([
            'organizations' => array_map([self::class, 'serialize_organization'], $rows ?: [])
        ]);
    }

    public static function organization(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $id = EZEV_Core_Domain::normalize_id((string) $request['organization_id']);
        $user_id = get_current_user_id();
        if (!EZEV_Core_Auth::can_read_organization($user_id, $id)) {
            return new WP_Error('forbidden', 'Forbidden: you do not have access to this organization.', ['status' => 403]);
        }
        $row = EZEV_Core_Domain::organization_by_id($id);
        if (!$row) {
            return new WP_Error('not_found', 'Organization not found.', ['status' => 404]);
        }
        return rest_ensure_response(['organization' => self::serialize_organization($row)]);
    }

    public static function create_organization(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $body = (array) $request->get_json_params();
        $name = sanitize_text_field((string) ($body['name'] ?? ''));
        if ($name === '') {
            return new WP_Error('invalid_data', 'Organization name is required.', ['status' => 400]);
        }
        $org_id = EZEV_Core_Domain::normalize_id((string) ($body['organization_id'] ?? EZEV_Core_Domain::new_id('organization')));
        $org_code = sanitize_text_field((string) ($body['org_code'] ?? substr(strtoupper(preg_replace('/[^A-Z0-9]/', '', $name)), 0, 12)));
        if ($org_code === '') { $org_code = 'ORG' . wp_rand(1000, 9999); }
        $type = sanitize_key((string) ($body['type'] ?? 'business'));
        $country = sanitize_text_field((string) ($body['country_code'] ?? 'VN'));
        $status = sanitize_key((string) ($body['status'] ?? 'active'));
        $table = EZEV_Core_DB::table('organizations');

        // Check duplicate
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE organization_id = %s OR org_code = %s", $org_id, $org_code));
        if ($existing) {
            return new WP_Error('conflict', 'Organization with this ID or code already exists.', ['status' => 409]);
        }
        $now = current_time('mysql', true);
        $inserted = $wpdb->insert($table, [
            'organization_id' => $org_id,
            'org_code'        => $org_code,
            'name'            => $name,
            'type'            => $type,
            'country_code'    => $country,
            'status'          => $status,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
        if (!$inserted) {
            return new WP_Error('db_error', 'Failed to create organization.', ['status' => 500]);
        }
        EZEV_Core_DB::log('organization_created', 'organization', $org_id, ['name' => $name, 'type' => $type]);
        $row = EZEV_Core_Domain::organization_by_id($org_id);
        return new WP_REST_Response(['organization' => self::serialize_organization($row ?: [])], 201);
    }

    public static function update_organization(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $id = EZEV_Core_Domain::normalize_id((string) $request['organization_id']);
        $user_id = get_current_user_id();
        if (!EZEV_Core_Auth::can_manage_organization($user_id, $id)) {
            return new WP_Error('forbidden', 'Forbidden: missing organization management capability.', ['status' => 403]);
        }
        $row = EZEV_Core_Domain::organization_by_id($id);
        if (!$row) {
            return new WP_Error('not_found', 'Organization not found.', ['status' => 404]);
        }
        $body = (array) $request->get_json_params();
        $fields = [];
        if (isset($body['name'])) { $fields['name'] = sanitize_text_field((string) $body['name']); }
        if (isset($body['type'])) { $fields['type'] = sanitize_key((string) $body['type']); }
        if (isset($body['country_code'])) { $fields['country_code'] = sanitize_text_field((string) $body['country_code']); }
        if (isset($body['status'])) { $fields['status'] = sanitize_key((string) $body['status']); }
        $fields['updated_at'] = current_time('mysql', true);

        $table = EZEV_Core_DB::table('organizations');
        $wpdb->update($table, $fields, ['organization_id' => $id]);
        EZEV_Core_DB::log('organization_updated', 'organization', $id, $fields);
        $updated = EZEV_Core_Domain::organization_by_id($id);
        return rest_ensure_response(['organization' => self::serialize_organization($updated ?: [])]);
    }

    public static function delete_organization(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $id = EZEV_Core_Domain::normalize_id((string) $request['organization_id']);
        $user_id = get_current_user_id();
        if (!EZEV_Core_Auth::can_manage_organization($user_id, $id)) {
            return new WP_Error('forbidden', 'Forbidden: missing organization management capability.', ['status' => 403]);
        }
        $row = EZEV_Core_Domain::organization_by_id($id);
        if (!$row) {
            return new WP_Error('not_found', 'Organization not found.', ['status' => 404]);
        }

        // Dependency check: Sites, Stations, Members
        $sitesCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . EZEV_Core_DB::table('sites') . " WHERE organization_ref = %s", $id));
        $membersCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . EZEV_Core_DB::table('org_members') . " WHERE organization_ref = %s", $id));
        $stations = get_posts([
            'post_type'   => EZEV_Core_Stations::POST_TYPE,
            'post_status' => 'any',
            'numberposts' => 1,
            'fields'      => 'ids',
            'meta_key'    => '_ezev_organization_id',
            'meta_value'  => $id,
        ]);
        if ($sitesCount > 0 || $membersCount > 0 || !empty($stations)) {
            return new WP_Error('resource_has_dependencies', 'Cannot delete organization with active sites, stations, or memberships.', ['status' => 409]);
        }

        $table = EZEV_Core_DB::table('organizations');
        $wpdb->delete($table, ['organization_id' => $id]);
        EZEV_Core_DB::log('organization_deleted', 'organization', $id);
        return rest_ensure_response(['deleted' => true]);
    }

    // --- Site Handlers ---
    public static function sites(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table = EZEV_Core_DB::table('sites');
        $user_id = get_current_user_id();
        $where = ["1=1"];
        $args = [];

        // Tenant Scoping: non-admin can only see sites within their assigned scope
        if (!current_user_can('manage_options') && !current_user_can('ezev_view_all_stations')) {
            $allowed_sites = EZEV_Core_Auth::user_allowed_site_ids($user_id);
            if (empty($allowed_sites)) {
                return rest_ensure_response(['sites' => []]);
            }
            $escaped = implode("','", array_map('esc_sql', $allowed_sites));
            $where[] = "site_id IN ('$escaped')";
        }

        $org_ref = (string) $request->get_param('organization_id');
        if ($org_ref !== '') {
            $where[] = "organization_ref = %s";
            $args[] = $org_ref;
        }
        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY name ASC";
        $rows = !empty($args) ? $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
        return rest_ensure_response(['sites' => array_map([self::class, 'serialize_site'], $rows ?: [])]);
    }

    public static function site(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $id = EZEV_Core_Domain::normalize_id((string) $request['site_id']);
        $user_id = get_current_user_id();
        if (!EZEV_Core_Auth::can_read_site($user_id, $id)) {
            return new WP_Error('forbidden', 'Forbidden: you do not have access to this site.', ['status' => 403]);
        }
        $row = EZEV_Core_Domain::site_by_id($id);
        if (!$row) {
            return new WP_Error('not_found', 'Site not found.', ['status' => 404]);
        }
        return rest_ensure_response(['site' => self::serialize_site($row)]);
    }

    public static function create_site(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $user_id = get_current_user_id();
        $body = (array) $request->get_json_params();
        $name = sanitize_text_field((string) ($body['name'] ?? ''));
        $org_ref = EZEV_Core_Domain::normalize_id((string) ($body['organization_id'] ?? ''));
        if ($name === '' || $org_ref === '') {
            return new WP_Error('invalid_data', 'Site name and organization_id are required.', ['status' => 400]);
        }
        if (!EZEV_Core_Auth::can_manage_organization($user_id, $org_ref)) {
            return new WP_Error('forbidden', 'Forbidden: missing permission to manage sites in this organization.', ['status' => 403]);
        }
        $org = EZEV_Core_Domain::organization_by_id($org_ref);
        if (!$org) {
            return new WP_Error('not_found', 'Referenced organization does not exist.', ['status' => 404]);
        }
        $site_id = EZEV_Core_Domain::normalize_id((string) ($body['site_id'] ?? EZEV_Core_Domain::new_id('site')));
        $site_code = sanitize_text_field((string) ($body['site_code'] ?? substr(strtoupper(preg_replace('/[^A-Z0-9]/', '', $name)), 0, 12)));
        if ($site_code === '') { $site_code = 'STE' . wp_rand(1000, 9999); }

        $table = EZEV_Core_DB::table('sites');
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE site_id = %s OR site_code = %s", $site_id, $site_code));
        if ($existing) {
            return new WP_Error('conflict', 'Site with this ID or code already exists.', ['status' => 409]);
        }
        $now = current_time('mysql', true);
        $insert_data = [
            'organization_id'  => (int) ($org['id'] ?? 0),
            'organization_ref' => $org_ref,
            'site_id'          => $site_id,
            'site_code'        => $site_code,
            'name'             => $name,
            'address'          => sanitize_textarea_field((string) ($body['address'] ?? '')),
            'city'             => sanitize_text_field((string) ($body['city'] ?? '')),
            'country_code'     => sanitize_text_field((string) ($body['country_code'] ?? 'VN')),
            'latitude'         => isset($body['latitude']) ? (float) $body['latitude'] : null,
            'longitude'        => isset($body['longitude']) ? (float) $body['longitude'] : null,
            'status'           => sanitize_key((string) ($body['status'] ?? 'active')),
            'created_at'       => $now,
            'updated_at'       => $now,
        ];
        $insert_format = ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s'];
        $wpdb->insert($table, $insert_data, $insert_format);
        EZEV_Core_DB::log('site_created', 'site', $site_id, ['name' => $name, 'organization_id' => $org_ref]);
        $row = EZEV_Core_Domain::site_by_id($site_id);
        return new WP_REST_Response(['site' => self::serialize_site($row ?: [])], 201);
    }

    public static function update_site(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $id = EZEV_Core_Domain::normalize_id((string) $request['site_id']);
        $user_id = get_current_user_id();
        if (!EZEV_Core_Auth::can_manage_site($user_id, $id)) {
            return new WP_Error('forbidden', 'Forbidden: missing permission to manage this site.', ['status' => 403]);
        }
        $row = EZEV_Core_Domain::site_by_id($id);
        if (!$row) {
            return new WP_Error('not_found', 'Site not found.', ['status' => 404]);
        }
        $body = (array) $request->get_json_params();
        $fields = [];
        if (isset($body['name'])) { $fields['name'] = sanitize_text_field((string) $body['name']); }
        if (isset($body['address'])) { $fields['address'] = sanitize_textarea_field((string) $body['address']); }
        if (isset($body['city'])) { $fields['city'] = sanitize_text_field((string) $body['city']); }
        if (isset($body['country_code'])) { $fields['country_code'] = sanitize_text_field((string) $body['country_code']); }
        if (isset($body['latitude'])) { $fields['latitude'] = (float) $body['latitude']; }
        if (isset($body['longitude'])) { $fields['longitude'] = (float) $body['longitude']; }
        if (isset($body['status'])) { $fields['status'] = sanitize_key((string) $body['status']); }
        $fields['updated_at'] = current_time('mysql', true);

        $table = EZEV_Core_DB::table('sites');
        $wpdb->update($table, $fields, ['site_id' => $id]);
        EZEV_Core_DB::log('site_updated', 'site', $id, $fields);
        $updated = EZEV_Core_Domain::site_by_id($id);
        return rest_ensure_response(['site' => self::serialize_site($updated ?: [])]);
    }

    public static function delete_site(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $id = EZEV_Core_Domain::normalize_id((string) $request['site_id']);
        $user_id = get_current_user_id();
        if (!EZEV_Core_Auth::can_manage_site($user_id, $id)) {
            return new WP_Error('forbidden', 'Forbidden: missing permission to delete this site.', ['status' => 403]);
        }
        $row = EZEV_Core_Domain::site_by_id($id);
        if (!$row) {
            return new WP_Error('not_found', 'Site not found.', ['status' => 404]);
        }

        // Dependency check: Stations linked to this site
        $stations = get_posts([
            'post_type'   => EZEV_Core_Stations::POST_TYPE,
            'post_status' => 'any',
            'numberposts' => 1,
            'fields'      => 'ids',
            'meta_key'    => '_ezev_site_id',
            'meta_value'  => $id,
        ]);
        if (!empty($stations)) {
            return new WP_Error('resource_has_dependencies', 'Cannot delete site with active stations.', ['status' => 409]);
        }

        $table = EZEV_Core_DB::table('sites');
        $wpdb->delete($table, ['site_id' => $id]);
        EZEV_Core_DB::log('site_deleted', 'site', $id);
        return rest_ensure_response(['deleted' => true]);
    }

    // --- Membership Handlers ---
    public static function members(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $user_id = get_current_user_id();
        $org_ref = EZEV_Core_Domain::normalize_id((string) $request['organization_id']);
        if (!EZEV_Core_Auth::can_read_organization($user_id, $org_ref)) {
            return new WP_Error('forbidden', 'Forbidden: you do not have access to view members of this organization.', ['status' => 403]);
        }
        $org = EZEV_Core_Domain::organization_by_id($org_ref);
        if (!$org) {
            return new WP_Error('not_found', 'Organization not found.', ['status' => 404]);
        }
        $can_manage = EZEV_Core_Auth::can_manage_membership($user_id, $org_ref);
        $table = EZEV_Core_DB::table('org_members');
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE organization_ref = %s ORDER BY created_at DESC", $org_ref), ARRAY_A);
        $members = array_map(static function ($r) use ($can_manage) {
            $data = self::serialize_member($r);
            // Hide email from non-managers to prevent enumeration
            if (!$can_manage) {
                $data['user_email'] = '';
            }
            return $data;
        }, $rows ?: []);
        return rest_ensure_response(['members' => $members]);
    }

    public static function create_member(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $user_id = get_current_user_id();
        $org_ref = EZEV_Core_Domain::normalize_id((string) $request['organization_id']);
        if (!EZEV_Core_Auth::can_manage_membership($user_id, $org_ref)) {
            return new WP_Error('forbidden', 'Forbidden: missing membership management capability for this organization.', ['status' => 403]);
        }
        $org = EZEV_Core_Domain::organization_by_id($org_ref);
        if (!$org) {
            return new WP_Error('not_found', 'Organization not found.', ['status' => 404]);
        }
        $body = (array) $request->get_json_params();
        $target_user_id = absint($body['user_id'] ?? 0);
        $role_key = sanitize_key((string) ($body['role_key'] ?? 'viewer'));
        if (!$target_user_id || !get_userdata($target_user_id)) {
            return new WP_Error('invalid_data', 'Valid user_id is required.', ['status' => 400]);
        }
        $table = EZEV_Core_DB::table('org_members');
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE organization_ref = %s AND user_id = %d", $org_ref, $target_user_id), ARRAY_A);
        $now = current_time('mysql', true);
        if ($existing) {
            $wpdb->update($table, ['role_key' => $role_key, 'updated_at' => $now], ['id' => $existing['id']]);
            $membership_id = $existing['membership_id'];
        } else {
            $membership_id = EZEV_Core_Domain::new_id('member');
            $wpdb->insert($table, [
                'organization_id'  => (int) $org['id'],
                'organization_ref' => $org_ref,
                'membership_id'    => $membership_id,
                'user_id'          => $target_user_id,
                'role_key'         => $role_key,
                'status'           => 'active',
                'created_at'       => $now,
                'updated_at'       => $now,
            ], ['%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s']);
        }
        EZEV_Core_DB::log('member_assigned', 'membership', $membership_id, ['organization_id' => $org_ref, 'user_id' => $target_user_id, 'role_key' => $role_key]);
        $m = EZEV_Core_Domain::membership_by_id($membership_id);
        return new WP_REST_Response(['member' => self::serialize_member($m ?: [])], 201);
    }

    public static function update_member(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $membership_id = EZEV_Core_Domain::normalize_id((string) $request['membership_id']);
        $m = EZEV_Core_Domain::membership_by_id($membership_id);
        if (!$m) {
            return new WP_Error('not_found', 'Membership not found.', ['status' => 404]);
        }
        $user_id = get_current_user_id();
        $org_ref = (string) ($m['organization_ref'] ?? '');
        if (!EZEV_Core_Auth::can_manage_membership($user_id, $org_ref)) {
            return new WP_Error('forbidden', 'Forbidden: missing membership management capability.', ['status' => 403]);
        }
        $body = (array) $request->get_json_params();
        $fields = [];
        if (isset($body['role_key'])) { $fields['role_key'] = sanitize_key((string) $body['role_key']); }
        if (isset($body['status'])) { $fields['status'] = sanitize_key((string) $body['status']); }
        $fields['updated_at'] = current_time('mysql', true);

        $table = EZEV_Core_DB::table('org_members');
        $wpdb->update($table, $fields, ['membership_id' => $membership_id]);
        EZEV_Core_DB::log('member_updated', 'membership', $membership_id, $fields);
        $updated = EZEV_Core_Domain::membership_by_id($membership_id);
        return rest_ensure_response(['member' => self::serialize_member($updated ?: [])]);
    }

    public static function delete_member(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $membership_id = EZEV_Core_Domain::normalize_id((string) $request['membership_id']);
        $m = EZEV_Core_Domain::membership_by_id($membership_id);
        if (!$m) {
            return new WP_Error('not_found', 'Membership not found.', ['status' => 404]);
        }
        $user_id = get_current_user_id();
        $org_ref = (string) ($m['organization_ref'] ?? '');
        if (!EZEV_Core_Auth::can_manage_membership($user_id, $org_ref)) {
            return new WP_Error('forbidden', 'Forbidden: missing membership management capability.', ['status' => 403]);
        }
        $table = EZEV_Core_DB::table('org_members');
        $wpdb->delete($table, ['membership_id' => $membership_id]);
        // Also cleanup access
        $wpdb->delete(EZEV_Core_DB::table('member_site_access'), ['membership_ref' => $membership_id]);
        $wpdb->delete(EZEV_Core_DB::table('member_station_access'), ['membership_ref' => $membership_id]);
        EZEV_Core_DB::log('member_removed', 'membership', $membership_id);
        return rest_ensure_response(['deleted' => true]);
    }

    public static function assign_member_site(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $membership_id = EZEV_Core_Domain::normalize_id((string) $request['membership_id']);
        $m = EZEV_Core_Domain::membership_by_id($membership_id);
        if (!$m) {
            return new WP_Error('not_found', 'Membership not found.', ['status' => 404]);
        }
        $user_id = get_current_user_id();
        $org_ref = (string) ($m['organization_ref'] ?? '');
        if (!EZEV_Core_Auth::can_manage_membership($user_id, $org_ref)) {
            return new WP_Error('forbidden', 'Forbidden: missing access management capability.', ['status' => 403]);
        }
        $body = (array) $request->get_json_params();
        $site_id = EZEV_Core_Domain::normalize_id((string) ($body['site_id'] ?? ''));
        $site = EZEV_Core_Domain::site_by_id($site_id);
        if (!$site) {
            return new WP_Error('not_found', 'Site not found.', ['status' => 404]);
        }

        // GATE 3.1: Cross-organization boundary enforcement
        if (($m['organization_ref'] ?? '') !== ($site['organization_ref'] ?? '')) {
            return new WP_Error('cross_organization_mismatch', 'Site does not belong to the member organization.', ['status' => 422]);
        }

        $table = EZEV_Core_DB::table('member_site_access');
        $wpdb->replace($table, [
            'member_id'      => (int) $m['id'],
            'site_id'        => (int) $site['id'],
            'membership_ref' => $membership_id,
            'site_ref'       => $site_id,
            'created_at'     => current_time('mysql', true),
        ], ['%d', '%d', '%s', '%s', '%s']);
        EZEV_Core_DB::log('member_site_assigned', 'membership_site_access', $membership_id, ['site_id' => $site_id]);
        return rest_ensure_response(['assigned' => true]);
    }

    public static function assign_member_station(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $membership_id = EZEV_Core_Domain::normalize_id((string) $request['membership_id']);
        $m = EZEV_Core_Domain::membership_by_id($membership_id);
        if (!$m) {
            return new WP_Error('not_found', 'Membership not found.', ['status' => 404]);
        }
        $user_id = get_current_user_id();
        $org_ref = (string) ($m['organization_ref'] ?? '');
        if (!EZEV_Core_Auth::can_manage_membership($user_id, $org_ref)) {
            return new WP_Error('forbidden', 'Forbidden: missing access management capability.', ['status' => 403]);
        }
        $body = (array) $request->get_json_params();
        $station_id = EZEV_Core_Domain::normalize_id((string) ($body['station_id'] ?? ''));
        $post_id = EZEV_Core_Stations::find_by_station_id($station_id);
        if (!$post_id) {
            return new WP_Error('not_found', 'Station not found.', ['status' => 404]);
        }

        // GATE 3.1: Cross-organization boundary enforcement
        $station_org = (string) get_post_meta($post_id, '_ezev_organization_id', true);
        if (($m['organization_ref'] ?? '') !== $station_org) {
            return new WP_Error('cross_organization_mismatch', 'Station does not belong to the member organization.', ['status' => 422]);
        }

        $table = EZEV_Core_DB::table('member_station_access');
        $wpdb->replace($table, [
            'member_id'       => (int) $m['id'],
            'station_post_id' => $post_id,
            'membership_ref'  => $membership_id,
            'station_id'      => $station_id,
            'created_at'      => current_time('mysql', true),
        ], ['%d', '%d', '%s', '%s', '%s']);
        EZEV_Core_DB::log('member_station_assigned', 'membership_station_access', $membership_id, ['station_id' => $station_id]);
        return rest_ensure_response(['assigned' => true]);
    }

    // --- Invitation Handlers ---
    public static function create_invitation(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $user_id = get_current_user_id();
        $org_ref = EZEV_Core_Domain::normalize_id((string) $request['organization_id']);
        if (!EZEV_Core_Auth::can_manage_membership($user_id, $org_ref)) {
            return new WP_Error('forbidden', 'Forbidden: missing invitation management capability for this organization.', ['status' => 403]);
        }
        $org = EZEV_Core_Domain::organization_by_id($org_ref);
        if (!$org) {
            return new WP_Error('not_found', 'Organization not found.', ['status' => 404]);
        }
        $body = (array) $request->get_json_params();
        $email = sanitize_email((string) ($body['email'] ?? ''));
        if (!is_email($email)) {
            return new WP_Error('invalid_email', 'A valid email address is required.', ['status' => 400]);
        }
        $role_key = sanitize_key((string) ($body['role_key'] ?? 'viewer'));
        $raw_token = wp_generate_password(32, false);
        $token_hash = hash('sha256', $raw_token);
        $expires_at = gmdate('Y-m-d H:i:s', time() + 7 * 86400); // 7-day TTL

        $table = EZEV_Core_DB::table('invitations');
        $wpdb->insert($table, [
            'organization_id' => (int) $org['id'],
            'email'           => $email,
            'role_key'        => $role_key,
            'token_hash'      => $token_hash,
            'status'          => 'pending',
            'expires_at'      => $expires_at,
            'created_by'      => $user_id,
            'created_at'      => current_time('mysql', true),
        ]);
        $invitation_id = (int) $wpdb->insert_id;
        EZEV_Core_DB::log('invitation_created', 'invitation', (string) $invitation_id, ['email' => $email, 'organization_id' => $org_ref]);
        return new WP_REST_Response([
            'invitation_id' => $invitation_id,
            'email'         => $email,
            'role_key'      => $role_key,
            'token'         => $raw_token, // Provided once upon creation
            'expires_at'    => $expires_at,
        ], 201);
    }

    public static function verify_invitation(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $token = (string) $request['token'];
        $token_hash = hash('sha256', $token);
        $table = EZEV_Core_DB::table('invitations');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE token_hash = %s", $token_hash), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', 'Invalid invitation token.', ['status' => 404]);
        }
        if ($row['status'] !== 'pending') {
            return new WP_Error('invitation_spent', 'This invitation is no longer active.', ['status' => 410]);
        }
        if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) {
            $wpdb->update($table, ['status' => 'expired'], ['id' => $row['id']]);
            return new WP_Error('invitation_expired', 'Invitation has expired.', ['status' => 410]);
        }
        $org = $wpdb->get_row($wpdb->prepare("SELECT organization_id, name FROM " . EZEV_Core_DB::table('organizations') . " WHERE id = %d", (int) $row['organization_id']), ARRAY_A);
        return rest_ensure_response([
            'valid'           => true,
            'email'           => $row['email'],
            'organization_id' => $org['organization_id'] ?? '',
            'organization_name' => $org['name'] ?? '',
            'role_key'        => $row['role_key'],
            'expires_at'      => $row['expires_at'],
        ]);
    }

    public static function accept_invitation(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $token = (string) $request['token'];
        $token_hash = hash('sha256', $token);
        $table = EZEV_Core_DB::table('invitations');
        $inv = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE token_hash = %s", $token_hash), ARRAY_A);
        if (!$inv) {
            return new WP_Error('not_found', 'Invalid invitation token.', ['status' => 404]);
        }
        if ($inv['status'] !== 'pending') {
            return new WP_Error('invitation_spent', 'Invitation already accepted or invalid.', ['status' => 409]);
        }
        if (!empty($inv['expires_at']) && strtotime($inv['expires_at']) < time()) {
            return new WP_Error('invitation_expired', 'Invitation has expired.', ['status' => 410]);
        }

        // GATE 3.1: Normalized invitation email must match current user's email
        $current_user = wp_get_current_user();
        if (strtolower(trim($inv['email'])) !== strtolower(trim((string) $current_user->user_email))) {
            return new WP_Error('email_mismatch', 'Invitation was issued for a different email address.', ['status' => 403]);
        }

        // GATE 3.1: Atomic single-use claim to prevent concurrent duplicate acceptances
        $claimed = $wpdb->query($wpdb->prepare(
            "UPDATE $table SET status = 'accepted' WHERE token_hash = %s AND status = 'pending'",
            $token_hash
        ));
        if ($claimed !== 1) {
            return new WP_Error('invitation_already_claimed', 'Invitation already accepted or invalid.', ['status' => 409]);
        }

        $user_id = $current_user->ID;
        $org = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . EZEV_Core_DB::table('organizations') . " WHERE id = %d", (int) $inv['organization_id']), ARRAY_A);
        if (!$org) {
            return new WP_Error('not_found', 'Referenced organization does not exist.', ['status' => 404]);
        }
        $org_ref = (string) $org['organization_id'];
        $now = current_time('mysql', true);

        // Create or update membership
        $memberTable = EZEV_Core_DB::table('org_members');
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $memberTable WHERE organization_ref = %s AND user_id = %d", $org_ref, $user_id), ARRAY_A);
        if ($existing) {
            $wpdb->update($memberTable, ['role_key' => $inv['role_key'], 'status' => 'active', 'updated_at' => $now], ['id' => $existing['id']]);
            $membership_id = $existing['membership_id'];
        } else {
            $membership_id = EZEV_Core_Domain::new_id('member');
            $wpdb->insert($memberTable, [
                'organization_id'  => (int) $org['id'],
                'organization_ref' => $org_ref,
                'membership_id'    => $membership_id,
                'user_id'          => $user_id,
                'role_key'         => $inv['role_key'],
                'status'           => 'active',
                'created_at'       => $now,
                'updated_at'       => $now,
            ], ['%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s']);
        }

        EZEV_Core_DB::log('invitation_accepted', 'invitation', (string) $inv['id'], ['membership_id' => $membership_id, 'user_id' => $user_id]);

        return rest_ensure_response([
            'accepted'      => true,
            'membership_id' => $membership_id,
            'organization_id' => $org_ref,
            'role_key'      => $inv['role_key'],
        ]);
    }

    public static function revoke_invitation(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $id = absint($request['id']);
        $table = EZEV_Core_DB::table('invitations');
        $inv = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
        if (!$inv) {
            return new WP_Error('not_found', 'Invitation not found.', ['status' => 404]);
        }
        $user_id = get_current_user_id();
        $org = $wpdb->get_row($wpdb->prepare("SELECT organization_id FROM " . EZEV_Core_DB::table('organizations') . " WHERE id = %d", (int) $inv['organization_id']), ARRAY_A);
        $org_ref = (string) ($org['organization_id'] ?? '');
        if (!EZEV_Core_Auth::can_manage_membership($user_id, $org_ref)) {
            return new WP_Error('forbidden', 'Forbidden: missing invitation management capability.', ['status' => 403]);
        }
        $wpdb->update($table, ['status' => 'revoked'], ['id' => $id]);
        EZEV_Core_DB::log('invitation_revoked', 'invitation', (string) $id);
        return rest_ensure_response(['revoked' => true]);
    }
}
