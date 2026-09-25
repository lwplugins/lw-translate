<?php
/**
 * L10n PHP Generator class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Installer;

use WP_Filesystem_Base;

/**
 * Builds the `.l10n.php` translation file locally from an installed .mo.
 *
 * WordPress 6.5+ prefers `.l10n.php` files and loads them with include,
 * so they are never taken from the repository. Core's own converter
 * (WP_Translation_File::transform) produces them from the .mo instead.
 * On older WordPress versions nothing is generated.
 */
final class L10nPhpGenerator {

	/**
	 * Converter: fn( string $mo_path, string $format ): string|false.
	 *
	 * @var callable|null
	 */
	private $transform;

	/**
	 * Constructor.
	 *
	 * @param callable|null $transform Converter. Defaults to core's WP_Translation_File::transform, which ships
	 *                                 together with the class since WordPress 6.5.
	 */
	public function __construct( ?callable $transform = null ) {
		if ( null === $transform && class_exists( 'WP_Translation_File' ) ) {
			$transform = [ 'WP_Translation_File', 'transform' ];
		}

		$this->transform = $transform;
	}

	/**
	 * Generate the `.l10n.php` next to a .mo file.
	 *
	 * When nothing can be generated, an existing `.l10n.php` is removed so
	 * WordPress does not keep loading an outdated one instead of the .mo.
	 *
	 * @param string             $mo_path    Absolute path of the installed .mo.
	 * @param WP_Filesystem_Base $filesystem Filesystem.
	 * @return bool True when a file was written.
	 */
	public function generate( string $mo_path, WP_Filesystem_Base $filesystem ): bool {
		$contents = null === $this->transform ? false : call_user_func( $this->transform, $mo_path, 'php' );

		if ( is_string( $contents ) && '' !== $contents
			&& $filesystem->put_contents( self::php_path_for( $mo_path ), $contents, FS_CHMOD_FILE ) ) {
			return true;
		}

		$this->remove( $mo_path, $filesystem );

		return false;
	}

	/**
	 * Remove the `.l10n.php` belonging to a .mo file, if present.
	 *
	 * @param string             $mo_path    Absolute path of the .mo.
	 * @param WP_Filesystem_Base $filesystem Filesystem.
	 * @return void
	 */
	public function remove( string $mo_path, WP_Filesystem_Base $filesystem ): void {
		$php_path = self::php_path_for( $mo_path );

		if ( $filesystem->exists( $php_path ) ) {
			$filesystem->delete( $php_path );
		}
	}

	/**
	 * The `.l10n.php` path for a .mo path.
	 *
	 * @param string $mo_path Path ending in ".mo".
	 * @return string
	 */
	public static function php_path_for( string $mo_path ): string {
		return substr( $mo_path, 0, -3 ) . '.l10n.php';
	}
}
