<?php
/**
 * GATE 3.2 PLUGIN API & SECURITY FREEZE INTEGRATION TEST SUITE
 *
 * Verifies end-to-end entity relationships, Tenancy boundaries,
 * Request-Aware Mutation Authorizers, Target-Specific Station Mutations,
 * Invitation Transaction Integrity (InnoDB Rollback), Safe Delete with Dependencies,
 * Membership Route Org Consistency, Scoped Freshness & Empty Datasets,
 * and Activation Cron Lifecycle.
 */

define('WP_USE_THEMES', false);

// 1. Fully Portable WP Discovery (No hardcoded absolute Windows paths)
$wpRoot = getenv('WP_ROOT') ?: ($argv[1] ?? null);
if (!$wpRoot) {
    $candidates = [
        getcwd(),
        dirname(__DIR__, 4) . '/public',
        dirname(__DIR__, 2) . '/app/public',
        dirname(__DIR__, 3) . '/app/public',
        dirname(__DIR__, 1) . '/public',
        dirname(getcwd()) . '/public',
    ];
    foreach ($candidates as $c) {
        if (file_exists($c . '/wp-load.php')) {
            $wpRoot = $c;
            break;
        }
    }
}

if (!$wpRoot || !file_exists($wpRoot . '/wp-load.php')) {
    echo "FATAL: wp-load.php not found. Please specify WP_ROOT environment variable or CLI argument.\n";
    exit(1);
}

require_once rtrim($wpRoot, '/\\') . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

// 2. Destructive Test Safety Guard
$env_type = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'local';
if (in_array($env_type, ['staging', 'production'], true)) {
    echo "FATAL: Refusing to execute integration test suite on {$env_type} environment!\n";
    exit(2);
}

$totalChecks = 0;
$failedChecks = 0;

function assertCheck(string $desc, bool $condition, string $detail = ''): void {
    global $totalChecks, $failedChecks;
    $totalChecks++;
    if ($condition) {
        echo "  [PASS] {$desc}\n";
    } else {
        $failedChecks++;
        echo "  [FAIL] {$desc}" . ($detail ? " -- {$detail}" : "") . "\n";
    }
}

function getOrCreateUser(string $login, string $role, ?string $email = null): WP_User {
    $u = get_user_by('login', $login);
    $userEmail = $email ?: ($login . '@gate32.test');
    if ($u) {
        $u->set_role($role);
        if ($email && $u->user_email !== $email) {
            wp_update_user(['ID' => $u->ID, 'user_email' => $email]);
        }
        return $u;
    }
    $pwd = wp_generate_password(24, true, true);
    $id = wp_create_user($login, $pwd, $userEmail);
    $u = get_userdata($id);
    $u->set_role($role);
    return $u;
}

echo "\n=================================================================\n";
echo "=== GATE 3.2: PLUGIN API & SECURITY FREEZE INTEGRATION SUITE ===\n";
echo "=================================================================\n";

// Ensure roles & schema are updated
EZEV_Core_Roles::install();
EZEV_Core_DB::maybe_upgrade();
$adminUser = get_user_by('login', 'admin');
if (!$adminUser) {
    $adminUser = getOrCreateUser('admin_test', 'administrator');
}
wp_set_current_user($adminUser->ID);

// Dynamic test identifiers to ensure test idempotency across runs
$runSuffix = wp_rand(1000, 9999);
$testOrgCode = 'G32_' . $runSuffix;
$testSiteCode = 'STE_' . $runSuffix;

// -------------------------------------------------------------
echo "\n--- [GROUP 1] Core Organization & Site CRUD ---\n";
// 1. Create Organization via REST
$orgReq = new WP_REST_Request('POST', '/ezev/v1/organizations');
$orgReq->set_header('content-type', 'application/json');
$orgReq->set_body(json_encode([
    'name'         => 'Gate32 Energy Group ' . $runSuffix,
    'org_code'     => $testOrgCode,
    'type'         => 'business',
    'country_code' => 'VN',
]));
$orgRes = rest_do_request($orgReq);
assertCheck("POST /organizations returns 201", $orgRes->get_status() === 201);
$orgData = $orgRes->get_data()['organization'] ?? [];
$g3OrgId = $orgData['organization_id'] ?? '';
assertCheck("Created organization has stable organization_id", !empty($g3OrgId));

// 2. Read Organization detail via REST
$orgGetRes = rest_do_request(new WP_REST_Request('GET', "/ezev/v1/organizations/{$g3OrgId}"));
assertCheck("GET /organizations/{id} returns 200", $orgGetRes->get_status() === 200);
assertCheck("Organization detail matches name", ($orgGetRes->get_data()['organization']['name'] ?? '') === ('Gate32 Energy Group ' . $runSuffix));

// 3. Create Site via REST
$siteReq = new WP_REST_Request('POST', '/ezev/v1/sites');
$siteReq->set_header('content-type', 'application/json');
$siteReq->set_body(json_encode([
    'organization_id' => $g3OrgId,
    'name'            => 'Gate32 Landmark Hub ' . $runSuffix,
    'site_code'       => $testSiteCode,
    'address'         => '123 Nguyen Hue, D1',
    'city'            => 'Ho Chi Minh City',
    'country_code'    => 'VN',
    'latitude'        => 10.7769,
    'longitude'       => 106.7009,
]));
$siteRes = rest_do_request($siteReq);
assertCheck("POST /sites returns 201", $siteRes->get_status() === 201);
$siteData = $siteRes->get_data()['site'] ?? [];
$g3SiteId = $siteData['site_id'] ?? '';
assertCheck("Created site has stable site_id", !empty($g3SiteId));

// 4. Read Site detail via REST
$siteGetRes = rest_do_request(new WP_REST_Request('GET', "/ezev/v1/sites/{$g3SiteId}"));
assertCheck("GET /sites/{id} returns 200", $siteGetRes->get_status() === 200);
assertCheck("Site detail matches name", ($siteGetRes->get_data()['site']['name'] ?? '') === ('Gate32 Landmark Hub ' . $runSuffix));


// -------------------------------------------------------------
echo "\n--- [GROUP 2] Core Memberships & Scopes ---\n";
// Create user for site manager with dynamic login for clean isolation
$managerUser = getOrCreateUser('g32_mgr_' . $runSuffix, 'ezev_business');

// Assign user as member of organization
$memReq = new WP_REST_Request('POST', "/ezev/v1/organizations/{$g3OrgId}/members");
$memReq->set_header('content-type', 'application/json');
$memReq->set_body(json_encode([
    'user_id'  => $managerUser->ID,
    'role_key' => 'site_manager',
]));
$memRes = rest_do_request($memReq);
assertCheck("POST /organizations/{id}/members returns 201", $memRes->get_status() === 201);
$g3MembershipId = $memRes->get_data()['member']['membership_id'] ?? '';
assertCheck("Created membership has stable membership_id", !empty($g3MembershipId));
assertCheck("REST-created membership_id starts with EZEV-MEM- prefix", str_starts_with($g3MembershipId, 'EZEV-MEM-'));

