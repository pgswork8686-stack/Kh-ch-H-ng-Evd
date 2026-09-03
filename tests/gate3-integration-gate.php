<?php
/**
 * GATE 3.1 INTEGRATION GATE TEST SUITE
 *
 * Verifies end-to-end entity relationships, Tenancy boundaries,
 * Granular Multi-Tier Authorization, Mutation RBAC, Safe Delete,
 * Real Freshness Semantics, and Activation Cron Lifecycle.
 */

define('WP_USE_THEMES', false);
$wpRoot = getenv('WP_ROOT') ?: (file_exists('C:/Users/Admin/Local Sites/test-2/app/public/wp-load.php') ? 'C:/Users/Admin/Local Sites/test-2/app/public' : dirname(__DIR__, 2) . '/app/public');
$wpLoadPath = rtrim($wpRoot, '/\\') . '/wp-load.php';
if (!file_exists($wpLoadPath)) {
    echo "FATAL: wp-load.php not found at {$wpLoadPath}\n";
    exit(1);
}
require_once $wpLoadPath;
require_once ABSPATH . 'wp-admin/includes/plugin.php';

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
    $userEmail = $email ?: ($login . '@gate3.test');
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
echo "=== GATE 3.1: TENANCY & AUTHORIZATION INTEGRITY INTEGRATION ===\n";
echo "=================================================================\n";

// Ensure roles are installed
EZEV_Core_Roles::install();
$adminUser = get_user_by('login', 'admin');
wp_set_current_user($adminUser->ID);

// Dynamic test identifiers to ensure test idempotency across runs
$runSuffix = wp_rand(1000, 9999);
$testOrgCode = 'G3_' . $runSuffix;
$testSiteCode = 'STE_' . $runSuffix;

