<?php
/**
 * Capability class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate;

/**
 * Who may install, update or delete translation files.
 *
 * Uses core's install_languages capability, which core maps to "do not
 * allow" when DISALLOW_FILE_MODS is set and, on multisite, for everyone
 * but super admins. Viewing and saving the settings stays manage_options.
 */
final class Capability {

	/**
	 * Capability required to change translation files.
	 */
	public const INSTALL = 'install_languages';

	/**
	 * Whether the current user may change translation files.
	 *
	 * @return bool
	 */
	public static function can_install(): bool {
		return current_user_can( self::INSTALL );
	}
}