// Assign Site scope to membership
$scopeSiteReq = new WP_REST_Request('POST', "/ezev/v1/memberships/{$g3MembershipId}/sites");
$scopeSiteReq->set_header('content-type', 'application/json');
$scopeSiteReq->set_body(json_encode(['site_id' => $g3SiteId]));
$scopeSiteRes = rest_do_request($scopeSiteReq);
assertCheck("POST /memberships/{id}/sites returns 200", $scopeSiteRes->get_status() === 200);


// -------------------------------------------------------------
echo "\n--- [GROUP 3] Core Invitations Lifecycle & Stable IDs ---\n";
$inviteeEmail = 'invitee_' . $runSuffix . '@gate32.test';
$invReq = new WP_REST_Request('POST', "/ezev/v1/organizations/{$g3OrgId}/invitations");
$invReq->set_header('content-type', 'application/json');
$invReq->set_body(json_encode([
    'email'    => $inviteeEmail,
    'role_key' => 'operations',
]));
$invRes = rest_do_request($invReq);
assertCheck("POST /organizations/{id}/invitations returns 201", $invRes->get_status() === 201);
$invData = $invRes->get_data();
$invToken = $invData['token'] ?? '';
$invId = $invData['invitation_id'] ?? '';
assertCheck("Created invitation provides raw token", !empty($invToken));
assertCheck("Created invitation provides stable invitation_ref/id", !empty($invId) && strpos($invId, 'EZEV-INV') === 0);

// Verify token publicly
$verifyRes = rest_do_request(new WP_REST_Request('GET', "/ezev/v1/invitations/{$invToken}"));
assertCheck("GET /invitations/{token} returns 200 (public)", $verifyRes->get_status() === 200);
assertCheck("Verification confirms organization name", ($verifyRes->get_data()['organization_name'] ?? '') === ('Gate32 Energy Group ' . $runSuffix));

// Negative: Wrong user email cannot accept invitation (403 email_mismatch)
$wrongUser = getOrCreateUser('wrong_inv_' . $runSuffix, 'ezev_business', 'wrong_' . $runSuffix . '@gate32.test');
wp_set_current_user($wrongUser->ID);
$wrongAcceptRes = rest_do_request(new WP_REST_Request('POST', "/ezev/v1/invitations/{$invToken}/accept"));
assertCheck("Wrong email acceptance rejected with 403 email_mismatch", $wrongAcceptRes->get_status() === 403 && $wrongAcceptRes->get_data()['code'] === 'email_mismatch');

// Positive: Correct user accepts invitation
$correctUser = getOrCreateUser('g32_inv_' . $runSuffix, 'ezev_business', $inviteeEmail);
wp_set_current_user($correctUser->ID);
$acceptRes = rest_do_request(new WP_REST_Request('POST', "/ezev/v1/invitations/{$invToken}/accept"));
assertCheck("POST /invitations/{token}/accept returns 200 for matching email", $acceptRes->get_status() === 200);
assertCheck("Acceptance created active membership", !empty($acceptRes->get_data()['membership_id']));

// Negative: Atomic claim prevents double accept (409 conflict)
$dupAcceptRes = rest_do_request(new WP_REST_Request('POST', "/ezev/v1/invitations/{$invToken}/accept"));
assertCheck("Duplicate invitation acceptance rejected with 409 conflict", $dupAcceptRes->get_status() === 409);

// Re-authenticate as admin for setup
wp_set_current_user($adminUser->ID);


// -------------------------------------------------------------
echo "\n--- [GROUP 4] Station -> Charger -> Connector Hierarchy ---\n";
$g3StationId = 'EZEV-VN-G32-' . $runSuffix;
$stationPostId = wp_insert_post([
    'post_title'  => 'Gate32 Central Hub ' . $runSuffix,
    'post_type'   => EZEV_Core_Stations::POST_TYPE,
    'post_status' => 'publish',
]);
update_post_meta($stationPostId, '_ezev_station_id', $g3StationId);
update_post_meta($stationPostId, '_ezev_organization_id', $g3OrgId);
update_post_meta($stationPostId, '_ezev_site_id', $g3SiteId);
update_post_meta($stationPostId, '_ezev_latitude', 10.7769);
update_post_meta($stationPostId, '_ezev_longitude', 106.7009);
assertCheck("Station created in hierarchy linked to Org & Site", !empty($stationPostId));

// Assign Station scope to manager membership
$scopeStnReq = new WP_REST_Request('POST', "/ezev/v1/memberships/{$g3MembershipId}/stations");
$scopeStnReq->set_header('content-type', 'application/json');
$scopeStnReq->set_body(json_encode(['station_id' => $g3StationId]));
$scopeStnRes = rest_do_request($scopeStnReq);
assertCheck("POST /memberships/{id}/stations returns 200", $scopeStnRes->get_status() === 200);

// Insert Charger & Connector in Operations DB
global $wpdb;
$g3ChargerId = 'CHG-G32-' . $runSuffix;
$g3ConnectorId = 'CONN-G32-' . $runSuffix;
$wpdb->replace(EZEV_Operations_DB::table('chargers'), [
    'charger_id'      => $g3ChargerId,
    'station_id'      => $g3StationId,
    'connector_id'    => $g3ConnectorId,
    'connector_type'  => 'CCS2',
    'max_power_kw'    => 180.0,
    'status'          => 'available',
    'current_power_kw'=> 0.0,
    'provider'        => 'manual',
    'updated_at'      => current_time('mysql', true),
]);
$wpdb->replace(EZEV_Operations_DB::table('connectors'), [
    'connector_id'    => $g3ConnectorId,
    'charger_id'      => $g3ChargerId,
    'station_id'      => $g3StationId,
    'connector_type'  => 'CCS2',
    'max_power_kw'    => 180.0,
    'status'          => 'available',
    'current_power_kw'=> 0.0,
    'provider'        => 'manual',
    'updated_at'      => current_time('mysql', true),
]);

$chgRes = rest_do_request(new WP_REST_Request('GET', "/ezev-ops/v1/chargers/{$g3ChargerId}"));
assertCheck("GET /chargers/{id} returns 200", $chgRes->get_status() === 200);
assertCheck("Charger detail has matching charger_id", ($chgRes->get_data()['charger']['charger_id'] ?? '') === $g3ChargerId);

$connRes = rest_do_request(new WP_REST_Request('GET', "/ezev-ops/v1/connectors/{$g3ConnectorId}"));
assertCheck("GET /connectors/{id} returns 200", $connRes->get_status() === 200);
assertCheck("Connector detail has matching connector_id", ($connRes->get_data()['connector']['connector_id'] ?? '') === $g3ConnectorId);


