<?php
/**
 * Station Card Component
 * Expected $args: StationDTO array
 */

if (!defined('ABSPATH')) { exit; }

$station = $args ?? [];
if (empty($station)) { return; }

$name        = $station['name'] ?? 'EZEV Station';
$station_id  = $station['station_id'] ?? '';
$slug        = $station['slug'] ?? '';
$url         = $station['url'] ?? home_url('/stations/' . $slug);
$address     = $station['address']['line'] ?? ($station['address'] ?? '');
$city        = $station['address']['city'] ?? '';
$connectors  = $station['connectors'] ?? [];
$max_power   = $station['max_power_kw'] ?? 0;
$ports_avail = $station['ports']['available'] ?? 0;
$ports_total = $station['ports']['total'] ?? 0;
$status      = $station['status'] ?? 'active';
$mode        = $station['data']['mode'] ?? 'manual';
$is_demo     = !empty($station['data']['is_demo']);
$thumbnail   = $station['thumbnail'] ?? (get_template_directory_uri() . '/assets/images/station-hero.jpg');
?>
<article class="ezev-station-card" data-station-id="<?php echo esc_attr($station_id); ?>" data-lat="<?php echo esc_attr($station['location']['lat'] ?? ''); ?>" data-lng="<?php echo esc_attr($station['location']['lng'] ?? ''); ?>">
  <div class="ezev-card-media">
    <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($name); ?>" loading="lazy" class="ezev-card-img" />
    <div class="ezev-card-badges">
      <?php get_template_part('template-parts/station/badge-status', null, ['status' => $status, 'mode' => $mode, 'is_demo' => $is_demo]); ?>
    </div>
  </div>

  <div class="ezev-card-body">
    <h3 class="ezev-card-title">
      <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($name); ?></a>
    </h3>

    <div class="ezev-card-location">
      <span class="ezev-icon-pin">📍</span>
      <span class="ezev-text-address"><?php echo esc_html($address ? ($city ? "$address, $city" : $address) : 'Vietnam'); ?></span>
    </div>

    <div class="ezev-card-specs">
      <div class="ezev-spec-item">
        <span class="ezev-spec-label">Connectors</span>
        <strong class="ezev-spec-val"><?php echo esc_html(implode(', ', (array)$connectors) ?: 'CCS2'); ?></strong>
      </div>
      <div class="ezev-spec-item">
        <span class="ezev-spec-label">Max Power</span>
        <strong class="ezev-spec-val"><?php echo esc_html($max_power); ?> kW</strong>
      </div>
    </div>

    <div class="ezev-card-footer">
      <div class="ezev-port-status">
        <span class="ezev-port-count"><?php echo esc_html($ports_avail); ?> / <?php echo esc_html($ports_total); ?></span>
        <span class="ezev-port-label">Available</span>
      </div>
      <a href="<?php echo esc_url($url); ?>" class="ezev-btn ezev-btn-primary ezev-btn-sm">
        View details &rarr;
      </a>
    </div>
  </div>
</article>