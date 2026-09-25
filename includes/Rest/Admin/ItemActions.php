<?php
/**
 * Runs install and delete over a list of items.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Rest\Admin;

use LightweightPlugins\Translate\Installer\SkippedFilesNotice;
use WP_Error;

/**
 * One honest result per item: every item is attempted, and a failure of
 * one never hides the outcome of the others.
 */
final class ItemActions {

	/**
	 * Installer: fn( string $slug, string $type ): array{skipped: array<int, string>}|WP_Error.
	 *
	 * @var callable
	 */
	private $install;

	/**
	 * Remover: fn( string $slug, string $type ): array{deleted: array<int, string>}|WP_Error.
	 *
	 * @var callable
	 */
	private $delete;

	/**
	 * Constructor.
	 *
	 * @param callable $install Installer.
	 * @param callable $delete  Remover.
	 */
	public function __construct( callable $install, callable $delete ) {
		$this->install = $install;
		$this->delete  = $delete;
	}

	/**
	 * Install or update each item.
	 *
	 * @param array<int, array{type: string, slug: string}> $items Items.
	 * @return array<int, array{type: string, slug: string, ok: bool, message: string, skipped: array<int, string>}>
	 */
	public function install( array $items ): array {
		$results = [];

		foreach ( $items as $item ) {
			$outcome = call_user_func( $this->install, $item['slug'], $item['type'] );
			$ok      = ! $outcome instanceof WP_Error;
			$skipped = $ok ? array_values( (array) ( $outcome['skipped'] ?? [] ) ) : [];

			$results[] = [
				'type'    => $item['type'],
				'slug'    => $item['slug'],
				'ok'      => $ok,
				'message' => $ok
					? trim( __( 'Translation installed successfully.', 'lw-translate' ) . ' ' . SkippedFilesNotice::message( $skipped ) )
					: $outcome->get_error_message(),
				'skipped' => $skipped,
			];
		}

		return $results;
	}

	/**
	 * Delete each item's files.
	 *
	 * @param array<int, array{type: string, slug: string}> $items Items.
	 * @return array<int, array{type: string, slug: string, ok: bool, message: string, deleted: array<int, string>}>
	 */
	public function delete( array $items ): array {
		$results = [];

		foreach ( $items as $item ) {
			$outcome = call_user_func( $this->delete, $item['slug'], $item['type'] );
			$ok      = ! $outcome instanceof WP_Error;
			$data    = $ok ? $outcome : $outcome->get_error_data();

			$results[] = [
				'type'    => $item['type'],
				'slug'    => $item['slug'],
				'ok'      => $ok,
				'message' => $ok ? __( 'Translation deleted.', 'lw-translate' ) : $outcome->get_error_message(),
				'deleted' => is_array( $data ) ? array_values( (array) ( $data['deleted'] ?? [] ) ) : [],
			];
		}

		return $results;
	}
}