// -------------------------------------------------------------
echo "\n--- [GROUP 5] Session -> Energy -> Alert -> Maintenance Hierarchy ---\n";
$g3SessionId = 'SES-G32-' . $runSuffix;
$wpdb->replace(EZEV_Operations_DB::table('sessions'), [
    'session_id'       => $g3SessionId,
    'station_id'       => $g3StationId,
    'charger_id'       => $g3ChargerId,
    'connector_id'     => $g3ConnectorId,
    'user_ref'         => 'USR-TEST',
    'started_at'       => gmdate('Y-m-d H:i:s', time() - 3600),
    'ended_at'         => gmdate('Y-m-d H:i:s', time()),
    'duration_seconds' => 3600,
    'energy_kwh'       => 45.5,
    'status'           => 'completed',
    'provider'         => 'manual',
]);

$sesRes = rest_do_request(new WP_REST_Request('GET', "/ezev-ops/v1/sessions/{$g3SessionId}"));
assertCheck("GET /sessions/{id} returns 200", $sesRes->get_status() === 200);

$g3AlertId = 'ALT-G32-' . $runSuffix;
$wpdb->replace(EZEV_Operations_DB::table('alerts'), [
    'alert_id'    => $g3AlertId,
    'station_id'  => $g3StationId,
    'charger_id'  => $g3ChargerId,
    'severity'    => 'high',
    'code'        => 'OVERTEMP_CRITICAL',
    'title'       => 'High Cable Temperature',
    'message'     => 'Connector overheating detected during fast charging',
    'status'      => 'open',
    'occurred_at' => current_time('mysql', true),
]);

// Test failure-path: forced ticket escalation failure rolls back both ticket and alert status
add_filter('ezevo_test_force_alert_ticket_failure', '__return_true');
$failEscReq = new WP_REST_Request('POST', "/ezev-ops/v1/alerts/{$g3AlertId}/create-ticket");
$failEscReq->set_header('content-type', 'application/json');
$failEscReq->set_body(json_encode(['priority' => 'critical']));
$failEscRes = rest_do_request($failEscReq);
remove_filter('ezevo_test_force_alert_ticket_failure', '__return_true');
assertCheck("Alert escalation forced failure returns 500 alert_update_failed", $failEscRes->get_status() === 500 && $failEscRes->get_data()['code'] === 'alert_update_failed');

$alertBeforeRollback = $wpdb->get_row($wpdb->prepare("SELECT status, acknowledged_at FROM " . EZEV_Operations_DB::table('alerts') . " WHERE alert_id = %s", $g3AlertId), ARRAY_A);
assertCheck("Failed escalation rolls back: Alert remains status 'open'", ($alertBeforeRollback['status'] ?? '') === 'open');

// Success path: escalation succeeds and creates ticket atomically
$escReq = new WP_REST_Request('POST', "/ezev-ops/v1/alerts/{$g3AlertId}/create-ticket");
$escReq->set_header('content-type', 'application/json');
$escReq->set_body(json_encode(['priority' => 'critical']));
$escRes = rest_do_request($escReq);
assertCheck("POST /alerts/{id}/create-ticket returns 201 on success", $escRes->get_status() === 201);
$g3TicketId = $escRes->get_data()['ticket']['ticket_id'] ?? '';
assertCheck("Escalated ticket has ticket_id", !empty($g3TicketId));

$alertAfterSuccess = $wpdb->get_row($wpdb->prepare("SELECT status FROM " . EZEV_Operations_DB::table('alerts') . " WHERE alert_id = %s", $g3AlertId), ARRAY_A);
assertCheck("Successful escalation marks Alert as 'acknowledged'", ($alertAfterSuccess['status'] ?? '') === 'acknowledged');

$maintGetRes = rest_do_request(new WP_REST_Request('GET', "/ezev-ops/v1/maintenance/{$g3TicketId}"));
assertCheck("GET /maintenance/{id} returns 200", $maintGetRes->get_status() === 200);


// -------------------------------------------------------------
echo "\n--- [GROUP 6] Granular Multi-Tier Authorization Matrix ---\n";
$investorUser = getOrCreateUser('g32_investor', 'ezev_investor');
wp_set_current_user($investorUser->ID);

$invOverview = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/overview'));
assertCheck("Investor CAN access GET /overview (200)", $invOverview->get_status() === 200);

$invReportPerf = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/reports/performance'));
assertCheck("Investor CAN access GET /reports/performance (200)", $invReportPerf->get_status() === 200);

$invSessions = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/sessions'));
assertCheck("Investor CANNOT access raw GET /sessions (403 rest_forbidden_data_tier)", $invSessions->get_status() === 403);

$invMaint = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/maintenance'));
assertCheck("Investor CANNOT access GET /maintenance (403)", $invMaint->get_status() === 403);

$custUser = getOrCreateUser('g32_customer', 'ezev_customer');
wp_set_current_user($custUser->ID);
$custOverview = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/overview'));
assertCheck("Customer GET /overview is 403", $custOverview->get_status() === 403);


// -------------------------------------------------------------
echo "\n--- [GROUP 7] Pagination & Metadata Verification ---\n";
wp_set_current_user($adminUser->ID);
$pageReq = new WP_REST_Request('GET', '/ezev-ops/v1/chargers');
$pageReq->set_param('page', 1);
$pageReq->set_param('per_page', 2);
$pageRes = rest_do_request($pageReq);
$pageData = $pageRes->get_data();
assertCheck("Collection response provides pagination block", isset($pageData['pagination']['page']) && $pageData['pagination']['per_page'] === 2);
assertCheck("Collection response provides meta block with source and fetched_at", !empty($pageData['meta']['source']) && !empty($pageData['meta']['fetched_at']));


// -------------------------------------------------------------
echo "\n--- [GROUP 8] GATE 3.1: Tenancy Scoping & Cross-Org Boundary Negative Tests ---\n";
wp_set_current_user($custUser->ID);
$custOrgsRes = rest_do_request(new WP_REST_Request('GET', '/ezev/v1/organizations'));
$custOrgs = $custOrgsRes->get_data()['organizations'] ?? ['unexpected'];
assertCheck("Customer cannot enumerate Organizations (returns empty list)", empty($custOrgs));

$custSitesRes = rest_do_request(new WP_REST_Request('GET', '/ezev/v1/sites'));
$custSites = $custSitesRes->get_data()['sites'] ?? ['unexpected'];
assertCheck("Customer cannot enumerate Sites (returns empty list)", empty($custSites));

$custOrgDetail = rest_do_request(new WP_REST_Request('GET', "/ezev/v1/organizations/{$g3OrgId}"));
assertCheck("Customer cannot read other tenant Organization detail (403 forbidden)", $custOrgDetail->get_status() === 403);

