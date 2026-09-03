<?php
/**
 * Front Page Template (Home)
 * Route: /
 * Compliance: Checkpoint 4.1D & UI Reference "Trang Chủ.png"
 */

if (!defined('ABSPATH')) { exit; }

get_header();

$theme_uri = get_template_directory_uri();
wp_enqueue_script('ezev-home-controller', $theme_uri . '/assets/js/home.js', ['ezev-data-client'], '1.0.0', true);

// Fetch initial stations via Core service for initial server render
$featured_stations = class_exists('EZEV_Core_Stations') ? array_slice(EZEV_Core_Stations::domain_list(), 0, 6) : [];
$total_stations_count = class_exists('EZEV_Core_Stations') ? count(EZEV_Core_Stations::domain_list()) : 60;
?>

<!-- 1. Hero Section -->
<section class="ezev-hero-section">
  <div class="ezev-container">
    <div class="ezev-hero-grid">
      <!-- Left Hero Content -->
      <div>
        <h1 class="ezev-hero-title">
          Powering the Future of <span class="ezev-highlight">EV</span>
        </h1>
        <p class="ezev-hero-subtitle">
          <strong>Fast. Reliable. Always on.</strong><br />
          Smart charging solutions with real-time availability and nationwide coverage.
        </p>
        <div class="ezev-hero-ctas">
          <a href="<?php echo esc_url(home_url('/find-a-charger')); ?>" class="ezev-btn ezev-btn-primary ezev-btn-lg">
            ⚡ Find a Charger
          </a>
          <a href="<?php echo esc_url(home_url('/how-to-charge')); ?>" class="ezev-btn ezev-btn-secondary ezev-btn-lg">
            ▶ How to Charge
          </a>
        </div>
      </div>

      <!-- Right Hero Media -->
      <div class="ezev-hero-media">
        <img src="<?php echo esc_url($theme_uri . '/assets/images/hero-home.jpg'); ?>" alt="EZEV Ultra Fast Charging Station Hub" class="ezev-hero-img" />
      </div>
    </div>

    <!-- Stats Bar (Derived dynamically) -->
    <div class="ezev-stats-bar">
      <div class="ezev-stat-item">
        <div class="ezev-stat-icon">⚡</div>
        <div>
          <div class="ezev-stat-num" id="ezevStatTotalPorts">120+</div>
          <div class="ezev-stat-lbl">Charging Points</div>
        </div>
      </div>
      <div class="ezev-stat-item">
        <div class="ezev-stat-icon">🏢</div>
        <div>
          <div class="ezev-stat-num" id="ezevStatTotalStations"><?php echo esc_html($total_stations_count); ?>+</div>
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
          <div class="ezev-stat-num" id="ezevStatCountries">3</div>
          <div class="ezev-stat-lbl">Countries Served</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 2. Find a Charger Near You (Search, Mini Map & Preview) -->
