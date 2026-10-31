<?php
/**
 * File Name Policy class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Installer;

/**
 * Decides which translation file names may be installed for an item.
 *
 * Only the names WordPress core loads from WP_LANG_DIR/{plugins|themes}
 * are allowed: `{prefix}-{locale}.mo`, `{prefix}-{locale}.po` and the
 * script translations `{prefix}-{locale}-{md5}.json`, where the prefix is
 * the item's directory name or one of its text domains: the header's and
 * the repository folder's (WordPress loads the file named after the text
 * domain the code uses; it can differ from both, see TextDomainResolver).
 * Executable `.l10n.php` files are never accepted from the repository;
 * they are generated locally from the .mo (see L10nPhpGenerator).
 */
final class FileNamePolicy {

	/**
	 * Plugin or theme directory name. No separators, no leading dot.
	 */
	private const SLUG_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,199}$/D';

	/**
	 * Text domain used as a file name prefix. Like a slug, but it may start
	 * with an underscore: WordPress loads `_wswebinar-hu_HU.mo` for the
	 * `_wswebinar` domain (WebinarPress). Never a leading dot or separator.
	 */
	private const TEXT_DOMAIN_PATTERN = '/^[A-Za-z0-9_][A-Za-z0-9._-]{0,199}$/D';

	/**
	 * WordPress locale code, e.g. hu_HU, es, de_DE_formal, pt_PT_ao90.
	 */
	private const LOCALE_PATTERN = '/^[a-z]{2,3}(?:_[A-Z]{2}|_[0-9]{3})?(?:_[a-z0-9]+)?$/D';

	/**
	 * Check a plugin or theme slug.
	 *
	 * @param string $slug Slug.
	 * @return bool
	 */
	public static function is_valid_slug( string $slug ): bool {
		return 1 === preg_match( self::SLUG_PATTERN, $slug ) && ! str_contains( $slug, '..' );
	}

	/**
	 * Check a text domain that may become a file name prefix.
	 *
	 * @param string $domain Text domain.
	 * @return bool
	 */
	public static function is_valid_text_domain( string $domain ): bool {
		return 1 === preg_match( self::TEXT_DOMAIN_PATTERN, $domain ) && ! str_contains( $domain, '..' );
	}

	/**
	 * Check a locale code.
	 *
	 * @param string $locale Locale.
	 * @return bool
	 */
	public static function is_valid_locale( string $locale ): bool {
		return 1 === preg_match( self::LOCALE_PATTERN, $locale );
	}

	/**
	 * File name prefixes allowed for an item: its slug, plus each of its
	 * text domains that is valid and differs from the slug, once.
	 *
	 * @param string                    $slug        Plugin or theme slug (directory name).
	 * @param string|array<int, string> $text_domain Text domain(s) of the item (see TextDomainResolver), '' for none.
	 * @return array<int, string> Empty when the slug itself is invalid.
	 */
	public static function prefixes( string $slug, string|array $text_domain = '' ): array {
		if ( ! self::is_valid_slug( $slug ) ) {
			return [];
		}

		$prefixes = [ $slug ];

		foreach ( (array) $text_domain as $domain ) {
			$domain = (string) $domain;

			if ( '' !== $domain && ! in_array( $domain, $prefixes, true ) && self::is_valid_text_domain( $domain ) ) {
				$prefixes[] = $domain;
			}
		}

		return $prefixes;
	}

	/**
	 * Check whether a file name may be installed for the given item.
	 *
	 * @param string                    $basename    File name (no directory part).
	 * @param string                    $slug        Plugin or theme slug.
	 * @param string                    $locale      Locale.
	 * @param string|array<int, string> $text_domain Text domain(s) of the item, '' for none. Invalid ones are ignored.
	 * @return bool
	 */
	public static function is_allowed_basename( string $basename, string $slug, string $locale, string|array $text_domain = '' ): bool {
		if ( ! self::is_valid_locale( $locale ) ) {
			return false;
		}

		foreach ( self::prefixes( $slug, $text_domain ) as $prefix ) {
			$pattern = '/^' . preg_quote( $prefix . '-' . $locale, '/' ) . '(?:\.mo|\.po|-[0-9a-f]{32}\.json)$/D';

			if ( 1 === preg_match( $pattern, $basename ) ) {
				return true;
			}
		}

		return false;
	}
}
