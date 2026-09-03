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

$escReq = new WP_REST_Request('POST', "/ezev-ops/v1/alerts/{$g3AlertId}/create-ticket");
$escReq->set_header('content-type', 'application/json');
$escReq->set_body(json_encode(['priority' => 'critical']));
$escRes = rest_do_request($escReq);
assertCheck("POST /alerts/{id}/create-ticket returns 201", $escRes->get_status() === 201);
$g3TicketId = $escRes->get_data()['ticket']['ticket_id'] ?? '';
assertCheck("Escalated ticket has ticket_id", !empty($g3TicketId));

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
// Create actors for Org A:
$ownerA = getOrCreateUser('owner_a_' . $runSuffix, 'ezev_business');
$adminA = getOrCreateUser('admin_a_' . $runSuffix, 'ezev_business');
$siteMgrA = getOrCreateUser('sitemgr_a_' . $runSuffix, 'ezev_business');
$viewerA = getOrCreateUser('viewer_a_' . $runSuffix, 'ezev_business');
$financeA = getOrCreateUser('finance_a_' . $runSuffix, 'ezev_business');
$ownerB = getOrCreateUser('owner_b_' . $runSuffix, 'ezev_business');

// Set memberships in Org A
$now = current_time('mysql', true);
$mTable = EZEV_Core_DB::table('org_members');
$wpdb->replace($mTable, ['organization_id' => 1, 'organization_ref' => $g3OrgId, 'membership_id' => 'MEM-OWNA-' . $runSuffix, 'user_id' => $ownerA->ID, 'role_key' => 'owner', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
$wpdb->replace($mTable, ['organization_id' => 1, 'organization_ref' => $g3OrgId, 'membership_id' => 'MEM-ADMA-' . $runSuffix, 'user_id' => $adminA->ID, 'role_key' => 'admin', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
$wpdb->replace($mTable, ['organization_id' => 1, 'organization_ref' => $g3OrgId, 'membership_id' => 'MEM-SMGA-' . $runSuffix, 'user_id' => $siteMgrA->ID, 'role_key' => 'site_manager', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
$wpdb->replace($mTable, ['organization_id' => 1, 'organization_ref' => $g3OrgId, 'membership_id' => 'MEM-VIEWA-' . $runSuffix, 'user_id' => $viewerA->ID, 'role_key' => 'viewer', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
$wpdb->replace($mTable, ['organization_id' => 1, 'organization_ref' => $g3OrgId, 'membership_id' => 'MEM-FINA-' . $runSuffix, 'user_id' => $financeA->ID, 'role_key' => 'finance', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
// Set membership for Owner B in Org B
$wpdb->replace($mTable, ['organization_id' => 2, 'organization_ref' => $g3OrgBId, 'membership_id' => 'MEM-OWNB-' . $runSuffix, 'user_id' => $ownerB->ID, 'role_key' => 'owner', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);

// Assign Site A to Site Manager A
$msTable = EZEV_Core_DB::table('member_site_access');
$wpdb->replace($msTable, ['member_id' => 1, 'site_id' => 1, 'membership_ref' => 'MEM-SMGA-' . $runSuffix, 'site_ref' => $g3SiteId, 'created_at' => $now]);

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
$wpdb->replace($mTable, ['organization_id' => 1, 'organization_ref' => $g3OrgId, 'membership_id' => 'MEM-XA-' . $runSuffix, 'user_id' => $userX->ID, 'role_key' => 'site_manager', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
$wpdb->replace($mTable, ['organization_id' => 2, 'organization_ref' => $g3OrgBId, 'membership_id' => 'MEM-XB-' . $runSuffix, 'user_id' => $userX->ID, 'role_key' => 'viewer', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);

$mstTable = EZEV_Core_DB::table('member_station_access');
$wpdb->replace($mstTable, ['member_id' => 1, 'station_post_id' => $stationPostId, 'membership_ref' => 'MEM-XA-' . $runSuffix, 'station_id' => $g3StationId, 'created_at' => $now]);
$wpdb->replace($mstTable, ['member_id' => 2, 'station_post_id' => $stnBPostId, 'membership_ref' => 'MEM-XB-' . $runSuffix, 'station_id' => $g3StationBId, 'created_at' => $now]);

wp_set_current_user($userX->ID);

// 1. Positive: User X mutates Station A alert & ticket
$ackARes = rest_do_request(new WP_REST_Request('POST', "/ezev-ops/v1/alerts/{$g3AlertId}/acknowledge"));
assertCheck("User X CAN acknowledge Alert A (200)", $ackARes->get_status() === 200);

$transReqA = new WP_REST_Request('POST', "/ezev-ops/v1/maintenance/{$g3TicketId}/transition");
$transReqA->set_header('content-type', 'application/json');
$transReqA->set_body(json_encode(['status' => 'in_progress']));
assertCheck("User X CAN transition Ticket A (200)", rest_do_request($transReqA)->get_status() === 200);

// 2. Negative: User X CANNOT mutate Station B alert & ticket (Privilege Escalation Defense)
$ackBRes = rest_do_request(new WP_REST_Request('POST', "/ezev-ops/v1/alerts/{$g3AlertBId}/acknowledge"));
assertCheck("User X CANNOT acknowledge Alert B (403 rest_forbidden)", $ackBRes->get_status() === 403);

$transReqB = new WP_REST_Request('POST', "/ezev-ops/v1/maintenance/{$g3TicketBId}/transition");
$transReqB->set_header('content-type', 'application/json');
$transReqB->set_body(json_encode(['status' => 'resolved']));
$transBRes = rest_do_request($transReqB);
assertCheck("User X CANNOT transition Ticket B (403 rest_forbidden)", $transBRes->get_status() === 403);

// 3. Verify Station B DB rows remain completely untouched
$alertBRow = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . EZEV_Operations_DB::table('alerts') . " WHERE alert_id = %s", $g3AlertBId), ARRAY_A);
assertCheck("Alert B in database status is STILL 'open'", $alertBRow['status'] === 'open');
assertCheck("Alert B acknowledged_at is STILL NULL", is_null($alertBRow['acknowledged_at']));

$ticketBRow = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . EZEV_Operations_DB::table('maintenance') . " WHERE ticket_id = %s", $g3TicketBId), ARRAY_A);
assertCheck("Ticket B in database status is STILL 'open'", $ticketBRow['status'] === 'open');
assertCheck("Ticket B closed_at is STILL NULL", is_null($ticketBRow['closed_at']));


// -------------------------------------------------------------
echo "\n--- [GROUP 11] GATE 3.2: Invitation Transaction Integrity & Rollback ---\n";
wp_set_current_user($adminUser->ID);
$transToken = wp_generate_password(32, false);
$transTokenHash = hash('sha256', $transToken);
$transEmail = 'trans_test_' . $runSuffix . '@gate32.test';
$invTable = EZEV_Core_DB::table('invitations');

$wpdb->insert($invTable, [
    'invitation_ref'   => 'EZEV-INV-TRANS-' . $runSuffix,
    'organization_id'  => 9999999, // NON-EXISTENT ORG TO TRIGGER DB INSERT/LOOKUP ERROR OR SIMULATE FAILURE
    'organization_ref' => 'NON-EXISTENT-ORG',
    'email'            => $transEmail,
    'role_key'         => 'viewer',
    'token_hash'       => $transTokenHash,
    'status'           => 'pending',
    'expires_at'       => gmdate('Y-m-d H:i:s', time() + 3600),
    'created_at'       => current_time('mysql', true),
]);

$transUser = getOrCreateUser('trans_user_' . $runSuffix, 'ezev_business', $transEmail);
wp_set_current_user($transUser->ID);
$claimFailRes = rest_do_request(new WP_REST_Request('POST', "/ezev/v1/invitations/{$transToken}/accept"));
assertCheck("Invitation accept with invalid org fails with 404", $claimFailRes->get_status() === 404);

// Verify status was NOT marked accepted
$invStatusAfter = $wpdb->get_var($wpdb->prepare("SELECT status FROM $invTable WHERE token_hash = %s", $transTokenHash));
assertCheck("Failed invitation claim remains 'pending' (no dirty commit)", $invStatusAfter === 'pending');


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

// Create pending invitation in this org
$wpdb->insert($invTable, [
    'invitation_ref'   => 'EZEV-INV-DEL-' . $runSuffix,
    'organization_id'  => 0,
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

// Assign member access to this site
$wpdb->replace(EZEV_Core_DB::table('member_site_access'), [
    'member_id'      => 1,
    'site_id'        => 1,
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