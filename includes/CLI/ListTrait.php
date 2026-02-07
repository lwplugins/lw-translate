<?php
/**
 * CLI List trait.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\CLI;

use LightweightPlugins\Translate\Translation\Comparator;
use LightweightPlugins\Translate\Translation\TranslationItem;
use WP_CLI;
use WP_CLI\Utils;

/**
 * Provides the list subcommand for WP-CLI.
 */
trait ListTrait {

	/**
	 * List available translations.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Filter by status. Options: all, update, up_to_date, not_installed. Default: all.
	 *
	 * [--type=<type>]
	 * : Filter by type. Options: plugin, theme.
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, csv, yaml, count. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-translate list
	 *     wp lw-translate list --status=update
	 *     wp lw-translate list --type=plugin
	 *     wp lw-translate list --format=json
	 *
	 * @subcommand list
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function list_translations( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
		$items = Comparator::compare_all();

		if ( is_wp_error( $items ) ) {
			WP_CLI::error( $items->get_error_message() );
		}

		$status = $assoc_args['status'] ?? 'all';
		$type   = $assoc_args['type'] ?? '';
		$format = $assoc_args['format'] ?? 'table';

		$items = $this->filter_items( $items, $status, $type );
		$rows  = $this->items_to_rows( $items );

		if ( empty( $rows ) ) {
			WP_CLI::warning( 'No translations found matching the criteria.' );
			return;
		}

		Utils\format_items(
			$format,
			$rows,
			[ 'slug', 'name', 'type', 'status', 'files', 'local_date' ]
		);
	}

	/**
	 * Filter translation items by status and type.
	 *
	 * @param array<TranslationItem> $items  Translation items.
	 * @param string                 $status Status filter.
	 * @param string                 $type   Type filter.
	 * @return array<TranslationItem>
	 */
	private function filter_items( array $items, string $status, string $type ): array {
		if ( 'all' !== $status ) {
			$items = array_filter(
				$items,
				static fn( TranslationItem $item ): bool => $item->status === $status
			);
		}

		if ( '' !== $type ) {
			$items = array_filter(
				$items,
				static fn( TranslationItem $item ): bool => $item->type === $type
			);
		}

		return array_values( $items );
	}

	/**
	 * Convert TranslationItem objects to row arrays.
	 *
	 * @param array<TranslationItem> $items Translation items.
	 * @return array<array<string, string|int>>
	 */
	private function items_to_rows( array $items ): array {
		return array_map(
			static fn( TranslationItem $item ): array => [
				'slug'       => $item->slug,
				'name'       => $item->name,
				'type'       => $item->type,
				'status'     => $item->status,
				'files'      => $item->file_count,
				'local_date' => '' !== $item->local_date ? $item->local_date : '-',
			],
			$items
		);
	}
}
