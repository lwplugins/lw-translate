<?php
/**
 * Tests for LocaleCatalog.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Settings;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\Options;
use LightweightPlugins\Translate\Settings\LocaleCatalog;
use LightweightPlugins\Translate\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Translate\Settings\LocaleCatalog
 */
final class LocaleCatalogTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
	}

	protected function tearDown(): void {
		Options::clear_cache();
		parent::tearDown();
	}

	public function test_from_tree_collects_the_locales_of_every_tone_sorted_and_unique(): void {
		$tree = [
			[ 'type' => 'tree', 'path' => 'formal/plugins/hu_HU' ],
			[ 'type' => 'tree', 'path' => 'informal/themes/hu_HU' ],
			[ 'type' => 'tree', 'path' => 'informal/plugins/de_DE' ],
			[ 'type' => 'tree', 'path' => 'formal/plugins/Not A Locale' ],
		];

		$this->assertSame( [ 'de_DE', 'hu_HU' ], LocaleCatalog::from_tree( $tree ) );
	}

	public function test_label_uses_the_native_language_name(): void {
		if ( ! class_exists( 'Locale' ) ) {
			$this->markTestSkipped( 'The intl extension is not loaded.' );
		}

		$this->assertSame( 'Magyar (hu_HU)', LocaleCatalog::label( 'hu_HU' ) );
	}

	public function test_label_falls_back_to_the_code_for_an_unknown_language(): void {
		$this->assertSame( 'xx_XX', LocaleCatalog::label( 'xx_XX' ) );
	}

	public function test_options_pairs_values_and_labels(): void {
		$this->assertSame( [ [ 'value' => 'xx_XX', 'label' => 'xx_XX' ] ], LocaleCatalog::options( [ 'xx_XX' ] ) );
	}

	public function test_offered_falls_back_to_the_default_and_saved_locale_when_github_fails(): void {
		Functions\when( 'get_option' )->justReturn( [ 'locale' => 'sk_SK' ] );
		Functions\when( 'wp_parse_args' )->alias( static fn ( $args, $defaults ): array => array_merge( (array) $defaults, (array) $args ) );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'wp_remote_get' )->justReturn( new \WP_Error( 'http_request_failed', 'down' ) );
		Functions\when( 'is_wp_error' )->alias( static fn ( $thing ): bool => $thing instanceof \WP_Error );

		$this->assertSame( [ 'hu_HU', 'sk_SK' ], LocaleCatalog::offered() );
	}

	public function test_offered_reads_the_cached_tree(): void {
		Functions\when( 'get_transient' )->justReturn(
			[
				'tree'       => [ [ 'type' => 'tree', 'path' => 'formal/plugins/hu_HU' ] ],
				'truncated'  => false,
				'fetched_at' => 1,
			]
		);
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\expect( 'wp_remote_get' )->never();

		$this->assertSame( [ 'hu_HU' ], LocaleCatalog::offered() );
	}

	public function test_offered_without_fetching_never_contacts_github(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_parse_args' )->alias( static fn ( $args, $defaults ): array => array_merge( (array) $defaults, (array) $args ) );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\expect( 'wp_remote_get' )->never();

		$this->assertSame( [ 'hu_HU' ], LocaleCatalog::offered( false ) );
	}
}
