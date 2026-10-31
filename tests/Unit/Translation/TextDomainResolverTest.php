<?php
/**
 * Tests for TextDomainResolver: the header first, else the single file name
 * prefix of the repository folder, when that is safe.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Translation;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\Tests\Unit\MonkeyTestCase;
use LightweightPlugins\Translate\Translation\TextDomainResolver;

/**
 * @covers \LightweightPlugins\Translate\Translation\TextDomainResolver
 */
final class TextDomainResolverTest extends MonkeyTestCase {

	private const RESERVED = [ 'admin-menu-editor-pro', 'woocommerce', 'akismet', 'astra' ];

	/**
	 * @param array<int, string> $names Repository file names.
	 */
	private function resolve( array $names, string $slug = 'admin-menu-editor-pro', string $header = '' ): string {
		return TextDomainResolver::resolve( $slug, $header, $names, 'hu_HU', self::RESERVED );
	}

	/**
	 * Admin Menu Editor Pro has no Text Domain header; the repository names
	 * the files after the domain from its code.
	 */
	public function test_without_a_header_the_single_prefix_of_the_folder_is_the_domain(): void {
		$this->assertSame(
			'admin-menu-editor',
			$this->resolve( [ 'admin-menu-editor-hu_HU.mo', 'admin-menu-editor-hu_HU.po', 'admin-menu-editor-hu_HU-0123456789abcdef0123456789abcdef.json' ] )
		);
	}

	public function test_the_folder_named_files_do_not_count_as_a_second_prefix(): void {
		$this->assertSame( 'admin-menu-editor', $this->resolve( [ 'admin-menu-editor-pro-hu_HU.mo', 'admin-menu-editor-hu_HU.mo' ] ) );
	}

	public function test_two_different_prefixes_give_no_domain(): void {
		$this->assertSame( '', $this->resolve( [ 'admin-menu-editor-hu_HU.mo', 'ame-hu_HU.mo', 'admin-menu-editor-pro-hu_HU.mo' ] ) );
	}

	public function test_a_prefix_that_is_another_installed_items_folder_or_domain_is_refused(): void {
		$this->assertSame( '', $this->resolve( [ 'woocommerce-hu_HU.mo' ], 'woo-addon' ) );
		$this->assertSame( '', TextDomainResolver::resolve( 'woo-addon', '', [ 'wc-hu_HU.mo' ], 'hu_HU', [ 'woo-addon', 'wc' ] ) );
	}

	public function test_a_valid_header_wins_over_the_repository_prefix(): void {
		$this->assertSame( 'ame', $this->resolve( [ 'admin-menu-editor-hu_HU.mo' ], 'admin-menu-editor-pro', 'ame' ) );
		$this->assertSame( 'woocommerce', $this->resolve( [ 'other-hu_HU.mo' ], 'woocommerce', 'woocommerce' ) );
	}

	public function test_an_invalid_header_counts_as_none(): void {
		$this->assertSame( 'admin-menu-editor', $this->resolve( [ 'admin-menu-editor-hu_HU.mo' ], 'admin-menu-editor-pro', 'bad domain' ) );
	}

	public function test_only_allowed_names_directly_in_the_folder_give_a_prefix(): void {
		$this->assertSame(
			'',
			$this->resolve( [ 'admin-menu-editor-hu_HU.l10n.php', 'admin-menu-editor-hu_HU.txt', 'admin-menu-editor-de_DE.mo', 'sub/x-hu_HU.mo' ] )
		);
		$this->assertSame( 'admin-menu-editor', $this->resolve( [ 'readme.txt', 'admin-menu-editor-hu_HU.mo', 'sub/x-hu_HU.mo' ] ) );
	}

	public function test_a_prefix_that_is_not_a_valid_slug_is_refused(): void {
		$this->assertSame( '', $this->resolve( [ '..-hu_HU.mo' ] ) );
		$this->assertSame( '', $this->resolve( [ '.hidden-hu_HU.mo' ] ) );
		$this->assertSame( '', $this->resolve( [ 'a..b-hu_HU.mo' ] ) );
	}

	public function test_nothing_but_the_folder_named_files_gives_no_domain(): void {
		$this->assertSame( '', $this->resolve( [ 'admin-menu-editor-pro-hu_HU.mo' ] ) );
		$this->assertSame( '', $this->resolve( [] ) );
	}

	public function test_resolve_matches_fills_in_each_match(): void {
		$matches = TextDomainResolver::resolve_matches(
			[
				[
					'type'        => 'plugin',
					'slug'        => 'admin-menu-editor-pro',
					'name'        => 'AME Pro',
					'text_domain' => '',
					'files'       => [ 'admin-menu-editor-hu_HU.mo' => 'a' ],
				],
				[
					'type'  => 'plugin',
					'slug'  => 'woo-addon',
					'name'  => 'Addon',
					'files' => [ 'woocommerce-hu_HU.mo' => 'b' ],
				],
			],
			'hu_HU',
			self::RESERVED
		);

		$this->assertSame( 'admin-menu-editor', $matches[0]['text_domain'] );
		$this->assertSame( '', $matches[1]['text_domain'] );
	}

	public function test_reserved_names_are_every_installed_folder_and_header_domain(): void {
		$theme = new class() {
			/**
			 * @param string $key Header.
			 */
			public function get( string $key ): string {
				return 'TextDomain' === $key ? 'astra-td' : 'Astra';
			}
		};
		Functions\when( 'get_plugins' )->justReturn(
			[
				'woocommerce/woocommerce.php'             => [ 'Name' => 'WooCommerce', 'TextDomain' => 'woocommerce' ],
				'sitepress-multilingual-cms/sitepress.php' => [ 'Name' => 'WPML' ],
				'complianz-gdpr-premium/c.php'            => [ 'Name' => 'Complianz', 'TextDomain' => 'complianz-gdpr' ],
			]
		);
		Functions\when( 'wp_get_themes' )->justReturn( [ 'astra' => $theme ] );

		$reserved = TextDomainResolver::reserved_names();
		sort( $reserved );

		$this->assertSame( [ 'astra', 'astra-td', 'complianz-gdpr', 'complianz-gdpr-premium', 'sitepress-multilingual-cms', 'woocommerce' ], $reserved );
	}
}
