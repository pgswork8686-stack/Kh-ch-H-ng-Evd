<?php
if (!defined('ABSPATH')) { exit; }

final class EZEV_Core_Shortcodes {
    public static function init(): void {
        add_shortcode('ezev_find_charger', [self::class, 'find_charger']);
        add_shortcode('ezev_login', [self::class, 'login']);
        add_shortcode('ezev_account', [self::class, 'account']);
        add_shortcode('ezev_business', [self::class, 'business']);
        add_shortcode('ezev_partner', [self::class, 'partner']);
        add_shortcode('ezev_internal', [self::class, 'internal']);
        add_action('wp_enqueue_scripts', [self::class, 'register_assets']);
    }

    public static function register_assets(): void {
        wp_register_style('ezev-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap', [], null);
        wp_register_style('ezev-core-public', EZEV_CORE_URL . 'assets/css/public.css', ['ezev-inter'], EZEV_CORE_VERSION);
        wp_register_script('ezev-core-public', EZEV_CORE_URL . 'assets/js/public-map.js', [], EZEV_CORE_VERSION, true);
    }

    public static function find_charger(): string {
        wp_enqueue_style('ezev-inter');
        wp_enqueue_style('ezev-core-public');
        wp_enqueue_script('ezev-core-public');
        $api_key = EZEV_Core_Admin::decrypt_secret((string)get_option('ezev_map_api_key',''));
        wp_localize_script('ezev-core-public', 'EZEV_PUBLIC', [
            'stationsUrl' => rest_url('ezev/v1/stations'),
            'savedUrl' => rest_url('ezev/v1/saved-stations'),
            'nonce' => wp_create_nonce('wp_rest'),
            'isLoggedIn' => is_user_logged_in(),
            'googleMapsKey' => $api_key,
            'googleMapId' => sanitize_text_field((string)get_option('ezev_google_map_id','DEMO_MAP_ID')),
            'defaultCenter' => get_option('ezev_map_default_center', '107.0,15.5'),
            'defaultZoom' => (float) get_option('ezev_map_default_zoom', 4.2),
            'countryBias' => sanitize_text_field((string)get_option('ezev_google_country_bias','VN,PH,CN')),
            'loginUrl' => home_url('/login/'),
        ]);
        ob_start(); ?>
        <div class="ezev-find-charger" data-ezev-find-charger>
            <header class="ezev-fc-head">
                <div><span class="ezev-eyebrow">EZEV CHARGING NETWORK</span><h2>Find a Charger</h2><p>Search real Google Maps locations, use GPS and compare EZEV stations near you.</p></div>
                <div class="ezev-map-provider-badge"><span class="ezev-google-g">G</span><div><strong>Google Maps</strong><small>Live map provider</small></div></div>
            </header>
            <div class="ezev-search-row">
                <div class="ezev-searchbox"><span class="ezev-search-icon">⌕</span><input type="search" autocomplete="off" placeholder="Search city, address or station..." data-ezev-search></div>
                <button type="button" class="ezev-btn ezev-btn-primary" data-ezev-search-btn>Search</button>
                <button type="button" class="ezev-btn ezev-btn-ghost" data-ezev-location>◎ Use my location</button>
            </div>
            <div class="ezev-fc-filters">
                <select data-ezev-country><option value="">All countries</option><option value="VN">Vietnam</option><option value="PH">Philippines</option><option value="CN">China</option></select>
                <select data-ezev-power><option value="0">Any power</option><option value="120">120 kW+</option><option value="180">180 kW+</option><option value="240">240 kW+</option><option value="360">360 kW+</option><option value="480">480 kW+</option></select>
                <label class="ezev-filter-check"><input type="checkbox" data-ezev-open checked> Active stations</label>
                <span class="ezev-fc-count" data-ezev-count>Loading stations...</span>
            </div>
            <div class="ezev-fc-grid">
                <aside class="ezev-results-panel"><div class="ezev-results-title"><div><strong>Stations</strong><small>Sorted by distance when GPS is active</small></div><span data-ezev-result-mode>Network</span></div><div class="ezev-station-list" data-ezev-list></div></aside>
                <div class="ezev-map-shell"><div id="ezev-public-map" class="ezev-public-map"><div class="ezev-map-loading">Connecting to Google Maps...</div></div><button type="button" class="ezev-search-area" data-ezev-search-area hidden>Search this area</button></div>
            </div>
            <div class="ezev-demo-note"><strong>Demo dataset:</strong> the included 60 station records are development data only. Google Maps, GPS, geocoding, distance calculation, database reads and directions are real once the API key is configured.</div>
        </div>
        <?php return (string) ob_get_clean();
    }

