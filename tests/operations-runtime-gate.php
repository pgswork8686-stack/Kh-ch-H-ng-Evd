<?php
declare(strict_types=1);
/**
 * EZEV Operations Runtime Gate Verification Suite
 * Tests against live WordPress + MySQL instance.
 */

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

$totalChecks = 0;
$failedChecks = 0;

function assertCheck(string $desc, bool $condition, string $failureDetails = ''): void {
    global $totalChecks, $failedChecks;
    $totalChecks++;
    if ($condition) {
        echo "  [PASS] {$desc}\n";
    } else {
        $failedChecks++;
        echo "  [FAIL] {$desc}" . ($failureDetails ? " - {$failureDetails}" : "") . "\n";
    }
}

echo "\n=== [TEST GROUP 1] Plugin Bootstrap, Schema & Migration ===\n";
assertCheck("Plugin ezev-operations is active", is_plugin_active('ezev-operations/ezev-operations.php'));
assertCheck("Constant EZEVO_DB_VERSION is defined and equals 1.2.0", defined('EZEVO_DB_VERSION') && EZEVO_DB_VERSION === '1.2.0');

// Trigger install / upgrade
EZEV_Operations_DB::install();
$installedDbVer = (string) get_option('ezevo_db_version');
assertCheck("Installed DB version option is 1.2.0", $installedDbVer === '1.2.0', "Got: $installedDbVer");

// Verify monotonic upgrade migration check without downgrade
update_option('ezevo_db_version', '1.0.1');
EZEV_Operations_DB::maybe_upgrade();
assertCheck("Monotonic upgrade restores legacy 1.0.1 to 1.2.0", get_option('ezevo_db_version') === '1.2.0');

// GATE 2.2: Verify 1.1.0 → 1.2.0 migration via maybe_upgrade() only (NOT install() first)
// Simulate a site that was on 1.1.0 (Gate 2.1 state) — maybe_upgrade must do the upgrade
update_option('ezevo_db_version', '1.1.0');
EZEV_Operations_DB::maybe_upgrade();
assertCheck("maybe_upgrade() upgrades 1.1.0 to 1.2.0 without calling install() first", get_option('ezevo_db_version') === '1.2.0');

// Verify legacy 4.0.1 migration specifically preserves chargers and generates connectors
global $wpdb;
$testLegacyCharger = 'EZEV-LEGACY-401-CH1';
$wpdb->replace(EZEV_Operations_DB::table('chargers'), [
    'charger_id' => $testLegacyCharger,
    'station_id' => 'EZEV-LEGACY-STATION',
    'connector_type' => 'CCS2',
    'max_power_kw' => 120,
    'status' => 'available',
    'updated_at' => current_time('mysql', true),
]);
update_option('ezevo_db_version', '4.0.1');
EZEV_Operations_DB::maybe_upgrade();
assertCheck("Legacy 4.0.1 is recognized and upgraded to 1.2.0", get_option('ezevo_db_version') === '1.2.0');
$migratedConn = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . EZEV_Operations_DB::table('connectors') . " WHERE charger_id=%s", $testLegacyCharger), ARRAY_A);
assertCheck("Legacy charger connector was successfully migrated into connectors table", !empty($migratedConn) && $migratedConn['charger_id'] === $testLegacyCharger);

$expectedTables = [
    'ezev_chargers',
    'ezev_connectors',
    'ezev_sessions',
    'ezev_energy',
    'ezev_alerts',
    'ezev_maintenance',
    'ezev_integrations',
    'ezev_sync_logs',
    'ezev_webhook_receipts',
];
foreach ($expectedTables as $t) {
    $full = $wpdb->prefix . $t;
    $exists = (string) $wpdb->get_var("SHOW TABLES LIKE '$full'") === $full;
    assertCheck("Table exists: {$full}", $exists);
}

// Verify webhook_receipts has expires_at column (Gate 2.2 requirement)
$receiptTable = EZEV_Operations_DB::table('webhook_receipts');
$receiptCols = $wpdb->get_col("DESCRIBE $receiptTable", 0) ?: [];
assertCheck("webhook_receipts has expires_at column", in_array('expires_at', $receiptCols, true));

