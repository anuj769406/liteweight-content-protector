<?php
/**
 * Plugin Name: Liteweight Content Protector
 * Plugin URI: https://wplite.com/
 * Description: Liteweight, selective content protection for posts and pages with performance-first loading.
 * Version: 1.0.5
 * Author: Anuj Dhungana
 * Requires at least: 6.3
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: liteweight-content-protector
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LWCP_VERSION', '1.0.5' );
define( 'LWCP_PLUGIN_FILE', __FILE__ );
define( 'LWCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LWCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once LWCP_PLUGIN_DIR . 'includes/class-lwcp-plugin.php';

register_activation_hook( LWCP_PLUGIN_FILE, array( 'LWCP_Plugin', 'activate' ) );

/**
 * Bootstrap the plugin.
 *
 * @return LWCP_Plugin
 */
function lwcp() {
	return LWCP_Plugin::instance();
}

lwcp();
