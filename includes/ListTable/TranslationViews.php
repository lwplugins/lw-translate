<?php
/**
 * Translation Views Trait.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\ListTable;

use LightweightPlugins\Translate\Translation\TranslationItem;

/**
 * Trait for views, filters, search and sorting.
 */
trait TranslationViews {

	/**
	 * Get views for filtering.
	 *
	 * @return array<string, string>
	 */
	protected function get_views(): array {
		$current = isset( $_GET['filter'] ) ? sanitize_text_field( wp_unslash( $_GET['filter'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$counts  = $this->get_status_counts();
		$url     = admin_url( 'admin.php?page=lw-translate' );

		$views = [
			'all' => sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
				esc_url( $url ),
				'all' === $current ? 'current' : '',
				esc_html__( 'All', 'lw-translate' ),
				$counts['all']
			),
		];

		$view_defs = [
			'plugins'       => __( 'Plugins', 'lw-translate' ),
			'themes'        => __( 'Themes', 'lw-translate' ),
			'updates'       => __( 'Updates Available', 'lw-translate' ),
			'not_installed' => __( 'Not Installed', 'lw-translate' ),
		];

		foreach ( $view_defs as $key => $label ) {
			if ( $counts[ $key ] > 0 ) {
				$views[ $key ] = sprintf(
					'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
					esc_url( add_query_arg( 'filter', $key, $url ) ),
					$key === $current ? 'current' : '',
					esc_html( $label ),
					$counts[ $key ]
				);
			}
		}

		return $views;
	}

	/**
	 * Apply view filters.
	 *
	 * @param array<TranslationItem> $items Items to filter.
	 * @return array<TranslationItem>
	 */
	private function apply_filters( array $items ): array {
		$filter = isset( $_GET['filter'] ) ? sanitize_text_field( wp_unslash( $_GET['filter'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return match ( $filter ) {
			'plugins'       => array_filter( $items, static fn( TranslationItem $i ) => 'plugin' === $i->type ),
			'themes'        => array_filter( $items, static fn( TranslationItem $i ) => 'theme' === $i->type ),
			'updates'       => array_filter( $items, static fn( TranslationItem $i ) => $i->has_update() ),
			'not_installed' => array_filter( $items, static fn( TranslationItem $i ) => ! $i->is_installed() ),
			default         => $items,
		};
	}

	/**
	 * Apply search filtering.
	 *
	 * @param array<TranslationItem> $items Items to search.
	 * @return array<TranslationItem>
	 */
	private function apply_search( array $items ): array {
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( empty( $search ) ) {
			return $items;
		}

		$search = strtolower( $search );

		return array_filter(
			$items,
			static fn( TranslationItem $i ) => str_contains( strtolower( $i->name ), $search )
				|| str_contains( strtolower( $i->slug ), $search )
		);
	}

	/**
	 * Apply sorting.
	 *
	 * @param array<TranslationItem> $items Items to sort.
	 * @return array<TranslationItem>
	 */
	private function apply_sort( array $items ): array {
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'name'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order   = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'asc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		usort(
			$items,
			static function ( TranslationItem $a, TranslationItem $b ) use ( $orderby, $order ) {
				$result = match ( $orderby ) {
					'type'       => strcmp( $a->type, $b->type ),
					'status'     => strcmp( $a->status, $b->status ),
					'local_date' => strcmp( $a->local_date, $b->local_date ),
					default      => strcasecmp( $a->name, $b->name ),
				};

				return 'desc' === $order ? -$result : $result;
			}
		);

		return $items;
	}

	/**
	 * Get counts for status views.
	 *
	 * @return array{all: int, plugins: int, themes: int, updates: int, not_installed: int}
	 */
	private function get_status_counts(): array {
		$counts = [
			'all'           => count( $this->all_items ),
			'plugins'       => 0,
			'themes'        => 0,
			'updates'       => 0,
			'not_installed' => 0,
		];

		foreach ( $this->all_items as $item ) {
			if ( 'plugin' === $item->type ) {
				++$counts['plugins'];
			} else {
				++$counts['themes'];
			}

			if ( $item->has_update() ) {
				++$counts['updates'];
			} elseif ( ! $item->is_installed() ) {
				++$counts['not_installed'];
			}
		}

		return $counts;
	}
}
