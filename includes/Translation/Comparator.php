<?php
/**
 * Comparator class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Translation;

use LightweightPlugins\Translate\Api\GitHubClient;
use LightweightPlugins\Translate\Api\TreeParser;
use LightweightPlugins\Translate\Installer\FileNamePolicy;
use LightweightPlugins\Translate\Options;
use WP_Error;

/**
 * Compares local and remote translations to determine status.
 */
final class Comparator {

	/**
	 * Compare all installed plugins/themes with remote translations.
	 *
	 * @return array<TranslationItem>|WP_Error
	 */
	public static function compare_all(): array|WP_Error {
		$locale = (string) Options::get( 'locale', 'hu_HU' );
		$tone   = (string) Options::get( 'tone', 'formal' );

		$cache_key = CompareCache::key( $locale, $tone );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		$client = new GitHubClient();
		$tree   = $client->fetch_tree();

		if ( is_wp_error( $tree ) ) {
			return $tree;
		}

		$matches = self::match(
			TreeParser::parse( $tree, $tone, $locale ),
			LocalScanner::get_installed_plugins(),
			LocalScanner::get_installed_themes()
		);
		$items   = [];

		foreach ( $matches as $match ) {
			$files   = self::installable( $match['files'], $match['slug'], $locale );
			$items[] = new TranslationItem(
				slug: $match['slug'],
				name: $match['name'],
				type: $match['type'],
				status: StatusResolver::resolve( $files, LocalScanner::get_local_shas( $match['type'], array_keys( $files ) ) ),
				file_count: count( $files ),
				local_date: LocalScanner::get_local_date( $match['slug'], $match['type'], $locale ),
				files: $files,
			);
		}

		set_transient( $cache_key, $items, CompareCache::TTL );

		return $items;
	}

	/**
	 * Pair the installed plugins and themes with their repository folders.
	 *
	 * @param array{plugin: array<string, array<string, string>>, theme: array<string, array<string, string>>} $remote  Parsed tree (type => slug => files).
	 * @param array<string, string>                                                                            $plugins Installed plugins (slug => name).
	 * @param array<string, string>                                                                            $themes  Installed themes (slug => name).
	 * @return array<int, array{type: string, slug: string, name: string, files: array<string, string>}>
	 */
	public static function match( array $remote, array $plugins, array $themes ): array {
		$matches = [];

		foreach ( [
			'plugin' => $plugins,
			'theme'  => $themes,
		] as $type => $installed ) {
			foreach ( $installed as $slug => $name ) {
				$slug = (string) $slug;

				if ( ! isset( $remote[ $type ][ $slug ] ) ) {
					continue;
				}

				$matches[] = [
					'type'  => $type,
					'slug'  => $slug,
					'name'  => (string) $name,
					'files' => $remote[ $type ][ $slug ],
				];
			}
		}

		return $matches;
	}

	/**
	 * Keep the repository files FileNamePolicy allows for the item.
	 *
	 * @param array<string, string> $files  File name => blob SHA.
	 * @param string                $slug   Plugin or theme slug.
	 * @param string                $locale Locale.
	 * @return array<string, string>
	 */
	public static function installable( array $files, string $slug, string $locale ): array {
		return array_filter(
			$files,
			static fn( $name ): bool => FileNamePolicy::is_allowed_basename( (string) $name, $slug, $locale ),
			ARRAY_FILTER_USE_KEY
		);
	}
}
