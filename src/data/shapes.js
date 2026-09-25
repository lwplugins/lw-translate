/**
 * Response adapters: the ONLY place that knows the backend's field names
 * (lw-translate/v1 admin routes — includes/Rest/Admin/*). The UI reads the
 * camelCase objects built here.
 */
/**
 * Internal dependencies
 */
import { DOCS_URL } from './boot';

const obj = ( value ) =>
	value && typeof value === 'object' && ! Array.isArray( value ) ? value : {};
const list = ( value ) => ( Array.isArray( value ) ? value : [] );
const num = ( value ) => Number( value ) || 0;
const numOrNull = ( value ) =>
	value === null || value === undefined ? null : Number( value ) || 0;
const str = ( value ) =>
	value === null || value === undefined ? '' : String( value );
const oneOf = ( value, allowed, fallback ) =>
	allowed.includes( value ) ? value : fallback;
const strings = ( value ) => list( value ).map( String );

export const TYPES = [ 'plugin', 'theme' ];
export const STATUSES = [ 'installed', 'update', 'not_installed' ];
export const LEVELS = [ 'error', 'warning', 'info' ];

/**
 * { repo, branch, url } of the translation repository.
 *
 * @param {Object} data Raw source.
 * @return {Object} Source.
 */
const toSource = ( data ) => {
	const source = obj( data );
	return {
		repo: str( source.repo ),
		branch: str( source.branch ),
		url: str( source.url ),
		tone: str( source.tone ),
		locale: str( source.locale ),
	};
};

// Settings ------------------------------------------------------------------

/**
 * GET/POST /admin/settings → { options, meta }.
 *
 * @param {Object} data Response.
 * @return {Object} { options, meta }.
 */
export function toSettings( data ) {
	const meta = obj( data?.meta );
	const options = obj( data?.options );
	const lockedConstants = Object.fromEntries(
		Object.entries( obj( meta.locked ) ).map( ( [ k, v ] ) => [
			k,
			String( v ),
		] )
	);

	return {
		options: {
			tone: str( options.tone ),
			locale: str( options.locale ),
			cache_ttl: num( options.cache_ttl ),
		},
		meta: {
			locked: Object.keys( lockedConstants ),
			lockedConstants,
			tones: strings( meta.tones ),
			locales: list( meta.locales ).map( ( locale ) => ( {
				value: str( locale?.value ),
				label: str( locale?.label || locale?.value ),
			} ) ),
			ranges: obj( meta.ranges ),
			defaults: obj( meta.defaults ),
			source: toSource( meta.source ),
			docsUrl: str( meta.docs_url ) || DOCS_URL,
			canInstall: !! meta.can_install,
		},
	};
}

// Translations --------------------------------------------------------------

/**
 * One table row. `views` holds the filter chips the row belongs to.
 *
 * @param {Object} row Raw row.
 * @return {Object} Row.
 */
function toRow( row ) {
	const type = oneOf( row?.type, TYPES, 'plugin' );
	const status = oneOf( row?.status, STATUSES, 'not_installed' );
	const slug = str( row?.slug );
	const remote = obj( row?.remote );

	return {
		id: str( row?.id ) || `${ type }:${ slug }`,
		slug,
		name: str( row?.name ) || slug,
		type,
		status,
		files: num( row?.files ),
		localDate: str( row?.local_date ),
		remoteFiles: strings( remote.files ),
		remoteUrl: str( remote.url ),
		views: [ type, status ],
	};
}

/**
 * One per-item action result.
 *
 * @param {Object} result Raw result.
 * @return {Object} Result.
 */
function toResult( result ) {
	return {
		type: oneOf( result?.type, TYPES, 'plugin' ),
		slug: str( result?.slug ),
		ok: !! result?.ok,
		message: str( result?.message ),
		skipped: strings( result?.skipped ),
		deleted: strings( result?.deleted ),
	};
}

/**
 * GET /admin/translations, and the install / delete / refresh answers
 * (those add `results`).
 *
 * @param {Object} data Response.
 * @return {Object} { rows, counts, warnings, cache, source, canInstall, results }.
 */
export function toTranslations( data ) {
	const counts = obj( data?.counts );
	const cache = obj( data?.cache );

	return {
		rows: list( data?.rows ).map( toRow ),
		counts: {
			all: num( counts.all ),
			plugin: num( counts.plugin ),
			theme: num( counts.theme ),
			installed: num( counts.installed ),
			update: num( counts.update ),
			notInstalled: num( counts.not_installed ),
		},
		warnings: list( data?.warnings ).map( ( warning ) => ( {
			code: str( warning?.code ),
			level: oneOf( warning?.level, LEVELS, 'warning' ),
			message: str( warning?.message ),
		} ) ),
		cache: {
			fetchedAt: numOrNull( cache.fetched_at ),
			age: numOrNull( cache.age ),
			ttl: num( cache.ttl ),
		},
		source: toSource( data?.source ),
		canInstall: !! data?.can_install,
		results: list( data?.results ).map( toResult ),
	};
}
