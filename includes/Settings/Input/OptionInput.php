<?php
/**
 * The input layer for settings writes.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Settings\Input;

use LightweightPlugins\Translate\Options;

/**
 * Turns raw submitted values into option values, per key.
 *
 * Only the keys that were sent are parsed (a partial save never touches
 * the others), a blank or out-of-range number is an error instead of a
 * silent clamp, and a locked key cannot be written.
 */
final class OptionInput {

	/**
	 * Parse a batch of submitted values.
	 *
	 * @param array<string|int, mixed> $raw     Submitted key => value pairs.
	 * @param array<int, string>       $offered Locales the repository offers.
	 * @param array<string, string>    $locked  Locked key => constant name.
	 * @return InputReport
	 */
	public static function parse( array $raw, array $offered, array $locked = [] ): InputReport {
		$defaults = Options::get_defaults();
		$values   = [];
		$errors   = [];
		$unknown  = [];

		foreach ( $raw as $key => $value ) {
			$key = (string) $key;

			if ( ! array_key_exists( $key, $defaults ) ) {
				$unknown[] = $key;
				continue;
			}

			if ( isset( $locked[ $key ] ) ) {
				$errors[ $key ] = [
					/* translators: %s: constant name */
					sprintf( __( 'This setting is set by %s in wp-config.php and cannot be changed here.', 'lw-translate' ), $locked[ $key ] ),
				];
				continue;
			}

			$result = self::parse_value( $key, $value, $offered );

			if ( $result->is_valid() ) {
				$values[ $key ] = $result->value();
			} else {
				$errors[ $key ] = $result->errors();
			}
		}

		return new InputReport( $values, $errors, $unknown );
	}

	/**
	 * Parse one value for a known key.
	 *
	 * @param string             $key     Option key.
	 * @param mixed              $value   Raw value.
	 * @param array<int, string> $offered Locales the repository offers.
	 * @return ParseResult
	 */
	private static function parse_value( string $key, mixed $value, array $offered ): ParseResult {
		switch ( $key ) {
			case 'tone':
				return EnumParser::parse( $value, Options::TONES );
			case 'locale':
				return LocaleParser::parse( $value, $offered );
			case 'cache_ttl':
				return IntParser::parse( $value, Options::CACHE_TTL_MIN, Options::CACHE_TTL_MAX );
		}

		/* translators: %s: setting key */
		return ParseResult::fail( [ sprintf( __( 'Unknown setting: %s.', 'lw-translate' ), $key ) ] );
	}
}