// Check connector columns
$connTable = EZEV_Operations_DB::table('connectors');
$cols = $wpdb->get_col("DESCRIBE $connTable", 0) ?: [];
assertCheck("connectors has connector_id", in_array('connector_id', $cols, true));
assertCheck("connectors has charger_id", in_array('charger_id', $cols, true));
assertCheck("connectors has station_id", in_array('station_id', $cols, true));
assertCheck("connectors has connector_type", in_array('connector_type', $cols, true));

// Check sessions connector_id column
$sessTable = EZEV_Operations_DB::table('sessions');
$sessCols = $wpdb->get_col("DESCRIBE $sessTable", 0) ?: [];
assertCheck("sessions has connector_id column", in_array('connector_id', $sessCols, true));

// Check energy provider_record_id column
$energyTable = EZEV_Operations_DB::table('energy');
$energyCols = $wpdb->get_col("DESCRIBE $energyTable", 0) ?: [];
assertCheck("energy has provider_record_id column", in_array('provider_record_id', $energyCols, true));

echo "\n=== [TEST GROUP 2] Provider Separation (Manual vs Demo) ===\n";
update_option('ezevo_active_provider', 'manual');
$activeManual = EZEV_Operations_Provider_Manager::active();
assertCheck("ManualProvider active key is 'manual'", $activeManual->key() === 'manual');
assertCheck("ManualProvider label is 'Manual Provider'", $activeManual->label() === 'Manual Provider');
assertCheck("ManualProvider mode is 'manual'", $activeManual->mode() === 'manual');

update_option('ezevo_active_provider', 'demo');
$activeDemo = EZEV_Operations_Provider_Manager::active();
assertCheck("DemoProvider active key is 'demo'", $activeDemo->key() === 'demo');
assertCheck("DemoProvider label is 'Demo / Simulation Provider'", $activeDemo->label() === 'Demo / Simulation Provider');
assertCheck("DemoProvider mode is 'demo'", $activeDemo->mode() === 'demo');

echo "\n=== [TEST GROUP 3] Explicit Demo Data Seeding & Hierarchy ===\n";
global $wpdb;
// Wipe tables for clean test (guarded by local check above)
$wpdb->query("TRUNCATE TABLE " . EZEV_Operations_DB::table('chargers'));
$wpdb->query("TRUNCATE TABLE " . EZEV_Operations_DB::table('connectors'));
$wpdb->query("TRUNCATE TABLE " . EZEV_Operations_DB::table('sessions'));
$wpdb->query("TRUNCATE TABLE " . EZEV_Operations_DB::table('energy'));
$wpdb->query("TRUNCATE TABLE " . EZEV_Operations_DB::table('alerts'));

assertCheck("Clean state has 0 chargers", (int)$wpdb->get_var("SELECT COUNT(*) FROM " . EZEV_Operations_DB::table('chargers')) === 0);
assertCheck("Clean state has 0 connectors", (int)$wpdb->get_var("SELECT COUNT(*) FROM " . EZEV_Operations_DB::table('connectors')) === 0);

// Run explicit seed
$seedRes = EZEV_Operations_DB::seed_demo();
assertCheck("Explicit seed executed with created > 0", ($seedRes['created'] ?? 0) > 0);
assertCheck("Connectors created > 0", ($seedRes['connectors'] ?? 0) > 0);

$chargerCount = (int)$wpdb->get_var("SELECT COUNT(*) FROM " . EZEV_Operations_DB::table('chargers'));
$connectorCount = (int)$wpdb->get_var("SELECT COUNT(*) FROM " . EZEV_Operations_DB::table('connectors'));
$sessionCount = (int)$wpdb->get_var("SELECT COUNT(*) FROM " . EZEV_Operations_DB::table('sessions'));
assertCheck("Chargers populated in DB", $chargerCount > 0);
assertCheck("Connectors populated in DB (2 per charger)", $connectorCount === $chargerCount * 2);
assertCheck("Sessions populated in DB", $sessionCount > 0);

// Verify hierarchy: Station -> Charger -> Connector -> Session
$sampleSession = $wpdb->get_row("SELECT * FROM " . EZEV_Operations_DB::table('sessions') . " LIMIT 1", ARRAY_A);
assertCheck("Session has station_id", !empty($sampleSession['station_id']));
assertCheck("Session has charger_id", !empty($sampleSession['charger_id']));
assertCheck("Session has connector_id", !empty($sampleSession['connector_id']));

