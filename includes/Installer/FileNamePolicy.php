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
 * are allowed: `{domain}-{locale}.mo`, `{domain}-{locale}.po` and the
 * script translations `{domain}-{locale}-{md5}.json`. Executable
 * `.l10n.php` files are never accepted from the repository; they are
 * generated locally from the .mo (see L10nPhpGenerator).
 */
final class FileNamePolicy {

	/**
	 * Plugin or theme directory name. No separators, no leading dot.
	 */
	private const SLUG_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,199}$/D';

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
	 * Check a locale code.
	 *
	 * @param string $locale Locale.
	 * @return bool
	 */
	public static function is_valid_locale( string $locale ): bool {
		return 1 === preg_match( self::LOCALE_PATTERN, $locale );
	}

	/**
	 * Check whether a file name may be installed for the given item.
	 *
	 * @param string $basename File name (no directory part).
	 * @param string $slug     Plugin or theme slug.
	 * @param string $locale   Locale.
	 * @return bool
	 */
	public static function is_allowed_basename( string $basename, string $slug, string $locale ): bool {
		if ( ! self::is_valid_slug( $slug ) || ! self::is_valid_locale( $locale ) ) {
			return false;
		}

		$pattern = '/^' . preg_quote( $slug . '-' . $locale, '/' ) . '(?:\.mo|\.po|-[0-9a-f]{32}\.json)$/D';

		return 1 === preg_match( $pattern, $basename );
	}
}
