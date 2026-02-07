<?php
/**
 * Local Scanner class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Translation;

/**
 * Scans installed plugins and themes for translation data.
 */
final class LocalScanner {

	/**
	 * Get all installed plugin slugs with display names.
	 *
	 * @return array<string, string> Slug => Name.
	 */
	public static function get_installed_plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
		$result  = [];

		foreach ( $plugins as $file => $data ) {
			$slug = self::extract_plugin_slug( $file );

			if ( ! empty( $slug ) ) {
				$result[ $slug ] = $data['Name'] ?? $slug;
			}
		}

		return $result;
	}

	/**
	 * Get all installed theme slugs with display names.
	 *
	 * @return array<string, string> Slug => Name.
	 */
	public static function get_installed_themes(): array {
		$themes = wp_get_themes();
		$result = [];

		foreach ( $themes as $slug => $theme ) {
			$result[ $slug ] = $theme->get( 'Name' );
		}

		return $result;
	}

	/**
	 * Get the local .mo file SHA for comparison.
	 *
	 * @param string $slug   Plugin or theme slug.
	 * @param string $type   Type: 'plugin' or 'theme'.
	 * @param string $locale Locale code.
	 * @return string|null Git-compatible SHA or null if not found.
	 */
	public static function get_local_sha( string $slug, string $type, string $locale ): ?string {
		$path = self::get_local_mo_path( $slug, $type, $locale );

		if ( ! file_exists( $path ) ) {
			return null;
		}

		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $content ) {
			return null;
		}

		return self::git_blob_sha( $content );
	}

	/**
	 * Get the PO-Revision-Date from a local .po file.
	 *
	 * @param string $slug   Plugin or theme slug.
	 * @param string $type   Type: 'plugin' or 'theme'.
	 * @param string $locale Locale code.
	 * @return string Date string or empty.
	 */
	public static function get_local_date( string $slug, string $type, string $locale ): string {
		$dir  = 'theme' === $type ? 'themes' : 'plugins';
		$path = WP_LANG_DIR . '/' . $dir . '/' . $slug . '-' . $locale . '.po';

		if ( ! file_exists( $path ) ) {
			return '';
		}

		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $content ) {
			return '';
		}

		if ( preg_match( '/PO-Revision-Date:\s*(.+?)\\\\n/', $content, $matches ) ) {
			return trim( $matches[1] );
		}

		return '';
	}

	/**
	 * Get the full path to a local .mo file.
	 *
	 * @param string $slug   Plugin or theme slug.
	 * @param string $type   Type: 'plugin' or 'theme'.
	 * @param string $locale Locale code.
	 * @return string Full file path.
	 */
	public static function get_local_mo_path( string $slug, string $type, string $locale ): string {
		$dir = 'theme' === $type ? 'themes' : 'plugins';
		return WP_LANG_DIR . '/' . $dir . '/' . $slug . '-' . $locale . '.mo';
	}

	/**
	 * Calculate a git blob SHA from file content.
	 *
	 * @param string $content File content.
	 * @return string SHA hash.
	 */
	public static function git_blob_sha( string $content ): string {
		return sha1( 'blob ' . strlen( $content ) . "\0" . $content );
	}

	/**
	 * Extract the plugin slug from a plugin file path.
	 *
	 * @param string $file Plugin file path (e.g. "akismet/akismet.php").
	 * @return string Plugin slug.
	 */
	private static function extract_plugin_slug( string $file ): string {
		if ( str_contains( $file, '/' ) ) {
			return dirname( $file );
		}

		return pathinfo( $file, PATHINFO_FILENAME );
	}
}
