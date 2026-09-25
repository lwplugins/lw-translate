<?php
/**
 * Uninstall LW Translate.
 *
 * @package LightweightPlugins\Translate
 */

// If uninstall not called from WordPress, die.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

// Remember the saved locale before the options go.
$lw_translate_options = get_option( 'lw_translate_options', [] );
$lw_translate_locale  = is_array( $lw_translate_options ) && isset( $lw_translate_options['locale'] ) ? (string) $lw_translate_options['locale'] : 'hu_HU';

// Remove plugin options.
delete_option( 'lw_translate_options' );
delete_option( 'lw_translate_version' );
delete_option( 'lw_translate_upgrade_state' );

// Remove transient caches.
delete_transient( 'lw_translate_tree_cache' );

// Comparison transients through the transient API, so a persistent object
// cache (Redis, Memcached) is cleared too.
foreach ( array_unique( [ $lw_translate_locale, 'hu_HU' ] ) as $lw_translate_code ) {
	foreach ( [ 'formal', 'informal' ] as $lw_translate_tone ) {
		delete_transient( 'lw_translate_compare_' . $lw_translate_code . '_' . $lw_translate_tone );
	}
}

// Leftover rows of other locales in the options table.
global $wpdb;
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		'_transient_lw_translate_compare_%'
	)
);
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		'_transient_timeout_lw_translate_compare_%'
	)
);
