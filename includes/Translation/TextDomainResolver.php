<?php
/**
 * Text Domain Resolver class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Translation;

use LightweightPlugins\Translate\Installer\FileNamePolicy;
use LightweightPlugins\Translate\Installer\TreeSelection;

/**
 * Decides which text domain an item's translation files are named after.
 *
 * The TextDomain header of the plugin or theme wins. Many plugins leave
 * the header out (the domain is only in their code, e.g. folder
 * admin-menu-editor-pro, domain admin-menu-editor), so without a valid
 * header the single file name prefix of the item's repository folder is
 * taken instead, when it is safe: exactly one prefix besides the folder
 * name, a valid slug, and not the folder name or text domain of another
 * installed plugin or theme (an add-on's folder must not ship, say,
 * woocommerce-hu_HU.mo).
 *
 * Every caller (install, delete, the comparison and its status, the local
 * date) gets the domain from here, so they all agree.
 */
final class TextDomainResolver {

	/**
	 * Text domain of one installed item, from its header or its repository folder.
	 *
	 * @param array<int, array{type?: string, path?: mixed, sha?: mixed}> $tree   Raw tree. Untrusted remote input.
	 * @param string                                                      $slug   Plugin or theme slug.
	 * @param string                                                      $type   Type: 'plugin' or 'theme'.
	 * @param string                                                      $tone   Translation tone.
	 * @param string                                                      $locale Locale.
	 * @return string '' when neither gives a usable domain.
	 */
	public static function for_item( array $tree, string $slug, string $type, string $tone, string $locale ): string {
		$header = LocalScanner::get_text_domain( $type, $slug );

		if ( FileNamePolicy::is_valid_slug( $header ) ) {
			return $header;
		}

		$folder = TreeSelection::select( $tree, $slug, $type, $tone, $locale );

		return self::resolve(
			$slug,
			$header,
			array_merge( array_map( 'strval', array_keys( $folder['files'] ) ), $folder['skipped'] ),
			$locale,
			self::reserved_names()
		);
	}

	/**
	 * Pick the text domain from the header or the repository file names.
	 *
	 * @param string             $slug     Plugin or theme slug (directory name).
	 * @param string             $header   TextDomain header, '' for none.
	 * @param array<int, string> $names    File names in the item's repository folder.
	 * @param string             $locale   Locale.
	 * @param array<int, string> $reserved Folder names and text domains of every installed plugin and theme.
	 * @return string '' when neither gives a usable domain.
	 */
	public static function resolve( string $slug, string $header, array $names, string $locale, array $reserved ): string {
		if ( FileNamePolicy::is_valid_slug( $header ) ) {
			return $header;
		}

		if ( ! FileNamePolicy::is_valid_slug( $slug ) || ! FileNamePolicy::is_valid_locale( $locale ) ) {
			return '';
		}

		$prefixes = self::prefixes( $names, $slug, $locale );

		if ( 1 !== count( $prefixes ) ) {
			return '';
		}

		$prefix = $prefixes[0];

		if ( ! FileNamePolicy::is_valid_slug( $prefix ) || in_array( $prefix, $reserved, true ) ) {
			return '';
		}

		return $prefix;
	}

	/**
	 * Fill in the text domain of each comparison match.
	 *
	 * @param array<int, array{type: string, slug: string, name: string, text_domain?: string, files: array<string, string>}> $matches  Matches (text_domain: the header).
	 * @param string                                                                                                          $locale   Locale.
	 * @param array<int, string>                                                                                              $reserved Folder names and text domains of every installed plugin and theme.
	 * @return array<int, array{type: string, slug: string, name: string, text_domain: string, files: array<string, string>}>
	 */
	public static function resolve_matches( array $matches, string $locale, array $reserved ): array {
		foreach ( $matches as $i => $match ) {
			$matches[ $i ]['text_domain'] = self::resolve(
				$match['slug'],
				$match['text_domain'] ?? '',
				array_map( 'strval', array_keys( $match['files'] ) ),
				$locale,
				$reserved
			);
		}

		return $matches;
	}

	/**
	 * Folder names and header text domains of every installed plugin and theme.
	 *
	 * @return array<int, string>
	 */
	public static function reserved_names(): array {
		$names = array_merge(
			array_keys( LocalScanner::get_installed_plugins() ),
			array_keys( LocalScanner::get_installed_themes() ),
			array_values( LocalScanner::get_text_domains( 'plugin' ) ),
			array_values( LocalScanner::get_text_domains( 'theme' ) )
		);

		return array_values( array_unique( array_map( 'strval', $names ) ) );
	}

	/**
	 * The distinct `{prefix}` parts of `{prefix}-{locale}.mo|.po|-{md5}.json`
	 * names directly in the folder, the folder name left out.
	 *
	 * @param array<int, string> $names  File names.
	 * @param string             $slug   Folder name.
	 * @param string             $locale Locale.
	 * @return array<int, string>
	 */
	private static function prefixes( array $names, string $slug, string $locale ): array {
		$pattern  = '/^(.+)-' . preg_quote( $locale, '/' ) . '(?:\.mo|\.po|-[0-9a-f]{32}\.json)$/D';
		$prefixes = [];

		foreach ( $names as $name ) {
			$name = (string) $name;

			if ( ! str_contains( $name, '/' ) && 1 === preg_match( $pattern, $name, $m ) && $m[1] !== $slug ) {
				$prefixes[ $m[1] ] = true;
			}
		}

		return array_map( 'strval', array_keys( $prefixes ) );
	}
}
