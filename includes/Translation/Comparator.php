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

		$cache_key = 'lw_translate_compare_' . $locale . '_' . $tone;
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		$client = new GitHubClient();
		$tree   = $client->fetch_tree();

		if ( is_wp_error( $tree ) ) {
			return $tree;
		}

		$remote_data = TreeParser::parse( $tree, $tone, $locale );
		$plugins     = LocalScanner::get_installed_plugins();
		$themes      = LocalScanner::get_installed_themes();
		$items       = [];

		$items = array_merge(
			$items,
			self::compare_type( $remote_data, $plugins, 'plugin', $locale )
		);

		$items = array_merge(
			$items,
			self::compare_type( $remote_data, $themes, 'theme', $locale )
		);

		set_transient( $cache_key, $items, 3600 );

		return $items;
	}

	/**
	 * Compare a specific type (plugin or theme).
	 *
	 * @param array<string, array{type: string, files: array<string, string>}> $remote_data Parsed remote data.
	 * @param array<string, string>                                            $installed   Installed items (slug => name).
	 * @param string                                                           $type        Type: 'plugin' or 'theme'.
	 * @param string                                                           $locale      Locale code.
	 * @return array<TranslationItem>
	 */
	private static function compare_type( array $remote_data, array $installed, string $type, string $locale ): array {
		$items = [];

		foreach ( $installed as $slug => $name ) {
			if ( ! isset( $remote_data[ $slug ] ) ) {
				continue;
			}

			$remote = $remote_data[ $slug ];

			if ( $remote['type'] !== $type ) {
				continue;
			}

			$status     = self::determine_status( $slug, $type, $locale, $remote['files'] );
			$local_date = LocalScanner::get_local_date( $slug, $type, $locale );

			$items[] = new TranslationItem(
				slug: $slug,
				name: $name,
				type: $type,
				status: $status,
				file_count: count( $remote['files'] ),
				local_date: $local_date,
				files: $remote['files'],
			);
		}

		return $items;
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
