<?php
/**
 * Tests for ItemActions (per-item results).
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Rest\Admin;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\Rest\Admin\ItemActions;
use LightweightPlugins\Translate\Tests\Unit\MonkeyTestCase;
use WP_Error;

/**
 * @covers \LightweightPlugins\Translate\Rest\Admin\ItemActions
 */
final class ItemActionsTest extends MonkeyTestCase {

	private const ITEMS = [
		[ 'type' => 'plugin', 'slug' => 'good' ],
		[ 'type' => 'theme', 'slug' => 'bad' ],
	];

	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
	}

	private function actions(): ItemActions {
		return new ItemActions(
			static fn ( string $slug ) => 'good' === $slug
				? [ 'installed' => [ 'good-hu_HU.mo' ], 'skipped' => [ 'readme.txt' ] ]
				: new WP_Error( 'no_files', 'No translation files found for this item.' ),
			static fn ( string $slug ) => 'good' === $slug
				? [ 'deleted' => [ 'good-hu_HU.mo' ] ]
				: new WP_Error( 'delete_failed', 'Could not delete: bad-hu_HU.po', [ 'deleted' => [ 'bad-hu_HU.mo' ] ] )
		);
	}

	public function test_install_reports_every_item_even_after_a_failure(): void {
		$results = $this->actions()->install( self::ITEMS );

		$this->assertSame(
			[
				'type'    => 'plugin',
				'slug'    => 'good',
				'ok'      => true,
				'message' => 'Translation installed successfully. Ignored files that are not valid translation files for this item: readme.txt',
				'skipped' => [ 'readme.txt' ],
			],
			$results[0]
		);
		$this->assertSame(
			[
				'type'    => 'theme',
				'slug'    => 'bad',
				'ok'      => false,
				'message' => 'No translation files found for this item.',
				'skipped' => [],
			],
			$results[1]
		);
	}

	/**
	 * Before 1.2.0 delete always answered "Translation deleted."
	 */
	public function test_delete_reports_failures_honestly_with_what_was_removed(): void {
		$results = $this->actions()->delete( self::ITEMS );

		$this->assertTrue( $results[0]['ok'] );
		$this->assertSame( [ 'good-hu_HU.mo' ], $results[0]['deleted'] );
		$this->assertFalse( $results[1]['ok'] );
		$this->assertSame( 'Could not delete: bad-hu_HU.po', $results[1]['message'] );
		$this->assertSame( [ 'bad-hu_HU.mo' ], $results[1]['deleted'] );
	}
}
