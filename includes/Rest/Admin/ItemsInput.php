<?php
/**
 * Items input parser.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Rest\Admin;

use LightweightPlugins\Translate\Installer\FileNamePolicy;

/**
 * Parses the {items: [{type, slug}]} body of the install and delete routes.
 */
final class ItemsInput {

	/**
	 * Most items one request may carry (the admin sends bulk actions in
	 * batches, so a long list never runs into max_execution_time).
	 */
	public const MAX_ITEMS = 10;

	/**
	 * Item types.
	 */
	public const TYPES = [ 'plugin', 'theme' ];

	/**
	 * Parse the raw items list.
	 *
	 * Duplicates are dropped. Any malformed item refuses the whole request.
	 *
	 * @param mixed $raw Raw "items" value.
	 * @return array{items: array<int, array{type: string, slug: string}>, error: string} "error" is '' when valid.
	 */
	public static function parse( mixed $raw ): array {
		if ( ! is_array( $raw ) || [] === $raw ) {
			return self::fail( __( 'No items selected.', 'lw-translate' ) );
		}

		$items = [];

		foreach ( array_values( $raw ) as $entry ) {
			$type = is_array( $entry ) && is_string( $entry['type'] ?? null ) ? $entry['type'] : '';
			$slug = is_array( $entry ) && is_string( $entry['slug'] ?? null ) ? $entry['slug'] : '';

			if ( ! in_array( $type, self::TYPES, true ) || ! FileNamePolicy::is_valid_slug( $slug ) ) {
				return self::fail( __( 'Each item needs a type (plugin or theme) and a valid slug.', 'lw-translate' ) );
			}

			$items[ $type . ':' . $slug ] = [
				'type' => $type,
				'slug' => $slug,
			];
		}

		if ( count( $items ) > self::MAX_ITEMS ) {
			/* translators: %d: maximum number of items */
			return self::fail( sprintf( __( 'Send at most %d items per request.', 'lw-translate' ), self::MAX_ITEMS ) );
		}

		return [
			'items' => array_values( $items ),
			'error' => '',
		];
	}

	/**
	 * A refused request.
	 *
	 * @param string $message Reason.
	 * @return array{items: array<int, array{type: string, slug: string}>, error: string}
	 */
	private static function fail( string $message ): array {
		return [
			'items' => [],
			'error' => $message,
		];
	}
}
