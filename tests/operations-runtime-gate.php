<?php
declare(strict_types=1);
/**
 * EZEV Operations Runtime Gate Verification Suite
 * Tests against live WordPress + MySQL instance.
 */

require_once 'C:/Users/Admin/Local Sites/test-2/app/public/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

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
assertCheck("Constant EZEVO_DB_VERSION is defined and equals 1.1.0", defined('EZEVO_DB_VERSION') && EZEVO_DB_VERSION === '1.1.0');

// Trigger install / upgrade
EZEV_Operations_DB::install();
$installedDbVer = (string) get_option('ezevo_db_version');
assertCheck("Installed DB version option is 1.1.0", $installedDbVer === '1.1.0', "Got: $installedDbVer");

global $wpdb;
$expectedTables = [
    'ezev_chargers',
    'ezev_connectors',
    'ezev_sessions',
    'ezev_energy',
    'ezev_alerts',
    'ezev_maintenance',
    'ezev_integrations',
    'ezev_sync_logs',
];
foreach ($expectedTables as $t) {
    $full = $wpdb->prefix . $t;
    $exists = (string) $wpdb->get_var("SHOW TABLES LIKE '$full'") === $full;
    assertCheck("Table exists: {$full}", $exists);
}

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
// Wipe tables for clean test
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

echo "\n=== [TEST GROUP 4] Energy Sync Idempotency ===\n";
$testStation = 'EZEV-IDEMPOTENT-TEST';
$testDate = '2026-09-03 12:00:00';
$energyTable = EZEV_Operations_DB::table('energy');

// First sync entry
$syncRows = [
    [
        'station_id' => $testStation,
        'recorded_at' => $testDate,
        'grid_kwh' => 100.5,
        'ev_kwh' => 95.0,
        'solar_kwh' => 10.0,
        'bess_charge_kwh' => 5.0,
        'bess_discharge_kwh' => 2.0,
        'peak_kw' => 50.0,
    ]
];
$r1 = EZEV_Operations_Sync::sync_energy($syncRows, 'test');
assertCheck("First energy sync inserts 1 row", $r1 === 1);
$countBefore = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $energyTable WHERE station_id=%s AND recorded_at=%s", $testStation, $testDate));
assertCheck("Energy record exists exactly once", $countBefore === 1);

// Second sync entry with exact same station and recorded_at but updated values
$syncRowsUpdated = [
    [
        'station_id' => $testStation,
        'recorded_at' => $testDate,
        'grid_kwh' => 120.0,
        'ev_kwh' => 110.0,
        'solar_kwh' => 10.0,
        'bess_charge_kwh' => 5.0,
        'bess_discharge_kwh' => 2.0,
        'peak_kw' => 55.0,
    ]
];
$r2 = EZEV_Operations_Sync::sync_energy($syncRowsUpdated, 'test');
assertCheck("Second energy sync runs without error", $r2 === 1);
$countAfter = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $energyTable WHERE station_id=%s AND recorded_at=%s", $testStation, $testDate));
assertCheck("Energy record remains exactly 1 row (NO duplicate INSERT)", $countAfter === 1);
$valAfter = (float) $wpdb->get_var($wpdb->prepare("SELECT ev_kwh FROM $energyTable WHERE station_id=%s AND recorded_at=%s", $testStation, $testDate));
assertCheck("Energy record value was idempotently updated", abs($valAfter - 110.0) < 0.001);

echo "\n=== [TEST GROUP 5] REST API Authorization & Scoping ===\n";
// Ensure REST server loaded
rest_get_server();

// 1. Unauthenticated request
wp_set_current_user(0);
$authReq = new WP_REST_Request('GET', '/ezev-ops/v1/overview');
$authRes = rest_do_request($authReq);
assertCheck("Unauthenticated GET /ezev-ops/v1/overview returns 401", $authRes->get_status() === 401);

// 2. User without ezev_view_operations (e.g. ezev_customer)
$customerId = (int) $wpdb->get_var("SELECT ID FROM {$wpdb->users} WHERE user_login='customer_demo'");
if (!$customerId) {
    $customerId = wp_create_user('customer_demo', 'DemoPass123!@#', 'customer_demo@ezev.test');
    $u = new WP_User($customerId);
    $u->set_role('ezev_customer');
}
wp_set_current_user($customerId);
$custReq = new WP_REST_Request('GET', '/ezev-ops/v1/overview');
$custRes = rest_do_request($custReq);
assertCheck("Customer GET /ezev-ops/v1/overview returns 403 Forbidden", $custRes->get_status() === 403);

