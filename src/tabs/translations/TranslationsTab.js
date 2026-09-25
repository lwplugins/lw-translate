/**
 * External dependencies
 */
import { DataTable, useTableState } from '@lwplugins/data-table';
import '@lwplugins/data-table/style.css';

/**
 * WordPress dependencies
 */
import {
	Notice,
	// Core has no stable ConfirmDialog yet (same as the sibling LW admins).
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalConfirmDialog as ConfirmDialog,
} from '@wordpress/components';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Callout from '../../components/Callout';
import LoadError from '../../components/LoadError';
import Section from '../../components/Section';
import { tableLabels } from '../../components/tableLabels';
import { errorMessage } from '../../data/api';
import SourceBar from './SourceBar';
import ViewChips from './ViewChips';
import { translationColumns } from './translationColumns';

/**
 * Confirmation text for deleting rows.
 *
 * @param {Array} rows Rows to delete.
 * @return {string} Question.
 */
const deleteQuestion = ( rows ) =>
	rows.length === 1
		? __( 'Delete this translation?', 'lw-translate' ) +
			' ' +
			rows[ 0 ].name
		: sprintf(
				/* translators: %d: number of translations. */
				_n(
					'Delete %d selected translation?',
					'Delete %d selected translations?',
					rows.length,
					'lw-translate'
				),
				rows.length
			);

/**
 * Translations: every installed plugin and theme that has a folder in the
 * repository, with install / update / delete per row or for a selection.
 *
 * @param {Object} props
 * @param {Object} props.list    useRemote() of the list.
 * @param {Object} props.actions useTranslationActions().
 */
export default function TranslationsTab( { list, actions } ) {
	const [ selected, setSelected ] = useState( [] );
	const [ pendingDelete, setPendingDelete ] = useState( null );
	const data = list.data;
	const rows = useMemo( () => data?.rows || [], [ data ] );
	const canInstall = !! data?.canInstall;
	const counts = data?.counts;

	// Rows touched by the last run: successes glow for a few seconds,
	// failures keep their reason until the next run.
	const [ showOk, setShowOk ] = useState( false );
	useEffect( () => {
		if ( ! actions.result ) {
			return undefined;
		}
		setShowOk( true );
		const timer = setTimeout( () => setShowOk( false ), 6000 );
		return () => clearTimeout( timer );
	}, [ actions.result ] );
	const marks = useMemo( () => {
		const map = {};
		( actions.result?.items || [] ).forEach( ( r ) => {
			if ( ! r.ok || showOk ) {
				map[ `${ r.type }:${ r.slug }` ] = {
					ok: r.ok,
					action: actions.result.action,
					message: r.message,
				};
			}
		} );
		return map;
	}, [ actions.result, showOk ] );

	const act = ( action, target, busyKey ) => {
		if ( action === 'delete' ) {
			setPendingDelete( { rows: target, busyKey } );
			return;
		}
		actions
			.run( 'install', target, busyKey )
			.then( () => setSelected( [] ) );
	};

	const columns = translationColumns( {
		canInstall,
		busy: actions.busy,
		marks,
		onAction: ( action, row ) =>
			act( action, [ row ], `${ action }:${ row.id }` ),
	} );
	const table = useTableState( rows, {
		searchFields: [ 'name', 'slug' ],
		columns,
		sort: { field: 'name', direction: 'asc' },
		perPage: 20,
	} );

	if ( list.error && ! data ) {
		return (
			<LoadError
				message={ errorMessage( list.error ) }
				onRetry={ () => list.reload() }
			/>
		);
	}

	return (
		<Section
			title={ __( 'Available translations', 'lw-translate' ) }
			description={ __(
				'Installed plugins and themes that have a translation in the repository.',
				'lw-translate'
			) }
		>
			<SourceBar
				data={ data }
				busy={ actions.busy }
				onRefresh={ actions.refresh }
			/>
			{ ( data?.warnings || [] ).map( ( warning ) => (
				<Notice
					key={ warning.code }
					status={ warning.level }
					isDismissible={ false }
				>
					{ warning.message }
				</Notice>
			) ) }
			{ list.error && data && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage( list.error ) }
				</Notice>
			) }
			{ data && ! canInstall && (
				<Callout tone="warning">
					{ __(
						'You can view the translations, but installing, updating or deleting them is not allowed for your account on this site (file changes may be disabled).',
						'lw-translate'
					) }
				</Callout>
			) }
			{ actions.progress && (
				<p className="lw-admin-hint" role="status">
					{ sprintf(
						/* translators: 1: items done, 2: items in total. */
						__( 'Working… %1$d of %2$d', 'lw-translate' ),
						actions.progress.done,
						actions.progress.total
					) }
				</p>
			) }
			<DataTable
				columns={ columns }
				table={ table }
				isLoading={ list.isLoading || actions.busy === 'refresh' }
				caption={ __( 'Translations', 'lw-translate' ) }
				toolbar={
					counts ? (
						<ViewChips table={ table } counts={ counts } />
					) : null
				}
				labels={ {
					...tableLabels(),
					search: __( 'Search by name or slug', 'lw-translate' ),
					empty: __(
						'No translation matches these filters.',
						'lw-translate'
					),
					emptyAll: __(
						'None of the installed plugins and themes has a translation in the repository for this tone and language.',
						'lw-translate'
					),
					selectAll: __(
						'Select all translations on this page',
						'lw-translate'
					),
				} }
				getRowId={ ( r ) => r.id }
				getRowLabel={ ( r ) => r.name }
				selection={
					canInstall ? { selected, onChange: setSelected } : undefined
				}
				bulkActions={
					canInstall
						? [
								{
									id: 'install',
									label: __(
										'Install/Update selected',
										'lw-translate'
									),
									isEligible: ( r ) =>
										r.status !== 'installed',
									onClick: ( eligible ) =>
										act( 'install', eligible, 'bulk' ),
								},
								{
									id: 'delete',
									label: __(
										'Delete selected',
										'lw-translate'
									),
									isDestructive: true,
									isEligible: ( r ) =>
										r.status !== 'not_installed',
									onClick: ( eligible ) =>
										act( 'delete', eligible, 'bulk' ),
								},
							]
						: undefined
				}
			/>
			<ConfirmDialog
				isOpen={ !! pendingDelete }
				confirmButtonText={ __( 'Delete', 'lw-translate' ) }
				onConfirm={ () => {
					const pending = pendingDelete;
					setPendingDelete( null );
					actions
						.run( 'delete', pending.rows, pending.busyKey )
						.then( () => setSelected( [] ) );
				} }
				onCancel={ () => setPendingDelete( null ) }
			>
				{ pendingDelete ? deleteQuestion( pendingDelete.rows ) : '' }
			</ConfirmDialog>
		</Section>
	);
}
