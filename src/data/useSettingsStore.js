/**
 * WordPress dependencies
 */
import { useDispatch } from '@wordpress/data';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal dependencies
 */
import { api, errorMessage, fieldErrors } from './api';

/**
 * The lw_translate_options draft. Save sends only changed keys (never a
 * locked one), so every omitted key survives on the server. A
 * `400 lw_translate_invalid` keeps the draft and puts `data.fields` next to
 * each field; the server saved nothing.
 *
 * @param {Function} onSaved Runs after a successful save (the list depends on tone and locale).
 * @return {Object} Store: data { options, meta }, set, hasEdits, save, discard…
 */
export default function useSettingsStore( onSaved ) {
	const [ server, setServer ] = useState( null );
	const [ options, setOptions ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ errors, setErrors ] = useState( {} );
	const [ isSaving, setIsSaving ] = useState( false );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	const apply = useCallback( ( data ) => {
		setServer( data );
		setOptions( data.options );
		setErrors( {} );
	}, [] );

	const reload = useCallback( () => {
		setError( null );
		return api
			.settings()
			.then( apply, ( e ) => setError( errorMessage( e ) ) );
	}, [ apply ] );

	useEffect( () => {
		reload();
	}, [ reload ] );

	const locked = server?.meta.locked || [];
	const patch = options
		? Object.fromEntries(
				Object.keys( options )
					.filter(
						( key ) =>
							! locked.includes( key ) &&
							options[ key ] !== server.options[ key ]
					)
					.map( ( key ) => [ key, options[ key ] ] )
			)
		: {};
	const hasEdits = Object.keys( patch ).length > 0;

	const set = ( key, value ) => {
		setOptions( ( prev ) => ( { ...prev, [ key ]: value } ) );
		setErrors( ( prev ) => {
			if ( ! ( key in prev ) ) {
				return prev;
			}
			const next = { ...prev };
			delete next[ key ];
			return next;
		} );
	};

	const save = async () => {
		if ( ! hasEdits ) {
			return false;
		}
		setIsSaving( true );
		let ok = false;
		try {
			apply( await api.saveSettings( patch ) );
			ok = true;
			createSuccessNotice( __( 'Settings saved.', 'lw-translate' ), {
				type: 'snackbar',
			} );
			if ( onSaved && ( 'tone' in patch || 'locale' in patch ) ) {
				onSaved();
			}
		} catch ( e ) {
			const fields = fieldErrors( e );
			if ( fields ) {
				setErrors( fields );
			}
			createErrorNotice(
				fields
					? __(
							'Nothing was saved. Fix the highlighted fields and save again.',
							'lw-translate'
						)
					: errorMessage( e ),
				{ type: 'snackbar' }
			);
		}
		setIsSaving( false );
		return ok;
	};

	return {
		data: options ? { options, meta: server.meta } : null,
		saved: server?.options || {},
		isLoading: ! options && ! error,
		error,
		reload,
		isLocked: ( key ) => locked.includes( key ),
		errors,
		set,
		hasEdits,
		isSaving,
		discard: () => {
			setOptions( server.options );
			setErrors( {} );
		},
		save,
	};
}
