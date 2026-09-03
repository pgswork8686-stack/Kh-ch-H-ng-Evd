<?php
/**
 * Template Name: Find a Charger
 * Route: /find-a-charger
 * Compliance: Checkpoint 4.1B & UI Reference "Tìm trạm sạc.png"
 */

if (!defined('ABSPATH')) { exit; }

get_header();

$theme_uri = get_template_directory_uri();
wp_enqueue_script('ezev-maps-manager', $theme_uri . '/assets/js/maps-manager.js', ['ezev-data-client'], '1.0.0', true);
wp_enqueue_script('ezev-find-charger-controller', $theme_uri . '/assets/js/find-charger.js', ['ezev-maps-manager'], '1.0.0', true);
?>

<div class="ezev-find-layout">
  <!-- Sidebar Search & Filter Panel (~35% desktop) -->
  <aside class="ezev-find-sidebar" id="ezevFindSidebar">
    <div class="ezev-search-box">
      <h1 class="ezev-search-title">Find a charger near you</h1>
      <p class="ezev-search-subtitle">Search, filter and find the nearest EZEV charging stations.</p>

      <!-- Search Input with Location Autocomplete & Geolocation -->
      <div class="ezev-input-group">
        <span class="ezev-search-icon" aria-hidden="true">🔍</span>
        <input type="text" id="ezevLocationSearch" class="ezev-search-input" placeholder="Enter location, city or station name" />
        <button type="button" id="ezevLocateBtn" class="ezev-locate-btn" title="Use My Location" aria-label="Use My Location">
          📍
        </button>
      </div>

      <!-- Filters Row 1: Dropdown Selects -->
      <div class="ezev-filter-row">
        <select id="ezevCountryFilter" class="ezev-select" aria-label="Filter by Country">
          <option value="">All Countries</option>
        </select>

        <select id="ezevCityFilter" class="ezev-select" aria-label="Filter by City">
          <option value="">All Cities</option>
        </select>

        <select id="ezevConnectorFilter" class="ezev-select" aria-label="Filter by Connector">
          <option value="">All Connectors</option>
        </select>

        <select id="ezevPowerFilter" class="ezev-select" aria-label="Filter by Power">
          <option value="">Any Power</option>
          <option value="22">22 kW+</option>
          <option value="60">60 kW+ (Fast)</option>
          <option value="120">120 kW+ (Super)</option>
          <option value="180">180 kW+ (Ultra)</option>
        </select>

        <select id="ezevStatusFilter" class="ezev-select" aria-label="Filter by Status">
          <option value="">All Statuses</option>
          <option value="active">Available</option>
          <option value="maintenance">Maintenance</option>
        </select>
      </div>
    </div>

    <!-- Results Metadata Bar -->
    <div style="padding: 12px 20px 0; display: flex; justify-content: space-between; align-items: center;">
      <span id="ezevResultCount" style="font-size: 0.8125rem; font-weight: 600; color: #475569;">Loading stations...</span>
      <button type="button" id="ezevClearFiltersBtn" class="ezev-clear-btn">Reset Filters</button>
    </div>

    <!-- Stations Results List -->
    <div class="ezev-station-list-container" id="ezevStationList">
      <!-- Skeleton Loading Cards -->
      <div class="ezev-skeleton" style="height: 180px; margin-bottom: 12px;"></div>
      <div class="ezev-skeleton" style="height: 180px; margin-bottom: 12px;"></div>
      <div class="ezev-skeleton" style="height: 180px;"></div>
    </div>
  </aside>

  <!-- Interactive Map Canvas (~65% desktop) -->
  <section class="ezev-find-map-area" id="ezevFindMapArea">
    <button type="button" id="ezevSearchAreaBtn" class="ezev-search-area-btn">
      🔄 Search this area
    </button>
    <?php get_template_part('template-parts/maps/container', null, ['map_id' => 'ezevMap', 'class' => 'ezev-fullscreen-map']); ?>
  </section>

  <!-- Mobile Map / List Toggle Floating Switch -->
  <div class="ezev-mobile-switch">
    <button type="button" id="ezevSwitchList" class="ezev-switch-btn active">List</button>
    <button type="button" id="ezevSwitchMap" class="ezev-switch-btn">Map</button>
  </div>
</div>

<?php
get_footer();