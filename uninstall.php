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

// Remove plugin options.
delete_option( 'lw_translate_options' );

// Remove transient caches.
delete_transient( 'lw_translate_tree_cache' );

// Delete all comparison transients.
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
