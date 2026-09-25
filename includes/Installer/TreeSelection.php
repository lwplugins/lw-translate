<?php
/**
 * Tree Selection class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Installer;

/**
 * Picks the files to install for one item from the repository tree.
 */
final class TreeSelection {

	/**
	 * Select the installable files of an item.
	 *
	 * Every blob directly inside `{tone}/{plugins|themes}/{locale}/{slug}/`
	 * whose name passes FileNamePolicy is selected. Anything else in that
	 * folder is left out and listed in "skipped".
	 *
	 * @param array<int, array{type?: string, path?: mixed, sha?: mixed}> $tree   Raw tree. Untrusted remote input.
	 * @param string                                                      $slug   Plugin or theme slug.
	 * @param string                                                      $type   Type: 'plugin' or 'theme'.
	 * @param string                                                      $tone   Translation tone.
	 * @param string                                                      $locale Locale.
	 * @return array{files: array<string, array{path: string, sha: string}>, skipped: array<int, string>}
	 */
	public static function select( array $tree, string $slug, string $type, string $tone, string $locale ): array {
		$selection = [
			'files'   => [],
			'skipped' => [],
		];

		if ( ! FileNamePolicy::is_valid_slug( $slug ) || ! FileNamePolicy::is_valid_locale( $locale ) ) {
			return $selection;
		}

		$dir    = 'theme' === $type ? 'themes' : 'plugins';
		$prefix = $tone . '/' . $dir . '/' . $locale . '/' . $slug . '/';

		foreach ( $tree as $entry ) {
			$path = $entry['path'] ?? '';

			if ( 'blob' !== ( $entry['type'] ?? '' ) || ! is_string( $path ) || ! str_starts_with( $path, $prefix ) ) {
				continue;
			}

			if ( str_contains( $path, "\0" ) ) {
				continue;
			}

			$name = substr( $path, strlen( $prefix ) );

			if ( ! FileNamePolicy::is_allowed_basename( $name, $slug, $locale ) ) {
				$selection['skipped'][] = $name;
				continue;
			}

			$sha                         = $entry['sha'] ?? '';
			$selection['files'][ $name ] = [
				'path' => $path,
				'sha'  => is_string( $sha ) ? $sha : '',
			];
		}

		return $selection;
	}
}
