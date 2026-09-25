/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Icon, check, closeSmall, update } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import StatusBadge from './StatusBadge';

const STATUS = {
	installed: { tone: 'ok', icon: check },
	update: { tone: 'warning', icon: update },
	not_installed: { tone: 'idle', icon: closeSmall },
};

/**
 * Translated status label.
 *
 * @param {string} status installed|update|not_installed.
 * @return {string} Label.
 */
export const statusLabel = ( status ) => {
	if ( status === 'installed' ) {
		return __( 'Up to date', 'lw-translate' );
	}
	return status === 'update'
		? __( 'Update available', 'lw-translate' )
		: __( 'Not installed', 'lw-translate' );
};

/**
 * Status badge with an icon (never a bare glyph).
 *
 * @param {Object} props
 * @param {string} props.status installed|update|not_installed.
 */
export default function TranslationStatus( { status } ) {
	const meta = STATUS[ status ] || STATUS.not_installed;
	return (
		<StatusBadge status={ meta.tone }>
			<span className="lw-translate-status">
				<Icon icon={ meta.icon } size={ 14 } />
				{ statusLabel( status ) }
			</span>
		</StatusBadge>
	);
}