// Setup Org B & Site B
wp_set_current_user($adminUser->ID);
$orgBReq = new WP_REST_Request('POST', '/ezev/v1/organizations');
$orgBReq->set_header('content-type', 'application/json');
$orgBReq->set_body(json_encode([
    'name'     => 'Foreign Org B ' . $runSuffix,
    'org_code' => 'ORGB_' . $runSuffix,
]));
$orgBRes = rest_do_request($orgBReq);
$g3OrgBId = $orgBRes->get_data()['organization']['organization_id'] ?? '';

$siteBReq = new WP_REST_Request('POST', '/ezev/v1/sites');
$siteBReq->set_header('content-type', 'application/json');
$siteBReq->set_body(json_encode([
    'organization_id' => $g3OrgBId,
    'name'            => 'Foreign Site B ' . $runSuffix,
    'site_code'       => 'STEB_' . $runSuffix,
]));
$siteBRes = rest_do_request($siteBReq);
$g3SiteBId = $siteBRes->get_data()['site']['site_id'] ?? '';

$stnBPostId = wp_insert_post([
    'post_title'  => 'Foreign Station B ' . $runSuffix,
    'post_type'   => EZEV_Core_Stations::POST_TYPE,
    'post_status' => 'publish',
]);
$g3StationBId = 'EZEV-VN-ORGB-' . $runSuffix;
update_post_meta($stnBPostId, '_ezev_station_id', $g3StationBId);
update_post_meta($stnBPostId, '_ezev_organization_id', $g3OrgBId);
update_post_meta($stnBPostId, '_ezev_site_id', $g3SiteBId);

// Cross-Org Site/Station scope assignment returns 422
$crossSiteReq = new WP_REST_Request('POST', "/ezev/v1/memberships/{$g3MembershipId}/sites");
$crossSiteReq->set_header('content-type', 'application/json');
$crossSiteReq->set_body(json_encode(['site_id' => $g3SiteBId]));
$crossSiteRes = rest_do_request($crossSiteReq);
assertCheck("Cross-Org Site assignment rejected with 422 cross_organization_mismatch", $crossSiteRes->get_status() === 422 && $crossSiteRes->get_data()['code'] === 'cross_organization_mismatch');

$crossStnReq = new WP_REST_Request('POST', "/ezev/v1/memberships/{$g3MembershipId}/stations");
$crossStnReq->set_header('content-type', 'application/json');
$crossStnReq->set_body(json_encode(['station_id' => $g3StationBId]));
$crossStnRes = rest_do_request($crossStnReq);
assertCheck("Cross-Org Station assignment rejected with 422 cross_organization_mismatch", $crossStnRes->get_status() === 422 && $crossStnRes->get_data()['code'] === 'cross_organization_mismatch');


// -------------------------------------------------------------
echo "\n--- [GROUP 9] GATE 3.2: Business Owner / Admin Self-Service Test Matrix ---\n";
// Dynamically resolve real numeric compatibility IDs from newly created entities
$orgNumericId = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM " . EZEV_Core_DB::table('organizations') . " WHERE organization_id = %s", $g3OrgId));
$orgBNumericId = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM " . EZEV_Core_DB::table('organizations') . " WHERE organization_id = %s", $g3OrgBId));
$siteNumericId = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM " . EZEV_Core_DB::table('sites') . " WHERE site_id = %s", $g3SiteId));
$siteBNumericId = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM " . EZEV_Core_DB::table('sites') . " WHERE site_id = %s", $g3SiteBId));

// Create actors for Org A:
$ownerA = getOrCreateUser('owner_a_' . $runSuffix, 'ezev_business');
$adminA = getOrCreateUser('admin_a_' . $runSuffix, 'ezev_business');
$siteMgrA = getOrCreateUser('sitemgr_a_' . $runSuffix, 'ezev_business');
$viewerA = getOrCreateUser('viewer_a_' . $runSuffix, 'ezev_business');
$financeA = getOrCreateUser('finance_a_' . $runSuffix, 'ezev_business');
$ownerB = getOrCreateUser('owner_b_' . $runSuffix, 'ezev_business');

