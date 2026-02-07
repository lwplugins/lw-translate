<?php
/**
 * Main Plugin class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate;

use LightweightPlugins\Translate\Admin\SettingsPage;
use LightweightPlugins\Translate\Admin\TranslationsPage;
use LightweightPlugins\Translate\Installer\FileInstaller;

/**
 * Main plugin class.
 */
final class Plugin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'init', [ $this, 'load_textdomain' ] );

		if ( is_admin() ) {
			new SettingsPage();
			new TranslationsPage();

			add_action( 'wp_ajax_lw_translate_install', [ $this, 'ajax_install' ] );
			add_action( 'wp_ajax_lw_translate_bulk_install', [ $this, 'ajax_bulk_install' ] );
			add_action( 'wp_ajax_lw_translate_delete', [ $this, 'ajax_delete' ] );
			add_action( 'wp_ajax_lw_translate_refresh_cache', [ $this, 'ajax_refresh_cache' ] );
		}
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'lw-translate',
			false,
			dirname( plugin_basename( LW_TRANSLATE_FILE ) ) . '/languages'
		);
	}

	/**
	 * AJAX: Install or update a single translation.
	 *
	 * @return void
	 */
	public function ajax_install(): void {
		check_ajax_referer( 'lw_translate_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'lw-translate' ) ] );
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
		$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';

		if ( empty( $slug ) || empty( $type ) ) {
			wp_send_json_error( [ 'message' => __( 'Missing parameters.', 'lw-translate' ) ] );
		}

		$installer = new FileInstaller();
		$result    = $installer->install( $slug, $type );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		self::clear_comparison_cache();

		wp_send_json_success( [ 'message' => __( 'Translation installed successfully.', 'lw-translate' ) ] );
	}

	/**
	 * AJAX: Bulk install translations.
	 *
	 * @return void
	 */
	public function ajax_bulk_install(): void {
		check_ajax_referer( 'lw_translate_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'lw-translate' ) ] );
		}

		$items = isset( $_POST['items'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['items'] ) ) : [];

		if ( empty( $items ) ) {
			wp_send_json_error( [ 'message' => __( 'No items selected.', 'lw-translate' ) ] );
		}

		$installer = new FileInstaller();
		$results   = [];

		foreach ( $items as $item ) {
			$parts = explode( ':', $item, 2 );
			if ( 2 !== count( $parts ) ) {
				continue;
			}

			$result = $installer->install( $parts[1], $parts[0] );

			$results[] = [
				'slug'    => $parts[1],
				'type'    => $parts[0],
				'success' => ! is_wp_error( $result ),
				'message' => is_wp_error( $result ) ? $result->get_error_message() : '',
			];
		}

		self::clear_comparison_cache();

		wp_send_json_success( [ 'results' => $results ] );
	}

	/**
	 * AJAX: Delete a translation.
	 *
	 * @return void
	 */
	public function ajax_delete(): void {
		check_ajax_referer( 'lw_translate_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'lw-translate' ) ] );
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
		$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';

		if ( empty( $slug ) || empty( $type ) ) {
			wp_send_json_error( [ 'message' => __( 'Missing parameters.', 'lw-translate' ) ] );
		}

		$installer = new FileInstaller();
		$installer->delete( $slug, $type );

		self::clear_comparison_cache();

		wp_send_json_success( [ 'message' => __( 'Translation deleted.', 'lw-translate' ) ] );
	}

	/**
	 * AJAX: Refresh the GitHub tree cache.
	 *
	 * @return void
	 */
	public function ajax_refresh_cache(): void {
		check_ajax_referer( 'lw_translate_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'lw-translate' ) ] );
		}

		delete_transient( 'lw_translate_tree_cache' );
		self::clear_comparison_cache();

		wp_send_json_success( [ 'message' => __( 'Cache cleared.', 'lw-translate' ) ] );
	}

	/**
	 * Clear comparison transient caches.
	 *
	 * @return void
	 */
	private static function clear_comparison_cache(): void {
		global $wpdb;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_lw_translate_compare_%',
				'_transient_timeout_lw_translate_compare_%'
			)
		);
	}
}
