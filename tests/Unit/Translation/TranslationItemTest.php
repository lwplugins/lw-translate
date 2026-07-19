<?php
/**
 * Tests for the TranslationItem value object.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Translation;

use LightweightPlugins\Translate\Translation\TranslationItem;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\Translate\Translation\TranslationItem
 */
final class TranslationItemTest extends TestCase {

	/**
	 * @dataProvider provide_statuses
	 */
	public function test_has_update_is_true_only_for_the_update_status( string $status, bool $expected ): void {
		$item = $this->make_item( $status );

		$this->assertSame( $expected, $item->has_update() );
	}

	/**
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public static function provide_statuses(): array {
		return [
			'up to date'   => [ TranslationItem::STATUS_UP_TO_DATE, false ],
			'update'       => [ TranslationItem::STATUS_UPDATE, true ],
			'not installed' => [ TranslationItem::STATUS_NOT_INSTALLED, false ],
		];
	}

	/**
	 * @dataProvider provide_installed_statuses
	 */
	public function test_is_installed_is_false_only_for_the_not_installed_status( string $status, bool $expected ): void {
		$item = $this->make_item( $status );

		$this->assertSame( $expected, $item->is_installed() );
	}

	/**
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public static function provide_installed_statuses(): array {
		return [
			'up to date'    => [ TranslationItem::STATUS_UP_TO_DATE, true ],
			'update'        => [ TranslationItem::STATUS_UPDATE, true ],
			'not installed' => [ TranslationItem::STATUS_NOT_INSTALLED, false ],
		];
	}

	private function make_item( string $status ): TranslationItem {
		return new TranslationItem(
			slug: 'woocommerce',
			name: 'WooCommerce',
			type: 'plugin',
			status: $status,
			file_count: 1,
			local_date: '',
			files: []
		);
	}
}
