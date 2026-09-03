<?php
/**
 * Map Container Component
 * Args passed via $args: ['map_id' => 'ezevMap', 'class' => 'ezev-map-frame', 'show_status' => true]
 */

if (!defined('ABSPATH')) { exit; }

$map_elem_id = $args['map_id'] ?? 'ezevMap';
$custom_class = $args['class'] ?? '';
$maps_config  = EZEV_Theme_Maps::get_config();
$has_key      = $maps_config['hasKey'];
?>
<div class="ezev-map-wrapper <?php echo esc_attr($custom_class); ?>" id="<?php echo esc_attr($map_elem_id); ?>Wrapper">
  <?php if ($has_key): ?>
    <div id="<?php echo esc_attr($map_elem_id); ?>" class="ezev-map-canvas" style="width: 100%; height: 100%; min-height: 420px;"></div>
  <?php else: ?>
    <div class="ezev-map-placeholder" style="width: 100%; height: 100%; min-height: 420px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #F1F5F9; border-radius: var(--ezev-radius-lg); padding: var(--ezev-space-8); text-align: center;">
      <div style="font-size: 2.5rem; margin-bottom: var(--ezev-space-3);">🗺️</div>
      <h3 style="margin-bottom: var(--ezev-space-2); color: var(--ezev-color-gray-700);">Google Maps not configured</h3>
      <p style="max-width: 420px; font-size: 0.875rem; color: var(--ezev-color-gray-500);">
        Please configure the Google Maps API Key in EZEV Core settings (Maps &amp; Integrations) to enable interactive map browsing.
      </p>
    </div>
  <?php endif; ?>
</div>