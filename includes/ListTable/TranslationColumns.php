<?php
/**
 * Translation Columns Trait.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\ListTable;

use LightweightPlugins\Translate\Translation\TranslationItem;

/**
 * Trait for rendering list table columns.
 */
trait TranslationColumns {

	/**
	 * Render the Name column.
	 *
	 * @param TranslationItem $item Translation item.
	 * @return string
	 */
	protected function column_name( TranslationItem $item ): string {
		return '<strong>' . esc_html( $item->name ) . '</strong>'
			. '<br><code>' . esc_html( $item->slug ) . '</code>';
	}

	/**
	 * Render the Type column.
	 *
	 * @param TranslationItem $item Translation item.
	 * @return string
	 */
	protected function column_type( TranslationItem $item ): string {
		$label = 'theme' === $item->type
			? __( 'Theme', 'lw-translate' )
			: __( 'Plugin', 'lw-translate' );

		$class = 'theme' === $item->type ? 'lw-badge-theme' : 'lw-badge-plugin';

		return '<span class="lw-translate-badge ' . esc_attr( $class ) . '">'
			. esc_html( $label ) . '</span>';
	}

	/**
	 * Render the Status column.
	 *
	 * @param TranslationItem $item Translation item.
	 * @return string
	 */
	protected function column_status( TranslationItem $item ): string {
		switch ( $item->status ) {
			case TranslationItem::STATUS_UP_TO_DATE:
				return '<span class="lw-translate-status lw-status-ok" title="'
					. esc_attr__( 'Up to date', 'lw-translate' ) . '">&#10004;</span>';

			case TranslationItem::STATUS_UPDATE:
				return '<span class="lw-translate-status lw-status-update" title="'
					. esc_attr__( 'Update available', 'lw-translate' ) . '">&#8635;</span>';

			default:
				return '<span class="lw-translate-status lw-status-none" title="'
					. esc_attr__( 'Not installed', 'lw-translate' ) . '">&mdash;</span>';
		}
	}

	/**
	 * Render the Files column.
	 *
	 * @param TranslationItem $item Translation item.
	 * @return string
	 */
	protected function column_files( TranslationItem $item ): string {
		return (string) $item->file_count;
	}

	/**
	 * Render the Local Date column.
	 *
	 * @param TranslationItem $item Translation item.
	 * @return string
	 */
	protected function column_local_date( TranslationItem $item ): string {
		return ! empty( $item->local_date ) ? esc_html( $item->local_date ) : '&mdash;';
	}
}
