<?php
declare(strict_types=1);

define('WP_USE_THEMES', false);

// 1. Resolve WordPress root path safely from CLI or environment, with local fallback
$wp_root = getenv('WP_ROOT') ?: getenv('WORDPRESS_ROOT');
if (!$wp_root && isset($argv[1]) && is_dir($argv[1])) {
    $wp_root = $argv[1];
}
if (!$wp_root) {
    $fallback = 'C:/Users/Admin/Local Sites/test-2/app/public';
    if (file_exists($fallback . '/wp-load.php')) {
        $wp_root = $fallback;
    }
}

if (!$wp_root || !file_exists($wp_root . '/wp-load.php')) {
    fwrite(STDERR, "FATAL: Could not locate WordPress root. Specify via WP_ROOT environment variable or first CLI argument.\n");
    exit(1);
}

require_once rtrim($wp_root, '/\\') . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

// Environment safety check: Refuse to run destructive tests against staging/production
$env = defined('WP_ENVIRONMENT_TYPE') ? WP_ENVIRONMENT_TYPE : (getenv('WP_ENVIRONMENT_TYPE') ?: 'local');
$site_url = get_option('siteurl', '');
if (in_array($env, ['staging', 'production', 'live'], true) || (!str_contains($site_url, 'local') && !str_contains($site_url, 'test') && !str_contains($site_url, 'localhost') && !str_contains($site_url, '127.0.0.1'))) {
    fwrite(STDERR, "SECURITY REFUSAL: Runtime gate suite detected non-local environment ('$env', URL: '$site_url'). Destructive tests aborted.\n");
    exit(1);
}

$failures = [];
$passes = [];

function check(bool $condition, string $test_name, string $details = ''): void {
    global $failures, $passes;
    if ($condition) {
        $passes[] = $test_name;
        echo "[PASS] $test_name\n";
    } else {
        $msg = "[FAIL] $test_name: $details";
        $failures[] = $msg;
        echo "$msg\n";
    }
}

echo "=== STARTING CORE RUNTIME GATE SUITE ===\n";

// 1. Clean Activation & Plugin Active
check(is_plugin_active('ezev-core/ezev-core.php'), 'Core Plugin Active', 'ezev-core/ezev-core.php is not active in WP');

// 2. Upgrade Migration & DB Version
global $wpdb;
update_option('ezev_core_db_version', '4.0.0'); // simulate legacy state
EZEV_Core_DB::maybe_upgrade();
$db_ver = get_option('ezev_core_db_version');
check($db_ver === '1.1.0', 'Upgrade Migration to EZEV_CORE_DB_VERSION', "expected 1.1.0, got $db_ver");

$expected_tables = [
    'ezev_organizations',
    'ezev_sites',
    'ezev_org_members',
    'ezev_member_site_access',
    'ezev_member_station_access',
    'ezev_saved_stations',
    'ezev_invitations',
    'ezev_audit_logs',
];
foreach ($expected_tables as $tbl) {
    $tbl_name = $wpdb->prefix . $tbl;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$tbl_name'") === $tbl_name;
    check($exists, "Table $tbl exists", "Table $tbl_name was not created");
}

// 3. Organization -> Site -> Station domain lifecycle
$test_org_id = 'EZEV-ORG-RUNTIME-01';
$test_site_id = 'EZEV-SITE-RUNTIME-01';
$test_station_id = 'EZEV-STN-RUNTIME-01';

$wpdb->delete($wpdb->prefix . 'ezev_organizations', ['organization_id' => $test_org_id]);
$wpdb->delete($wpdb->prefix . 'ezev_sites', ['site_id' => $test_site_id]);
$old_post_id = EZEV_Core_Stations::find_by_station_id($test_station_id);
if ($old_post_id) {
    wp_delete_post($old_post_id, true);
}

