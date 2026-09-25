<?php
/**
 * Settings read/write for the admin API.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Settings;

use LightweightPlugins\Translate\Options;
use LightweightPlugins\Translate\Settings\Input\OptionInput;

/**
 * Reads the settings as typed values and applies partial, atomic updates:
 * a key the client did not send keeps its stored value, and nothing is
 * saved when any submitted field is invalid.
 */
final class SettingsStore {

	/**
	 * Keys pinned by a constant (none today), key => constant name.
	 *
	 * @return array<string, string>
	 */
	public static function locked(): array {
		return [];
	}

	/**
	 * Every setting, typed like its default.
	 *
	 * @return array{tone: string, locale: string, cache_ttl: int}
	 */
	public static function current(): array {
		$options = Options::get_all();

		return [
			'tone'      => (string) $options['tone'],
			'locale'    => (string) $options['locale'],
			'cache_ttl' => Options::clamp_cache_ttl( (int) $options['cache_ttl'] ),
		];
	}

	/**
	 * Apply a partial update.
	 *
	 * @param array<string|int, mixed> $body    Submitted key => value pairs.
	 * @param array<int, string>       $offered Locales the repository offers.
	 * @return array<string, array<int, string>> Messages per invalid field; empty when saved.
	 */
	public static function save( array $body, array $offered ): array {
		$report = OptionInput::parse( $body, $offered, self::locked() );

		if ( $report->has_errors() ) {
			return $report->errors();
		}

		if ( [] !== $report->values() ) {
			$known = array_intersect_key( Options::get_all(), Options::get_defaults() );
			Options::save( array_merge( $known, $report->values() ) );
		}

		return [];
	}
}