<section class="ezev-home-find-section">
  <div class="ezev-container">
    <div class="ezev-home-find-box">
      <div class="ezev-home-find-grid">
        <!-- Quick Search Form -->
        <div class="ezev-home-find-form">
          <h2 style="font-size: 1.375rem; font-weight: 700; color: #0F172A;">Find a charger near you</h2>
          <div class="ezev-input-group">
            <span class="ezev-search-icon">🔍</span>
            <input type="text" class="ezev-search-input" placeholder="Enter location or address" />
          </div>
          <select class="ezev-select" style="width: 100%;">
            <option>All Connectors (CCS2, Type 2)</option>
          </select>
          <select class="ezev-select" style="width: 100%;">
            <option>All Speeds (Fast, Super, Ultra)</option>
          </select>
          <a href="<?php echo esc_url(home_url('/find-a-charger')); ?>" class="ezev-btn ezev-btn-primary" style="width: 100%; justify-content: center; height: 44px;">
            Search on Interactive Map
          </a>
        </div>

        <!-- Mini Google Map Container -->
        <div class="ezev-home-mini-map-container">
          <div id="ezevHomeMiniMap" style="width: 100%; height: 100%; min-height: 380px;"></div>
        </div>

        <!-- Station Preview Card -->
        <div class="ezev-home-preview-card">
          <div style="border-radius: 12px; overflow: hidden; height: 150px; margin-bottom: 12px; background: #0F172A;">
            <img src="<?php echo esc_url($theme_uri . '/assets/images/station-hero.jpg'); ?>" id="ezevPreviewImg" alt="Station preview" style="width: 100%; height: 100%; object-fit: cover;" />
          </div>
          <h3 id="ezevPreviewTitle" style="font-size: 1.125rem; font-weight: 700; margin-bottom: 4px;">EZEV Charging Hub</h3>
          <p id="ezevPreviewAddress" style="font-size: 0.8125rem; color: #64748B; margin-bottom: 8px;">Select a marker on the map</p>
          <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; font-weight: 600; margin-bottom: 12px;">
            <span id="ezevPreviewPorts" style="color: #10B981;">Available</span>
            <span id="ezevPreviewPower" style="color: #0F172A;">Ultra-Fast</span>
          </div>
          <a href="<?php echo esc_url(home_url('/find-a-charger')); ?>" id="ezevPreviewLink" class="ezev-btn ezev-btn-primary ezev-btn-sm" style="width: 100%; justify-content: center;">
            View Station Details &rarr;
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 3. Why Choose EZEV -->
<section class="ezev-props-section">
  <div class="ezev-container">
    <div class="ezev-section-eyebrow">WHY CHOOSE EZEV</div>
    <h2 style="font-size: 2.25rem;">Charging made simple and reliable</h2>
    <div class="ezev-props-grid">
      <div class="ezev-prop-card">
        <div class="ezev-prop-icon">⚡</div>
        <h3 style="font-size: 1.125rem; margin-bottom: 6px;">Ultra Fast Charging</h3>
        <p style="font-size: 0.875rem;">High-power DC fast chargers up to 480kW to get you back on the road in no time.</p>
      </div>
      <div class="ezev-prop-card">
        <div class="ezev-prop-icon">🛡️</div>
        <h3 style="font-size: 1.125rem; margin-bottom: 6px;">Reliable Network</h3>
        <p style="font-size: 0.875rem;">Built with the latest smart technology and 24/7 technical monitoring for maximum uptime.</p>
      </div>
      <div class="ezev-prop-card">
        <div class="ezev-prop-icon">🌱</div>
        <h3 style="font-size: 1.125rem; margin-bottom: 6px;">Sustainable Future</h3>
        <p style="font-size: 0.875rem;">Powering a cleaner tomorrow with renewable energy and smart grid infrastructure.</p>
      </div>
      <div class="ezev-prop-card">
        <div class="ezev-prop-icon">📱</div>
        <h3 style="font-size: 1.125rem; margin-bottom: 6px;">Easy to Use</h3>
        <p style="font-size: 0.875rem;">Simple process, seamless mobile experience, and dedicated 24/7 driver support.</p>
      </div>
    </div>
  </div>
</section>

<!-- 4. Mascot Zappy Section -->
<section class="ezev-zappy-section">
  <div class="ezev-container">
    <div class="ezev-zappy-card">
      <div>
        <h2 style="font-size: 2.25rem; margin-bottom: 12px; color: #090D1A;">
          Hello, Drivers.<br /><span class="ezev-highlight" style="color: #5A9420;">I am Zappy.</span>
        </h2>
        <p style="font-size: 1.0625rem; color: #334155; margin-bottom: 24px; max-width: 440px;">
          I will be waiting at every EZEV station — fast, friendly, always ready to assist your journey.
        </p>
        <a href="<?php echo esc_url(home_url('/find-a-charger')); ?>" class="ezev-btn ezev-btn-primary ezev-btn-lg">
          Meet Zappy ⚡
        </a>
      </div>
      <div>
        <img src="<?php echo esc_url($theme_uri . '/assets/images/zappy-hello-philippines.jpg'); ?>" alt="EZEV Zappy Mascot" class="ezev-zappy-img" />
      </div>
    </div>
  </div>
</section>