// Set memberships in Org A with real numeric org ID
$now = current_time('mysql', true);
$mTable = EZEV_Core_DB::table('org_members');
$wpdb->replace($mTable, ['organization_id' => $orgNumericId, 'organization_ref' => $g3OrgId, 'membership_id' => 'MEM-OWNA-' . $runSuffix, 'user_id' => $ownerA->ID, 'role_key' => 'owner', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
$wpdb->replace($mTable, ['organization_id' => $orgNumericId, 'organization_ref' => $g3OrgId, 'membership_id' => 'MEM-ADMA-' . $runSuffix, 'user_id' => $adminA->ID, 'role_key' => 'admin', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
$wpdb->replace($mTable, ['organization_id' => $orgNumericId, 'organization_ref' => $g3OrgId, 'membership_id' => 'MEM-SMGA-' . $runSuffix, 'user_id' => $siteMgrA->ID, 'role_key' => 'site_manager', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
$wpdb->replace($mTable, ['organization_id' => $orgNumericId, 'organization_ref' => $g3OrgId, 'membership_id' => 'MEM-VIEWA-' . $runSuffix, 'user_id' => $viewerA->ID, 'role_key' => 'viewer', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
$wpdb->replace($mTable, ['organization_id' => $orgNumericId, 'organization_ref' => $g3OrgId, 'membership_id' => 'MEM-FINA-' . $runSuffix, 'user_id' => $financeA->ID, 'role_key' => 'finance', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
// Set membership for Owner B in Org B with real numeric org B ID
$wpdb->replace($mTable, ['organization_id' => $orgBNumericId, 'organization_ref' => $g3OrgBId, 'membership_id' => 'MEM-OWNB-' . $runSuffix, 'user_id' => $ownerB->ID, 'role_key' => 'owner', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);

// Resolve real numeric ID of Site Manager member
$memNumSiteMgrA = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $mTable WHERE membership_id = %s", 'MEM-SMGA-' . $runSuffix));

// Assign Site A to Site Manager A with real numeric IDs
$msTable = EZEV_Core_DB::table('member_site_access');
$wpdb->replace($msTable, ['member_id' => $memNumSiteMgrA, 'site_id' => $siteNumericId, 'membership_ref' => 'MEM-SMGA-' . $runSuffix, 'site_ref' => $g3SiteId, 'created_at' => $now]);

// 1. Read Org A Matrix
wp_set_current_user($ownerA->ID);
assertCheck("Owner A CAN read Org A (200)", rest_do_request(new WP_REST_Request('GET', "/ezev/v1/organizations/{$g3OrgId}"))->get_status() === 200);

wp_set_current_user($adminA->ID);
assertCheck("Admin A CAN read Org A (200)", rest_do_request(new WP_REST_Request('GET', "/ezev/v1/organizations/{$g3OrgId}"))->get_status() === 200);

wp_set_current_user($viewerA->ID);
assertCheck("Viewer A CAN read Org A (200)", rest_do_request(new WP_REST_Request('GET', "/ezev/v1/organizations/{$g3OrgId}"))->get_status() === 200);

wp_set_current_user($ownerB->ID);
assertCheck("Owner B CANNOT read Org A (403)", rest_do_request(new WP_REST_Request('GET', "/ezev/v1/organizations/{$g3OrgId}"))->get_status() === 403);

// 2. Create Site in Org A Matrix
wp_set_current_user($ownerA->ID);
$reqCreateSite = new WP_REST_Request('POST', '/ezev/v1/sites');
$reqCreateSite->set_header('content-type', 'application/json');
$reqCreateSite->set_body(json_encode(['organization_id' => $g3OrgId, 'name' => 'Owner A Site ' . $runSuffix]));
assertCheck("Owner A CAN create Site in Org A (201)", rest_do_request($reqCreateSite)->get_status() === 201);

wp_set_current_user($siteMgrA->ID);
$reqCreateSiteSM = new WP_REST_Request('POST', '/ezev/v1/sites');
$reqCreateSiteSM->set_header('content-type', 'application/json');
$reqCreateSiteSM->set_body(json_encode(['organization_id' => $g3OrgId, 'name' => 'SM Unauthorized Site']));
assertCheck("Site Manager A CANNOT create Site in Org A (403)", rest_do_request($reqCreateSiteSM)->get_status() === 403);

wp_set_current_user($ownerB->ID);
$reqCreateSiteOB = new WP_REST_Request('POST', '/ezev/v1/sites');
$reqCreateSiteOB->set_header('content-type', 'application/json');
$reqCreateSiteOB->set_body(json_encode(['organization_id' => $g3OrgId, 'name' => 'Cross-tenant Org B Site']));
assertCheck("Owner B CANNOT create Site in Org A (403)", rest_do_request($reqCreateSiteOB)->get_status() === 403);

// 3. Update Site A Matrix
wp_set_current_user($siteMgrA->ID);
$reqUpSiteSM = new WP_REST_Request('PUT', "/ezev/v1/sites/{$g3SiteId}");
$reqUpSiteSM->set_header('content-type', 'application/json');
$reqUpSiteSM->set_body(json_encode(['address' => 'Updated by Assigned Site Manager']));
assertCheck("Assigned Site Manager A CAN update Site A (200)", rest_do_request($reqUpSiteSM)->get_status() === 200);

wp_set_current_user($viewerA->ID);
$reqUpSiteV = new WP_REST_Request('PUT', "/ezev/v1/sites/{$g3SiteId}");
$reqUpSiteV->set_header('content-type', 'application/json');
$reqUpSiteV->set_body(json_encode(['address' => 'Unauthorized Viewer update']));
assertCheck("Viewer A CANNOT update Site A (403)", rest_do_request($reqUpSiteV)->get_status() === 403);

wp_set_current_user($ownerB->ID);
$reqUpSiteOB = new WP_REST_Request('PUT', "/ezev/v1/sites/{$g3SiteId}");
$reqUpSiteOB->set_header('content-type', 'application/json');
$reqUpSiteOB->set_body(json_encode(['address' => 'Owner B foreign update']));
assertCheck("Owner B CANNOT update Site A (403)", rest_do_request($reqUpSiteOB)->get_status() === 403);

// 4. Invite user into Org A Matrix
wp_set_current_user($ownerA->ID);
$reqInv = new WP_REST_Request('POST', "/ezev/v1/organizations/{$g3OrgId}/invitations");
$reqInv->set_header('content-type', 'application/json');
$reqInv->set_body(json_encode(['email' => 'newuser_' . $runSuffix . '@test.com', 'role_key' => 'viewer']));
assertCheck("Owner A CAN invite user into Org A (201)", rest_do_request($reqInv)->get_status() === 201);

wp_set_current_user($siteMgrA->ID);
assertCheck("Site Manager A CANNOT invite user into Org A (403)", rest_do_request($reqInv)->get_status() === 403);

wp_set_current_user($ownerB->ID);
assertCheck("Owner B CANNOT invite user into Org A (403)", rest_do_request($reqInv)->get_status() === 403);


// -------------------------------------------------------------
echo "\n--- [GROUP 10] GATE 3.2: Operations Target-Specific Station Mutation Matrix ---\n";
// Setup Alert B and Ticket B belonging to Station B (Org B)
$g3AlertBId = 'ALT-B-' . $runSuffix;
$wpdb->replace(EZEV_Operations_DB::table('alerts'), [
    'alert_id'    => $g3AlertBId,
    'station_id'  => $g3StationBId,
    'charger_id'  => 'CHG-B',
    'severity'    => 'high',
    'code'        => 'FOREIGN_ALERT',
    'title'       => 'Foreign Alert B',
    'message'     => 'Testing isolation',
    'status'      => 'open',
    'occurred_at' => current_time('mysql', true),
]);

$g3TicketBId = 'TKT-B-' . $runSuffix;
$wpdb->replace(EZEV_Operations_DB::table('maintenance'), [
    'ticket_id'        => $g3TicketBId,
    'station_id'       => $g3StationBId,
    'charger_id'       => 'CHG-B',
    'priority'         => 'high',
    'status'           => 'open',
    'assigned_user_id' => null,
    'summary'          => 'Foreign Ticket B',
    'details'          => 'Testing cross-tenant mutation defense',
    'opened_at'        => current_time('mysql', true),
    'updated_at'       => current_time('mysql', true),
]);

// Create User X: Org A -> site_manager (Station A assigned); Org B -> viewer (Station B readable)
$userX = getOrCreateUser('user_x_' . $runSuffix, 'ezev_business');
$wpdb->replace($mTable, ['organization_id' => $orgNumericId, 'organization_ref' => $g3OrgId, 'membership_id' => 'MEM-XA-' . $runSuffix, 'user_id' => $userX->ID, 'role_key' => 'site_manager', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
$wpdb->replace($mTable, ['organization_id' => $orgBNumericId, 'organization_ref' => $g3OrgBId, 'membership_id' => 'MEM-XB-' . $runSuffix, 'user_id' => $userX->ID, 'role_key' => 'viewer', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);

$memNumUserXA = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $mTable WHERE membership_id = %s", 'MEM-XA-' . $runSuffix));
$memNumUserXB = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $mTable WHERE membership_id = %s", 'MEM-XB-' . $runSuffix));

$mstTable = EZEV_Core_DB::table('member_station_access');
$wpdb->replace($mstTable, ['member_id' => $memNumUserXA, 'station_post_id' => $stationPostId, 'membership_ref' => 'MEM-XA-' . $runSuffix, 'station_id' => $g3StationId, 'created_at' => $now]);
$wpdb->replace($mstTable, ['member_id' => $memNumUserXB, 'station_post_id' => $stnBPostId, 'membership_ref' => 'MEM-XB-' . $runSuffix, 'station_id' => $g3StationBId, 'created_at' => $now]);

wp_set_current_user($userX->ID);

// 1. Positive: User X mutates Station A alert & ticket
$ackARes = rest_do_request(new WP_REST_Request('POST', "/ezev-ops/v1/alerts/{$g3AlertId}/acknowledge"));
assertCheck("User X CAN acknowledge Alert A (200)", $ackARes->get_status() === 200);

$transReqA = new WP_REST_Request('POST', "/ezev-ops/v1/maintenance/{$g3TicketId}/transition");
$transReqA->set_header('content-type', 'application/json');
$transReqA->set_body(json_encode(['status' => 'in_progress']));
assertCheck("User X CAN transition Ticket A (200)", rest_do_request($transReqA)->get_status() === 200);

// 2. Negative: User X CANNOT mutate Station B resources (Test all 6 mutation handlers)
// Handler 1: Alert acknowledge outside scope -> 403
$ackBRes = rest_do_request(new WP_REST_Request('POST', "/ezev-ops/v1/alerts/{$g3AlertBId}/acknowledge"));
assertCheck("Security Regression 1/6: Acknowledge Alert B rejected with 403", $ackBRes->get_status() === 403);

// Handler 2: Alert resolve outside scope -> 403
$resBRes = rest_do_request(new WP_REST_Request('POST', "/ezev-ops/v1/alerts/{$g3AlertBId}/resolve"));
assertCheck("Security Regression 2/6: Resolve Alert B rejected with 403", $resBRes->get_status() === 403);

// Handler 3: Alert create ticket outside scope -> 403
$escBReq = new WP_REST_Request('POST', "/ezev-ops/v1/alerts/{$g3AlertBId}/create-ticket");
$escBReq->set_header('content-type', 'application/json');
$escBReq->set_body(json_encode(['priority' => 'critical']));
$escBRes = rest_do_request($escBReq);
assertCheck("Security Regression 3/6: Create Ticket from Alert B rejected with 403", $escBRes->get_status() === 403);

// Handler 4: Create Maintenance ticket for station outside scope -> 403
$createMaintBReq = new WP_REST_Request('POST', '/ezev-ops/v1/maintenance');
$createMaintBReq->set_header('content-type', 'application/json');
$createMaintBReq->set_body(json_encode([
    'station_id' => $g3StationBId,
    'summary'    => 'Unauthorized Ticket for Station B',
]));
$createMaintBRes = rest_do_request($createMaintBReq);
assertCheck("Security Regression 4/6: Create Maintenance for Station B rejected with 403", $createMaintBRes->get_status() === 403);

// Handler 5: Update Maintenance ticket outside scope -> 403
$upMaintBReq = new WP_REST_Request('PUT', "/ezev-ops/v1/maintenance/{$g3TicketBId}");
$upMaintBReq->set_header('content-type', 'application/json');
$upMaintBReq->set_body(json_encode(['summary' => 'Tampered Summary']));
$upMaintBRes = rest_do_request($upMaintBReq);
assertCheck("Security Regression 5/6: Update Ticket B rejected with 403", $upMaintBRes->get_status() === 403);

// Handler 6: Transition Maintenance ticket outside scope -> 403
$transReqB = new WP_REST_Request('POST', "/ezev-ops/v1/maintenance/{$g3TicketBId}/transition");
$transReqB->set_header('content-type', 'application/json');
$transReqB->set_body(json_encode(['status' => 'resolved']));
$transBRes = rest_do_request($transReqB);
assertCheck("Security Regression 6/6: Transition Ticket B rejected with 403", $transBRes->get_status() === 403);

// 3. Verify Station B DB rows remain completely untouched
$alertBRow = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . EZEV_Operations_DB::table('alerts') . " WHERE alert_id = %s", $g3AlertBId), ARRAY_A);
assertCheck("Alert B in database status is STILL 'open'", ($alertBRow['status'] ?? '') === 'open');
assertCheck("Alert B acknowledged_at is STILL NULL", is_null($alertBRow['acknowledged_at']));
assertCheck("Alert B resolved_at is STILL NULL", is_null($alertBRow['resolved_at']));

$ticketBRow = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . EZEV_Operations_DB::table('maintenance') . " WHERE ticket_id = %s", $g3TicketBId), ARRAY_A);
assertCheck("Ticket B in database status is STILL 'open'", ($ticketBRow['status'] ?? '') === 'open');
assertCheck("Ticket B summary is STILL 'Foreign Ticket B'", ($ticketBRow['summary'] ?? '') === 'Foreign Ticket B');
assertCheck("Ticket B closed_at is STILL NULL", is_null($ticketBRow['closed_at']));


// -------------------------------------------------------------
echo "\n--- [GROUP 11] GATE 3.2: Invitation Transaction Integrity & Rollback ---\n";
wp_set_current_user($adminUser->ID);
$transToken = wp_generate_password(32, false);
$transTokenHash = hash('sha256', $transToken);
$transEmail = 'trans_test_' . $runSuffix . '@gate32.test';
$invTable = EZEV_Core_DB::table('invitations');

// Create valid pending invitation under Org A
$wpdb->insert($invTable, [
    'invitation_ref'   => 'EZEV-INV-TRANS-' . $runSuffix,
    'organization_id'  => $orgNumericId,
    'organization_ref' => $g3OrgId,
    'email'            => $transEmail,
    'role_key'         => 'viewer',
    'token_hash'       => $transTokenHash,
    'status'           => 'pending',
    'expires_at'       => gmdate('Y-m-d H:i:s', time() + 3600),
    'created_at'       => current_time('mysql', true),
]);

$transUser = getOrCreateUser('trans_user_' . $runSuffix, 'ezev_business', $transEmail);
wp_set_current_user($transUser->ID);

// 1. Inject forced failure after atomic claim, during membership creation
add_filter('ezev_test_force_invitation_membership_failure', '__return_true');
$forcedFailRes = rest_do_request(new WP_REST_Request('POST', "/ezev/v1/invitations/{$transToken}/accept"));
remove_filter('ezev_test_force_invitation_membership_failure', '__return_true');

assertCheck("Acceptance with forced membership failure returns 500 transaction_failed", $forcedFailRes->get_status() === 500 && $forcedFailRes->get_data()['code'] === 'transaction_failed');

// Verify database rollback: status remains 'pending' and NO membership row was committed
$invStatusAfterRollback = $wpdb->get_var($wpdb->prepare("SELECT status FROM $invTable WHERE token_hash = %s", $transTokenHash));
assertCheck("Rollback verified: Invitation status in DB remains 'pending'", $invStatusAfterRollback === 'pending');

$membershipCountAfterRollback = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $mTable WHERE user_id = %d AND organization_ref = %s", $transUser->ID, $g3OrgId));
assertCheck("Rollback verified: No membership created for user in DB", $membershipCountAfterRollback === 0);

// 2. Retry with the EXACT same invitation token: must succeed cleanly
$cleanAcceptRes = rest_do_request(new WP_REST_Request('POST', "/ezev/v1/invitations/{$transToken}/accept"));
assertCheck("Retry with same token after rollback returns 200 accepted", $cleanAcceptRes->get_status() === 200 && ($cleanAcceptRes->get_data()['accepted'] ?? false) === true);
$claimedMemId = (string) ($cleanAcceptRes->get_data()['membership_id'] ?? '');
assertCheck("Invitation-created membership_id starts with EZEV-MEM- prefix", str_starts_with($claimedMemId, 'EZEV-MEM-'));

$invStatusAfterSuccess = $wpdb->get_var($wpdb->prepare("SELECT status FROM $invTable WHERE token_hash = %s", $transTokenHash));
assertCheck("Final DB state: Invitation status is now 'accepted'", $invStatusAfterSuccess === 'accepted');

$membershipCountAfterSuccess = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $mTable WHERE user_id = %d AND organization_ref = %s", $transUser->ID, $g3OrgId));
assertCheck("Final DB state: Exactly 1 membership row exists for user", $membershipCountAfterSuccess === 1);


// -------------------------------------------------------------
echo "\n--- [GROUP 12] GATE 3.2: Safe Delete Dependency Rules ---\n";
wp_set_current_user($adminUser->ID);

// 1. Pending invitation prevents Org deletion
$pendingOrgCode = 'DELORG_' . $runSuffix;
$orgDelReq = new WP_REST_Request('POST', '/ezev/v1/organizations');
$orgDelReq->set_header('content-type', 'application/json');
$orgDelReq->set_body(json_encode(['name' => 'Pending Inv Org ' . $runSuffix, 'org_code' => $pendingOrgCode]));
$orgDelRes = rest_do_request($orgDelReq);
$delOrgId = $orgDelRes->get_data()['organization']['organization_id'];
$delOrgNumericId = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM " . EZEV_Core_DB::table('organizations') . " WHERE organization_id = %s", $delOrgId));

// Create pending invitation in this org
$wpdb->insert($invTable, [
    'invitation_ref'   => 'EZEV-INV-DEL-' . $runSuffix,
    'organization_id'  => $delOrgNumericId,
    'organization_ref' => $delOrgId,
    'email'            => 'del_' . $runSuffix . '@test.com',
    'role_key'         => 'viewer',
    'token_hash'       => hash('sha256', wp_generate_password(16, false)),
    'status'           => 'pending',
    'created_at'       => current_time('mysql', true),
]);

$delOrgAttempt = rest_do_request(new WP_REST_Request('DELETE', "/ezev/v1/organizations/{$delOrgId}"));
assertCheck("Delete Org with pending invitation rejected with 409", $delOrgAttempt->get_status() === 409 && $delOrgAttempt->get_data()['code'] === 'resource_has_dependencies');
assertCheck("409 response includes pending_invitations count", ($delOrgAttempt->get_data()['data']['dependencies']['pending_invitations'] ?? 0) >= 1);

// 2. Member site access prevents Site deletion
$steDelCode = 'DELSTE_' . $runSuffix;
$siteDelReq = new WP_REST_Request('POST', '/ezev/v1/sites');
$siteDelReq->set_header('content-type', 'application/json');
$siteDelReq->set_body(json_encode(['organization_id' => $g3OrgId, 'name' => 'Access Site ' . $runSuffix, 'site_code' => $steDelCode]));
$siteDelRes = rest_do_request($siteDelReq);
$delSiteId = $siteDelRes->get_data()['site']['site_id'];
$delSiteNumericId = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM " . EZEV_Core_DB::table('sites') . " WHERE site_id = %s", $delSiteId));
$memNumOwnerA = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $mTable WHERE membership_id = %s", 'MEM-OWNA-' . $runSuffix));

// Assign member access to this site with real numeric IDs
$wpdb->replace(EZEV_Core_DB::table('member_site_access'), [
    'member_id'      => $memNumOwnerA,
    'site_id'        => $delSiteNumericId,
    'membership_ref' => 'MEM-OWNA-' . $runSuffix,
    'site_ref'       => $delSiteId,
    'created_at'     => current_time('mysql', true),
]);

$delSiteAttempt = rest_do_request(new WP_REST_Request('DELETE', "/ezev/v1/sites/{$delSiteId}"));
assertCheck("Delete Site with member_site_access rejected with 409", $delSiteAttempt->get_status() === 409 && $delSiteAttempt->get_data()['code'] === 'resource_has_dependencies');
assertCheck("409 response includes member_site_access count", ($delSiteAttempt->get_data()['data']['dependencies']['member_site_access'] ?? 0) >= 1);


// -------------------------------------------------------------
echo "\n--- [GROUP 13] GATE 3.2: Membership Route Organization Consistency ---\n";
// Attempt to update/delete Member of Org A using Org B URL
$crossOrgMemUp = new WP_REST_Request('PUT', "/ezev/v1/organizations/{$g3OrgBId}/members/MEM-OWNA-{$runSuffix}");
$crossOrgMemUp->set_header('content-type', 'application/json');
$crossOrgMemUp->set_body(json_encode(['role_key' => 'viewer']));
$crossOrgMemUpRes = rest_do_request($crossOrgMemUp);
assertCheck("Update member of Org A via Org B URL returns 404", $crossOrgMemUpRes->get_status() === 404);

$crossOrgMemDel = rest_do_request(new WP_REST_Request('DELETE', "/ezev/v1/organizations/{$g3OrgBId}/members/MEM-OWNA-{$runSuffix}"));
assertCheck("Delete member of Org A via Org B URL returns 404", $crossOrgMemDel->get_status() === 404);

// Verify Member A is STILL owner of Org A
$memRow = $wpdb->get_row($wpdb->prepare("SELECT role_key FROM $mTable WHERE membership_id = %s", 'MEM-OWNA-' . $runSuffix), ARRAY_A);
assertCheck("Member A role_key in DB remains untouched as 'owner'", ($memRow['role_key'] ?? '') === 'owner');

// GATE 3.2.1: Transactional Member Delete & Scope Cleanup Rollback
// 1. Create dedicated user and member with site & station scopes
$delTestUser = getOrCreateUser('del_test_usr_' . $runSuffix, 'ezev_business');
$delMemReq = new WP_REST_Request('POST', "/ezev/v1/organizations/{$g3OrgId}/members");
$delMemReq->set_header('content-type', 'application/json');
$delMemReq->set_body(json_encode(['user_id' => $delTestUser->ID, 'role_key' => 'site_manager']));
$delMemRes = rest_do_request($delMemReq);
$delMemId = $delMemRes->get_data()['member']['membership_id'] ?? '';
assertCheck("Transactional Delete Setup: member created with EZEV-MEM-", str_starts_with($delMemId, 'EZEV-MEM-'));

// Assign Site A and Station A to this member
$delSiteAssign = new WP_REST_Request('POST', "/ezev/v1/memberships/{$delMemId}/sites");
$delSiteAssign->set_header('content-type', 'application/json');
$delSiteAssign->set_body(json_encode(['site_id' => $g3SiteId]));
rest_do_request($delSiteAssign);

$delStnAssign = new WP_REST_Request('POST', "/ezev/v1/memberships/{$delMemId}/stations");
$delStnAssign->set_header('content-type', 'application/json');
$delStnAssign->set_body(json_encode(['station_id' => $g3StationId]));
rest_do_request($delStnAssign);

$preSiteCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . EZEV_Core_DB::table('member_site_access') . " WHERE membership_ref = %s", $delMemId));
$preStnCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . EZEV_Core_DB::table('member_station_access') . " WHERE membership_ref = %s", $delMemId));
assertCheck("Pre-test: member has 1 site access and 1 station access", $preSiteCount === 1 && $preStnCount === 1);

// 2. Inject forced failure seam during delete
add_filter('ezev_test_force_member_delete_failure', '__return_true');
$forcedDelReq = new WP_REST_Request('DELETE', "/ezev/v1/organizations/{$g3OrgId}/members/{$delMemId}");
$forcedDelRes = rest_do_request($forcedDelReq);
remove_filter('ezev_test_force_member_delete_failure', '__return_true');

assertCheck("Forced failure during delete returns 500 member_delete_failed", $forcedDelRes->get_status() === 500 && $forcedDelRes->get_data()['code'] === 'member_delete_failed');

// 3. Confirm ROLLBACK: org_members, member_site_access, member_station_access rows remain 100% intact
$rbMemCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . EZEV_Core_DB::table('org_members') . " WHERE membership_id = %s", $delMemId));
$rbSiteCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . EZEV_Core_DB::table('member_site_access') . " WHERE membership_ref = %s", $delMemId));
$rbStnCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . EZEV_Core_DB::table('member_station_access') . " WHERE membership_ref = %s", $delMemId));

