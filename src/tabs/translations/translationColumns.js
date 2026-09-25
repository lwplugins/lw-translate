/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { Icon, external } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import StatusBadge from '../../components/StatusBadge';
import TranslationStatus, {
	statusLabel,
} from '../../components/TranslationStatus';
import { typeLabel } from '../../data/format';

// Update first, then not installed, then up to date.
const STATUS_ORDER = { update: 0, not_installed: 1, installed: 2 };

/**
 * Row action buttons: Install (not installed), Update + Delete (update
 * available), Delete (up to date).
 *
 * @param {Object}   props
 * @param {Object}   props.row      Row.
 * @param {string}   props.busy     Busy key ('' idle).
 * @param {Function} props.onAction ( action, row ).
 */
function RowActions( { row, busy, onAction } ) {
	const disabled = !! busy;
	const install = (
		<Button
			size="compact"
			variant="primary"
			isBusy={ busy === `install:${ row.id }` }
			disabled={ disabled }
			accessibleWhenDisabled
			label={
				row.status === 'update'
					? sprintf(
							/* translators: %s: plugin or theme name. */
							__( 'Update the %s translation', 'lw-translate' ),
							row.name
						)
					: sprintf(
							/* translators: %s: plugin or theme name. */
							__( 'Install the %s translation', 'lw-translate' ),
							row.name
						)
			}
			showTooltip={ false }
			onClick={ () => onAction( 'install', row ) }
		>
			{ row.status === 'update'
				? __( 'Update', 'lw-translate' )
				: __( 'Install', 'lw-translate' ) }
		</Button>
	);
	const remove = (
		<Button
			size="compact"
			variant="secondary"
			isDestructive
			isBusy={ busy === `delete:${ row.id }` }
			disabled={ disabled }
			accessibleWhenDisabled
			label={ sprintf(
				/* translators: %s: plugin or theme name. */
				__( 'Delete the %s translation', 'lw-translate' ),
				row.name
			) }
			showTooltip={ false }
			onClick={ () => onAction( 'delete', row ) }
		>
			{ __( 'Delete', 'lw-translate' ) }
		</Button>
	);

	return (
		<span className="lw-admin-inline lw-translate-actions">
			{ row.status !== 'installed' && install }
			{ row.status !== 'not_installed' && remove }
		</span>
	);
}

/**
 * What the last run did to this row: a short "just installed/deleted" tag,
 * or the failure reason. The row itself is tinted through CSS :has().
 *
 * @param {Object}      props
 * @param {Object|null} props.mark { ok, action, message } or undefined.
 */
function RowMark( { mark } ) {
	if ( ! mark ) {
		return null;
	}
	if ( ! mark.ok ) {
		return (
			<span className="lw-translate-mark is-failed" role="alert">
				{ mark.message || __( 'Failed', 'lw-translate' ) }
			</span>
		);
	}
	return (
		<span className="lw-translate-mark is-ok">
			{ mark.action === 'install'
				? __( 'just installed', 'lw-translate' )
				: __( 'just deleted', 'lw-translate' ) }
		</span>
	);
}

/**
 * Columns of the translations table.
 *
 * @param {Object}   args
 * @param {boolean}  args.canInstall Whether actions are allowed.
 * @param {string}   args.busy       Busy key.
 * @param {Object}   args.marks      Row id → { ok, action, message } of the last run.
 * @param {Function} args.onAction   ( action, row ).
 */
export function translationColumns( {
	canInstall,
	busy,
	marks = {},
	onAction,
} ) {
	const columns = [
		{
			id: 'name',
			label: __( 'Name', 'lw-translate' ),
			sortable: true,
			defaultSortDirection: 'asc',
			searchValue: ( r ) => `${ r.name } ${ r.slug }`,
			render: ( r ) => (
				<span className="lw-admin-stack">
					<span className="lw-admin-inline">
						<strong>{ r.name }</strong>
						<RowMark mark={ marks[ r.id ] } />
					</span>
					<span className="lw-admin-inline lw-translate-slug">
						<code className="lw-admin-code">{ r.slug }</code>
						{ r.remoteUrl && (
							<a
								href={ r.remoteUrl }
								target="_blank"
								rel="noopener noreferrer"
								className="lw-translate-source"
							>
								{ __( 'Source', 'lw-translate' ) }
								<Icon icon={ external } size={ 14 } />
								<span className="screen-reader-text">
									{ __(
										'(opens in a new tab)',
										'lw-translate'
									) }
								</span>
							</a>
						) }
					</span>
				</span>
			),
		},
		{
			id: 'type',
			label: __( 'Type', 'lw-translate' ),
			sortable: true,
			sortValue: ( r ) => typeLabel( r.type ),
			render: ( r ) => (
				<StatusBadge status={ r.type === 'theme' ? 'info' : 'idle' }>
					{ typeLabel( r.type ) }
				</StatusBadge>
			),
		},
		{
			id: 'status',
			label: __( 'Status', 'lw-translate' ),
			sortable: true,
			sortValue: ( r ) => STATUS_ORDER[ r.status ],
			searchValue: ( r ) => statusLabel( r.status ),
			render: ( r ) => <TranslationStatus status={ r.status } />,
		},
		{
			id: 'files',
			label: __( 'Files', 'lw-translate' ),
			sortable: true,
			align: 'end',
			render: ( r ) => (
				<span title={ r.remoteFiles.join( '\n' ) }>
					{ String( r.files ) }
				</span>
			),
		},
		{
			id: 'localDate',
			label: __( 'Local Date', 'lw-translate' ),
			sortable: true,
			render: ( r ) =>
				r.localDate ? (
					<span className="lw-admin-nowrap">{ r.localDate }</span>
				) : (
					<span className="lw-admin-muted">
						<span aria-hidden="true">—</span>
						<span className="screen-reader-text">
							{ __( 'None', 'lw-translate' ) }
						</span>
					</span>
				),
		},
	];

	if ( canInstall ) {
		columns.push( {
			id: 'actions',
			label: __( 'Actions', 'lw-translate' ),
			align: 'end',
			render: ( r ) => (
				<RowActions row={ r } busy={ busy } onAction={ onAction } />
			),
		} );
	}

	return columns;
}
