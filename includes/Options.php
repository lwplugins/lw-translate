<?php
/**
 * Options management class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate;

/**
 * Handles plugin options and settings.
 */
final class Options {

	/**
	 * Option name in database.
	 */
	public const OPTION_NAME = 'lw_translate_options';

	/**
	 * Allowed translation tones.
	 */
	public const TONES = [ 'formal', 'informal' ];

	/**
	 * Shortest tree cache lifetime in seconds (1 hour).
	 */
	public const CACHE_TTL_MIN = 3600;

	/**
	 * Longest tree cache lifetime in seconds (1 week).
	 */
	public const CACHE_TTL_MAX = 604800;

	/**
	 * Cached options.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $options = null;

	/**
	 * Get default options.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		return [
			'tone'      => 'formal',
			'locale'    => 'hu_HU',
			'cache_ttl' => 43200,
		];
	}

	/**
	 * Get all options.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all(): array {
		if ( null === self::$options ) {
			$saved         = get_option( self::OPTION_NAME, [] );
			self::$options = wp_parse_args( $saved, self::get_defaults() );
		}

		return self::$options;
	}

	/**
	 * Get a single option.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Default value if not set.
	 * @return mixed
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		$options = self::get_all();

		if ( array_key_exists( $key, $options ) ) {
			return $options[ $key ];
		}

		return $default ?? ( self::get_defaults()[ $key ] ?? null );
	}

	/**
	 * Save all options.
	 *
	 * @param array<string, mixed> $options Options to save.
	 * @return bool
	 */
	public static function save( array $options ): bool {
		$options       = self::sanitize( $options );
		self::$options = $options;
		return update_option( self::OPTION_NAME, $options );
	}

	/**
	 * Enforce the allowed values. Shared by the settings form and WP-CLI.
	 *
	 * An unknown tone becomes "formal"; cache_ttl is clamped to
	 * CACHE_TTL_MIN..CACHE_TTL_MAX. Keys that are not given stay absent.
	 *
	 * @param array<string, mixed> $options Options to check.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $options ): array {
		if ( array_key_exists( 'tone', $options ) ) {
			$options['tone'] = in_array( $options['tone'], self::TONES, true ) ? $options['tone'] : 'formal';
		}

		if ( array_key_exists( 'cache_ttl', $options ) ) {
			$ttl                  = is_numeric( $options['cache_ttl'] ) ? (int) $options['cache_ttl'] : 0;
			$options['cache_ttl'] = self::clamp_cache_ttl( $ttl );
		}

		return $options;
	}

	/**
	 * Clamp a cache lifetime to the allowed range.
	 *
	 * @param int $ttl Seconds.
	 * @return int
	 */
	public static function clamp_cache_ttl( int $ttl ): int {
		return max( self::CACHE_TTL_MIN, min( self::CACHE_TTL_MAX, $ttl ) );
	}

	/**
	 * Clear options cache.
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$options = null;
	}
}
