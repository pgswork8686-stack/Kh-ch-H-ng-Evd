<?php
/**
 * Header Navigation Template Part
 * Compliance: docs/NAVIGATION-ARCHITECTURE.md
 */

if (!defined('ABSPATH')) { exit; }

$home_url   = home_url('/');
$logo_url   = get_template_directory_uri() . '/assets/images/logo.svg';
$find_url   = home_url('/find-a-charger');
$is_logged_in = is_user_logged_in();
?>
<header class="ezev-header">
  <div class="ezev-container">
    <!-- Brand Logo -->
    <a href="<?php echo esc_url($home_url); ?>" class="ezev-logo-link" aria-label="EZEV Fast Charging Homepage">
      <img src="<?php echo esc_url($logo_url); ?>" alt="EZEV Fast Charging" class="ezev-logo-img" />
    </a>

    <!-- Desktop Navigation -->
    <nav class="ezev-desktop-nav" aria-label="Main Navigation">
      <ul class="ezev-nav-list">
        <li class="ezev-nav-item <?php echo is_page('find-a-charger') ? 'active' : ''; ?>">
          <a href="<?php echo esc_url($find_url); ?>" class="ezev-nav-link">Find a Charger</a>
        </li>

        <!-- Charging Dropdown -->
        <li class="ezev-nav-item <?php echo (is_page('charging-network') || is_singular('ezev_station')) ? 'active' : ''; ?>">
          <a href="<?php echo esc_url(home_url('/charging-network')); ?>" class="ezev-nav-link">Charging <span aria-hidden="true">▾</span></a>
          <div class="ezev-nav-dropdown">
            <a href="<?php echo esc_url(home_url('/charging-network')); ?>" class="ezev-dropdown-link">Charging Network</a>
            <a href="<?php echo esc_url(home_url('/how-to-charge')); ?>" class="ezev-dropdown-link">How to Charge</a>
            <a href="<?php echo esc_url(home_url('/charging-rates')); ?>" class="ezev-dropdown-link">Charging Rates</a>
            <a href="<?php echo esc_url(home_url('/drivers')); ?>" class="ezev-dropdown-link">For Drivers</a>
          </div>
        </li>

        <!-- Business Dropdown -->
        <li class="ezev-nav-item">
          <a href="<?php echo esc_url(home_url('/business')); ?>" class="ezev-nav-link">Business <span aria-hidden="true">▾</span></a>
          <div class="ezev-nav-dropdown">
            <a href="<?php echo esc_url(home_url('/business')); ?>" class="ezev-dropdown-link">For Business</a>
            <a href="<?php echo esc_url(home_url('/partners')); ?>" class="ezev-dropdown-link">Partners</a>
            <a href="<?php echo esc_url(home_url('/partners/register')); ?>" class="ezev-dropdown-link">Become a Partner</a>
          </div>
        </li>

        <!-- Discover Dropdown -->
        <li class="ezev-nav-item">
          <a href="<?php echo esc_url(home_url('/solutions')); ?>" class="ezev-nav-link">Discover <span aria-hidden="true">▾</span></a>
          <div class="ezev-nav-dropdown">
            <a href="<?php echo esc_url(home_url('/solutions')); ?>" class="ezev-dropdown-link">Solutions</a>
            <a href="<?php echo esc_url(home_url('/projects')); ?>" class="ezev-dropdown-link">Projects</a>
            <a href="<?php echo esc_url(home_url('/news')); ?>" class="ezev-dropdown-link">News &amp; Insights</a>
            <a href="<?php echo esc_url(home_url('/about')); ?>" class="ezev-dropdown-link">About EVD</a>
          </div>
        </li>

        <li class="ezev-nav-item">
          <a href="<?php echo esc_url(home_url('/support')); ?>" class="ezev-nav-link">Support</a>
        </li>
      </ul>
    </nav>

    <!-- Header Actions -->
    <div class="ezev-header-actions">
      <!-- Find Charger CTA Button -->
      <a href="<?php echo esc_url($find_url); ?>" class="ezev-btn ezev-btn-primary ezev-btn-sm" style="display: none;">
        <span>⚡ Find a Charger</span>
      </a>

      <!-- Account Menu (3 Context Options) -->
      <div class="ezev-account-menu">
        <?php if ($is_logged_in): ?>
          <?php
            $current_user = wp_get_current_user();
            $destination = class_exists('EZEV_Core_Auth') ? EZEV_Core_Auth::destination_for_user($current_user) : home_url('/account/');
          ?>
          <button type="button" class="ezev-account-btn" aria-haspopup="true" aria-expanded="false">
            <span>👤 <?php echo esc_html($current_user->display_name ?: 'Account'); ?></span>
            <span aria-hidden="true">▾</span>
          </button>
          <div class="ezev-account-dropdown">
            <a href="<?php echo esc_url($destination); ?>" class="ezev-account-item">
              <div class="ezev-account-title">My Dashboard</div>
              <div class="ezev-account-desc">Open your portal</div>
            </a>
            <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="ezev-account-item">
              <div class="ezev-account-title">Log Out</div>
            </a>
          </div>
        <?php else: ?>
          <button type="button" class="ezev-account-btn" aria-haspopup="true" aria-expanded="false">
            <span>👤 Account</span>
            <span aria-hidden="true">▾</span>
          </button>
          <div class="ezev-account-dropdown">
            <a href="<?php echo esc_url(home_url('/login?type=customer')); ?>" class="ezev-account-item">
              <div class="ezev-account-title">Customer Login</div>
              <div class="ezev-account-desc">For drivers and customers</div>
            </a>
            <a href="<?php echo esc_url(home_url('/login?type=partner')); ?>" class="ezev-account-item">
              <div class="ezev-account-title">Partner Login</div>
              <div class="ezev-account-desc">For partners and investors</div>
            </a>
            <a href="<?php echo esc_url(home_url('/login?type=internal')); ?>" class="ezev-account-item">
              <div class="ezev-account-title">EZEV Internal</div>
              <div class="ezev-account-desc">For EZEV operations staff</div>
            </a>
          </div>
        <?php endif; ?>
      </div>

      <!-- Mobile Hamburger Button -->
      <button type="button" class="ezev-hamburger" id="ezevHamburgerBtn" aria-label="Toggle Navigation Menu">
        <span></span>
        <span></span>
        <span></span>
      </button>
    </div>
  </div>

  <!-- Mobile Drawer Navigation -->
  <div class="ezev-mobile-drawer" id="ezevMobileDrawer">
    <a href="<?php echo esc_url($find_url); ?>" class="ezev-btn ezev-btn-primary ezev-btn-lg" style="width: 100%; justify-content: center;">
      ⚡ Find a Charger
    </a>
    <div style="display: flex; flex-direction: column; gap: var(--ezev-space-3);">
      <a href="<?php echo esc_url(home_url('/charging-network')); ?>" class="ezev-dropdown-link" style="font-size: 1.125rem;">Charging Network</a>
      <a href="<?php echo esc_url(home_url('/how-to-charge')); ?>" class="ezev-dropdown-link" style="font-size: 1.125rem;">How to Charge</a>
      <a href="<?php echo esc_url(home_url('/charging-rates')); ?>" class="ezev-dropdown-link" style="font-size: 1.125rem;">Charging Rates</a>
      <a href="<?php echo esc_url(home_url('/drivers')); ?>" class="ezev-dropdown-link" style="font-size: 1.125rem;">For Drivers</a>
      <a href="<?php echo esc_url(home_url('/business')); ?>" class="ezev-dropdown-link" style="font-size: 1.125rem;">For Business</a>
      <a href="<?php echo esc_url(home_url('/partners')); ?>" class="ezev-dropdown-link" style="font-size: 1.125rem;">Partners</a>
      <a href="<?php echo esc_url(home_url('/support')); ?>" class="ezev-dropdown-link" style="font-size: 1.125rem;">Support</a>
      <a href="<?php echo esc_url(home_url('/about')); ?>" class="ezev-dropdown-link" style="font-size: 1.125rem;">About EVD</a>
    </div>
    <div style="margin-top: auto; border-top: 1px solid rgba(255,255,255,0.1); padding-top: var(--ezev-space-4);">
      <div style="color: #94A3B8; font-size: 0.8125rem; margin-bottom: var(--ezev-space-2);">ACCOUNT ACCESS</div>
      <a href="<?php echo esc_url(home_url('/login?type=customer')); ?>" class="ezev-dropdown-link">Customer Login</a>
      <a href="<?php echo esc_url(home_url('/login?type=partner')); ?>" class="ezev-dropdown-link">Partner Login</a>
      <a href="<?php echo esc_url(home_url('/login?type=internal')); ?>" class="ezev-dropdown-link">EZEV Internal</a>
    </div>
  </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var hamburger = document.getElementById('ezevHamburgerBtn');
  var drawer = document.getElementById('ezevMobileDrawer');
  if (hamburger && drawer) {
    hamburger.addEventListener('click', function() {
      drawer.classList.toggle('active');
    });
  }
});
</script>