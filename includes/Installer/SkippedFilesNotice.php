<?php
/**
 * Skipped Files Notice class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Installer;

/**
 * Turns the "skipped" list of an install result into a user message.
 */
final class SkippedFilesNotice {

	/**
	 * Build the message, or an empty string when nothing was skipped.
	 *
	 * @param array<int, string> $skipped File names left out of the install.
	 * @return string
	 */
	public static function message( array $skipped ): string {
		if ( empty( $skipped ) ) {
			return '';
		}

		return sprintf(
			/* translators: %s: comma-separated list of file names */
			__( 'Ignored files that are not valid translation files for this item: %s', 'lw-translate' ),
			implode( ', ', $skipped )
		);
	}
}
