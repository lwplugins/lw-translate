<?php
/**
 * Verified Downloader class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Installer;

use LightweightPlugins\Translate\Translation\LocalScanner;
use WP_Error;

/**
 * Downloads all files of an item and checks each against its git blob SHA.
 *
 * Everything is downloaded and verified before the caller writes a single
 * file, so a tampered or corrupted download leaves the item untouched.
 */
final class VerifiedDownloader {

	/**
	 * Downloader: fn( string $remote_path ): string|WP_Error.
	 *
	 * @var callable
	 */
	private $download;

	/**
	 * Constructor.
	 *
	 * @param callable $download Downloader, e.g. [ GitHubClient, 'download_file' ].
	 */
	public function __construct( callable $download ) {
		$this->download = $download;
	}

	/**
	 * Download and verify every file.
	 *
	 * @param array<string, array{path: string, sha: string}> $files File name => remote path and tree blob SHA.
	 * @return array<string, string>|WP_Error File name => contents, or the first error.
	 */
	public function fetch_all( array $files ): array|WP_Error {
		$contents = [];

		foreach ( $files as $name => $file ) {
			$body = call_user_func( $this->download, $file['path'] );

			if ( is_wp_error( $body ) ) {
				return $body;
			}

			if ( ! is_string( $body ) || '' === $file['sha']
				|| ! hash_equals( strtolower( $file['sha'] ), LocalScanner::git_blob_sha( $body ) ) ) {
				return new WP_Error(
					'checksum_mismatch',
					/* translators: %s: file name */
					sprintf( __( 'The downloaded file %s does not match the repository checksum. Nothing was installed for this item.', 'lw-translate' ), $name )
				);
			}

			$contents[ $name ] = $body;
		}

		return $contents;
	}
}