    public static function login(): string {
        if (is_user_logged_in()) {
            return '<div class="ezev-auth-card"><h3>You are already signed in.</h3><p><a href="' . esc_url(home_url('/account/')) . '">Open account</a> · <a href="' . esc_url(wp_logout_url(home_url('/'))) . '">Log out</a></p></div>';
        }
        ob_start();
        echo '<div class="ezev-auth-card"><span class="ezev-eyebrow">EZEV ACCOUNT</span><h2>Sign in</h2><p>One secure identity for customer, business, partner/investor and internal access.</p>';
        wp_login_form(['redirect' => home_url('/account/'), 'remember' => true]);
        echo '<p class="ezev-small"><a href="' . esc_url(wp_lostpassword_url()) . '">Forgot password?</a></p></div>';
        return (string) ob_get_clean();
    }

    public static function account(): string {
        if (!is_user_logged_in()) { return '<div class="ezev-auth-card"><h3>Sign in required</h3><p><a href="' . esc_url(home_url('/login/')) . '">Go to login</a></p></div>'; }
        $u = wp_get_current_user();
        $memberships = EZEV_Core_Auth::user_access($u->ID);
        $allowed = EZEV_Core_Auth::allowed_station_ids($u->ID);
        ob_start(); ?>
        <div class="ezev-account-card">
            <span class="ezev-eyebrow">CUSTOMER ACCOUNT</span>
            <h2><?php echo esc_html($u->display_name); ?></h2>
            <p><?php echo esc_html($u->user_email); ?></p>
            <div class="ezev-account-stats"><div><strong><?php echo count($memberships); ?></strong><span>Organizations</span></div><div><strong><?php echo count($allowed); ?></strong><span>Allowed stations</span></div></div>
            <h3>Memberships</h3>
            <?php if (!$memberships): ?><p>Standard Customer Account</p><?php else: ?><ul><?php foreach ($memberships as $m): ?><li><strong><?php echo esc_html($m['organization_name']); ?></strong> - <?php echo esc_html(EZEV_Core_Roles::role_label($m['role_key'])); ?></li><?php endforeach; ?></ul><?php endif; ?>
            <p><a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Log out</a></p>
        </div>
        <?php return (string) ob_get_clean();
    }

    public static function business(): string {
        if (!is_user_logged_in()) { return '<div class="ezev-auth-card"><h3>Business Login Required</h3><p><a href="' . esc_url(home_url('/login/')) . '">Go to login</a></p></div>'; }
        $u = wp_get_current_user();
        $memberships = EZEV_Core_Auth::user_access($u->ID);
        $business_memberships = array_filter($memberships, fn($m) => ($m['organization_type'] ?? '') === 'business');
        $allowed = EZEV_Core_Auth::allowed_station_ids($u->ID);
        ob_start(); ?>
        <div class="ezev-account-card ezev-portal-card">
            <span class="ezev-eyebrow">BUSINESS PORTAL</span>
            <h2><?php echo esc_html($u->display_name); ?></h2>
            <p><?php echo esc_html($u->user_email); ?></p>
            <div class="ezev-account-stats"><div><strong><?php echo count($business_memberships); ?></strong><span>Business Workspaces</span></div><div><strong><?php echo count($allowed); ?></strong><span>Accessible Stations</span></div></div>
            <h3>Assigned Workspaces</h3>
            <?php if (!$business_memberships): ?><p>No business workspace assigned. Please contact your organization administrator.</p><?php else: ?><ul><?php foreach ($business_memberships as $m): ?><li><strong><?php echo esc_html($m['organization_name']); ?></strong> (<?php echo esc_html($m['org_code']); ?>) - Role: <?php echo esc_html(EZEV_Core_Roles::role_label($m['role_key'])); ?></li><?php endforeach; ?></ul><?php endif; ?>
            <p><a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Log out</a></p>
        </div>
        <?php return (string) ob_get_clean();
    }