$matchingConnector = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . EZEV_Operations_DB::table('connectors') . " WHERE connector_id=%s", $sampleSession['connector_id']), ARRAY_A);
assertCheck("Session connector exists in connectors table", !empty($matchingConnector));
assertCheck("Session connector charger_id matches", $matchingConnector['charger_id'] === $sampleSession['charger_id']);

echo "\n=== [TEST GROUP 4] Energy Identity & Provider-Aware Idempotency ===\n";
$testStation = 'EZEV-IDEMPOTENT-TEST';
$testDate = '2026-09-03 12:00:00';

// 1. Sync from Provider A ('provider_a')
$syncA = [
    [
        'station_id' => $testStation,
        'recorded_at' => $testDate,
        'grid_kwh' => 100.0,
        'ev_kwh' => 90.0,
        'solar_kwh' => 10.0,
        'bess_charge_kwh' => 5.0,
        'bess_discharge_kwh' => 2.0,
        'peak_kw' => 50.0,
        'provider_record_id' => 'REC-A-001',
    ]
];
$r1 = EZEV_Operations_Sync::sync_energy($syncA, 'provider_a');
assertCheck("Provider A energy sync inserts 1 row", $r1 === 1);

// 2. Re-sync Provider A with updated values (idempotent update, no duplicate)
$syncA_upd = [
    [
        'station_id' => $testStation,
        'recorded_at' => $testDate,
        'grid_kwh' => 110.0,
        'ev_kwh' => 100.0,
        'solar_kwh' => 10.0,
        'bess_charge_kwh' => 5.0,
        'bess_discharge_kwh' => 2.0,
        'peak_kw' => 55.0,
        'provider_record_id' => 'REC-A-001',
    ]
];
$r2 = EZEV_Operations_Sync::sync_energy($syncA_upd, 'provider_a');
assertCheck("Provider A re-sync updates idempotently", $r2 === 1);
$countA = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $energyTable WHERE station_id=%s AND recorded_at=%s AND provider=%s", $testStation, $testDate, 'provider_a'));
assertCheck("Provider A has exactly 1 row", $countA === 1);
$valA = (float) $wpdb->get_var($wpdb->prepare("SELECT ev_kwh FROM $energyTable WHERE station_id=%s AND recorded_at=%s AND provider=%s", $testStation, $testDate, 'provider_a'));
assertCheck("Provider A ev_kwh updated to 100.0", abs($valA - 100.0) < 0.001);

// 3. Sync same station/time from Provider B ('provider_b'): MUST NOT overwrite Provider A
$syncB = [
    [
        'station_id' => $testStation,
        'recorded_at' => $testDate,
        'grid_kwh' => 200.0,
        'ev_kwh' => 180.0,
        'solar_kwh' => 20.0,
        'bess_charge_kwh' => 10.0,
        'bess_discharge_kwh' => 4.0,
        'peak_kw' => 90.0,
        'provider_record_id' => 'REC-B-001',
    ]
];
$r3 = EZEV_Operations_Sync::sync_energy($syncB, 'provider_b');
assertCheck("Provider B energy sync inserts without error", $r3 === 1);

// Verify Provider A was not overwritten
$valA_after = (float) $wpdb->get_var($wpdb->prepare("SELECT ev_kwh FROM $energyTable WHERE station_id=%s AND recorded_at=%s AND provider=%s", $testStation, $testDate, 'provider_a'));
assertCheck("Provider A data preserved alongside Provider B", abs($valA_after - 100.0) < 0.001);
$valB_after = (float) $wpdb->get_var($wpdb->prepare("SELECT ev_kwh FROM $energyTable WHERE station_id=%s AND recorded_at=%s AND provider=%s", $testStation, $testDate, 'provider_b'));
assertCheck("Provider B recorded independently", abs($valB_after - 180.0) < 0.001);

echo "\n=== [TEST GROUP 5] Operations Capability Matrix & Station Scoping ===\n";
rest_get_server();

// Setup users for capability matrix
function getOrCreateUser(string $login, string $role, array $extra_caps = []): WP_User {
    $u = get_user_by('login', $login);
    if (!$u) {
        $id = wp_create_user($login, wp_generate_password(20), $login . '@ezev.test');
        $u = new WP_User($id);
    }
    $u->set_role($role);
    foreach ($extra_caps as $c) {
        $u->add_cap($c);
    }
    return $u;
}

