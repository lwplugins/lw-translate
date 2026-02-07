<?php
/**
 * GitHub API Client.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Api;

use LightweightPlugins\Translate\Options;
use WP_Error;

/**
 * Handles communication with the GitHub API.
 */
final class GitHubClient {

	/**
	 * GitHub repository owner.
	 */
	private const OWNER = 'hellowpio';

	/**
	 * GitHub repository name.
	 */
	private const REPO = 'wordpress-translations';

	/**
	 * GitHub Trees API base URL.
	 */
	private const API_BASE = 'https://api.github.com';

	/**
	 * Raw content base URL.
	 */
	private const RAW_BASE = 'https://raw.githubusercontent.com';

	/**
	 * Transient cache key.
	 */
	private const CACHE_KEY = 'lw_translate_tree_cache';

	/**
	 * Fetch the full repository tree, cached.
	 *
	 * @return array<int, array<string, string>>|WP_Error
	 */
	public function fetch_tree(): array|WP_Error {
		$cached = get_transient( self::CACHE_KEY );

		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		$url      = self::API_BASE . '/repos/' . self::OWNER . '/' . self::REPO . '/git/trees/main?recursive=1';
		$response = wp_remote_get(
			$url,
			[
				'timeout' => 30,
				'headers' => [
					'Accept'     => 'application/vnd.github.v3+json',
					'User-Agent' => 'LW-Translate/' . LW_TRANSLATE_VERSION,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return new WP_Error(
				'github_api_error',
				/* translators: %d: HTTP status code */
				sprintf( __( 'GitHub API returned status %d.', 'lw-translate' ), $code )
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $body['tree'] ) || ! is_array( $body['tree'] ) ) {
			return new WP_Error( 'github_api_error', __( 'Invalid tree response.', 'lw-translate' ) );
		}

		$tree = $body['tree'];
		$ttl  = (int) Options::get( 'cache_ttl', 43200 );

		set_transient( self::CACHE_KEY, $tree, $ttl );

		return $tree;
	}

	/**
	 * Download a raw file from the repository.
	 *
	 * @param string $path File path in the repository.
	 * @return string|WP_Error
	 */
	public function download_file( string $path ): string|WP_Error {
		$url      = self::RAW_BASE . '/' . self::OWNER . '/' . self::REPO . '/main/' . $path;
		$response = wp_remote_get(
			$url,
			[
				'timeout' => 30,
				'headers' => [
					'User-Agent' => 'LW-Translate/' . LW_TRANSLATE_VERSION,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return new WP_Error(
				'download_error',
				/* translators: 1: file path, 2: HTTP status code */
				sprintf( __( 'Failed to download %1$s (HTTP %2$d).', 'lw-translate' ), $path, $code )
			);
		}

		return wp_remote_retrieve_body( $response );
	}
}
