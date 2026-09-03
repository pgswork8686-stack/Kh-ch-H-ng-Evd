<?php
/**
 * Template Name: Charging Network
 * Route: /charging-network
 * Compliance: Checkpoint 4.1E & UI Reference "Mạng lưới.png"
 */

if (!defined('ABSPATH')) { exit; }

get_header();

$theme_uri = get_template_directory_uri();
wp_enqueue_script('ezev-network-controller', $theme_uri . '/assets/js/charging-network.js', ['ezev-data-client'], '1.0.0', true);

$featured_stations = class_exists('EZEV_Core_Stations') ? array_slice(EZEV_Core_Stations::domain_list(), 0, 4) : [];
$total_stations = class_exists('EZEV_Core_Stations') ? count(EZEV_Core_Stations::domain_list()) : 60;
?>

<!-- 1. Hero Section -->
<section class="ezev-network-hero">
  <div class="ezev-container">
    <nav class="ezev-breadcrumbs" aria-label="Breadcrumb" style="color: #94A3B8; margin-bottom: 16px;">
      <a href="<?php echo esc_url(home_url('/')); ?>" style="color: #CBD5E1;">Home</a>
      <span aria-hidden="true">&gt;</span>
      <span aria-current="page" style="color: #FFFFFF;">Charging Network</span>
    </nav>

    <h1 class="ezev-network-title">
      Charging <span class="ezev-highlight">Network</span>
    </h1>
    <p class="ezev-network-subtitle">
      A nationwide and regional network of fast, reliable EV charging stations. Always on, always there.
    </p>

    <!-- Header Stats Bar -->
    <div class="ezev-stats-bar">
      <div class="ezev-stat-item">
        <div class="ezev-stat-icon">⚡</div>
        <div>
          <div class="ezev-stat-num" id="ezevNetTotalPorts">120+</div>
          <div class="ezev-stat-lbl">Charging Points</div>
        </div>
      </div>
      <div class="ezev-stat-item">
        <div class="ezev-stat-icon">🏢</div>
        <div>
          <div class="ezev-stat-num" id="ezevNetTotalStations"><?php echo esc_html($total_stations); ?>+</div>
          <div class="ezev-stat-lbl">Charging Stations</div>
        </div>
      </div>
      <div class="ezev-stat-item">
        <div class="ezev-stat-icon">🕒</div>
        <div>
          <div class="ezev-stat-num">24/7</div>
          <div class="ezev-stat-lbl">Access &amp; Support</div>
        </div>
      </div>
      <div class="ezev-stat-item">
        <div class="ezev-stat-icon">🌐</div>
        <div>
          <div class="ezev-stat-num" id="ezevNetTotalCountries">3</div>
          <div class="ezev-stat-lbl">Countries Served</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 2. Macro Map View Section -->
<section class="ezev-network-map-section">
  <div class="ezev-container">
    <div class="ezev-network-map-box">
      <div style="padding: 20px; border-bottom: 1px solid var(--ezev-color-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
          <h2 style="font-size: 1.25rem; font-weight: 700; color: #0F172A;">Interactive Network Coverage</h2>
          <p style="font-size: 0.8125rem; color: #64748B;">Explore high-power charging corridors across the region.</p>
        </div>
        <a href="<?php echo esc_url(home_url('/find-a-charger')); ?>" class="ezev-btn ezev-btn-primary ezev-btn-sm">
          Open Search &amp; Filters &rarr;
        </a>
      </div>
      <div style="height: 480px; position: relative;">
        <div id="ezevMacroMap" style="width: 100%; height: 100%;"></div>
      </div>
    </div>
  </div>
</section>

