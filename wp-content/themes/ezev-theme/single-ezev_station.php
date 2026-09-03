<?php
/**
 * Single Station Detail Template
 * Route: /stations/[slug]
 * Compliance: Checkpoint 4.1C & UI Reference "Chi tiết trạm.png"
 */

if (!defined('ABSPATH')) { exit; }

$station = ezev_resolve_current_station();

// Fail-closed: If station entity cannot be resolved, return 404
if (!$station) {
    global $wp_query;
    $wp_query->set_404();
    status_header(404);
    get_template_part('404');
    exit;
}

get_header();

$theme_uri = get_template_directory_uri();
wp_enqueue_script('ezev-station-detail-controller', $theme_uri . '/assets/js/station-detail.js', ['ezev-data-client'], '1.0.0', true);

$station_id  = $station['station_id'] ?? '';
$name        = $station['name'] ?? 'EZEV Station';
$desc        = $station['description'] ?? '';
$address     = $station['address']['line'] ?? ($station['address'] ?? '');
$city        = $station['address']['city'] ?? '';
$region      = $station['address']['region'] ?? '';
$country     = $station['address']['country'] ?? 'Vietnam';
$full_addr   = $address ? ($city ? "$address, $city, $country" : "$address, $country") : $country;
$lat         = $station['location']['lat'] ?? null;
$lng         = $station['location']['lng'] ?? null;
$connectors  = $station['connectors'] ?? [];
$max_power   = $station['max_power_kw'] ?? 0;
$ports_avail = $station['ports']['available'] ?? 0;
$ports_total = $station['ports']['total'] ?? 0;
$hours       = $station['opening_hours'] ?? '24/7';
$status      = $station['status'] ?? 'active';
$mode        = $station['data']['mode'] ?? 'manual';
$is_demo     = !empty($station['data']['is_demo']);
$amenities   = $station['amenities'] ?? [];
$thumbnail   = $station['thumbnail'] ?? ($theme_uri . '/assets/images/station-hero.jpg');
$updated_at  = $station['updated_at'] ? gmdate('M j, Y H:i', strtotime($station['updated_at'])) . ' UTC' : 'Recently';

// Google Directions URL
$directions_url = ($lat && $lng)
    ? sprintf('https://www.google.com/maps/dir/?api=1&destination=%s,%s', rawurlencode($lat), rawurlencode($lng))
    : '#';
?>

<script>
  window.ezevStationData = <?php echo wp_json_encode($station); ?>;
</script>

