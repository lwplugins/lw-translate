<?php
/**
 * File Matcher class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Installer;

use LightweightPlugins\Translate\Options;

/**
 * Matches remote file paths to local WordPress language directory paths.
 */
final class FileMatcher {

	/**
	 * Get remote paths for a plugin/theme translation.
	 *
	 * @param string $slug Plugin or theme slug.
	 * @param string $type Type: 'plugin' or 'theme'.
	 * @return array<string> Remote file paths in the repository.
	 */
	public static function get_remote_paths( string $slug, string $type ): array {
		$tone   = (string) Options::get( 'tone', 'formal' );
		$locale = (string) Options::get( 'locale', 'hu_HU' );
		$dir    = 'theme' === $type ? 'themes' : 'plugins';
		$prefix = $tone . '/' . $dir . '/' . $locale . '/' . $slug . '/';

		$extensions = [ '.mo', '.po', '.l10n.php' ];
		$paths      = [];

		foreach ( $extensions as $ext ) {
			$paths[] = $prefix . $slug . '-' . $locale . $ext;
		}

		// JSON files may have different naming (e.g. with md5 hash).
		// We'll handle these from the tree data directly.

		return $paths;
	}

	/**
	 * Get remote paths from cached tree data for a slug.
	 *
	 * @param string                            $slug Plugin or theme slug.
	 * @param string                            $type Type: 'plugin' or 'theme'.
	 * @param array<int, array<string, string>> $tree Full tree data.
	 * @return array<string> Matching remote file paths.
	 */
	public static function get_remote_paths_from_tree( string $slug, string $type, array $tree ): array {
		$tone   = (string) Options::get( 'tone', 'formal' );
		$locale = (string) Options::get( 'locale', 'hu_HU' );
		$dir    = 'theme' === $type ? 'themes' : 'plugins';
		$prefix = $tone . '/' . $dir . '/' . $locale . '/' . $slug . '/';
		$paths  = [];

		foreach ( $tree as $entry ) {
			if ( 'blob' !== ( $entry['type'] ?? '' ) ) {
				continue;
			}

			$path = $entry['path'] ?? '';

			if ( str_starts_with( $path, $prefix ) ) {
				$paths[] = $path;
			}
		}

		return $paths;
	}

	/**
	 * Convert a remote path to a local WordPress language directory path.
	 *
	 * @param string $remote_path Remote file path in the repository.
	 * @return string Local file path under WP_LANG_DIR.
	 */
	public static function remote_to_local( string $remote_path ): string {
		// Path format: formal/plugins/hu_HU/slug/filename.
		$parts = explode( '/', $remote_path );

		if ( count( $parts ) < 5 ) {
			return '';
		}

		// $parts: [tone, type_dir, locale, slug, filename].
		$type_dir = $parts[1]; // "plugins" or "themes"
		$filename = end( $parts );

		return WP_LANG_DIR . '/' . $type_dir . '/' . $filename;
	}
}
