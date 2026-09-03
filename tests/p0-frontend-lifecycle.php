<?php
/**
 * P0 Core Frontend Lifecycle & End-to-End Verification Test
 * Checkpoint 4.1F: Tests end-to-end master data synchronization across 4 P0 pages.
 */

define('WP_USE_THEMES', false);
$wp_load_path = getenv('WORDPRESS_PATH') ?: 'C:/Users/Admin/Local Sites/test-2/app/public/wp-load.php';
if (!file_exists($wp_load_path)) {
    $alt = dirname(__DIR__, 4) . '/Local Sites/test-2/app/public/wp-load.php';
    if (file_exists($alt)) { $wp_load_path = $alt; }
}
require_once $wp_load_path;

echo "=== Running P0 Frontend Lifecycle Verification ===\n";

$checks = 0;
$failed = 0;

function check(bool $condition, string $title, string $details = ''): void {
    global $checks, $failed;
    $checks++;
    if ($condition) {
        echo "  [PASS] $title\n";
    } else {
        $failed++;
        echo "  [FAIL] $title" . ($details ? " ($details)" : '') . "\n";
    }
}

// 1. Verify Theme Files & Architecture
$theme_dir = get_theme_root() . '/ezev-theme';
check(file_exists($theme_dir . '/style.css'), 'ezev-theme/style.css exists');
check(file_exists($theme_dir . '/functions.php'), 'ezev-theme/functions.php exists');
check(file_exists($theme_dir . '/front-page.php'), 'ezev-theme/front-page.php exists');
check(file_exists($theme_dir . '/page-find-a-charger.php'), 'ezev-theme/page-find-a-charger.php exists');
check(file_exists($theme_dir . '/single-ezev_station.php'), 'ezev-theme/single-ezev_station.php exists');
check(file_exists($theme_dir . '/page-charging-network.php'), 'ezev-theme/page-charging-network.php exists');
check(file_exists($theme_dir . '/assets/js/ezev-data-client.js'), 'ezev-data-client.js SDK exists');
check(file_exists($theme_dir . '/assets/js/maps-manager.js'), 'maps-manager.js exists');

// 2. Lifecycle Step 1: Create a Station via Core Service
$unique_id = 'EZEV-VN-TEST-' . substr(strtoupper(bin2hex(random_bytes(4))), 0, 6);
$unique_slug = strtolower($unique_id);

$created_post_id = EZEV_Core_Stations::create([
    'station_id'                 => $unique_id,
    'name'                       => 'Lifecycle Test Fast Charging Hub',
    'description'                => 'E2E Lifecycle Verification station in District 1, HCMC',
    'country_code'               => 'VN',
    'country'                    => 'Vietnam',
    'city'                       => 'Ho Chi Minh City',
    'region'                     => 'District 1',
    'address'                    => '123 Nguyen Hue Boulevard',
    'latitude'                   => 10.7769,
    'longitude'                  => 106.7009,
    'connector_types'            => ['CCS2', 'Type 2'],
    'max_power_kw'               => 240,
    'ports_total'                => 4,
    'ports_available_manual'     => 4,
    'operational_status_manual'  => 'active',
    'data_mode'                  => 'manual',
    'is_demo'                    => false,
    'amenities'                  => ['wifi', 'restroom', 'coffee'],
]);

check(is_int($created_post_id) && $created_post_id > 0, "Lifecycle station created successfully (Post ID: $created_post_id)");

// 3. Lifecycle Step 2: Verify in Public REST Collection
$rest_server = rest_get_server();
$req_list = new WP_REST_Request('GET', '/ezev/v1/stations');
$res_list = $rest_server->dispatch($req_list);
check($res_list->get_status() === 200, 'GET /ezev/v1/stations returns 200 OK');

$stations = $res_list->get_data()['stations'] ?? [];
$found = null;
foreach ($stations as $s) {
    if (($s['station_id'] ?? '') === $unique_id) {
        $found = $s;
        break;
    }
}
check($found !== null, "Newly created station found in public REST collection ($unique_id)");
check(($found['location']['lat'] ?? 0) === 10.7769, 'Tọa độ latitude chính xác 10.7769');
check(($found['location']['lng'] ?? 0) === 106.7009, 'Tọa độ longitude chính xác 106.7009');
check(($found['max_power_kw'] ?? 0) === 240.0, 'Công suất max_power_kw chính xác 240 kW');

// 4. Lifecycle Step 3: Slug Resolver & Detail Resolution
$post = get_post($created_post_id);
$actual_slug = $post->post_name;
$resolved_station_id = EZEV_Core_Stations::get_station_id_by_slug($actual_slug);
check($resolved_station_id === $unique_id, "Slug resolver maps '$actual_slug' to station_id '$unique_id'");

$req_detail = new WP_REST_Request('GET', '/ezev/v1/stations/' . $unique_id);
$res_detail = $rest_server->dispatch($req_detail);
check($res_detail->get_status() === 200, "GET /ezev/v1/stations/$unique_id returns 200 OK");
check(($res_detail->get_data()['station']['name'] ?? '') === 'Lifecycle Test Fast Charging Hub', 'Station detail name matches');

// 5. Lifecycle Step 4: Modification & Coordinates Update
EZEV_Core_Stations::create([
    'station_id'                 => $unique_id,
    'name'                       => 'Lifecycle Test Hub Updated',
    'latitude'                   => 10.7800,
    'longitude'                  => 106.7050,
    'max_power_kw'               => 360,
]);

$req_updated = new WP_REST_Request('GET', '/ezev/v1/stations/' . $unique_id);
$res_updated = $rest_server->dispatch($req_updated);
$updated_st = $res_updated->get_data()['station'] ?? [];
check(($updated_st['name'] ?? '') === 'Lifecycle Test Hub Updated', 'Station name updated');
check(($updated_st['location']['lat'] ?? 0) === 10.7800, 'Latitude updated to 10.7800');
check(($updated_st['location']['lng'] ?? 0) === 106.7050, 'Longitude updated to 106.7050');
check(($updated_st['max_power_kw'] ?? 0) === 360.0, 'Max power updated to 360 kW');

// 6. Lifecycle Step 5: Unpublish / Trash Station
wp_trash_post($created_post_id);

$req_after_trash = new WP_REST_Request('GET', '/ezev/v1/stations/' . $unique_id);
$res_after_trash = $rest_server->dispatch($req_after_trash);
check($res_after_trash->get_status() === 404, 'GET /ezev/v1/stations/{id} returns 404 after station is trashed');

$resolved_after_trash = EZEV_Core_Stations::get_station_id_by_slug($actual_slug);
check($resolved_after_trash === null, 'Slug resolver returns null after station is trashed');

$res_list_after = $rest_server->dispatch(new WP_REST_Request('GET', '/ezev/v1/stations'));
$stations_after = $res_list_after->get_data()['stations'] ?? [];
$found_after = false;
foreach ($stations_after as $s) {
    if (($s['station_id'] ?? '') === $unique_id) {
        $found_after = true;
        break;
    }
}
check(!$found_after, 'Trashed station is completely removed from public collection');

// Clean up test post permanently
wp_delete_post($created_post_id, true);

echo "\nSUMMARY: $checks checks, $failed failures.\n";
if ($failed > 0) {
    exit(1);
}
echo "ALL P0 FRONTEND LIFECYCLE CHECKS PASSED!\n";
exit(0);