assertCheck("Rollback verified: org_members row remains intact", $rbMemCount === 1);
assertCheck("Rollback verified: member_site_access row remains intact", $rbSiteCount === 1);
assertCheck("Rollback verified: member_station_access row remains intact", $rbStnCount === 1);

// 4. Clean success delete: all 3 tables cleaned up atomically
$cleanDelReq = new WP_REST_Request('DELETE', "/ezev/v1/organizations/{$g3OrgId}/members/{$delMemId}");
$cleanDelRes = rest_do_request($cleanDelReq);
assertCheck("Clean delete succeeds with 200 deleted: true", $cleanDelRes->get_status() === 200 && ($cleanDelRes->get_data()['deleted'] ?? false) === true);

$postMemCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . EZEV_Core_DB::table('org_members') . " WHERE membership_id = %s", $delMemId));
$postSiteCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . EZEV_Core_DB::table('member_site_access') . " WHERE membership_ref = %s", $delMemId));
$postStnCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . EZEV_Core_DB::table('member_station_access') . " WHERE membership_ref = %s", $delMemId));

assertCheck("Post-delete: org_members cleanly removed (0)", $postMemCount === 0);
assertCheck("Post-delete: member_site_access cleanly removed (0)", $postSiteCount === 0);
assertCheck("Post-delete: member_station_access cleanly removed (0)", $postStnCount === 0);


