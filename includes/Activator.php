<?php
/**
 * Plugin Activator class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate;

/**
 * Handles plugin activation.
 */
final class Activator {

	/**
	 * Activate the plugin.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::set_defaults();
	}

	/**
	 * Set default options if not already set.
	 *
	 * @return void
	 */
	private static function set_defaults(): void {
		if ( false === get_option( Options::OPTION_NAME ) ) {
			add_option( Options::OPTION_NAME, Options::get_defaults() );
		}
	}
}
