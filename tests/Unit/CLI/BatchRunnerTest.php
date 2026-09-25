<?php
/**
 * Tests for the CLI BatchRunner.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\CLI;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\CLI\BatchRunner;
use LightweightPlugins\Translate\Options;
use LightweightPlugins\Translate\Tests\Unit\MonkeyTestCase;
use LightweightPlugins\Translate\Translation\TranslationItem;
use WP_CLI;
use WP_Error;

/**
 * @covers \LightweightPlugins\Translate\CLI\BatchRunner
 */
final class BatchRunnerTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
		WP_CLI::reset();
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_parse_args' )->alias( static fn ( $args, $defaults ): array => array_merge( (array) $defaults, (array) $args ) );
		Functions\when( 'WP_CLI\Utils\make_progress_bar' )->justReturn(
			new class() {
				public function tick(): void {}
				public function finish(): void {}
			}
		);
	}

	protected function tearDown(): void {
		Options::clear_cache();
		parent::tearDown();
	}

	private function item( string $slug ): TranslationItem {
		return new TranslationItem( $slug, $slug, 'plugin', TranslationItem::STATUS_UP_TO_DATE, 1, '', [] );
	}

	/**
	 * Before 1.2.0 "delete --all" counted every item as deleted.
	 */
	public function test_a_failing_item_is_reported_and_not_counted_and_the_cache_is_cleared(): void {
		Functions\expect( 'delete_transient' )->atLeast()->once();

		BatchRunner::run(
			[ $this->item( 'good' ), $this->item( 'bad' ) ],
			static fn ( string $slug ) => 'bad' === $slug ? new WP_Error( 'x', 'Nope.' ) : [ 'deleted' => [ 'a' ] ],
			'Deleting translations',
			'Deleted %d translation(s).'
		);

		$this->assertSame(
			[
				[ 'warning', 'bad: Nope.' ],
				[ 'success', 'Deleted 1 translation(s). 1 error(s).' ],
			],
			WP_CLI::$calls
		);
	}
}
