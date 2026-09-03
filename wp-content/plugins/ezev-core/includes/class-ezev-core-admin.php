<?php
if (!defined('ABSPATH')) { exit; }

final class EZEV_Core_Admin {
    public static function init(): void {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
        add_action('admin_post_ezev_save_station', [self::class, 'handle_save_station']);
        add_action('admin_post_ezev_seed_demo', [self::class, 'handle_seed_demo']);
        add_action('admin_post_ezev_reset_demo', [self::class, 'handle_reset_demo']);
        add_action('admin_post_ezev_save_map_settings', [self::class, 'handle_map_settings']);
        add_action('admin_post_ezev_create_org', [self::class, 'handle_create_org']);
        add_action('admin_post_ezev_create_site', [self::class, 'handle_create_site']);
        add_action('admin_post_ezev_assign_member', [self::class, 'handle_assign_member']);
        add_action('admin_notices', [self::class, 'notices']);
    }

    public static function menu(): void {
        add_menu_page('EZEV Core', 'EZEV Core', 'ezev_view_core', 'ezev-core', [self::class, 'dashboard'], 'dashicons-location-alt', 3);
        add_submenu_page('ezev-core', 'Dashboard', 'Dashboard', 'ezev_view_core', 'ezev-core', [self::class, 'dashboard']);
        add_submenu_page('ezev-core', 'Stations', 'Stations', 'ezev_manage_stations', 'ezev-stations', [self::class, 'stations']);
        add_submenu_page('ezev-core', 'Add Station', 'Add Station', 'ezev_manage_stations', 'ezev-add-station', [self::class, 'add_station']);
        add_submenu_page('ezev-core', 'Organizations', 'Organizations & Sites', 'ezev_manage_organizations', 'ezev-organizations', [self::class, 'organizations']);
        add_submenu_page('ezev-core', 'Users & Access', 'Users & Access', 'ezev_manage_access', 'ezev-access', [self::class, 'access']);
        add_submenu_page('ezev-core', 'Maps & Integrations', 'Maps & Integrations', 'ezev_manage_integrations', 'ezev-map-settings', [self::class, 'map_settings']);
        add_submenu_page('ezev-core', 'Demo Data', 'Demo Data', 'ezev_manage_stations', 'ezev-demo-data', [self::class, 'demo_data']);
    }

    public static function assets(string $hook): void {
        if (strpos($hook, 'ezev') === false) { return; }
        wp_enqueue_style('ezev-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap', [], null);
        wp_enqueue_style('ezev-core-admin', EZEV_CORE_URL . 'assets/css/admin.css', ['ezev-inter'], EZEV_CORE_VERSION);
        wp_enqueue_script('ezev-core-admin', EZEV_CORE_URL . 'assets/js/admin.js', [], EZEV_CORE_VERSION, true);
        $enc = (string) get_option('ezev_map_api_key', '');
        wp_localize_script('ezev-core-admin', 'EZEV_ADMIN', [
            'stations' => EZEV_Core_Stations::list(),
            'googleMapsKey' => self::decrypt_secret($enc),
            'googleMapId' => sanitize_text_field((string) get_option('ezev_google_map_id', 'DEMO_MAP_ID')),
            'defaultCenter' => get_option('ezev_map_default_center', '107.0,15.5'),
            'defaultZoom' => (float) get_option('ezev_map_default_zoom', 4.2),
            'countryBias' => sanitize_text_field((string) get_option('ezev_google_country_bias', 'VN,PH,CN')),
            'mapConfigured' => $enc !== '',
            'settingsUrl' => admin_url('admin.php?page=ezev-map-settings'),
            'brand' => ['dark' => '#123D38', 'green' => '#2FA866', 'lime' => '#B7D52A', 'blue' => '#2E7892'],
        ]);
    }

    private static function header(string $title, string $subtitle = ''): void { ?>
        <div class="ezev-admin-wrap">
            <div class="ezev-admin-hero">
                <div class="ezev-brand-lockup">
                    <img src="<?php echo esc_url(EZEV_CORE_URL . 'assets/images/ezev-logo.svg'); ?>" alt="EZEV">
                    <span>CORE PLATFORM</span>
                </div>
                <div>
                    <h1><?php echo esc_html($title); ?></h1>
                    <?php if ($subtitle): ?><p><?php echo esc_html($subtitle); ?></p><?php endif; ?>
                </div>
                <div class="ezev-status-pill"><span></span> Core v<?php echo esc_html(EZEV_CORE_VERSION); ?></div>
            </div>
    <?php }

    private static function footer(): void {
        echo '</div>';
    }

