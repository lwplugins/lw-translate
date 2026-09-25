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
	 * Branch the translations are read from.
	 */
	private const BRANCH = 'main';

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
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public function fetch_tree(): array|WP_Error {
		$payload = $this->fetch_payload();

		return is_wp_error( $payload ) ? $payload : $payload['tree'];
	}

	/**
	 * What is known about the cached tree, without fetching it.
	 *
	 * @return array{truncated: bool, fetched_at: int|null}|null Null when nothing is cached.
	 */
	public static function cached_info(): ?array {
		$payload = TreePayload::read( get_transient( self::CACHE_KEY ) );

		if ( null === $payload ) {
			return null;
		}

		return [
			'truncated'  => $payload['truncated'],
			'fetched_at' => $payload['fetched_at'],
		];
	}

	/**
	 * The cached tree payload, fetched from GitHub on a miss.
	 *
	 * @return array{tree: array<int, array<string, mixed>>, truncated: bool, fetched_at: int|null}|WP_Error
	 */
	private function fetch_payload(): array|WP_Error {
		$cached = TreePayload::read( get_transient( self::CACHE_KEY ) );

		if ( null !== $cached ) {
			return $cached;
		}

		$url      = self::API_BASE . '/repos/' . self::OWNER . '/' . self::REPO . '/git/trees/' . self::BRANCH . '?recursive=1';
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

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return GitHubError::for_status(
				$code,
				(string) wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' ),
				(string) wp_remote_retrieve_header( $response, 'x-ratelimit-reset' ),
				time()
			);
		}

		$payload = TreePayload::from_body( json_decode( wp_remote_retrieve_body( $response ), true ), time() );

		if ( null === $payload ) {
			return new WP_Error( 'github_api_error', __( 'Invalid tree response.', 'lw-translate' ) );
		}

		$ttl = Options::clamp_cache_ttl( (int) Options::get( 'cache_ttl', 43200 ) );

		set_transient( self::CACHE_KEY, $payload, $ttl );

		return $payload;
	}

	/**
	 * Source label shown in the admin, e.g. "hellowpio/wordpress-translations (main)".
	 *
	 * @return array{repo: string, branch: string, url: string}
	 */
	public static function source(): array {
		return [
			'repo'   => self::OWNER . '/' . self::REPO,
			'branch' => self::BRANCH,
			'url'    => 'https://github.com/' . self::OWNER . '/' . self::REPO,
		];
	}

	/**
	 * Forget the cached tree.
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Download a raw file from the repository.
	 *
	 * @param string $path File path in the repository.
	 * @return string|WP_Error
	 */
	public function download_file( string $path ): string|WP_Error {
		$encoded  = implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) );
		$url      = self::RAW_BASE . '/' . self::OWNER . '/' . self::REPO . '/' . self::BRANCH . '/' . $encoded;
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
