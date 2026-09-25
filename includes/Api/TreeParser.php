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
	 *
	 * Public: this is the single source of truth for "is this a
	 * translation file" checks. TreeParser::parse() uses it to filter remote
	 * tree paths, so keep it here rather than duplicating the list.
	 *
	 * ".l10n.php" is deliberately absent: those files are executable PHP,
	 * so they are never taken from the repository. L10nPhpGenerator builds
	 * them locally from the installed .mo.
	 */
	public const EXTENSIONS = [ '.mo', '.po', '.json' ];

	/**
	 * Repository folder per item type.
	 */
	private const DIRS = [
		'plugin' => 'plugins',
		'theme'  => 'themes',
	];

	/**
	 * Parse tree entries for a given tone and locale.
	 *
	 * Plugins and themes are kept apart, because a theme and a plugin can
	 * share a directory name.
	 *
	 * @param array<int, array{type?: string, path?: mixed, sha?: string}> $tree   Raw tree from GitHub API. "path" is
	 *                                                                             untrusted remote input and is not
	 *                                                                             guaranteed to be a string.
	 * @param string                                                       $tone   Translation tone (formal/informal).
	 * @param string                                                       $locale Target locale (e.g. hu_HU).
	 * @return array{plugin: array<string, array<string, string>>, theme: array<string, array<string, string>>} Type => slug => file name => blob SHA.
	 */
	public static function parse( array $tree, string $tone, string $locale ): array {
		$results = [
			'plugin' => [],
			'theme'  => [],
		];

		foreach ( self::DIRS as $type => $dir ) {
			$prefix = $tone . '/' . $dir . '/' . $locale . '/';

			foreach ( $tree as $entry ) {
				$file = self::match_entry( $entry, $prefix );

				if ( null === $file ) {
					continue;
				}

				$results[ $type ][ $file[0] ][ $file[1] ] = (string) ( $entry['sha'] ?? '' );
			}
		}

		return $results;
	}

	/**
	 * Slug and file name of a translation blob under a prefix, or null.
	 *
	 * @param array{type?: string, path?: mixed} $entry  Tree entry.
	 * @param string                             $prefix "{tone}/{dir}/{locale}/".
	 * @return array{0: string, 1: string}|null
	 */
	private static function match_entry( array $entry, string $prefix ): ?array {
		$path = $entry['path'] ?? '';

		if ( 'blob' !== ( $entry['type'] ?? '' ) || ! is_string( $path ) || ! str_starts_with( $path, $prefix ) ) {
			return null;
		}

		$parts = explode( '/', substr( $path, strlen( $prefix ) ), 2 );

		if ( 2 !== count( $parts ) || ! self::is_translation_file( $parts[1] ) ) {
			return null;
		}

		return [ $parts[0], $parts[1] ];
	}

	/**
	 * Extract available locales from the tree.
	 *
	 * @param array<int, array{type?: string, path?: mixed}> $tree Raw tree from GitHub API. "path" is untrusted
	 *                                                              remote input and is not guaranteed to be a string.
	 * @param string                                         $tone Translation tone.
	 * @return array<string>
	 */
	public static function get_available_locales( array $tree, string $tone ): array {
		$locales = [];

		foreach ( $tree as $entry ) {
			if ( 'tree' !== ( $entry['type'] ?? '' ) ) {
				continue;
			}

			$path = $entry['path'] ?? '';

			if ( ! is_string( $path ) ) {
				continue;
			}

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
