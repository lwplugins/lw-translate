<?php
/**
 * Translation Remover class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Installer;

use WP_Filesystem_Base;

/**
 * Deletes an item's translation files from its language folder.
 *
 * Only the names the repository lists for the item (and FileNamePolicy
 * allows) go, plus the `.l10n.php` generated from its .mo when that .mo is
 * deleted. Same-named files a translate.wordpress.org language pack put
 * there under other names (other script-translation hashes) are left alone.
 */
final class TranslationRemover {

	/**
	 * Delete the item's files.
	 *
	 * @param WP_Filesystem_Base $filesystem Filesystem.
	 * @param string             $base_dir   Language folder of the item type.
	 * @param string             $slug       Plugin or theme slug.
	 * @param string             $locale     Locale.
	 * @param array<int, string> $remote     File names the repository lists for the item.
	 * @return array{deleted: array<int, string>, failed: array<int, string>} File names.
	 */
	public function remove( WP_Filesystem_Base $filesystem, string $base_dir, string $slug, string $locale, array $remote ): array {
		$result = [
			'deleted' => [],
			'failed'  => [],
		];

		foreach ( self::targets( $slug, $locale, $remote ) as $name ) {
			$path = $base_dir . '/' . $name;

			if ( ! $filesystem->exists( $path ) || ! PathGuard::is_contained( $base_dir, $path ) ) {
				continue;
			}

			$result[ self::delete_file( $filesystem, $path ) ? 'deleted' : 'failed' ][] = $name;
		}

		$mo = $slug . '-' . $locale . '.mo';

		if ( in_array( $mo, $result['deleted'], true ) ) {
			$php  = basename( L10nPhpGenerator::php_path_for( $mo ) );
			$path = $base_dir . '/' . $php;

			if ( $filesystem->exists( $path ) && PathGuard::is_contained( $base_dir, $path ) ) {
				$result[ self::delete_file( $filesystem, $path ) ? 'deleted' : 'failed' ][] = $php;
			}
		}

		sort( $result['deleted'], SORT_STRING );
		sort( $result['failed'], SORT_STRING );

		return $result;
	}

	/**
	 * Delete one file; true when it is gone afterwards.
	 *
	 * @param WP_Filesystem_Base $filesystem Filesystem.
	 * @param string             $path       File path.
	 * @return bool
	 */
	private static function delete_file( WP_Filesystem_Base $filesystem, string $path ): bool {
		$filesystem->delete( $path );

		return ! $filesystem->exists( $path );
	}

	/**
	 * Repository names that FileNamePolicy allows for the item.
	 *
	 * @param string             $slug   Plugin or theme slug.
	 * @param string             $locale Locale.
	 * @param array<int, string> $remote Repository file names.
	 * @return array<int, string>
	 */
	private static function targets( string $slug, string $locale, array $remote ): array {
		return array_values(
			array_unique(
				array_filter(
					array_map( 'strval', $remote ),
					static fn( string $name ): bool => FileNamePolicy::is_allowed_basename( $name, $slug, $locale )
				)
			)
		);
	}
}
