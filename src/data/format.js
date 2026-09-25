/**
 * Display helpers. Every unit and word goes through __() / _n().
 */
/**
 * WordPress dependencies
 */
import { humanTimeDiff } from '@wordpress/date';
import { __, _n, sprintf } from '@wordpress/i18n';

const HOUR = 3600;
const DAY = 86400;

/**
 * Relative past time in the site locale ("12 minutes ago"); humanTimeDiff
 * carries WordPress' own translated "ago".
 *
 * @param {number} ts Unix seconds.
 * @return {string} Label.
 */
export const ago = ( ts ) => humanTimeDiff( ts * 1000 );

/**
 * A cache lifetime in words: "12 hours", "2 days", "1 day 6 hours".
 *
 * @param {number} seconds Seconds.
 * @return {string} Label, '' for an invalid value.
 */
export function lifetimeLabel( seconds ) {
	const s = Math.floor( Number( seconds ) );
	if ( ! Number.isFinite( s ) || s <= 0 ) {
		return '';
	}
	const days = Math.floor( s / DAY );
	const hours = Math.floor( ( s % DAY ) / HOUR );
	const minutes = Math.floor( ( s % HOUR ) / 60 );
	const parts = [];
	if ( days ) {
		parts.push(
			sprintf(
				/* translators: %d: number of days. */
				_n( '%d day', '%d days', days, 'lw-translate' ),
				days
			)
		);
	}
	if ( hours ) {
		parts.push(
			sprintf(
				/* translators: %d: number of hours. */
				_n( '%d hour', '%d hours', hours, 'lw-translate' ),
				hours
			)
		);
	}
	if ( minutes && ! days ) {
		parts.push(
			sprintf(
				/* translators: %d: number of minutes. */
				_n( '%d minute', '%d minutes', minutes, 'lw-translate' ),
				minutes
			)
		);
	}
	return parts.join( ' ' ) || __( 'less than a minute', 'lw-translate' );
}

/**
 * Translated item type.
 *
 * @param {string} type plugin|theme.
 * @return {string} Label.
 */
export const typeLabel = ( type ) =>
	type === 'theme'
		? __( 'Theme', 'lw-translate' )
		: __( 'Plugin', 'lw-translate' );

/**
 * Translated tone.
 *
 * @param {string} tone formal|informal.
 * @return {string} Label.
 */
export const toneLabel = ( tone ) =>
	tone === 'formal'
		? __( 'Formal', 'lw-translate' )
		: __( 'Informal', 'lw-translate' );
