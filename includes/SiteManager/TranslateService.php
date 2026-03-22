<?php
/**
 * Translate Service for LW Site Manager abilities.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\SiteManager;

use LightweightPlugins\Translate\Installer\FileInstaller;
use LightweightPlugins\Translate\Options;
use LightweightPlugins\Translate\Translation\Comparator;
use LightweightPlugins\Translate\Translation\TranslationItem;
use WP_Error;

/**
 * Executes translation abilities for the Site Manager.
 */
final class TranslateService {

	/**
	 * List all translations with their current status.
	 *
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function list_translations( array $input ): array|WP_Error {
		$items = Comparator::compare_all();

		if ( is_wp_error( $items ) ) {
			return $items;
		}

		$type_filter   = isset( $input['type'] ) ? sanitize_text_field( $input['type'] ) : 'all';
		$status_filter = isset( $input['status'] ) ? sanitize_text_field( $input['status'] ) : 'all';

		$translations = [];

		foreach ( $items as $item ) {
			if ( 'all' !== $type_filter && $item->type !== $type_filter ) {
				continue;
			}

			if ( 'all' !== $status_filter && $item->status !== $status_filter ) {
				continue;
			}

			$translations[] = self::item_to_array( $item );
		}

		return [
			'success'      => true,
			'translations' => $translations,
			'total'        => count( $translations ),
		];
	}

	/**
	 * Get LW Translate plugin options.
	 *
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>
	 */
	public static function get_options( array $input ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by ability callback interface.
		return [
			'success' => true,
			'options' => Options::get_all(),
		];
	}

	/**
	 * Install or update a translation for a single plugin or theme.
	 *
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function install_translation( array $input ): array|WP_Error {
		$slug = isset( $input['slug'] ) ? sanitize_text_field( $input['slug'] ) : '';
		$type = isset( $input['type'] ) ? sanitize_text_field( $input['type'] ) : '';

		if ( empty( $slug ) || empty( $type ) ) {
			return new WP_Error(
				'missing_params',
				__( 'Both slug and type are required.', 'lw-translate' ),
				[ 'status' => 400 ]
			);
		}

		if ( ! in_array( $type, [ 'plugin', 'theme' ], true ) ) {
			return new WP_Error(
				'invalid_type',
				__( 'Type must be plugin or theme.', 'lw-translate' ),
				[ 'status' => 400 ]
			);
		}

		$installer = new FileInstaller();
		$result    = $installer->install( $slug, $type );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		self::clear_comparison_cache();

		return [
			'success' => true,
			/* translators: 1: plugin/theme type, 2: slug */
			'message' => sprintf( __( 'Translation installed for %1$s %2$s.', 'lw-translate' ), $type, $slug ),
		];
	}

	/**
	 * Update all translations that have an available update.
	 *
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function update_translations( array $input ): array|WP_Error { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by ability callback interface.
		$items = Comparator::compare_all();

		if ( is_wp_error( $items ) ) {
			return $items;
		}

		$installer = new FileInstaller();
		$updated   = [];
		$failed    = [];

		foreach ( $items as $item ) {
			if ( ! $item->has_update() ) {
				continue;
			}

			$result = $installer->install( $item->slug, $item->type );

			if ( is_wp_error( $result ) ) {
				$failed[] = [
					'slug'    => $item->slug,
					'type'    => $item->type,
					'message' => $result->get_error_message(),
				];
			} else {
				$updated[] = $item->type . ':' . $item->slug;
			}
		}

		if ( ! empty( $updated ) ) {
			self::clear_comparison_cache();
		}

		return [
			'success' => true,
			'updated' => $updated,
			'failed'  => $failed,
			'total'   => count( $updated ),
		];
	}

	/**
	 * Convert a TranslationItem to a plain array.
	 *
	 * @param TranslationItem $item Translation item.
	 * @return array<string, mixed>
	 */
	private static function item_to_array( TranslationItem $item ): array {
		return [
			'slug'       => $item->slug,
			'name'       => $item->name,
			'type'       => $item->type,
			'status'     => $item->status,
			'file_count' => $item->file_count,
			'local_date' => $item->local_date,
		];
	}

	/**
	 * Clear comparison transient caches.
	 *
	 * @return void
	 */
	private static function clear_comparison_cache(): void {
		global $wpdb;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_lw_translate_compare_%',
				'_transient_timeout_lw_translate_compare_%'
			)
		);
	}
}
