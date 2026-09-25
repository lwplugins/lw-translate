/**
 * Field helpers bound to the settings store: every row disables itself when
 * its key is locked and shows the server's validation messages for its key.
 */

/**
 * SettingRow props for an option key.
 *
 * @param {Object} store Settings store.
 * @param {string} name  Option key.
 * @return {Object} { locked: constant name or '', errors }.
 */
export const fieldOf = ( store, name ) => ( {
	locked: store.isLocked( name )
		? store.data.meta.lockedConstants[ name ] || ''
		: '',
	errors: store.errors[ name ] || [],
} );