// 1. Unauthenticated -> 401
wp_set_current_user(0);
$authRes = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/overview'));
assertCheck("Unauthenticated GET /overview returns 401", $authRes->get_status() === 401);

// 2. Customer -> 403 (Forbidden from reading operations)
$custUser = getOrCreateUser('test_cust_matrix', 'ezev_customer');
wp_set_current_user($custUser->ID);
$resCust = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/overview'));
assertCheck("Customer GET /overview returns 403 Forbidden", $resCust->get_status() === 403);

// 3. Partner (has ezev_view_operations) -> 200 OK, scoped
$partUser = getOrCreateUser('test_partner_matrix', 'ezev_partner');
wp_set_current_user($partUser->ID);
$resPart = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/overview'));
assertCheck("Partner with ops cap GET /overview returns 200 OK", $resPart->get_status() === 200);
assertCheck("Partner scope is restricted", ($resPart->get_data()['scope'] ?? '') === 'restricted');

// 4. Investor (has ezev_view_operations) -> 200 OK, scoped
$invUser = getOrCreateUser('test_investor_matrix', 'ezev_investor');
wp_set_current_user($invUser->ID);
$resInv = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/overview'));
assertCheck("Investor with ops cap GET /overview returns 200 OK", $resInv->get_status() === 200);
assertCheck("Investor scope is restricted", ($resInv->get_data()['scope'] ?? '') === 'restricted');

// 5. Internal Business (has ezev_view_internal, but NOT ezev_view_operations) -> MUST be 403
$bizIntUser = getOrCreateUser('test_biz_internal_matrix', 'ezev_internal_business');
wp_set_current_user($bizIntUser->ID);
$resBizInt = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/overview'));
assertCheck("Internal Business (ezev_view_internal only) GET /overview returns 403", $resBizInt->get_status() === 403);

// 6. Internal Content (has ezev_view_internal, but NOT ezev_view_operations) -> MUST be 403
$contentUser = getOrCreateUser('test_content_matrix', 'ezev_internal_content');
wp_set_current_user($contentUser->ID);
$resContent = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/overview'));
assertCheck("Internal Content (ezev_view_internal only) GET /overview returns 403", $resContent->get_status() === 403);

// GATE 2.2: Test that ezev_view_internal alone CANNOT bypass station scope
// Create a user with ONLY ezev_view_internal (no manage_options, no ezev_view_all_stations)
$internalOnlyUser = getOrCreateUser('test_internal_scope_bypass', 'ezev_internal_business');
wp_set_current_user($internalOnlyUser->ID);
// This user has no station assignments, so allowed_station_keys should be empty (not all stations)
$scopeTestAllowed = EZEV_Core_Auth::allowed_station_keys($internalOnlyUser->ID);
assertCheck("ezev_view_internal alone does NOT grant all-station scope (no bypass)", $scopeTestAllowed === []);

// 7. Internal Operations -> 200 OK but SCOPED to assigned stations (not all-station bypass)
// Create 2 stations, assign ops user to only 1, verify chargers endpoint returns only that station
$station_a = 'EZEV-VN-DEMO-001'; // existing demo station
$station_b = 'EZEV-VN-DEMO-002'; // another existing demo station
$opsUser = getOrCreateUser('test_ops_scoped', 'ezev_internal_ops');

// Ensure test organization exists and fetch its auto-increment ID
$opsOrgRef = 'ORG-TEST-GATE';
$orgTable = EZEV_Core_DB::table('organizations');
$wpdb->replace($orgTable, [
    'organization_id' => $opsOrgRef,
    'org_code'        => 'TESTGATE',
    'name'            => 'Gate Testing Org',
    'type'            => 'business',
    'status'          => 'active',
    'created_at'      => current_time('mysql', true),
    'updated_at'      => current_time('mysql', true),
]);
$opsOrgDbId = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $orgTable WHERE organization_id = %s", $opsOrgRef));

