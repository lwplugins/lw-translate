/**
 * Every REST call the admin makes, in one place (lw-translate/v1, prefix
 * /admin). Responses go through ./shapes before the UI reads them, so a
 * backend shape change is a one-file fix there.
 */
/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NAMESPACE } from './boot';
import { toSettings, toTranslations } from './shapes';

const path = ( route ) => `/${ NAMESPACE }/admin${ route }`;
const get = ( route ) => apiFetch( { path: path( route ) } );
const send = ( route, data ) =>
	apiFetch( { path: path( route ), method: 'POST', data } );

// Most items one install/delete request may carry (ItemsInput::MAX_ITEMS).
export const BATCH_SIZE = 10;

export const api = {
	// Settings: GET → { options, meta }; POST any subset (atomic) → same shape.
	settings: () => get( '/settings' ).then( toSettings ),
	saveSettings: ( patch ) => send( '/settings', patch ).then( toSettings ),

	// List: { rows, counts, warnings, cache, source, canInstall }.
	translations: () => get( '/translations' ).then( toTranslations ),
	// Install/update and delete take [ { type, slug } ] (≤ BATCH_SIZE) and
	// answer with the per-item results plus the fresh list.
	install: ( items ) =>
		send( '/translations/install', { items } ).then( toTranslations ),
	remove: ( items ) =>
		send( '/translations/delete', { items } ).then( toTranslations ),
	// Drops the cached repository tree and comparison; answers the fresh list.
	refresh: () => send( '/translations/refresh' ).then( toTranslations ),
};

/**
 * Human message of a failed request.
 *
 * @param {Object} error apiFetch rejection.
 * @return {string} Message.
 */
export const errorMessage = ( error ) =>
	error?.message ||
	__(
		'That did not work. Please reload the page and try again.',
		'lw-translate'
	);

/**
 * Per-field validation errors of a `400 lw_translate_invalid` response.
 *
 * @param {Object} error apiFetch rejection.
 * @return {Object|null} { key: [ messages ] } or null.
 */
export function fieldErrors( error ) {
	const fields = error?.data?.fields;
	if ( ! fields || typeof fields !== 'object' ) {
		return null;
	}
	return Object.fromEntries(
		Object.entries( fields ).map( ( [ key, messages ] ) => [
			key,
			( Array.isArray( messages ) ? messages : [ messages ] ).map(
				String
			),
		] )
	);
}