// Create Org
$now = current_time('mysql', true);
$wpdb->insert($wpdb->prefix . 'ezev_organizations', [
    'organization_id' => $test_org_id,
    'org_code' => $test_org_id,
    'name' => 'Runtime Test Org',
    'type' => 'business',
    'country_code' => 'VN',
    'status' => 'active',
    'created_at' => $now,
    'updated_at' => $now,
]);
$org_row = EZEV_Core_Domain::organization_by_id($test_org_id);
check($org_row !== null && $org_row['name'] === 'Runtime Test Org', 'Organization created and resolved', 'Failed to retrieve org by stable ID');

// Create Site under Org
$wpdb->insert($wpdb->prefix . 'ezev_sites', [
    'organization_id' => (int) $org_row['id'],
    'organization_ref' => $test_org_id,
    'site_id' => $test_site_id,
    'site_code' => $test_site_id,
    'name' => 'Runtime Test Site',
    'address' => '123 Nguyen Hue, Ben Nghe, District 1, HCM',
    'city' => 'Ho Chi Minh City',
    'country_code' => 'VN',
    'latitude' => 10.7745,
    'longitude' => 106.7025,
    'status' => 'active',
    'created_at' => $now,
    'updated_at' => $now,
], ['%d','%s','%s','%s','%s','%s','%s','%s','%f','%f','%s','%s','%s']);
$site_row = EZEV_Core_Domain::site_by_id($test_site_id);
check($site_row !== null && $site_row['organization_ref'] === $test_org_id, 'Site created under Organization', 'Failed to retrieve site by stable ID or org mismatch');

// 4. REST list / detail / create / update on live server
$rest_server = rest_get_server();

// Check GET /ezev/v1/stations
$req_list = new WP_REST_Request('GET', '/ezev/v1/stations');
$res_list = $rest_server->dispatch($req_list);
check($res_list->get_status() === 200, 'GET /ezev/v1/stations 200 OK', 'Status: ' . $res_list->get_status());
$list_data = $res_list->get_data();
check(isset($list_data['stations']) && is_array($list_data['stations']), 'Stations array present in response');

// Login as admin for creatable/updatable endpoints
$admin_user = get_user_by('login', 'admin');
wp_set_current_user($admin_user->ID);

// POST /ezev/v1/stations (Create)
$req_create = new WP_REST_Request('POST', '/ezev/v1/stations');
$req_create->set_header('content-type', 'application/json');
$req_create->set_body(wp_json_encode([
    'station_id' => $test_station_id,
    'name' => 'Runtime Station Alpha',
    'description' => 'Created via live REST request',
    'address' => [
        'line' => '123 Nguyen Hue',
        'city' => 'Ho Chi Minh City',
        'region' => 'South',
        'country' => 'Vietnam',
        'country_code' => 'VN',
    ],
    'location' => ['lat' => 10.7745123, 'lng' => 106.7025456],
    'connectors' => ['CCS2', 'Type 2'],
    'max_power_kw' => 120,
    'ports' => ['total' => 2, 'available' => 2],
    'opening_hours' => '24/7',
    'status' => 'active',
    'ownership' => [
        'organization_id' => $test_org_id,
        'site_id' => $test_site_id,
    ],
    'data' => [
        'mode' => 'manual',
        'is_demo' => false,
    ],
]));
$res_create = $rest_server->dispatch($req_create);
check($res_create->get_status() === 201, 'POST /ezev/v1/stations 201 Created', 'Status: ' . $res_create->get_status() . ' Error: ' . wp_json_encode($res_create->get_data()));
$created_data = $res_create->get_data()['station'] ?? [];
check(($created_data['station_id'] ?? '') === $test_station_id, 'Created station has stable ID', 'Received: ' . ($created_data['station_id'] ?? 'none'));

// GET /ezev/v1/stations/{station_id} (Detail)
$req_detail = new WP_REST_Request('GET', '/ezev/v1/stations/' . $test_station_id);
$res_detail = $rest_server->dispatch($req_detail);
check($res_detail->get_status() === 200, 'GET /ezev/v1/stations/{station_id} 200 OK', 'Status: ' . $res_detail->get_status());
$detail_station = $res_detail->get_data()['station'] ?? [];
check(abs(($detail_station['location']['lat'] ?? 0) - 10.7745123) < 0.0001, 'Detail location lat matches', 'Lat was ' . ($detail_station['location']['lat'] ?? 0));
check(($detail_station['ownership']['site_id'] ?? '') === $test_site_id, 'Detail ownership site_id matches');