// -------------------------------------------------------------
echo "\n--- [GROUP 1] Core Organization & Site CRUD ---\n";
// 1. Create Organization via REST
$orgReq = new WP_REST_Request('POST', '/ezev/v1/organizations');
$orgReq->set_header('content-type', 'application/json');
$orgReq->set_body(json_encode([
    'name'         => 'Gate3 Energy Group ' . $runSuffix,
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
assertCheck("Organization detail matches name", ($orgGetRes->get_data()['organization']['name'] ?? '') === ('Gate3 Energy Group ' . $runSuffix));

// 3. Create Site via REST
$siteReq = new WP_REST_Request('POST', '/ezev/v1/sites');
$siteReq->set_header('content-type', 'application/json');
$siteReq->set_body(json_encode([
    'organization_id' => $g3OrgId,
    'name'            => 'Gate3 Landmark Hub ' . $runSuffix,
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
assertCheck("Site detail matches name", ($siteGetRes->get_data()['site']['name'] ?? '') === ('Gate3 Landmark Hub ' . $runSuffix));


// -------------------------------------------------------------
echo "\n--- [GROUP 2] Core Memberships & Scopes ---\n";
// Create user for site manager with dynamic login for clean isolation
$managerUser = getOrCreateUser('g3_manager_' . $runSuffix, 'ezev_business');

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
echo "\n--- [GROUP 3] Core Invitations Lifecycle & Atomic Email Verification ---\n";
$inviteeEmail = 'invitee_' . $runSuffix . '@gate3.test';
$invReq = new WP_REST_Request('POST', "/ezev/v1/organizations/{$g3OrgId}/invitations");
$invReq->set_header('content-type', 'application/json');
$invReq->set_body(json_encode([
    'email'    => $inviteeEmail,
    'role_key' => 'operations',
]));
$invRes = rest_do_request($invReq);
assertCheck("POST /organizations/{id}/invitations returns 201", $invRes->get_status() === 201);
$invToken = $invRes->get_data()['token'] ?? '';
$invId = $invRes->get_data()['invitation_id'] ?? 0;
assertCheck("Created invitation provides raw token", !empty($invToken));

// Verify token publicly
$verifyRes = rest_do_request(new WP_REST_Request('GET', "/ezev/v1/invitations/{$invToken}"));
assertCheck("GET /invitations/{token} returns 200 (public)", $verifyRes->get_status() === 200);
assertCheck("Verification confirms organization name", ($verifyRes->get_data()['organization_name'] ?? '') === ('Gate3 Energy Group ' . $runSuffix));

// Negative: Wrong user email cannot accept invitation (403 email_mismatch)
$wrongUser = getOrCreateUser('wrong_invitee_' . $runSuffix, 'ezev_business', 'wrong_' . $runSuffix . '@gate3.test');
wp_set_current_user($wrongUser->ID);
$wrongAcceptRes = rest_do_request(new WP_REST_Request('POST', "/ezev/v1/invitations/{$invToken}/accept"));
assertCheck("Wrong email acceptance rejected with 403 email_mismatch", $wrongAcceptRes->get_status() === 403 && $wrongAcceptRes->get_data()['code'] === 'email_mismatch');

// Positive: Correct user accepts invitation
$correctUser = getOrCreateUser('g3_invitee_' . $runSuffix, 'ezev_business', $inviteeEmail);
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
$g3StationId = 'EZEV-VN-G3-' . $runSuffix;
$stationPostId = wp_insert_post([
    'post_title'  => 'Gate3 Central Hub ' . $runSuffix,
    'post_type'   => EZEV_Core_Stations::POST_TYPE,
    'post_status' => 'publish',
]);
update_post_meta($stationPostId, '_ezev_station_id', $g3StationId);
update_post_meta($stationPostId, '_ezev_organization_id', $g3OrgId);
update_post_meta($stationPostId, '_ezev_site_id', $g3SiteId);
update_post_meta($stationPostId, '_ezev_latitude', 10.7769);
update_post_meta($stationPostId, '_ezev_longitude', 106.7009);
assertCheck("Station created in hierarchy linked to Org & Site", !empty($stationPostId));

// Assign Station scope to membership
$scopeStnReq = new WP_REST_Request('POST', "/ezev/v1/memberships/{$g3MembershipId}/stations");
$scopeStnReq->set_header('content-type', 'application/json');
$scopeStnReq->set_body(json_encode(['station_id' => $g3StationId]));
$scopeStnRes = rest_do_request($scopeStnReq);
assertCheck("POST /memberships/{id}/stations returns 200", $scopeStnRes->get_status() === 200);

// Insert Charger & Connector in Operations DB
global $wpdb;
$g3ChargerId = 'CHG-G3-' . $runSuffix;
$g3ConnectorId = 'CONN-G3-' . $runSuffix;
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
$g3SessionId = 'SES-G3-' . $runSuffix;
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
assertCheck("Session detail matches session_id", ($sesRes->get_data()['session']['session_id'] ?? '') === $g3SessionId);

$g3AlertId = 'ALT-G3-' . $runSuffix;
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

$altRes = rest_do_request(new WP_REST_Request('GET', "/ezev-ops/v1/alerts/{$g3AlertId}"));
assertCheck("GET /alerts/{id} returns 200", $altRes->get_status() === 200);

$ackRes = rest_do_request(new WP_REST_Request('POST', "/ezev-ops/v1/alerts/{$g3AlertId}/acknowledge"));
assertCheck("POST /alerts/{id}/acknowledge returns 200", $ackRes->get_status() === 200);
assertCheck("Alert status is now acknowledged", ($ackRes->get_data()['alert']['status'] ?? '') === 'acknowledged');

$escReq = new WP_REST_Request('POST', "/ezev-ops/v1/alerts/{$g3AlertId}/create-ticket");
$escReq->set_header('content-type', 'application/json');
$escReq->set_body(json_encode(['priority' => 'critical']));
$escRes = rest_do_request($escReq);
assertCheck("POST /alerts/{id}/create-ticket returns 201", $escRes->get_status() === 201);
$g3TicketId = $escRes->get_data()['ticket']['ticket_id'] ?? '';
assertCheck("Escalated ticket has ticket_id", !empty($g3TicketId));

$maintGetRes = rest_do_request(new WP_REST_Request('GET', "/ezev-ops/v1/maintenance/{$g3TicketId}"));
assertCheck("GET /maintenance/{id} returns 200", $maintGetRes->get_status() === 200);

$transReq1 = new WP_REST_Request('POST', "/ezev-ops/v1/maintenance/{$g3TicketId}/transition");
$transReq1->set_header('content-type', 'application/json');
$transReq1->set_body(json_encode(['status' => 'in_progress']));
$transRes1 = rest_do_request($transReq1);
assertCheck("Transition to in_progress returns 200", $transRes1->get_status() === 200);
assertCheck("Ticket status is in_progress", ($transRes1->get_data()['ticket']['status'] ?? '') === 'in_progress');

$transReq2 = new WP_REST_Request('POST', "/ezev-ops/v1/maintenance/{$g3TicketId}/transition");
$transReq2->set_header('content-type', 'application/json');
$transReq2->set_body(json_encode(['status' => 'resolved']));
$transRes2 = rest_do_request($transReq2);
assertCheck("Transition to resolved returns 200", $transRes2->get_status() === 200);
assertCheck("Ticket status is resolved and closed_at is set", !empty($transRes2->get_data()['ticket']['closed_at']));


// -------------------------------------------------------------
echo "\n--- [GROUP 6] Granular Multi-Tier Authorization Matrix ---\n";
// 1. Investor: CAN view overview and reports, CANNOT view raw sessions/maintenance
$investorUser = getOrCreateUser('g3_investor', 'ezev_investor');
wp_set_current_user($investorUser->ID);

$invOverview = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/overview'));
assertCheck("Investor CAN access GET /overview (200)", $invOverview->get_status() === 200);

$invReportPerf = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/reports/performance'));
assertCheck("Investor CAN access GET /reports/performance (200)", $invReportPerf->get_status() === 200);

$invSessions = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/sessions'));
assertCheck("Investor CANNOT access raw GET /sessions (403 rest_forbidden_data_tier)", $invSessions->get_status() === 403);

$invMaint = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/maintenance'));
assertCheck("Investor CANNOT access GET /maintenance (403)", $invMaint->get_status() === 403);

// 2. Customer: Strictly 403 across all Operations endpoints
$custUser = getOrCreateUser('g3_customer', 'ezev_customer');
wp_set_current_user($custUser->ID);
$custOverview = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/overview'));
assertCheck("Customer GET /overview is 403", $custOverview->get_status() === 403);
$custSessions = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/sessions'));
assertCheck("Customer GET /sessions is 403", $custSessions->get_status() === 403);

// 3. Scoped Site Manager: Can read chargers for assigned station, filtered properly
wp_set_current_user($managerUser->ID);
$mgrChargers = rest_do_request(new WP_REST_Request('GET', '/ezev-ops/v1/chargers'));
assertCheck("Scoped Site Manager GET /chargers returns 200", $mgrChargers->get_status() === 200);
$mgrChargerList = $mgrChargers->get_data()['chargers'] ?? [];
$allMatch = true;
foreach ($mgrChargerList as $c) {
    if (($c['station_id'] ?? '') !== $g3StationId) {
        $allMatch = false;
        break;
    }
}
assertCheck("Site Manager ONLY sees chargers from assigned station ($g3StationId)", $allMatch && !empty($mgrChargerList));


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
// 1. Customer cannot enumerate all Organizations or Sites
wp_set_current_user($custUser->ID);
$custOrgsRes = rest_do_request(new WP_REST_Request('GET', '/ezev/v1/organizations'));
$custOrgs = $custOrgsRes->get_data()['organizations'] ?? ['unexpected'];
assertCheck("Customer cannot enumerate Organizations (returns empty list)", empty($custOrgs));

$custSitesRes = rest_do_request(new WP_REST_Request('GET', '/ezev/v1/sites'));
$custSites = $custSitesRes->get_data()['sites'] ?? ['unexpected'];
assertCheck("Customer cannot enumerate Sites (returns empty list)", empty($custSites));

$custOrgDetail = rest_do_request(new WP_REST_Request('GET', "/ezev/v1/organizations/{$g3OrgId}"));
assertCheck("Customer cannot read other tenant Organization detail (403 forbidden)", $custOrgDetail->get_status() === 403);

$custSiteDetail = rest_do_request(new WP_REST_Request('GET', "/ezev/v1/sites/{$g3SiteId}"));
assertCheck("Customer cannot read other tenant Site detail (403 forbidden)", $custSiteDetail->get_status() === 403);

// 2. Cross-Organization Boundary Enforcement:
// Create Org B & Site B
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

// Station B belonging to Org B
$stnBPostId = wp_insert_post([
    'post_title'  => 'Foreign Station B ' . $runSuffix,
    'post_type'   => EZEV_Core_Stations::POST_TYPE,
    'post_status' => 'publish',
]);
$g3StationBId = 'EZEV-VN-ORGB-' . $runSuffix;
update_post_meta($stnBPostId, '_ezev_station_id', $g3StationBId);
update_post_meta($stnBPostId, '_ezev_organization_id', $g3OrgBId);
update_post_meta($stnBPostId, '_ezev_site_id', $g3SiteBId);

// Negative: Attempt to assign Site B to Membership of Org A -> Must reject with 422 cross_organization_mismatch
$crossSiteReq = new WP_REST_Request('POST', "/ezev/v1/memberships/{$g3MembershipId}/sites");
$crossSiteReq->set_header('content-type', 'application/json');
$crossSiteReq->set_body(json_encode(['site_id' => $g3SiteBId]));
$crossSiteRes = rest_do_request($crossSiteReq);
assertCheck("Cross-Org Site assignment rejected with 422 cross_organization_mismatch", $crossSiteRes->get_status() === 422 && $crossSiteRes->get_data()['code'] === 'cross_organization_mismatch');

// Negative: Attempt to assign Station B to Membership of Org A -> Must reject with 422 cross_organization_mismatch
$crossStnReq = new WP_REST_Request('POST', "/ezev/v1/memberships/{$g3MembershipId}/stations");
$crossStnReq->set_header('content-type', 'application/json');
$crossStnReq->set_body(json_encode(['station_id' => $g3StationBId]));
$crossStnRes = rest_do_request($crossStnReq);
assertCheck("Cross-Org Station assignment rejected with 422 cross_organization_mismatch", $crossStnRes->get_status() === 422 && $crossStnRes->get_data()['code'] === 'cross_organization_mismatch');

// 3. Safe Delete with Dependency Integrity
$delOrgRes = rest_do_request(new WP_REST_Request('DELETE', "/ezev/v1/organizations/{$g3OrgId}"));
assertCheck("Delete Organization with active dependencies rejected with 409 resource_has_dependencies", $delOrgRes->get_status() === 409 && $delOrgRes->get_data()['code'] === 'resource_has_dependencies');

$delSiteRes = rest_do_request(new WP_REST_Request('DELETE', "/ezev/v1/sites/{$g3SiteId}"));
assertCheck("Delete Site with active stations rejected with 409 resource_has_dependencies", $delSiteRes->get_status() === 409 && $delSiteRes->get_data()['code'] === 'resource_has_dependencies');


// -------------------------------------------------------------
echo "\n--- [GROUP 9] GATE 3.1: Operations Role_key Mutation Negative Tests ---\n";
// Create user with WP role 'ezev_business' but membership role 'finance' and 'viewer'
$financeUser = getOrCreateUser('g3_finance_' . $runSuffix, 'ezev_business');
$wpdb->replace(EZEV_Core_DB::table('org_members'), [
    'organization_id'  => 1,
    'organization_ref' => $g3OrgId,
    'membership_id'    => 'MEM-FIN-' . $runSuffix,
    'user_id'          => $financeUser->ID,
    'role_key'         => 'finance',
    'status'           => 'active',
    'created_at'       => current_time('mysql', true),
    'updated_at'       => current_time('mysql', true),
]);

$viewerUser = getOrCreateUser('g3_viewer_' . $runSuffix, 'ezev_business');
$wpdb->replace(EZEV_Core_DB::table('org_members'), [
    'organization_id'  => 1,
    'organization_ref' => $g3OrgId,
    'membership_id'    => 'MEM-VIEW-' . $runSuffix,
    'user_id'          => $viewerUser->ID,
    'role_key'         => 'viewer',
    'status'           => 'active',
    'created_at'       => current_time('mysql', true),
    'updated_at'       => current_time('mysql', true),
]);

// Negative: Finance user attempts maintenance mutation -> 403
wp_set_current_user($financeUser->ID);
$finMaintReq = new WP_REST_Request('POST', '/ezev-ops/v1/maintenance');
$finMaintReq->set_header('content-type', 'application/json');
$finMaintReq->set_body(json_encode([
    'station_id' => $g3StationId,
    'summary'    => 'Finance Unauthorized Maintenance Attempt',
]));
$finMaintRes = rest_do_request($finMaintReq);
assertCheck("Business user with role_key 'finance' CANNOT create maintenance (403 rest_forbidden)", $finMaintRes->get_status() === 403);

// Negative: Viewer user attempts alert acknowledge -> 403
wp_set_current_user($viewerUser->ID);
$viewAckRes = rest_do_request(new WP_REST_Request('POST', "/ezev-ops/v1/alerts/{$g3AlertId}/acknowledge"));
assertCheck("Business user with role_key 'viewer' CANNOT acknowledge alerts (403 rest_forbidden)", $viewAckRes->get_status() === 403);


// -------------------------------------------------------------
echo "\n--- [GROUP 10] GATE 3.1: Operations Real Freshness Semantics ---\n";
wp_set_current_user($adminUser->ID);
$freshReq = new WP_REST_Request('GET', '/ezev-ops/v1/chargers');
$freshRes = rest_do_request($freshReq);
$meta = $freshRes->get_data()['meta'] ?? [];
assertCheck("Meta contains data_source field", isset($meta['data_source']));
assertCheck("Meta contains data_mode field", isset($meta['data_mode']));
assertCheck("Meta contains last_updated timestamp", !empty($meta['last_updated']));
assertCheck("Meta contains freshness_seconds integer >= 0", isset($meta['freshness_seconds']) && is_numeric($meta['freshness_seconds']));
assertCheck("Meta contains is_stale boolean", isset($meta['is_stale']) && is_bool($meta['is_stale']));
assertCheck("Manual/Demo data_mode is NOT realtime", in_array($meta['data_mode'], ['manual', 'demo', 'api'], true));


// -------------------------------------------------------------
echo "\n--- [GROUP 11] GATE 3.1: Fresh-Activation Cron Lifecycle Verification ---\n";
// Clear existing schedules
EZEV_Operations_Sync::unschedule();
assertCheck("Cron events can be cleanly unscheduled", !wp_next_scheduled('ezevo_sync_event') && !wp_next_scheduled('ezevo_cleanup_receipts_event'));

// Trigger fresh activation
EZEV_Operations::activate();

$syncNext = wp_next_scheduled('ezevo_sync_event');
$cleanNext = wp_next_scheduled('ezevo_cleanup_receipts_event');
assertCheck("Fresh activation schedules ezevo_sync_event", $syncNext !== false && $syncNext > time());
assertCheck("Fresh activation schedules ezevo_cleanup_receipts_event", $cleanNext !== false && $cleanNext > time());

$schedules = wp_get_schedules();
assertCheck("Custom recurrence ezevo_5min (300s) registered in WordPress", isset($schedules['ezevo_5min']) && $schedules['ezevo_5min']['interval'] === 300);
assertCheck("Custom recurrence ezevo_hourly (3600s) registered in WordPress", isset($schedules['ezevo_hourly']) && $schedules['ezevo_hourly']['interval'] === 3600);


// -------------------------------------------------------------
echo "\n=================================================================\n";
echo "SUMMARY: {$totalChecks} checks, {$failedChecks} failures.\n";
echo "=================================================================\n";

if ($failedChecks > 0) {
    exit(1);
}
exit(0);