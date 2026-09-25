<?php
/**
 * Tests for ItemsInput.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Rest\Admin;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\Rest\Admin\ItemsInput;
use LightweightPlugins\Translate\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Translate\Rest\Admin\ItemsInput
 */
final class ItemsInputTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
	}

	public function test_valid_items_are_returned_without_duplicates(): void {
		$parsed = ItemsInput::parse(
			[
				[ 'type' => 'plugin', 'slug' => 'akismet' ],
				[ 'type' => 'theme', 'slug' => 'akismet' ],
				[ 'type' => 'plugin', 'slug' => 'akismet' ],
			]
		);

		$this->assertSame( '', $parsed['error'] );
		$this->assertSame(
			[
				[ 'type' => 'plugin', 'slug' => 'akismet' ],
				[ 'type' => 'theme', 'slug' => 'akismet' ],
			],
			$parsed['items']
		);
	}

	/**
	 * @dataProvider provide_bad_input
	 */
	public function test_bad_input_refuses_the_whole_request( mixed $raw ): void {
		$parsed = ItemsInput::parse( $raw );

		$this->assertNotSame( '', $parsed['error'] );
		$this->assertSame( [], $parsed['items'] );
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function provide_bad_input(): array {
		return [
			'missing'        => [ null ],
			'empty list'     => [ [] ],
			'string'         => [ 'plugin:akismet' ],
			'unknown type'   => [ [ [ 'type' => 'mu-plugin', 'slug' => 'akismet' ] ] ],
			'traversal slug' => [ [ [ 'type' => 'plugin', 'slug' => '../akismet' ] ] ],
			'no slug'        => [ [ [ 'type' => 'plugin' ] ] ],
			'slug as array'  => [ [ [ 'type' => 'plugin', 'slug' => [ 'a' ] ] ] ],
			'one bad of two' => [ [ [ 'type' => 'plugin', 'slug' => 'ok' ], 'bad' ] ],
			'not a list'     => [ [ 'a' => [ 'type' => 'plugin', 'slug' => 'ok' ] ] ],
		];
	}

	public function test_more_than_the_batch_limit_is_refused(): void {
		$raw = [];
		for ( $i = 0; $i <= ItemsInput::MAX_ITEMS; $i++ ) {
			$raw[] = [ 'type' => 'plugin', 'slug' => 'p' . $i ];
		}

		$this->assertStringContainsString( (string) ItemsInput::MAX_ITEMS, ItemsInput::parse( $raw )['error'] );
		$this->assertSame( '', ItemsInput::parse( array_slice( $raw, 0, ItemsInput::MAX_ITEMS ) )['error'] );
	}

	/**
	 * The size limit is checked before any item is validated or
	 * de-duplicated, so a huge list is refused without walking it and
	 * duplicates cannot smuggle it under the limit.
	 */
	public function test_the_batch_limit_counts_raw_items_including_duplicates(): void {
		$raw = array_fill( 0, ItemsInput::MAX_ITEMS + 1, [ 'type' => 'plugin', 'slug' => 'same' ] );

		$this->assertStringContainsString( (string) ItemsInput::MAX_ITEMS, ItemsInput::parse( $raw )['error'] );
	}

	public function test_an_oversized_list_of_garbage_reports_the_limit_not_the_first_bad_item(): void {
		$raw = array_fill( 0, 5000, 'garbage' );

		$this->assertStringContainsString( (string) ItemsInput::MAX_ITEMS, ItemsInput::parse( $raw )['error'] );
	}
}
