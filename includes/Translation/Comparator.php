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
			$items[] = new TranslationItem(
				slug: $match['slug'],
				name: $match['name'],
				type: $match['type'],
				status: self::determine_status( $match['slug'], $match['type'], $locale, $match['files'] ),
				file_count: count( $match['files'] ),
				local_date: LocalScanner::get_local_date( $match['slug'], $match['type'], $locale ),
				files: $match['files'],
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
	 * Determine translation status by comparing SHA hashes.
	 *
	 * @param string               $slug   Plugin or theme slug.
	 * @param string               $type   Type: 'plugin' or 'theme'.
	 * @param string               $locale Locale code.
	 * @param array<string,string> $files  Remote files with SHA hashes.
	 * @return string Status constant.
	 */
	private static function determine_status( string $slug, string $type, string $locale, array $files ): string {
		$local_sha = LocalScanner::get_local_sha( $slug, $type, $locale );

		if ( null === $local_sha ) {
			return TranslationItem::STATUS_NOT_INSTALLED;
		}

		$mo_filename = $slug . '-' . $locale . '.mo';

		foreach ( $files as $filename => $remote_sha ) {
			if ( $filename === $mo_filename && $local_sha !== $remote_sha ) {
				return TranslationItem::STATUS_UPDATE;
			}
		}

		return TranslationItem::STATUS_UP_TO_DATE;
	}
}