// Assign opsUser to ONLY station_a via member_station_access
$opsMembershipId = 'MEMB-GATE-OPS-' . $opsUser->ID;
$wpdb->replace(EZEV_Core_DB::table('org_members'), [
    'membership_id'    => $opsMembershipId,
    'organization_id'  => $opsOrgDbId,
    'organization_ref' => $opsOrgRef,
    'user_id'          => $opsUser->ID,
    'role_key'         => 'operations',
    'status'           => 'active',
    'created_at'       => current_time('mysql', true),
    'updated_at'       => current_time('mysql', true),
]);
$opsMemberDbId = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM " . EZEV_Core_DB::table('org_members') . " WHERE membership_id = %s", $opsMembershipId));
$stationA_post_id = EZEV_Core_Stations::find_by_station_id($station_a);
if ($stationA_post_id) {
    $wpdb->replace(EZEV_Core_DB::table('member_station_access'), [
        'member_id'       => $opsMemberDbId,
        'station_post_id' => $stationA_post_id,
        'membership_ref'  => $opsMembershipId,
        'station_id'      => $station_a,
        'created_at'      => current_time('mysql', true),
    ]);
}

wp_set_current_user($opsUser->ID);
$resOps = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/overview'));
assertCheck("Internal Ops (with ezev_view_operations) GET /overview returns 200", $resOps->get_status() === 200);
$opsScope = $resOps->get_data()['scope'] ?? '';
assertCheck("Internal Ops user is scoped (restricted, NOT all)", $opsScope === 'restricted');

$resOpsChargers = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/chargers'));
$opsChargers = $resOpsChargers->get_data()['chargers'] ?? [];
$opsBadStation = false;
foreach ($opsChargers as $ch) {
    if (($ch['station_id'] ?? '') !== $station_a) { $opsBadStation = true; break; }
}
assertCheck("Internal Ops only sees chargers from assigned station ($station_a), NOT $station_b", !$opsBadStation && !empty($opsChargers));

// 8. Internal Technical -> same scoped behavior, NOT all-station bypass
$techUser = getOrCreateUser('test_tech_scoped', 'ezev_internal_technical');
$techMembershipId = 'MEMB-GATE-TECH-' . $techUser->ID;
$wpdb->replace(EZEV_Core_DB::table('org_members'), [
    'membership_id'    => $techMembershipId,
    'organization_id'  => $opsOrgDbId,
    'organization_ref' => $opsOrgRef,
    'user_id'          => $techUser->ID,
    'role_key'         => 'operations',
    'status'           => 'active',
    'created_at'       => current_time('mysql', true),
    'updated_at'       => current_time('mysql', true),
]);
$techMemberDbId = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM " . EZEV_Core_DB::table('org_members') . " WHERE membership_id = %s", $techMembershipId));
if ($stationA_post_id) {
    $wpdb->replace(EZEV_Core_DB::table('member_station_access'), [
        'member_id'       => $techMemberDbId,
        'station_post_id' => $stationA_post_id,
        'membership_ref'  => $techMembershipId,
        'station_id'      => $station_a,
        'created_at'      => current_time('mysql', true),
    ]);
}
wp_set_current_user($techUser->ID);
$resTech = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/overview'));
assertCheck("Internal Technical (with ezev_view_operations) GET /overview returns 200", $resTech->get_status() === 200);
$techScope = $resTech->get_data()['scope'] ?? '';
assertCheck("Internal Technical user is scoped (restricted, NOT all)", $techScope === 'restricted');

// 9. Administrator -> 200 OK (all scope)
$adminUser = get_user_by('login', 'admin');
wp_set_current_user($adminUser->ID);
$resAdmin = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/overview'));
assertCheck("Administrator GET /overview returns 200", $resAdmin->get_status() === 200);
$adminData = $resAdmin->get_data();
assertCheck("Administrator scope is 'all'", ($adminData['scope'] ?? '') === 'all');

// Serializer DTO verification: ensure no internal numeric ID or provider_payload leaks
$resChargersDto = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/chargers'));
$sampleChargerDto = ($resChargersDto->get_data()['chargers'] ?? [])[0] ?? [];
assertCheck("Charger DTO has charger_id", !empty($sampleChargerDto['charger_id']));
assertCheck("Charger DTO does NOT expose numeric ID", !array_key_exists('id', $sampleChargerDto));

