/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { Icon, caution } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import StatusBadge from '../components/StatusBadge';
import { tabOfField } from './tabs';

/**
 * Nav extras: the number of available updates on Translations, and a flag
 * on every tab holding a field the last save rejected.
 *
 * @param {Object}      props
 * @param {Object}      props.errors Field errors { key: [ messages ] }.
 * @param {Object|null} props.list   Translations list.
 * @return {Object} { tabId: node }.
 */
export default function navMeta( { errors, list } ) {
	const meta = {};
	const updates = list?.counts.update || 0;

	if ( updates ) {
		meta.translations = (
			<StatusBadge status="warning">
				<span aria-hidden="true">{ String( updates ) }</span>
				<span className="screen-reader-text">
					{ sprintf(
						/* translators: %d: number of translations with an update. */
						_n(
							'%d update available',
							'%d updates available',
							updates,
							'lw-translate'
						),
						updates
					) }
				</span>
			</StatusBadge>
		);
	}

	Object.keys( errors ).forEach( ( key ) => {
		const tab = tabOfField( key );
		if ( tab ) {
			meta[ tab ] = (
				<span className="lw-admin-navflag">
					<Icon icon={ caution } size={ 18 } />
					<span className="screen-reader-text">
						{ __( 'Has invalid fields', 'lw-translate' ) }
					</span>
				</span>
			);
		}
	} );

	return meta;
}
