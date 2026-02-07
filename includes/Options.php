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
		self::$options = $options;
		return update_option( self::OPTION_NAME, $options );
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