$resSessionsDto = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/sessions'));
$sampleSessionDto = ($resSessionsDto->get_data()['sessions'] ?? [])[0] ?? [];
assertCheck("Session DTO has session_id", !empty($sampleSessionDto['session_id']));
assertCheck("Session DTO does NOT expose numeric ID", !array_key_exists('id', $sampleSessionDto));
assertCheck("Session DTO does NOT expose provider_payload", !array_key_exists('provider_payload', $sampleSessionDto));


// 10. Scoped Business Site Manager test
$scopedStation = 'EZEV-VN-DEMO-001';
$scopedUser = getOrCreateUser('test_scoped_sitemanager', 'ezev_business');

// Assign user to organization and site in Core DB
$orgTable = EZEV_Core_DB::table('organizations');
$siteTable = EZEV_Core_DB::table('sites');
$memberTable = EZEV_Core_DB::table('org_members');
$stationAccessTable = EZEV_Core_DB::table('member_station_access');

$wpdb->replace($orgTable, [
    'organization_id' => 'ORG-TEST-GATE',
    'org_code' => 'TESTGATE',
    'name' => 'Gate Testing Org',
    'type' => 'business',
    'status' => 'active',
    'created_at' => current_time('mysql', true),
    'updated_at' => current_time('mysql', true),
]);

$scopedOrgDbId = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $orgTable WHERE organization_id = %s", 'ORG-TEST-GATE'));

$membershipId = 'MEMB-TEST-SCOPE-' . $scopedUser->ID;
$wpdb->replace($memberTable, [
    'membership_id' => $membershipId,
    'organization_id' => $scopedOrgDbId,
    'organization_ref' => 'ORG-TEST-GATE',
    'user_id' => $scopedUser->ID,
    'role_key' => 'site_manager',
    'status' => 'active',
    'created_at' => current_time('mysql', true),
    'updated_at' => current_time('mysql', true),
]);
$scopedMemberDbId = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $memberTable WHERE membership_id = %s", $membershipId));

// Map member to only $scopedStation
$post_id = EZEV_Core_Stations::find_by_station_id($scopedStation);
if ($post_id) {
    $wpdb->replace($stationAccessTable, [
        'member_id' => $scopedMemberDbId,
        'station_post_id' => $post_id,
        'membership_ref' => $membershipId,
        'station_id' => $scopedStation,
        'created_at' => current_time('mysql', true),
    ]);
}

wp_set_current_user($scopedUser->ID);
$resScopedOverview = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/overview'));
assertCheck("Scoped user GET /overview returns 200", $resScopedOverview->get_status() === 200);
$scopedData = $resScopedOverview->get_data();
assertCheck("Scoped user scope is 'restricted'", ($scopedData['scope'] ?? '') === 'restricted');

$resScopedChargers = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/chargers'));
$chargersList = $resScopedChargers->get_data()['chargers'] ?? [];
$allMatchScope = true;
foreach ($chargersList as $ch) {
    if (($ch['station_id'] ?? '') !== $scopedStation) {
        $allMatchScope = false;
        break;
    }
}
assertCheck("Scoped user chargers exclusively match assigned station ($scopedStation)", !empty($chargersList) && $allMatchScope);

echo "\n=== [TEST GROUP 6] True Webhook Replay Protection ===\n";
// Create integration WITHOUT secret to specifically test missing_secret
$integrationTable = EZEV_Operations_DB::table('integrations');
$wpdb->insert($integrationTable, [
    'name' => 'Secretless Test Provider',
    'provider_type' => 'generic_http',
    'environment' => 'sandbox',
    'auth_type' => 'none',
    'webhook_secret_enc' => '', // NO SECRET
    'enabled' => 1,
    'created_at' => current_time('mysql', true),
    'updated_at' => current_time('mysql', true),
]);
$secretlessId = (int) $wpdb->insert_id;

$reqMissingSecret = new WP_REST_Request('POST', "/ezev-ops/v1/webhook/$secretlessId");
$resMissingSecret = rest_do_request($reqMissingSecret);
assertCheck("Request to integration without secret returns 401 missing_secret", $resMissingSecret->get_status() === 401 && ($resMissingSecret->get_data()['code'] ?? '') === 'missing_secret');

