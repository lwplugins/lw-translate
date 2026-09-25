<?php
/**
 * Locale Catalog class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Settings;

use LightweightPlugins\Translate\Api\GitHubClient;
use LightweightPlugins\Translate\Api\TreeParser;
use LightweightPlugins\Translate\Installer\FileNamePolicy;
use LightweightPlugins\Translate\Options;

/**
 * The locales the repository offers, with display names.
 */
final class LocaleCatalog {

	/**
	 * Locales in the repository tree, over every tone.
	 *
	 * @param array<int, array{type?: string, path?: mixed}> $tree Raw tree.
	 * @return array<int, string> Sorted, well-formed locale codes.
	 */
	public static function from_tree( array $tree ): array {
		$locales = [];

		foreach ( Options::TONES as $tone ) {
			foreach ( TreeParser::get_available_locales( $tree, $tone ) as $locale ) {
				if ( FileNamePolicy::is_valid_locale( $locale ) ) {
					$locales[ $locale ] = true;
				}
			}
		}

		$codes = array_map( 'strval', array_keys( $locales ) );
		sort( $codes, SORT_STRING );

		return $codes;
	}

	/**
	 * The offered locales: from the tree, or the default and the saved
	 * locale when the repository cannot be read.
	 *
	 * @param bool $fetch Fetch the tree from GitHub on a cache miss. Reading
	 *                    the settings passes false (the Translations list,
	 *                    loaded next to it, fetches the tree anyway);
	 *                    validating a save passes true.
	 * @return array<int, string>
	 */
	public static function offered( bool $fetch = true ): array {
		$tree    = $fetch ? ( new GitHubClient() )->fetch_tree() : GitHubClient::cached_tree();
		$locales = is_array( $tree ) ? self::from_tree( $tree ) : [];

		if ( [] !== $locales ) {
			return $locales;
		}

		return array_values( array_unique( [ (string) Options::get_defaults()['locale'], (string) Options::get( 'locale', 'hu_HU' ) ] ) );
	}

	/**
	 * Locale options for the screen: value and "Native name (code)".
	 *
	 * @param array<int, string> $codes Locale codes.
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function options( array $codes ): array {
		return array_map(
			static fn( string $code ): array => [
				'value' => $code,
				'label' => self::label( $code ),
			],
			$codes
		);
	}

	/**
	 * "Magyar (hu_HU)" when the intl extension knows the language, else the code.
	 *
	 * @param string $code Locale code.
	 * @return string
	 */
	public static function label( string $code ): string {
		if ( ! class_exists( 'Locale' ) ) {
			return $code;
		}

		$name = \Locale::getDisplayLanguage( $code, $code );

		if ( ! is_string( $name ) || strtolower( $name ) === strtolower( explode( '_', $code )[0] ) ) {
			return $code;
		}

		return mb_strtoupper( mb_substr( $name, 0, 1 ) ) . mb_substr( $name, 1 ) . ' (' . $code . ')';
	}
}
