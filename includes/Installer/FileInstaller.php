<?php
/**
 * File Installer class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Installer;

use LightweightPlugins\Translate\Api\GitHubClient;
use WP_Error;
use WP_Filesystem_Base;

/**
 * Downloads and installs translation files to the WordPress language directory.
 */
final class FileInstaller {

	/**
	 * GitHub client.
	 *
	 * @var GitHubClient
	 */
	private GitHubClient $client;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->client = new GitHubClient();
	}

	/**
	 * Install translation files for a slug.
	 *
	 * @param string $slug Plugin or theme slug.
	 * @param string $type Type: 'plugin' or 'theme'.
	 * @return true|WP_Error
	 */
	public function install( string $slug, string $type ): bool|WP_Error {
		$tree = $this->client->fetch_tree();

		if ( is_wp_error( $tree ) ) {
			return $tree;
		}

		$remote_paths = FileMatcher::get_remote_paths_from_tree( $slug, $type, $tree );

		if ( empty( $remote_paths ) ) {
			return new WP_Error(
				'no_files',
				__( 'No translation files found for this item.', 'lw-translate' )
			);
		}

		$filesystem = $this->get_filesystem();

		if ( is_wp_error( $filesystem ) ) {
			return $filesystem;
		}

		$this->ensure_directories( $filesystem, $type );

		foreach ( $remote_paths as $remote_path ) {
			$result = $this->download_and_save( $remote_path, $filesystem );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Delete translation files for a slug.
	 *
	 * @param string $slug Plugin or theme slug.
	 * @param string $type Type: 'plugin' or 'theme'.
	 * @return void
	 */
	public function delete( string $slug, string $type ): void {
		$tree = $this->client->fetch_tree();

		if ( is_wp_error( $tree ) ) {
			return;
		}

		$remote_paths = FileMatcher::get_remote_paths_from_tree( $slug, $type, $tree );

		foreach ( $remote_paths as $remote_path ) {
			$local_path = FileMatcher::remote_to_local( $remote_path );

			if ( ! empty( $local_path ) && file_exists( $local_path ) ) {
				wp_delete_file( $local_path );
			}
		}
	}

	/**
	 * Download a remote file and save it locally.
	 *
	 * @param string             $remote_path Remote file path.
	 * @param WP_Filesystem_Base $filesystem  WordPress filesystem instance.
	 * @return true|WP_Error
	 */
	private function download_and_save( string $remote_path, WP_Filesystem_Base $filesystem ): bool|WP_Error {
		$content = $this->client->download_file( $remote_path );

		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$local_path = FileMatcher::remote_to_local( $remote_path );

		if ( empty( $local_path ) ) {
			return new WP_Error( 'invalid_path', __( 'Could not determine local path.', 'lw-translate' ) );
		}

		$written = $filesystem->put_contents( $local_path, $content, FS_CHMOD_FILE );

		if ( ! $written ) {
			return new WP_Error(
				'write_error',
				/* translators: %s: local file path */
				sprintf( __( 'Could not write file: %s', 'lw-translate' ), $local_path )
			);
		}

		return true;
	}

	/**
	 * Ensure language directories exist.
	 *
	 * @param WP_Filesystem_Base $filesystem WordPress filesystem instance.
	 * @param string             $type       Type: 'plugin' or 'theme'.
	 * @return void
	 */
	private function ensure_directories( WP_Filesystem_Base $filesystem, string $type ): void {
		$dir = 'theme' === $type ? 'themes' : 'plugins';

		$target = WP_LANG_DIR . '/' . $dir;

		if ( ! $filesystem->is_dir( $target ) ) {
			$filesystem->mkdir( $target, FS_CHMOD_DIR );
		}
	}

	/**
	 * Get WordPress filesystem instance.
	 *
	 * @return WP_Filesystem_Base|WP_Error
	 */
	private function get_filesystem(): WP_Filesystem_Base|WP_Error {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! WP_Filesystem() ) {
			return new WP_Error( 'filesystem_error', __( 'Could not initialize filesystem.', 'lw-translate' ) );
		}

		return $wp_filesystem;
	}
}