<!-- 3. Our Network at a Glance (5-col KPIs) -->
<section class="ezev-network-kpis-section">
  <div class="ezev-container">
    <div class="ezev-section-eyebrow">OVERVIEW</div>
    <h2 style="font-size: 2.25rem;">Our network at a glance</h2>

    <div class="ezev-network-kpis-grid">
      <div class="ezev-network-kpi-card">
        <div class="ezev-network-kpi-icon">🏢</div>
        <div class="ezev-network-kpi-val"><?php echo esc_html($total_stations); ?>+</div>
        <div class="ezev-network-kpi-lbl">Charging Stations</div>
        <div class="ezev-network-kpi-sub">Across key hubs</div>
      </div>

      <div class="ezev-network-kpi-card">
        <div class="ezev-network-kpi-icon">⚡</div>
        <div class="ezev-network-kpi-val">120+</div>
        <div class="ezev-network-kpi-lbl">Charging Points</div>
        <div class="ezev-network-kpi-sub">DC Fast &amp; Ultra-Fast</div>
      </div>

      <div class="ezev-network-kpi-card">
        <div class="ezev-network-kpi-icon">🌐</div>
        <div class="ezev-network-kpi-val">3</div>
        <div class="ezev-network-kpi-lbl">Countries Served</div>
        <div class="ezev-network-kpi-sub">Vietnam, Philippines, China</div>
      </div>

      <div class="ezev-network-kpi-card">
        <div class="ezev-network-kpi-icon">🛡️</div>
        <div class="ezev-network-kpi-val">99.8%</div>
        <div class="ezev-network-kpi-lbl">Network Uptime</div>
        <div class="ezev-network-kpi-sub">Monitored 24/7</div>
      </div>

      <div class="ezev-network-kpi-card">
        <div class="ezev-network-kpi-icon">🎧</div>
        <div class="ezev-network-kpi-val">24/7</div>
        <div class="ezev-network-kpi-lbl">Customer Support</div>
        <div class="ezev-network-kpi-sub">Always on standby</div>
      </div>
    </div>
  </div>
</section>

<!-- 4. Regional Expansion (Dynamic by Country from StationDTO) -->
<section class="ezev-regional-section">
  <div class="ezev-container">
    <div class="ezev-regional-container">
      <div class="ezev-regional-header">
        <div class="ezev-section-eyebrow" style="color: var(--ezev-color-primary);">REGIONAL EXPANSION</div>
        <h2 style="font-size: 2.25rem; color: #FFFFFF;">
          Growing across Asia. <span class="ezev-highlight">Powering the future together.</span>
        </h2>
        <p style="color: #94A3B8; font-size: 0.9375rem; margin-top: 8px; max-width: 580px;">
          EZEV builds reliable, high-power DC fast charging corridors connecting strategic metropolitan hubs and expressways.
        </p>
      </div>

      <!-- Dynamic Country Breakdown Cards -->
      <div class="ezev-regional-cards-grid" id="ezevRegionalCardsContainer">
        <div class="ezev-skeleton" style="height: 180px; background: rgba(255,255,255,0.05);"></div>
        <div class="ezev-skeleton" style="height: 180px; background: rgba(255,255,255,0.05);"></div>
        <div class="ezev-skeleton" style="height: 180px; background: rgba(255,255,255,0.05);"></div>
      </div>
    </div>
  </div>
</section>

<!-- 5. Featured Charging Stations -->
<section class="ezev-featured-section">
  <div class="ezev-container">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px;">
      <div>
        <div class="ezev-section-eyebrow">HUBS</div>
        <h2 style="font-size: 2rem;">Featured charging stations</h2>
      </div>
      <a href="<?php echo esc_url(home_url('/find-a-charger')); ?>" class="ezev-btn ezev-btn-outline ezev-btn-sm">
        View all stations &rarr;
      </a>
    </div>

    <div class="ezev-featured-grid">
      <?php if (!empty($featured_stations)): ?>
        <?php foreach ($featured_stations as $st): ?>
          <?php get_template_part('template-parts/station/card', null, $st); ?>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="color: #64748B;">No stations currently available.</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- 6. Zappy Assistant CTA Box -->
<section class="ezev-container">
  <div class="ezev-assistant-box">
    <div>
      <h3 style="font-size: 1.75rem; font-weight: 800; margin-bottom: 8px;">Hi, I'm Zappy!</h3>
      <p style="font-size: 1rem; color: #1E293B; max-width: 500px;">
        I can help you find the nearest station, check real-time availability, and plan your long-distance EV trip.
      </p>
    </div>
    <div style="display: flex; gap: var(--ezev-space-3); flex-wrap: wrap;">
      <a href="<?php echo esc_url(home_url('/find-a-charger')); ?>" class="ezev-btn" style="background: #090D1A; color: #FFFFFF;">
        Find stations near me ⚡
      </a>
      <a href="<?php echo esc_url(home_url('/support')); ?>" class="ezev-btn" style="background: rgba(0,0,0,0.1); color: #090D1A;">
        Ask a question
      </a>
    </div>
  </div>
</section>

<?php
get_footer();