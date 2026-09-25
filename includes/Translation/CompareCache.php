<?php
/**
 * Compare Cache class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Translation;

use LightweightPlugins\Translate\Api\GitHubClient;
use LightweightPlugins\Translate\Options;

/**
 * Keys and invalidation of the cached comparison results.
 *
 * Everything goes through the transient API, so the cache is also cleared
 * when a persistent object cache (Redis, Memcached) holds the transients.
 */
final class CompareCache {

	/**
	 * Transient name prefix.
	 */
	public const PREFIX = 'lw_translate_compare_';

	/**
	 * Cache lifetime in seconds.
	 */
	public const TTL = 3600;

	/**
	 * Transient name for a locale and tone.
	 *
	 * @param string $locale Locale.
	 * @param string $tone   Tone.
	 * @return string
	 */
	public static function key( string $locale, string $tone ): string {
		return self::PREFIX . $locale . '_' . $tone;
	}

	/**
	 * Drop the comparison results of every tone, for the saved and the
	 * default locale.
	 *
	 * @return void
	 */
	public static function clear(): void {
		$locales = array_unique(
			[
				(string) Options::get( 'locale', 'hu_HU' ),
				(string) Options::get_defaults()['locale'],
			]
		);

		foreach ( $locales as $locale ) {
			foreach ( Options::TONES as $tone ) {
				delete_transient( self::key( $locale, $tone ) );
			}
		}
	}

	/**
	 * Drop the comparison results and the cached repository tree.
	 *
	 * @return void
	 */
	public static function clear_all(): void {
		GitHubClient::clear_cache();
		self::clear();
	}
}
