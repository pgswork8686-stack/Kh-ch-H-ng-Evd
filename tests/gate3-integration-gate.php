<?php
/**
 * GATE 3 INTEGRATION GATE TEST SUITE
 *
 * Verifies end-to-end entity relationships:
 * Organization -> Site -> Station -> Charger -> Connector -> Session -> Energy -> Alert -> Maintenance
 * And granular multi-tier authorization across 5 role groups:
 * Administrator, Internal Ops/Technical, Business, Partner, Investor, Customer
 */

define('WP_USE_THEMES', false);
$wpLoadPath = 'C:/Users/Admin/Local Sites/test-2/app/public/wp-load.php';
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

function getOrCreateUser(string $login, string $role): WP_User {
    $u = get_user_by('login', $login);
    if ($u) {
        $u->set_role($role);
        return $u;
    }
    $id = wp_create_user($login, 'Password123!@#', $login . '@gate3.test');
    $u = get_userdata($id);
    $u->set_role($role);
    return $u;
}

echo "\n=======================================================\n";
echo "=== GATE 3: CORE PLUGIN FEATURE COMPLETION INTEGRATION ===\n";
echo "=======================================================\n";

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
// Assign user to Organization as site_manager
$managerUser = getOrCreateUser('g3_site_manager', 'ezev_business');
$memReq = new WP_REST_Request('POST', "/ezev/v1/organizations/{$g3OrgId}/members");
$memReq->set_header('content-type', 'application/json');
$memReq->set_body(json_encode([
    'user_id'  => $managerUser->ID,
    'role_key' => 'site_manager',
]));
$memRes = rest_do_request($memReq);
assertCheck("POST /organizations/{id}/members returns 201", $memRes->get_status() === 201);
$g3MemberId = $memRes->get_data()['member']['membership_id'] ?? '';
assertCheck("Created membership has stable membership_id", !empty($g3MemberId));

// Assign Site scope to membership
$scopeSiteReq = new WP_REST_Request('POST', "/ezev/v1/memberships/{$g3MemberId}/sites");
$scopeSiteReq->set_header('content-type', 'application/json');
$scopeSiteReq->set_body(json_encode(['site_id' => $g3SiteId]));
$scopeSiteRes = rest_do_request($scopeSiteReq);
assertCheck("POST /memberships/{id}/sites returns 200", $scopeSiteRes->get_status() === 200);

