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
		return TextDomainResolver::folder_prefix( $slug, $header, $names, 'hu_HU', self::RESERVED );
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
		$this->assertSame( '', TextDomainResolver::folder_prefix( 'woo-addon', '', [ 'wc-hu_HU.mo' ], 'hu_HU', [ 'woo-addon', 'wc' ] ) );
	}

	/**
	 * On a case-insensitive filesystem (APFS, NTFS) WooCommerce-hu_HU.mo is
	 * the same file as woocommerce-hu_HU.mo.
	 */
	public function test_a_reserved_name_is_refused_in_any_letter_case(): void {
		$this->assertSame( '', $this->resolve( [ 'WooCommerce-hu_HU.mo' ], 'woo-addon' ) );
		$this->assertSame( '', TextDomainResolver::folder_prefix( 'woo-addon', '', [ 'wc-hu_HU.mo' ], 'hu_HU', [ 'woo-addon', 'WC' ] ) );
		$this->assertSame( '', $this->resolve( [ 'Admin-Menu-Editor-Pro-hu_HU.mo' ] ), 'Its own folder name in other case is not a domain either.' );
	}

	/**
	 * One prefix can belong to one item only: when two header-less items
	 * would both take it, neither does.
	 */
	public function test_a_prefix_two_items_would_take_goes_to_neither(): void {
		$item = static fn ( string $slug, string $file ): array => [
			'type'        => 'plugin',
			'slug'        => $slug,
			'name'        => $slug,
			'text_domain' => '',
			'files'       => [ $file => 'x' ],
		];

		$matches = TextDomainResolver::resolve_matches(
			[
				$item( 'shared-pro', 'shared-hu_HU.mo' ),
				$item( 'shared-lite', 'Shared-hu_HU.mo' ),
				$item( 'admin-menu-editor-pro', 'admin-menu-editor-hu_HU.mo' ),
			],
			'hu_HU',
			self::RESERVED
		);

		$this->assertSame( [ '', '', 'admin-menu-editor' ], array_column( $matches, 'text_domain' ) );
	}

	public function test_a_header_domain_shared_with_another_item_still_wins(): void {
		$matches = TextDomainResolver::resolve_matches(
			[
				[
					'type'        => 'plugin',
					'slug'        => 'a',
					'name'        => 'A',
					'text_domain' => 'shared',
					'files'       => [ 'shared-hu_HU.mo' => 'x' ],
				],
				[
					'type'        => 'theme',
					'slug'        => 'b',
					'name'        => 'B',
					'text_domain' => 'shared',
					'files'       => [ 'shared-hu_HU.mo' => 'y' ],
				],
			],
			'hu_HU',
			[ 'a', 'b', 'shared' ]
		);

		$this->assertSame( [ 'shared', 'shared' ], array_column( $matches, 'text_domain' ) );
	}

	/**
	 * Admin Menu Editor Pro's header says admin-menu-editor-pro, its code
	 * loads admin-menu-editor: the header does not shut the repository
	 * prefix out.
	 */
	public function test_with_a_header_the_folder_prefix_still_counts(): void {
		$this->assertSame( 'admin-menu-editor', $this->resolve( [ 'admin-menu-editor-hu_HU.mo' ], 'admin-menu-editor-pro', 'admin-menu-editor-pro' ) );
		$this->assertSame(
			'admin-menu-editor',
			$this->resolve( [ 'ame-hu_HU.mo', 'admin-menu-editor-hu_HU.mo', 'admin-menu-editor-pro-hu_HU.po' ], 'admin-menu-editor-pro', 'ame' ),
			'The header-named and folder-named files are left out of the count.'
		);
	}

	public function test_with_a_header_and_two_foreign_prefixes_there_is_no_folder_prefix(): void {
		$this->assertSame( '', $this->resolve( [ 'ame-hu_HU.mo', 'one-hu_HU.mo', 'two-hu_HU.mo' ], 'admin-menu-editor-pro', 'ame' ) );
	}

	public function test_resolve_matches_lists_the_header_and_the_folder_prefix(): void {
		$matches = TextDomainResolver::resolve_matches(
			[
				[
					'type'        => 'plugin',
					'slug'        => 'admin-menu-editor-pro',
					'name'        => 'AME Pro',
					'text_domain' => 'admin-menu-editor-pro',
					'files'       => [ 'admin-menu-editor-hu_HU.mo' => 'a' ],
				],
				[
					'type'        => 'plugin',
					'slug'        => 'akismet',
					'name'        => 'Akismet',
					'text_domain' => 'akismet-td',
					'files'       => [ 'akismet-td-hu_HU.mo' => 'b' ],
				],
			],
			'hu_HU',
			array_merge( self::RESERVED, [ 'akismet-td' ] )
		);

		$this->assertSame( [ 'admin-menu-editor-pro', 'admin-menu-editor' ], $matches[0]['text_domains'] );
		$this->assertSame( 'admin-menu-editor', $matches[0]['text_domain'], 'The domain WordPress loads, from the repository.' );
		$this->assertSame( [ 'akismet-td' ], $matches[1]['text_domains'] );
		$this->assertSame( 'akismet-td', $matches[1]['text_domain'] );
	}

	/**
	 * The one-item rule holds for items with a header as well.
	 */
	public function test_a_prefix_a_header_item_and_a_headerless_item_would_take_goes_to_neither(): void {
		$matches = TextDomainResolver::resolve_matches(
			[
				[
					'type'        => 'plugin',
					'slug'        => 'shared-pro',
					'name'        => 'Pro',
					'text_domain' => 'shared-pro',
					'files'       => [ 'shared-hu_HU.mo' => 'a' ],
				],
				[
					'type'        => 'plugin',
					'slug'        => 'shared-lite',
					'name'        => 'Lite',
					'text_domain' => '',
					'files'       => [ 'shared-hu_HU.mo' => 'b' ],
				],
			],
			'hu_HU',
			[ 'shared-pro', 'shared-lite' ]
		);

		$this->assertSame( [ [ 'shared-pro' ], [] ], array_column( $matches, 'text_domains' ) );
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

	/**
	 * WebinarPress (folder wpwebinarsystem) declares and uses `_wswebinar`.
	 */
	public function test_a_text_domain_starting_with_an_underscore_is_accepted(): void {
		$names = [ '_wswebinar-hu_HU.mo', '_wswebinar-hu_HU.po' ];

		$this->assertSame( '_wswebinar', $this->resolve( $names, 'wpwebinarsystem' ) );

		$matches = TextDomainResolver::resolve_matches(
			[
				[
					'type'        => 'plugin',
					'slug'        => 'wpwebinarsystem',
					'name'        => 'WebinarPress',
					'text_domain' => '_wswebinar',
					'files'       => array_fill_keys( $names, 'sha' ),
				],
			],
			'hu_HU',
			[ 'wpwebinarsystem', '_wswebinar' ]
		);

		$this->assertSame( [ '_wswebinar' ], $matches[0]['text_domains'] );
		$this->assertSame( '_wswebinar', $matches[0]['text_domain'] );
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
