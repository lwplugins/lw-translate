<?php
/**
 * Locale parser.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Settings\Input;

use LightweightPlugins\Translate\Installer\FileNamePolicy;

/**
 * Accepts a well-formed WordPress locale code that the repository offers.
 */
final class LocaleParser {

	/**
	 * Parse a raw value.
	 *
	 * @param mixed              $raw     Raw value.
	 * @param array<int, string> $offered Locales the repository offers.
	 * @return ParseResult
	 */
	public static function parse( mixed $raw, array $offered ): ParseResult {
		$value = is_string( $raw ) ? trim( $raw ) : '';

		if ( ! FileNamePolicy::is_valid_locale( $value ) ) {
			return ParseResult::fail( [ __( 'Not a valid locale code.', 'lw-translate' ) ] );
		}

		if ( ! in_array( $value, $offered, true ) ) {
			return ParseResult::fail(
				[
					/* translators: %s: comma-separated list of locale codes */
					sprintf( __( 'The repository has no translations for this locale. Available: %s.', 'lw-translate' ), implode( ', ', $offered ) ),
				]
			);
		}

		return ParseResult::ok( $value );
	}
}
