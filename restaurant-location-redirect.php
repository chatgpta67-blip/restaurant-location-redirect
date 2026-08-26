<?php
/**
 * Plugin Name:       Restaurant Location Order Redirect
 * Plugin URI:         https://example.com/plugins/restaurant-location-redirect
 * Description:        Detects a visitor's likely restaurant location (via saved preference or IP geolocation) and dynamically points "Order Now" buttons to the correct location-specific ordering URL. Includes a manual location selector, admin-managed locations, and privacy-conscious analytics.
 * Version:            1.0.0
 * Requires at least:  5.8
 * Requires PHP:       7.4
 * Author:              Digi-Pro
 * License:             GPL v2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:         restaurant-location-redirect
 * Domain Path:         /languages
 *
 * @package Restaurant_Location_Redirect
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core plugin constants.
 */
define( 'RLR_VERSION', '1.0.0' );
define( 'RLR_DB_VERSION', '1.0.0' );
define( 'RLR_PLUGIN_FILE', __FILE__ );
define( 'RLR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RLR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RLR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Dependency includes.
 *
 * Loaded in dependency order. This plugin intentionally avoids a class
 * autoloader in favor of explicit requires so the load order is obvious
 * and predictable across hosting environments.
 */
require_once RLR_PLUGIN_DIR . 'includes/class-rlr-helpers.php';
require_once RLR_PLUGIN_DIR . 'includes/class-rlr-settings.php';
require_once RLR_PLUGIN_DIR . 'includes/class-rlr-location-manager.php';
require_once RLR_PLUGIN_DIR . 'includes/interface-rlr-geolocation-provider.php';
require_once RLR_PLUGIN_DIR . 'includes/providers/class-rlr-provider-ipapi.php';
require_once RLR_PLUGIN_DIR . 'includes/providers/class-rlr-provider-ipinfo.php';
require_once RLR_PLUGIN_DIR . 'includes/class-rlr-geolocation.php';
require_once RLR_PLUGIN_DIR . 'includes/class-rlr-matcher.php';
require_once RLR_PLUGIN_DIR . 'includes/class-rlr-analytics.php';
require_once RLR_PLUGIN_DIR . 'includes/class-rlr-rest.php';
require_once RLR_PLUGIN_DIR . 'includes/class-rlr-admin.php';
require_once RLR_PLUGIN_DIR . 'includes/class-rlr-public.php';
require_once RLR_PLUGIN_DIR . 'includes/class-rlr-plugin.php';
require_once RLR_PLUGIN_DIR . 'includes/class-rlr-activator.php';
require_once RLR_PLUGIN_DIR . 'includes/class-rlr-deactivator.php';

/**
 * Activation / deactivation hooks.
 */
register_activation_hook( __FILE__, array( 'RLR_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RLR_Deactivator', 'deactivate' ) );

/**
 * Boot the plugin.
 */
function rlr_run_plugin() {
	$plugin = new RLR_Plugin();
	$plugin->run();
}
add_action( 'plugins_loaded', 'rlr_run_plugin' );
