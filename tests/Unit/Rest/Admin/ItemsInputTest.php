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
}
