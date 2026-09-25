<?php
/**
 * Tests for CompareCache (comparison result cache keys and invalidation).
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Translation;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\Options;
use LightweightPlugins\Translate\Translation\CompareCache;
use LightweightPlugins\Translate\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Translate\Translation\CompareCache
 */
final class CompareCacheTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( $args, $defaults = [] ): array => array_merge( (array) $defaults, (array) $args )
		);
	}

	protected function tearDown(): void {
		Options::clear_cache();
		parent::tearDown();
	}

	public function test_key_combines_locale_and_tone(): void {
		$this->assertSame( 'lw_translate_compare_hu_HU_informal', CompareCache::key( 'hu_HU', 'informal' ) );
	}

	/**
	 * The raw SQL delete it replaces never reached a persistent object
	 * cache (Redis, Memcached), so the list stayed stale after an install.
	 * Going through delete_transient() works with and without one.
	 */
	public function test_clear_deletes_every_tone_of_the_saved_and_default_locale_through_the_transient_api(): void {
		Functions\when( 'get_option' )->justReturn( [ 'locale' => 'de_DE' ] );

		$deleted = [];
		Functions\when( 'delete_transient' )->alias(
			static function ( string $key ) use ( &$deleted ): bool {
				$deleted[] = $key;
				return true;
			}
		);

		CompareCache::clear();

		sort( $deleted );
		$this->assertSame(
			[
				'lw_translate_compare_de_DE_formal',
				'lw_translate_compare_de_DE_informal',
				'lw_translate_compare_hu_HU_formal',
				'lw_translate_compare_hu_HU_informal',
			],
			$deleted
		);
	}

	public function test_clear_does_not_repeat_the_default_locale(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\expect( 'delete_transient' )->times( 2 );

		CompareCache::clear();
	}

	public function test_clear_all_also_drops_the_tree_cache(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$deleted = [];
		Functions\when( 'delete_transient' )->alias(
			static function ( string $key ) use ( &$deleted ): bool {
				$deleted[] = $key;
				return true;
			}
		);

		CompareCache::clear_all();

		$this->assertContains( 'lw_translate_tree_cache', $deleted );
		$this->assertContains( 'lw_translate_compare_hu_HU_formal', $deleted );
	}

	public function test_no_class_clears_the_comparison_cache_with_raw_sql_any_more(): void {
		$root = dirname( __DIR__, 3 ) . '/includes';
		$hits = [];

		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $files as $file ) {
			if ( str_contains( (string) file_get_contents( (string) $file ), '_transient_lw_translate_compare_' ) ) {
				$hits[] = (string) $file;
			}
		}

		$this->assertSame( [], $hits );
	}
}
