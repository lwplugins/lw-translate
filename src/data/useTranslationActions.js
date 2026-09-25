/**
 * WordPress dependencies
 */
import { useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal dependencies
 */
import { api, BATCH_SIZE, errorMessage } from './api';

const chunk = ( items, size ) =>
	Array.from( { length: Math.ceil( items.length / size ) }, ( _, index ) =>
		items.slice( index * size, index * size + size )
	);

/**
 * Install / update / delete / refresh. Items go to the server in batches
 * (so a long selection never runs into the PHP time limit); every answer
 * carries the fresh list, which replaces the table. The per-item results of
 * the whole run stay on screen until the next action or a dismiss.
 *
 * @param {Object} list useRemote() of the translations list.
 * @return {Object} { run, refresh, busy, result, progress, dismiss }.
 */
/**
 * Snackbar text for a finished run: what worked (by name) and what failed.
 *
 * @param {string}      action  install|delete.
 * @param {Array}       items   Per-item results with names.
 * @param {string|null} failure Request-level error.
 * @return {Object} { message, failed }.
 */
export function runSummary( action, items, failure ) {
	const ok = items.filter( ( r ) => r.ok );
	const bad = items.filter( ( r ) => ! r.ok );
	const names = ( list ) => list.map( ( r ) => r.name ).join( ', ' );
	const parts = [];

	if ( ok.length && action === 'install' ) {
		parts.push(
			sprintf(
				/* translators: 1: number of translations, 2: plugin/theme names. */
				_n(
					'%1$d translation installed: %2$s',
					'%1$d translations installed: %2$s',
					ok.length,
					'lw-translate'
				),
				ok.length,
				names( ok )
			)
		);
	} else if ( ok.length ) {
		parts.push(
			sprintf(
				/* translators: 1: number of translations, 2: plugin/theme names. */
				_n(
					'%1$d translation deleted: %2$s',
					'%1$d translations deleted: %2$s',
					ok.length,
					'lw-translate'
				),
				ok.length,
				names( ok )
			)
		);
	}
	if ( bad.length ) {
		parts.push(
			sprintf(
				/* translators: 1: number of failed items, 2: plugin/theme names. */
				_n(
					'%1$d failed: %2$s',
					'%1$d failed: %2$s',
					bad.length,
					'lw-translate'
				),
				bad.length,
				names( bad )
			)
		);
	}
	if ( failure ) {
		parts.push( failure );
	}

	return {
		message: parts.join( ' · ' ),
		failed: bad.length > 0 || !! failure,
	};
}

export default function useTranslationActions( list ) {
	const [ busy, setBusy ] = useState( '' );
	const [ result, setResult ] = useState( null );
	const [ progress, setProgress ] = useState( null );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	/**
	 * @param {string} action  install|delete.
	 * @param {Array}  rows    Rows to act on.
	 * @param {string} busyKey Row id or 'bulk' (which button spins).
	 */
	const run = async ( action, rows, busyKey ) => {
		if ( busy || ! rows.length ) {
			return;
		}
		const call = action === 'install' ? api.install : api.remove;
		const names = Object.fromEntries(
			rows.map( ( r ) => [ r.id, r.name ] )
		);
		const batches = chunk(
			rows.map( ( r ) => ( { type: r.type, slug: r.slug } ) ),
			BATCH_SIZE
		);
		const results = [];
		let failure = null;

		setBusy( busyKey );
		setResult( null );
		for ( let index = 0; index < batches.length; index++ ) {
			setProgress(
				batches.length > 1
					? { done: index * BATCH_SIZE, total: rows.length }
					: null
			);
			try {
				const data = await call( batches[ index ] );
				results.push( ...data.results );
				list.setData( data );
			} catch ( e ) {
				failure = errorMessage( e );
				break;
			}
		}
		setProgress( null );
		setBusy( '' );

		const named = results.map( ( r ) => ( {
			...r,
			name: names[ `${ r.type }:${ r.slug }` ] || r.slug,
		} ) );
		setResult( { action, items: named, failure } );

		const summary = runSummary( action, named, failure );
		if ( summary.failed ) {
			// Failures stay until dismissed; the failed rows say why.
			createErrorNotice( summary.message, {
				type: 'snackbar',
				explicitDismiss: true,
			} );
		} else {
			createSuccessNotice( summary.message, { type: 'snackbar' } );
		}
	};

	const refresh = async () => {
		if ( busy ) {
			return;
		}
		setBusy( 'refresh' );
		setResult( null );
		try {
			list.setData( await api.refresh() );
			createSuccessNotice( __( 'Cache cleared.', 'lw-translate' ), {
				type: 'snackbar',
			} );
		} catch ( e ) {
			createErrorNotice( errorMessage( e ), { type: 'snackbar' } );
		}
		setBusy( '' );
	};

	return {
		run,
		refresh,
		busy,
		result,
		progress,
		dismiss: () => setResult( null ),
	};
}
