<?php
/**
 * Admin REST routes bootstrap.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Rest\Admin;

use LightweightPlugins\Translate\Capability;
use WP_Error;

/**
 * Registers the lw-translate/v1/admin/* routes used by the React admin.
 *
 * Reading and the settings need manage_options; changing translation
 * files needs install_languages (Capability::INSTALL). REST cookie auth
 * supplies the nonce.
 */
final class Routes {

	/**
	 * REST namespace.
	 */
	public const NAMESPACE = 'lw-translate/v1';

	/**
	 * Hook the route registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	/**
	 * Register every admin route.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		( new SettingsController() )->register_routes();
		( new TranslationsController() )->register_routes();
	}

	/**
	 * Permission callback for reading and for the settings.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission callback for installing, updating and deleting files.
	 *
	 * @return bool
	 */
	public static function can_install(): bool {
		return Capability::can_install();
	}

	/**
	 * Register one route with a handler per HTTP method.
	 *
	 * @param string                $path       Route path under the namespace.
	 * @param array<string, string> $handlers   Method constant => public method name on $owner.
	 * @param object                $owner      Controller instance.
	 * @param string                $permission Routes method used as permission callback.
	 * @return void
	 */
	public static function add( string $path, array $handlers, object $owner, string $permission = 'can_manage' ): void {
		$endpoints = [];

		foreach ( $handlers as $methods => $callback ) {
			$endpoints[] = [
				'methods'             => $methods,
				'callback'            => [ $owner, $callback ],
				'permission_callback' => [ self::class, $permission ],
			];
		}

		register_rest_route( self::NAMESPACE, $path, $endpoints );
	}

	/**
	 * A translated REST error.
	 *
	 * @param string               $code    Error code.
	 * @param string               $message Translated message.
	 * @param int                  $status  HTTP status.
	 * @param array<string, mixed> $data    Extra error data.
	 * @return WP_Error
	 */
	public static function error( string $code, string $message, int $status, array $data = [] ): WP_Error {
		return new WP_Error( $code, $message, array_merge( [ 'status' => $status ], $data ) );
	}
}