// 3. User with ezev_view_operations (e.g. administrator or internal ops)
$adminUser = get_user_by('login', 'admin');
wp_set_current_user($adminUser->ID);
$adminReq = new WP_REST_Request('GET', '/ezev-ops/v1/overview');
$adminRes = rest_do_request($adminReq);
assertCheck("Admin GET /ezev-ops/v1/overview returns 200 OK", $adminRes->get_status() === 200);

$chargersReq = new WP_REST_Request('GET', '/ezev-ops/v1/chargers');
$chargersRes = rest_do_request($chargersReq);
assertCheck("Admin GET /ezev-ops/v1/chargers returns 200 OK", $chargersRes->get_status() === 200);

$connReq = new WP_REST_Request('GET', '/ezev-ops/v1/connectors');
$connRes = rest_do_request($connReq);
assertCheck("Admin GET /ezev-ops/v1/connectors returns 200 OK", $connRes->get_status() === 200);
$connData = $connRes->get_data();
assertCheck("Connectors payload contains connectors key", isset($connData['connectors']));

echo "\n=== [TEST GROUP 6] Webhook Security (Secret, Signature & Replay Protection) ===\n";
// Insert test integration with secret
$webhookSecret = 'test_super_secret_webhook_key_123';
$integrationTable = EZEV_Operations_DB::table('integrations');
$wpdb->insert($integrationTable, [
    'name' => 'Automated Test Provider',
    'provider_type' => 'generic_http',
    'environment' => 'sandbox',
    'auth_type' => 'bearer',
    'webhook_secret_enc' => EZEV_Operations_Secrets::encrypt($webhookSecret),
    'enabled' => 1,
    'created_at' => current_time('mysql', true),
    'updated_at' => current_time('mysql', true),
]);
$integrationId = (int) $wpdb->insert_id;

$testPayload = json_encode(['event' => 'charger.status_change', 'status' => 'charging']);

// 1. Missing secret / unsigned request
$reqNoSig = new WP_REST_Request('POST', "/ezev-ops/v1/webhook/$integrationId");
$reqNoSig->set_body($testPayload);
$resNoSig = rest_do_request($reqNoSig);
assertCheck("Missing timestamp/signature returns 401", $resNoSig->get_status() === 401);

// 2. Replay attack with expired timestamp (> 300 seconds)
$expiredTs = time() - 400;
$expiredSig = hash_hmac('sha256', $expiredTs . '.' . $testPayload, $webhookSecret);
$reqReplay = new WP_REST_Request('POST', "/ezev-ops/v1/webhook/$integrationId");
$reqReplay->set_header('x-ezev-timestamp', (string)$expiredTs);
$reqReplay->set_header('x-ezev-signature', $expiredSig);
$reqReplay->set_body($testPayload);
$resReplay = rest_do_request($reqReplay);
assertCheck("Expired timestamp (>300s) returns 401 replay_rejected", $resReplay->get_status() === 401);

// 3. Bad signature with valid timestamp
$currentTs = time();
$reqBadSig = new WP_REST_Request('POST', "/ezev-ops/v1/webhook/$integrationId");
$reqBadSig->set_header('x-ezev-timestamp', (string)$currentTs);
$reqBadSig->set_header('x-ezev-signature', 'invalid_signature_hex_0000000000');
$reqBadSig->set_body($testPayload);
$resBadSig = rest_do_request($reqBadSig);
assertCheck("Invalid signature returns 401 invalid_signature", $resBadSig->get_status() === 401);

// 4. Valid signature with fresh timestamp
$validSig = hash_hmac('sha256', $currentTs . '.' . $testPayload, $webhookSecret);
$reqValid = new WP_REST_Request('POST', "/ezev-ops/v1/webhook/$integrationId");
$reqValid->set_header('x-ezev-timestamp', (string)$currentTs);
$reqValid->set_header('x-ezev-signature', $validSig);
$reqValid->set_body($testPayload);
$resValid = rest_do_request($reqValid);
assertCheck("Valid HMAC signature and fresh timestamp returns 200 OK", $resValid->get_status() === 200);

echo "\n==========================================\n";
echo "SUMMARY: {$totalChecks} checks, {$failedChecks} failures.\n";
echo "==========================================\n";

if ($failedChecks > 0) {
    exit(1);
}
exit(0);
