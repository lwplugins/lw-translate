<?php
/**
 * Path Guard class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Installer;

/**
 * Makes sure a target file stays directly inside a language folder.
 */
final class PathGuard {

	/**
	 * Check that a path points to a file directly inside the base folder.
	 *
	 * The file name may not contain a separator of any platform (a
	 * backslash is a separator on Windows), and the resolved parent
	 * folder must be the resolved base folder.
	 *
	 * @param string $base_dir Base folder, e.g. WP_LANG_DIR/plugins.
	 * @param string $path     Target file path.
	 * @return bool
	 */
	public static function is_contained( string $base_dir, string $path ): bool {
		if ( str_contains( $path, "\0" ) ) {
			return false;
		}

		$slash = strrpos( $path, '/' );
		$name  = false === $slash ? $path : substr( $path, $slash + 1 );

		if ( '' === $name || '.' === $name || '..' === $name || str_contains( $name, '\\' ) ) {
			return false;
		}

		$base   = realpath( $base_dir );
		$parent = realpath( dirname( $path ) );

		return false !== $base && false !== $parent && $base === $parent;
	}
}