// PUT /ezev/v1/stations/{station_id} (Update)
$req_update = new WP_REST_Request('PUT', '/ezev/v1/stations/' . $test_station_id);
$req_update->set_header('content-type', 'application/json');
$req_update->set_body(wp_json_encode([
    'station_id' => $test_station_id,
    'name' => 'Runtime Station Alpha (Updated Coordinates)',
    'location' => ['lat' => 10.7750000, 'lng' => 106.7030000],
    'max_power_kw' => 180,
]));
$res_update = $rest_server->dispatch($req_update);
check($res_update->get_status() === 200, 'PUT /ezev/v1/stations/{station_id} 200 OK', 'Status: ' . $res_update->get_status());
$updated_station = $res_update->get_data()['station'] ?? [];
check(abs(($updated_station['location']['lat'] ?? 0) - 10.7750000) < 0.0001, 'Updated location lat saved and verified');
check(($updated_station['max_power_kw'] ?? 0) == 180, 'Updated power saved');

// 5. Auth Login/Logout endpoint verification
$test_admin_login = 'test_gate_admin_' . wp_rand(1000, 9999);
$test_admin_pass = wp_generate_password(24, true, true);
$test_admin_id = wp_create_user($test_admin_login, $test_admin_pass, $test_admin_login . '@ezev.test');
$test_admin_obj = new WP_User($test_admin_id);
$test_admin_obj->set_role('administrator');

$req_login = new WP_REST_Request('POST', '/ezev/v1/auth/login');
$req_login->set_body_params(['username' => $test_admin_login, 'password' => $test_admin_pass]);
$res_login = $rest_server->dispatch($req_login);
check($res_login->get_status() === 200, 'POST /auth/login returns 200', 'Status: ' . $res_login->get_status());
$login_data = $res_login->get_data();
check(!empty($login_data['rest_nonce']), 'Login response provides rest_nonce');
check($login_data['redirect_url'] === admin_url(), 'Admin redirects to wp-admin', 'Got: ' . $login_data['redirect_url']);
wp_delete_user($test_admin_id);

// Customer login & redirect
$cust = get_user_by('login', 'customer_auto');
if ($cust) {
    wp_set_password('testpassword', $cust->ID);
    $req_cust_login = new WP_REST_Request('POST', '/ezev/v1/auth/login');
    $req_cust_login->set_body_params(['username' => 'customer_auto', 'password' => 'testpassword']);
    $res_cust_login = $rest_server->dispatch($req_cust_login);
    check($res_cust_login->get_status() === 200, 'Customer login 200 OK', 'Status: ' . $res_cust_login->get_status());
    check(str_contains($res_cust_login->get_data()['redirect_url'] ?? '', '/account'), 'Customer redirected to /account/');
}

// Logout
$req_logout = new WP_REST_Request('POST', '/ezev/v1/auth/logout');
$res_logout = $rest_server->dispatch($req_logout);
check($res_logout->get_status() === 200, 'POST /auth/logout returns 200', 'Status: ' . $res_logout->get_status());

// 6. Saved Station Persistence
$cust_user = get_user_by('login', 'customer_auto');
wp_set_current_user($cust_user->ID);

$wpdb->delete($wpdb->prefix . 'ezev_saved_stations', ['user_id' => $cust_user->ID]);

$req_save = new WP_REST_Request('POST', '/ezev/v1/saved-stations');
$req_save->set_body_params(['station_id' => $test_station_id]);
$res_save = $rest_server->dispatch($req_save);
check($res_save->get_status() === 200 && ($res_save->get_data()['saved'] ?? false) === true, 'POST /saved-stations persists station');

$req_get_saved = new WP_REST_Request('GET', '/ezev/v1/saved-stations');
$res_get_saved = $rest_server->dispatch($req_get_saved);
check($res_get_saved->get_status() === 200, 'GET /saved-stations returns 200');
$saved_list = $res_get_saved->get_data()['stations'] ?? [];
$saved_ids = array_column($saved_list, 'station_id');
check(in_array($test_station_id, $saved_ids, true), 'Saved station appears in user list');

