<?php
/**
 * Text Domain Resolver class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Translation;

use LightweightPlugins\Translate\Api\TreeParser;
use LightweightPlugins\Translate\Installer\FileNamePolicy;

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
 * woocommerce-hu_HU.mo), compared in lower case, and not taken by a second
 * installed item's folder too (then neither gets it).
 *
 * Known limits: it cannot see domains set only in code by other items,
 * mu-plugins and drop-ins, or plugins not installed yet; keeping each
 * repository folder to the item's own domain is the repository's job.
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

		// Every installed item is resolved, so a prefix that two of them
		// would take is refused here exactly as in the comparison.
		$matches = self::resolve_matches(
			Comparator::match(
				TreeParser::parse( $tree, $tone, $locale ),
				LocalScanner::get_installed_plugins(),
				LocalScanner::get_installed_themes(),
				[
					'plugin' => LocalScanner::get_text_domains( 'plugin' ),
					'theme'  => LocalScanner::get_text_domains( 'theme' ),
				]
			),
			$locale,
			self::reserved_names()
		);

		foreach ( $matches as $match ) {
			if ( $match['type'] === $type && $match['slug'] === $slug ) {
				return $match['text_domain'];
			}
		}

		return '';
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

		// Compared in lower case: on a case-insensitive filesystem (APFS,
		// NTFS) WooCommerce-hu_HU.mo is woocommerce-hu_HU.mo.
		$reserved = array_map( static fn( $name ): string => strtolower( (string) $name ), $reserved );

		if ( ! FileNamePolicy::is_valid_slug( $prefix ) || in_array( strtolower( $prefix ), $reserved, true ) ) {
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
		$from_folder = [];
		$claims      = [];

		foreach ( $matches as $i => $match ) {
			$header                       = $match['text_domain'] ?? '';
			$matches[ $i ]['text_domain'] = self::resolve(
				$match['slug'],
				$header,
				array_map( 'strval', array_keys( $match['files'] ) ),
				$locale,
				$reserved
			);

			if ( '' !== $matches[ $i ]['text_domain'] && ! FileNamePolicy::is_valid_slug( $header ) ) {
				$key               = strtolower( $matches[ $i ]['text_domain'] );
				$from_folder[ $i ] = $key;
				$claims[ $key ]    = ( $claims[ $key ] ?? 0 ) + 1;
			}
		}

		// A prefix from the repository belongs to one item only.
		foreach ( $from_folder as $i => $key ) {
			if ( $claims[ $key ] > 1 ) {
				$matches[ $i ]['text_domain'] = '';
			}
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
