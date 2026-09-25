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

		const failed = named.filter( ( r ) => ! r.ok ).length;
		if ( failure ) {
			createErrorNotice( failure, { type: 'snackbar' } );
		} else if ( ! failed ) {
			createSuccessNotice(
				action === 'install'
					? sprintf(
							/* translators: %d: number of translations. */
							_n(
								'%d translation installed.',
								'%d translations installed.',
								named.length,
								'lw-translate'
							),
							named.length
						)
					: sprintf(
							/* translators: %d: number of translations. */
							_n(
								'%d translation deleted.',
								'%d translations deleted.',
								named.length,
								'lw-translate'
							),
							named.length
						),
				{ type: 'snackbar' }
			);
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
