<?php
/**
 * Settings REST controller.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Rest\Admin;

use LightweightPlugins\Translate\Settings\LocaleCatalog;
use LightweightPlugins\Translate\Settings\SettingsStore;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET/POST lw-translate/v1/admin/settings.
 */
final class SettingsController {

	/**
	 * Largest accepted request body, in bytes.
	 */
	private const MAX_BYTES = 8192;

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		Routes::add(
			'/admin/settings',
			[
				WP_REST_Server::READABLE  => 'get_settings',
				WP_REST_Server::CREATABLE => 'save_settings',
			],
			$this
		);
	}

	/**
	 * Current options plus the screen context.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings(): WP_REST_Response {
		return new WP_REST_Response( self::shape( LocaleCatalog::offered( false ) ) );
	}

	/**
	 * Partial, atomic update: only the submitted keys change, and nothing is
	 * saved when any of them is invalid.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_settings( WP_REST_Request $request ) {
		if ( strlen( (string) $request->get_body() ) > self::MAX_BYTES ) {
			return Routes::error( 'lw_translate_too_large', __( 'The request is too large.', 'lw-translate' ), 413 );
		}

		$body = $request->get_json_params();

		if ( empty( $body ) ) {
			$body = $request->get_body_params();
		}

		$offered = LocaleCatalog::offered();
		$errors  = SettingsStore::save( (array) $body, $offered );

		if ( [] !== $errors ) {
			return Routes::error(
				'lw_translate_invalid',
				__( 'Some settings are not valid. Nothing was saved.', 'lw-translate' ),
				400,
				[ 'fields' => $errors ]
			);
		}

		return new WP_REST_Response( self::shape( $offered ) );
	}

	/**
	 * Response shape shared by GET and POST.
	 *
	 * @param array<int, string> $offered Locales the repository offers.
	 * @return array{options: array<string, mixed>, meta: array<string, mixed>}
	 */
	public static function shape( array $offered ): array {
		return [
			'options' => SettingsStore::current(),
			'meta'    => SettingsMeta::build( $offered ),
		];
	}
}
