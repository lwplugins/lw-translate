<?php
/**
 * Tests for the Options class (defaults, merging, caching).
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\Options;

/**
 * @covers \LightweightPlugins\Translate\Options
 */
final class OptionsTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
	}

	protected function tearDown(): void {
		Options::clear_cache();
		parent::tearDown();
	}

	public function test_get_defaults_returns_tone_locale_and_cache_ttl(): void {
		$this->assertSame(
			[
				'tone'      => 'formal',
				'locale'    => 'hu_HU',
				'cache_ttl' => 43200,
			],
			Options::get_defaults()
		);
	}

	public function test_get_all_falls_back_to_defaults_when_nothing_saved(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( $args, $defaults = [] ): array => array_merge( (array) $defaults, (array) $args )
		);

		$this->assertSame( Options::get_defaults(), Options::get_all() );
	}

	public function test_get_all_backfills_a_key_missing_from_older_saved_data(): void {
		// Simulates data written by a version of the plugin that predates
		// "cache_ttl": the saved array simply doesn't have it.
		Functions\when( 'get_option' )->justReturn( [ 'locale' => 'de_DE' ] );
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( $args, $defaults = [] ): array => array_merge( (array) $defaults, (array) $args )
		);

		$options = Options::get_all();

		$this->assertSame( 'de_DE', $options['locale'] );
		$this->assertSame( 43200, $options['cache_ttl'] );
	}

	public function test_get_returns_the_saved_value_for_a_known_key(): void {
		Functions\when( 'get_option' )->justReturn( [ 'tone' => 'informal' ] );
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( $args, $defaults = [] ): array => array_merge( (array) $defaults, (array) $args )
		);

		$this->assertSame( 'informal', Options::get( 'tone' ) );
	}

	public function test_get_returns_null_for_an_unknown_key_with_no_default(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( $args, $defaults = [] ): array => array_merge( (array) $defaults, (array) $args )
		);

		$this->assertNull( Options::get( 'no_such_option' ) );
	}

	public function test_get_returns_the_caller_supplied_default_for_an_unknown_key(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( $args, $defaults = [] ): array => array_merge( (array) $defaults, (array) $args )
		);

		$this->assertSame( 'fallback', Options::get( 'no_such_option', 'fallback' ) );
	}

	/**
	 * The array_key_exists() branch runs before the caller-supplied $default
	 * is ever considered, so a saved value always wins over it.
	 */
	public function test_get_ignores_the_caller_supplied_default_when_the_key_already_exists(): void {
		Functions\when( 'get_option' )->justReturn( [ 'cache_ttl' => 3600 ] );
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( $args, $defaults = [] ): array => array_merge( (array) $defaults, (array) $args )
		);

		$this->assertSame( 3600, Options::get( 'cache_ttl', 999 ) );
	}

	public function test_save_persists_via_update_option_and_returns_its_result(): void {
		Functions\expect( 'update_option' )
			->once()
			->with( Options::OPTION_NAME, [ 'tone' => 'informal' ] )
			->andReturn( true );

		$this->assertTrue( Options::save( [ 'tone' => 'informal' ] ) );
	}

	public function test_save_updates_the_in_memory_cache_so_get_all_skips_get_option(): void {
		Functions\when( 'update_option' )->justReturn( true );
		Options::save( [ 'tone' => 'informal' ] );

		// If get_all() re-read from the DB instead of the cache it would
		// need get_option() again; asserting it is never called proves the
		// cache (not a fresh lookup) is what backs the next get_all() call.
		Functions\expect( 'get_option' )->never();

		$this->assertSame( [ 'tone' => 'informal' ], Options::get_all() );
	}

	public function test_clear_cache_forces_get_all_to_read_get_option_again(): void {
		Functions\when( 'update_option' )->justReturn( true );
		Options::save( [ 'tone' => 'informal' ] );

		Options::clear_cache();

		Functions\expect( 'get_option' )->once()->andReturn( [ 'tone' => 'formal' ] );
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( $args, $defaults = [] ): array => array_merge( (array) $defaults, (array) $args )
		);

		$this->assertSame( 'formal', Options::get( 'tone' ) );
	}
}
