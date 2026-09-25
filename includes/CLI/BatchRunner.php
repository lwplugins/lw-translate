<?php
/**
 * CLI Batch Runner class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\CLI;

use LightweightPlugins\Translate\Translation\CompareCache;
use LightweightPlugins\Translate\Translation\TranslationItem;
use WP_CLI;
use WP_Error;

/**
 * Runs install or delete over many items with a progress bar: a failing
 * item is reported and counted, the others go on, and the comparison cache
 * is cleared at the end.
 */
final class BatchRunner {

	/**
	 * Run the action over the items.
	 *
	 * @param array<TranslationItem> $items  Items.
	 * @param callable               $action fn( string $slug, string $type ): mixed|WP_Error.
	 * @param string                 $label  Progress bar label.
	 * @param string                 $done   Success line, "%d" = items that worked.
	 * @return void
	 */
	public static function run( array $items, callable $action, string $label, string $done ): void {
		$progress = WP_CLI\Utils\make_progress_bar( $label, count( $items ) );
		$errors   = 0;

		foreach ( $items as $item ) {
			$result = call_user_func( $action, $item->slug, $item->type );

			if ( $result instanceof WP_Error ) {
				WP_CLI::warning( "{$item->slug}: " . $result->get_error_message() );
				++$errors;
			}

			$progress->tick();
		}

		$progress->finish();
		CompareCache::clear();

		WP_CLI::success( sprintf( $done, count( $items ) - $errors ) . ( $errors > 0 ? " {$errors} error(s)." : '' ) );
	}
}