<div class="ezev-detail-page">
  <div class="ezev-container">
    <!-- Breadcrumbs -->
    <nav class="ezev-breadcrumbs" aria-label="Breadcrumb">
      <a href="<?php echo esc_url(home_url('/')); ?>">Home</a>
      <span aria-hidden="true">&gt;</span>
      <a href="<?php echo esc_url(home_url('/find-a-charger')); ?>">Find a Charger</a>
      <span aria-hidden="true">&gt;</span>
      <span aria-current="page"><?php echo esc_html($name); ?></span>
    </nav>

    <!-- Top Grid: Hero Gallery + Summary Card -->
    <div class="ezev-detail-top-grid">
      <!-- Media Gallery -->
      <div class="ezev-detail-gallery">
        <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($name); ?>" class="ezev-detail-hero-img" />
        <div class="ezev-detail-gallery-badge">
          <?php get_template_part('template-parts/station/badge-status', null, ['status' => $status, 'mode' => $mode, 'is_demo' => $is_demo]); ?>
        </div>
      </div>

      <!-- Summary Info Card -->
      <div class="ezev-detail-summary-card">
        <div>
          <h1 class="ezev-detail-title"><?php echo esc_html($name); ?></h1>
          <div class="ezev-detail-address" style="margin-top: 8px;">
            <span>📍</span>
            <span><?php echo esc_html($full_addr); ?></span>
          </div>
        </div>

        <div style="display: flex; gap: var(--ezev-space-4); align-items: center; flex-wrap: wrap; font-size: 0.9375rem; color: #475569;">
          <span>⚡ <?php echo esc_html(implode(', ', (array)$connectors) ?: 'CCS2'); ?></span>
          <span>·</span>
          <span>⚡ Up to <?php echo esc_html($max_power); ?> kW</span>
          <span>·</span>
          <span>🕒 <?php echo esc_html($hours); ?></span>
        </div>

        <div class="ezev-detail-kpis">
          <div class="ezev-detail-kpi-item">
            <span class="ezev-detail-kpi-val"><?php echo esc_html($ports_avail); ?> / <?php echo esc_html($ports_total); ?></span>
            <span class="ezev-detail-kpi-lbl">Connectors Available</span>
          </div>
          <div class="ezev-detail-kpi-item">
            <span class="ezev-detail-kpi-val"><?php echo esc_html($max_power); ?> kW</span>
            <span class="ezev-detail-kpi-lbl">Max Power Output</span>
          </div>
        </div>

        <div class="ezev-detail-actions">
          <button type="button" class="ezev-btn ezev-btn-primary ezev-btn-lg" id="ezevStartChargingBtn" style="width: 100%;">
            ⚡ Start Charging
          </button>
          <a href="<?php echo esc_url($directions_url); ?>" target="_blank" rel="noopener noreferrer" class="ezev-btn ezev-btn-outline" style="width: 100%;">
            🧭 Get Directions
          </a>
        </div>
      </div>
    </div>

    <!-- Tab Navigation -->
    <div class="ezev-detail-tabs">
      <button type="button" class="ezev-tab-btn active" data-target="sectionOverview">Overview</button>
      <button type="button" class="ezev-tab-btn" data-target="sectionConnectors">Connectors (<?php echo count((array)$connectors); ?>)</button>
      <button type="button" class="ezev-tab-btn" data-target="sectionAmenities">Amenities</button>
      <button type="button" class="ezev-tab-btn" data-target="sectionNearby">Nearby Stations</button>
    </div>

    <!-- Tab Content Grid -->
    <div class="ezev-detail-section-grid">
      <!-- Left Column: Specs, Connectors, Amenities -->
      <div>
        <!-- Section: Overview -->
        <section class="ezev-panel" id="sectionOverview">
          <h2 class="ezev-panel-title">About this station</h2>
          <p style="margin-bottom: var(--ezev-space-6);">
            <?php echo esc_html($desc ?: 'High-speed DC fast charging hub equipped with smart power management and sustainable energy infrastructure.'); ?>
          </p>

          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--ezev-space-4);">
            <div style="background: #F8FAFC; padding: 16px; border-radius: 12px;">
              <div style="font-size: 1.25rem; margin-bottom: 4px;">⚡</div>
              <strong style="font-size: 0.875rem; color: #0F172A;">Ultra-Fast Charging</strong>
              <p style="font-size: 0.8125rem; margin-top: 2px;">Up to <?php echo esc_html($max_power); ?> kW high power output.</p>
            </div>
            <div style="background: #F8FAFC; padding: 16px; border-radius: 12px;">
              <div style="font-size: 1.25rem; margin-bottom: 4px;">🕒</div>
              <strong style="font-size: 0.875rem; color: #0F172A;">Always Open</strong>
              <p style="font-size: 0.8125rem; margin-top: 2px;"><?php echo esc_html($hours); ?> access for all EV drivers.</p>
            </div>
            <div style="background: #F8FAFC; padding: 16px; border-radius: 12px;">
              <div style="font-size: 1.25rem; margin-bottom: 4px;">🛡️</div>
              <strong style="font-size: 0.875rem; color: #0F172A;">Safe &amp; Monitored</strong>
              <p style="font-size: 0.8125rem; margin-top: 2px;">CCTV surveillance and 24/7 technical operations.</p>
            </div>
          </div>
        </section>

        <!-- Section: Connectors -->
        <section class="ezev-panel" id="sectionConnectors">
          <h2 class="ezev-panel-title">Available Connectors</h2>
          <div class="ezev-connectors-grid">
            <?php if (!empty($connectors)): ?>
              <?php foreach ($connectors as $c): ?>
                <div class="ezev-connector-box">
                  <div class="ezev-connector-icon">🔌</div>
                  <div class="ezev-connector-type"><?php echo esc_html($c); ?></div>
                  <div class="ezev-connector-power">DC · Up to <?php echo esc_html($max_power); ?> kW</div>
                  <span class="ezev-badge ezev-badge-available"><span class="ezev-badge-dot"></span>Available</span>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="ezev-connector-box">
                <div class="ezev-connector-icon">🔌</div>
                <div class="ezev-connector-type">CCS2 Fast</div>
                <div class="ezev-connector-power">DC · Up to <?php echo esc_html($max_power); ?> kW</div>
                <span class="ezev-badge ezev-badge-available"><span class="ezev-badge-dot"></span>Available</span>
              </div>
            <?php endif; ?>
          </div>
        </section>

        <!-- Section: Amenities -->
        <section class="ezev-panel" id="sectionAmenities">
          <h2 class="ezev-panel-title">Station Amenities</h2>
          <div class="ezev-amenities-list">
            <?php if (!empty($amenities)): ?>
              <?php foreach ($amenities as $a): ?>
                <div class="ezev-amenity-item">
                  <span>✓</span>
                  <span><?php echo esc_html(ucwords(str_replace('_', ' ', $a))); ?></span>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="ezev-amenity-item"><span>✓</span><span>Convenience Store</span></div>
              <div class="ezev-amenity-item"><span>✓</span><span>Restrooms</span></div>
              <div class="ezev-amenity-item"><span>✓</span><span>Coffee &amp; Dining</span></div>
              <div class="ezev-amenity-item"><span>✓</span><span>Free Wi-Fi</span></div>
              <div class="ezev-amenity-item"><span>✓</span><span>24/7 Security</span></div>
              <div class="ezev-amenity-item"><span>✓</span><span>Well-lit Area</span></div>
            <?php endif; ?>
          </div>
        </section>
      </div>

      <!-- Right Column: Location Map & Metadata Table -->
      <div>
        <!-- Location Map Card -->
        <div class="ezev-panel">
          <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 12px;">Station Location</h3>
          <div style="height: 240px; border-radius: 12px; overflow: hidden; margin-bottom: 16px; background: #E2E8F0;" id="ezevStationMiniMap"></div>
          <a href="<?php echo esc_url($directions_url); ?>" target="_blank" rel="noopener noreferrer" class="ezev-btn ezev-btn-primary" style="width: 100%; justify-content: center;">
            🧭 View on Google Maps
          </a>
        </div>

        <!-- Station Metadata Information -->
        <div class="ezev-panel">
          <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 12px;">Station Information</h3>
          <table class="ezev-info-table">
            <tr>
              <th>Station ID</th>
              <td><code><?php echo esc_html($station_id); ?></code></td>
            </tr>
            <tr>
              <th>Operator</th>
              <td>EZEV Fast Charging Network</td>
            </tr>
            <tr>
              <th>Coordinates</th>
              <td><?php echo esc_html(sprintf('%.4f, %.4f', (float)$lat, (float)$lng)); ?></td>
            </tr>
            <tr>
              <th>Data Mode</th>
              <td><?php echo ezev_render_data_badge($mode, $is_demo); ?></td>
            </tr>
            <tr>
              <th>Last Updated</th>
              <td><?php echo esc_html($updated_at); ?></td>
            </tr>
          </table>
        </div>
      </div>
    </div>

    <!-- App Promo Banner -->
    <div class="ezev-app-promo-box">
      <div>
        <h3 style="color: #FFFFFF; font-size: 1.5rem; margin-bottom: 8px;">Charge smarter with the EZEV App</h3>
        <p style="color: #94A3B8; font-size: 0.9375rem; max-width: 480px;">
          Find stations, track live availability, start charging sessions and view payment history on the go.
        </p>
      </div>
      <div style="display: flex; gap: var(--ezev-space-3);">
        <a href="#" class="ezev-btn ezev-btn-primary"> App Store</a>
        <a href="#" class="ezev-btn ezev-btn-secondary">▶ Google Play</a>
      </div>
    </div>

    <!-- Section: Nearby Stations -->
    <section id="sectionNearby" style="margin-top: var(--ezev-space-12);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--ezev-space-4);">
        <h2 style="font-size: 1.5rem; font-weight: 700;">Nearby Charging Stations</h2>
        <a href="<?php echo esc_url(home_url('/find-a-charger')); ?>" style="color: var(--ezev-color-primary); font-weight: 600; font-size: 0.875rem;">
          View all on map &rarr;
        </a>
      </div>
      <div class="ezev-nearby-grid" id="ezevNearbyStationsContainer">
        <div class="ezev-skeleton" style="height: 220px;"></div>
        <div class="ezev-skeleton" style="height: 220px;"></div>
        <div class="ezev-skeleton" style="height: 220px;"></div>
        <div class="ezev-skeleton" style="height: 220px;"></div>
      </div>
    </section>
  </div>
</div>

<?php
get_footer();