    public static function partner(): string {
        if (!is_user_logged_in()) { return '<div class="ezev-auth-card"><h3>Partner Login Required</h3><p><a href="' . esc_url(home_url('/login/')) . '">Go to login</a></p></div>'; }
        $u = wp_get_current_user();
        $memberships = EZEV_Core_Auth::user_access($u->ID);
        $partner_memberships = array_filter($memberships, fn($m) => in_array($m['organization_type'] ?? '', ['partner', 'investor'], true));
        $allowed = EZEV_Core_Auth::allowed_station_ids($u->ID);
        ob_start(); ?>
        <div class="ezev-account-card ezev-portal-card">
            <span class="ezev-eyebrow">PARTNER / INVESTOR PORTAL</span>
            <h2><?php echo esc_html($u->display_name); ?></h2>
            <p><?php echo esc_html($u->user_email); ?></p>
            <div class="ezev-account-stats"><div><strong><?php echo count($partner_memberships); ?></strong><span>Partner Accounts</span></div><div><strong><?php echo count($allowed); ?></strong><span>Assigned Stations</span></div></div>
            <h3>Assigned Partner Portfolios</h3>
            <?php if (!$partner_memberships): ?><p>No partner or investor organization portfolio assigned.</p><?php else: ?><ul><?php foreach ($partner_memberships as $m): ?><li><strong><?php echo esc_html($m['organization_name']); ?></strong> - Role: <?php echo esc_html(EZEV_Core_Roles::role_label($m['role_key'])); ?></li><?php endforeach; ?></ul><?php endif; ?>
            <p><a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Log out</a></p>
        </div>
        <?php return (string) ob_get_clean();
    }

    public static function internal(): string {
        if (!is_user_logged_in()) { return '<div class="ezev-auth-card"><h3>Internal Access Required</h3><p><a href="' . esc_url(home_url('/login/')) . '">Go to login</a></p></div>'; }
        if (!current_user_can('ezev_view_internal') && !current_user_can('manage_options')) {
            return '<div class="ezev-auth-card"><h3>Access Denied</h3><p>Your account does not have internal operations access privileges.</p></div>';
        }
        $u = wp_get_current_user();
        $stations = EZEV_Core_Stations::list();
        ob_start(); ?>
        <div class="ezev-account-card ezev-portal-card">
            <span class="ezev-eyebrow">EZEV INTERNAL OPERATIONS PORTAL</span>
            <h2><?php echo esc_html($u->display_name); ?></h2>
            <p><?php echo esc_html($u->user_email); ?> · Technical Staff</p>
            <div class="ezev-account-stats"><div><strong><?php echo count($stations); ?></strong><span>Total Stations</span></div><div><strong>Realtime</strong><span>Operations Hub</span></div></div>
            <h3>Operations Hub Navigation</h3>
            <p>Access the centralized operations metrics, alerts, chargers, and sync logs directly through the internal platform console.</p>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=ezev-operations')); ?>">Open Operations Dashboard &rarr;</a> · <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Log out</a></p>
        </div>
        <?php return (string) ob_get_clean();
    }
}
