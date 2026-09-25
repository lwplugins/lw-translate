<?php
/**
 * Main Plugin class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate;

use LightweightPlugins\Translate\Admin\SettingsPage;
use LightweightPlugins\Translate\CLI\Commands as CLICommands;
use LightweightPlugins\Translate\Installer\FileInstaller;
use LightweightPlugins\Translate\Translation\CompareCache;
use LightweightPlugins\Translate\Installer\SkippedFilesNotice;
use LightweightPlugins\Translate\SiteManager\Integration as SiteManagerIntegration;
use LightweightPlugins\Translate\Upgrade\Upgrader;

/**
 * Main plugin class.
 */
final class Plugin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
		$this->init_site_manager();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'init', [ $this, 'load_textdomain' ] );

		CLICommands::register();

		if ( is_admin() ) {
			new SettingsPage();
			Upgrader::init();

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

		if ( ! Capability::can_install() ) {
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

		CompareCache::clear();

		wp_send_json_success(
			[
				'message' => trim( __( 'Translation installed successfully.', 'lw-translate' ) . ' ' . SkippedFilesNotice::message( $result['skipped'] ) ),
				'skipped' => $result['skipped'],
			]
		);
	}

	/**
	 * AJAX: Bulk install translations.
	 *
	 * @return void
	 */
	public function ajax_bulk_install(): void {
		check_ajax_referer( 'lw_translate_nonce', 'nonce' );

		if ( ! Capability::can_install() ) {
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
				'message' => is_wp_error( $result ) ? $result->get_error_message() : SkippedFilesNotice::message( $result['skipped'] ),
			];
		}

		CompareCache::clear();

		wp_send_json_success( [ 'results' => $results ] );
	}

	/**
	 * AJAX: Delete a translation.
	 *
	 * @return void
	 */
	public function ajax_delete(): void {
		check_ajax_referer( 'lw_translate_nonce', 'nonce' );

		if ( ! Capability::can_install() ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'lw-translate' ) ] );
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
		$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';

		if ( empty( $slug ) || empty( $type ) ) {
			wp_send_json_error( [ 'message' => __( 'Missing parameters.', 'lw-translate' ) ] );
		}

		$installer = new FileInstaller();
		$result    = $installer->delete( $slug, $type );

		CompareCache::clear();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'message' => __( 'Translation deleted.', 'lw-translate' ) ] );
	}

	/**
	 * AJAX: Refresh the GitHub tree cache.
	 *
	 * @return void
	 */
	public function ajax_refresh_cache(): void {
		check_ajax_referer( 'lw_translate_nonce', 'nonce' );

		if ( ! Capability::can_install() ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'lw-translate' ) ] );
		}

		CompareCache::clear_all();

		wp_send_json_success( [ 'message' => __( 'Cache cleared.', 'lw-translate' ) ] );
	}

	/**
	 * Initialize LW Site Manager integration.
	 *
	 * No-op if Site Manager is not active.
	 *
	 * @return void
	 */
	private function init_site_manager(): void {
		SiteManagerIntegration::init();
	}
}
