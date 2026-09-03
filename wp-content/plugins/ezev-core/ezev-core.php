<?php
/**
 * Plugin Name: EZEV Core
 * Description: Core data, stations, real map, organizations, identity and access foundation for EZEV/EVD.
 * Version: 4.0.1
 * Author: PGS Agency
 * Text Domain: ezev-core
 * Requires at least: 6.4
 * Requires PHP: 8.1
 */

if (!defined('ABSPATH')) { exit; }

define('EZEV_CORE_VERSION', '4.0.1');
define('EZEV_CORE_FILE', __FILE__);
define('EZEV_CORE_DIR', plugin_dir_path(__FILE__));
define('EZEV_CORE_URL', plugin_dir_url(__FILE__));

require_once EZEV_CORE_DIR . 'includes/class-ezev-core-db.php';
require_once EZEV_CORE_DIR . 'includes/class-ezev-core-roles.php';
require_once EZEV_CORE_DIR . 'includes/class-ezev-core-stations.php';
require_once EZEV_CORE_DIR . 'includes/class-ezev-core-auth.php';
require_once EZEV_CORE_DIR . 'includes/class-ezev-core-rest.php';
require_once EZEV_CORE_DIR . 'includes/class-ezev-core-admin.php';
require_once EZEV_CORE_DIR . 'includes/class-ezev-core-shortcodes.php';
require_once EZEV_CORE_DIR . 'includes/class-ezev-core.php';

register_activation_hook(__FILE__, ['EZEV_Core', 'activate']);
register_deactivation_hook(__FILE__, ['EZEV_Core', 'deactivate']);

add_action('plugins_loaded', static function () {
    EZEV_Core::instance()->boot();
});
