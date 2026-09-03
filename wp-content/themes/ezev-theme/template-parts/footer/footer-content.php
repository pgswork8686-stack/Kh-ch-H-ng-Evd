<?php
/**
 * Footer Content Template Part
 * Compliance: docs/NAVIGATION-ARCHITECTURE.md
 */

if (!defined('ABSPATH')) { exit; }

$home_url = home_url('/');
$logo_url = get_template_directory_uri() . '/assets/images/logo.svg';
?>
<footer class="ezev-footer">
  <div class="ezev-container">
    <div class="ezev-footer-grid">
      <!-- Col 1: Brand & Overview -->
      <div class="ezev-footer-col">
        <a href="<?php echo esc_url($home_url); ?>" aria-label="EZEV Fast Charging">
          <img src="<?php echo esc_url($logo_url); ?>" alt="EZEV Fast Charging" style="height: 36px; margin-bottom: var(--ezev-space-4);" />
        </a>
        <p style="font-size: 0.875rem; margin-bottom: var(--ezev-space-4); max-width: 320px;">
          Powering the future of EV with fast, reliable and sustainable charging solutions.
        </p>
        <div style="font-size: 0.8125rem; color: #64748B;">
          <div>Hotline: <strong style="color: #F8FAFC;">1900-xxxx</strong></div>
          <div>Support: <strong style="color: #F8FAFC;">support@ezev.vn</strong></div>
        </div>
      </div>

      <!-- Col 2: Charging -->
      <div class="ezev-footer-col">
        <h4>Find a Charger</h4>
        <ul class="ezev-footer-links">
          <li><a href="<?php echo esc_url(home_url('/find-a-charger')); ?>">Map &amp; Search</a></li>
          <li><a href="<?php echo esc_url(home_url('/charging-network')); ?>">Charging Network</a></li>
          <li><a href="<?php echo esc_url(home_url('/how-to-charge')); ?>">How to Charge</a></li>
          <li><a href="<?php echo esc_url(home_url('/charging-rates')); ?>">Charging Rates</a></li>
          <li><a href="<?php echo esc_url(home_url('/drivers')); ?>">For Drivers</a></li>
        </ul>
      </div>

      <!-- Col 3: Business -->
      <div class="ezev-footer-col">
        <h4>Business</h4>
        <ul class="ezev-footer-links">
          <li><a href="<?php echo esc_url(home_url('/business')); ?>">For Business</a></li>
          <li><a href="<?php echo esc_url(home_url('/partners')); ?>">Partners</a></li>
          <li><a href="<?php echo esc_url(home_url('/partners/register')); ?>">Become a Partner</a></li>
          <li><a href="<?php echo esc_url(home_url('/solutions')); ?>">Solutions</a></li>
          <li><a href="<?php echo esc_url(home_url('/projects')); ?>">Featured Projects</a></li>
        </ul>
      </div>

      <!-- Col 4: Discover & Support -->
      <div class="ezev-footer-col">
        <h4>Support</h4>
        <ul class="ezev-footer-links">
          <li><a href="<?php echo esc_url(home_url('/support')); ?>">Help Center</a></li>
          <li><a href="<?php echo esc_url(home_url('/about')); ?>">About EVD</a></li>
          <li><a href="<?php echo esc_url(home_url('/news')); ?>">News &amp; Insights</a></li>
          <li><a href="<?php echo esc_url(home_url('/contact')); ?>">Contact Us</a></li>
          <li><a href="<?php echo esc_url(home_url('/policies/privacy')); ?>">Privacy Policy</a></li>
          <li><a href="<?php echo esc_url(home_url('/policies/terms')); ?>">Terms of Service</a></li>
        </ul>
      </div>

      <!-- Col 5: App & Legal -->
      <div class="ezev-footer-col">
        <h4>Download App</h4>
        <p style="font-size: 0.8125rem; margin-bottom: var(--ezev-space-3);">
          Download the EZEV mobile app for iOS and Android.
        </p>
        <div style="display: flex; flex-direction: column; gap: var(--ezev-space-2); max-width: 140px;">
          <a href="#" class="ezev-btn ezev-btn-secondary ezev-btn-sm" style="font-size: 0.75rem; justify-content: flex-start;">
            <span> App Store</span>
          </a>
          <a href="#" class="ezev-btn ezev-btn-secondary ezev-btn-sm" style="font-size: 0.75rem; justify-content: flex-start;">
            <span>▶ Google Play</span>
          </a>
        </div>
      </div>
    </div>

    <!-- Bottom Bar -->
    <div class="ezev-footer-bottom">
      <div>
        &copy; <?php echo esc_html(gmdate('Y')); ?> EZEV Global / EVD Energy Group. All rights reserved.
      </div>
      <div style="display: flex; gap: var(--ezev-space-4);">
        <a href="<?php echo esc_url(home_url('/policies/privacy')); ?>" style="color: #64748B;">Privacy Policy</a>
        <span style="color: #334155;">·</span>
        <a href="<?php echo esc_url(home_url('/policies/terms')); ?>" style="color: #64748B;">Terms of Service</a>
        <span style="color: #334155;">·</span>
        <a href="<?php echo esc_url(home_url('/policies/charging')); ?>" style="color: #64748B;">Charging Policy</a>
      </div>
    </div>
  </div>
</footer>