<?php
/**
 * CLI Install/Delete trait.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\CLI;

use LightweightPlugins\Translate\Installer\FileInstaller;
use LightweightPlugins\Translate\Translation\CompareCache;
use LightweightPlugins\Translate\Translation\Comparator;
use LightweightPlugins\Translate\Translation\TranslationItem;
use WP_CLI;

/**
 * Provides install and delete subcommands for WP-CLI.
 */
trait InstallTrait {

	/**
	 * Install or update a translation.
	 *
	 * ## OPTIONS
	 *
	 * [<slug>]
	 * : Plugin or theme slug. Required unless --all is used.
	 *
	 * [--type=<type>]
	 * : Type of item. Options: plugin, theme. Default: plugin.
	 *
	 * [--all]
	 * : Install or update all available translations.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-translate install woocommerce
	 *     wp lw-translate install flavor --type=theme
	 *     wp lw-translate install --all
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function install( array $args, array $assoc_args ): void {
		$install_all = isset( $assoc_args['all'] );

		if ( $install_all ) {
			$this->install_all();
			return;
		}

		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Please specify a slug or use --all.' );
		}

		$slug = $args[0];
		$type = $assoc_args['type'] ?? 'plugin';

		$installer = new FileInstaller();
		$result    = $installer->install( $slug, $type );

		CompareCache::clear();

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		if ( ! empty( $result['skipped'] ) ) {
			WP_CLI::warning( 'Ignored files that are not valid translation files for this item: ' . implode( ', ', $result['skipped'] ) );
		}

		WP_CLI::success( "Translation installed for {$type}: {$slug}" );
	}

	/**
	 * Delete a translation.
	 *
	 * ## OPTIONS
	 *
	 * [<slug>]
	 * : Plugin or theme slug. Required unless --all is used.
	 *
	 * [--type=<type>]
	 * : Type of item. Options: plugin, theme. Default: plugin.
	 *
	 * [--all]
	 * : Delete all installed translations.
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-translate delete woocommerce
	 *     wp lw-translate delete flavor --type=theme
	 *     wp lw-translate delete --all --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function delete( array $args, array $assoc_args ): void {
		$delete_all = isset( $assoc_args['all'] );

		if ( $delete_all ) {
			WP_CLI::confirm( 'Delete ALL installed translations?', $assoc_args );
			$this->delete_all();
			return;
		}

		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Please specify a slug or use --all.' );
		}

		$slug = $args[0];
		$type = $assoc_args['type'] ?? 'plugin';

		$installer = new FileInstaller();
		$result    = $installer->delete( $slug, $type );

		CompareCache::clear();

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success( "Translation deleted for {$type}: {$slug}" );
	}

	/**
	 * Install or update all available translations.
	 *
	 * @return void
	 */
	private function install_all(): void {
		$items = Comparator::compare_all();

		if ( is_wp_error( $items ) ) {
			WP_CLI::error( $items->get_error_message() );
		}

		$updatable = array_filter(
			$items,
			static fn( TranslationItem $item ): bool => TranslationItem::STATUS_UP_TO_DATE !== $item->status
		);

		if ( empty( $updatable ) ) {
			WP_CLI::success( 'All translations are up to date.' );
			return;
		}

		$installer = new FileInstaller();
		BatchRunner::run( $updatable, [ $installer, 'install' ], 'Installing translations', 'Installed %d translation(s).' );
	}

	/**
	 * Delete all installed translations.
	 *
	 * @return void
	 */
	private function delete_all(): void {
		$items = Comparator::compare_all();

		if ( is_wp_error( $items ) ) {
			WP_CLI::error( $items->get_error_message() );
		}

		$installed = array_filter(
			$items,
			static fn( TranslationItem $item ): bool => $item->is_installed()
		);

		if ( empty( $installed ) ) {
			WP_CLI::warning( 'No installed translations found.' );
			return;
		}

		$installer = new FileInstaller();
		BatchRunner::run( $installed, [ $installer, 'delete' ], 'Deleting translations', 'Deleted %d translation(s).' );
	}
}
