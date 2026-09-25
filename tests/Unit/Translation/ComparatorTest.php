<?php
/**
 * Tests for Comparator: the cache short-circuit and the pure pairing and
 * filtering steps (the status decision is StatusResolverTest).
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Translation;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\Options;
use LightweightPlugins\Translate\Translation\Comparator;
use LightweightPlugins\Translate\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Translate\Translation\Comparator
 */
final class ComparatorTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
	}

	protected function tearDown(): void {
		Options::clear_cache();
		parent::tearDown();
	}

	public function test_compare_all_returns_the_cached_result_without_contacting_github(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( $args, $defaults = [] ): array => array_merge( (array) $defaults, (array) $args )
		);

		$cached = [ 'woocommerce' ];
		Functions\when( 'get_transient' )->justReturn( $cached );

		// The fact that fetch_tree()'s HTTP call is never made is the
		// behaviour under test: it proves the cache genuinely short-circuits
		// the network fetch instead of merely returning a value that
		// happens to match.
		Functions\expect( 'wp_remote_get' )->never();

		$this->assertSame( $cached, Comparator::compare_all() );
	}

	/**
	 * A theme sharing its directory name with a plugin used to be dropped
	 * (its files were filed under the plugin).
	 */
	public function test_match_lists_a_theme_that_shares_its_slug_with_a_plugin(): void {
		$remote = [
			'plugin' => [ 'astra' => [ 'astra-hu_HU.mo' => 'p' ] ],
			'theme'  => [ 'astra' => [ 'astra-hu_HU.mo' => 't' ] ],
		];

		$matches = Comparator::match( $remote, [ 'astra' => 'Astra Addon' ], [ 'astra' => 'Astra' ] );

		$this->assertSame(
			[
				[
					'type'  => 'plugin',
					'slug'  => 'astra',
					'name'  => 'Astra Addon',
					'files' => [ 'astra-hu_HU.mo' => 'p' ],
				],
				[
					'type'  => 'theme',
					'slug'  => 'astra',
					'name'  => 'Astra',
					'files' => [ 'astra-hu_HU.mo' => 't' ],
				],
			],
			$matches
		);
	}

	public function test_match_skips_installed_items_without_a_repository_folder_and_the_other_way_round(): void {
		$remote = [
			'plugin' => [ 'woocommerce' => [ 'woocommerce-hu_HU.mo' => 'x' ] ],
			'theme'  => [],
		];

		$matches = Comparator::match( $remote, [ 'akismet' => 'Akismet' ], [ 'woocommerce' => 'Not a plugin' ] );

		$this->assertSame( [], $matches );
	}

	public function test_match_accepts_numeric_slugs_that_php_turned_into_integer_keys(): void {
		$remote = [
			'plugin' => [ '2048' => [ '2048-hu_HU.mo' => 'x' ] ],
			'theme'  => [],
		];

		$matches = Comparator::match( $remote, [ '2048' => 'Game' ], [] );

		$this->assertSame( '2048', $matches[0]['slug'] );
	}

	public function test_installable_drops_files_the_file_name_policy_rejects(): void {
		$files = [
			'akismet-hu_HU.mo'       => 'a',
			'akismet-hu_HU.l10n.php' => 'b',
			'other-hu_HU.mo'         => 'c',
		];

		$this->assertSame( [ 'akismet-hu_HU.mo' => 'a' ], Comparator::installable( $files, 'akismet', 'hu_HU' ) );
	}
}
