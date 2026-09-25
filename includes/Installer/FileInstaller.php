<?php
/**
 * File Installer class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Installer;

use LightweightPlugins\Translate\Api\GitHubClient;
use LightweightPlugins\Translate\Options;
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
	 * Local `.l10n.php` generator.
	 *
	 * @var L10nPhpGenerator
	 */
	private L10nPhpGenerator $generator;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->client    = new GitHubClient();
		$this->generator = new L10nPhpGenerator();
	}

	/**
	 * Install translation files for a slug.
	 *
	 * Only `{slug}-{locale}.mo|.po` and `{slug}-{locale}-{md5}.json` are
	 * installed. Any other file in the item's repository folder is left
	 * out and listed under "skipped". The `.l10n.php` is generated locally
	 * from the installed .mo.
	 *
	 * @param string $slug Plugin or theme slug.
	 * @param string $type Type: 'plugin' or 'theme'.
	 * @return array{installed: array<int, string>, skipped: array<int, string>}|WP_Error
	 */
	public function install( string $slug, string $type ): array|WP_Error {
		$locale = (string) Options::get( 'locale', 'hu_HU' );
		$valid  = $this->validate_item( $slug, $locale );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$tree = $this->client->fetch_tree();

		if ( is_wp_error( $tree ) ) {
			return $tree;
		}

		$selection = TreeSelection::select( $tree, $slug, $type, (string) Options::get( 'tone', 'formal' ), $locale );

		if ( empty( $selection['files'] ) ) {
			return new WP_Error(
				'no_files',
				__( 'No translation files found for this item.', 'lw-translate' )
			);
		}

		$filesystem = $this->get_filesystem();

		if ( is_wp_error( $filesystem ) ) {
			return $filesystem;
		}

		$base_dir = $this->ensure_directories( $filesystem, $type );

		foreach ( $selection['files'] as $name => $file ) {
			$result = $this->download_and_save( $file['path'], $base_dir . '/' . $name, $base_dir, $filesystem );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		$mo_name = $slug . '-' . $locale . '.mo';

		if ( isset( $selection['files'][ $mo_name ] ) ) {
			$this->generator->generate( $base_dir . '/' . $mo_name, $filesystem );
		}

		return [
			'installed' => array_keys( $selection['files'] ),
			'skipped'   => $selection['skipped'],
		];
	}

	/**
	 * Delete translation files for a slug.
	 *
	 * Only file names that pass FileNamePolicy for this item are deleted,
	 * plus the locally generated `.l10n.php` when the .mo goes.
	 *
	 * @param string $slug Plugin or theme slug.
	 * @param string $type Type: 'plugin' or 'theme'.
	 * @return void
	 */
	public function delete( string $slug, string $type ): void {
		$locale = (string) Options::get( 'locale', 'hu_HU' );

		if ( is_wp_error( $this->validate_item( $slug, $locale ) ) ) {
			return;
		}

		$tree = $this->client->fetch_tree();

		if ( is_wp_error( $tree ) ) {
			return;
		}

		$filesystem = $this->get_filesystem();

		if ( is_wp_error( $filesystem ) ) {
			return;
		}

		$selection = TreeSelection::select( $tree, $slug, $type, (string) Options::get( 'tone', 'formal' ), $locale );
		$base_dir  = WP_LANG_DIR . '/' . self::type_dir( $type );

		foreach ( array_keys( $selection['files'] ) as $name ) {
			$local_path = $base_dir . '/' . $name;

			if ( PathGuard::is_contained( $base_dir, $local_path ) && file_exists( $local_path ) ) {
				wp_delete_file( $local_path );
			}
		}

		$mo_path = $base_dir . '/' . $slug . '-' . $locale . '.mo';

		if ( ! file_exists( $mo_path ) && PathGuard::is_contained( $base_dir, $mo_path ) ) {
			$this->generator->remove( $mo_path, $filesystem );
		}
	}

	/**
	 * Reject slugs and locales that cannot form a safe file name.
	 *
	 * @param string $slug   Plugin or theme slug.
	 * @param string $locale Locale.
	 * @return true|WP_Error
	 */
	private function validate_item( string $slug, string $locale ): bool|WP_Error {
		if ( ! FileNamePolicy::is_valid_slug( $slug ) ) {
			return new WP_Error( 'invalid_slug', __( 'Invalid plugin or theme slug.', 'lw-translate' ) );
		}

		if ( ! FileNamePolicy::is_valid_locale( $locale ) ) {
			return new WP_Error( 'invalid_locale', __( 'Invalid locale setting.', 'lw-translate' ) );
		}

		return true;
	}

	/**
	 * Download a remote file and save it locally.
	 *
	 * @param string             $remote_path Remote file path.
	 * @param string             $local_path  Target path.
	 * @param string             $base_dir    Language folder the target must stay in.
	 * @param WP_Filesystem_Base $filesystem  WordPress filesystem instance.
	 * @return true|WP_Error
	 */
	private function download_and_save( string $remote_path, string $local_path, string $base_dir, WP_Filesystem_Base $filesystem ): bool|WP_Error {
		if ( ! PathGuard::is_contained( $base_dir, $local_path ) ) {
			return new WP_Error( 'invalid_path', __( 'Could not determine local path.', 'lw-translate' ) );
		}

		$content = $this->client->download_file( $remote_path );

		if ( is_wp_error( $content ) ) {
			return $content;
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
	 * @return string The language folder for the type.
	 */
	private function ensure_directories( WP_Filesystem_Base $filesystem, string $type ): string {
		$target = WP_LANG_DIR . '/' . self::type_dir( $type );

		if ( ! $filesystem->is_dir( $target ) ) {
			$filesystem->mkdir( $target, FS_CHMOD_DIR );
		}

		return $target;
	}

	/**
	 * Language sub-folder for a type.
	 *
	 * @param string $type Type: 'plugin' or 'theme'.
	 * @return string
	 */
	private static function type_dir( string $type ): string {
		return 'theme' === $type ? 'themes' : 'plugins';
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
