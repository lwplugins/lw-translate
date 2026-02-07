<?php
/**
 * Translation List Table.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\ListTable;

use LightweightPlugins\Translate\Translation\Comparator;
use LightweightPlugins\Translate\Translation\TranslationItem;
use WP_List_Table;

// Load WP_List_Table if not loaded.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Displays translations in a WP_List_Table.
 */
final class TranslationListTable extends WP_List_Table {

	use TranslationColumns;
	use TranslationViews;

	/**
	 * All items before filtering.
	 *
	 * @var array<TranslationItem>
	 */
	private array $all_items = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			[
				'singular' => 'translation',
				'plural'   => 'translations',
				'ajax'     => false,
			]
		);
	}

	/**
	 * Get columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return [
			'cb'         => '<input type="checkbox" />',
			'name'       => __( 'Name', 'lw-translate' ),
			'type'       => __( 'Type', 'lw-translate' ),
			'status'     => __( 'Status', 'lw-translate' ),
			'files'      => __( 'Files', 'lw-translate' ),
			'local_date' => __( 'Local Date', 'lw-translate' ),
			'actions'    => __( 'Actions', 'lw-translate' ),
		];
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	protected function get_sortable_columns(): array {
		return [
			'name'       => [ 'name', false ],
			'type'       => [ 'type', false ],
			'status'     => [ 'status', false ],
			'local_date' => [ 'local_date', false ],
		];
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions(): array {
		return [
			'install' => __( 'Install/Update Selected', 'lw-translate' ),
			'delete'  => __( 'Delete Selected', 'lw-translate' ),
		];
	}

	/**
	 * Checkbox column.
	 *
	 * @param TranslationItem $item Translation item.
	 * @return string
	 */
	protected function column_cb( $item ): string { // phpcs:ignore Squiz.Commenting.FunctionComment.ScalarTypeHintMissing
		return '<input type="checkbox" name="translations[]" value="'
			. esc_attr( $item->type . ':' . $item->slug ) . '" />';
	}

	/**
	 * Actions column.
	 *
	 * @param TranslationItem $item Translation item.
	 * @return string
	 */
	protected function column_actions( TranslationItem $item ): string {
		$data = 'data-slug="' . esc_attr( $item->slug ) . '" '
			. 'data-type="' . esc_attr( $item->type ) . '"';

		switch ( $item->status ) {
			case TranslationItem::STATUS_NOT_INSTALLED:
				return '<button type="button" class="button button-primary lw-translate-install" '
					. $data . '>' . esc_html__( 'Install', 'lw-translate' ) . '</button>';

			case TranslationItem::STATUS_UPDATE:
				return '<button type="button" class="button button-primary lw-translate-install" '
					. $data . '>' . esc_html__( 'Update', 'lw-translate' ) . '</button>'
					. ' <button type="button" class="button lw-translate-delete" '
					. $data . '>' . esc_html__( 'Delete', 'lw-translate' ) . '</button>';

			default:
				return '<span class="lw-translate-uptodate">'
					. esc_html__( 'Up to date', 'lw-translate' ) . '</span>'
					. ' <button type="button" class="button lw-translate-delete" '
					. $data . '>' . esc_html__( 'Delete', 'lw-translate' ) . '</button>';
		}
	}

	/**
	 * Default column rendering.
	 *
	 * @param TranslationItem $item        Translation item.
	 * @param string          $column_name Column name.
	 * @return string
	 */
	protected function column_default( $item, $column_name ): string { // phpcs:ignore Squiz.Commenting.FunctionComment.ScalarTypeHintMissing
		return '';
	}

	/**
	 * Prepare items for display.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$this->_column_headers = [
			$this->get_columns(),
			[],
			$this->get_sortable_columns(),
		];

		$result = Comparator::compare_all();

		if ( is_wp_error( $result ) ) {
			$this->items = [];
			return;
		}

		$this->all_items = $result;
		$items           = $this->apply_filters( $result );
		$items           = $this->apply_search( $items );
		$items           = $this->apply_sort( $items );

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$total_items  = count( $items );

		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			]
		);

		$this->items = array_slice( $items, ( $current_page - 1 ) * $per_page, $per_page );
	}
}