// -------------------------------------------------------------
echo "\n--- [GROUP 14] GATE 3.2: Scoped Freshness & Empty Dataset ---\n";
// Filter chargers for Station A: should return real timestamp
$freshReq = new WP_REST_Request('GET', '/ezev-ops/v1/chargers');
$freshReq->set_param('station_id', $g3StationId);
$freshRes = rest_do_request($freshReq);
$meta = $freshRes->get_data()['meta'] ?? [];
assertCheck("Scoped Freshness: last_updated matches Station A data", !empty($meta['last_updated']));
assertCheck("Scoped Freshness: data_source is manual", ($meta['data_source'] ?? '') === 'manual');
assertCheck("Scoped Freshness: is_stale is false for recent data", ($meta['is_stale'] ?? true) === false);

// Filter chargers for Non-Existent Station: empty dataset must return null timestamps and is_stale = true
$emptyReq = new WP_REST_Request('GET', '/ezev-ops/v1/chargers');
$emptyReq->set_param('station_id', 'NON-EXISTENT-STATION-' . $runSuffix);
$emptyRes = rest_do_request($emptyReq);
$emptyMeta = $emptyRes->get_data()['meta'] ?? [];
assertCheck("Empty Dataset: last_updated is NULL", is_null($emptyMeta['last_updated']));
assertCheck("Empty Dataset: freshness_seconds is NULL", is_null($emptyMeta['freshness_seconds']));
assertCheck("Empty Dataset: is_stale is true", ($emptyMeta['is_stale'] ?? false) === true);


