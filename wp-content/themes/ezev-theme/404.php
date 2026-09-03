<?php
/**
 * 404 Not Found Template
 * Unpublish Station lifecycle handler
 */

if (!defined('ABSPATH')) { exit; }

get_header();
?>
<section class="ezev-container" style="padding-top: var(--ezev-space-20); padding-bottom: var(--ezev-space-24); text-align: center;">
  <div style="max-width: 540px; margin: 0 auto;">
    <div style="font-size: 5rem; font-weight: 800; color: var(--ezev-color-primary); line-height: 1;">404</div>
    <h1 style="margin-top: var(--ezev-space-4); margin-bottom: var(--ezev-space-2); font-size: 1.875rem;">Station or Page Not Found</h1>
    <p style="margin-bottom: var(--ezev-space-8);">
      The charging station or page you are looking for is currently inactive, has been decommissioned, or does not exist.
    </p>
    <div style="display: flex; justify-content: center; gap: var(--ezev-space-4);">
      <a href="<?php echo esc_url(home_url('/find-a-charger')); ?>" class="ezev-btn ezev-btn-primary">
        ⚡ Find a Charger
      </a>
      <a href="<?php echo esc_url(home_url('/')); ?>" class="ezev-btn ezev-btn-outline">
        Back to Home
      </a>
    </div>
  </div>
</section>
<?php
get_footer();