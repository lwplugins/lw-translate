<?php
/**
 * Tests for FileNamePolicy (which file names may be installed).
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Installer;

use LightweightPlugins\Translate\Installer\FileNamePolicy;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\Translate\Installer\FileNamePolicy
 */
final class FileNamePolicyTest extends TestCase {

	/**
	 * @dataProvider provide_allowed_names
	 */
	public function test_accepts_the_file_names_wordpress_loads_for_the_item( string $name ): void {
		$this->assertTrue( FileNamePolicy::is_allowed_basename( $name, 'woocommerce', 'hu_HU' ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function provide_allowed_names(): array {
		return [
			'mo'           => [ 'woocommerce-hu_HU.mo' ],
			'po'           => [ 'woocommerce-hu_HU.po' ],
			'script json'  => [ 'woocommerce-hu_HU-0123456789abcdef0123456789abcdef.json' ],
		];
	}

	/**
	 * Upstream-supplied PHP must never be installed: WordPress 6.5+ runs
	 * `.l10n.php` translation files with include.
	 *
	 * @dataProvider provide_rejected_names
	 */
	public function test_rejects_any_other_file_name( string $name ): void {
		$this->assertFalse( FileNamePolicy::is_allowed_basename( $name, 'woocommerce', 'hu_HU' ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function provide_rejected_names(): array {
		return [
			'l10n php of the item'   => [ 'woocommerce-hu_HU.l10n.php' ],
			'arbitrary l10n php'     => [ 'evil.l10n.php' ],
			'plain php'              => [ 'woocommerce-hu_HU.php' ],
			'another plugin mo'      => [ 'akismet-hu_HU.mo' ],
			'another locale mo'      => [ 'woocommerce-de_DE.mo' ],
			'short json hash'        => [ 'woocommerce-hu_HU-abcd1234.json' ],
			'uppercase json hash'    => [ 'woocommerce-hu_HU-0123456789ABCDEF0123456789ABCDEF.json' ],
			'double extension'       => [ 'woocommerce-hu_HU.mo.php' ],
			'trailing newline'       => [ "woocommerce-hu_HU.mo\n" ],
			'windows traversal'      => [ '..\\..\\woocommerce-hu_HU.mo' ],
			'unix traversal'         => [ '../woocommerce-hu_HU.mo' ],
			'subfolder'              => [ 'sub/woocommerce-hu_HU.mo' ],
			'empty'                  => [ '' ],
		];
	}

	public function test_rejects_every_name_when_the_slug_is_invalid(): void {
		$this->assertFalse( FileNamePolicy::is_allowed_basename( '..-hu_HU.mo', '..', 'hu_HU' ) );
	}

	/**
	 * @dataProvider provide_valid_slugs
	 */
	public function test_accepts_plugin_and_theme_directory_names_as_slugs( string $slug ): void {
		$this->assertTrue( FileNamePolicy::is_valid_slug( $slug ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function provide_valid_slugs(): array {
		return [
			'plain'      => [ 'woocommerce' ],
			'hyphenated' => [ 'wordpress-seo' ],
			'dotted'     => [ 'theme.v2' ],
			'underscore' => [ 'my_plugin' ],
			'mixed case' => [ 'Divi' ],
		];
	}

	/**
	 * @dataProvider provide_invalid_slugs
	 */
	public function test_rejects_slugs_that_could_leave_the_file_name( string $slug ): void {
		$this->assertFalse( FileNamePolicy::is_valid_slug( $slug ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function provide_invalid_slugs(): array {
		return [
			'empty'        => [ '' ],
			'slash'        => [ 'a/b' ],
			'backslash'    => [ 'a\\b' ],
			'dot dot'      => [ '..' ],
			'leading dot'  => [ '.hidden' ],
			'embedded dot' => [ 'a..b' ],
			'space'        => [ 'a b' ],
			'nul'          => [ "a\0b" ],
			'newline'      => [ "abc\n" ],
		];
	}

	/**
	 * @dataProvider provide_valid_locales
	 */
	public function test_accepts_wordpress_locale_codes( string $locale ): void {
		$this->assertTrue( FileNamePolicy::is_valid_locale( $locale ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function provide_valid_locales(): array {
		return [
			'language and region' => [ 'hu_HU' ],
			'language only'       => [ 'es' ],
			'three letters'       => [ 'ary' ],
			'variant'             => [ 'de_DE_formal' ],
			'numbered variant'    => [ 'pt_PT_ao90' ],
		];
	}

	/**
	 * @dataProvider provide_invalid_locales
	 */
	public function test_rejects_malformed_locales( string $locale ): void {
		$this->assertFalse( FileNamePolicy::is_valid_locale( $locale ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function provide_invalid_locales(): array {
		return [
			'empty'     => [ '' ],
			'slash'     => [ 'hu/HU' ],
			'dots'      => [ '..' ],
			'uppercase' => [ 'HU_HU' ],
			'newline'   => [ "hu_HU\n" ],
		];
	}

	/**
	 * The folder name and the text domain differ for e.g. premium builds;
	 * WordPress loads the file named after the text domain.
	 *
	 * @dataProvider provide_text_domain_names
	 */
	public function test_accepts_the_text_domain_as_prefix( string $name ): void {
		$this->assertTrue( FileNamePolicy::is_allowed_basename( $name, 'complianz-gdpr-premium', 'hu_HU', 'complianz-gdpr' ) );
		$this->assertTrue( FileNamePolicy::is_allowed_basename( 'complianz-gdpr-premium-hu_HU.mo', 'complianz-gdpr-premium', 'hu_HU', 'complianz-gdpr' ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function provide_text_domain_names(): array {
		return [
			'mo'          => [ 'complianz-gdpr-hu_HU.mo' ],
			'po'          => [ 'complianz-gdpr-hu_HU.po' ],
			'script json' => [ 'complianz-gdpr-hu_HU-0123456789abcdef0123456789abcdef.json' ],
		];
	}

	/**
	 * @dataProvider provide_rejected_with_text_domain
	 */
	public function test_the_text_domain_opens_no_other_names( string $name ): void {
		$this->assertFalse( FileNamePolicy::is_allowed_basename( $name, 'complianz-gdpr-premium', 'hu_HU', 'complianz-gdpr' ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function provide_rejected_with_text_domain(): array {
		return [
			'l10n php of the domain' => [ 'complianz-gdpr-hu_HU.l10n.php' ],
			'l10n php of the slug'   => [ 'complianz-gdpr-premium-hu_HU.l10n.php' ],
			'foreign prefix'         => [ 'akismet-hu_HU.mo' ],
			'another locale'         => [ 'complianz-gdpr-de_DE.mo' ],
			'unix traversal'         => [ '../complianz-gdpr-hu_HU.mo' ],
			'windows traversal'      => [ '..\\complianz-gdpr-hu_HU.mo' ],
		];
	}

	/**
	 * @dataProvider provide_invalid_text_domains
	 */
	public function test_an_invalid_text_domain_is_ignored( string $domain, string $name ): void {
		$this->assertFalse( FileNamePolicy::is_allowed_basename( $name, 'woocommerce', 'hu_HU', $domain ) );
		$this->assertTrue( FileNamePolicy::is_allowed_basename( 'woocommerce-hu_HU.mo', 'woocommerce', 'hu_HU', $domain ) );
		$this->assertSame( [ 'woocommerce' ], FileNamePolicy::prefixes( 'woocommerce', $domain ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function provide_invalid_text_domains(): array {
		return [
			'dot dot'     => [ '..', '..-hu_HU.mo' ],
			'traversal'   => [ '../evil', '../evil-hu_HU.mo' ],
			'backslash'   => [ 'a\\b', 'a\\b-hu_HU.mo' ],
			'leading dot' => [ '.hidden', '.hidden-hu_HU.mo' ],
		];
	}

	public function test_prefixes_list_the_slug_and_a_differing_text_domain_once(): void {
		$this->assertSame( [ 'admin-menu-editor-pro', 'admin-menu-editor' ], FileNamePolicy::prefixes( 'admin-menu-editor-pro', 'admin-menu-editor' ) );
		$this->assertSame( [ 'akismet' ], FileNamePolicy::prefixes( 'akismet', 'akismet' ) );
		$this->assertSame( [ 'akismet' ], FileNamePolicy::prefixes( 'akismet' ) );
		$this->assertSame( [], FileNamePolicy::prefixes( '..', 'akismet' ) );
	}
}
