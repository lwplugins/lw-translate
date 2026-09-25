<?php
/**
 * Main Plugin class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate;

use LightweightPlugins\Translate\Admin\SettingsPage;
use LightweightPlugins\Translate\CLI\Commands as CLICommands;
use LightweightPlugins\Translate\Rest\Admin\Routes as AdminRoutes;
use LightweightPlugins\Translate\SiteManager\Integration as SiteManagerIntegration;
use LightweightPlugins\Translate\Upgrade\Upgrader;

/**
 * Main plugin class.
 */
final class Plugin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
		$this->init_site_manager();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'init', [ $this, 'load_textdomain' ] );

		CLICommands::register();

		// REST requests are not is_admin(): the admin routes register on
		// every request (rest_api_init only fires for REST ones).
		AdminRoutes::register();

		if ( is_admin() ) {
			new SettingsPage();
			Upgrader::init();
		}
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'lw-translate',
			false,
			dirname( plugin_basename( LW_TRANSLATE_FILE ) ) . '/languages'
		);
	}

	/**
	 * Initialize LW Site Manager integration.
	 *
	 * No-op if Site Manager is not active.
	 *
	 * @return void
	 */
	private function init_site_manager(): void {
		SiteManagerIntegration::init();
	}
}
