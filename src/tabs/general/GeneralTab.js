/**
 * WordPress dependencies
 */
import { SelectControl, TextControl } from '@wordpress/components';
import { createInterpolateElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Callout from '../../components/Callout';
import { fieldOf } from '../../components/Fields';
import Section from '../../components/Section';
import Segmented from '../../components/Segmented';
import SettingRow from '../../components/SettingRow';
import { lifetimeLabel } from '../../data/format';
import { rangeOf } from '../../data/ranges';

/**
 * Cache lifetime in seconds, with the value spelled out in hours/days.
 *
 * @param {Object} props
 * @param {Object} props.store Settings store.
 */
function CacheLifetimeRow( { store } ) {
	const field = fieldOf( store, 'cache_ttl' );
	const [ min, max ] = rangeOf( store.data.meta, 'cache_ttl' );
	const value = store.data.options.cache_ttl;
	const fallback = store.data.meta.defaults.cache_ttl;
	const spelled = lifetimeLabel( value );

	return (
		<SettingRow
			title={ __( 'Cache lifetime', 'lw-translate' ) }
			htmlFor="lw-translate-cache-ttl"
			help={ sprintf(
				/* translators: 1: shortest lifetime, 2: longest lifetime, 3: default lifetime in seconds, 4: default spelled out. */
				__(
					'How long the repository listing is cached, in seconds: %1$s to %2$s. Default: %3$d (%4$s). A new value applies from the next refresh.',
					'lw-translate'
				),
				lifetimeLabel( min ),
				lifetimeLabel( max ),
				fallback,
				lifetimeLabel( fallback )
			) }
			{ ...field }
		>
			<span
				className={ `lw-admin-inline lw-admin-number ${
					field.errors.length ? 'has-error' : ''
				}` }
			>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					id="lw-translate-cache-ttl"
					label={ __( 'Cache lifetime', 'lw-translate' ) }
					hideLabelFromVision
					type="number"
					min={ min }
					max={ max }
					step={ 3600 }
					disabled={ !! field.locked }
					value={ String( value ?? '' ) }
					onChange={ ( next ) =>
						store.set(
							'cache_ttl',
							next === '' ? '' : Number( next )
						)
					}
				/>
				<span className="lw-admin-muted">
					{ __( 'seconds', 'lw-translate' ) }
				</span>
				{ spelled && (
					<span className="lw-admin-hint" aria-live="polite">
						{ sprintf(
							/* translators: %s: lifetime spelled out, e.g. "12 hours". */
							__( '= %s', 'lw-translate' ),
							spelled
						) }
					</span>
				) }
			</span>
		</SettingRow>
	);
}

/**
 * General: tone, target language and the listing cache lifetime.
 *
 * @param {Object} props
 * @param {Object} props.store Settings store.
 */
export default function GeneralTab( { store } ) {
	const { options, meta } = store.data;
	const tone = fieldOf( store, 'tone' );
	const locale = fieldOf( store, 'locale' );
	const locales = meta.locales.some( ( l ) => l.value === options.locale )
		? meta.locales
		: [ { value: options.locale, label: options.locale }, ...meta.locales ];

	return (
		<>
			<Section
				title={ __( 'Translation Settings', 'lw-translate' ) }
				description={ __(
					'Configure the translation source and caching.',
					'lw-translate'
				) }
			>
				<SettingRow
					title={ __( 'Tone', 'lw-translate' ) }
					help={ __(
						'Choose between formal and informal translation tone.',
						'lw-translate'
					) }
					{ ...tone }
				>
					<Segmented
						label={ __( 'Tone', 'lw-translate' ) }
						value={ options.tone }
						disabled={ !! tone.locked }
						onChange={ ( value ) => store.set( 'tone', value ) }
						options={ [
							{
								value: 'formal',
								label: __( 'Formal (magázó)', 'lw-translate' ),
							},
							{
								value: 'informal',
								label: __(
									'Informal (tegező)',
									'lw-translate'
								),
							},
						] }
					/>
				</SettingRow>
				<SettingRow
					title={ __( 'Locale', 'lw-translate' ) }
					htmlFor="lw-translate-locale"
					help={ __(
						'Target language for translations.',
						'lw-translate'
					) }
					{ ...locale }
				>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						id="lw-translate-locale"
						label={ __( 'Locale', 'lw-translate' ) }
						hideLabelFromVision
						disabled={ !! locale.locked }
						value={ options.locale }
						options={ locales }
						onChange={ ( value ) => store.set( 'locale', value ) }
					/>
				</SettingRow>
				<CacheLifetimeRow store={ store } />
			</Section>
			{ meta.source.url && (
				<Callout>
					{ createInterpolateElement(
						sprintf(
							/* translators: %s: repository name and branch, e.g. "owner/repo (main)". */
							__(
								'Translations come from <a>%s</a>.',
								'lw-translate'
							),
							`${ meta.source.repo } (${ meta.source.branch })`
						),
						{
							a: (
								// eslint-disable-next-line jsx-a11y/anchor-has-content -- content comes from the string.
								<a
									href={ meta.source.url }
									target="_blank"
									rel="noopener noreferrer"
								/>
							),
						}
					) }{ ' ' }
					{ __(
						'Only .mo, .po and script translation (.json) files are installed; the fast .l10n.php files are generated on this site.',
						'lw-translate'
					) }
				</Callout>
			) }
		</>
	);
}
