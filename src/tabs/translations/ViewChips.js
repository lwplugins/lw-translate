/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { Icon, check } from '@wordpress/icons';

/**
 * Chip label with its count: "Plugins (3)".
 *
 * @param {string} label Label.
 * @param {number} count Count.
 * @return {string} Chip label.
 */
const withCount = ( label, count ) =>
	sprintf(
		/* translators: 1: filter name, 2: number of items. */
		__( '%1$s (%2$d)', 'lw-translate' ),
		label,
		count
	);

/**
 * One-choice view chips with counts — All, Plugins, Themes, Updates
 * available, Not installed — driving the table's `views` filter. Styled
 * like the data table's own chips (lw-table__chip).
 *
 * @param {Object} props
 * @param {Object} props.table  useTableState() result.
 * @param {Object} props.counts Counts per view.
 */
export default function ViewChips( { table, counts } ) {
	const current = ( table.filters.views || [] )[ 0 ] || '';
	const views = [
		{ value: '', label: __( 'All', 'lw-translate' ), count: counts.all },
		{
			value: 'plugin',
			label: __( 'Plugins', 'lw-translate' ),
			count: counts.plugin,
		},
		{
			value: 'theme',
			label: __( 'Themes', 'lw-translate' ),
			count: counts.theme,
		},
		{
			value: 'update',
			label: __( 'Updates available', 'lw-translate' ),
			count: counts.update,
		},
		{
			value: 'not_installed',
			label: __( 'Not installed', 'lw-translate' ),
			count: counts.notInstalled,
		},
	];

	return (
		<div
			className="lw-table__chips"
			role="group"
			aria-label={ __( 'Show', 'lw-translate' ) }
		>
			{ views.map( ( view ) => {
				const pressed = view.value === current;
				return (
					<button
						key={ view.value || 'all' }
						type="button"
						className="lw-table__chip"
						aria-pressed={ pressed }
						onClick={ () =>
							table.setFilter(
								'views',
								view.value ? [ view.value ] : []
							)
						}
					>
						{ pressed && <Icon icon={ check } size={ 16 } /> }
						{ withCount( view.label, view.count ) }
					</button>
				);
			} ) }
		</div>
	);
}