<!-- 5. Solutions for Every Need -->
<section class="ezev-solutions-section">
  <div class="ezev-container">
    <div style="text-align: center; max-width: 600px; margin: 0 auto;">
      <div class="ezev-section-eyebrow">SOLUTIONS</div>
      <h2 style="font-size: 2rem;">Tailored for every charging need</h2>
    </div>

    <div class="ezev-solutions-grid">
      <div class="ezev-solution-card">
        <div style="font-size: 2.5rem; margin-bottom: 12px;">🚗</div>
        <h3 style="font-size: 1.25rem; margin-bottom: 8px;">For Drivers</h3>
        <p style="font-size: 0.875rem; margin-bottom: 16px; flex-grow: 1;">
          Find, charge, and go with ease. Reliable high-speed charging wherever your journey takes you.
        </p>
        <a href="<?php echo esc_url(home_url('/drivers')); ?>" style="color: var(--ezev-color-primary); font-weight: 600; font-size: 0.875rem;">
          Learn more &rarr;
        </a>
      </div>

      <div class="ezev-solution-card">
        <div style="font-size: 2.5rem; margin-bottom: 12px;">🏢</div>
        <h3 style="font-size: 1.25rem; margin-bottom: 8px;">For Business</h3>
        <p style="font-size: 0.875rem; margin-bottom: 16px; flex-grow: 1;">
          Attract customers and power your operations with commercial turnkey EV charging solutions.
        </p>
        <a href="<?php echo esc_url(home_url('/business')); ?>" style="color: var(--ezev-color-primary); font-weight: 600; font-size: 0.875rem;">
          Learn more &rarr;
        </a>
      </div>

      <div class="ezev-solution-card">
        <div style="font-size: 2.5rem; margin-bottom: 12px;">⚡</div>
        <h3 style="font-size: 1.25rem; margin-bottom: 8px;">For Energy Solutions</h3>
        <p style="font-size: 0.875rem; margin-bottom: 16px; flex-grow: 1;">
          Smart charging management, load balancing, and battery storage integration for optimal efficiency.
        </p>
        <a href="<?php echo esc_url(home_url('/solutions')); ?>" style="color: var(--ezev-color-primary); font-weight: 600; font-size: 0.875rem;">
          Learn more &rarr;
        </a>
      </div>
    </div>
  </div>
</section>

<!-- 6. FAQs Section -->
<section class="ezev-faqs-section">
  <div class="ezev-container" style="max-width: 800px;">
    <div style="text-align: center; margin-bottom: var(--ezev-space-8);">
      <div class="ezev-section-eyebrow">FAQS</div>
      <h2 style="font-size: 2rem;">Frequently Asked Questions</h2>
    </div>

    <div>
      <div class="ezev-faq-item active">
        <button type="button" class="ezev-faq-question">
          <span>How do I start charging my EV?</span>
          <span>▾</span>
        </button>
        <div class="ezev-faq-answer">
          Plug the connector into your vehicle, scan the QR code using the EZEV Mobile App or tap your RFID card, and charging will begin automatically.
        </div>
      </div>

      <div class="ezev-faq-item">
        <button type="button" class="ezev-faq-question">
          <span>Are EZEV stations open 24/7?</span>
          <span>▾</span>
        </button>
        <div class="ezev-faq-answer">
          Yes, the majority of EZEV highway hubs and public stations operate 24 hours a day, 7 days a week with security surveillance.
        </div>
      </div>

      <div class="ezev-faq-item">
        <button type="button" class="ezev-faq-question">
          <span>What connector types are supported?</span>
          <span>▾</span>
        </button>
        <div class="ezev-faq-answer">
          Our ultra-fast charging stations support European standard CCS2 (Combo 2), Type 2 AC, as well as CHAdeMO and GB/T at selected locations.
        </div>
      </div>

      <div class="ezev-faq-item">
        <button type="button" class="ezev-faq-question">
          <span>Do I need a membership to charge?</span>
          <span>▾</span>
        </button>
        <div class="ezev-faq-answer">
          No membership is required for guest charging via our mobile app, though registered members enjoy preferential rates and automated billing.
        </div>
      </div>
    </div>
  </div>
</section>

<?php
get_footer();