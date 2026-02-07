<?php
/**
 * GitHub Tree Parser.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Api;

/**
 * Parses the GitHub tree into structured translation data.
 */
final class TreeParser {

	/**
	 * Supported file extensions for translation files.
	 */
	private const EXTENSIONS = [ '.mo', '.po', '.l10n.php', '.json' ];

	/**
	 * Parse tree entries for a given tone and locale.
	 *
	 * Returns an array keyed by slug, containing type and file entries.
	 *
	 * @param array<int, array<string, string>> $tree   Raw tree from GitHub API.
	 * @param string                            $tone   Translation tone (formal/informal).
	 * @param string                            $locale Target locale (e.g. hu_HU).
	 * @return array<string, array{type: string, files: array<string, string>}>
	 */
	public static function parse( array $tree, string $tone, string $locale ): array {
		$results = [];

		foreach ( [ 'plugins', 'themes' ] as $type ) {
			$prefix = $tone . '/' . $type . '/' . $locale . '/';

			foreach ( $tree as $entry ) {
				if ( 'blob' !== ( $entry['type'] ?? '' ) ) {
					continue;
				}

				$path = $entry['path'] ?? '';

				if ( ! str_starts_with( $path, $prefix ) ) {
					continue;
				}

				$relative = substr( $path, strlen( $prefix ) );
				$parts    = explode( '/', $relative, 2 );

				if ( 2 !== count( $parts ) ) {
					continue;
				}

				$slug     = $parts[0];
				$filename = $parts[1];

				if ( ! self::is_translation_file( $filename ) ) {
					continue;
				}

				if ( ! isset( $results[ $slug ] ) ) {
					$results[ $slug ] = [
						'type'  => 'themes' === $type ? 'theme' : 'plugin',
						'files' => [],
					];
				}

				$results[ $slug ]['files'][ $filename ] = $entry['sha'] ?? '';
			}
		}

		return $results;
	}

	/**
	 * Extract available locales from the tree.
	 *
	 * @param array<int, array<string, string>> $tree Raw tree from GitHub API.
	 * @param string                            $tone Translation tone.
	 * @return array<string>
	 */
	public static function get_available_locales( array $tree, string $tone ): array {
		$locales = [];

		foreach ( $tree as $entry ) {
			if ( 'tree' !== ( $entry['type'] ?? '' ) ) {
				continue;
			}

			$path = $entry['path'] ?? '';

			if ( ! str_starts_with( $path, $tone . '/plugins/' ) && ! str_starts_with( $path, $tone . '/themes/' ) ) {
				continue;
			}

			$parts = explode( '/', $path );

			if ( 3 === count( $parts ) ) {
				$locales[ $parts[2] ] = true;
			}
		}

		$result = array_keys( $locales );
		sort( $result );

		return $result;
	}

	/**
	 * Check if a filename is a supported translation file.
	 *
	 * @param string $filename File name to check.
	 * @return bool
	 */
	private static function is_translation_file( string $filename ): bool {
		foreach ( self::EXTENSIONS as $ext ) {
			if ( str_ends_with( $filename, $ext ) ) {
				return true;
			}
		}

		return false;
	}
}