// -------------------------------------------------------------
echo "\n--- [GROUP 15] GATE 3.2: Fresh-Activation Cron Lifecycle Verification ---\n";
EZEV_Operations_Sync::unschedule();
assertCheck("Cron events cleanly unscheduled", !wp_next_scheduled('ezevo_sync_event') && !wp_next_scheduled('ezevo_cleanup_receipts_event'));

EZEV_Operations::activate();
assertCheck("Activation schedules ezevo_sync_event", wp_next_scheduled('ezevo_sync_event') > time());
assertCheck("Activation schedules ezevo_cleanup_receipts_event", wp_next_scheduled('ezevo_cleanup_receipts_event') > time());

$schedules = wp_get_schedules();
assertCheck("Custom recurrence ezevo_5min (300s) registered", isset($schedules['ezevo_5min']) && $schedules['ezevo_5min']['interval'] === 300);
assertCheck("Custom recurrence ezevo_hourly (3600s) registered", isset($schedules['ezevo_hourly']) && $schedules['ezevo_hourly']['interval'] === 3600);


// -------------------------------------------------------------
echo "\n=================================================================\n";
echo "SUMMARY: {$totalChecks} checks, {$failedChecks} failures.\n";
echo "=================================================================\n";

if ($failedChecks > 0) {
    exit(1);
}
exit(0);