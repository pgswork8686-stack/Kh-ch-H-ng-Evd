<?php
/**
 * Station Status & Data Mode Badges
 * Args passed via $args: ['status' => 'active', 'mode' => 'manual', 'is_demo' => false]
 */

if (!defined('ABSPATH')) { exit; }

$status  = $args['status'] ?? 'active';
$mode    = $args['mode'] ?? 'manual';
$is_demo = !empty($args['is_demo']);
?>
<div style="display: flex; gap: var(--ezev-space-2); align-items: center; flex-wrap: wrap;">
  <?php echo ezev_render_status_badge($status); ?>
  <?php echo ezev_render_data_badge($mode, $is_demo); ?>
</div>