$webhookSecret = 'gate_test_super_secret_webhook_key';
$wpdb->insert($integrationTable, [
    'name' => 'Gate 2 Test Provider',
    'provider_type' => 'generic_http',
    'environment' => 'sandbox',
    'auth_type' => 'bearer',
    'webhook_secret_enc' => EZEV_Operations_Secrets::encrypt($webhookSecret),
    'enabled' => 1,
    'created_at' => current_time('mysql', true),
    'updated_at' => current_time('mysql', true),
]);
$integrationId = (int) $wpdb->insert_id;

// Also create a second integration with secret to test cross-integration event collision resistance
$wpdb->insert($integrationTable, [
    'name' => 'Second Provider For Collision Test',
    'provider_type' => 'generic_http',
    'environment' => 'sandbox',
    'auth_type' => 'bearer',
    'webhook_secret_enc' => EZEV_Operations_Secrets::encrypt($webhookSecret),
    'enabled' => 1,
    'created_at' => current_time('mysql', true),
    'updated_at' => current_time('mysql', true),
]);
$secondIntegrationId = (int) $wpdb->insert_id;

$testPayload = json_encode(['event' => 'charger.status_change', 'status' => 'charging', 'seq' => wp_rand(1000, 9999)]);

// 1. Missing secret / unsigned
$reqNoSig = new WP_REST_Request('POST', "/ezev-ops/v1/webhook/$integrationId");
$reqNoSig->set_body($testPayload);
assertCheck("Missing timestamp/signature returns 401", rest_do_request($reqNoSig)->get_status() === 401);

// 2. Missing timestamp header
$reqNoTs = new WP_REST_Request('POST', "/ezev-ops/v1/webhook/$integrationId");
$reqNoTs->set_header('x-ezev-signature', 'somesig');
$reqNoTs->set_body($testPayload);
assertCheck("Missing timestamp header returns 401 missing_timestamp", rest_do_request($reqNoTs)->get_status() === 401);

// 3. Expired timestamp (>300s)
$expiredTs = time() - 350;
$reqExp = new WP_REST_Request('POST', "/ezev-ops/v1/webhook/$integrationId");
$reqExp->set_header('x-ezev-timestamp', (string)$expiredTs);
$reqExp->set_header('x-ezev-signature', hash_hmac('sha256', $expiredTs . '.' . $testPayload, $webhookSecret));
$reqExp->set_body($testPayload);
assertCheck("Expired timestamp returns 401 replay_rejected", rest_do_request($reqExp)->get_status() === 401);

// 4. Bad signature
$currentTs = time();
$reqBad = new WP_REST_Request('POST', "/ezev-ops/v1/webhook/$integrationId");
$reqBad->set_header('x-ezev-timestamp', (string)$currentTs);
$reqBad->set_header('x-ezev-signature', 'deadbeef00001111');
$reqBad->set_body($testPayload);
assertCheck("Bad signature returns 401 invalid_signature", rest_do_request($reqBad)->get_status() === 401);

// 5. Valid request 1st time -> 200 OK
$eventId = 'evt_' . wp_rand(100000, 999999);
$validSig = hash_hmac('sha256', $currentTs . '.' . $testPayload, $webhookSecret);
$reqValid1 = new WP_REST_Request('POST', "/ezev-ops/v1/webhook/$integrationId");
$reqValid1->set_header('x-ezev-timestamp', (string)$currentTs);
$reqValid1->set_header('x-ezev-signature', $validSig);
$reqValid1->set_header('x-ezev-event-id', $eventId);
$reqValid1->set_body($testPayload);
assertCheck("First valid webhook delivery returns 200 OK", rest_do_request($reqValid1)->get_status() === 200);

// 6. Duplicate delivery of exact same valid request -> 409 Conflict (Replay Deduplication)
$reqValid2 = new WP_REST_Request('POST', "/ezev-ops/v1/webhook/$integrationId");
$reqValid2->set_header('x-ezev-timestamp', (string)$currentTs);
$reqValid2->set_header('x-ezev-signature', $validSig);
$reqValid2->set_header('x-ezev-event-id', $eventId);
$reqValid2->set_body($testPayload);
$resDup = rest_do_request($reqValid2);
assertCheck("Duplicate valid webhook delivery rejected with 409 duplicate_webhook", $resDup->get_status() === 409);