// -------------------------------------------------------------
echo "\n--- [GROUP 3] Core Invitations Lifecycle ---\n";
// Create invitation token
$invReq = new WP_REST_Request('POST', "/ezev/v1/organizations/{$g3OrgId}/invitations");
$invReq->set_header('content-type', 'application/json');
$invReq->set_body(json_encode([
    'email'    => 'invitee@gate3.test',
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

// Accept invitation by an authenticated user
$inviteeUser = getOrCreateUser('g3_invitee', 'ezev_business');
wp_set_current_user($inviteeUser->ID);
$acceptRes = rest_do_request(new WP_REST_Request('POST', "/ezev/v1/invitations/{$invToken}/accept"));
assertCheck("POST /invitations/{token}/accept returns 200", $acceptRes->get_status() === 200);
assertCheck("Acceptance created active membership", !empty($acceptRes->get_data()['membership_id']));

// Second attempt to accept same token must fail 409
$acceptAgainRes = rest_do_request(new WP_REST_Request('POST', "/ezev/v1/invitations/{$invToken}/accept"));
assertCheck("Duplicate invitation acceptance rejected with 409 conflict", $acceptAgainRes->get_status() === 409);

// -------------------------------------------------------------
echo "\n--- [GROUP 4] Station -> Charger -> Connector Hierarchy ---\n";
wp_set_current_user($adminUser->ID);
$g3StationId = 'EZEV-VN-G3-001';
$stnCreate = EZEV_Core_Stations::create([
    'station_id'      => $g3StationId,
    'name'            => 'Gate3 Fast Charging Station',
    'organization_id' => $g3OrgId,
    'site_id'         => $g3SiteId,
    'country_code'    => 'VN',
    'max_power_kw'    => 240,
    'ports_total'     => 2,
]);
assertCheck("Station created in hierarchy linked to Org & Site", !is_wp_error($stnCreate));

// Assign managerUser specifically to station
$scopeStnReq = new WP_REST_Request('POST', "/ezev/v1/memberships/{$g3MemberId}/stations");
$scopeStnReq->set_header('content-type', 'application/json');
$scopeStnReq->set_body(json_encode(['station_id' => $g3StationId]));
$scopeStnRes = rest_do_request($scopeStnReq);
assertCheck("POST /memberships/{id}/stations returns 200", $scopeStnRes->get_status() === 200);

// Insert Charger & Connector linked to this station
global $wpdb;
$g3ChargerId = $g3StationId . '-CH1';
$g3ConnId = $g3ChargerId . '-CONN1';
$wpdb->replace(EZEV_Operations_DB::table('chargers'), [
    'charger_id'       => $g3ChargerId,
    'station_id'       => $g3StationId,
    'connector_id'     => $g3ConnId,
    'connector_type'   => 'CCS2',
    'max_power_kw'     => 180,
    'status'           => 'available',
    'current_power_kw' => 0,
    'provider'         => 'manual',
    'updated_at'       => current_time('mysql', true),
]);
$wpdb->replace(EZEV_Operations_DB::table('connectors'), [
    'connector_id'     => $g3ConnId,
    'charger_id'       => $g3ChargerId,
    'station_id'       => $g3StationId,
    'connector_type'   => 'CCS2',
    'max_power_kw'     => 180,
    'status'           => 'available',
    'current_power_kw' => 0,
    'provider'         => 'manual',
    'updated_at'       => current_time('mysql', true),
]);

// Read Charger and Connector detail via REST
$chGetRes = rest_do_request(new WP_REST_Request('GET', "/ezev-ops/v1/chargers/{$g3ChargerId}"));
assertCheck("GET /chargers/{id} returns 200", $chGetRes->get_status() === 200);
assertCheck("Charger detail has matching charger_id", ($chGetRes->get_data()['charger']['charger_id'] ?? '') === $g3ChargerId);

$connGetRes = rest_do_request(new WP_REST_Request('GET', "/ezev-ops/v1/connectors/{$g3ConnId}"));
assertCheck("GET /connectors/{id} returns 200", $connGetRes->get_status() === 200);
assertCheck("Connector detail has matching connector_id", ($connGetRes->get_data()['connector']['connector_id'] ?? '') === $g3ConnId);

// -------------------------------------------------------------
echo "\n--- [GROUP 5] Session -> Energy -> Alert -> Maintenance Hierarchy ---\n";
$g3SessionId = 'SESS-G3-' . wp_rand(1000, 9999);
$wpdb->replace(EZEV_Operations_DB::table('sessions'), [
    'session_id'       => $g3SessionId,
    'station_id'       => $g3StationId,
    'charger_id'       => $g3ChargerId,
    'connector_id'     => $g3ConnId,
    'started_at'       => current_time('mysql', true),
    'duration_seconds' => 1800,
    'energy_kwh'       => 45.5,
    'status'           => 'completed',
    'provider'         => 'manual',
]);
$sessGetRes = rest_do_request(new WP_REST_Request('GET', "/ezev-ops/v1/sessions/{$g3SessionId}"));
assertCheck("GET /sessions/{id} returns 200", $sessGetRes->get_status() === 200);
assertCheck("Session detail matches session_id", ($sessGetRes->get_data()['session']['session_id'] ?? '') === $g3SessionId);

// Insert an Alert
$g3AlertId = 'ALT-G3-' . wp_rand(1000, 9999);
$wpdb->replace(EZEV_Operations_DB::table('alerts'), [
    'alert_id'    => $g3AlertId,
    'station_id'  => $g3StationId,
    'charger_id'  => $g3ChargerId,
    'severity'    => 'high',
    'code'        => 'G3_TEMP_WARNING',
    'title'       => 'High Temperature Warning',
    'message'     => 'Inverter temperature reached 75C',
    'status'      => 'open',
    'occurred_at' => current_time('mysql', true),
]);

$alertGetRes = rest_do_request(new WP_REST_Request('GET', "/ezev-ops/v1/alerts/{$g3AlertId}"));
assertCheck("GET /alerts/{id} returns 200", $alertGetRes->get_status() === 200);

// Acknowledge Alert via REST
$ackRes = rest_do_request(new WP_REST_Request('POST', "/ezev-ops/v1/alerts/{$g3AlertId}/acknowledge"));
assertCheck("POST /alerts/{id}/acknowledge returns 200", $ackRes->get_status() === 200);
assertCheck("Alert status is now acknowledged", ($ackRes->get_data()['alert']['status'] ?? '') === 'acknowledged');

// Escalate Alert to Maintenance Ticket
$escRes = rest_do_request(new WP_REST_Request('POST', "/ezev-ops/v1/alerts/{$g3AlertId}/create-ticket"));
assertCheck("POST /alerts/{id}/create-ticket returns 201", $escRes->get_status() === 201);
$g3TicketId = $escRes->get_data()['ticket']['ticket_id'] ?? '';
assertCheck("Escalated ticket has ticket_id", !empty($g3TicketId));

// Read Maintenance detail
$tktGetRes = rest_do_request(new WP_REST_Request('GET', "/ezev-ops/v1/maintenance/{$g3TicketId}"));
assertCheck("GET /maintenance/{id} returns 200", $tktGetRes->get_status() === 200);

// Maintenance Status Transition: open -> in_progress -> resolved
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
echo "\n=======================================================\n";
echo "SUMMARY: {$totalChecks} checks, {$failedChecks} failures.\n";
echo "=======================================================\n";

if ($failedChecks > 0) {
    exit(1);
}
exit(0);