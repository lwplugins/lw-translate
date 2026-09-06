<?php
/**
 * Plugin Name:       LW Translate
 * Plugin URI:        https://github.com/lwplugins/lw-translate
 * Description:       Lightweight translate — manage WordPress translations from community repositories.
 * Version:           1.1.2
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            LW Plugins
 * Author URI:        https://lwplugins.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       lw-translate
 * Domain Path:       /languages
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'LW_TRANSLATE_VERSION', '1.1.2' );
define( 'LW_TRANSLATE_FILE', __FILE__ );
define( 'LW_TRANSLATE_PATH', plugin_dir_path( __FILE__ ) );
define( 'LW_TRANSLATE_URL', plugin_dir_url( __FILE__ ) );

// Autoloader: local vendor (standalone/ZIP) or root Composer (dependency install).
if ( file_exists( LW_TRANSLATE_PATH . 'vendor/autoload.php' ) ) {
	require_once LW_TRANSLATE_PATH . 'vendor/autoload.php';
} elseif ( ! class_exists( Plugin::class ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p><strong>LW Translate:</strong> %s</p></div>',
				esc_html__( 'Autoloader not found. Please run "composer install" in the plugin directory, or re-install the plugin from a release ZIP.', 'lw-translate' )
			);
		}
	);
	return;
}

// Activation hook.
register_activation_hook( __FILE__, [ Activator::class, 'activate' ] );

/**
 * Returns the main plugin instance.
 *
 * @return Plugin
 */
function lw_translate(): Plugin {
	static $instance = null;

	if ( null === $instance ) {
		$instance = new Plugin();
	}

	return $instance;
}

// Initialize the plugin.
add_action(
	'plugins_loaded',
	static function (): void {
		lw_translate();
	}
);
