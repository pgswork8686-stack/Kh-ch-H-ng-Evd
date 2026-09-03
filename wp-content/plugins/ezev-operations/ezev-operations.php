<?php
/**
 * Plugin Name: EZEV Operations
 * Description: Charger operations, energy, sessions, alerts, maintenance and API integration layer for EZEV/EVD.
 * Version: 4.0.1
 * Author: PGS Agency
 * Text Domain: ezev-operations
 * Requires at least: 6.4
 * Requires PHP: 8.1
 */
if (!defined('ABSPATH')) { exit; }

define('EZEVO_VERSION','4.0.1');
define('EZEVO_DB_VERSION','1.1.0');
define('EZEVO_FILE',__FILE__);
define('EZEVO_DIR',plugin_dir_path(__FILE__));
define('EZEVO_URL',plugin_dir_url(__FILE__));

require_once EZEVO_DIR.'includes/class-ezevo-db.php';
require_once EZEVO_DIR.'includes/class-ezevo-secrets.php';
require_once EZEVO_DIR.'includes/providers/interface-ezevo-provider.php';
require_once EZEVO_DIR.'includes/providers/class-ezevo-manual-provider.php';
require_once EZEVO_DIR.'includes/providers/class-ezevo-demo-provider.php';
require_once EZEVO_DIR.'includes/providers/class-ezevo-http-provider.php';
require_once EZEVO_DIR.'includes/class-ezevo-provider-manager.php';
require_once EZEVO_DIR.'includes/class-ezevo-sync.php';
require_once EZEVO_DIR.'includes/class-ezevo-rest.php';
require_once EZEVO_DIR.'includes/class-ezevo-admin.php';
require_once EZEVO_DIR.'includes/class-ezevo.php';

register_activation_hook(__FILE__,['EZEV_Operations','activate']);
register_deactivation_hook(__FILE__,['EZEV_Operations','deactivate']);
add_action('plugins_loaded',static function(){ EZEV_Operations::instance()->boot(); });
