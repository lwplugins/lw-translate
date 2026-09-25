<?php
/**
 * Translations REST controller.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Rest\Admin;

use LightweightPlugins\Translate\Api\GitHubClient;
use LightweightPlugins\Translate\Capability;
use LightweightPlugins\Translate\Installer\FileInstaller;
use LightweightPlugins\Translate\Options;
use LightweightPlugins\Translate\Translation\CompareCache;
use LightweightPlugins\Translate\Translation\Comparator;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET  lw-translate/v1/admin/translations
 * POST lw-translate/v1/admin/translations/install  {items: [{type, slug}]}
 * POST lw-translate/v1/admin/translations/delete   {items: [{type, slug}]}
 * POST lw-translate/v1/admin/translations/refresh
 */
final class TranslationsController {

	/**
	 * Largest accepted request body, in bytes (10 items need well under 2 KB).
	 */
	private const MAX_BYTES = 8192;

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		Routes::add( '/admin/translations', [ WP_REST_Server::READABLE => 'get_list' ], $this );
		Routes::add( '/admin/translations/install', [ WP_REST_Server::CREATABLE => 'install' ], $this, 'can_install' );
		Routes::add( '/admin/translations/delete', [ WP_REST_Server::CREATABLE => 'delete' ], $this, 'can_install' );
		Routes::add( '/admin/translations/refresh', [ WP_REST_Server::CREATABLE => 'refresh' ], $this, 'can_install' );
	}

	/**
	 * The list.
	 *
	 * @return WP_REST_Response
	 */
	public function get_list(): WP_REST_Response {
		return new WP_REST_Response( self::shape() );
	}

	/**
	 * Install or update the given items.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function install( WP_REST_Request $request ) {
		return $this->run( $request, 'install' );
	}

	/**
	 * Delete the given items' files.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete( WP_REST_Request $request ) {
		return $this->run( $request, 'delete' );
	}

	/**
	 * Forget the cached tree and comparison, and return the fresh list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function refresh( WP_REST_Request $request ) {
		$too_large = self::too_large( $request );

		if ( null !== $too_large ) {
			return $too_large;
		}

		CompareCache::clear_all();

		return new WP_REST_Response( self::shape() );
	}

	/**
	 * Parse the items, run the action per item and return the results with
	 * the fresh list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $action  "install" or "delete".
	 * @return WP_REST_Response|WP_Error
	 */
	private function run( WP_REST_Request $request, string $action ) {
		$too_large = self::too_large( $request );

		if ( null !== $too_large ) {
			return $too_large;
		}

		$parsed = ItemsInput::parse( $request->get_param( 'items' ) );

		if ( '' !== $parsed['error'] ) {
			return Routes::error( 'lw_translate_invalid_items', $parsed['error'], 400 );
		}

		$installer = new FileInstaller();
		$actions   = new ItemActions( [ $installer, 'install' ], [ $installer, 'delete' ] );
		$results   = 'install' === $action ? $actions->install( $parsed['items'] ) : $actions->delete( $parsed['items'] );

		CompareCache::clear();

		return new WP_REST_Response( array_merge( [ 'results' => $results ], self::shape() ) );
	}

	/**
	 * A 413 error for an oversized body, or null.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_Error|null
	 */
	private static function too_large( WP_REST_Request $request ): ?WP_Error {
		if ( strlen( (string) $request->get_body() ) <= self::MAX_BYTES ) {
			return null;
		}

		return Routes::error( 'lw_translate_too_large', __( 'The request is too large.', 'lw-translate' ), 413 );
	}

	/**
	 * The list response: rows, counts, warnings, cache and source.
	 *
	 * @return array<string, mixed>
	 */
	public static function shape(): array {
		$tone   = (string) Options::get( 'tone', 'formal' );
		$locale = (string) Options::get( 'locale', 'hu_HU' );
		$items  = Comparator::compare_all();
		$info   = GitHubClient::cached_info();
		$source = GitHubClient::source();
		$folder = $source['url'] . '/tree/' . $source['branch'] . '/' . rawurlencode( $tone ) . '/{dir}/' . rawurlencode( $locale ) . '/';
		$ttl    = Options::clamp_cache_ttl( (int) Options::get( 'cache_ttl', 43200 ) );

		return array_merge(
			TranslationList::build( $items, null !== $info && $info['truncated'], $folder ),
			[
				'cache'       => [
					'fetched_at' => $info['fetched_at'] ?? null,
					'age'        => isset( $info['fetched_at'] ) ? max( 0, time() - $info['fetched_at'] ) : null,
					'ttl'        => $ttl,
				],
				'source'      => array_merge(
					$source,
					[
						'tone'   => $tone,
						'locale' => $locale,
					]
				),
				'can_install' => Capability::can_install(),
			]
		);
	}
}
