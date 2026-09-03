<?php
if (!defined('ABSPATH')) { exit; }

final class EZEV_Core_REST {
    public static function init(): void {
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function routes(): void {
        register_rest_route('ezev/v1', '/stations', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'stations'],
            'permission_callback' => '__return_true',
            'args' => [
                'country' => ['sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
        register_rest_route('ezev/v1', '/me', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'me'],
            'permission_callback' => static fn() => is_user_logged_in(),
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
        register_rest_route('ezev/v1', '/saved-stations/(?P<station_id>\d+)', [
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
        $rows = EZEV_Core_Stations::list($country ? ['country_code' => $country] : []);
        return rest_ensure_response([
            'count' => count($rows),
            'mode' => 'station-master-data',
            'stations' => $rows,
        ]);
    }

    public static function me(): WP_REST_Response {
        $user = wp_get_current_user();
        return rest_ensure_response([
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'roles' => array_values($user->roles),
            'memberships' => EZEV_Core_Auth::user_access($user->ID),
            'allowed_station_post_ids' => EZEV_Core_Auth::allowed_station_ids($user->ID),
        ]);
    }

    public static function saved_stations(): WP_REST_Response {
        global $wpdb;
        $user_id = get_current_user_id();
        $ids = array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT station_post_id FROM " . EZEV_Core_DB::table('saved_stations') . " WHERE user_id=%d ORDER BY created_at DESC", $user_id
        )) ?: []);
        $stations = [];
        foreach ($ids as $id) {
            $post = get_post($id);
            if ($post && $post->post_type === EZEV_Core_Stations::POST_TYPE) { $stations[] = EZEV_Core_Stations::to_array($post); }
        }
        return rest_ensure_response(['stations' => $stations]);
    }

    public static function save_station(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $station_id = absint($request->get_param('station_id'));
        $post = get_post($station_id);
        if (!$post || $post->post_type !== EZEV_Core_Stations::POST_TYPE) { return new WP_Error('invalid_station', 'Station not found.', ['status' => 404]); }
        $wpdb->replace(EZEV_Core_DB::table('saved_stations'), [
            'user_id' => get_current_user_id(),
            'station_post_id' => $station_id,
            'created_at' => current_time('mysql', true),
        ], ['%d','%d','%s']);
        EZEV_Core_DB::log('station_saved', 'station', (string) $station_id);
        return rest_ensure_response(['saved' => true]);
    }

    public static function remove_saved_station(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $station_id = absint($request['station_id']);
        $wpdb->delete(EZEV_Core_DB::table('saved_stations'), ['user_id' => get_current_user_id(), 'station_post_id' => $station_id], ['%d','%d']);
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
        $redirect_url = home_url('/account/');

        if (in_array('administrator', $roles, true)) {
            $redirect_url = admin_url();
        } elseif (array_filter($roles, static fn($r) => str_starts_with($r, 'ezev_internal_'))) {
            $redirect_url = home_url('/internal/');
        } elseif (in_array('ezev_business', $roles, true)) {
            $redirect_url = home_url('/business/');
        } elseif (in_array('ezev_partner', $roles, true) || in_array('ezev_investor', $roles, true)) {
            $redirect_url = home_url('/partner/');
        }

        EZEV_Core_DB::log('frontend_login_success', 'user', (string) $user->ID, ['redirect' => $redirect_url]);

        return rest_ensure_response([
            'success' => true,
            'message' => 'Đăng nhập thành công.',
            'redirect_url' => $redirect_url,
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
