<?php
/**
 * Local Scanner class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Translation;

use LightweightPlugins\Translate\Installer\FileNamePolicy;

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
	 * Text domains of the installed plugins or themes, from their headers.
	 *
	 * Only items with a non-empty TextDomain are listed. For a plugin folder
	 * with several plugin files the first one that declares a domain wins.
	 *
	 * @param string $type Type: 'plugin' or 'theme'.
	 * @return array<string, string> Slug => text domain.
	 */
	public static function get_text_domains( string $type ): array {
		$result = [];

		if ( 'theme' === $type ) {
			foreach ( wp_get_themes() as $slug => $theme ) {
				$domain = (string) $theme->get( 'TextDomain' );

				if ( '' !== $domain ) {
					$result[ (string) $slug ] = $domain;
				}
			}

			return $result;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( get_plugins() as $file => $data ) {
			$slug   = self::extract_plugin_slug( (string) $file );
			$domain = $data['TextDomain'] ?? '';

			if ( '' !== $slug && ! isset( $result[ $slug ] ) && is_string( $domain ) && '' !== $domain ) {
				$result[ $slug ] = $domain;
			}
		}

		return $result;
	}

	/**
	 * Text domain of one installed plugin or theme, '' when unknown.
	 *
	 * @param string $type Type: 'plugin' or 'theme'.
	 * @param string $slug Plugin or theme slug.
	 * @return string
	 */
	public static function get_text_domain( string $type, string $slug ): string {
		return self::get_text_domains( $type )[ $slug ] ?? '';
	}

	/**
	 * Blob SHAs of the given file names that exist in the item's language folder.
	 *
	 * @param string             $type  Type: 'plugin' or 'theme'.
	 * @param array<int, string> $names File names (already checked by FileNamePolicy).
	 * @return array<string, string> File name => git blob SHA.
	 */
	public static function get_local_shas( string $type, array $names ): array {
		$dir  = WP_LANG_DIR . '/' . ( 'theme' === $type ? 'themes' : 'plugins' );
		$shas = [];

		foreach ( $names as $name ) {
			$path = $dir . '/' . $name;

			if ( ! is_file( $path ) ) {
				continue;
			}

			$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

			if ( false !== $content ) {
				$shas[ $name ] = self::git_blob_sha( $content );
			}
		}

		return $shas;
	}

	/**
	 * Get the PO-Revision-Date from a local .po file.
	 *
	 * The .po named after the text domain (the one WordPress loads) is
	 * preferred; the one named after the slug is the fallback.
	 *
	 * @param string                    $slug        Plugin or theme slug.
	 * @param string                    $type        Type: 'plugin' or 'theme'.
	 * @param string                    $locale      Locale code.
	 * @param string|array<int, string> $text_domain Text domain(s) of the item, '' for none.
	 * @return string Date string or empty.
	 */
	public static function get_local_date( string $slug, string $type, string $locale, string|array $text_domain = '' ): string {
		$dir  = WP_LANG_DIR . '/' . ( 'theme' === $type ? 'themes' : 'plugins' );
		$path = '';

		foreach ( array_reverse( FileNamePolicy::prefixes( $slug, $text_domain ) ) as $prefix ) {
			if ( file_exists( $dir . '/' . $prefix . '-' . $locale . '.po' ) ) {
				$path = $dir . '/' . $prefix . '-' . $locale . '.po';
				break;
			}
		}

		if ( '' === $path ) {
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
