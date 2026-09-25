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
 * The files are taken from the folder itself, not from the repository
 * tree, so a file that was removed upstream can still be deleted. Only
 * names FileNamePolicy allows for the item go, plus the `.l10n.php` that
 * was generated from its .mo.
 */
final class TranslationRemover {

	/**
	 * Delete the item's files.
	 *
	 * @param WP_Filesystem_Base $filesystem Filesystem.
	 * @param string             $base_dir   Language folder of the item type.
	 * @param string             $slug       Plugin or theme slug.
	 * @param string             $locale     Locale.
	 * @return array{deleted: array<int, string>, failed: array<int, string>} File names.
	 */
	public function remove( WP_Filesystem_Base $filesystem, string $base_dir, string $slug, string $locale ): array {
		$result = [
			'deleted' => [],
			'failed'  => [],
		];

		foreach ( $this->targets( $filesystem, $base_dir, $slug, $locale ) as $name ) {
			$path = $base_dir . '/' . $name;

			if ( ! PathGuard::is_contained( $base_dir, $path ) ) {
				continue;
			}

			$filesystem->delete( $path );
			$result[ $filesystem->exists( $path ) ? 'failed' : 'deleted' ][] = $name;
		}

		return $result;
	}

	/**
	 * File names in the folder that belong to the item, sorted.
	 *
	 * @param WP_Filesystem_Base $filesystem Filesystem.
	 * @param string             $base_dir   Language folder.
	 * @param string             $slug       Plugin or theme slug.
	 * @param string             $locale     Locale.
	 * @return array<int, string>
	 */
	private function targets( WP_Filesystem_Base $filesystem, string $base_dir, string $slug, string $locale ): array {
		$list = $filesystem->dirlist( $base_dir, false, false );

		if ( ! is_array( $list ) || ! FileNamePolicy::is_valid_slug( $slug ) || ! FileNamePolicy::is_valid_locale( $locale ) ) {
			return [];
		}

		$generated = basename( L10nPhpGenerator::php_path_for( $slug . '-' . $locale . '.mo' ) );
		$names     = [];

		foreach ( $list as $name => $entry ) {
			$name = (string) $name;

			if ( 'f' === $entry['type']
				&& ( $generated === $name || FileNamePolicy::is_allowed_basename( $name, $slug, $locale ) ) ) {
				$names[] = $name;
			}
		}

		sort( $names, SORT_STRING );

		return $names;
	}
}
