<?php
/**
 * GitHub Error class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Api;

use WP_Error;

/**
 * Turns a failed GitHub API response into a message the admin can act on.
 *
 * Unauthenticated requests are limited to 60 an hour per IP; when that
 * runs out GitHub answers 403 or 429 with X-RateLimit-Remaining: 0 and the
 * reset time in X-RateLimit-Reset.
 */
final class GitHubError {

	/**
	 * Error for a non-200 response.
	 *
	 * @param int    $status    HTTP status.
	 * @param string $remaining X-RateLimit-Remaining header ('' when absent).
	 * @param string $reset     X-RateLimit-Reset header, Unix time ('' when absent).
	 * @param int    $now       Current Unix time.
	 * @return WP_Error
	 */
	public static function for_status( int $status, string $remaining, string $reset, int $now ): WP_Error {
		if ( in_array( $status, [ 403, 429 ], true ) && '0' === trim( $remaining ) ) {
			return self::rate_limited( ctype_digit( trim( $reset ) ) ? (int) $reset : 0, $now );
		}

		return new WP_Error(
			'github_api_error',
			/* translators: %d: HTTP status code */
			sprintf( __( 'GitHub API returned status %d.', 'lw-translate' ), $status )
		);
	}

	/**
	 * Rate limit error with the wait in minutes.
	 *
	 * @param int $reset Reset time (Unix), 0 when unknown.
	 * @param int $now   Current Unix time.
	 * @return WP_Error
	 */
	private static function rate_limited( int $reset, int $now ): WP_Error {
		if ( $reset <= 0 ) {
			return new WP_Error( 'github_rate_limited', __( 'GitHub API rate limit reached. Try again later.', 'lw-translate' ) );
		}

		$minutes = max( 1, (int) ceil( ( $reset - $now ) / 60 ) );

		return new WP_Error(
			'github_rate_limited',
			sprintf(
				/* translators: %d: number of minutes */
				_n( 'GitHub API rate limit reached. Try again in %d minute.', 'GitHub API rate limit reached. Try again in %d minutes.', $minutes, 'lw-translate' ),
				$minutes
			),
			[ 'reset' => $reset ]
		);
	}
}