$req_del_saved = new WP_REST_Request('DELETE', '/ezev/v1/saved-stations/' . $test_station_id);
$res_del_saved = $rest_server->dispatch($req_del_saved);
check($res_del_saved->get_status() === 200 && ($res_del_saved->get_data()['saved'] ?? true) === false, 'DELETE /saved-stations removes station');

// 7. RBAC / Scope / 403 Enforcement
wp_set_current_user($cust_user->ID);
$req_scoped = new WP_REST_Request('GET', '/ezev/v1/me/stations/' . $test_station_id);
$res_scoped = $rest_server->dispatch($req_scoped);
check($res_scoped->get_status() === 403, 'Unauthorized access to /me/stations/{station_id} returns 403 Forbidden', 'Status: ' . $res_scoped->get_status());

// Grant access via site membership
$membership_id = 'EZEV-MEM-RUNTIME-01';
$wpdb->delete($wpdb->prefix . 'ezev_org_members', ['membership_id' => $membership_id]);
$wpdb->delete($wpdb->prefix . 'ezev_member_site_access', ['membership_ref' => $membership_id]);

$wpdb->insert($wpdb->prefix . 'ezev_org_members', [
    'membership_id' => $membership_id,
    'organization_id' => (int) $org_row['id'],
    'organization_ref' => $test_org_id,
    'user_id' => $cust_user->ID,
    'role_key' => 'site_manager',
    'status' => 'active',
    'created_at' => $now,
    'updated_at' => $now,
]);
$member_db_id = $wpdb->insert_id;
$wpdb->insert($wpdb->prefix . 'ezev_member_site_access', [
    'member_id' => $member_db_id,
    'site_id' => (int) $site_row['id'],
    'membership_ref' => $membership_id,
    'site_ref' => $test_site_id,
    'created_at' => $now,
]);

$res_scoped_allowed = $rest_server->dispatch($req_scoped);
check($res_scoped_allowed->get_status() === 200, 'Scoped site manager access to /me/stations/{station_id} returns 200 OK', 'Status: ' . $res_scoped_allowed->get_status());

// 8. Google Maps live geocoding/places/drag integration contract check
$admin_js = file_get_contents(EZEV_CORE_DIR . 'assets/js/admin.js');
$public_js = file_get_contents(EZEV_CORE_DIR . 'assets/js/public-map.js');
check(str_contains($admin_js, 'gmpDraggable:true') && str_contains($admin_js, "marker.addListener('dragend'"), 'Admin JS has draggable AdvancedMarkerElement with dragend sync');
check(str_contains($admin_js, 'google.maps.places.Autocomplete') && str_contains($admin_js, 'new google.maps.Geocoder'), 'Admin JS has Places Autocomplete and Geocoder');
check(str_contains($admin_js, 'updatePreview') && str_contains($admin_js, 'latInput.value=Number(pos.lat).toFixed(7)'), 'Admin coordinates sync to input fields for save');

// Clean up test data
$wpdb->delete($wpdb->prefix . 'ezev_organizations', ['organization_id' => $test_org_id]);
$wpdb->delete($wpdb->prefix . 'ezev_sites', ['site_id' => $test_site_id]);
$wpdb->delete($wpdb->prefix . 'ezev_org_members', ['membership_id' => $membership_id]);
$wpdb->delete($wpdb->prefix . 'ezev_member_site_access', ['membership_ref' => $membership_id]);
$post_cleanup_id = EZEV_Core_Stations::find_by_station_id($test_station_id);
if ($post_cleanup_id) {
    wp_delete_post($post_cleanup_id, true);
}

echo "=== SUMMARY ===\n";
echo "Passed: " . count($passes) . "\n";
echo "Failed: " . count($failures) . "\n";

if (!empty($failures)) {
    echo "\nFailures encountered:\n" . implode("\n", $failures) . "\n";
    exit(1);
}

echo "ALL CORE RUNTIME GATE CHECKS PASSED ON LIVE WORDPRESS + MYSQL!\n";
exit(0);