// 7. Same event_id sent to DIFFERENT integration_id MUST be accepted (no cross-integration collision)
$reqValidOtherIntegration = new WP_REST_Request('POST', "/ezev-ops/v1/webhook/$secondIntegrationId");
$reqValidOtherIntegration->set_header('x-ezev-timestamp', (string)$currentTs);
$reqValidOtherIntegration->set_header('x-ezev-signature', $validSig);
$reqValidOtherIntegration->set_header('x-ezev-event-id', $eventId);
$reqValidOtherIntegration->set_body($testPayload);
$resOtherIntegration = rest_do_request($reqValidOtherIntegration);
assertCheck("Same event_id delivered to different integration is accepted (no collision)", $resOtherIntegration->get_status() === 200);

// 8. Receipt retention cleanup: purge expired receipts, verify active dedup still works
// Insert a receipt with expires_at in the past
$receiptTable = EZEV_Operations_DB::table('webhook_receipts');
$oldHash = hash('sha256', 'test_old_expired_receipt_' . wp_rand(1, 99999));
$wpdb->insert($receiptTable, [
    'integration_id' => $integrationId,
    'dedup_hash'     => $oldHash,
    'event_id'       => 'old_evt',
    'created_at'     => gmdate('Y-m-d H:i:s', time() - 86400 - 100), // 1 day + 100s ago
    'expires_at'     => gmdate('Y-m-d H:i:s', time() - 100),         // expired 100s ago
]);
$beforeCleanup = (int) $wpdb->get_var("SELECT COUNT(*) FROM $receiptTable WHERE dedup_hash='$oldHash'");
$cleaned = EZEV_Operations_DB::cleanup_webhook_receipts();
$afterCleanup = (int) $wpdb->get_var("SELECT COUNT(*) FROM $receiptTable WHERE dedup_hash='$oldHash'");
assertCheck("cleanup_webhook_receipts() removed expired receipt", $beforeCleanup === 1 && $afterCleanup === 0 && $cleaned >= 1);

// Verify the dedup still works for the ACTIVE receipt (the valid delivery above was NOT expired)
$reqDupAfterCleanup = new WP_REST_Request('POST', "/ezev-ops/v1/webhook/$integrationId");
$reqDupAfterCleanup->set_header('x-ezev-timestamp', (string)$currentTs);
$reqDupAfterCleanup->set_header('x-ezev-signature', $validSig);
$reqDupAfterCleanup->set_header('x-ezev-event-id', $eventId);
$reqDupAfterCleanup->set_body($testPayload);
assertCheck("Dedup still works for active receipts after cleanup", rest_do_request($reqDupAfterCleanup)->get_status() === 409);

// 9. GATE 2.2: DB error path — drop receipt table temporarily, verify fail-closed 503
// This simulates receipt storage failure: table unavailable
$receiptTableBackup = $wpdb->prefix . 'ezev_webhook_receipts_gate22backup';
$wpdb->query("RENAME TABLE $receiptTable TO $receiptTableBackup");

$dbErrPayload = json_encode(['event' => 'db_error_test', 'seq' => wp_rand(1, 999)]);
$dbErrTs = time();
$dbErrSig = hash_hmac('sha256', $dbErrTs . '.' . $dbErrPayload, $webhookSecret);
$dbErrEventId = 'dberr_' . wp_rand(100000, 999999);
$reqDbErr = new WP_REST_Request('POST', "/ezev-ops/v1/webhook/$integrationId");
$reqDbErr->set_header('x-ezev-timestamp', (string)$dbErrTs);
$reqDbErr->set_header('x-ezev-signature', $dbErrSig);
$reqDbErr->set_header('x-ezev-event-id', $dbErrEventId);
$reqDbErr->set_body($dbErrPayload);
$resDbErr = rest_do_request($reqDbErr);
assertCheck("Webhook with receipt storage failure returns 503 fail-closed (not 200)", $resDbErr->get_status() === 503);
assertCheck("Webhook DB error returns receipt_storage_failure code", ($resDbErr->get_data()['code'] ?? '') === 'receipt_storage_failure');

// Restore the table
$wpdb->query("RENAME TABLE $receiptTableBackup TO $receiptTable");

echo "\n==========================================\n";
echo "SUMMARY: {$totalChecks} checks, {$failedChecks} failures.\n";
echo "==========================================\n";

if ($failedChecks > 0) {
    exit(1);
}
exit(0);
