/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Translated UI strings for `@lwplugins/data-table` (it has no text domain).
 *
 * @return {Object} Labels.
 */
export function tableLabels() {
	return {
		search: __( 'Search', 'lw-translate' ),
		filter: __( 'Filter', 'lw-translate' ),
		clear: __( 'Clear', 'lw-translate' ),
		clearAll: __( 'Clear all filters', 'lw-translate' ),
		all: __( 'All', 'lw-translate' ),
		empty: __( 'No entries match these filters.', 'lw-translate' ),
		emptyAll: __( 'Nothing here yet.', 'lw-translate' ),
		loading: __( 'Loading…', 'lw-translate' ),
		previous: __( 'Previous page', 'lw-translate' ),
		next: __( 'Next page', 'lw-translate' ),
		perPage: __( 'Rows per page', 'lw-translate' ),
		selectAll: __( 'Select all rows on this page', 'lw-translate' ),
		clearSelection: __( 'Clear selection', 'lw-translate' ),
		bulkActions: __( 'Bulk actions', 'lw-translate' ),
		entries: ( n ) =>
			sprintf(
				/* translators: %d: number of rows. */ _n(
					'%d entry',
					'%d entries',
					n,
					'lw-translate'
				),
				n
			),
		results: ( n ) =>
			sprintf(
				/* translators: %d: number of results. */ _n(
					'%d result',
					'%d results',
					n,
					'lw-translate'
				),
				n
			),
		page: ( p, t ) =>
			sprintf(
				/* translators: 1: current page, 2: total pages. */ __(
					'Page %1$d of %2$d',
					'lw-translate'
				),
				p,
				t
			),
		selectRow: ( label ) =>
			sprintf(
				/* translators: %s: row name. */ __(
					'Select: %s',
					'lw-translate'
				),
				label
			),
		selected: ( n, onPage ) =>
			n === onPage
				? sprintf(
						/* translators: %d: number of selected rows. */
						_n( '%d selected', '%d selected', n, 'lw-translate' ),
						n
					)
				: sprintf(
						/* translators: 1: selected rows, 2: of those, on this page. */
						__(
							'%1$d selected, %2$d on this page',
							'lw-translate'
						),
						n,
						onPage
					),
		eligible: ( e, n ) =>
			sprintf(
				/* translators: 1: rows the action applies to, 2: selected rows on this page. */ __(
					'applies to %1$d of %2$d',
					'lw-translate'
				),
				e,
				n
			),
	};
}
