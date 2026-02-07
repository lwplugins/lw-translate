<?php
/**
 * WP-CLI Commands.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\CLI;

use LightweightPlugins\Translate\Options;
use WP_CLI;

/**
 * Manage LW Translate translations and settings.
 *
 * ## EXAMPLES
 *
 *     # List all available translations
 *     wp lw-translate list
 *
 *     # Install a translation
 *     wp lw-translate install woocommerce
 *
 *     # Refresh cache
 *     wp lw-translate refresh
 *
 *     # View settings
 *     wp lw-translate settings list
 */
final class Commands {

	use ListTrait;
	use InstallTrait;

	/**
	 * Register CLI commands.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		WP_CLI::add_command( 'lw-translate', self::class );
	}

	/**
	 * Manage plugin settings.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : The action to perform (list, get, set).
	 *
	 * [<key>]
	 * : The setting key (required for get/set).
	 *
	 * [<value>]
	 * : The setting value (required for set).
	 *
	 * [--format=<format>]
	 * : Output format (table, json, yaml). Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-translate settings list
	 *     wp lw-translate settings list --format=json
	 *     wp lw-translate settings get tone
	 *     wp lw-translate settings set tone informal
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function settings( array $args, array $assoc_args ): void {
		$action = $args[0] ?? 'list';

		switch ( $action ) {
			case 'list':
				$this->settings_list( $assoc_args['format'] ?? 'table' );
				break;

			case 'get':
				if ( empty( $args[1] ) ) {
					WP_CLI::error( 'Please specify a setting key.' );
				}
				$this->settings_get( $args[1] );
				break;

			case 'set':
				if ( empty( $args[1] ) || ! isset( $args[2] ) ) {
					WP_CLI::error( 'Please specify both key and value.' );
				}
				$this->settings_set( $args[1], $args[2] );
				break;

			default:
				WP_CLI::error( "Unknown action: {$action}. Use: list, get, set." );
		}
	}

	/**
	 * Refresh translation cache.
	 *
	 * Clears the GitHub tree cache and comparison cache.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-translate refresh
	 *
	 * @return void
	 */
	public function refresh(): void {
		global $wpdb;

		delete_transient( 'lw_translate_tree_cache' );

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_lw_translate_compare_%',
				'_transient_timeout_lw_translate_compare_%'
			)
		);

		WP_CLI::success( 'Cache cleared. Next list/install will fetch fresh data.' );
	}

	/**
	 * List all settings.
	 *
	 * @param string $format Output format.
	 * @return void
	 */
	private function settings_list( string $format ): void {
		$options  = Options::get_all();
		$defaults = Options::get_defaults();
		$items    = [];

		foreach ( $options as $key => $value ) {
			$items[] = [
				'key'        => $key,
				'value'      => (string) $value,
				'default'    => (string) ( $defaults[ $key ] ?? '' ),
				'is_default' => ( ( $defaults[ $key ] ?? null ) === $value ) ? 'yes' : 'no',
			];
		}

		WP_CLI\Utils\format_items( $format, $items, [ 'key', 'value', 'default', 'is_default' ] );
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key Setting key.
	 * @return void
	 */
	private function settings_get( string $key ): void {
		$defaults = Options::get_defaults();

		if ( ! array_key_exists( $key, $defaults ) ) {
			WP_CLI::error( "Unknown setting: {$key}" );
		}

		WP_CLI::log( (string) Options::get( $key ) );
	}

	/**
	 * Set a single setting.
	 *
	 * @param string $key   Setting key.
	 * @param string $value Setting value.
	 * @return void
	 */
	private function settings_set( string $key, string $value ): void {
		$defaults = Options::get_defaults();

		if ( ! array_key_exists( $key, $defaults ) ) {
			WP_CLI::error( "Unknown setting: {$key}" );
		}

		$default_value = $defaults[ $key ];

		if ( is_int( $default_value ) ) {
			$value = (int) $value;
		}

		$options         = Options::get_all();
		$options[ $key ] = $value;
		Options::save( $options );

		WP_CLI::success( "Setting '{$key}' updated." );
	}
}
