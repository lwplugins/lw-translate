/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { cog, language } from '@wordpress/icons';

/**
 * Tab registry: hash slugs and order of the classic screen (?tab= honoured).
 * `save` = the tab edits lw_translate_options (top bar Save and Cmd/Ctrl+S).
 * `fields` lists the option keys on the tab, so a failed save can flag it.
 */
export const TABS = [
	{
		id: 'translations',
		label: __( 'Translations', 'lw-translate' ),
		title: __( 'Translations', 'lw-translate' ),
		icon: language,
		save: false,
	},
	{
		id: 'general',
		label: __( 'General', 'lw-translate' ),
		title: __( 'Translation Settings', 'lw-translate' ),
		icon: cog,
		save: true,
		fields: [ 'tone', 'locale', 'cache_ttl' ],
	},
];

/**
 * Tab id that holds an option key (for error flags in the nav).
 *
 * @param {string} field Option key.
 * @return {string|undefined} Tab id.
 */
export const tabOfField = ( field ) =>
	TABS.find( ( tab ) => tab.fields?.includes( field ) )?.id;
