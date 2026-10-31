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
 * Decides which text domains an item's translation files may be named after.
 *
 * Besides the folder name, two are allowed: the TextDomain header (when it
 * is valid) and the single file name prefix of the item's repository
 * folder. The header alone is not enough: many plugins leave it out, and
 * some declare one domain in the header and use another in their code
 * (Admin Menu Editor Pro: header admin-menu-editor-pro, code
 * admin-menu-editor), and WordPress loads the file named after the one in
 * the code. The repository prefix is taken only when it is safe: leaving
 * out the folder-named and header-named files there is exactly one prefix,
 * it is a valid slug, it is not the folder name or header text domain of
 * another installed plugin or theme (an add-on's folder must not ship,
 * say, woocommerce-hu_HU.mo), compared in lower case, and no other
 * installed item's folder offers it too (then neither gets it).
 *
 * Known limits: it cannot see domains set only in code by other items,
 * mu-plugins and drop-ins, or plugins not installed yet; keeping each
 * repository folder to the item's own domain is the repository's job.
 *
 * Every caller (install, delete, the comparison and its status, the local
 * date) gets the domains from here, so they all agree.
 */
final class TextDomainResolver {

	/**
	 * Text domains of one installed item: its valid header domain and its
	 * repository folder's prefix, each when there is one.
	 *
	 * @param array<int, array{type?: string, path?: mixed, sha?: mixed}> $tree   Raw tree. Untrusted remote input.
	 * @param string                                                      $slug   Plugin or theme slug.
	 * @param string                                                      $type   Type: 'plugin' or 'theme'.
	 * @param string                                                      $tone   Translation tone.
	 * @param string                                                      $locale Locale.
	 * @return array<int, string> Empty when neither gives a usable domain.
	 */
	public static function for_item( array $tree, string $slug, string $type, string $tone, string $locale ): array {
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
				return $match['text_domains'];
			}
		}

		$header = LocalScanner::get_text_domain( $type, $slug );

		return FileNamePolicy::is_valid_slug( $header ) ? [ $header ] : [];
	}

	/**
	 * The single file name prefix of the item's repository folder, when safe.
	 *
	 * Does not check whether another item's folder offers the same prefix;
	 * resolve_matches() does.
	 *
	 * @param string             $slug     Plugin or theme slug (directory name).
	 * @param string             $header   TextDomain header, '' for none.
	 * @param array<int, string> $names    File names in the item's repository folder.
	 * @param string             $locale   Locale.
	 * @param array<int, string> $reserved Folder names and header text domains of every installed plugin and theme.
	 * @return string '' when there is none.
	 */
	public static function folder_prefix( string $slug, string $header, array $names, string $locale, array $reserved ): string {
		if ( ! FileNamePolicy::is_valid_slug( $slug ) || ! FileNamePolicy::is_valid_locale( $locale ) ) {
			return '';
		}

		$own      = FileNamePolicy::is_valid_slug( $header ) ? [ $slug, $header ] : [ $slug ];
		$prefixes = self::prefixes( $names, $own, $locale );

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
	 * Fill in the text domains of each comparison match.
	 *
	 * "text_domains" lists the allowed domains (header, then repository
	 * prefix); "text_domain" is the one WordPress most likely loads: the
	 * repository prefix when there is one, else the header.
	 *
	 * @param array<int, array{type: string, slug: string, name: string, text_domain?: string, files: array<string, string>}> $matches  Matches (text_domain: the header).
	 * @param string                                                                                                          $locale   Locale.
	 * @param array<int, string>                                                                                              $reserved Folder names and header text domains of every installed plugin and theme.
	 * @return array<int, array{type: string, slug: string, name: string, text_domain: string, text_domains: array<int, string>, files: array<string, string>}>
	 */
	public static function resolve_matches( array $matches, string $locale, array $reserved ): array {
		$headers  = [];
		$prefixes = [];
		$claims   = [];

		foreach ( $matches as $i => $match ) {
			$header        = $match['text_domain'] ?? '';
			$headers[ $i ] = FileNamePolicy::is_valid_slug( $header ) ? $header : '';
			$prefix        = self::folder_prefix(
				$match['slug'],
				$headers[ $i ],
				array_map( 'strval', array_keys( $match['files'] ) ),
				$locale,
				$reserved
			);

			$prefixes[ $i ] = $prefix;

			if ( '' !== $prefix ) {
				$key            = strtolower( $prefix );
				$claims[ $key ] = ( $claims[ $key ] ?? 0 ) + 1;
			}
		}

		$resolved = [];

		foreach ( $matches as $i => $match ) {
			// A prefix from the repository belongs to one item only.
			$prefix = $prefixes[ $i ];

			if ( '' !== $prefix && $claims[ strtolower( $prefix ) ] > 1 ) {
				$prefix = '';
			}

			$match['text_domains'] = array_values( array_filter( [ $headers[ $i ], $prefix ], static fn( string $d ): bool => '' !== $d ) );
			$match['text_domain']  = '' !== $prefix ? $prefix : $headers[ $i ];
			$resolved[ $i ]        = $match;
		}

		return $resolved;
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
	 * names directly in the folder, the item's own names left out.
	 *
	 * @param array<int, string> $names  File names.
	 * @param array<int, string> $own    Folder name and header domain.
	 * @param string             $locale Locale.
	 * @return array<int, string>
	 */
	private static function prefixes( array $names, array $own, string $locale ): array {
		$pattern  = '/^(.+)-' . preg_quote( $locale, '/' ) . '(?:\.mo|\.po|-[0-9a-f]{32}\.json)$/D';
		$prefixes = [];

		foreach ( $names as $name ) {
			$name = (string) $name;

			if ( ! str_contains( $name, '/' ) && 1 === preg_match( $pattern, $name, $m ) && ! in_array( $m[1], $own, true ) ) {
				$prefixes[ $m[1] ] = true;
			}
		}

		return array_map( 'strval', array_keys( $prefixes ) );
	}
}
