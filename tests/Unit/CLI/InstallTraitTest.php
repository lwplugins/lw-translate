<?php
/**
 * Tests that the WP-CLI install/delete commands invalidate the comparison cache.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\CLI;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\CLI\InstallTrait;
use LightweightPlugins\Translate\Options;
use LightweightPlugins\Translate\Tests\Unit\MonkeyTestCase;
use WP_CLI;

/**
 * @covers \LightweightPlugins\Translate\CLI\InstallTrait
 */
final class InstallTraitTest extends MonkeyTestCase {

	/**
	 * Transients deleted during the test.
	 *
	 * @var array<int, string>
	 */
	private array $deleted = [];

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
		WP_CLI::reset();
		$this->deleted = [];

		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( $args, $defaults = [] ): array => array_merge( (array) $defaults, (array) $args )
		);
		Functions\when( 'is_wp_error' )->alias( static fn ( $thing ): bool => $thing instanceof \WP_Error );
		Functions\when( '__' )->returnArg();
		Functions\when( 'delete_transient' )->alias(
			function ( string $key ): bool {
				$this->deleted[] = $key;
				return true;
			}
		);
	}

	protected function tearDown(): void {
		Options::clear_cache();
		parent::tearDown();
	}

	private function command(): object {
		return new class() {
			use InstallTrait;
		};
	}

	/**
	 * Before 1.2.0 a CLI install left the admin list on "Not installed"
	 * for up to an hour.
	 */
	public function test_install_clears_the_comparison_cache_even_when_it_fails(): void {
		try {
			$this->command()->install( [ '../bad' ], [] );
			$this->fail( 'An invalid slug must stop the command.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'Invalid plugin or theme slug.', $e->getMessage() );
		}

		$this->assertContains( 'lw_translate_compare_hu_HU_formal', $this->deleted );
	}

	public function test_delete_clears_the_comparison_cache(): void {
		try {
			$this->command()->delete( [ '../bad' ], [] );
		} catch ( \RuntimeException $e ) {
			unset( $e );
		}

		$this->assertContains( 'lw_translate_compare_hu_HU_informal', $this->deleted );
	}
}