    public static function dashboard(): void {
        if (!current_user_can('ezev_view_core')) { wp_die('Access denied.'); }
        global $wpdb;
        $stations = EZEV_Core_Stations::list();
        $orgs = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . EZEV_Core_DB::table('organizations'));
        $sites = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . EZEV_Core_DB::table('sites'));
        $members = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . EZEV_Core_DB::table('org_members'));
        $countries = array_unique(array_filter(array_column($stations, 'country_code')));
        self::header('Core Dashboard', 'Master data, map, organizations, users and access scopes.'); ?>
        <div class="ezev-kpi-grid">
            <?php self::kpi('Stations', count($stations), '60 demo records are included for local testing', 'location-alt'); ?>
            <?php self::kpi('Countries', count($countries), implode(' · ', $countries), 'admin-site-alt3'); ?>
            <?php self::kpi('Organizations', $orgs, 'Business / Partner / Investor', 'building'); ?>
            <?php self::kpi('Sites & Members', $sites . ' / ' . $members, 'Site scope and delegated access', 'groups'); ?>
        </div>
        <div class="ezev-panel-grid ezev-panel-grid-2">
            <section class="ezev-panel">
                <div class="ezev-panel-head"><div><span class="ezev-eyebrow">REAL MAP FOUNDATION</span><h2>Station Master Map</h2></div><a class="ezev-button" href="<?php echo esc_url(admin_url('admin.php?page=ezev-stations')); ?>">Open manager</a></div>
                <div id="ezev-dashboard-map" class="ezev-admin-map" data-ezev-map="dashboard"></div>
                <div class="ezev-map-legend"><span><i class="ok"></i> Active/manual</span><span><i class="demo"></i> Demo record</span></div>
            </section>
            <section class="ezev-panel">
                <span class="ezev-eyebrow">SYSTEM MODEL</span><h2>Identity → Organization → Site → Station</h2>
                <div class="ezev-flow-stack">
                    <div><strong>User</strong><span>Customer / Business / Partner / Internal</span></div>
                    <b>↓</b><div><strong>Organization</strong><span>Company, partner or investor workspace</span></div>
                    <b>↓</b><div><strong>Site</strong><span>Physical business location / depot / property</span></div>
                    <b>↓</b><div><strong>Station</strong><span>Public or private charging location</span></div>
                </div>
                <div class="ezev-callout">Frontend location uses GPS/geocoding. Account access uses User ID + Organization + Role + Site/Station Scope. These are intentionally separate.</div>
            </section>
        </div>
        <section class="ezev-panel">
            <div class="ezev-panel-head"><div><span class="ezev-eyebrow">QUICK START</span><h2>Local test flow</h2></div></div>
            <div class="ezev-step-grid">
                <?php foreach ([['1','Demo Stations','60 records are seeded automatically.'],['2','Google Maps','Add a restricted Google Maps Platform browser API key.'],['3','Add Station','Geocode a real address, drag the Google marker and verify coordinates live.'],['4','Publish','Station appears immediately through the REST API.'],['5','Assign Access','Link users to organization/site/station scopes.'],['6','Operations','EZEV Operations reads the same Station ID.']] as $s): ?><div class="ezev-step"><span><?php echo esc_html($s[0]); ?></span><strong><?php echo esc_html($s[1]); ?></strong><p><?php echo esc_html($s[2]); ?></p></div><?php endforeach; ?>
            </div>
        </section>
        <?php self::footer();
    }

    private static function kpi(string $label, string|int $value, string $hint, string $icon): void { ?>
        <div class="ezev-kpi"><div class="ezev-kpi-icon"><span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span></div><div><small><?php echo esc_html($label); ?></small><strong><?php echo esc_html((string) $value); ?></strong><p><?php echo esc_html($hint); ?></p></div></div>
    <?php }

    public static function stations(): void {
        if (!current_user_can('ezev_manage_stations')) { wp_die('Access denied.'); }
        $stations = EZEV_Core_Stations::list();
        self::header('Station Manager', 'Table + Google Maps split view. Click a station to verify the exact location.'); ?>
        <div class="ezev-toolbar"><input type="search" placeholder="Search station, city or ID..." data-ezev-station-search><select data-ezev-country-filter><option value="">All countries</option><option value="VN">Vietnam</option><option value="PH">Philippines</option><option value="CN">China</option></select><a class="ezev-button ezev-button-primary" href="<?php echo esc_url(admin_url('admin.php?page=ezev-add-station')); ?>">+ Add Station</a></div>
        <div class="ezev-station-manager">
            <section class="ezev-panel ezev-station-table-panel">
                <div class="ezev-table-scroll"><table class="ezev-table"><thead><tr><th>Station</th><th>Country</th><th>Power</th><th>Ports</th><th>Status</th><th></th></tr></thead><tbody data-ezev-station-table>
                <?php foreach ($stations as $s): ?><tr data-station-row data-id="<?php echo esc_attr($s['post_id']); ?>" data-country="<?php echo esc_attr($s['country_code']); ?>" data-search="<?php echo esc_attr(strtolower($s['station_id'].' '.$s['name'].' '.$s['city'].' '.$s['address'])); ?>"><td><strong><?php echo esc_html($s['name']); ?></strong><small><?php echo esc_html($s['station_id']); ?> · <?php echo esc_html($s['city']); ?></small></td><td><?php echo esc_html($s['country_code']); ?></td><td><?php echo esc_html((string)$s['max_power_kw']); ?> kW</td><td><?php echo esc_html((string)$s['ports_total']); ?></td><td><span class="ezev-badge <?php echo $s['is_demo'] ? 'demo' : 'active'; ?>"><?php echo $s['is_demo'] ? 'Demo' : esc_html(ucfirst($s['operational_status_manual'])); ?></span></td><td><a href="<?php echo esc_url(admin_url('admin.php?page=ezev-add-station&station=' . $s['post_id'])); ?>">Edit</a></td></tr><?php endforeach; ?>
                </tbody></table></div>
            </section>
            <section class="ezev-panel ezev-station-map-panel"><div id="ezev-stations-map" class="ezev-admin-map ezev-admin-map-tall" data-ezev-map="stations"></div><div class="ezev-selected-card" data-ezev-selected-card><span class="ezev-eyebrow">SELECT A STATION</span><h3>Map verification</h3><p>Click a station row or marker to verify its coordinates.</p></div></section>
        </div>
        <?php self::footer();
    }

    public static function add_station(): void {
        if (!current_user_can('ezev_manage_stations')) { wp_die('Access denied.'); }
        $post_id = isset($_GET['station']) ? absint($_GET['station']) : 0;
        $station = $post_id ? EZEV_Core_Stations::to_array($post_id) : [];
        global $wpdb;
        $orgs = $wpdb->get_results("SELECT id,name FROM " . EZEV_Core_DB::table('organizations') . " WHERE status='active' ORDER BY name", ARRAY_A) ?: [];
        $sites = $wpdb->get_results("SELECT id,name,organization_id FROM " . EZEV_Core_DB::table('sites') . " WHERE status='active' ORDER BY name", ARRAY_A) ?: [];
        self::header($post_id ? 'Edit Station' : 'Add Station', 'Enter station data and verify the exact point on the live map before publishing.'); ?>
        <form class="ezev-station-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="ezev_save_station"><input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>"><?php wp_nonce_field('ezev_admin_save_station', 'ezev_admin_nonce'); ?>
            <div class="ezev-station-editor">
                <section class="ezev-panel ezev-form-panel">
                    <span class="ezev-eyebrow">STATION MASTER DATA</span><h2><?php echo $post_id ? 'Update location' : 'Create a new location'; ?></h2>
                    <div class="ezev-fields-2"><label>Station ID<input required name="station_id" value="<?php echo esc_attr($station['station_id'] ?? ''); ?>" placeholder="EZEV-VN-HN-001"></label><label>Station Name<input required name="name" value="<?php echo esc_attr($station['name'] ?? ''); ?>" placeholder="EZEV Hanoi Center"></label></div>
                    <div class="ezev-fields-3"><label>Country<select name="country_code" data-ezev-country-select><option value="VN" <?php selected($station['country_code'] ?? '', 'VN'); ?>>Vietnam</option><option value="PH" <?php selected($station['country_code'] ?? '', 'PH'); ?>>Philippines</option><option value="CN" <?php selected($station['country_code'] ?? '', 'CN'); ?>>China</option></select></label><label>City<input name="city" value="<?php echo esc_attr($station['city'] ?? ''); ?>"></label><label>Region<input name="region" value="<?php echo esc_attr($station['region'] ?? ''); ?>"></label></div>
                    <label>Address<div class="ezev-inline-field"><input name="address" data-ezev-address value="<?php echo esc_attr($station['address'] ?? ''); ?>" placeholder="Type address, then locate it on the map"><button type="button" class="ezev-button" data-ezev-geocode>Locate on Google Maps</button></div></label>
                    <div class="ezev-fields-2"><label>Latitude<input required type="number" step="0.0000001" name="latitude" data-ezev-lat value="<?php echo esc_attr((string)($station['latitude'] ?? '')); ?>"></label><label>Longitude<input required type="number" step="0.0000001" name="longitude" data-ezev-lng value="<?php echo esc_attr((string)($station['longitude'] ?? '')); ?>"></label></div>
                    <div class="ezev-divider"></div><span class="ezev-eyebrow">CHARGING CONFIGURATION</span>
                    <div class="ezev-fields-3"><label>Max Power (kW)<input type="number" step="0.1" name="max_power_kw" value="<?php echo esc_attr((string)($station['max_power_kw'] ?? 180)); ?>"></label><label>Total Ports<input type="number" name="ports_total" value="<?php echo esc_attr((string)($station['ports_total'] ?? 4)); ?>"></label><label>Manual Available<input type="number" name="ports_available_manual" value="<?php echo esc_attr((string)($station['ports_available_manual'] ?? 0)); ?>"></label></div>
                    <div class="ezev-fields-2"><label>Connector Types<input name="connector_types" value="<?php echo esc_attr(implode(', ', (array)($station['connector_types'] ?? ['CCS2']))); ?>" placeholder="CCS2, Type 2"></label><label>Opening Hours<input name="opening_hours" value="<?php echo esc_attr($station['opening_hours'] ?? '24/7'); ?>"></label></div>
                    <div class="ezev-fields-2"><label>Status<select name="operational_status_manual"><option value="active" <?php selected($station['operational_status_manual'] ?? 'active','active'); ?>>Active</option><option value="maintenance" <?php selected($station['operational_status_manual'] ?? '','maintenance'); ?>>Maintenance</option><option value="temporarily_closed" <?php selected($station['operational_status_manual'] ?? '','temporarily_closed'); ?>>Temporarily Closed</option></select></label><label>Amenities<input name="amenities" value="<?php echo esc_attr(implode(', ', (array)($station['amenities'] ?? []))); ?>" placeholder="parking, cafe, restroom, wifi"></label></div>
                    <div class="ezev-divider"></div><span class="ezev-eyebrow">OWNERSHIP & ACCESS</span>
                    <div class="ezev-fields-2"><label>Organization<select name="organization_id"><option value="0">Unassigned / Public Network</option><?php foreach ($orgs as $o): ?><option value="<?php echo (int)$o['id']; ?>" <?php selected((int)($station['organization_id'] ?? 0),(int)$o['id']); ?>><?php echo esc_html($o['name']); ?></option><?php endforeach; ?></select></label><label>Site<select name="site_id"><option value="0">No site</option><?php foreach ($sites as $s): ?><option value="<?php echo (int)$s['id']; ?>" <?php selected((int)($station['site_id'] ?? 0),(int)$s['id']); ?>><?php echo esc_html($s['name']); ?></option><?php endforeach; ?></select></label></div>
                    <label>Public notes<textarea name="public_notes" rows="3"><?php echo esc_textarea($station['public_notes'] ?? ''); ?></textarea></label>
                    <div class="ezev-form-actions"><a class="ezev-button" href="<?php echo esc_url(admin_url('admin.php?page=ezev-stations')); ?>">Cancel</a><button class="ezev-button ezev-button-primary" type="submit"><?php echo $post_id ? 'Update Station' : 'Publish Station'; ?></button></div>
                </section>
                <section class="ezev-panel ezev-live-preview"><div class="ezev-panel-head"><div><span class="ezev-eyebrow">LIVE VERIFICATION</span><h2>Map Preview</h2></div><span class="ezev-badge active">Google Maps</span></div><div id="ezev-editor-map" class="ezev-admin-map ezev-admin-map-editor" data-ezev-map="editor" data-lat="<?php echo esc_attr((string)($station['latitude'] ?? '')); ?>" data-lng="<?php echo esc_attr((string)($station['longitude'] ?? '')); ?>"></div><div class="ezev-callout"><strong>Drag the marker</strong> if the geocoded location is not exact. Latitude and longitude update immediately.</div><div class="ezev-public-preview" data-ezev-public-preview><small>PUBLIC CARD PREVIEW</small><h3><?php echo esc_html($station['name'] ?? 'New EZEV Station'); ?></h3><p><?php echo esc_html($station['address'] ?? 'Address will appear here'); ?></p><div><span>CCS2</span><span><?php echo esc_html((string)($station['max_power_kw'] ?? 180)); ?> kW</span><span>24/7</span></div></div></section>
            </div>
        </form>
        <?php self::footer();
    }

    public static function organizations(): void {
        if (!current_user_can('ezev_manage_organizations')) { wp_die('Access denied.'); }
        global $wpdb;
        $orgs = $wpdb->get_results("SELECT * FROM " . EZEV_Core_DB::table('organizations') . " ORDER BY name", ARRAY_A) ?: [];
        $sites = $wpdb->get_results("SELECT s.*, o.name AS organization_name FROM " . EZEV_Core_DB::table('sites') . " s LEFT JOIN " . EZEV_Core_DB::table('organizations') . " o ON o.id=s.organization_id ORDER BY s.name", ARRAY_A) ?: [];
        self::header('Organizations & Sites', 'Model business customers, partners and investors without mixing identity, locations and station IDs.'); ?>
        <div class="ezev-panel-grid ezev-panel-grid-2">
            <section class="ezev-panel"><span class="ezev-eyebrow">NEW ORGANIZATION</span><h2>Create workspace</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('ezev_create_org'); ?><input type="hidden" name="action" value="ezev_create_org"><label>Organization Code<input name="org_code" required placeholder="ORG-0001"></label><label>Name<input name="name" required placeholder="ABC Logistics"></label><div class="ezev-fields-2"><label>Type<select name="type"><option value="business">Business Customer</option><option value="partner">Partner</option><option value="investor">Investor</option></select></label><label>Country<select name="country_code"><option value="VN">Vietnam</option><option value="PH">Philippines</option><option value="CN">China</option></select></label></div><button class="ezev-button ezev-button-primary">Create organization</button></form></section>
            <section class="ezev-panel"><span class="ezev-eyebrow">NEW SITE</span><h2>Add physical location</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('ezev_create_site'); ?><input type="hidden" name="action" value="ezev_create_site"><label>Organization<select name="organization_id" required><option value="">Select</option><?php foreach($orgs as $o): ?><option value="<?php echo (int)$o['id']; ?>"><?php echo esc_html($o['name']); ?></option><?php endforeach; ?></select></label><label>Site Code<input name="site_code" required placeholder="SITE-HN-01"></label><label>Site Name<input name="name" required placeholder="Hanoi Warehouse"></label><label>Address<input name="address"></label><button class="ezev-button ezev-button-primary">Create site</button></form></section>
        </div>
        <section class="ezev-panel"><div class="ezev-panel-head"><div><span class="ezev-eyebrow">WORKSPACES</span><h2>Organizations</h2></div><span class="ezev-badge active"><?php echo count($orgs); ?> organizations</span></div><div class="ezev-card-grid"><?php foreach($orgs as $o): ?><div class="ezev-org-card"><span class="ezev-badge"><?php echo esc_html(strtoupper($o['type'])); ?></span><h3><?php echo esc_html($o['name']); ?></h3><p><?php echo esc_html($o['org_code'].' · '.$o['country_code']); ?></p><small><?php echo esc_html($o['status']); ?></small></div><?php endforeach; ?><?php if(!$orgs): ?><p>No organizations yet.</p><?php endif; ?></div></section>
        <section class="ezev-panel"><span class="ezev-eyebrow">PHYSICAL LOCATIONS</span><h2>Sites</h2><div class="ezev-table-scroll"><table class="ezev-table"><thead><tr><th>Site</th><th>Organization</th><th>Address</th><th>Status</th></tr></thead><tbody><?php foreach($sites as $s): ?><tr><td><strong><?php echo esc_html($s['name']); ?></strong><small><?php echo esc_html($s['site_code']); ?></small></td><td><?php echo esc_html($s['organization_name'] ?: '—'); ?></td><td><?php echo esc_html($s['address']); ?></td><td><span class="ezev-badge active"><?php echo esc_html($s['status']); ?></span></td></tr><?php endforeach; ?></tbody></table></div></section>
        <?php self::footer();
    }

    public static function access(): void {
        if (!current_user_can('ezev_manage_access')) { wp_die('Access denied.'); }
        global $wpdb;
        $orgs = $wpdb->get_results("SELECT id,name FROM " . EZEV_Core_DB::table('organizations') . " ORDER BY name", ARRAY_A) ?: [];
        $members = $wpdb->get_results("SELECT m.*,o.name organization_name,u.display_name,u.user_email FROM " . EZEV_Core_DB::table('org_members') . " m JOIN " . EZEV_Core_DB::table('organizations') . " o ON o.id=m.organization_id JOIN {$wpdb->users} u ON u.ID=m.user_id ORDER BY o.name,u.display_name", ARRAY_A) ?: [];
        $users = get_users(['orderby' => 'display_name', 'order' => 'ASC']);
        self::header('Users & Access', 'Authentication identity is separate from organization membership and location scope.'); ?>
        <section class="ezev-panel"><span class="ezev-eyebrow">ASSIGN MEMBERSHIP</span><h2>User → Organization → Role</h2><form class="ezev-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('ezev_assign_member'); ?><input type="hidden" name="action" value="ezev_assign_member"><label>User<select name="user_id" required><option value="">Select user</option><?php foreach($users as $u): ?><option value="<?php echo (int)$u->ID; ?>"><?php echo esc_html($u->display_name.' — '.$u->user_email); ?></option><?php endforeach; ?></select></label><label>Organization<select name="organization_id" required><option value="">Select organization</option><?php foreach($orgs as $o): ?><option value="<?php echo (int)$o['id']; ?>"><?php echo esc_html($o['name']); ?></option><?php endforeach; ?></select></label><label>Role<select name="role_key"><option value="owner">Owner</option><option value="admin">Admin</option><option value="operations">Operations</option><option value="finance">Finance</option><option value="site_manager">Site Manager</option><option value="support">Support</option><option value="viewer">Viewer</option></select></label><button class="ezev-button ezev-button-primary">Assign</button></form><p class="description">Site/station scopes can be added after the base membership is created. Owner/Admin with no explicit scope inherits the organization's assigned stations.</p></section>
        <section class="ezev-panel"><div class="ezev-panel-head"><div><span class="ezev-eyebrow">MEMBERSHIPS</span><h2>Delegated access</h2></div><span class="ezev-badge active"><?php echo count($members); ?> memberships</span></div><div class="ezev-table-scroll"><table class="ezev-table"><thead><tr><th>User</th><th>Organization</th><th>Role</th><th>Status</th></tr></thead><tbody><?php foreach($members as $m): ?><tr><td><strong><?php echo esc_html($m['display_name']); ?></strong><small><?php echo esc_html($m['user_email']); ?></small></td><td><?php echo esc_html($m['organization_name']); ?></td><td><?php echo esc_html(EZEV_Core_Roles::role_label($m['role_key'])); ?></td><td><span class="ezev-badge active"><?php echo esc_html($m['status']); ?></span></td></tr><?php endforeach; ?></tbody></table></div></section>
        <?php self::footer();
    }

    public static function map_settings(): void {
        if (!current_user_can('ezev_manage_integrations')) { wp_die('Access denied.'); }
        $enc = (string) get_option('ezev_map_api_key','');
        $masked = self::masked_secret($enc);
        self::header('Google Maps Platform', 'One real map provider for station creation, Find a Charger and network operations.'); ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('ezev_map_settings'); ?><input type="hidden" name="action" value="ezev_save_map_settings">
            <div class="ezev-map-settings-hero">
                <div><span class="ezev-eyebrow">PRODUCTION MAP ENGINE</span><h2>Google Maps Platform</h2><p>Maps JavaScript API drives every map. Address lookup uses Google Geocoder and Places when available. Directions open Google Maps with the exact station coordinates.</p></div>
                <div class="ezev-api-state <?php echo $enc ? 'is-ready' : 'is-missing'; ?>"><i></i><div><strong><?php echo $enc ? 'API key configured' : 'API key required'; ?></strong><span><?php echo esc_html($masked); ?></span></div></div>
            </div>
            <div class="ezev-panel-grid ezev-panel-grid-2 ezev-settings-grid">
                <section class="ezev-panel"><span class="ezev-eyebrow">GOOGLE CLOUD</span><h2>Browser API credentials</h2>
                    <label>Google Maps API Key<input type="password" name="map_api_key" value="" autocomplete="new-password" placeholder="<?php echo $enc ? 'Leave blank to keep current key' : 'AIza...'; ?>"><small>Restrict this key by HTTP referrer and allow only the Maps APIs used by EZEV.</small></label>
                    <label>Google Map ID<input name="google_map_id" value="<?php echo esc_attr(get_option('ezev_google_map_id','DEMO_MAP_ID')); ?>" placeholder="DEMO_MAP_ID"><small>Use your production Map ID for Advanced Markers. DEMO_MAP_ID is acceptable during local development.</small></label>
                    <div class="ezev-map-api-list"><span>Maps JavaScript API</span><span>Places API</span><span>Geocoding</span><span>Google Maps Directions Link</span></div>
                </section>
                <section class="ezev-panel"><span class="ezev-eyebrow">DEFAULT EXPERIENCE</span><h2>Map behavior</h2>
                    <div class="ezev-fields-2"><label>Default Center (lng,lat)<input name="default_center" value="<?php echo esc_attr(get_option('ezev_map_default_center','107.0,15.5')); ?>"></label><label>Default Zoom<input name="default_zoom" type="number" step="0.1" min="2" max="20" value="<?php echo esc_attr(get_option('ezev_map_default_zoom',4.2)); ?>"></label></div>
                    <label>Country bias<input name="country_bias" value="<?php echo esc_attr(get_option('ezev_google_country_bias','VN,PH,CN')); ?>" placeholder="VN,PH,CN"><small>Used for address autocomplete/search prioritization; it never replaces GPS.</small></label>
                    <div class="ezev-callout"><strong>Real-data rule:</strong> when no valid Google key is configured, EZEV shows a setup state instead of rendering a fake/static map.</div>
                </section>
            </div>
            <section class="ezev-panel"><div class="ezev-panel-head"><div><span class="ezev-eyebrow">CONNECTION CHECK</span><h2>Google Maps test</h2></div><button type="button" class="ezev-button" data-ezev-google-test>Test connection</button></div><div class="ezev-google-test" data-ezev-google-test-result><span>Save the key, then run a live browser test.</span></div></section>
            <button class="ezev-button ezev-button-primary">Save Google Maps settings</button>
        </form>
        <?php self::footer();
    }

    public static function demo_data(): void {
        if (!current_user_can('ezev_manage_stations')) { wp_die('Access denied.'); }
        $stations = EZEV_Core_Stations::list(); $demo = count(array_filter($stations, static fn($s) => $s['is_demo']));
        self::header('Demo Data', '60 seeded stations for Vietnam, Philippines and China.'); ?>
        <section class="ezev-panel"><div class="ezev-kpi-grid"><div class="ezev-kpi"><div><small>Total stations</small><strong><?php echo count($stations); ?></strong><p>All station master records</p></div></div><div class="ezev-kpi"><div><small>Demo records</small><strong><?php echo $demo; ?></strong><p>Safe to remove when official data arrives</p></div></div></div><div class="ezev-callout"><strong>Important:</strong> Demo coordinates are representative development locations and are not claims of real EZEV charging sites.</div><div class="ezev-form-actions"><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('ezev_seed_demo'); ?><input type="hidden" name="action" value="ezev_seed_demo"><button class="ezev-button ezev-button-primary">Seed / Refresh 60 Demo Stations</button></form><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Delete all demo station records?');"><?php wp_nonce_field('ezev_reset_demo'); ?><input type="hidden" name="action" value="ezev_reset_demo"><button class="ezev-button ezev-button-danger">Remove Demo Stations</button></form></div></section>
        <?php self::footer();
    }

    public static function handle_save_station(): void {
        if (!current_user_can('ezev_manage_stations')) { wp_die('Access denied.'); }
        check_admin_referer('ezev_admin_save_station', 'ezev_admin_nonce');
        $post_id = absint($_POST['post_id'] ?? 0);
        $data = wp_unslash($_POST);
        if ($post_id) {
            $existing = get_post($post_id); if (!$existing || $existing->post_type !== EZEV_Core_Stations::POST_TYPE) { wp_die('Invalid station.'); }
            $data['station_id'] = sanitize_text_field($data['station_id'] ?? '');
            $data['name'] = sanitize_text_field($data['name'] ?? '');
            $result = wp_update_post(['ID'=>$post_id,'post_title'=>$data['name'],'post_status'=>'publish'], true);
            if (!is_wp_error($result)) { EZEV_Core_Stations::save_fields($post_id, $data); }
        } else {
            $result = EZEV_Core_Stations::create($data);
            $post_id = is_wp_error($result) ? 0 : (int)$result;
        }
        $status = (isset($result) && is_wp_error($result)) ? 'error' : 'saved';
        wp_safe_redirect(admin_url('admin.php?page=ezev-add-station&station=' . $post_id . '&ezev_notice=' . $status)); exit;
    }

    public static function handle_seed_demo(): void { if (!current_user_can('ezev_manage_stations')) { wp_die('Access denied.'); } check_admin_referer('ezev_seed_demo'); $r=EZEV_Core_Stations::seed_demo_if_empty(true); wp_safe_redirect(admin_url('admin.php?page=ezev-demo-data&ezev_notice=seeded&created='.(int)($r['created']??0).'&updated='.(int)($r['updated']??0))); exit; }
    public static function handle_reset_demo(): void { if (!current_user_can('ezev_manage_stations')) { wp_die('Access denied.'); } check_admin_referer('ezev_reset_demo'); $c=EZEV_Core_Stations::delete_demo(); wp_safe_redirect(admin_url('admin.php?page=ezev-demo-data&ezev_notice=removed&count='.$c)); exit; }

    public static function handle_map_settings(): void {
        if (!current_user_can('ezev_manage_integrations')) { wp_die('Access denied.'); }
        check_admin_referer('ezev_map_settings');
        update_option('ezev_google_map_id', sanitize_text_field(wp_unslash($_POST['google_map_id'] ?? 'DEMO_MAP_ID')), false);
        update_option('ezev_google_country_bias', sanitize_text_field(wp_unslash($_POST['country_bias'] ?? 'VN,PH,CN')), false);
        update_option('ezev_map_default_center', sanitize_text_field(wp_unslash($_POST['default_center'] ?? '107.0,15.5')), false);
        update_option('ezev_map_default_zoom', (float)($_POST['default_zoom'] ?? 4.2), false);
        if (!empty($_POST['map_api_key'])) {
            update_option('ezev_map_api_key', self::encrypt_secret(sanitize_text_field(wp_unslash($_POST['map_api_key']))), false);
        }
        EZEV_Core_DB::log('google_maps_settings_updated','integration');
        wp_safe_redirect(admin_url('admin.php?page=ezev-map-settings&ezev_notice=saved')); exit;
    }

    private static function encrypt_secret(string $plain): string {
        if ($plain === '') { return ''; }
        $key = hash('sha256', wp_salt('auth'), true);
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $cipher);
    }

    private static function masked_secret(string $enc): string {
        $plain = self::decrypt_secret($enc);
        if ($plain === '') { return 'Not configured'; }
        if (strlen($plain) <= 10) { return '••••••••'; }
        return substr($plain, 0, 5) . '••••••••••' . substr($plain, -4);
    }

    public static function decrypt_secret(string $enc): string {
        if ($enc === '') { return ''; } $raw=base64_decode($enc, true); if ($raw===false || strlen($raw)<17) return '';
        $iv=substr($raw,0,16); $cipher=substr($raw,16); $key=hash('sha256', wp_salt('auth'), true);
        return (string)openssl_decrypt($cipher,'AES-256-CBC',$key,OPENSSL_RAW_DATA,$iv);
    }

    public static function handle_create_org(): void {
        if (!current_user_can('ezev_manage_organizations')) { wp_die('Access denied.'); } check_admin_referer('ezev_create_org'); global $wpdb; $now=current_time('mysql',true);
        $wpdb->insert(EZEV_Core_DB::table('organizations'),['org_code'=>sanitize_text_field(wp_unslash($_POST['org_code']??'')),'name'=>sanitize_text_field(wp_unslash($_POST['name']??'')),'type'=>sanitize_key($_POST['type']??'business'),'country_code'=>sanitize_text_field(wp_unslash($_POST['country_code']??'')),'status'=>'active','created_at'=>$now,'updated_at'=>$now]);
        EZEV_Core_DB::log('organization_created','organization',(string)$wpdb->insert_id); wp_safe_redirect(admin_url('admin.php?page=ezev-organizations&ezev_notice=saved')); exit;
    }

    public static function handle_create_site(): void {
        if (!current_user_can('ezev_manage_organizations')) { wp_die('Access denied.'); } check_admin_referer('ezev_create_site'); global $wpdb; $now=current_time('mysql',true);
        $wpdb->insert(EZEV_Core_DB::table('sites'),['organization_id'=>absint($_POST['organization_id']??0),'site_code'=>sanitize_text_field(wp_unslash($_POST['site_code']??'')),'name'=>sanitize_text_field(wp_unslash($_POST['name']??'')),'address'=>sanitize_textarea_field(wp_unslash($_POST['address']??'')),'status'=>'active','created_at'=>$now,'updated_at'=>$now]);
        EZEV_Core_DB::log('site_created','site',(string)$wpdb->insert_id); wp_safe_redirect(admin_url('admin.php?page=ezev-organizations&ezev_notice=saved')); exit;
    }

    public static function handle_assign_member(): void {
        if (!current_user_can('ezev_manage_access')) { wp_die('Access denied.'); } check_admin_referer('ezev_assign_member'); global $wpdb; $now=current_time('mysql',true);
        $wpdb->replace(EZEV_Core_DB::table('org_members'),['organization_id'=>absint($_POST['organization_id']??0),'user_id'=>absint($_POST['user_id']??0),'role_key'=>sanitize_key($_POST['role_key']??'viewer'),'status'=>'active','created_at'=>$now,'updated_at'=>$now]);
        EZEV_Core_DB::log('membership_assigned','membership',(string)$wpdb->insert_id); wp_safe_redirect(admin_url('admin.php?page=ezev-access&ezev_notice=saved')); exit;
    }

    public static function notices(): void {
        if (empty($_GET['ezev_notice'])) { return; }
        $notice = sanitize_key(wp_unslash($_GET['ezev_notice']));
        $messages = ['saved'=>'Saved successfully.','seeded'=>'Demo stations seeded/refreshed.','removed'=>'Demo stations removed.','error'=>'Something could not be saved.'];
        if (!isset($messages[$notice])) { return; }
        echo '<div class="notice notice-' . ($notice==='error'?'error':'success') . ' is-dismissible"><p>' . esc_html($messages[$notice]) . '</p></div>';
    }
}
