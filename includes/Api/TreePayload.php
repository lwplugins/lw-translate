<?php
/**
 * Tree Payload class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Api;

/**
 * The cached repository tree plus what the screen reports about it: whether
 * GitHub cut the listing short ("truncated") and when it was fetched.
 */
final class TreePayload {

	/**
	 * Build the payload from a decoded GitHub Trees API body.
	 *
	 * @param mixed $body Decoded JSON body.
	 * @param int   $now  Fetch time (Unix).
	 * @return array{tree: array<int, array<string, mixed>>, truncated: bool, fetched_at: int}|null Null when there is no tree.
	 */
	public static function from_body( mixed $body, int $now ): ?array {
		if ( ! is_array( $body ) || ! isset( $body['tree'] ) || ! is_array( $body['tree'] ) || [] === $body['tree'] ) {
			return null;
		}

		return [
			'tree'       => array_values( $body['tree'] ),
			'truncated'  => true === ( $body['truncated'] ?? false ),
			'fetched_at' => $now,
		];
	}

	/**
	 * Read a cached value.
	 *
	 * @param mixed $cached Transient value.
	 * @return array{tree: array<int, array<string, mixed>>, truncated: bool, fetched_at: int|null}|null Null on a miss.
	 */
	public static function read( mixed $cached ): ?array {
		if ( ! is_array( $cached ) || [] === $cached ) {
			return null;
		}

		if ( ! array_key_exists( 'tree', $cached ) ) {
			// A bare tree list, cached by a version before 1.2.0.
			return [
				'tree'       => array_values( $cached ),
				'truncated'  => false,
				'fetched_at' => null,
			];
		}

		if ( ! is_array( $cached['tree'] ) || [] === $cached['tree'] ) {
			return null;
		}

		return [
			'tree'       => $cached['tree'],
			'truncated'  => true === ( $cached['truncated'] ?? false ),
			'fetched_at' => isset( $cached['fetched_at'] ) && is_int( $cached['fetched_at'] ) ? $cached['fetched_at'] : null,
		];
	}
}
